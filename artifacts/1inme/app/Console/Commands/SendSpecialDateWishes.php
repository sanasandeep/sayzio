<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\SpecialDateWishLog;
use App\Modules\User\Models\User;
use App\Modules\User\Support\SpecialDates;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Task #6551 — fan out "wish" notifications on the day of a creator's
 * special dates (birthday / anniversary / company anniversary / product
 * release day) to their followers, plus a heads-up to the creator.
 *
 * Timezone aware: scheduled hourly, a creator's occurrences are processed
 * when the creator's LOCAL time is 9 AM (pass --force to bypass, tests).
 * Idempotent: a unique (creator, entry, occurrence-year) row is claimed in
 * special_date_wish_logs BEFORE the fan-out, so re-runs never double-send.
 *
 * Only entries that are BOTH public and notify-enabled fan out — private
 * dates never notify. Channels honor the 'special_date_wish' preference
 * entry (in-app + push via NotificationService, email via Emailer).
 * Sync-enabled entries also get their mirrored calendar event rolled
 * forward to next year's occurrence once today's has been processed.
 */
class SendSpecialDateWishes extends Command
{
    protected $signature = 'special-dates:send-wishes {--force : Process regardless of local hour}';
    protected $description = "Notify followers about creators' special dates happening today (creator-tz aware, once per occurrence).";

    /** Human phrase per kind, used in notification copy. */
    protected function occasion(array $entry): string
    {
        return match ($entry['kind'] ?? '') {
            'birthday'            => 'birthday',
            'anniversary'         => 'anniversary',
            'company_anniversary' => 'company anniversary',
            'product_release'     => trim((string) ($entry['label'] ?? '')) !== ''
                ? '"' . $entry['label'] . '" release day'
                : 'release day',
            default               => 'special day',
        };
    }

    public function handle(NotificationService $notifications): int
    {
        $force = (bool) $this->option('force');
        $sent  = 0;

        User::query()
            ->whereNotNull('special_dates')
            ->whereNotNull('handle')
            ->chunkById(200, function ($creators) use ($notifications, $force, &$sent) {
                foreach ($creators as $creator) {
                    $sent += $this->processCreator($creator, $notifications, $force);
                }
            });

        $this->info("Special-date wish fan-outs: {$sent}.");
        return self::SUCCESS;
    }

    protected function processCreator(User $creator, NotificationService $notifications, bool $force): int
    {
        // Plan-gated (Task #6646): skip creators whose plan explicitly
        // disables special dates (legacy plans without the key default ON).
        if (! $creator->getPlanFeature('special_dates', true)) return 0;

        $entries = SpecialDates::entries($creator);
        if (empty($entries)) return 0;

        $tz       = \App\Support\PlatformTimezone::forUser($creator);
        $nowLocal = Carbon::now($tz);

        // Hourly schedule; only do real work at 9 AM creator-local.
        if (!$force && $nowLocal->hour !== 9) return 0;

        $todays = array_filter($entries, fn ($e) => SpecialDates::occursOn($e, $nowLocal));
        if (empty($todays)) return 0;

        $profileUrl = url('/@' . $creator->handle);
        $creatorName = $creator->name ?: '@' . $creator->handle;
        $followerIds = null; // lazy — only load when an entry actually notifies
        $count = 0;

        foreach ($todays as $entry) {
            // Claim the occurrence FIRST — the unique index makes re-runs no-ops.
            $claimed = SpecialDateWishLog::query()->getQuery()->insertOrIgnore([
                'user_id'         => $creator->id,
                'entry_id'        => (string) ($entry['id'] ?? ''),
                'occurrence_year' => $nowLocal->year,
                'created_at'      => now(),
            ]);
            if (!$claimed) continue; // already processed this occurrence

            // Roll the mirrored calendar event forward regardless of notify flags.
            SpecialDates::rollEventForward($creator, $entry, $nowLocal);

            // Private dates never notify; notify flag is opt-in.
            if (empty($entry['notify']) || empty($entry['public'])) continue;

            $occasion = $this->occasion($entry);

            $followerIds ??= Follow::query()
                ->withoutGlobalScope('workspace')
                ->where('creator_id', $creator->id)
                ->pluck('follower_id')
                ->unique()
                ->values();

            $followers = User::query()->whereIn('id', $followerIds)->get();
            foreach ($followers as $follower) {
                $title = "It's {$creatorName}'s {$occasion}!";
                $body  = "Send your wishes to @{$creator->handle} today.";

                $notifications->notify($follower, 'special_date_wish', [
                    'title'   => $title,
                    'body'    => $body,
                    'url'     => $profileUrl,
                    'handle'  => $creator->handle,
                    'kind'    => $entry['kind'] ?? null,
                ]);
                $notifications->pushToUser($follower, 'special_date_wish', $title, $body, ['url' => $profileUrl]);

                if ($follower->email && $notifications->prefersChannel($follower->id, 'special_date_wish', 'email')) {
                    Emailer::send('social.special_date_wish', $follower->email, [
                        'creator_name' => $creatorName,
                        'handle'       => (string) $creator->handle,
                        'occasion'     => $occasion,
                        'profile_url'  => $profileUrl,
                    ], ['user_id' => $follower->id]);
                }

                $count++;
            }

            // Heads-up to the creator (in-app + push; no email — they know).
            $headsUp = "Today is your {$occasion} — followers are being invited to send wishes.";
            $notifications->notify($creator, 'special_date_wish', [
                'title' => 'Your special day is here 🎉',
                'body'  => $headsUp,
                'url'   => $profileUrl,
            ]);
            $notifications->pushToUser($creator, 'special_date_wish', 'Your special day is here 🎉', $headsUp, ['url' => $profileUrl]);
        }

        return $count;
    }
}
