<?php

namespace App\Services\Events;

use App\Modules\User\Models\EventContactExchange;
use App\Modules\User\Models\EventDiscoverability;
use App\Modules\User\Models\Link;

/**
 * Task #5010 — Aggregate contact-exchange stats for an event, shown on the
 * organizer "People" dashboard (web) and the owner API endpoint. Shared so
 * both surfaces always report the same numbers.
 */
class EventPeopleStats
{
    public static function for(Link $link): array
    {
        $optedInActive = EventDiscoverability::active()
            ->where('link_id', $link->id)
            ->count();

        // Rows are deleted when a user opts out, so "total" counts everyone
        // with a row (active + expired) — the best available opt-in figure.
        $optedInTotal = EventDiscoverability::where('link_id', $link->id)->count();

        $byStatus = EventContactExchange::where('link_id', $link->id)
            ->selectRaw('status, COUNT(*) AS n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $requests = (int) $byStatus->sum();
        $accepted = (int) ($byStatus[EventContactExchange::STATUS_ACCEPTED] ?? 0);
        $pending  = (int) ($byStatus[EventContactExchange::STATUS_PENDING] ?? 0);
        $declined = (int) ($byStatus[EventContactExchange::STATUS_DECLINED] ?? 0);

        $recent = EventContactExchange::where('link_id', $link->id)
            ->where('status', EventContactExchange::STATUS_ACCEPTED)
            ->with(['requester:id,name,handle', 'recipient:id,name,handle'])
            ->orderByDesc('accepted_at')
            ->limit(10)
            ->get()
            ->map(fn (EventContactExchange $ex) => [
                'requester_name' => $ex->requester?->name,
                'recipient_name' => $ex->recipient?->name,
                'accepted_at'    => optional($ex->accepted_at)->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'opted_in_active'  => $optedInActive,
            'opted_in_total'   => $optedInTotal,
            'requests_total'   => $requests,
            'accepted_total'   => $accepted,
            'pending_total'    => $pending,
            'declined_total'   => $declined,
            'acceptance_rate'  => $requests > 0 ? round($accepted / $requests * 100) : 0,
            'recent_accepted'  => $recent,
        ];
    }
}
