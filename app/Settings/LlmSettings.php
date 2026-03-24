<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class LlmSettings extends Settings
{
    public ?string $global_default_model;
    public bool $use_intelligent_fallback;
    
    // Schritt-spezifische Modelle
    public ?string $model_extraction;
    public ?string $model_analysis;
    public ?string $model_association;

    public static function group(): string
    {
        return 'llm';
    }
}
