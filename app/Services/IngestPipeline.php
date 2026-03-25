<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\IngestSignal;
use App\Settings\LlmSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Str;

class IngestPipeline
{
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
        $target = app()->isProduction() ? 'pro' : 'flash';
        return collect($models)->keys()->filter(fn($m) => str_contains(strtolower($m), $target))->sortDesc()->first() ?? 'gemini-1.5-flash';
    }

    public function getAvailableModels(): array
    {
        return Cache::remember('gemini_models_final', 3600, function () {
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
        $logs = $signal->processing_logs;
        if (!is_array($logs)) $logs = [];
        $logs[] = ['t' => now()->format('H:i:s'), 'm' => $message, 'type' => $type];
        $signal->processing_logs = $logs;
        $signal->save();
    }

    public function ask(string $step, ?Agent $agent, string $content, IngestSignal $signal, int $maxRetries = 3)
    {
        $model = $this->resolveModel($step, $agent);
        $model = str_replace('models/', '', $model);
        
        $this->log($signal, "📤 [PROMPT OUT] ({$model}): \n" . Str::limit($content, 200), 'warning');

        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            $this->log($signal, "❌ API KEY FEHLT!", 'error');
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $temp = (float) data_get($agent, 'soul_configuration.temperature', 0.2);

        $attempt = 0;
        while ($attempt < $maxRetries) {
            try {
                $response = Http::timeout(120)->post($url, [
                    'contents' => [['parts' => [['text' => $content]]]],
                    'generationConfig' => [
                        'temperature' => $temp
                    ]
                ]);

                if ($response->successful()) {
                    $resText = data_get($response->json(), 'candidates.0.content.parts.0.text');
                    
                    if ($resText === null) {
                        $this->log($signal, "⚠️ [RESPONSE IN]: Erfolgreich, aber Text ist NULL (Safety Filter?)", 'warning');
                    } else {
                        $this->log($signal, "📥 [RESPONSE IN]: \n" . Str::limit(trim($resText), 150), 'done');
                    }
                    return $resText;
                } 
                
                // --- 🛡️ DAS 429 RATE LIMIT SCHUTZSCHILD ---
                if ($response->status() === 429) {
                    $this->log($signal, "⏳ [RATE LIMIT] 429 Too Many Requests! KI macht 60 Sekunden Pause...", 'warning');
                    sleep(60); // Eine volle Minute warten, um den RPM-Zähler von Google zu nullen!
                    // WICHTIG: $attempt wird NICHT hochgezählt. Er probiert es unendlich oft, bis die Sperre weg ist.
                    continue; 
                }

                // Bei anderen Fehlern (500, 400) zählen wir den Versuch mit
                $this->log($signal, "⛔ [API ERROR] HTTP " . $response->status() . ": " . $response->body(), 'error');
                $attempt++;
                sleep(5 * $attempt);
                
            } catch (\Exception $e) {
                $this->log($signal, "💥 [HTTP CRASH]: " . $e->getMessage(), 'error');
                $attempt++;
                sleep(5);
            }
        }
        
        $this->log($signal, "🛑 [GIVE UP] Maximale Versuche ({$maxRetries}) erreicht. Gebe NULL zurück.", 'error');
        return null;
    }

   protected function extractPdfLocally(string $path, IngestSignal $signal): string
    {
        $this->log($signal, "⚙️ EXTRAKTION: Starte System-Level Tool (pdftotext)...", 'info');
        $output = [];
        $returnVar = -1;
        
        $cmd = "pdftotext -layout " . escapeshellarg($path) . " -";
        exec($cmd, $output, $returnVar);

        $text = "";

        if ($returnVar === 0 && count($output) > 0) {
            $text = implode("\n", $output);
            if (strlen(trim($text)) > 100 && !str_starts_with(trim($text), '%PDF')) {
                $this->log($signal, "✅ EXTRAKTION: pdftotext erfolgreich.", 'done');
            } else {
                $text = ""; // Reset, falls es doch nur Müll war
            }
        }

        if (empty($text)) {
            $this->log($signal, "⚠️ pdftotext gescheitert. Fallback auf Smalot PHP-Parser...", 'warning');
            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($path);
                $text = $pdf->getText();
                
                if (strlen(trim($text)) > 100 && !str_starts_with(trim($text), '%PDF')) {
                    $this->log($signal, "✅ EXTRAKTION: Smalot Parser erfolgreich.", 'done');
                } else {
                    $text = "";
                }
            } catch (\Throwable $t) {
                $this->log($signal, "❌ Smalot Parser gescheitert: " . $t->getMessage(), 'error');
            }
        }

        // --- NEU: DER ULTIMATIVE UTF-8 FILTER ---
        if (!empty($text)) {
            // 1. Erzwinge valides UTF-8 (wirft kaputte Bytes weg)
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            
            // 2. Entferne unsichtbare Control-Character (außer Linebreaks und Tabs)
            // Diese unsichtbaren Zeichen sind zu 99% der Auslöser für den Livewire JsonException Fehler
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
            
            return $text;
        }

        return "";
    }

    public function processWithRouting(IngestSignal $signal)
    {
        $this->log($signal, "🚀 SYSTEM: Initialisiere Boardroom-Prozess...", 'info');
        $signal->update(['status' => 'processing', 'processing_logs' => []]);

        // 1. ZUVERLÄSSIGE TEXT-EXTRAKTION
        if (empty($signal->raw_content) || str_contains($signal->raw_content, '%PDF')) {
            $this->log($signal, "📂 DATEN: Verarbeite Dokumente...", 'info');
            $allText = "";
            $media = $signal->getMedia('scouts');

            foreach ($media as $file) {
                $this->log($signal, "📄 Lade Datei: " . $file->file_name, 'info');
                $extractedText = $this->extractPdfLocally($file->getPath(), $signal);
                if (!empty($extractedText)) {
                    $allText .= $extractedText . "\n\n";
                }
            }
            
            $signal->raw_content = trim($allText);

            if (empty($signal->raw_content) || str_starts_with($signal->raw_content, '%PDF')) {
                $this->log($signal, "🛑 ABBRUCH: Lokale Extraktion komplett fehlgeschlagen.", 'error');
                $signal->update(['status' => 'cancelled', 'raw_content' => null]);
                return;
            }

            $signal->save();
        }

        // 2. SMART CHUNKING (Regex-Split anhand von Kapitel-Markern)
        $this->log($signal, "🧠 STRUKTUR: Suche nach Kapitel-Markierungen im Text...", 'info');
        
        $chapters = [];
        // Splittet den Text vor jeder Zeile, die mit "Chapter X" oder "Kapitel X" beginnt
        $chunks = preg_split('/(?=^\s*(?:Chapter|Kapitel)\s*\d+)/mi', $signal->raw_content);

        if (count($chunks) > 1) { 
            foreach ($chunks as $chunk) {
                if (strlen(trim($chunk)) < 150) continue; // Inhaltsverzeichnisse & Reste ignorieren
                
                // Wir holen uns die erste Zeile des gefundenen Chunks als Titel
                $lines = array_filter(explode("\n", trim($chunk)));
                $title = trim(reset($lines) ?: 'Unbekanntes Kapitel');
                $title = Str::limit(preg_replace('/[^A-Za-z0-9\s\:\-\_]/', '', $title), 80);

                $chapters[] = [
                    'title' => $title,
                    'content' => trim($chunk)
                ];
            }
            $this->log($signal, "✅ Struktur erkannt: " . count($chapters) . " echte Kapitel extrahiert.", 'done');
        } 
        
        // Fallback: Sollte ein Dokument keine Calibre-typischen Marker haben
        if (empty($chapters)) {
            $this->log($signal, "⚠️ Keine Kapitel-Marker gefunden. Erstelle 15k-Blöcke...", 'warning');
            
            // Sauberer Split an Wortgrenzen (Leerzeichen), damit die KI nicht verwirrt wird
            $wrapped = wordwrap($signal->raw_content, 15000, "|||CHUNK|||");
            $rawChunks = explode("|||CHUNK|||", $wrapped);
            
            foreach($rawChunks as $i => $c) {
                $chapters[] = [
                    'title' => "Dossier Abschnitt " . ($i+1), 
                    'content' => trim($c)
                ];
            }
        }

        $this->process($signal, $chapters);
    }

    public function process(IngestSignal $signal, array $chapters)
    {
        $agents = Agent::where('is_active', true)->get();
        $signal->update(['master_blob_draft' => ""]); 

        if ($agents->isEmpty()) {
            $this->log($signal, "❌ FATAL: Keine aktiven Agenten in der Datenbank gefunden!", 'error');
            return;
        }

        foreach ($chapters as $index => $chapter) {
            $title = $chapter['title'] ?? "Abschnitt " . ($index + 1);
            $content = $chapter['content'] ?? "";

            $this->log($signal, "📘 KAPITEL: '{$title}' (" . strlen($content) . " Zeichen)", 'info');
            $this->appendToBlob($signal, "# {$title}\n\n");

            // --- MODERATOR DELEGATION ---
            $agentList = $agents->pluck('name')->implode(', ');
            $moderatorPrompt = "Kapitel: '{$title}'\n\nInhalt: '" . Str::limit($content, 1000) . "'\n\nVerfügbare Experten: {$agentList}.\nWer muss diesen Teil zwingend analysieren? Antworte NUR mit den exakten Namen (Komma-getrennt) oder 'NONE', falls völlig irrelevant.";
            
            $relevantAgents = $this->ask('analysis', null, $moderatorPrompt, $signal);
            
            if ($relevantAgents === null) {
                $this->log($signal, "⏭️ Überspringe: Moderator gab NULL zurück (API-Fehler).");
                continue;
            }

            if (str_contains(strtoupper($relevantAgents), 'NONE')) {
                $this->log($signal, "⏭️ Überspringe: Moderator hat explizit 'NONE' gesagt.");
                continue;
            }

            $this->log($signal, "📢 DELEGATION VERSTANDEN: -> {$relevantAgents}", 'done');

            $assigned = false;
            foreach ($agents as $agent) {
                if (str_contains(strtolower($relevantAgents), strtolower($agent->name))) {
                    $assigned = true;
                    $this->log($signal, "🕵️ ANALYSE: {$agent->name} wird aktiv...", 'info');
                    
                    $role = $agent->system_prompt ?? $agent->bio ?? 'Experte';
                    $prompt = "Du bist {$agent->name}. Rolle: {$role}. Analysiere dieses Kapitel tiefgreifend für das Board und gib dein CEO-Votum ab:\n\n{$content}";
                    
                    $votum = $this->ask('analysis', $agent, $prompt, $signal);

                    if ($votum) {
                        $this->appendToBlob($signal, "## Votum {$agent->name}\n\n{$votum}\n\n---\n\n");
                        $this->log($signal, "🧠 SYSTEM: Votum von {$agent->name} gesichert.", 'done');
                    }
                }
            }

            if (!$assigned) {
                $this->log($signal, "⚠️ WARNUNG: Moderator sagte '{$relevantAgents}', aber kein Agent-Name hat gematched! (Tippfehler der KI?)", 'warning');
            }
        }

        $signal->update(['status' => 'done']);
        $this->log($signal, "🏁 FINISH: Board Meeting abgeschlossen.", 'done');
    }

    protected function appendToBlob(IngestSignal $signal, string $text)
    {
        $signal->refresh();
        $signal->update(['master_blob_draft' => ($signal->master_blob_draft ?? "") . $text]);
    }
}
