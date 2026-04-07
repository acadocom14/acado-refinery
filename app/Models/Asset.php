<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Asset extends Model
{
    protected $guarded = [];

    // DAS HIER MUSS REIN:
    protected $casts = [
        'processing_logs' => 'array',
        'pipeline_outputs' => 'array',
    ];

    // Wir sagen Laravel hier: Nutze 'asset_agent' statt des Defaults 'agent_asset'
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'asset_agent');
    }

    // Hier passt der Default ('asset_ingest_signal'), aber zur Sicherheit 
    // schreiben wir es auch hier explizit rein:
    public function ingestSignals(): BelongsToMany
    {
        return $this->belongsToMany(IngestSignal::class, 'asset_ingest_signal');
    }

    public function pipelineStages()
{
    return $this->hasMany(PipelineStage::class)->orderBy('stage_level');
}
}
