<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\NotificationService;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\Common\Support\EmailVerificationReminderSettings;
use App\Modules\User\Models\EmailVerificationReminderSend;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Daily job that gently nudges users who still haven't verified their
 * email. The in-app banner only nudges users while they are actively
 * signed in, so users who skipped verification at sign-up and rarely log
 * back in are never reminded. This emails them a fresh verification link.
 *
 * Guardrails so it never becomes spammy:
 *   - Only targets users with a null `email_verified_at` (so it stops the
 *     moment they verify) who have a real email on file.
 *   - Skips entirely when email verification can't meaningfully apply
 *     under the current login policy (AuthMethods::emailVerificationMeaningful()
 *     — e.g. a mobile-only configuration), matching the banner's rule.
 *   - A short grace period after sign-up (admin-tunable grace days) so the
 *     original sign-up email + in-app banner get a chance first.
 *   - At most one reminder every interval (admin-tunable), capped at a
 *     total per user (admin-tunable).
 *   - The whole feature can be switched off platform-wide by an admin.
 *   - Honours the per-user `email_verification_reminder` email preference
 *     (default on) and a one-click signed unsubscribe link.
 */
class SendEmailVerificationReminders extends Command
{
    protected $signature = 'users:send-email-verification-reminders
        {--user= : Optional user id to remind (default: all eligible)}
        {--force : Ignore the grace period / interval / cap and send to any unverified, opted-in user}';

    protected $description = 'Email users who still have an unverified email a gentle, rate-limited reminder to verify it.';

    public function handle(NotificationService $prefs): int
    {
        $force  = (bool) $this->option('force');

        // Admins can switch the whole feature off platform-wide. --force
        // still respects this kill switch (it only bypasses the cadence).
        if (! EmailVerificationReminderSettings::enabled()) {
            $this->info('Email verification reminders are disabled in admin settings; nothing to do.');
            return self::SUCCESS;
        }

        // Verification only matters when email actually authenticates an
        // account. In a mobile-only login policy the email never verifies,
        // so a reminder would be pointless — bail out wholesale.
        if (! AuthMethods::emailVerificationMeaningful()) {
            $this->info('Email verification is not meaningful under the current login policy; nothing to do.');
            return self::SUCCESS;
        }

        $graceDays    = EmailVerificationReminderSettings::graceDays();
        $intervalDays = EmailVerificationReminderSettings::intervalDays();
        $maxReminders = EmailVerificationReminderSettings::maxReminders();

        $userId = $this->option('user');
        $now    = now();

        $query = User::query()
            ->whereNull('email_verified_at')
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($userId) {
            $query->where('id', $userId);
        }

        if (! $force) {
            // Cap: never exceed the configured total.
            $query->where('email_verification_reminders_sent', '<', $maxReminders);
        }

        $sent = 0;
        $skipped = 0;

        $query->chunkById(200, function ($users) use (&$sent, &$skipped, $prefs, $now, $force, $graceDays, $intervalDays) {
            foreach ($users as $user) {
                if (! $force) {
                    // Grace period for users who just signed up.
                    if ($user->created_at
                        && $user->created_at->greaterThan($now->copy()->subDays($graceDays))) {
                        $skipped++;
                        continue;
                    }

                    // Interval since the last reminder.
                    if ($user->email_verification_reminder_sent_at
                        && $user->email_verification_reminder_sent_at
                            ->greaterThan($now->copy()->subDays($intervalDays))) {
                        $skipped++;
                        continue;
                    }
                }

                // Respect the user's email preference for this type.
                if (! $prefs->prefersChannel($user->id, 'email_verification_reminder', 'email')) {
                    $skipped++;
                    continue;
                }

                if ($this->sendReminder($user)) {
                    $sentAt = now();
                    $user->forceFill([
                        'email_verification_reminders_sent' => (int) ($user->email_verification_reminders_sent ?? 0) + 1,
                        'email_verification_reminder_sent_at' => $sentAt,
                    ])->save();

                    // Log this individual send so the admin trend can report
                    // exact per-week counts instead of the most-recent-timestamp
                    // proxy. Best-effort: never let a logging hiccup undo a send.
                    try {
                        EmailVerificationReminderSend::create([
                            'user_id' => $user->id,
                            'sent_at' => $sentAt,
                        ]);
                    } catch (\Throwable $e) {
                        \Log::warning('Failed to log email verification reminder send for user ' . $user->id . ': ' . $e->getMessage());
                    }

                    $sent++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info("Email verification reminder run complete. Sent: {$sent}, skipped: {$skipped}.");
        return self::SUCCESS;
    }

    /**
     * Build the signed verification link (the same one the in-app resend
     * uses) plus a one-click unsubscribe link and email the reminder.
     * Returns true on success so the caller can stamp the counter.
     */
    private function sendReminder(User $user): bool
    {
        try {
            $verificationUrl = URL::temporarySignedRoute(
                'user.verification.verify',
                now()->addDays(7),
                ['id' => $user->id, 'hash' => sha1($user->email)]
            );

            $unsubscribeUrl = URL::signedRoute(
                'user.notifications.email-verification-reminder.unsubscribe',
                ['user' => $user->id]
            );

            \App\Modules\Common\Services\Emailer::send('auth.verify_email_reminder', $user->email, [
                'name'             => $user->name,
                'verification_url' => $verificationUrl,
            ], [
                'user'      => $user->id,
                'related'   => $user,
                'view_data' => ['user' => $user, 'verificationUrl' => $verificationUrl, 'unsubscribeUrl' => $unsubscribeUrl],
                'headers'   => [
                    'List-Unsubscribe'      => '<' . $unsubscribeUrl . '>',
                    'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                ],
            ]);

            return true;
        } catch (\Throwable $e) {
            \Log::warning('Email verification reminder send failed for user ' . $user->id . ': ' . $e->getMessage());
            return false;
        }
    }
}
