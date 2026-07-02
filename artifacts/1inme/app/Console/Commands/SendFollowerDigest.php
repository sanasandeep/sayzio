<?php

namespace App\Console\Commands;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Services\FollowerDigestComposer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Hourly job that emails each follower a single digest covering every
 * creator they follow that has had pending activity since their last
 * digest. Runs every hour but only sends to users whose preferred local
 * hour matches the current hour in their timezone — so a user who picked
 * 8am in Europe/London receives the digest at 08:00 London time, not
 * 09:00 UTC like before.
 *
 * Followers in 'instant' mode are skipped (their emails go out
 * synchronously from CreatorPostController). Followers in 'off' mode
 * never have rows queued, so they're naturally excluded.
 *
 * Idempotent within each user's local day: digest emails are gated on
 * `follower_digest_last_sent_at`, so re-running the command in the same
 * local day for that user does nothing.
 */
class SendFollowerDigest extends Command
{
    protected $signature = 'followers:send-digest
        {--user= : Optional follower user id to digest (default: all eligible)}
        {--hour= : Override the "current hour" used for matching (0-23). Defaults to now (UTC).}
        {--any-hour : Ignore each user\'s preferred hour and send to anyone eligible}
        {--force : Send even if a digest was already sent today}';

    protected $description = 'Email each opted-in follower one digest of new creator activity, honouring their preferred local hour.';

    public function handle(): int
    {
        $now = now();
        // The "wall-clock" hour the run represents (UTC). For each candidate
        // user we convert this to their local hour and compare with their
        // preferred hour.
        $runHour = $this->option('hour') !== null ? (int) $this->option('hour') : (int) $now->copy()->utc()->format('G');
        $anyHour = (bool) $this->option('any-hour');
        $force   = (bool) $this->option('force');

        $query = User::query()->where('follower_updates_mode', 'digest');
        if ($this->option('user')) {
            $query->where('id', $this->option('user'));
        }

        $sent = 0;
        $skipped = 0;

        $query->chunkById(200, function ($users) use (&$sent, &$skipped, $now, $runHour, $anyHour, $force) {
            foreach ($users as $user) {
                $tz = \App\Support\PlatformTimezone::forUser($user);
                try {
                    $userNow = $now->copy()->setTimezone($tz);
                } catch (\Throwable $e) {
                    $userNow = $now->copy();
                }
                $preferred = (int) ($user->digest_preferred_hour ?? 9);
                if ($preferred < 0 || $preferred > 23) $preferred = 9;

                // Compare the user's *local* hour for the run timestamp
                // against their preferred hour. This naturally honours DST
                // transitions because Carbon's setTimezone does the work.
                $localHour = (int) $now->copy()->utc()->setTime($runHour, 0)->setTimezone($tz)->format('G');
                if (!$anyHour && $localHour !== $preferred) {
                    $skipped++;
                    continue;
                }

                $localStartOfDay = $userNow->copy()->startOfDay();
                if (!$force && $user->follower_digest_last_sent_at
                    && $user->follower_digest_last_sent_at->copy()->setTimezone($tz)
                        ->greaterThanOrEqualTo($localStartOfDay)) {
                    $skipped++;
                    continue;
                }

                $pending = UserNotification::where('user_id', $user->id)
                    ->where('type', 'follower_update')
                    ->whereNull('emailed_at')
                    ->orderBy('created_at')
                    ->get();

                if ($pending->isEmpty()) {
                    $skipped++;
                    continue;
                }

                if ($this->emailDigest($user, $pending)) {
                    $stamp = now();
                    UserNotification::whereIn('id', $pending->pluck('id'))
                        ->update(['emailed_at' => $stamp]);
                    $user->forceFill(['follower_digest_last_sent_at' => $stamp])->save();
                    $sent++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info("Digest run complete. Sent: {$sent}, skipped: {$skipped}.");
        return self::SUCCESS;
    }

    /**
     * Group pending notifications by creator and email a single summary.
     * Returns true on success so the caller can mark the rows emailed.
     */
    private function emailDigest(User $user, $pending): bool
    {
        $composed = FollowerDigestComposer::compose($user, $pending);

        try {
            \App\Modules\Common\Services\Emailer::send('digests.follower', $user->email, [], [
                'user'      => $user->id,
                'subject'   => $composed['subject'],
                'view_data' => $composed['viewData'],
            ]);
            return true;
        } catch (\Throwable $e) {
            \Log::warning('follower digest send failed for user ' . $user->id . ': ' . $e->getMessage());
            return false;
        }
    }
}
