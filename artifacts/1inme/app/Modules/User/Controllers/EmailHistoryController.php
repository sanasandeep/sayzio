<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Services\Emailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * User-facing "Email history" — lets a signed-in user see the transactional
 * emails the platform sent them and re-send the ones they're likely to need
 * again (invoices/receipts and email verification). Every row is scoped to the
 * authenticated user (by user_id) and to a safe allow-list of template keys,
 * and resend is throttled per user.
 */
class EmailHistoryController extends Controller
{
    /** Template keys a user may resend to themselves. */
    private const RESENDABLE_KEYS = [
        'billing.receipt',
        'billing.client_invoice',
        'billing.offline_renewal',
        'auth.verify_email',
        'auth.verify_email_reminder',
    ];

    private const RESEND_MAX = 5;
    private const RESEND_DECAY_SECONDS = 60;

    public function index(Request $request)
    {
        $user = $request->user();

        $logs = EmailLog::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('user.emails.index', [
            'logs'           => $logs,
            'resendableKeys' => self::RESENDABLE_KEYS,
        ]);
    }

    public function resend(Request $request, EmailLog $emailLog)
    {
        $user = $request->user();

        // Self-scope: only the user's own logs, only allow-listed keys, only
        // ones still addressed to their current email.
        abort_unless((int) $emailLog->user_id === (int) $user->id, 403);
        abort_unless(in_array($emailLog->email_key, self::RESENDABLE_KEYS, true), 403);

        if (!hash_equals(mb_strtolower((string) $emailLog->recipient), mb_strtolower((string) $user->email))) {
            return back()->with('error', 'This email was sent to a different address and cannot be resent here.');
        }

        $throttleKey = 'user-email-resend:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, self::RESEND_MAX)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->with('error', "Please wait {$seconds}s before requesting another resend.");
        }
        RateLimiter::hit($throttleKey, self::RESEND_DECAY_SECONDS);

        if (empty($emailLog->body) && empty($emailLog->subject)) {
            return back()->with('error', 'There is nothing stored to resend for this email.');
        }

        $new = Emailer::resend($emailLog);

        return $new->status === 'failed'
            ? back()->with('error', "Couldn't resend right now. Please try again shortly.")
            : back()->with('success', 'Resent to ' . $emailLog->recipient . '.');
    }
}
