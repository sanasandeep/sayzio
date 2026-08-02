<?php

namespace App\Modules\User\Services;

use App\Mail\EventWaitlistPromotedMail;
use App\Modules\Common\Services\Emailer;
use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Waitlist auto-promotion (Task #6xxx).
 *
 * When a confirmed guest frees a seat (self-cancel, organizer cancel/remove)
 * or the organizer raises capacity, the oldest waitlisted guests that still
 * fit are automatically promoted to confirmed and emailed.
 *
 * Race-safety: all capacity math + status flips happen inside a single DB
 * transaction that first takes row locks (SELECT ... FOR UPDATE) on the
 * event's rsvp rows (RSVP-capacity events) or the tier row (ticketed
 * events). Concurrent cancellations therefore serialize on the same locked
 * rows and can never over-promote past the cap.
 *
 * Free-only: only free RSVPs are auto-confirmed. PAID tiers cannot be
 * auto-charged, so instead the oldest waitlisted guest gets a "spot opened"
 * invite email with a purchase link — they still have to buy.
 */
class WaitlistPromotionService
{
    /**
     * Run promotion for an RSVP-capacity event (the non-tier
     * `rsvp_settings.capacity` model). Free RSVPs only.
     *
     * @return int number of RSVPs promoted
     */
    public function promoteForLink(Link $link): int
    {
        // Re-read settings off the (possibly stale) model without a global scope.
        $settings     = (array) ($link->settings ?? []);
        $rsvpSettings = (array) ($settings['rsvp_settings'] ?? []);

        // A cancelled event has no seats to promote into (Sayzio events).
        if (!empty($settings['event_cancelled'])) return 0;

        // Per-event toggle: defaults ON. Stored explicitly false when off.
        if (!self::autoPromoteEnabled($rsvpSettings)) return 0;

        $cap = isset($rsvpSettings['capacity']) ? (int) $rsvpSettings['capacity'] : 0;
        // No finite capacity means there is no "full" state to promote out of.
        if ($cap <= 0) return 0;

        $promoted = [];

        DB::transaction(function () use ($link, $cap, &$promoted) {
            // Lock every non-cancelled RSVP row for this event so a
            // concurrent cancel/promotion serializes here.
            $rows = Rsvp::query()
                ->where('link_id', $link->id)
                ->whereIn('status', ['confirmed', 'waitlist'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $used = 0;
            foreach ($rows as $r) {
                if ($r->status === 'confirmed') $used += $r->seatsConsumed();
            }

            $waitlisted = $rows->where('status', 'waitlist')
                ->filter(fn (Rsvp $r) => $r->response === 'yes')
                ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                ->values();

            foreach ($waitlisted as $r) {
                $need = 1 + max(0, (int) $r->plus_ones);
                if (($used + $need) > $cap) {
                    // This one doesn't fit; a smaller party behind it might,
                    // but promoting out of order would jump the queue. Keep
                    // strict FIFO fairness and stop at the first that overflows.
                    break;
                }
                $r->status = 'confirmed';
                $r->save();
                $used += $need;
                $promoted[] = $r;
            }
        });

        foreach ($promoted as $r) {
            \App\Services\Events\RsvpTicketService::sync($r->fresh());
            $this->notifyPromoted($link, $r);
        }

        return count($promoted);
    }

    /**
     * Run promotion for a specific ticket tier. Free tiers auto-promote the
     * matching waitlisted RSVPs; PAID tiers instead email the oldest
     * waitlisted guest a "spot opened" purchase invite (no auto-charge).
     *
     * @return int number of RSVPs auto-promoted (0 for paid tiers)
     */
    public function promoteForTier(Link $link, EventTicketTier $tier): int
    {
        // A cancelled event has no seats to promote into (Sayzio events).
        if (!empty(($link->settings ?? [])['event_cancelled'])) return 0;
        if (!self::autoPromoteEnabled((array) (($link->settings ?? [])['rsvp_settings'] ?? []))) return 0;

        // Unbounded tier: nothing to promote out of.
        if ($tier->capacity === null) return 0;

        if (!$tier->isFree()) {
            $this->invitePaidWaitlist($link, $tier);
            return 0;
        }

        // Free tier: fall back to the link-level RSVP capacity model, since
        // free RSVP attendees are tracked as rsvps rather than tier tickets.
        return $this->promoteForLink($link);
    }

    /** A paid waitlist invite is only re-sent after this cooldown elapses. */
    private const INVITE_COOLDOWN_HOURS = 24;

    /**
     * PAID tiers can't be auto-confirmed. Email the oldest still-waiting
     * guest(s) a "spot opened" invite with a purchase link, up to the number
     * of freed seats.
     *
     * Idempotent + race-safe: the selection and the invite-marker stamp
     * (`waitlist_invited_at`) both happen inside a single locked transaction
     * (SELECT ... FOR UPDATE on the event's waitlisted rows). A guest invited
     * within the last INVITE_COOLDOWN_HOURS is skipped, so concurrent or
     * repeated capacity triggers can never double-email the same guest — only
     * one trigger wins the lock, stamps the rows, and the others see the fresh
     * timestamp and skip.
     */
    public function invitePaidWaitlist(Link $link, EventTicketTier $tier): void
    {
        if ($tier->capacity === null) return;
        $free = max(0, (int) $tier->capacity - (int) $tier->sold_count);
        if ($free <= 0) return;

        $cutoffIso = now()->subHours(self::INVITE_COOLDOWN_HOURS);
        $toNotify = [];

        DB::transaction(function () use ($link, $free, $cutoffIso, &$toNotify) {
            // Lock the event's waitlisted rows so a concurrent trigger blocks
            // here and, once we commit, reads the just-written timestamps.
            $rows = Rsvp::query()
                ->where('link_id', $link->id)
                ->where('status', 'waitlist')
                ->where('response', 'yes')
                ->whereNotNull('email')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $picked = 0;
            foreach ($rows as $r) {
                if ($picked >= $free) break;
                // Skip anyone already invited within the cooldown window.
                if ($r->waitlist_invited_at !== null && $r->waitlist_invited_at->gt($cutoffIso)) {
                    continue;
                }
                $r->waitlist_invited_at = now();
                $r->save();
                $toNotify[] = $r;
                $picked++;
            }
        });

        foreach ($toNotify as $r) {
            $this->notifyPaidInvite($link, $r, $tier);
        }
    }

    /**
     * Per-event toggle, default ON. Only an explicit `false` disables it.
     */
    public static function autoPromoteEnabled(array $rsvpSettings): bool
    {
        return ($rsvpSettings['waitlist_auto_promote'] ?? true) !== false;
    }

    private function notifyPromoted(Link $link, Rsvp $rsvp): void
    {
        if (!$rsvp->email) return;
        try {
            Emailer::sendMailable(
                'events.waitlist_promoted',
                $rsvp->email,
                new EventWaitlistPromotedMail($link, $rsvp),
                ['title' => $link->title],
                ['related' => $link, 'user' => $link->user_id],
            );
        } catch (\Throwable $e) {
            Log::warning("Waitlist promotion email failed for rsvp {$rsvp->id}: " . $e->getMessage());
        }
    }

    private function notifyPaidInvite(Link $link, Rsvp $rsvp, EventTicketTier $tier): void
    {
        if (!$rsvp->email) return;
        try {
            Emailer::sendMailable(
                'events.waitlist_spot_opened',
                $rsvp->email,
                new EventWaitlistPromotedMail($link, $rsvp, $tier),
                ['title' => $link->title],
                ['related' => $link, 'user' => $link->user_id],
            );
        } catch (\Throwable $e) {
            Log::warning("Waitlist spot-opened email failed for rsvp {$rsvp->id}: " . $e->getMessage());
        }
    }
}
