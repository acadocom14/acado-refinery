<?php

namespace App\Jobs;

use App\Models\IngestSignal;
use App\Services\IngestPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBoardMeetingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Wir geben dem Job 10 Minuten Zeit, bevor er abbricht
    public $timeout = 600;

    public function __construct(
        public IngestSignal $signal
    ) {}

    public function handle(IngestPipeline $pipeline): void
    {
        // Hier rufen wir die Pipeline auf, die wir gerade synchronisiert haben
        $pipeline->processWithRouting($this->signal);
    }
}
