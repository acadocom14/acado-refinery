<?php

namespace App\Services;

use App\Models\Agent;
use App\Settings\LlmSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class IngestPipeline
{
    public function resolveModel(string $step, ?Agent $agent = null): string
    {
        $settings = app(LlmSettings::class);

        // 1. Priorität: Spezielles Modell pro Agent (für Spezialanbindungen)
        if ($step === 'analysis' && $agent?->soul_configuration['model']) {
            return $agent->soul_configuration['model'];
        }

        // 2. Priorität: Spezielles Modell für diesen Pipeline-Schritt
        $stepMap = [
            'extraction'  => $settings->model_extraction,
            'analysis'    => $settings->model_analysis,
            'association' => $settings->model_association,
        ];
        if (!empty($stepMap[$step])) return $stepMap[$step];

        // 3. Priorität: Globales Modell (falls manuell gesetzt)
        if (!$settings->use_intelligent_fallback && $settings->global_default_model) {
            return $settings->global_default_model;
        }

        // 4. Priorität: Intelligente Rückfallregel
        return $this->getIntelligentFallback();
    }

    protected function getIntelligentFallback(): string
    {
        $models = $this->getAvailableModels();
        
        // Logik: Suche das höchste Flash für Dev, Pro für Prod
        $target = app()->isProduction() ? 'pro' : 'flash';
        
        return collect($models)
            ->keys()
            ->filter(fn($m) => str_contains(strtolower($m), $target))
            ->sortDesc() // Höchste Version zuerst (z.B. 2.5 > 1.5)
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
}
