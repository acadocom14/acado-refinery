<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Asset;
use App\Models\IngestSignal;
use App\Settings\LlmSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Str;

class IngestPipeline
{
    /**
     * Zentrales Logging für Terminal (Log-File) und Dashboard (DB).
     */
    protected function log($target, string $message, string $type = 'info')
    {
        $timestamp = now()->format('H:i:s');
        $prefix = match($type) {
            'info'    => " [SYSTEM] ",
            'warning' => "![ACTION] ",
            'done'    => ">>[OK]    ",
            'error'   => "##[ERROR] ",
            default   => " [LOG]    ",
        };

        // 1. Immer ins Laravel-System-Log für PowerShell tail/Get-Content
        \Log::info("{$timestamp}{$prefix}{$message}");

        // 2. In die Datenbank für das Refinery-Terminal (Live-Poll)
        if ($target instanceof Asset) {
            $logs = $target->processing_logs;
            if (!is_array($logs)) {
                $logs = [];
            }
            $logs[] = ['t' => $timestamp, 'm' => $message, 'type' => $type];
            $target->update(['processing_logs' => array_slice($logs, -100)]);
        } 
        elseif ($target instanceof IngestSignal && $target->exists) {
            $logs = $target->processing_logs ?? [];
            $logs[] = ['t' => $timestamp, 'm' => $message, 'type' => $type];
            $target->update(['processing_logs' => $logs]);
        }
    }

    /**
     * Resolves the appropriate model for the current task.
     */
    public function resolveModel(string $step, ?Agent $agent = null): string
    {
        $settings = app(LlmSettings::class);
        $agentModel = data_get($agent, 'soul_configuration.model');
        
        if ($step === 'analysis' && !empty($agentModel)) return $agentModel;

        $stepMap = [
            'extraction'  => $settings->model_extraction,
            'analysis'    => $settings->model_analysis,
            'association' => $settings->model_association,
        ];
        
        return !empty($stepMap[$step]) ? $stepMap[$step] : $this->getIntelligentFallback();
    }

    protected function getIntelligentFallback(): string
    {
        $models = $this->getAvailableModels();
        $availableKeys = collect($models)->keys();
        if ($availableKeys->contains('gemini-3.1-flash-lite')) return 'gemini-3.1-flash-lite';
        if ($availableKeys->contains('gemini-3-flash')) return 'gemini-3-flash';
        return 'gemini-2.5-flash';
    }

    public function getAvailableModels(): array
    {
        return Cache::remember('gemini_models_v2026', 3600, function () {
            $apiKey = config('services.gemini.key');
            $response = Http::get("https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}");
            if ($response->failed()) return ['gemini-1.5-flash' => 'Fallback: Flash 1.5'];
            
            return collect($response->json()['models'])
                ->filter(fn($m) => in_array('generateContent', $m['supportedGenerationMethods']))
                ->mapWithKeys(fn($m) => [str_replace('models/', '', $m['name']) => $m['displayName']])
                ->toArray();
        });
    }

    public function ask(string $step, ?Agent $agent, string $content, $logTarget, int $maxRetries = 3)
    {
        $model = $this->resolveModel($step, $agent);
        $model = str_replace('models/', '', $model);
        
        $this->log($logTarget, "📤 [PROMPT] ({$model}): " . Str::limit($content, 250), 'warning');

        $apiKey = config('services.gemini.key');
        if (!$apiKey) return null;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $temp = (float) data_get($agent, 'soul_configuration.temperature', 0.2);

        $attempt = 0;
        while ($attempt < $maxRetries) {
            try {
                $response = Http::timeout(120)->post($url, [
                    'contents' => [['parts' => [['text' => $content]]]],
                    'generationConfig' => ['temperature' => $temp]
                ]);

                if ($response->successful()) {
                    $resText = data_get($response->json(), 'candidates.0.content.parts.0.text');
                    $this->log($logTarget, "📥 [RESPONSE]: " . Str::limit(trim($resText), 150), 'done');
                    return $resText;
                } 
                
                if ($response->status() === 429) {
                    $this->log($logTarget, "⏳ [QUOTA EXCEEDED] Pausing for 60s...", 'warning');
                    sleep(60); 
                    continue; 
                }

                $this->log($logTarget, "⛔ [API ERROR] HTTP " . $response->status(), 'error');
                $attempt++;
                sleep(5 * $attempt);
            } catch (\Exception $e) {
                $this->log($logTarget, "💥 [FATAL CRASH]: " . $e->getMessage(), 'error');
                $attempt++;
                sleep(5);
            }
        }
        return null;
    }

    public function askStructured(string $step, ?Agent $agent, string $content, array $jsonSchema, $logTarget, int $maxRetries = 3)
    {
        $model = $this->resolveModel($step, $agent);
        $model = str_replace('models/', '', $model);
        $apiKey = config('services.gemini.key');
        if (!$apiKey) return null;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $temp = 0.1; 

        $attempt = 0;
        while ($attempt < $maxRetries) {
            try {
                $response = Http::timeout(120)->post($url, [
                    'contents' => [['parts' => [['text' => $content]]]],
                    'generationConfig' => [
                        'temperature' => $temp,
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $jsonSchema
                    ]
                ]);

                if ($response->successful()) {
                    return data_get($response->json(), 'candidates.0.content.parts.0.text');
                } 
                
                if ($response->status() === 429) {
                    $this->log($logTarget, "⏳ [QUOTA EXCEEDED] Pausing for 60s...", 'warning');
                    sleep(60); 
                    continue; 
                }

                $this->log($logTarget, "⛔ [API ERROR] HTTP " . $response->status(), 'error');
                $attempt++;
                sleep(5 * $attempt);
            } catch (\Exception $e) {
                $this->log($logTarget, "💥 [FATAL CRASH]: " . $e->getMessage(), 'error');
                $attempt++;
                sleep(5);
            }
        }
        return null;
    }

    /**
     * DER HAUPTMOTOR FÜR DIE ASSET-FABRIK (V3.1 - AUTONOMOUS FEEDBACK LOOP)
     */
    public function processAssetPipeline(Asset $asset)
    {
        $separator = str_repeat("=", 60);
        $this->log($asset, $separator);
        $this->log($asset, "INITIALIZING ACADO ASSET REFINERY v3.1 (AGENTIC LOOP)", 'info');
        $this->log($asset, "TARGET ASSET: " . strtoupper($asset->name), 'info');

        if (empty($asset->trigger_content)) {
            $this->log($asset, "CRITICAL: NO TRIGGER CONTENT FOUND. ABORTING.", 'error');
            return $asset->update(['status' => 'failed']);
        }

        $this->log($asset, "TRIGGER RECEIVED: " . Str::limit($asset->trigger_content, 50), 'done');
        $this->log($asset, $separator);

        $asset->update(['status' => 'processing']);
        $stages = $asset->pipelineStages()->orderBy('stage_level', 'asc')->get();

        if ($stages->isEmpty()) {
            $this->log($asset, "CRITICAL: NO PIPELINE STAGES DEFINED", 'error');
            return $asset->update(['status' => 'failed']);
        }

        $allSignals = $asset->ingestSignals ?? collect();

        // =====================================================================
        // 🔄 STATE MANAGEMENT FÜR DEN LOOPBACK
        // =====================================================================
        $stageOutputs = [];
        $stageOutputs[-1] = $asset->trigger_content; 
    
        $rejectionFeedback = ""; 
        $loopbackCount = 0;      
        $maxLoopbacks = 3;       

        for ($i = 0; $i < count($stages); $i++) {
            $stage = $stages[$i];
            $chainedOutput = $stageOutputs[$i - 1]; 

            $this->log($asset, "--- LOADING STAGE {$stage->stage_level}: " . strtoupper($stage->name) . " ---", 'info');
    
            $activeAgent = $stage->agents->first();
            if ($activeAgent) {
                $this->log($asset, "DEPLOYING AGENT: {$activeAgent->name}", 'warning');
            }

            // =====================================================================
            // 🛰️ KNOWLEDGE RETRIEVAL ÜBER 'CATEGORY' (v3.4)
            // =====================================================================
            $allSignals = collect($asset->ingestSignals ?? []);

            $knowledgeBase = match($stage->signal_filter) {
                // Wir suchen in der Spalte 'category'
                'tech'  => $allSignals->filter(function($s) {
                    $c = strtolower($s->category ?? '');
                    return str_contains($c, 'tech') || str_contains($c, 'specialist') || str_contains($c, 'fach');
                })->pluck('master_blob_draft')->filter()->implode("\n\n---\n\n"),

                'biz'   => $allSignals->filter(function($s) {
                    $c = strtolower($s->category ?? '');
                    return str_contains($c, 'biz') || str_contains($c, 'business') || str_contains($c, 'wirtschaft');
                })->pluck('master_blob_draft')->filter()->implode("\n\n---\n\n"),

                'philo' => $allSignals->filter(function($s) {
                    $c = strtolower($s->category ?? '');
                    return str_contains($c, 'philo') || str_contains($c, 'poes') || str_contains($c, 'verse') || str_contains($c, 'lyrik');
                })->pluck('master_blob_draft')->filter()->implode("\n\n---\n\n"),

                'all'   => $allSignals->pluck('master_blob_draft')->filter()->implode("\n\n---\n\n"),
                default => "",
            };

            // Korrigiertes Debug-Logging
            $foundCount = $allSignals->filter(function($s) use ($stage) {
                $c = strtolower($s->category ?? '');
                if($stage->signal_filter == 'tech') return str_contains($c, 'tech') || str_contains($c, 'specialist');
                if($stage->signal_filter == 'biz') return str_contains($c, 'biz') || str_contains($c, 'business');
                return $s->category == $stage->signal_filter;
            })->count();

            $this->log($asset, "DEBUG: Total Signals linked: " . $allSignals->count(), 'info');
            $this->log($asset, "DEBUG: Category-Matches for '{$stage->signal_filter}': " . $foundCount, 'info');

            $kbLength = strlen($knowledgeBase);
            if ($kbLength > 0) {
                $this->log($asset, "📚 KNOWLEDGE INJECTED: " . number_format($kbLength, 0, ',', '.') . " Chars", 'done');
            } else {
                $this->log($asset, "📭 NO KNOWLEDGE INJECTED (Filter: {$stage->signal_filter})", 'warning');
            }

      

            $knowledgeChunks = $this->splitIntoChunks($knowledgeBase, 150000);
            if (empty($knowledgeChunks)) {
                $knowledgeChunks = ['']; 
            }

            $responses = [];
            $stageSuccess = true; 

            foreach ($knowledgeChunks as $idx => $chunk) {
                $chunkLabel = count($knowledgeChunks) > 1 ? " (Part " . ($idx+1) . "/" . count($knowledgeChunks) . ")" : "";
                $this->log($asset, "Processing Execution{$chunkLabel}...", 'info');
        
                $knowledgeInject = !empty($chunk) ? "### REFERENCE KNOWLEDGE (Expert Library) ###\n{$chunk}" : "";

                $feedbackInject = "";
                if (!empty($rejectionFeedback)) {
                    $feedbackInject = "\n\n=========================================\n" .
                                      "⚠️ URGENT REVISION REQUIRED (FEEDBACK FROM LATER STAGE):\n" .
                                      "Your previous output was rejected. You MUST fix these issues:\n" .
                                      "REASON: {$rejectionFeedback}\n" .
                                      "=========================================\n";
                }

                $prompt = "{$stage->fixed_prompt_template}\n\n" .
                          "DIRECTIVE: {$stage->custom_prompt_directive}\n" .
                          $feedbackInject . "\n" .
                          "### SUBJECT TO ANALYZE (Current Progress) ###\n" .
                          "{$chainedOutput}\n\n" .
                          $knowledgeInject;
        
                $res = $this->ask('analysis', $activeAgent, $prompt, $asset);
            
                if ($res) {
                    $cleanJson = trim(preg_replace('/^```json\s*(.*?)\s*```$/is', '$1', $res));
                    $parsedData = json_decode($cleanJson, true);

                    if (json_last_error() === JSON_ERROR_NONE && isset($parsedData['gatekeeper_decision'])) {
                    
                        $decision = strtoupper($parsedData['gatekeeper_decision']);
                        $reason = $parsedData['reason'] ?? 'Sicherheitsrisiko durch Gatekeeper erkannt.';

                        if ($decision === 'BLOCK' || $decision === 'RED') {
                            if ($loopbackCount < $maxLoopbacks && $i > 0) {
                                $loopbackCount++;
                                $rejectionFeedback = $reason; 
                            
                                $this->log($asset, "🔄 [GATEKEEPER REJECTED] Bouncing back to Stage " . ($stages[$i-1]->stage_level) . " (Attempt {$loopbackCount}/{$maxLoopbacks}). Reason: " . $reason, 'error');
                            
                                $stageSuccess = false;
                                $i -= 2; 
                                break; 
                            } else {
                                $asset->update([
                                    'status' => 'failed',
                                    'final_content' => "## 🛑 FATAL PIPELINE BLOCK\n**Stage:** {$stage->name}\n**Grund:** {$reason}\n*(Abgebrochen nach {$loopbackCount} Korrekturversuchen)*"
                                ]);
                                $this->log($asset, "💀 [GATEKEEPER FATAL] Max retries reached. Pipeline terminated.", 'error');
                                return; 
                            }
                        }

                        if ($decision === 'WARN' || $decision === 'YELLOW') {
                            $this->log($asset, "⚠️ [GATEKEEPER WARNING] " . $reason, 'warning');
                        }

                        $res = $parsedData['safe_content'] ?? '';
                    }

                    if (!empty(trim($res))) {
                        $responses[] = $res;
                    }
                }
        
                // Cool-down nur innerhalb der Chunks
                if (count($knowledgeChunks) > 1) {
                    $this->log($asset, "Enforcing API cool-down...", 'info');
                    sleep(4);
                }
            }

            if (!$stageSuccess) {
                continue; 
            }

            $rejectionFeedback = ""; 
            $loopbackCount = 0;      
        
            $isJson = Str::contains(strtolower($stage->fixed_prompt_template), 'json');
            $finalStageOutput = $isJson ? "[\n".implode(",\n", $responses)."\n]" : implode("\n\n", $responses);

            // 🛑 VOID CHECK
            if (empty(trim($finalStageOutput))) {
                $this->log($asset, "💀 FATAL: Stage {$stage->stage_level} produced EMPTY output.", 'error');
                return $asset->update(['status' => 'failed', 'final_content' => "## 🛑 PIPELINE KILLED\nStage '{$stage->name}' hat ein leeres Ergebnis geliefert."]);
            }
        
            $stageOutputs[$i] = $finalStageOutput;
    
            $allOutputs = $asset->pipeline_outputs ?? [];
            $allOutputs["stage_{$stage->stage_level}"] = [
                'name' => $stage->name, 
                'content' => $finalStageOutput
            ];
            $asset->update(['pipeline_outputs' => $allOutputs]);

            $this->log($asset, "STAGE {$stage->stage_level} COMPLETED.", 'done');
            $this->log($asset, str_repeat("-", 40));
        }

        $this->log($asset, "ALL STAGES PROCESSED. COMPILING FINAL MANIFEST...", 'done');
        $this->log($asset, $separator);

        $final = ($asset->pipeline_outputs['stage_4']['content'] ?? '') . "\n\n" . ($asset->pipeline_outputs['stage_5']['content'] ?? '');
        $asset->update(['status' => 'active', 'final_content' => $final]);
    }

    /**
     * ALTE INGEST-LOGIK (BOARDROOM)
     */
    public function processWithRouting(IngestSignal $signal)
    {
        $this->log($signal, "🚀 BOARDROOM: Initializing process...", 'info');
        $signal->update(['status' => 'processing', 'processing_logs' => []]);

        if (empty($signal->raw_content) || str_contains($signal->raw_content, '%PDF')) {
            $allText = "";
            $media = $signal->getMedia('scouts');
            foreach ($media as $file) {
                $extracted = $this->extractPdfLocally($file->getPath(), $signal);
                if (!empty($extracted)) $allText .= $extracted . "\n\n";
            }
            $signal->raw_content = trim($allText);
            if (empty($signal->raw_content)) {
                $this->log($signal, "🛑 ABORT: No content found.", 'error');
                return $signal->update(['status' => 'cancelled']);
            }
            $signal->save();
        }

        $chunks = preg_split('/(?=^\s*(?:Chapter|Kapitel)\s*\d+)/mi', $signal->raw_content);
        $chapters = [];
        if (count($chunks) <= 3) {
            $rawChunks = explode("|||", wordwrap($signal->raw_content, 15000, "|||"));
            foreach($rawChunks as $i => $c) { $chapters[] = ['title' => "Section " . ($i+1), 'content' => trim($c)]; }
        } else {
            foreach ($chunks as $chunk) {
                if (strlen(trim($chunk)) < 150) continue;
                $lines = array_filter(explode("\n", trim($chunk)));
                $chapters[] = ['title' => Str::limit(reset($lines), 60), 'content' => trim($chunk)];
            }
        }

        $this->process($signal, $chapters);
    }

    public function process(IngestSignal $signal, array $chapters)
    {
        $agents = Agent::where('is_active', true)->get();
        $signal->update(['master_blob_draft' => ""]); 

        $agentDefinitions = $agents->map(function ($agent) {
            $role = $agent->soul ?? 'Domain Expert';
            $angles = collect($agent->perspectives)->pluck('angle')->filter()->implode(', ');
            $topics = !empty($angles) ? "\n  Focus: {$angles}." : "";
            $instr = $agent->system_prompt ? "\n  Instructions: {$agent->system_prompt}" : "";
            return "- **{$agent->name}** (Role: {$role}){$topics}{$instr}";
        })->implode("\n\n");

        $jsonSchemaProperties = [];
        foreach ($agents as $agent) {
            $key = strtolower(str_replace(' ', '_', $agent->name)) . '_notes';
            $jsonSchemaProperties[$key] = ['type' => 'string', 'description' => "Insights from {$agent->name}"];
        }

        foreach ($chapters as $index => $chapter) {
            $prompt = "Analyze chunk simultaneously:\nEXPERTS:\n{$agentDefinitions}\n\nCHUNK:\n" . Str::limit($chapter['content'], 25000);
            $jsonResponse = $this->askStructured('analysis', null, $prompt, ['type' => 'object', 'properties' => $jsonSchemaProperties], $signal);

            if ($jsonResponse) {
                $parsedData = json_decode($jsonResponse, true);
                $chapterBuffer = "# " . ($chapter['title'] ?? 'Section') . "\n\n";
                foreach ($agents as $agent) {
                    $key = strtolower(str_replace(' ', '_', $agent->name)) . '_notes';
                    if (isset($parsedData[$key]) && $parsedData[$key] !== 'NULL') {
                        $chapterBuffer .= "## {$agent->name}'s Brief\n\n{$parsedData[$key]}\n\n---\n\n";
                    }
                }
                $signal->update(['master_blob_draft' => $signal->master_blob_draft . $chapterBuffer]);
            }
            sleep(4);
        }
        $signal->update(['status' => 'done']);
    }

    protected function extractPdfLocally(string $path, IngestSignal $signal): string
    {
        $output = []; $returnVar = -1;
        exec("pdftotext -layout " . escapeshellarg($path) . " -", $output, $returnVar);
        $text = ($returnVar === 0) ? implode("\n", $output) : "";
        if (empty($text)) {
            try { $text = (new Parser())->parseFile($path)->getText(); } catch (\Throwable $t) {}
        }
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', mb_convert_encoding($text, 'UTF-8', 'UTF-8')));
    }


    /**
     * Zerlegt den massiven Wissenstext in Chunks, 
     * damit Gemini nicht überladen wird.
     */
    protected function splitIntoChunks(string $text, int $maxChars = 150000): array
    {
        if (empty(trim($text))) {
            return [];
        }

        // Wir nutzen eine saubere Zerlegung an Wortgrenzen oder Absätzen
        // 150.000 Zeichen sind für Gemini 2.5 Flash ein Kinderspiel.
        $chunks = explode("|||", wordwrap($text, $maxChars, "|||"));
        
        return array_filter(array_map('trim', $chunks));
    }

}
