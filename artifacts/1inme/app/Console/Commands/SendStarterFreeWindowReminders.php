<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Daily job that nudges free Starter-plan users to re-confirm their plan once
 * a year, before (or just after) their rolling 1-year free window lapses.
 *
 * The free Starter plan is genuinely free forever; the only thing the yearly
 * window does is ask the user to re-confirm they still want the account. It
 * is a REMINDER ONLY — lapsing never locks the account, downgrades anything,
 * or removes data. A one-click "renew free for another year" link resets the
 * window.
 *
 * Guardrails so it never gets spammy:
 *   - Only targets users actually on the lineup default (free Starter) plan
 *     with a real email — flag-based (Plan::defaultPlan()) so it keeps working
 *     if the free tier is ever re-slugged.
 *   - Only fires once the window is within the lead time of lapsing
 *     (--lead-days, default 14), so people are reminded near renewal, not
 *     the day they sign up.
 *   - At most one reminder per window: the per-window `starter_renewal_reminder_sent_at`
 *     stamp is cleared by renewStarterFreeWindow() so next year's nudge fires.
 *   - Honours the per-user `starter.free_window_renewal` email preference.
 */
class SendStarterFreeWindowReminders extends Command
{
    protected $signature = 'starter:send-free-window-reminders
        {--user= : Optional user id to remind (default: all eligible)}
        {--lead-days=14 : Send when the free window is within this many days of lapsing}
        {--force : Ignore the lead-time / once-per-window guards}';

    protected $description = 'Remind free Starter-plan users to re-confirm their plan once a year (email + in-app), with a one-click free renewal.';

    /**
     * Single source of truth for the in-app notification copy, shared by the
     * live send (remind()) and the admin preview/test-send tool so the two can
     * never drift apart.
     *
     * @return array{title: string, message: string}
     */
    public static function inAppCopy(): array
    {
        return [
            'title'   => 'Re-confirm your free Starter plan',
            'message' => 'Your free year is almost up. Renew free for another year — your account and links stay exactly as they are.',
        ];
    }

    public function handle(NotificationService $prefs): int
    {
        $force    = (bool) $this->option('force');
        $leadDays = max(0, (int) $this->option('lead-days'));
        $userId   = $this->option('user');
        $now      = now();

        $default = Plan::defaultPlan();
        if (!$default) {
            $this->info('No default plan resolved; nothing to do.');
            return self::SUCCESS;
        }

        $query = User::query()
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotNull('starter_free_window_ends_at')
            ->where(function ($q) use ($default) {
                $q->where('plan_id', $default->id)->orWhereNull('plan_id');
            });

        if ($userId) {
            $query->where('id', $userId);
        }

        if (!$force) {
            // Only those whose window is within the lead time of lapsing.
            $query->where('starter_free_window_ends_at', '<=', $now->copy()->addDays($leadDays));
        }

        $sent = 0;
        $skipped = 0;

        $query->chunkById(200, function ($users) use (&$sent, &$skipped, $prefs, $force) {
            foreach ($users as $user) {
                // Once per window: skip if we already reminded for the current
                // window (the stamp is cleared on renewal so next year fires).
                if (!$force
                    && $user->starter_renewal_reminder_sent_at
                    && $user->starter_free_window_ends_at
                    && $user->starter_renewal_reminder_sent_at->greaterThanOrEqualTo(
                        $user->starter_free_window_ends_at->copy()->subYear()
                    )) {
                    $skipped++;
                    continue;
                }

                $delivered = $this->remind($user, $prefs);
                if ($delivered) {
                    $user->forceFill(['starter_renewal_reminder_sent_at' => now()])->save();
                    $sent++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info("Starter free-window reminder run complete. Sent: {$sent}, skipped: {$skipped}.");
        return self::SUCCESS;
    }

    /**
     * Drop the in-app notification (honoring the in_app preference) and email
     * the user a re-confirmation with a one-click renew link. Returns true if
     * at least one channel was attempted so the per-window stamp is recorded.
     */
    private function remind(User $user, NotificationService $prefs): bool
    {
        // One-click renew CTA for the email: a GET click with no guaranteed
        // session, so use a signed URL whose signature authenticates the user.
        // Generous 60-day validity so the link stays usable across the whole
        // reminder window (and a few resends) without ever expiring mid-window.
        $renewUrl = URL::temporarySignedRoute(
            'user.starter.renew-free-window.link',
            now()->addDays(60),
            ['user' => $user->id]
        );
        $endsAt   = $user->starter_free_window_ends_at;

        // Track whether at least one channel actually delivered. We only stamp
        // starter_renewal_reminder_sent_at (the once-per-window guard) when a
        // reminder truly went out — otherwise a fully muted user, or an email
        // send failure, would suppress every later retry within the same window.
        $delivered = false;

        // In-app banner/list entry — points at the dashboard where the renew
        // banner lives. notify() returns null when the user muted the in_app
        // channel for this type, so a non-null result means it was created.
        if ($prefs->notify($user, 'starter.free_window_renewal', array_merge(self::inAppCopy(), [
            'url'      => \App\Modules\Common\Support\PlatformHosts::outboundUrl(route('user.dashboard')),
            'ends_at'  => $endsAt?->toIso8601String(),
        ])) !== null) {
            $delivered = true;
        }

        // Email — only if the user hasn't muted the email channel for this type.
        if ($prefs->prefersChannel($user->id, 'starter.free_window_renewal', 'email')) {
            try {
                \App\Modules\Common\Services\Emailer::send('starter.free_window_reminder', $user->email, [
                    'name'      => $user->name,
                    'renew_url' => $renewUrl,
                ], [
                    'user'      => $user->id,
                    'view_data' => ['user' => $user, 'renewUrl' => $renewUrl, 'endsAt' => $endsAt],
                ]);
                $delivered = true;
            } catch (\Throwable $e) {
                \Log::warning('Starter free-window reminder email failed for user ' . $user->id . ': ' . $e->getMessage());
            }
        }

        return $delivered;
    }
}
