<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Exceptions\EmailDeliveryException;
use App\Modules\Common\Services\Emailer;
use App\Modules\User\Models\User;
use App\Modules\User\Services\UserPasswordService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Public forgot-password flow for regular users, mirroring the admin
 * PasswordResetController mechanics: password_reset_tokens rows keyed by
 * email (namespaced with a `user:` prefix so a user reset can never redeem
 * an admin token or vice versa), Str::random(64) token stored Hash::make'd,
 * 60-minute expiry, and existence-neutral responses throughout.
 *
 * Email-null (WhatsApp-only) accounts simply never match an email lookup,
 * so they fall into the same neutral "if an account exists" path.
 */
class PasswordResetController extends Controller
{
    private const MSG_SENT = 'If an account exists with that email, a reset link has been sent.';

    /**
     * Namespace user rows in the shared password_reset_tokens table so the
     * admin flow (keyed on the bare email) and this one can never collide.
     */
    private static function tokenKey(string $email): string
    {
        return 'user:' . strtolower(trim($email));
    }

    public function showForgotForm()
    {
        return view('user.auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $email = strtolower(trim($request->email));
        $user  = User::whereRaw('lower(email) = ?', [$email])->first();

        if (!$user) {
            // Neutral — never leak whether the account exists.
            return back()->with('status', self::MSG_SENT)->with('reset_email_sent_to', $email);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => self::tokenKey($user->email)],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        $resetUrl = route('user.password.reset', ['token' => $token, 'email' => $user->email]);

        try {
            Emailer::send('user.password_reset', $user->email, [
                'name'      => $user->name ?: 'there',
                'reset_url' => $resetUrl,
            ], [
                'throw_on_failure' => true,
            ]);
        } catch (EmailDeliveryException $e) {
            // Existence-neutral delivery-failure message.
            return back()->withInput()
                ->with('delivery_error', 'If an account exists with that email, we were unable to deliver the reset link at this time. Please try again later.')
                ->with('reset_email_sent_to', $email);
        }

        return back()->with('status', self::MSG_SENT)->with('reset_email_sent_to', $email);
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('user.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request, UserPasswordService $passwords)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|max:72|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', self::tokenKey($request->email))
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', self::tokenKey($request->email))->delete();
            return back()->withErrors(['email' => 'This password reset link has expired. Please request a new one.']);
        }

        $user = User::whereRaw('lower(email) = ?', [strtolower(trim($request->email))])->first();
        if (!$user) {
            return back()->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        // Signs out every session and token — the visitor is unauthenticated
        // here, so nothing is kept.
        $passwords->apply($user, $request->password, null, null, 'reset');

        DB::table('password_reset_tokens')->where('email', self::tokenKey($request->email))->delete();

        return redirect()->route('user.login')
            ->with('status', 'Your password has been reset. Please sign in with your new password.');
    }
}
