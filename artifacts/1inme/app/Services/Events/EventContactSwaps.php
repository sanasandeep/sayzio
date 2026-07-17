<?php

namespace App\Services\Events;

use App\Modules\User\Models\EventContactExchange;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;

/**
 * Task #5052 — "My swaps" per event: list the viewer's own pending/accepted
 * contact-swap requests at an event, and let the sender withdraw a pending
 * one. Shared by the web (session) and API (Sanctum) controllers so both
 * surfaces stay in lockstep.
 *
 * Declined requests are deliberately excluded from the list — they're
 * pruned ~30 days after the event ends and were never acted on by the
 * viewer's counterpart in a way worth surfacing.
 */
class EventContactSwaps
{
    /**
     * All pending + accepted exchanges involving $user at $link, newest
     * first. Each item carries the other party's public card info plus
     * whether the viewer sent it (only sent+pending requests can be
     * cancelled).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listForUser(User $user, Link $link): array
    {
        return EventContactExchange::with(['requester', 'recipient'])
            ->where('link_id', $link->id)
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                  ->orWhere('recipient_id', $user->id);
            })
            ->whereIn('status', [
                EventContactExchange::STATUS_PENDING,
                EventContactExchange::STATUS_ACCEPTED,
            ])
            ->orderByDesc('id')
            ->get()
            ->map(function (EventContactExchange $ex) use ($user) {
                $sentByMe = (int) $ex->requester_id === (int) $user->id;
                $other    = $sentByMe ? $ex->recipient : $ex->requester;

                return [
                    'exchange_id' => $ex->id,
                    'status'      => $ex->status,
                    'sent_by_me'  => $sentByMe,
                    'can_cancel'  => $sentByMe && $ex->isPending(),
                    'created_at'  => $ex->created_at?->toIso8601String(),
                    'accepted_at' => $ex->accepted_at?->toIso8601String(),
                    'other'       => $other ? [
                        'id'         => $other->id,
                        'name'       => $other->name,
                        'handle'     => $other->handle,
                        'avatar_url' => $other->resolveAvatarUrl(),
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Withdraw a pending request. Sender-only; accepted/declined requests
     * cannot be cancelled. Returns a machine-readable error code or null on
     * success. The row is deleted (not status-flipped) so the pair can
     * exchange again later if they choose — a withdrawn request should
     * leave no trace, matching the privacy promise.
     *
     * @return string|null null on success, else one of:
     *                     'not_found' | 'not_sender' | 'already_resolved'
     */
    public static function cancel(User $user, int $exchangeId): ?string
    {
        $exchange = EventContactExchange::find($exchangeId);
        if (!$exchange) return 'not_found';

        if ((int) $exchange->requester_id !== (int) $user->id) {
            return 'not_sender';
        }
        if (!$exchange->isPending()) {
            return 'already_resolved';
        }

        $exchange->delete();

        return null;
    }
}
