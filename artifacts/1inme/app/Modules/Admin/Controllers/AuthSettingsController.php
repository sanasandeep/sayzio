<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\AuthMethods;
use Illuminate\Http\Request;

class AuthSettingsController extends Controller
{
    /**
     * Login-method policy: email is always accepted; WhatsApp (mobile) OTP
     * login is behind a toggle with an editable allowed-country-code list.
     *
     * The WhatsApp delivery credentials live in config/whatsapp.php (env),
     * not here — this page only shows whether they're configured so an admin
     * knows codes will be sent live vs. logged in preview mode.
     */
    public function index()
    {
        $credsConfigured = config('whatsapp.phone_number_id') && config('whatsapp.access_token');

        return view('admin.auth-settings.index', [
            'mobileLoginEnabled'         => AuthMethods::mobileLoginEnabled(),
            'emailPasswordEnabled'       => AuthMethods::emailPasswordEnabled(),
            'emailOtpEnabled'            => AuthMethods::emailOtpEnabled(),
            'emailVerificationRequired'  => AuthMethods::emailVerificationRequired(),
            'registrationPaused'         => AuthMethods::registrationPaused(),
            'demoRevealOtpEnabled'       => AuthMethods::demoRevealOtpEnabled(),
            'allowedCodesText'     => implode("\n", AuthMethods::allowedCountryCodes()),
            'credsConfigured'      => (bool) $credsConfigured,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'email_password_enabled'       => ['nullable', 'boolean'],
            'email_otp_enabled'            => ['nullable', 'boolean'],
            'email_verification_required'  => ['nullable', 'boolean'],
            'registration_paused'          => ['nullable', 'boolean'],
            'demo_reveal_otp_enabled'      => ['nullable', 'boolean'],
            'mobile_login_enabled'         => ['nullable', 'boolean'],
            'allowed_country_codes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $emailPasswordEnabled = (bool) ($data['email_password_enabled'] ?? false);
        $emailOtpEnabled      = (bool) ($data['email_otp_enabled'] ?? false);

        // At least one email-based login method must stay on — otherwise an
        // admin could lock every user out (WhatsApp alone can't recover an
        // account and isn't available to everyone).
        if (!$emailPasswordEnabled && !$emailOtpEnabled) {
            return back()
                ->withErrors(['email_otp_enabled' => 'At least one email login method (password or one-time code) must stay enabled.'])
                ->withInput();
        }

        $codes = AuthMethods::normalizeCodes(
            preg_split('/[\s,]+/', (string) ($data['allowed_country_codes'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        );

        // Don't let an admin enable WhatsApp login with an empty allow-list —
        // that would reject every number. Fall back to the seeded defaults.
        if (empty($codes)) {
            $codes = AuthMethods::DEFAULT_ALLOWED_CODES;
        }

        AppSetting::put(AuthMethods::SETTING_EMAIL_PASSWORD_ENABLED, $emailPasswordEnabled);
        AppSetting::put(AuthMethods::SETTING_EMAIL_OTP_ENABLED, $emailOtpEnabled);
        AppSetting::put(
            AuthMethods::SETTING_EMAIL_VERIFICATION_REQUIRED,
            (bool) ($data['email_verification_required'] ?? false)
        );
        AppSetting::put(
            AuthMethods::SETTING_REGISTRATION_PAUSED,
            (bool) ($data['registration_paused'] ?? false)
        );
        AppSetting::put(
            AuthMethods::SETTING_DEMO_REVEAL_OTP,
            (bool) ($data['demo_reveal_otp_enabled'] ?? false)
        );
        AppSetting::put(AuthMethods::SETTING_MOBILE_ENABLED, (bool) ($data['mobile_login_enabled'] ?? false));
        AppSetting::put(AuthMethods::SETTING_ALLOWED_CODES, $codes);

        return back()->with('success', 'Login settings saved.');
    }
}
