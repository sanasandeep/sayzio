<?php

namespace App\Jobs;

use App\Modules\User\Models\ConnectedApp;
use App\Modules\User\Services\ConnectedApps\ConnectedAppManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Forwards a single click/visitor event to a user's active Google Analytics 4
 * connections via the Measurement Protocol, entirely off the click hot path
 * (the click write itself is never blocked by GA availability/latency).
 */
class ForwardAnalyticsEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    /**
     * @param array{name:string,client_id?:string,params?:array<string,mixed>} $event
     */
    public function __construct(private int $userId, private array $event)
    {
    }

    public function handle(ConnectedAppManager $manager): void
    {
        $conns = ConnectedApp::forUser($this->userId)
            ->analytics()
            ->where('status', ConnectedApp::STATUS_CONNECTED)
            ->get();

        foreach ($conns as $conn) {
            try {
                $manager->forwarder()->forward($conn, $this->event);
                $conn->forceFill([
                    'records_sent'   => $conn->records_sent + 1,
                    'last_synced_at' => now(),
                    'last_sync_status' => 'ok',
                    'last_sync_error'  => null,
                ])->save();
            } catch (\Throwable $e) {
                $conn->forceFill([
                    'last_sync_status' => 'error',
                    'last_sync_error'  => \Illuminate\Support\Str::limit($e->getMessage(), 500),
                ])->save();
                report($e);
            }
        }
    }

    /** True when the user has at least one connected GA property. */
    public static function shouldQueue(int $userId): bool
    {
        // Hard-stop when the plan no longer includes Connected Apps, so a
        // creator who connected while entitled stops forwarding after downgrade.
        if (!optional(\App\Modules\User\Models\User::find($userId))->getPlanFeature('connected_apps', false)) {
            return false;
        }

        return ConnectedApp::forUser($userId)
            ->analytics()
            ->where('status', ConnectedApp::STATUS_CONNECTED)
            ->exists();
    }

    /**
     * @param array{name:string,client_id?:string,params?:array<string,mixed>} $event
     */
    public static function forUser(int $userId, array $event): void
    {
        if (self::shouldQueue($userId)) {
            self::dispatch($userId, $event);
        }
    }
}
