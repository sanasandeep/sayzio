<?php

namespace App\Jobs;

use App\Modules\Common\Services\LoginAlertService;
use App\Modules\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Deferred login-event pipeline.
 *
 * Dispatched immediately after every successful authentication so that the
 * blocking work (GeoIP HTTP lookup, suspicious-login comparison, audit-row
 * INSERT, optional alert email) happens off the request path. The response
 * goes out as soon as password verification and session regeneration finish;
 * this job drains via the scheduler-driven `queue:work` loop.
 *
 * IP and user-agent are captured at dispatch time (from the live Request)
 * and stored as plain strings so the job never needs a Request object.
 * The `loggedInAt` timestamp is similarly captured at dispatch time so the
 * `last_login_at` column reflects when the user actually signed in rather
 * than when the worker picked up the job.
 *
 * @param bool $updateLastLoginAt  Set false for registration-only calls where
 *                                 no session was established (e.g. api_register).
 */
class RecordLoginEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly int $userId,
        public readonly string $channel,
        public readonly string $ip,
        public readonly string $userAgent,
        public readonly array $opts = [],
        public readonly bool $updateLastLoginAt = true,
        public readonly ?Carbon $loggedInAt = null,
    ) {}

    public function handle(LoginAlertService $service): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        if ($this->updateLastLoginAt) {
            try {
                $user->forceFill(['last_login_at' => $this->loggedInAt ?? now()])->save();
            } catch (\Throwable $e) {
                Log::warning('RecordLoginEventJob: last_login_at update failed', [
                    'user_id' => $this->userId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // Pass the dispatch-captured timestamp through so the login_events
        // row's created_at (what the Recent Logins page/API render) reflects
        // the actual sign-in moment, not delayed worker execution time.
        $service->recordRaw($user, $this->ip, $this->userAgent, $this->channel, $this->opts, $this->loggedInAt);
    }
}
