<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingRequest;
use Carbon\Carbon;

/**
 * Generates genuinely-free upcoming booking slots for a Service Booking page
 * (Task #3085), and re-validates a chosen slot at submission time.
 *
 * A slot is offered only when ALL of these hold, evaluated in the page's
 * configured timezone:
 *   - it falls inside an active weekly availability window for that weekday;
 *   - the whole [start, start+duration) fits inside that window;
 *   - the day is not a blocked date;
 *   - it is at least `lead_time_minutes` from now and within `max_days_ahead`;
 *   - it does not overlap any slot-holding (non-cancelled/declined) request.
 *
 * This treats the provider as a single resource (one booking at a time), which
 * matches the request-only, no-staff-assignment scope.
 */
class SlotAvailabilityService
{
    /** Hard cap on generated slots so the payload never explodes. */
    private const MAX_SLOTS = 400;

    /**
     * @return array<int,array{date:string,label:string,slots:array<int,array{start:string,end:string,label:string}>}>
     */
    public function freeSlots(ServiceBooking $config, int $durationMinutes, ?int $maxDays = null): array
    {
        $duration = max(1, $durationMinutes);
        $tz       = $config->effectiveTimezone();
        $now      = Carbon::now($tz);
        $slotLen  = max(5, (int) $config->slot_length_minutes);
        $lead     = max(0, (int) $config->lead_time_minutes);
        $window   = $maxDays ?? max(1, (int) $config->max_days_ahead);
        $earliest = $now->copy()->addMinutes($lead);
        $limit    = $now->copy()->addDays($window)->endOfDay();

        $rules = $config->availabilityRules()
            ->where('is_active', true)
            ->get()
            ->groupBy('day_of_week');
        if ($rules->isEmpty()) {
            return [];
        }

        $blocked = $config->blockedDates()
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->flip();

        $busy = $this->busyRanges($config, $now);

        $out   = [];
        $count = 0;
        for ($day = $now->copy()->startOfDay(); $day->lte($limit); $day->addDay()) {
            $dateStr = $day->format('Y-m-d');
            if ($blocked->has($dateStr)) {
                continue;
            }

            $dayRules = $rules->get($day->dayOfWeek);
            if (!$dayRules) {
                continue;
            }

            $seen  = [];
            $slots = [];
            foreach ($dayRules as $rule) {
                $winStart = Carbon::parse($dateStr . ' ' . $rule->start_time, $tz);
                $winEnd   = Carbon::parse($dateStr . ' ' . $rule->end_time, $tz);
                if ($winEnd->lte($winStart)) {
                    continue;
                }

                for ($cursor = $winStart->copy(); ; $cursor->addMinutes($slotLen)) {
                    $slotStart = $cursor->copy();
                    $slotEnd   = $cursor->copy()->addMinutes($duration);
                    if ($slotEnd->gt($winEnd)) {
                        break;
                    }
                    if ($slotStart->lt($earliest) || $slotStart->gt($limit)) {
                        continue;
                    }
                    $key = $slotStart->format('H:i');
                    if (isset($seen[$key])) {
                        continue;
                    }
                    if ($this->overlapsBusy($slotStart, $slotEnd, $busy)) {
                        continue;
                    }
                    $seen[$key] = true;
                    $slots[] = [
                        'start' => $slotStart->toIso8601String(),
                        'end'   => $slotEnd->toIso8601String(),
                        'label' => $slotStart->format('g:i A'),
                    ];
                    if (++$count >= self::MAX_SLOTS) {
                        break 2;
                    }
                }
            }

            if ($slots) {
                usort($slots, fn ($a, $b) => strcmp($a['start'], $b['start']));
                $out[] = [
                    'date'  => $dateStr,
                    'label' => $day->format('D, M j'),
                    'slots' => $slots,
                ];
            }
            if ($count >= self::MAX_SLOTS) {
                break;
            }
        }

        return $out;
    }

    /**
     * True when [start, start+duration) is a currently-bookable, free slot.
     * Used to re-validate the visitor's chosen slot at submission time. Pass
     * $ignoreRequestId to exclude an existing request (e.g. owner reschedule).
     */
    public function slotIsAvailable(ServiceBooking $config, int $durationMinutes, Carbon $slotStart, ?int $ignoreRequestId = null): bool
    {
        $duration = max(1, $durationMinutes);
        $tz       = $config->effectiveTimezone();
        $start    = $slotStart->copy()->setTimezone($tz);
        $end      = $start->copy()->addMinutes($duration);
        $now      = Carbon::now($tz);

        // Lead time + booking window.
        if ($start->lt($now->copy()->addMinutes(max(0, (int) $config->lead_time_minutes)))) {
            return false;
        }
        if ($start->gt($now->copy()->addDays(max(1, (int) $config->max_days_ahead))->endOfDay())) {
            return false;
        }

        // Blocked date.
        $blocked = $config->blockedDates()
            ->whereDate('date', $start->format('Y-m-d'))
            ->exists();
        if ($blocked) {
            return false;
        }

        // Must fit inside an active weekly window for this weekday.
        $rules = $config->availabilityRules()
            ->where('is_active', true)
            ->where('day_of_week', $start->dayOfWeek)
            ->get();
        $fits = false;
        $dateStr = $start->format('Y-m-d');
        foreach ($rules as $rule) {
            $winStart = Carbon::parse($dateStr . ' ' . $rule->start_time, $tz);
            $winEnd   = Carbon::parse($dateStr . ' ' . $rule->end_time, $tz);
            if ($start->gte($winStart) && $end->lte($winEnd)) {
                // Must be aligned to the slot grid from the window start.
                $slotLen = max(5, (int) $config->slot_length_minutes);
                $offset  = $winStart->diffInMinutes($start);
                if ($offset % $slotLen === 0) {
                    $fits = true;
                    break;
                }
            }
        }
        if (!$fits) {
            return false;
        }

        // No overlap with a slot-holding request.
        $busy = $this->busyRanges($config, $now, $ignoreRequestId);

        return !$this->overlapsBusy($start, $end, $busy);
    }

    /**
     * Load slot-holding requests as [start, end] Carbon pairs (absolute
     * instants — comparison is timezone-agnostic).
     *
     * @return array<int,array{0:Carbon,1:Carbon}>
     */
    private function busyRanges(ServiceBooking $config, Carbon $now, ?int $ignoreRequestId = null): array
    {
        $query = $config->requests()
            ->whereIn('status', ServiceBookingRequest::BLOCKING_STATUSES)
            ->where('slot_end', '>', $now->copy()->subDay());
        if ($ignoreRequestId) {
            $query->where('id', '!=', $ignoreRequestId);
        }

        return $query->get(['slot_start', 'slot_end'])
            ->map(fn ($r) => [Carbon::parse($r->slot_start), Carbon::parse($r->slot_end)])
            ->all();
    }

    /** Half-open overlap test: starts before an existing end AND ends after its start. */
    private function overlapsBusy(Carbon $start, Carbon $end, array $busy): bool
    {
        foreach ($busy as [$bs, $be]) {
            if ($start->lt($be) && $end->gt($bs)) {
                return true;
            }
        }

        return false;
    }
}
