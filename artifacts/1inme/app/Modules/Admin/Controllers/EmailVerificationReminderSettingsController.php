<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\Common\Support\EmailVerificationReminderSettings;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        ]);
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
}
