<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\PostUnlock;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Weekly creator-side digest (Task #1211). Mirrors the existing
 * follower / backlink digest pattern: a service that builds the
 * payload AND sends, so the on-demand "send sample" preview button
 * and the scheduled cron emit identical email bodies.
 *
 * Cron entry lives in routes/console.php (`weeklyOn(1, '08:00')`) so
 * it lands in inboxes right after Monday's standup hour in most
 * Western timezones. Per-user `creator_digest_last_sent_at` is the
 * idempotence key — re-running within 6 days is a no-op.
 */
class CreatorDigestService
{
    public const SCHEDULED_DOW = 1; // Monday
    public const SCHEDULED_HOUR = 8; // 08:00 UTC
    public const WINDOW_DAYS = 7;

    /**
     * Build digest data for $creator. Returns null when there's nothing
     * worth emailing about (no follower delta, no posts, no earnings).
     *
     * @return array{subject:string, viewData:array}|null
     */
    public function buildDigest(User $creator, bool $isSample = false): ?array
    {
        $start = now()->subDays(self::WINDOW_DAYS);

        $newFollowers = Follow::where('creator_id', $creator->id)
            ->where('created_at', '>=', $start)
            ->count();

        $posts = CreatorPost::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $start)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        $newSubs = CreatorSubscription::where('creator_user_id', $creator->id)
            ->where('created_at', '>=', $start)
            ->whereIn('status', ['active', 'trialing'])
            ->count();

        $unlocks = PostUnlock::whereIn('post_id', CreatorPost::query()
                ->withoutGlobalScope('workspace')
                ->where('user_id', $creator->id)->pluck('id'))
            ->where('unlocked_at', '>=', $start)
            ->whereNull('refunded_at')
            ->get();
        $unlockRevenueCents = (int) $unlocks->sum('price_cents');

        $totalSignal = $newFollowers + $posts->count() + $newSubs + $unlocks->count();
        if ($totalSignal === 0 && !$isSample) return null;

        $subject = "Your week on Sayzio: +{$newFollowers} followers, {$posts->count()} posts";

        return [
            'subject'  => $subject,
            'viewData' => [
                'creator'             => $creator,
                'isSample'            => $isSample,
                'newFollowers'        => $newFollowers,
                'newSubscribers'      => $newSubs,
                'newPosts'            => $posts,
                'unlockCount'         => $unlocks->count(),
                'unlockRevenueCents'  => $unlockRevenueCents,
                'profileUrl'          => AppModulesCommonSupportPlatformHosts::outboundUrl(url('/@' . $creator->handle)),
                'statsUrl'            => AppModulesCommonSupportPlatformHosts::outboundUrl(url('/user/creator-stats')),
                'periodStart'         => $start->format('M j'),
                'periodEnd'           => now()->format('M j, Y'),
            ],
        ];
    }

    /**
     * Send to $creator. Returns true on send, false when there was
     * nothing to send or when the creator has opted out of digest
     * email. Bumps `creator_digest_last_sent_at` so the cron picks
     * up where it left off.
     */
    public function send(User $creator, bool $isSample = false): bool
    {
        if (empty($creator->email)) return false;

        $digest = $this->buildDigest($creator, $isSample);
        if (!$digest) return false;

        try {
            \App\Modules\Common\Services\Emailer::send('digests.creator', $creator->email, [], [
                'user'      => $creator->id,
                'subject'   => $digest['subject'],
                'to_name'   => $creator->name,
                'view_data' => $digest['viewData'],
            ]);
            if (!$isSample) {
                $creator->forceFill(['creator_digest_last_sent_at' => now()])->save();
            }
            return true;
        } catch (\Throwable $e) {
            \Log::warning('Creator digest send failed', [
                'creator_id' => $creator->id,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Cron entrypoint. Returns the number of digests dispatched.
     */
    public function sendDueDigests(): int
    {
        $cutoff = now()->subDays(self::WINDOW_DAYS - 1);
        $sent = 0;
        User::query()
            ->whereNotNull('handle')
            ->where('profile_published', true)
            ->where(function ($w) use ($cutoff) {
                $w->whereNull('creator_digest_last_sent_at')
                  ->orWhere('creator_digest_last_sent_at', '<', $cutoff);
            })
            ->orderBy('id')
            ->chunkById(200, function ($creators) use (&$sent) {
                foreach ($creators as $c) {
                    if ($this->send($c)) $sent++;
                }
            });
        return $sent;
    }
}
