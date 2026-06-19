<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\Common\Support\EmailVerificationReminderSettings;
use App\Modules\User\Models\User;
use App\Services\Integrations\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

/**
 * Admin control over the cadence of the periodic email-verification
 * reminder (`users:send-email-verification-reminders`). Lets an admin tune
 * the grace period, reminder interval, and total cap — or switch the whole
 * feature off — without a code change. Backed by AppSetting, mirroring the
 * AuthMethods / mail-settings pattern.
 */
class EmailVerificationReminderSettingsController extends Controller
{
    public function index()
    {
        return view('admin.email-verification-reminders.index', [
            'enabled'      => EmailVerificationReminderSettings::enabled(),
            'graceDays'    => EmailVerificationReminderSettings::graceDays(),
            'intervalDays' => EmailVerificationReminderSettings::intervalDays(),
            'maxReminders' => EmailVerificationReminderSettings::maxReminders(),
            // Surface whether reminders can actually fire under the current
            // login policy, so the admin understands a no-op even when "on".
            'verificationMeaningful' => AuthMethods::emailVerificationMeaningful(),
            // Impact stats so the cadence isn't a blind toggle.
            'stats' => $this->stats(),
            // Short weekly trend so admins can see the cadence over time, not
            // just today's snapshot.
            'trend' => $this->trend(),
        ]);
    }

    /**
     * Build a compact weekly trend of reminders and conversions over the last
     * several weeks, derived from existing timestamps (no per-send log exists).
     *
     * Because only each user's *most recent* reminder timestamp is stored,
     * "reminded" is the count of users whose latest reminder fell in that week
     * (a close proxy for reminders sent), and "converted" is users who had at
     * least one reminder and verified during that week.
     */
    private function trend(int $weeks = 8): array
    {
        $start = Carbon::now()->startOfWeek()->subWeeks($weeks - 1);

        $reminded = $this->weeklyCounts(
            User::query()
                ->whereNotNull('email_verification_reminder_sent_at')
                ->where('email_verification_reminder_sent_at', '>=', $start),
            'email_verification_reminder_sent_at'
        );

        $converted = $this->weeklyCounts(
            User::query()
                ->whereNotNull('email_verified_at')
                ->where('email_verified_at', '>=', $start)
                ->where('email_verification_reminders_sent', '>', 0),
            'email_verified_at'
        );

        $series = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $key       = $weekStart->format('Y-m-d');
            $series[]  = [
                'weekStart' => $weekStart,
                'label'     => $weekStart->format('M j'),
                'reminded'  => (int) ($reminded[$key] ?? 0),
                'converted' => (int) ($converted[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Group a query into per-week counts keyed by the ISO week-start date
     * (Y-m-d, Monday) so it lines up with Carbon's startOfWeek().
     * The column is internal/whitelisted, never user input.
     */
    private function weeklyCounts($query, string $column): array
    {
        return $query
            ->selectRaw("date_trunc('week', {$column}) as wk, count(*) as c")
            ->groupBy('wk')
            ->get()
            ->mapWithKeys(fn ($row) => [Carbon::parse($row->wk)->format('Y-m-d') => (int) $row->c])
            ->all();
    }

    /**
     * Compute reminder-impact stats from the existing per-user columns
     * (`email_verification_reminders_sent`, `email_verification_reminder_sent_at`,
     * `email_verified_at`) — no new schema. Gives admins a feel for whether
     * the cadence is too aggressive or too quiet.
     */
    private function stats(): array
    {
        $maxReminders = EmailVerificationReminderSettings::maxReminders();
        $now          = Carbon::now();

        // Active users who still haven't verified — the eligible population
        // (mirrors the command's targeting, minus the email-present filter
        // which only affects deliverability, not "still unverified").
        $unverifiedActive = User::query()
            ->whereNull('email_verified_at')
            ->where('status', 'active')
            ->count();

        // Distinct users reminded in the last 30 days. We only store each
        // user's most-recent reminder timestamp, so this is "users reminded
        // recently", not a raw send count.
        $remindedLast30Days = User::query()
            ->whereNotNull('email_verification_reminder_sent_at')
            ->where('email_verification_reminder_sent_at', '>=', $now->copy()->subDays(30))
            ->count();

        // The job runs at most daily and stamps every send with `now()`, so
        // the most recent reminder timestamp anchors the last run. Count the
        // users stamped within a 12-hour window of it to capture that single
        // run without bleeding into the previous day's run.
        $lastRunAt    = User::query()->max('email_verification_reminder_sent_at');
        $lastRunAt    = $lastRunAt ? Carbon::parse($lastRunAt) : null;
        $lastRunCount = 0;
        if ($lastRunAt) {
            $lastRunCount = User::query()
                ->where('email_verification_reminder_sent_at', '>=', $lastRunAt->copy()->subHours(12))
                ->count();
        }

        // Still-unverified users who've exhausted their reminder allowance.
        $cappedUnverified = User::query()
            ->whereNull('email_verified_at')
            ->where('status', 'active')
            ->where('email_verification_reminders_sent', '>=', $maxReminders)
            ->count();

        // Users who received at least one reminder and have since verified —
        // a rough conversion signal for the nudges.
        $converted = User::query()
            ->whereNotNull('email_verified_at')
            ->where('email_verification_reminders_sent', '>', 0)
            ->count();

        return [
            'unverifiedActive'   => $unverifiedActive,
            'lastRunAt'          => $lastRunAt,
            'lastRunCount'       => $lastRunCount,
            'remindedLast30Days' => $remindedLast30Days,
            'cappedUnverified'   => $cappedUnverified,
            'converted'          => $converted,
        ];
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled'       => ['nullable', 'boolean'],
            'grace_days'    => [
                'required', 'integer',
                'min:' . EmailVerificationReminderSettings::MIN_GRACE_DAYS,
                'max:' . EmailVerificationReminderSettings::MAX_GRACE_DAYS,
            ],
            'interval_days' => [
                'required', 'integer',
                'min:' . EmailVerificationReminderSettings::MIN_INTERVAL_DAYS,
                'max:' . EmailVerificationReminderSettings::MAX_INTERVAL_DAYS,
            ],
            'max_reminders' => [
                'required', 'integer',
                'min:' . EmailVerificationReminderSettings::MIN_MAX_REMINDERS,
                'max:' . EmailVerificationReminderSettings::MAX_MAX_REMINDERS,
            ],
        ]);

        AppSetting::put(EmailVerificationReminderSettings::SETTING_ENABLED, (bool) ($data['enabled'] ?? false));
        AppSetting::put(EmailVerificationReminderSettings::SETTING_GRACE_DAYS, (int) $data['grace_days']);
        AppSetting::put(EmailVerificationReminderSettings::SETTING_INTERVAL_DAYS, (int) $data['interval_days']);
        AppSetting::put(EmailVerificationReminderSettings::SETTING_MAX_REMINDERS, (int) $data['max_reminders']);

        return back()->with('success', 'Verification reminder settings saved.');
    }

    /**
     * Send the rendered verification-reminder email to the logged-in admin's
     * own address so they can preview exactly what users receive and confirm
     * SMTP delivery before going live — without hunting for a real unverified
     * user or waiting for the schedule. Mirrors the mail-settings "send test
     * email" pattern. Rate-limited so it can't be spammed.
     */
    public function sendSample(Request $request)
    {
        $admin = Auth::guard('admin')->user() ?: $request->user();
        if (! $admin || empty($admin->email)) {
            return back()->with('error', 'We could not find an email address on your admin account to send the sample to.');
        }

        $rateKey = 'verify-reminder-sample:' . $admin->id;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $minutes = max(1, (int) ceil($seconds / 60));
            return back()->with('error', "You've sent a few sample reminders recently — please try again in about {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.');
        }
        RateLimiter::hit($rateKey, 600);

        // Apply the saved SMTP settings to the current process before sending,
        // so the sample reflects exactly what's configured.
        MailSettings::applyRuntimeConfig();

        // Use the admin's matching User account when one exists, so the
        // verification + unsubscribe links are real "self" links; otherwise
        // fall back to a display-only placeholder recipient.
        $previewUser = User::where('email', $admin->email)->first();

        if ($previewUser) {
            $verificationUrl = URL::temporarySignedRoute(
                'user.verification.verify',
                now()->addDays(7),
                ['id' => $previewUser->id, 'hash' => sha1($previewUser->email)]
            );
            $unsubscribeUrl = URL::signedRoute(
                'user.notifications.email-verification-reminder.unsubscribe',
                ['user' => $previewUser->id]
            );
            $recipient = $previewUser;
        } else {
            $previewUser = new User();
            $previewUser->name  = $admin->name ?: 'there';
            $previewUser->email = $admin->email;
            // Placeholder links so the template renders fully; they point at
            // a non-existent account and simply won't resolve if clicked.
            $verificationUrl = url('/verify-email/0/' . sha1($admin->email) . '?sample=1');
            $unsubscribeUrl  = url('/notifications/email-verification-reminder/unsubscribe/0?sample=1');
            $recipient = $previewUser;
        }

        try {
            Mail::send(
                'emails.verify-email-reminder',
                ['user' => $recipient, 'verificationUrl' => $verificationUrl, 'unsubscribeUrl' => $unsubscribeUrl],
                function ($message) use ($admin, $unsubscribeUrl) {
                    $message->to($admin->email);
                    $message->subject('[Sample] Reminder: verify your 1INME email');
                    $message->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
                    $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                }
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Sample reminder failed: ' . $e->getMessage());
        }

        if (MailSettings::mailer() === 'log') {
            return back()->with('info', 'The mailer is set to "log" — the sample was written to the log, not delivered. Choose the SMTP mailer to send live.');
        }

        return back()->with('success', 'Sample verification reminder dispatched to ' . $admin->email . '.');
    }
}
