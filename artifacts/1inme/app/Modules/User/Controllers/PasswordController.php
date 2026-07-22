<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\OtpService;
use App\Modules\User\Services\UserPasswordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Authenticated self-serve password management on the Security tab.
 *
 * Two variants share the single update endpoint:
 *   - accounts with a user-chosen password confirm their CURRENT password;
 *   - accounts whose password was never user-chosen (OTP / social sign-ups
 *     keep a random filler hash) verify a one-time code instead, requested
 *     via {@see sendSetPasswordCode()} (purpose 'set_password' so a login
 *     OTP can never be replayed here).
 */
class PasswordController extends Controller
{
    public function __construct(
        private OtpService $otp,
        private UserPasswordService $passwords,
    ) {}

    /** POST /user/settings/security/password/code — OTP for the set-first-password variant. */
    public function sendSetPasswordCode(Request $request)
    {
        $user = $request->user();

        if ($this->passwords->hasChosenPassword($user)) {
            return back()->withErrors(['password' => 'Your account already has a password — confirm your current password instead.']);
        }

        [$identifier, $type] = $this->codeChannel($user);
        if (!$identifier) {
            return back()->withErrors(['password' => 'We have no email or WhatsApp number on file to send a verification code to.']);
        }

        $code = $this->otp->generate($identifier, $type, 'set_password', 'web', $request->ip());
        $type === 'email' ? $this->otp->sendEmail($identifier, $code) : $this->otp->sendWhatsApp($identifier, $code);

        return back()->with('password_code_sent', $type === 'email'
            ? 'We sent a 6-digit code to your email address.'
            : 'We sent a 6-digit code to your WhatsApp number.');
    }

    /** POST /user/settings/security/password — change or set-first password. */
    public function update(Request $request)
    {
        $user = $request->user();
        $hasChosen = $this->passwords->hasChosenPassword($user);

        $rules = ['password' => ['required', 'string', 'min:8', 'max:72', 'confirmed']];
        if ($hasChosen) {
            $rules['current_password'] = ['required', 'string'];
        } else {
            $rules['code'] = ['required', 'digits:6'];
        }
        $data = $request->validate($rules);

        if ($hasChosen) {
            if (!Hash::check($data['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'That does not match your current password.']);
            }
        } else {
            [$identifier, $type] = $this->codeChannel($user);
            if (!$identifier || !$this->otp->verify($identifier, $data['code'], $type, 'set_password', 'web')) {
                return back()->withErrors(['code' => 'Invalid or expired code. Please request a new one.']);
            }
        }

        $this->passwords->apply($user, $data['password'], $request->session()->getId(), null, 'changed');

        // apply() rotated the remember token; refresh this session's auth so
        // the current user stays signed in.
        auth()->login($user->fresh(), true);
        $request->session()->regenerate();

        return redirect()->route('user.account.two-factor.show')
            ->with('success', $hasChosen
                ? 'Your password has been changed. Every other device has been signed out.'
                : 'Your password has been set. Every other device has been signed out.');
    }

    /** @return array{0:?string,1:string} [identifier, type] for the set-password OTP. */
    private function codeChannel($user): array
    {
        if (!blank($user->email)) {
            return [$user->email, 'email'];
        }
        if (!blank($user->mobile)) {
            return [$user->mobile, 'mobile'];
        }
        return [null, 'email'];
    }
}
