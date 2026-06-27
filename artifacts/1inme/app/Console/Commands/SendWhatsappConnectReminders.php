<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Weekly job that gently nudges users who still haven't shared a verified
 * WhatsApp number to add one and follow our channel. In-app only — it never
 * sends a WhatsApp message or an email; the goal is to grow the WhatsApp
 * audience and give every account a reliable contact channel.
 *
 * Guardrails so it never gets spammy:
 *   - Only targets users with NO verified phone (WhatsApp) identifier, filtered
 *     at the SQL level via whereDoesntHave so accounts that already have a
 *     number are never even loaded.
 *   - A short grace window after sign-up (--grace-days, default 3) so a
 *     brand-new account isn't nagged while it's still going through (or has
 *     just skipped) the post-registration WhatsApp step.
 *   - Honours the dashboard nudge's one-week snooze: if the user dismissed the
 *     card recently (`whatsapp_prompt_dismissed_at`), it stays quiet.
 *   - A per-user cooldown (--cooldown-days, default 6) recorded in
 *     `whatsapp_connect_notified_at` so re-runs / a daily safety schedule can't
 *     double-send within the same week.
 *   - Honours the per-user `whatsapp.connect_reminder` in-app preference.
 */
class SendWhatsappConnectReminders extends Command
{
    protected $signature = 'whatsapp:send-connect-reminders
        {--user= : Optional user id to remind (default: all eligible)}
        {--grace-days=3 : Skip accounts created within this many days}
        {--cooldown-days=6 : Minimum days between reminders for the same user}
        {--force : Ignore the grace / snooze / cooldown guards}';

    protected $description = 'Weekly in-app nudge for users with no verified WhatsApp number to add one and follow our channel.';

    /**
     * Single source of truth for the in-app notification copy so a future
     * admin preview/test-send tool can reuse it without drifting.
     *
     * @return array{title: string, message: string}
     */
    public static function inAppCopy(): array
    {
        return [
            'title'   => 'Add your WhatsApp number',
            'message' => 'Link and verify your WhatsApp number to sign in faster and stay reachable — and follow our channel for updates.',
        ];
    }

    public function handle(NotificationService $prefs): int
    {
        $force       = (bool) $this->option('force');
        $graceDays   = max(0, (int) $this->option('grace-days'));
        $cooldownDays = max(0, (int) $this->option('cooldown-days'));
        $userId      = $this->option('user');
        $now         = now();

        $query = User::query()
            ->where('status', 'active')
            ->whereDoesntHave('linkedIdentifiers', function ($q) {
                $q->where('kind', 'phone')->whereNotNull('verified_at');
            });

        if ($userId) {
            $query->where('id', $userId);
        }

        if (!$force && $graceDays > 0) {
            $query->where('created_at', '<=', $now->copy()->subDays($graceDays));
        }

        $sent = 0;
        $skipped = 0;

        $query->chunkById(200, function ($users) use (&$sent, &$skipped, $prefs, $force, $cooldownDays, $now) {
            foreach ($users as $user) {
                $settings = $user->settings ?? [];

                if (!$force) {
                    // Respect the dashboard nudge's one-week snooze.
                    $dismissedAt = $settings['whatsapp_prompt_dismissed_at'] ?? null;
                    if ($dismissedAt && $this->parse($dismissedAt)?->gt($now->copy()->subWeek())) {
                        $skipped++;
                        continue;
                    }

                    // Per-user cooldown so a re-run can't double-send.
                    $lastNotified = $settings['whatsapp_connect_notified_at'] ?? null;
                    if ($lastNotified && $this->parse($lastNotified)?->gt($now->copy()->subDays($cooldownDays))) {
                        $skipped++;
                        continue;
                    }
                }

                // In-app only. notify() returns null when the user muted the
                // in_app channel for this type, so a non-null result means it
                // was actually created — only then do we stamp the cooldown.
                $delivered = $prefs->notify($user, 'whatsapp.connect_reminder', array_merge(self::inAppCopy(), [
                    'url' => route('user.dashboard'),
                ])) !== null;

                if ($delivered) {
                    $settings['whatsapp_connect_notified_at'] = now()->toIso8601String();
                    $user->forceFill(['settings' => $settings])->save();
                    $sent++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info("WhatsApp connect reminder run complete. Sent: {$sent}, skipped: {$skipped}.");
        return self::SUCCESS;
    }

    private function parse(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
