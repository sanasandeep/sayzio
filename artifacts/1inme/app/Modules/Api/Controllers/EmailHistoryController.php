<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Services\Emailer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Bearer-token parity for the user-facing "Email history" screen so a
 * signed-in user can see the transactional emails the platform sent them and
 * resend the ones they're likely to need again (invoices/receipts and email
 * verification) from the Sayzio Mobile app.
 *
 * Mirrors the web {@see \App\Modules\User\Controllers\EmailHistoryController}:
 * every row is scoped to the authenticated user and to a safe allow-list of
 * template keys, the recipient must still match the user's email, and resend
 * is throttled per user.
 */
class EmailHistoryController extends Controller
{
    use ApiResponses;

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

        return $this->ok([
            'emails' => collect($logs->items())->map(fn (EmailLog $l) => [
                'id'         => $l->id,
                'subject'    => $l->subject,
                'status'     => $l->status,
                'created_at' => optional($l->created_at)->toIso8601String(),
                'resendable' => in_array($l->email_key, self::RESENDABLE_KEYS, true),
            ])->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    public function resend(Request $request, EmailLog $emailLog)
    {
        $user = $request->user();

        if ((int) $emailLog->user_id !== (int) $user->id) {
            return $this->forbidden('This email does not belong to you.');
        }
        if (!in_array($emailLog->email_key, self::RESENDABLE_KEYS, true)) {
            return $this->forbidden('This kind of email cannot be resent.');
        }
        if (!hash_equals(mb_strtolower((string) $emailLog->recipient), mb_strtolower((string) $user->email))) {
            return $this->fail('This email was sent to a different address and cannot be resent here.', 422, 'recipient_mismatch');
        }

        $throttleKey = 'user-email-resend-api:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, self::RESEND_MAX)) {
            return $this->fail('Please wait before requesting another resend.', 429, 'rate_limited');
        }
        RateLimiter::hit($throttleKey, self::RESEND_DECAY_SECONDS);

        if (empty($emailLog->body) && empty($emailLog->subject)) {
            return $this->fail('There is nothing stored to resend for this email.', 422, 'nothing_to_resend');
        }

        $new = Emailer::resend($emailLog);

        if ($new->status === 'failed') {
            return $this->fail("Couldn't resend right now. Please try again shortly.", 502, 'resend_failed');
        }

        return $this->ok(['resent_to' => $emailLog->recipient]);
    }
}
