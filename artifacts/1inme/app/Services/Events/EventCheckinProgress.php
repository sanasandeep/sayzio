<?php

namespace App\Services\Events;

use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\Link;

/**
 * Live door check-in tallies for a ticketed event (Task #3592): how many
 * ticket-holders have been scanned in vs. total sold, overall and per tier.
 *
 * Counts are summed by `quantity` so a multi-seat purchase reflects every
 * seat admitted, giving door staff an accurate "attendees in the room"
 * figure. Shared by the web scanner, the mobile scanner, and both owner
 * dashboards so every surface reports identical numbers.
 */
class EventCheckinProgress
{
    public static function for(Link $link): array
    {
        $rows = $link->eventTickets()
            ->selectRaw(
                'tier_id,
                 COALESCE(SUM(quantity) FILTER (WHERE status IN (?, ?)), 0) AS sold,
                 COALESCE(SUM(quantity) FILTER (WHERE status = ?), 0) AS checked_in',
                [EventTicket::STATUS_VALID, EventTicket::STATUS_CHECKED_IN, EventTicket::STATUS_CHECKED_IN],
            )
            ->groupBy('tier_id')
            ->get()
            ->keyBy('tier_id');

        $tiers = [];
        foreach ($link->eventTicketTiers()->orderBy('sort_order')->get() as $tier) {
            $row = $rows->get($tier->id);
            $tiers[] = [
                'id'         => $tier->id,
                'name'       => $tier->name,
                'sold'       => (int) ($row->sold ?? 0),
                'checked_in' => (int) ($row->checked_in ?? 0),
            ];
        }

        $totalSold      = (int) $rows->sum(fn ($r) => (int) $r->sold);
        $totalCheckedIn = (int) $rows->sum(fn ($r) => (int) $r->checked_in);

        return [
            'totals' => [
                'sold'       => $totalSold,
                'checked_in' => $totalCheckedIn,
                'remaining'  => max(0, $totalSold - $totalCheckedIn),
            ],
            'tiers'      => $tiers,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
