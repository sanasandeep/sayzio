<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\ServiceBookingStaff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Generates genuinely-free upcoming booking slots for a Service Booking page
 * (Task #3085), and re-validates a chosen slot at submission time.
 *
 * A slot is offered only when ALL of these hold, evaluated in the page's
 * configured timezone:
 *   - it falls inside an active weekly availability window for that weekday
 *     (the staff member's own hours when they have any, else the page hours);
 *   - the whole [start, start+duration) fits inside that window;
 *   - the day is not a blocked date (page-level, or the staff member's own);
 *   - it is at least `lead_time_minutes` from now and within `max_days_ahead`;
 *   - after expanding by buffer minutes, fewer than `capacity` slot-holding
 *     requests overlap it for that staff member (capacity = the smallest
 *     per-slot group capacity across the chosen services);
 *   - it does not overlap a busy block on the linked Google Calendar.
 *
 * Staff (Task #6325): pages without staff behave as a single resource. Pages
 * with active staff compute availability per member; "any available" is the
 * union of every member who can perform all chosen services.
 *
 * Options accepted by freeSlots()/slotIsAvailable() via $opts:
 *   staff_id       ?int  — restrict to one staff member (null = any / page)
 *   service_ids    int[] — chosen services (staff eligibility filter)
 *   buffer_before  int   — cart-effective buffer minutes before
 *   buffer_after   int   — cart-effective buffer minutes after
 *   capacity       int   — cart-effective per-slot capacity (default 1)
 *   ignore_request_id ?int — exclude an existing request (reschedule)
 */
class SlotAvailabilityService
{
    /** Hard cap on generated slots so the payload never explodes. */
    private const MAX_SLOTS = 400;

    public function __construct(protected ServiceBookingCalendarSync $calendarSync)
    {
    }

    /**
     * Active staff members able to perform every service in $serviceIds.
     *
     * @return Collection<int,ServiceBookingStaff>
     */
    public function eligibleStaff(ServiceBooking $config, array $serviceIds): Collection
    {
        return $config->staff()
            ->where('is_active', true)
            ->with('services:id')
            ->get()
            ->filter(fn (ServiceBookingStaff $s) => $s->canPerformAll($serviceIds))
            ->values();
    }

    /**
     * @return array<int,array{date:string,label:string,slots:array<int,array{start:string,end:string,label:string,remaining:int}>}>
     */
    public function freeSlots(ServiceBooking $config, int $durationMinutes, ?int $maxDays = null, array $opts = []): array
    {
        $duration = max(1, $durationMinutes);
        $tz       = $config->effectiveTimezone();
        $now      = Carbon::now($tz);
        $slotLen  = max(5, (int) $config->slot_length_minutes);
        $lead     = max(0, (int) $config->lead_time_minutes);
        $window   = $maxDays ?? max(1, (int) $config->max_days_ahead);
        $earliest = $now->copy()->addMinutes($lead);
        $limit    = $now->copy()->addDays($window)->endOfDay();

        $members = $this->resolveMembers($config, $opts);
        if ($members === null) {
            return []; // staff requested/required but none eligible
        }

        // date => start ISO => ['end' =>, 'label' =>, 'remaining' => int]
        $merged = [];
        $count  = 0;

        foreach ($members as $member) {
            $ctx = $this->memberContext($config, $member, $now, $limit, $opts);
            if ($ctx === null) {
                continue;
            }

            for ($day = $now->copy()->startOfDay(); $day->lte($limit); $day->addDay()) {
                $dateStr = $day->format('Y-m-d');
                if (isset($ctx['blocked'][$dateStr])) {
                    continue;
                }
                $dayRules = $ctx['rules']->get($day->dayOfWeek);
                if (!$dayRules) {
                    continue;
                }

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

                        $remaining = $this->remainingCapacity($slotStart, $slotEnd, $ctx, $opts);
                        if ($remaining < 1) {
                            continue;
                        }

                        $iso = $slotStart->toIso8601String();
                        $prev = $merged[$dateStr][$iso] ?? null;
                        if ($prev === null) {
                            $merged[$dateStr][$iso] = [
                                'end'       => $slotEnd->toIso8601String(),
                                'label'     => $slotStart->format('g:i A'),
                                'remaining' => $remaining,
                            ];
                            if (++$count >= self::MAX_SLOTS) {
                                break 4;
                            }
                        } elseif ($remaining > $prev['remaining']) {
                            // "Any available": surface the best remaining count.
                            $merged[$dateStr][$iso]['remaining'] = $remaining;
                        }
                    }
                }
            }
        }

        ksort($merged);
        $out = [];
        foreach ($merged as $dateStr => $slots) {
            ksort($slots);
            $out[] = [
                'date'  => $dateStr,
                'label' => Carbon::parse($dateStr, $tz)->format('D, M j'),
                'slots' => collect($slots)->map(fn ($s, $iso) => [
                    'start'     => $iso,
                    'end'       => $s['end'],
                    'label'     => $s['label'],
                    'remaining' => $s['remaining'],
                ])->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * True when [start, start+duration) is a currently-bookable, free slot.
     * When staff_id is set in $opts, validates against that member only.
     */
    public function slotIsAvailable(ServiceBooking $config, int $durationMinutes, Carbon $slotStart, ?int $ignoreRequestId = null, array $opts = []): bool
    {
        if ($ignoreRequestId !== null) {
            $opts['ignore_request_id'] = $ignoreRequestId;
        }

        $duration = max(1, $durationMinutes);
        $tz       = $config->effectiveTimezone();
        $start    = $slotStart->copy()->setTimezone($tz);
        $end      = $start->copy()->addMinutes($duration);
        $now      = Carbon::now($tz);

        // Lead time + booking window.
        if ($start->lt($now->copy()->addMinutes(max(0, (int) $config->lead_time_minutes)))) {
            return false;
        }
        $limit = $now->copy()->addDays(max(1, (int) $config->max_days_ahead))->endOfDay();
        if ($start->gt($limit)) {
            return false;
        }

        $members = $this->resolveMembers($config, $opts);
        if ($members === null) {
            return false;
        }

        foreach ($members as $member) {
            if ($this->memberSlotIsAvailable($config, $member, $start, $end, $now, $limit, $opts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pick a concrete free staff member for the slot ("any available" flow).
     * Returns the member, or null when the page has no staff, or false when
     * staff exist but none is free.
     */
    public function resolveStaffForSlot(ServiceBooking $config, int $durationMinutes, Carbon $slotStart, array $opts = []): ServiceBookingStaff|false|null
    {
        if (!empty($opts['staff_id'])) {
            $member = $config->staff()->where('is_active', true)->find((int) $opts['staff_id']);
            if (!$member) {
                return false;
            }
            $sub = array_merge($opts, ['staff_id' => $member->id]);

            return $this->slotIsAvailable($config, $durationMinutes, $slotStart, null, $sub) ? $member : false;
        }

        $eligible = $this->eligibleStaff($config, $opts['service_ids'] ?? []);
        if ($eligible->isEmpty()) {
            return $config->staff()->where('is_active', true)->exists() ? false : null;
        }

        foreach ($eligible as $member) {
            $sub = array_merge($opts, ['staff_id' => $member->id]);
            if ($this->slotIsAvailable($config, $durationMinutes, $slotStart, null, $sub)) {
                return $member;
            }
        }

        return false;
    }

    // ── Internals ─────────────────────────────────────────────────────

    /**
     * The set of "resources" to evaluate: [null] for a staff-less page, one
     * member when staff_id is given, or all eligible members. Returns null
     * when staff are required but the requested/eligible set is empty.
     *
     * @return array<int,?ServiceBookingStaff>|null
     */
    protected function resolveMembers(ServiceBooking $config, array $opts): ?array
    {
        $staffId = isset($opts['staff_id']) && $opts['staff_id'] ? (int) $opts['staff_id'] : null;

        if ($staffId !== null) {
            $member = $config->staff()->where('is_active', true)->find($staffId);
            if (!$member) {
                return null;
            }
            if (!empty($opts['service_ids']) && !$member->canPerformAll($opts['service_ids'])) {
                return null;
            }

            return [$member];
        }

        if (!$config->hasStaff()) {
            return [null];
        }

        $eligible = $this->eligibleStaff($config, $opts['service_ids'] ?? []);

        return $eligible->isEmpty() ? null : $eligible->all();
    }

    /**
     * Per-member availability context: weekly rules grouped by weekday,
     * blocked-date set, buffered busy ranges, and calendar busy blocks.
     */
    protected function memberContext(ServiceBooking $config, ?ServiceBookingStaff $member, Carbon $now, Carbon $limit, array $opts): ?array
    {
        $tz = $config->effectiveTimezone();

        // Weekly hours: the member's own active rules when they have any,
        // else the page-level (staff_id null) rules.
        $rulesQuery = $config->availabilityRules()->where('is_active', true);
        if ($member) {
            $own = (clone $rulesQuery)->where('staff_id', $member->id)->get();
            $rules = $own->isNotEmpty() ? $own : (clone $rulesQuery)->whereNull('staff_id')->get();
        } else {
            $rules = $rulesQuery->whereNull('staff_id')->get();
        }
        if ($rules->isEmpty()) {
            return null;
        }

        // Blocked dates: page-level always applies; staff blocks additionally.
        $blockedQuery = $config->blockedDates();
        if ($member) {
            $blockedQuery->where(fn ($q) => $q->whereNull('staff_id')->orWhere('staff_id', $member->id));
        } else {
            $blockedQuery->whereNull('staff_id');
        }
        $blocked = $blockedQuery->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->flip()
            ->all();

        return [
            'member'   => $member,
            'rules'    => $rules->groupBy('day_of_week'),
            'blocked'  => $blocked,
            'busy'     => $this->busyRanges($config, $now, $member, $opts['ignore_request_id'] ?? null),
            'calendar' => $this->calendarBusy($config, $member, $now, $limit),
        ];
    }

    /** Point check against one member's context. */
    protected function memberSlotIsAvailable(ServiceBooking $config, ?ServiceBookingStaff $member, Carbon $start, Carbon $end, Carbon $now, Carbon $limit, array $opts): bool
    {
        $tz  = $config->effectiveTimezone();
        $ctx = $this->memberContext($config, $member, $now, $limit, $opts);
        if ($ctx === null) {
            return false;
        }

        $dateStr = $start->format('Y-m-d');
        if (isset($ctx['blocked'][$dateStr])) {
            return false;
        }

        // Must fit inside an active weekly window for this weekday, aligned
        // to the slot grid from the window start.
        $dayRules = $ctx['rules']->get($start->dayOfWeek);
        if (!$dayRules) {
            return false;
        }
        $fits    = false;
        $slotLen = max(5, (int) $config->slot_length_minutes);
        foreach ($dayRules as $rule) {
            $winStart = Carbon::parse($dateStr . ' ' . $rule->start_time, $tz);
            $winEnd   = Carbon::parse($dateStr . ' ' . $rule->end_time, $tz);
            if ($start->gte($winStart) && $end->lte($winEnd)) {
                $offset = $winStart->diffInMinutes($start);
                if ($offset % $slotLen === 0) {
                    $fits = true;
                    break;
                }
            }
        }
        if (!$fits) {
            return false;
        }

        return $this->remainingCapacity($start, $end, $ctx, $opts) >= 1;
    }

    /**
     * Remaining group capacity for the candidate slot: cart capacity minus
     * the number of buffered busy ranges overlapping the buffered candidate.
     * Calendar busy blocks are hard blocks regardless of capacity.
     */
    protected function remainingCapacity(Carbon $start, Carbon $end, array $ctx, array $opts): int
    {
        $bufBefore = max(0, (int) ($opts['buffer_before'] ?? 0));
        $bufAfter  = max(0, (int) ($opts['buffer_after'] ?? 0));
        $capacity  = max(1, (int) ($opts['capacity'] ?? 1));

        $candStart = $start->copy()->subMinutes($bufBefore);
        $candEnd   = $end->copy()->addMinutes($bufAfter);

        foreach ($ctx['calendar'] as [$bs, $be]) {
            if ($candStart->lt($be) && $candEnd->gt($bs)) {
                return 0;
            }
        }

        $overlaps = 0;
        foreach ($ctx['busy'] as [$bs, $be]) {
            if ($candStart->lt($be) && $candEnd->gt($bs)) {
                $overlaps++;
                if ($overlaps >= $capacity) {
                    return 0;
                }
            }
        }

        return $capacity - $overlaps;
    }

    /**
     * Load slot-holding requests as buffered [start, end] Carbon pairs
     * (absolute instants — comparison is timezone-agnostic). Each range is
     * expanded by the buffers snapshotted on that request at placement time.
     *
     * Permanently-blocking statuses (BLOCKING_STATUSES) hold the slot
     * indefinitely. STATUS_AWAITING_PAYMENT holds the slot only while
     * checkout_expires_at is still in the future.
     *
     * For a specific staff member, only that member's requests plus legacy
     * unassigned requests (staff_id NULL, placed before staff existed) count.
     *
     * @return array<int,array{0:Carbon,1:Carbon}>
     */
    protected function busyRanges(ServiceBooking $config, Carbon $now, ?ServiceBookingStaff $member = null, ?int $ignoreRequestId = null): array
    {
        $cutoff = $now->copy()->subDay();

        $query = $config->requests()
            ->where(function ($q) use ($now) {
                $q->whereIn('status', ServiceBookingRequest::BLOCKING_STATUSES)
                  ->orWhere(function ($q2) use ($now) {
                      $q2->where('status', ServiceBookingRequest::STATUS_AWAITING_PAYMENT)
                         ->where('checkout_expires_at', '>', $now);
                  });
            })
            ->where('slot_end', '>', $cutoff);

        if ($member) {
            $query->where(fn ($q) => $q->whereNull('staff_id')->orWhere('staff_id', $member->id));
        }

        if ($ignoreRequestId) {
            $query->where('id', '!=', $ignoreRequestId);
        }

        return $query->get(['slot_start', 'slot_end', 'buffer_before_minutes', 'buffer_after_minutes'])
            ->map(fn ($r) => [
                Carbon::parse($r->slot_start)->subMinutes(max(0, (int) $r->buffer_before_minutes)),
                Carbon::parse($r->slot_end)->addMinutes(max(0, (int) $r->buffer_after_minutes)),
            ])
            ->all();
    }

    /**
     * Busy blocks from the linked Google Calendar (staff member's own account
     * when linked, else the page-level account). Degrades to [] on any error.
     *
     * @return array<int,array{0:Carbon,1:Carbon}>
     */
    protected function calendarBusy(ServiceBooking $config, ?ServiceBookingStaff $member, Carbon $from, Carbon $to): array
    {
        if (!$config->calendarSyncEnabled()) {
            return [];
        }

        $accountId = $member?->calendar_account_id ?: $config->calendarSyncAccountId();
        if (!$accountId) {
            return [];
        }

        return $this->calendarSync->busyBlocks($config, (int) $accountId, $from, $to);
    }
}
