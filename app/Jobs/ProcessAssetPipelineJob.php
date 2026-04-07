<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Services\IngestPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAssetPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Timeout auf 10 Minuten setzen, da LLMs Zeit brauchen
    public $timeout = 600;

    public function __construct(protected \App\Models\Asset $asset) {}

    public function handle(IngestPipeline $pipeline)
    {
        $pipeline->processAssetPipeline($this->asset);
    }
}
