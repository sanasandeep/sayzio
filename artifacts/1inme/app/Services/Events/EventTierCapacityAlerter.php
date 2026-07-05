<?php

namespace App\Services\Events;

use App\Modules\Common\Services\Emailer;
use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\UserNotification;
use Illuminate\Support\Facades\Log;

/**
 * Task #3623 — proactively alert an event owner the moment a paid ticket
 * tier crosses "90%+ full" or "sold out", so they can add capacity before
 * missing sales instead of only finding out when they ask the AI.
 *
 * Idempotent: each threshold fires at most once per tier via the
 * `capacity_alerted_near_at` / `capacity_alerted_full_at` stamps, claimed
 * with an atomic conditional UPDATE so concurrent sales can't double-send.
 * Only tiers that declare a capacity are considered (null-capacity tiers
 * are unbounded and never trip). Messages are counts-only — no attendee
 * PII — mirroring the AI events snapshot convention.
 *
 * Called from the shared ticket-issuing path right after `sold_count` is
 * bumped. When an owner raises a tier's capacity the stamps are cleared
 * (see the tier update controllers) so a re-fill re-alerts.
 */
class EventTierCapacityAlerter
{
    /** Trip the "almost gone" alert at 90% of capacity. */
    public const NEAR_FULL_RATIO = 0.9;

    public static function check(EventTicketTier $tier): void
    {
        // Re-read authoritative counts/stamps from the DB. The caller invokes
        // this immediately after an in-place `sold_count` increment, whose
        // in-memory value can be stale under concurrent purchases — a fresh
        // read ensures we don't miss the exact threshold-crossing sale.
        try {
            $tier->refresh();
        } catch (\Throwable $e) {
            return; // tier vanished mid-sale — nothing to alert on
        }

        // Only paid ticket tiers are in scope. Free / RSVP-style tiers
        // (price_cents <= 0) never trip a capacity alert.
        if ($tier->isFree()) return;

        $ratio = $tier->capacityFilledRatio();
        if ($ratio === null) return; // unbounded tier — nothing to alert on

        if ($ratio >= 1.0) {
            self::fireSoldOut($tier);
        } elseif ($ratio >= self::NEAR_FULL_RATIO) {
            self::fireNearFull($tier);
        }
    }

    protected static function fireSoldOut(EventTicketTier $tier): void
    {
        // Atomically claim the sold-out threshold. Also stamp the near-full
        // threshold in the same claim so a tier that jumps straight to
        // sold out (e.g. a large multi-seat purchase) doesn't later emit a
        // redundant "90%+ full" alert.
        $claimed = EventTicketTier::whereKey($tier->id)
            ->whereNull('capacity_alerted_full_at')
            ->update([
                'capacity_alerted_full_at' => now(),
                'capacity_alerted_near_at' => $tier->capacity_alerted_near_at ?? now(),
            ]);
        if (!$claimed) return;

        $tier->capacity_alerted_full_at = now();
        $tier->capacity_alerted_near_at = $tier->capacity_alerted_near_at ?? now();

        self::notify($tier, 'sold_out', 'sold out');
    }

    protected static function fireNearFull(EventTicketTier $tier): void
    {
        $claimed = EventTicketTier::whereKey($tier->id)
            ->whereNull('capacity_alerted_near_at')
            ->update(['capacity_alerted_near_at' => now()]);
        if (!$claimed) return;

        $tier->capacity_alerted_near_at = now();

        self::notify($tier, 'near_full', '90%+ full');
    }

    /**
     * @param  'sold_out'|'near_full'  $threshold
     */
    protected static function notify(EventTicketTier $tier, string $threshold, string $statusLabel): void
    {
        $link = $tier->link;
        $creator = $link?->user;
        if (!$creator) return;

        $eventName = $link->title ?: 'your event';
        $sold      = (int) $tier->sold_count;
        $capacity  = (int) $tier->capacity;
        $remaining = max(0, $capacity - $sold);
        $manageUrl = route('user.links.ics.tickets', $tier->link_id);

        $summary = $threshold === 'sold_out'
            ? sprintf('"%s" for %s is sold out (%d/%d).', $tier->name, $eventName, $sold, $capacity)
            : sprintf('"%s" for %s is %d%%+ full — %d of %d sold, %d left.', $tier->name, $eventName, 90, $sold, $capacity, $remaining);

        try {
            UserNotification::create([
                'user_id'    => $creator->id,
                'type'       => 'event.tier_' . $threshold,
                'data'       => [
                    'link_id'      => $tier->link_id,
                    'tier_id'      => $tier->id,
                    'tier_name'    => $tier->name,
                    'event_name'   => $eventName,
                    'sold'         => $sold,
                    'capacity'     => $capacity,
                    'remaining'    => $remaining,
                    'threshold'    => $threshold,
                    'status_label' => $statusLabel,
                    'message'      => $summary,
                    'url'          => $manageUrl,
                    'link'         => $manageUrl,
                ],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('event.tier_capacity.notification.failed', ['tier' => $tier->id, 'err' => $e->getMessage()]);
        }

        if (!$creator->email) return;

        try {
            Emailer::send('ticketing.tier_capacity', $creator->email, [
                'event_name'   => $eventName,
                'tier_name'    => $tier->name,
                'status_label' => $statusLabel,
                'sold'         => (string) $sold,
                'capacity'     => (string) $capacity,
                'remaining'    => (string) $remaining,
                'manage_url'   => $manageUrl,
            ], [
                'user'    => $creator->id,
                'related' => ['type' => 'link', 'id' => $tier->link_id],
            ]);
        } catch (\Throwable $e) {
            Log::warning('event.tier_capacity.email.failed', ['tier' => $tier->id, 'err' => $e->getMessage()]);
        }
    }
}
