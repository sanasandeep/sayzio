<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\OtpService;
use App\Modules\User\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showRegister(Request $request)
    {
        if (Auth::check()) return redirect()->route('user.dashboard');
        $prefilledRef = $request->query('ref') ?: $request->cookie(ReferralService::COOKIE_NAME);
        return view('user.auth.register', ['prefilledRef' => $prefilledRef]);
    }

    public function register(Request $request, ReferralService $referrals)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'nullable|string|max:20',
            'referral_code' => 'nullable|string|max:32',
        ];
        $validated = $request->validate($rules);

        // If a referral code was submitted, ensure it resolves to a real user;
        // otherwise drop it silently and fall back to the cookie attribution.
        $submittedCode = $validated['referral_code'] ?? null;
        if ($submittedCode && !$referrals->findReferrerByCode($submittedCode)) {
            return back()->withErrors(['referral_code' => 'That referral code is not valid.'])->withInput();
        }

        $freePlan = Plan::where('slug', 'free')->first();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'] ?? null,
            // Password column is NOT NULL but unused — fill with an
            // unguessable random hash so the OTP flow is the only way in.
            'password' => Hash::make(Str::random(48)),
            'plan_id' => $freePlan?->id,
            'status' => 'active',
            'referral_code' => $referrals->generateUniqueCode(),
        ]);

        $cookieCode = $request->cookie(ReferralService::COOKIE_NAME);
        $referrals->attributeSignup($user, $submittedCode, $cookieCode, $request->ip(), $request->userAgent());

        // Send a login OTP and route the new user through verification.
        $otpService = new OtpService();
        $code = $otpService->generate($user->email, 'email', 'login', 'web');
        try {
            $otpService->sendEmail($user->email, $code);
        } catch (\Exception $e) {
            \Log::warning('OTP email failed: ' . $e->getMessage());
        }

        session([
            'otp_identifier' => $user->email,
            'otp_type'       => 'email',
        ]);

        return redirect()->route('user.otp.verify.form')
            ->with('status', 'Account created. We sent a 6-digit code to ' . $user->email . '.');
    }

    public function showLogin()
    {
        if (Auth::check()) return redirect()->route('user.dashboard');
        return view('user.auth.login');
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'type' => 'required|in:email,mobile',
        ]);

        $identifier = $request->identifier;
        $type = $request->type;

        $user = $this->resolveUserByIdentifier($identifier, $type);

        if (!$user) {
            session(['otp_identifier' => $identifier, 'otp_type' => $type]);
            return redirect()->route('user.otp.verify.form')->with('status', 'If an account exists, an OTP has been sent to your ' . $type . '.');
        }

        $otpService = new OtpService();
        $code = $otpService->generate($identifier, $type, 'login', 'web');

        if ($type === 'email') {
            $otpService->sendEmail($identifier, $code);
        } else {
            $otpService->sendSms($identifier, $code);
        }

        session(['otp_identifier' => $identifier, 'otp_type' => $type]);
        // Regular login flow — clear any stale merge-challenge marker so
        // we don't accidentally hijack the session into a merge.
        session()->forget('merge_challenge_active');

        return redirect()->route('user.otp.verify.form')->with('status', 'OTP sent to your ' . $type . '.');
    }

    public function resendOtp(Request $request)
    {
        $identifier = session('otp_identifier');
        $type = session('otp_type', 'email');
        if (!$identifier) {
            return redirect()->route('user.login');
        }

        // Only generate/send when a real user matches the session identifier.
        // Always show a generic success so we don't leak account existence.
        $user = $this->resolveUserByIdentifier($identifier, $type);

        if ($user) {
            $otpService = new OtpService();
            $code = $otpService->generate($identifier, $type, 'login', 'web');
            try {
                if ($type === 'email') {
                    $otpService->sendEmail($identifier, $code);
                } else {
                    $otpService->sendSms($identifier, $code);
                }
            } catch (\Exception $e) {
                \Log::warning('Resend OTP failed: ' . $e->getMessage());
            }
        }

        return back()->with('status', 'If your account exists, a new code was sent to your ' . $type . '.');
    }

    public function showOtpVerify()
    {
        if (!session('otp_identifier')) {
            return redirect()->route('user.login');
        }
        return view('user.auth.otp-verify');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $identifier = session('otp_identifier');
        $type = session('otp_type', 'email');

        if (!$identifier) {
            return redirect()->route('user.login')->withErrors(['code' => 'Session expired. Please try again.']);
        }

        $otpService = new OtpService();
        if (!$otpService->verify($identifier, $request->code, $type, 'login', 'web')) {
            return back()->withErrors(['code' => 'Invalid or expired OTP.']);
        }

        // If this is a "merge another account" challenge, hand off to the
        // merge flow instead of swapping the active session.
        if (session('merge_challenge_active')) {
            $other = $this->resolveUserByIdentifier($identifier, $type);
            session()->forget(['otp_identifier', 'otp_type', 'merge_challenge_active']);
            if (!$other) {
                return redirect()->route('user.merge.start')
                    ->withErrors(['code' => 'No account matched that identifier.']);
            }
            session([
                'merge_secondary_id' => $other->id,
                'merge_primary_id'   => Auth::id(),
            ]);
            return redirect()->route('user.merge.preview');
        }

        $user = $this->resolveUserByIdentifier($identifier, $type);

        if ($user) {
            Auth::login($user, true);
            $user->update(['last_login_at' => now()]);
            session()->forget(['otp_identifier', 'otp_type']);
            $request->session()->regenerate();
            return redirect()->intended(route('user.dashboard'));
        }

        return redirect()->route('user.login')->withErrors(['code' => 'User not found.']);
    }

    /**
     * Resolve any verified linked identifier (email/phone) to its owning
     * user. Falls back to the legacy users.email / users.mobile columns
     * for accounts predating the linked-identifiers backfill.
     */
    private function resolveUserByIdentifier(string $identifier, string $type): ?User
    {
        $kind = $type === 'mobile' ? 'phone' : 'email';
        $user = LinkedIdentifier::resolveUser($kind, $identifier);
        if ($user) return $user;
        return $type === 'email'
            ? User::where('email', $identifier)->first()
            : User::where('mobile', $identifier)->first();
    }

    public function demoLogin(Request $request)
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $user = User::where('email', 'demo@1inme.com')->first();

        if (!$user) {
            $freePlan = Plan::where('slug', 'free')->first();
            $user = User::create([
                'name' => 'Demo User',
                'email' => 'demo@1inme.com',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'plan_id' => $freePlan?->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user);
        $user->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        return redirect()->route('user.dashboard');
    }

    public function showVerifyEmail()
    {
        if (Auth::user()->email_verified_at) {
            return redirect()->route('user.dashboard');
        }
        return view('user.auth.verify-email');
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if ((int) $id !== (int) Auth::id()) {
            abort(403, 'You can only verify your own email.');
        }

        if (!hash_equals(sha1($user->email), $hash)) {
            abort(403, 'Invalid verification link.');
        }

        if (!$user->email_verified_at) {
            $user->update(['email_verified_at' => now()]);
        }

        return redirect()->route('user.dashboard')->with('success', 'Email verified successfully.');
    }

    public function resendVerification(Request $request)
    {
        $user = Auth::user();

        if ($user->email_verified_at) {
            return back()->with('status', 'Email already verified.');
        }

        try {
            $verificationUrl = URL::temporarySignedRoute(
                'user.verification.verify',
                now()->addHours(24),
                ['id' => $user->id, 'hash' => sha1($user->email)]
            );

            Mail::send('emails.verify-email', ['verificationUrl' => $verificationUrl, 'user' => $user], function ($message) use ($user) {
                $message->to($user->email);
                $message->subject('Verify Your Email - 1INME');
            });
        } catch (\Exception $e) {
            \Log::warning('Verification email resend failed: ' . $e->getMessage());
        }

        return back()->with('status', 'Verification link sent.');
    }

    public function logout(Request $request)
    {
        if (session()->has('admin_id')) {
            $adminId = session('admin_id');
            session()->forget(['impersonate_user_id', 'admin_id']);
            Auth::logout();
            Auth::guard('admin')->loginUsingId($adminId);
            return redirect()->route('admin.users.index')->with('success', 'Impersonation stopped.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('user.login');
    }
}
