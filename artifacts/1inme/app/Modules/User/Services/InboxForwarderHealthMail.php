<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\InboxForwardDelivery;
use App\Modules\User\Models\InboxForwardDestination;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Transactional "your inbox forwarding destination keeps failing" email.
 *
 * Sent at most once per destination per 24 hours so a noisy outage cannot
 * spam the creator's inbox. Suppressed for users without a verified email
 * address. Failures are swallowed and logged — the in-app deliveries log
 * remains the source of truth.
 */
class InboxForwarderHealthMail
{
    /** Minimum gap between two health emails for the same destination. */
    public const COOLDOWN_HOURS = 24;

    /** A destination is "unhealthy" once it has this many dead deliveries in the lookback window. */
    public const DEAD_THRESHOLD = 3;

    /** Lookback window (hours) for counting recent dead deliveries. */
    public const LOOKBACK_HOURS = 24;

    /**
     * Decide whether $destination is currently unhealthy and email the owner
     * if so (respecting the per-destination cooldown). Returns true if an
     * email was sent, false otherwise.
     *
     * The trigger fires when EITHER of the following is true:
     *   - the most recent delivery just transitioned to 'dead' (i.e. hit
     *     MAX_ATTEMPTS) — this catches the first hard failure;
     *   - the destination has accumulated DEAD_THRESHOLD dead deliveries in
     *     the last LOOKBACK_HOURS — this catches slow bleeds where each
     *     delivery dies before the next one is dispatched.
     */
    public static function dispatchIfDue(InboxForwardDestination $destination, ?InboxForwardDelivery $justFinished = null): bool
    {
        $destination->loadMissing('user');
        $user = $destination->user;

        if (! $user || ! $user->email)  return false;
        if (! $user->email_verified_at) return false;

        // Per-destination 24h cooldown — one email per destination per day,
        // not one per failed delivery. Matches the task spec.
        if ($destination->last_failure_email_sent_at
            && $destination->last_failure_email_sent_at->greaterThan(now()->subHours(self::COOLDOWN_HOURS))) {
            return false;
        }

        $deadJustNow = $justFinished
            && $justFinished->destination_id === $destination->id
            && $justFinished->status === 'dead';

        $deadRecent = InboxForwardDelivery::where('destination_id', $destination->id)
            ->where('status', 'dead')
            ->where('updated_at', '>=', now()->subHours(self::LOOKBACK_HOURS))
            ->count();

        if (! $deadJustNow && $deadRecent < self::DEAD_THRESHOLD) {
            return false;
        }

        $reason = $justFinished?->last_error
            ?? optional(InboxForwardDelivery::where('destination_id', $destination->id)
                ->whereIn('status', ['dead', 'failed'])
                ->latest('updated_at')
                ->first())->last_error;

        $isWebhook = $destination->type === 'webhook';
        $subject   = "Heads up: your \"{$destination->label}\" forwarding rule on 1INME keeps failing";

        $viewData = [
            'subject'        => $subject,
            'userName'       => $user->name ?: 'there',
            'destination'    => $destination,
            'isWebhook'      => $isWebhook,
            'reason'         => $reason,
            'deadCount'      => $deadRecent,
            'lookbackHours'  => self::LOOKBACK_HOURS,
            'rulesUrl'       => route('user.inbox.forwards.index'),
        ];

        try {
            Mail::send('emails.inbox-forward-broken', $viewData, function ($m) use ($user, $subject) {
                $m->to($user->email)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning("inbox-forward-broken email failed for destination {$destination->id}: " . $e->getMessage());
            return false;
        }

        $destination->forceFill(['last_failure_email_sent_at' => now()])->save();
        return true;
    }
}
