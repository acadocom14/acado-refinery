<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\IngestSignal;
use App\Settings\LlmSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class IngestPipeline
{
    public function resolveModel(string $step, ?Agent $agent = null): string
    {
        $settings = app(LlmSettings::class);

        // 1. Priorität: Spezielles Modell pro Agent (NULL-SAFE)
        $agentModel = data_get($agent, 'soul_configuration.model');
        if ($step === 'analysis' && !empty($agentModel)) {
            return $agentModel;
        }

        $stepMap = [
            'extraction'  => $settings->model_extraction,
            'analysis'    => $settings->model_analysis,
            'association' => $settings->model_association,
        ];
        if (!empty($stepMap[$step])) return $stepMap[$step];

        if (!$settings->use_intelligent_fallback && $settings->global_default_model) {
            return $settings->global_default_model;
        }

        return $this->getIntelligentFallback();
    }

    protected function getIntelligentFallback(): string
    {
        $models = $this->getAvailableModels();
        $target = app()->isProduction() ? 'pro' : 'flash';
        
        return collect($models)
            ->keys()
            ->filter(fn($m) => str_contains(strtolower($m), $target))
            ->sortDesc()
            ->first() ?? 'gemini-1.5-flash';
    }

    public function getAvailableModels(): array
    {
        return Cache::remember('gemini_models', 3600, function () {
            $apiKey = config('services.gemini.key');
            $response = Http::get("https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}");
            
            if ($response->failed()) return ['gemini-1.5-flash' => 'Fallback: Flash 1.5'];

            return collect($response->json()['models'])
                ->filter(fn($m) => in_array('generateContent', $m['supportedGenerationMethods']))
                ->mapWithKeys(fn($m) => [str_replace('models/', '', $m['name']) => $m['displayName']])
                ->toArray();
        });
    }

    protected function log(IngestSignal $signal, string $message, string $type = 'info')
    {
        // Sicherstellen, dass wir ein Array haben, bevor wir pushen
        $logs = $signal->processing_logs;
        if (!is_array($logs)) $logs = [];
        
        $logs[] = ['t' => now()->format('H:i:s'), 'm' => $message, 'type' => $type];
        
        $signal->processing_logs = $logs;
        $signal->save();
    }

    protected function checkAbort(IngestSignal $signal): bool
    {
        $signal->refresh();
        if ($signal->status === 'cancelled') {
            $this->log($signal, "🛑 Verarbeitung abgebrochen.", 'error');
            return true;
        }
        return false;
    }

    public function ask(string $step, ?Agent $agent, string $content, IngestSignal $signal, int $maxRetries = 3)
    {
        $model = $this->resolveModel($step, $agent);
        $model = str_replace('models/', '', $model);

        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            $this->log($signal, "🛑 API Key fehlt!", 'error');
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $attempt = 0;
        
        // NULL-SAFE Temperatur
        $temp = (float) data_get($agent, 'soul_configuration.temperature', 0.2);

        while ($attempt < $maxRetries) {
            try {
                $response = Http::timeout(60)->post($url, [
                    'contents' => [['parts' => [['text' => $content]]]],
                    'generationConfig' => ['temperature' => $temp]
                ]);

                if ($response->successful()) {
                    sleep(5); // RPM Bremse
                    return data_get($response->json(), 'candidates.0.content.parts.0.text');
                }

                $attempt++;
                if ($response->status() === 429) {
                    sleep(5 * $attempt);
                    continue;
                }
                sleep(3);
            } catch (\Exception $e) {
                $attempt++;
                sleep(3);
            }
        }
        return null;
    }

    public function processWithRouting(IngestSignal $signal)
    {
        $this->log($signal, "🚀 Pipeline gestartet. Bereite Dokument vor...");
        $signal->update(['status' => 'processing', 'processing_logs' => []]);

        $content = $signal->raw_content ?? "Kein Inhalt gefunden.";
        $chapters = [['title' => 'Gesamtes Dokument', 'content' => $content]];

        $this->process($signal, $chapters);
    }

    public function process(IngestSignal $signal, array $chapters)
    {
        $agents = Agent::where('is_active', true)->get();
        if ($agents->isEmpty()) {
            $this->log($signal, "❌ Keine aktiven Agenten!", 'error');
            $signal->update(['status' => 'cancelled']);
            return;
        }

        $fullMasterBlob = "";

        foreach ($chapters as $index => $chapter) {
            if ($this->checkAbort($signal)) return;

            $title = $chapter['title'] ?? "Kapitel " . ($index + 1);
            $this->log($signal, "🎤 Moderator prüft: {$title}...");
            
            $moderatorPrompt = "Wer von diesen Experten (" . $agents->pluck('name')->implode(', ') . ") muss das Kapitel '{$title}' analysieren? Antworte NUR mit Namen oder NONE.";
            $relevantAgents = $this->ask('analysis', null, $moderatorPrompt, $signal);

            if (str_contains(strtoupper($relevantAgents ?? ''), 'NONE')) {
                $this->log($signal, "⏭️ Überspringe Kapitel.");
                continue;
            }

            $chapterContentBuffer = "";
            foreach ($agents as $agent) {
                if ($this->checkAbort($signal)) return;
                if (!str_contains(strtolower($relevantAgents ?? ''), strtolower($agent->name))) continue;

                $this->log($signal, "🕵️ {$agent->name} liest...");
                
                // Wir nutzen 'soul' oder 'system_prompt', was auch immer da ist
                $roleDesc = $agent->soul ?? $agent->system_prompt ?? 'Experte';
                $prompt = "Du bist {$agent->name}. Deine Rolle: {$roleDesc}. Analysiere: {$chapter['content']}";
                
                $excerpt = $this->ask('analysis', $agent, $prompt, $signal);

                if ($excerpt && !str_contains(strtoupper($excerpt), 'SKIP')) {
                    $chapterContentBuffer .= "**Votum {$agent->name}:**\n{$excerpt}\n\n";
                    $this->log($signal, "✅ Votum von {$agent->name} erfasst.");
                }
            }
            
            if (!empty(trim($chapterContentBuffer))) {
                $fullMasterBlob .= "## {$title}\n\n" . $chapterContentBuffer . "---\n\n";
            }
        }

        $signal->update([
            'master_blob_draft' => ltrim($fullMasterBlob),
            'status' => 'done'
        ]);
        $this->log($signal, "🏁 Pipeline abgeschlossen.", 'done');
    }
}
