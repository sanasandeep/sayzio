<?php

namespace App\Jobs;

use App\Services\AI\AiMindProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deferred provisioning of the platform-managed "Sayzio Default Mind".
 *
 * This used to run synchronously inside the `User::created` hook, which put
 * it on the account-creation request path: it creates AI Mind rows, dispatches
 * source-ingest jobs, and recounts stats. On a sync-queue install (or if the
 * ingestor ever grows a synchronous network call) that work would run inline
 * and could slow or stall a first-time sign-up. Moving it into a queued job
 * keeps account creation snappy while still guaranteeing the platform default
 * Mind is provisioned shortly after the first account exists.
 *
 * The provisioner is fully idempotent, so re-running (e.g. a retry, or several
 * signups racing to dispatch it) can never create duplicates.
 */
class ProvisionPlatformAiMindJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * Dispatch the provisioning job in a way that can *never* run inline on the
     * request that triggers it.
     *
     * A `ShouldQueue` job normally respects the app's default queue connection,
     * but Laravel's `sync` driver executes queued jobs immediately, in-process.
     * If `QUEUE_CONNECTION=sync` (a local/test default, or a misconfigured/
     * drifted production install), a plain `dispatch()` from the account-
     * creation path would run this heavy AI provisioning synchronously and
     * could stall a first-time sign-up — exactly the failure this job exists to
     * prevent. When the default connection resolves to `sync` we therefore
     * force the work onto the persistent `database` queue so it is always
     * deferred; the platform default Mind is also provisioned lazily the first
     * time a user opens the Mind dashboard, so a delayed (or, on a worker-less
     * sync install, never-drained) job never blocks account creation.
     */
    public static function dispatchDeferred(): void
    {
        if ((string) config('queue.default') === 'sync') {
            Log::debug(
                'ProvisionPlatformAiMindJob: default queue is sync; '
                . 'routing to the database queue so sign-up stays non-blocking.'
            );
            self::dispatch()->onConnection('database');

            return;
        }

        self::dispatch();
    }

    public function handle(): void
    {
        try {
            AiMindProvisioner::ensurePlatformDefault();
        } catch (\Throwable $e) {
            // AI provisioning must never be fatal; log and let the standard
            // retry/backoff take over. The Mind is also provisioned lazily
            // the first time a user opens the Mind dashboard.
            Log::warning('ProvisionPlatformAiMindJob failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
