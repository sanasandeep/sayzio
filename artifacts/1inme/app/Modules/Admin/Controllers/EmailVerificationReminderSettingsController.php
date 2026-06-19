<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\Common\Support\EmailVerificationReminderSettings;
use Illuminate\Http\Request;

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
        ]);
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
