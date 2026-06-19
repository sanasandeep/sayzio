<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\NotificationService;
use App\Modules\Common\Support\AuthMethods;
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
 *   - A short grace period after sign-up (FIRST_REMINDER_AFTER_DAYS) so the
 *     original sign-up email + in-app banner get a chance first.
 *   - At most one reminder every REMINDER_INTERVAL_DAYS, capped at
 *     MAX_REMINDERS total per user.
 *   - Honours the per-user `email_verification_reminder` email preference
 *     (default on) and a one-click signed unsubscribe link.
 */
class SendEmailVerificationReminders extends Command
{
    protected $signature = 'users:send-email-verification-reminders
        {--user= : Optional user id to remind (default: all eligible)}
        {--force : Ignore the grace period / interval / cap and send to any unverified, opted-in user}';

    protected $description = 'Email users who still have an unverified email a gentle, rate-limited reminder to verify it.';

    /** Wait this many days after sign-up before the first reminder. */
    private const FIRST_REMINDER_AFTER_DAYS = 3;

    /** Minimum gap between two reminders to the same user. */
    private const REMINDER_INTERVAL_DAYS = 7;

    /** Hard cap on the total number of reminders a user ever receives. */
    private const MAX_REMINDERS = 3;

    public function handle(NotificationService $prefs): int
    {
        // Verification only matters when email actually authenticates an
        // account. In a mobile-only login policy the email never verifies,
        // so a reminder would be pointless — bail out wholesale.
        if (! AuthMethods::emailVerificationMeaningful()) {
            $this->info('Email verification is not meaningful under the current login policy; nothing to do.');
            return self::SUCCESS;
        }

        $force  = (bool) $this->option('force');
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
            // Cap: never exceed MAX_REMINDERS total.
            $query->where('email_verification_reminders_sent', '<', self::MAX_REMINDERS);
        }

        $sent = 0;
        $skipped = 0;

        $query->chunkById(200, function ($users) use (&$sent, &$skipped, $prefs, $now, $force) {
            foreach ($users as $user) {
                if (! $force) {
                    // Grace period for users who just signed up.
                    if ($user->created_at
                        && $user->created_at->greaterThan($now->copy()->subDays(self::FIRST_REMINDER_AFTER_DAYS))) {
                        $skipped++;
                        continue;
                    }

                    // Interval since the last reminder.
                    if ($user->email_verification_reminder_sent_at
                        && $user->email_verification_reminder_sent_at
                            ->greaterThan($now->copy()->subDays(self::REMINDER_INTERVAL_DAYS))) {
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
                    $user->forceFill([
                        'email_verification_reminders_sent' => (int) ($user->email_verification_reminders_sent ?? 0) + 1,
                        'email_verification_reminder_sent_at' => now(),
                    ])->save();
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

            Mail::send(
                'emails.verify-email-reminder',
                ['user' => $user, 'verificationUrl' => $verificationUrl, 'unsubscribeUrl' => $unsubscribeUrl],
                function ($message) use ($user, $unsubscribeUrl) {
                    $message->to($user->email);
                    $message->subject('Reminder: verify your 1INME email');
                    $message->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
                    $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                }
            );

            return true;
        } catch (\Throwable $e) {
            \Log::warning('Email verification reminder send failed for user ' . $user->id . ': ' . $e->getMessage());
            return false;
        }
    }
}
