<?php

namespace App\Modules\Common\Services;

use App\Jobs\PersistLinkClicksJob;
use App\Modules\Common\Support\PendingClick;
use Illuminate\Support\Facades\Log;

/**
 * Request-scoped buffer for click write payloads. LinkTrackingService does the
 * synchronous, must-happen-before-the-redirect work (bot/throttle filtering,
 * atomic per-block cap reservation) and then PUSHes a payload here instead of
 * inserting into link_clicks inline. On request termination — i.e. after the
 * visitor's redirect response has already been flushed to the client — the
 * buffered payloads are handed to a single queued PersistLinkClicksJob that does
 * the geo lookup, idempotent insert, and counter buffering off the hot path.
 *
 * Bound as a singleton so every track()/trackBlockClick() call within one
 * request shares the same buffer and a single flush (one job per request).
 */
class ClickWriteBuffer
{
    /** @var array<int, array<string, mixed>> */
    private array $payloads = [];

    private bool $flushRegistered = false;

    /**
     * Buffer one click payload and return a handle for late enrichment.
     */
    public function push(array $payload): PendingClick
    {
        $index = count($this->payloads);
        $this->payloads[$index] = $payload;
        $this->ensureFlushRegistered();

        return new PendingClick(
            $this,
            $index,
            (string) ($payload['event_id'] ?? ''),
            (bool) ($payload['is_bot'] ?? false),
        );
    }

    /**
     * Mutate a single field on a still-buffered payload (used by PendingClick).
     */
    public function setField(int $index, string $key, mixed $value): void
    {
        if (array_key_exists($index, $this->payloads)) {
            $this->payloads[$index][$key] = $value;
        }
    }

    /**
     * Hand the buffered batch to the queue. A queue failure here must never break
     * the visitor's already-sent response — but it must also never silently drop
     * clicks. So on dispatch failure we fall back to persisting the batch
     * synchronously (this runs in terminate(), AFTER the response is flushed, so
     * the fallback adds no latency to the redirect). The buffer is only cleared
     * once the batch has been durably handed off, so a hard failure is logged
     * loudly rather than vanishing.
     */
    public function flush(): void
    {
        if (empty($this->payloads)) {
            return;
        }

        $batch = array_values($this->payloads);

        try {
            PersistLinkClicksJob::dispatch($batch);
            $this->reset();
            return;
        } catch (\Throwable $e) {
            Log::warning('ClickWriteBuffer queue dispatch failed; persisting synchronously: ' . $e->getMessage());
        }

        // Durability fallback: persist inline. The job is idempotent
        // (insertOrIgnore on event_id), so even if the dispatch partially
        // succeeded this can't double-count.
        try {
            (new PersistLinkClicksJob($batch))->handle();
        } catch (\Throwable $e) {
            Log::error('ClickWriteBuffer synchronous fallback failed; clicks lost: ' . $e->getMessage());
        } finally {
            $this->reset();
        }
    }

    private function reset(): void
    {
        $this->payloads = [];
        $this->flushRegistered = false;
    }

    private function ensureFlushRegistered(): void
    {
        if ($this->flushRegistered) {
            return;
        }
        $this->flushRegistered = true;
        // Runs after the response is sent to the client (FPM fastcgi_finish_request),
        // so persistence work never adds latency to the redirect.
        app()->terminating(function () {
            $this->flush();
        });
    }
}
