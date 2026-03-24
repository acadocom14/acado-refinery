<?php

namespace App\Services;

use App\Models\IngestSignal;
use App\Models\Agent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IngestPipeline
{
    /**
     * Die Haupt-Logik: Routing & Multi-Agent-Analyse
     */
    public function processWithRouting(IngestSignal $signal)
    {
        // 1. DYNAMISCHE TAG-WOLKE: Alle Expertisen aller Agenten sammeln
        $globalTagCloud = Agent::all()
            ->pluck('expertise')
            ->flatten()
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        // 2. TEXT-QUELLE: Wir nehmen den Text aus dem Master-Blob (oder Titel als Fallback)
        $fullText = $signal->master_blob_draft['raw_text'] ?? $signal->title; 
        
        // 3. CHUNKING: Wir zerlegen das Buch in handliche Portionen
        $chunks = $this->chunkText($fullText);
        $finalMasterBlob = [
            'raw_text' => $fullText, // Wir behalten das Original
            'chapters' => []
        ];

        foreach ($chunks as $index => $chunk) {
            // A. TAG-ZUORDNUNG (Welcher Agent muss hier ran?)
            $chapterTags = $this->determineTagsForChunk($chunk, $globalTagCloud, $signal);
            
            // B. AGENTEN-ROUTING (Wer hat die passende Brille?)
            $eligibleAgents = Agent::all()->filter(function ($agent) use ($chapterTags) {
                // TEST-MODUS: Wenn das Buch das "*" Tag hat, dürfen ALLE ran
                if (in_array('*', $chapterTags)) return true;
                
                // ROUTING-MODUS: Match zwischen Agenten-Expertise und Kapitel-Inhalt
                return count(array_intersect($agent->expertise ?? [], $chapterTags)) > 0;
            });

            // C. MULTI-AGENT ANALYSE (Jeder schreibt sein Exzerpt)
            $excerpts = [];
            foreach ($eligibleAgents as $agent) {
                $excerpts[$agent->name] = $this->askAgentAboutChunk($agent, $chunk);
            }

            // D. KAPITEL-ERGEBNIS SPEICHERN
            $finalMasterBlob['chapters'][] = [
                'chapter_index' => $index + 1,
                'detected_tags' => $chapterTags,
                'agent_excerpts' => $excerpts,
                'chunk_preview' => substr($chunk, 0, 100) . '...'
            ];
        }

        // 4. ABSCHLUSS: Zurück in die Bibliothek (Master Blob)
        $signal->update([
            'master_blob_draft' => $finalMasterBlob,
            'status' => 'done', // Analyse abgeschlossen
            'tags' => array_merge($signal->tags ?? [], ['multi-agent-v1'])
        ]);

        return $finalMasterBlob;
    }

    /**
     * Routing-Entscheidung via Gemini Flash
     */
    private function determineTagsForChunk(string $chunk, array $cloud, IngestSignal $signal): array
    {
        // GLOBALER OVERRIDE: Wenn das Buch selbst den Tag "*" hat -> Alle Agenten triggern
        if (in_array('*', $signal->tags ?? [])) {
            return ['*'];
        }

        $tagsCsv = implode(', ', $cloud);
        $prompt = "Expertise Cloud: [{$tagsCsv}]\n\n" .
                  "Which of these expertise tags are relevant to analyze this text? " .
                  "Return ONLY a JSON array of tags. If none fit, return [].\n\n" .
                  "Text: " . substr($chunk, 0, 2000);

        try {
            $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . config('services.gemini.key'), [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['response_mime_type' => 'application/json']
            ]);

            return json_decode($response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '[]', true);
        } catch (\Exception $e) {
            Log::error("Routing Error: " . $e->getMessage());
            return [];
        }
    }

    public function getAvailableModels(): array
{
    $apiKey = config('services.gemini.key');
    if (!$apiKey) return ['gemini-1.5-flash' => 'Key fehlt - Fallback Flash'];

    try {
        $response = Http::get("https://generativelanguage.googleapis.com/v1/models?key={$apiKey}");
        
        if ($response->failed()) return ['gemini-1.5-flash' => 'API Fehler - Fallback Flash'];

        // Wir filtern nur die Modelle, die "generateContent" unterstützen
        return collect($response->json()['models'])
            ->filter(fn($model) => in_array('generateContent', $model['supportedGenerationMethods']))
            ->mapWithKeys(fn($model) => [
                str_replace('models/', '', $model['name']) => $model['displayName']
            ])
            ->toArray();
    } catch (\Exception $e) {
        return ['gemini-1.5-flash' => 'Fehler - Fallback Flash'];
    }
}

    /**
     * Die eigentliche Agenten-Analyse (Rollen-Brille)
     */
    private function askAgentAboutChunk(Agent $agent, string $chunk)
{
    $apiKey = config('services.gemini.key');

    if (!$apiKey) {
        throw new \Exception("Abbruch: Kein API-Key in der Config gefunden!");
    }

    // Konfiguration aus der Agenten-Seele laden
    $config = $agent->soul_configuration;
    $expertise = implode(', ', $agent->expertise ?? []);
    
    // --- NEU: Dynamische Einstellungen pro Agent ---
    $model = $config['model'] ?? 'gemini-1.5-flash'; // Fallback auf Flash
    $temperature = (float) ($config['temperature'] ?? 0.2); // Fallback auf 0.2
    // -----------------------------------------------

    $prompt = "You are {$agent->name}, the {$agent->role}. Expertise: {$expertise}.\n" .
              "Personality: " . ($config['personality'] ?? 'professional') . ".\n\n" .
              "Analyze this document snippet from your specific professional perspective. " .
              "What are the key insights? Keep it concise.\n\n" .
              "Text: " . $chunk;

    // URL nutzt jetzt das dynamische Modell und die stabile v1 API
    $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";

    $response = Http::post($url, [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        // NEU: Hier übergeben wir die individuelle Temperatur
        'generationConfig' => [
            'temperature' => $temperature,
        ]
    ]);

    if ($response->failed()) {
        throw new \Exception("Agent {$agent->name} streikt ({$model}): " . ($response->json()['error']['message'] ?? 'Unbekannter API Fehler'));
    }

    $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$text) {
        return "{$agent->name} hat den Text gelesen, aber schweigt (Leere Antwort).";
    }

    return $text;
}

    /**
     * Text in Stücke schneiden (ca. 8000 Zeichen für Gemini Flash)
     */
    private function chunkText(string $text, int $size = 8000): array
    {
        if (empty($text)) return ['[Empty Document]'];
        return str_split($text, $size);
    }
}
