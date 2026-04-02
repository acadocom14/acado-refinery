<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\IngestSignal;
use App\Settings\LlmSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Str;

/**
 * IngestPipeline handles the multi-step processing of documents
 * using local extraction and LLM-based analysis.
 */
class IngestPipeline
{
    /**
     * Resolves the appropriate model for the current task.
     */
    public function resolveModel(string $step, ?Agent $agent = null): string
    {
        $settings = app(LlmSettings::class);
        $agentModel = data_get($agent, 'soul_configuration.model');
        
        // Priority: Agent-specific model > Global settings > Fallback
        if ($step === 'analysis' && !empty($agentModel)) return $agentModel;

        $stepMap = [
            'extraction'  => $settings->model_extraction,
            'analysis'    => $settings->model_analysis,
            'association' => $settings->model_association,
        ];
        
        return !empty($stepMap[$step]) ? $stepMap[$step] : $this->getIntelligentFallback();
    }

    /**
     * Provides a smart fallback model based on availability and capability.
     */
    protected function getIntelligentFallback(): string
    {
        $models = $this->getAvailableModels();
        $availableKeys = collect($models)->keys();
        
        // Priority: Latest generation > Lite version > Legacy Flash
        if ($availableKeys->contains('gemini-3.1-flash-lite')) return 'gemini-3.1-flash-lite';
        if ($availableKeys->contains('gemini-3-flash')) return 'gemini-3-flash';

        return 'gemini-2.5-flash';
    }

    /**
     * Fetches available models from the Google AI Gateway.
     */
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

    /**
     * Logs processing steps into the database for real-time streaming.
     */
    protected function log(IngestSignal $signal, string $message, string $type = 'info')
    {
        $logs = $signal->processing_logs;
        if (!is_array($logs)) $logs = [];
        $logs[] = ['t' => now()->format('H:i:s'), 'm' => $message, 'type' => $type];
        $signal->processing_logs = $logs;
        $signal->save();
    }

    /**
     * Central LLM gateway with built-in rate limit handling.
     */
    public function ask(string $step, ?Agent $agent, string $content, IngestSignal $signal, int $maxRetries = 3)
    {
        $model = $this->resolveModel($step, $agent);
        $model = str_replace('models/', '', $model);
        
        $this->log($signal, "📤 [PROMPT] ({$model}): " . Str::limit($content, 250), 'warning');

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
                    $this->log($signal, "📥 [RESPONSE]: " . Str::limit(trim($resText), 150), 'done');
                    return $resText;
                } 
                
                // 429 Rate Limit - Wait for quota reset
                if ($response->status() === 429) {
                    $this->log($signal, "⏳ [QUOTA EXCEEDED] Pausing for 60s...", 'warning');
                    sleep(60); 
                    continue; 
                }

                $this->log($signal, "⛔ [API ERROR] HTTP " . $response->status(), 'error');
                $attempt++;
                sleep(5 * $attempt);
            } catch (\Exception $e) {
                $this->log($signal, "💥 [FATAL CRASH]: " . $e->getMessage(), 'error');
                $attempt++;
                sleep(5);
            }
        }
        return null;
    }

    /**
     * Central LLM gateway for Structured Output (JSON).
     */
    public function askStructured(string $step, ?Agent $agent, string $content, array $jsonSchema, IngestSignal $signal, int $maxRetries = 3)
    {
        $model = $this->resolveModel($step, $agent);
        $model = str_replace('models/', '', $model);

        $apiKey = config('services.gemini.key');
        if (!$apiKey) return null;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        
        // Bei JSON-Extraktion wollen wir extrem deterministische Antworten (Temperature nahe 0)
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
                    $this->log($signal, "⏳ [QUOTA EXCEEDED] Pausing for 60s...", 'warning');
                    sleep(60); 
                    continue; 
                }

                $this->log($signal, "⛔ [API ERROR] HTTP " . $response->status(), 'error');
                $attempt++;
                sleep(5 * $attempt);
            } catch (\Exception $e) {
                $this->log($signal, "💥 [FATAL CRASH]: " . $e->getMessage(), 'error');
                $attempt++;
                sleep(5);
            }
        }
        return null;
    }

    /**
     * Extracts text using local OS tools or PHP fallbacks.
     */
    protected function extractPdfLocally(string $path, IngestSignal $signal): string
    {
        $this->log($signal, "⚙️ EXTRACTION: Initializing pdftotext...", 'info');
        $output = [];
        $returnVar = -1;
        
        $cmd = "pdftotext -layout " . escapeshellarg($path) . " -";
        exec($cmd, $output, $returnVar);

        $text = "";
        if ($returnVar === 0 && count($output) > 0) {
            $text = implode("\n", $output);
        }

        if (empty($text)) {
            $this->log($signal, "🩹 FALLBACK: Using Smalot PHP Parser...", 'warning');
            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($path);
                $text = $pdf->getText();
            } catch (\Throwable $t) {
                $this->log($signal, "❌ EXTRACTION FAILED: " . $t->getMessage(), 'error');
            }
        }

        // Clean output for JSON compatibility (UTF-8 Sanitization)
        if (!empty($text)) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
            return trim($text);
        }

        return "";
    }

    /**
     * Entry point for the boardroom workflow.
     */
    public function processWithRouting(IngestSignal $signal)
    {
        $this->log($signal, "🚀 BOARDROOM: Initializing process...", 'info');
        $signal->update(['status' => 'processing', 'processing_logs' => []]);

        // 1. DATA EXTRACTION
        if (empty($signal->raw_content) || str_contains($signal->raw_content, '%PDF')) {
            $allText = "";
            $media = $signal->getMedia('scouts');

            foreach ($media as $file) {
                $this->log($signal, "📄 Processing: " . $file->file_name, 'info');
                $extracted = $this->extractPdfLocally($file->getPath(), $signal);
                if (!empty($extracted)) $allText .= $extracted . "\n\n";
            }
            
            $signal->raw_content = trim($allText);
            if (empty($signal->raw_content)) {
                $this->log($signal, "🛑 ABORT: No readable content found.", 'error');
                $signal->update(['status' => 'cancelled']);
                return;
            }
            $signal->save();
        }

        // 2. SMART CHUNKING
        $this->log($signal, "🧠 STRUCTURE: Segmenting content by chapter...", 'info');
        $chapters = [];
        $chunks = preg_split('/(?=^\s*(?:Chapter|Kapitel)\s*\d+)/mi', $signal->raw_content);

        if (count($chunks) > 1) { 
            foreach ($chunks as $chunk) {
                if (strlen(trim($chunk)) < 150) continue; 
                $lines = array_filter(explode("\n", trim($chunk)));
                $title = Str::limit(preg_replace('/[^A-Za-z0-9\s]/', '', reset($lines)), 60);
                $chapters[] = ['title' => $title, 'content' => trim($chunk)];
            }
            $this->log($signal, "✅ Success: " . count($chapters) . " chapters identified.", 'done');
        } 
        
        // MODIFICATION 1: Smart Chunking Fallback (If 3 or fewer chapters found)
        if (count($chapters) <= 3) {
            $this->log($signal, "⚠️ Too few markers found. Forcing fixed 15k-wordwrap split.", 'warning');
            $chapters = []; // Reset array
            $rawChunks = explode("|||", wordwrap($signal->raw_content, 15000, "|||"));
            foreach($rawChunks as $i => $c) {
                $chapters[] = ['title' => "Section " . ($i+1), 'content' => trim($c)];
            }
        }

        $this->process($signal, $chapters);
    }

    /**
     * Executes the expert analysis for each chapter.
     */
/**
     * Executes the expert analysis for each chapter using a Single-Pass JSON approach.
     */
    public function process(IngestSignal $signal, array $chapters)
    {
        // Wir holen nur aktive Agenten, um den Prompt nicht unnötig aufzublähen
        $agents = Agent::where('is_active', true)->get();
        $signal->update(['master_blob_draft' => ""]); 

        // Die Agent-Definitionen aus allen 3 Bausteinen (Soul, Angles, Instructions) zusammensetzen
        $agentDefinitions = $agents->map(function ($agent) {
            
            // 1. Die Persona/Rolle (Wer ist der Agent?)
            // Fallback auf 'Domain Expert', falls die Soul leer ist
            $role = $agent->soul ?? 'Domain Expert';
            
            // 2. Die harten Fokus-Themen aus dem Repeater (Wonach sucht er?)
            $perspectivesArray = $agent->perspectives ?? []; 
            $topicsText = "";
            
            if (is_array($perspectivesArray) && count($perspectivesArray) > 0) {
                $angles = collect($perspectivesArray)->pluck('angle')->filter()->implode(', ');
                if (!empty($angles)) {
                    $topicsText = "\n  Focus EXCLUSIVELY on: {$angles}.";
                }
            }
            
            // 3. Die spezifischen Extraktionsanweisungen (Wie soll er arbeiten?)
            $instructions = $agent->system_prompt ? "\n  Extraction Instructions: {$agent->system_prompt}" : "";
            
            // Alles zu einem kugelsicheren Block verschmelzen
            return "- **{$agent->name}** (Role: {$role}){$topicsText}{$instructions}";
            
        })->implode("\n\n");

        $agentNames = $agents->pluck('name')->toArray();

        // 1. SCHRITT: JSON Schema definieren (Zwingt Gemini in ein hartes Format)
        // Wir erzeugen dynamisch die erwarteten JSON-Keys für jeden Agenten
        $jsonSchemaProperties = [];
        foreach ($agentNames as $name) {
            $key = strtolower(str_replace(' ', '_', $name)) . '_notes';
            $jsonSchemaProperties[$key] = [
                'type' => 'string',
                'description' => "Insights from {$name}'s perspective. Use strictly Markdown. If NO relevant data for {$name} is present in the text, output exactly: 'NULL'."
            ];
        }

        $jsonSchema = [
            'type' => 'object',
            'properties' => $jsonSchemaProperties,
            // Wir erzwingen nicht alle Felder (optional), falls ein Agent nichts zu sagen hat
        ];

        foreach ($chapters as $index => $chapter) {
            $title = $chapter['title'] ?? "Section " . ($index + 1);
            $content = $chapter['content'] ?? "";

            $this->log($signal, "📘 ANALYZING: '{$title}' (Single-Pass)", 'info');
            
            // --- DER SINGLE-PASS PROMPT ---
            $prompt = "You are the Acado Refinery central processor.\n\n" .
                      "Your task is to analyze the following document chunk simultaneously from the perspectives of multiple domain experts.\n\n" .
                      "EXPERTS & FOCUS AREAS:\n{$agentDefinitions}\n\n" .
                      "STRICT INSTRUCTIONS:\n" .
                      "- Act as each expert independently.\n" .
                      "- Extract ONLY hard facts, heuristcs, numbers, and actionable strategies relevant to that expert's focus.\n" .
                      "- Do NOT write introductory fluff (e.g., 'Here are the notes...').\n" .
                      "- If the text contains NO relevant data for a specific expert, you MUST return the exact string 'NULL' for that expert's key.\n\n" .
                      "-Do NOT force a connection. If the data is only tangentially related or generic (e.g. bibliographies, table of contents), output 'NULL'. Require a HIGH threshold of relevance.\n\n" .
                      "-Ignore publishing metadata, ISBNs, and copyright contact info.\n\n" .
                      "DOCUMENT CHUNK:\n" . Str::limit($content, 25000); // 25k limit to stay safe within token limits

            // 2. SCHRITT: Den modifizierten ask-Call abfeuern (mit JSON-Zwang)
            $this->log($signal, "📡 Sending single request to API (JSON-Mode)...", 'info');
            
            // Wir nutzen eine spezielle ask-Methode für strukturierten Output (siehe unten)
            $jsonResponse = $this->askStructured('analysis', null, $prompt, $jsonSchema, $signal);

            if (!$jsonResponse) {
                $this->log($signal, "🛑 API returned empty JSON or failed. Skipping chapter.", 'error');
                continue; // Skip to next chapter if API fails completely
            }

            $chapterHasContent = false;
            $chapterBuffer = "# {$title}\n\n";

            // 3. SCHRITT: Das JSON entpacken und den Master-Blob bauen
            $parsedData = json_decode($jsonResponse, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($parsedData)) {
                foreach ($agents as $agent) {
                    $key = strtolower(str_replace(' ', '_', $agent->name)) . '_notes';
                    
                    if (isset($parsedData[$key])) {
                        $votum = trim($parsedData[$key]);

                        // Prüfen, ob der Agent relevantes gefunden hat (Nicht "NULL" oder leer)
                        if ($votum !== 'NULL' && $votum !== '' && strtolower($votum) !== 'null') {
                            $chapterBuffer .= "## {$agent->name}'s Brief\n\n{$votum}\n\n---\n\n";
                            $chapterHasContent = true;
                            $this->log($signal, "🧠 Brief secured for {$agent->name}.", 'done');
                        } else {
                            $this->log($signal, "⚪ {$agent->name} found no domain data.");
                        }
                    }
                }
            } else {
                 $this->log($signal, "❌ Failed to parse JSON from API.", 'error');
            }

            // Nur wenn mindestens ein Agent echten Content geliefert hat, schreiben wir das Kapitel
            if ($chapterHasContent) {
                $this->appendToBlob($signal, $chapterBuffer);
            }
            
            // 4. SCHRITT: Das Rate-Limit-Sicherheitsnetz
            // WICHTIG: 4 Sekunden Pause zwingend einhalten wegen des 15 RPM Limits im Free Tier
            $this->log($signal, "⏱️ Enforcing 4s Free-Tier cool-down...", 'info');
            sleep(4); 
        }

        $signal->update(['status' => 'done']);
        $this->log($signal, "🏁 FINISH: Boardroom concluded.", 'done');
    }
        

    protected function appendToBlob(IngestSignal $signal, string $text)
    {
        $signal->refresh();
        $signal->update(['master_blob_draft' => ($signal->master_blob_draft ?? "") . $text]);
    }

}
