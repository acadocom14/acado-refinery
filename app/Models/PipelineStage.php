<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineStage extends Model
{
    // Erlaube das Speichern der ai_model_id
    protected $fillable = [
        'asset_id',
        'stage_level',
        'name',
        'llm_model',
        'signal_filter', // <--- NEU
        'fixed_prompt_template',
        'custom_prompt_directive',
    ];

    /**
     * Die Verbindung zum KI-Modell (Engine)
     */
    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    /**
     * Die Verbindung zum Asset
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
    
    /**
     * Verbindung zu den Agenten (Many-to-Many)
     */
    public function agents()
    {
        return $this->belongsToMany(Agent::class, 'agent_pipeline_stage');
    }
}
