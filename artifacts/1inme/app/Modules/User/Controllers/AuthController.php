<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class AuthController extends Controller
{
    public function showRegister()
    {
        if (Auth::check()) return redirect()->route('user.dashboard');
        return view('user.auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $freePlan = Plan::where('slug', 'free')->first();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'] ?? null,
            'password' => Hash::make($validated['password']),
            'plan_id' => $freePlan?->id,
            'status' => 'active',
        ]);

        Auth::login($user);

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
            \Log::warning('Verification email failed: ' . $e->getMessage());
        }

        return redirect()->route('user.dashboard');
    }

    public function showLogin()
    {
        if (Auth::check()) return redirect()->route('user.dashboard');
        return view('user.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            Auth::user()->update(['last_login_at' => now()]);
            return redirect()->intended(route('user.dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'type' => 'required|in:email,mobile',
        ]);

        $identifier = $request->identifier;
        $type = $request->type;

        if ($type === 'email') {
            $user = User::where('email', $identifier)->first();
        } else {
            $user = User::where('mobile', $identifier)->first();
        }

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

        return redirect()->route('user.otp.verify.form')->with('status', 'OTP sent to your ' . $type . '.');
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

        if ($type === 'email') {
            $user = User::where('email', $identifier)->first();
        } else {
            $user = User::where('mobile', $identifier)->first();
        }

        if ($user) {
            Auth::login($user, true);
            $user->update(['last_login_at' => now()]);
            session()->forget(['otp_identifier', 'otp_type']);
            $request->session()->regenerate();
            return redirect()->intended(route('user.dashboard'));
        }

        return redirect()->route('user.login')->withErrors(['code' => 'User not found.']);
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
