<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use App\Modules\Common\Exceptions\EmailDeliveryException;
use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Services\Emailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    private const RESEND_MAX           = 3;
    private const RESEND_DECAY_SECONDS = 120;

    /**
     * Neutral message shown regardless of whether an account exists.
     * Must never change per-branch or the phrasing itself leaks account existence.
     */
    private const MSG_SENT    = 'If an account exists with that email, a reset link has been sent.';
    private const MSG_RESENT  = 'If an account exists with that email, a new reset link has been sent.';

    /**
     * Delivery-failure message: still starts with "If an account exists…" so it
     * does NOT confirm that an account was found — it only says delivery could
     * not be completed if one existed.
     */
    private const MSG_FAILED  = 'If an account exists with that email, we were unable to deliver the reset link at this time. Please check Admin → Mail Settings and try again.';

    public function showForgotForm()
    {
        return view('admin.auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $email = $request->email;
        $admin = Admin::where('email', $email)->first();

        if (!$admin) {
            // Neutral response — do not leak account existence.
            // Still persist the email so the resend affordance appears; a resend
            // for an unknown address will silently no-op with the same neutral text.
            if ($request->ajax()) {
                return response()->json(['ok' => true, 'status' => self::MSG_SENT, 'csrf_token' => csrf_token(), 'reset_email_sent_to' => $email]);
            }
            return back()
                ->with('status', self::MSG_SENT)
                ->with('reset_email_sent_to', $email);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $admin->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        $resetUrl = AppModulesCommonSupportPlatformHosts::outboundUrl(route('admin.password.reset', ['token' => $token, 'email' => $admin->email]));

        try {
            Emailer::send('admin.password_reset', $admin->email, [
                'reset_url' => $resetUrl,
            ], [
                'view_data'        => ['resetUrl' => $resetUrl, 'admin' => $admin],
                'throw_on_failure' => true,
            ]);
        } catch (EmailDeliveryException $e) {
            // Delivery failed — surface an honest but existence-neutral message.
            // The failed row is already written to email_logs by Emailer before
            // the exception is thrown, so the admin can inspect it there.
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'errors' => ['_' => self::MSG_FAILED], 'csrf_token' => csrf_token(), 'reset_email_sent_to' => $email]);
            }
            return back()->withInput()
                ->with('delivery_error', self::MSG_FAILED)
                ->with('delivery_error_log', $this->hasFailedResetLog())
                ->with('reset_email_sent_to', $email);
        }

        if ($request->ajax()) {
            return response()->json(['ok' => true, 'status' => self::MSG_SENT, 'csrf_token' => csrf_token(), 'reset_email_sent_to' => $email]);
        }
        return back()
            ->with('status', self::MSG_SENT)
            ->with('reset_email_sent_to', $email);
    }

    public function resendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $email       = $request->email;
        $throttleKey = 'admin-pwd-reset-resend:' . sha1($email . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::RESEND_MAX)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'errors' => ['_' => 'Please wait ' . $seconds . ' second(s) before requesting another link.'], 'csrf_token' => csrf_token()]);
            }
            return back()
                ->with('status', self::MSG_SENT)
                ->with('reset_email_sent_to', $email)
                ->with('resend_throttled', $seconds);
        }

        RateLimiter::hit($throttleKey, self::RESEND_DECAY_SECONDS);

        $admin = Admin::where('email', $email)->first();

        if (!$admin) {
            // Neutral response — do not leak account existence.
            if ($request->ajax()) {
                return response()->json(['ok' => true, 'status' => self::MSG_SENT, 'csrf_token' => csrf_token()]);
            }
            return back()
                ->with('status', self::MSG_SENT)
                ->with('reset_email_sent_to', $email);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $admin->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        $resetUrl = AppModulesCommonSupportPlatformHosts::outboundUrl(route('admin.password.reset', ['token' => $token, 'email' => $admin->email]));

        try {
            Emailer::send('admin.password_reset', $admin->email, [
                'reset_url' => $resetUrl,
            ], [
                'view_data'        => ['resetUrl' => $resetUrl, 'admin' => $admin],
                'throw_on_failure' => true,
            ]);
        } catch (EmailDeliveryException $e) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'errors' => ['_' => self::MSG_FAILED], 'csrf_token' => csrf_token()]);
            }
            return back()->withInput()
                ->with('delivery_error', self::MSG_FAILED)
                ->with('delivery_error_log', $this->hasFailedResetLog())
                ->with('reset_email_sent_to', $email);
        }

        if ($request->ajax()) {
            return response()->json(['ok' => true, 'status' => self::MSG_RESENT, 'csrf_token' => csrf_token()]);
        }
        return back()
            ->with('status', self::MSG_RESENT)
            ->with('reset_email_sent_to', $email);
    }

    /**
     * Whether a failed admin.password_reset row actually exists in email_logs.
     * Used to gate the "View error details" deep-link in the delivery-failure
     * banner. Checks the key only (not the recipient) so the presence of the
     * link never leaks whether the submitted address belongs to a real admin.
     */
    private function hasFailedResetLog(): bool
    {
        return EmailLog::query()
            ->where('email_key', 'admin.password_reset')
            ->where('status', 'failed')
            ->exists();
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'errors' => ['_' => 'This password reset link is invalid or has expired.'], 'csrf_token' => csrf_token()]);
            }
            return back()->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'errors' => ['_' => 'This password reset link has expired. Please request a new one.'], 'csrf_token' => csrf_token()]);
            }
            return back()->withErrors(['email' => 'This password reset link has expired.']);
        }

        $admin = Admin::where('email', $request->email)->first();
        if (!$admin) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'errors' => ['_' => 'Admin account not found.'], 'csrf_token' => csrf_token()]);
            }
            return back()->withErrors(['email' => 'Admin not found.']);
        }

        $admin->update(['password' => Hash::make($request->password)]);
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        if ($request->ajax()) {
            return response()->json(['ok' => true, 'redirect' => route('admin.login'), 'csrf_token' => csrf_token(), 'status' => 'Your password has been reset. Please log in.']);
        }
        return redirect()->route('admin.login')->with('success', 'Your password has been reset. Please log in.');
    }
}
