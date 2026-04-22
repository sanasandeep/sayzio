<?php

namespace App\Jobs;

use App\Modules\User\Models\AiMindSource;
use App\Services\AI\AiMindIngestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Background ingestion of one AI Mind source. The controller queues
 * this when the user adds or refreshes a source — extraction +
 * embedding can take several seconds and we don't want to block the
 * request.
 */
class IngestAiMindSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public int $sourceId) {}

    public function handle(AiMindIngestor $ingestor): void
    {
        $source = AiMindSource::find($this->sourceId);
        if (!$source) return;
        $ingestor->ingest($source);
    }

    public function failed(\Throwable $e): void
    {
        $source = AiMindSource::find($this->sourceId);
        if (!$source) return;
        $source->forceFill([
            'status'         => AiMindSource::STATUS_FAILED,
            'status_message' => \Illuminate\Support\Str::limit($e->getMessage(), 480),
        ])->save();
    }
}
