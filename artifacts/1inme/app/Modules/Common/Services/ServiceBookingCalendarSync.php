<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\CalendarAccount;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Services\Calendar\CalendarProviderRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Google Calendar two-way sync for Service Booking pages (Task #6325).
 *
 * Read side: busyBlocks() pulls the linked account's events over the booking
 * window (cached briefly) so SlotAvailabilityService can subtract external
 * busy time from offered slots.
 *
 * Write side: syncBookingEvent() pushes CONFIRMED bookings to the calendar as
 * events, updates them on reschedule, and deletes them on cancel/decline.
 * The pushed event id lives in the request's meta['calendar_event_id'] (+
 * meta['calendar_account_id']).
 *
 * Every provider call degrades gracefully: failures are logged and the
 * booking flow continues — calendar sync must never block a booking.
 *
 * Plan gating: the whole feature is behind the boolean plan feature
 * `service_booking_calendar_sync`, checked against the page owner.
 */
class ServiceBookingCalendarSync
{
    private const BUSY_CACHE_SECONDS = 300;

    public function __construct(protected CalendarProviderRegistry $providers)
    {
    }

    /** True when the page owner's plan includes calendar sync. */
    public function planAllows(ServiceBooking $config): bool
    {
        $owner = $config->user;

        return (bool) ($owner?->getPlanFeature('service_booking_calendar_sync') ?? false);
    }

    /**
     * Busy [start, end] Carbon pairs from the account's calendar between
     * $from and $to. Cached for a few minutes; [] on any failure.
     *
     * @return array<int,array{0:Carbon,1:Carbon}>
     */
    public function busyBlocks(ServiceBooking $config, int $accountId, Carbon $from, Carbon $to): array
    {
        if (!$this->planAllows($config)) {
            return [];
        }

        $account = $this->resolveAccount($config, $accountId);
        if (!$account) {
            return [];
        }

        $key = sprintf(
            'sb:gcal-busy:%d:%d:%s:%s',
            $config->id,
            $account->id,
            $from->copy()->setTimezone('UTC')->format('YmdHi'),
            $to->copy()->setTimezone('UTC')->format('YmdHi'),
        );

        $raw = Cache::remember($key, self::BUSY_CACHE_SECONDS, function () use ($account, $from, $to) {
            try {
                $provider = $this->providers->get($account->provider);
                $blocks = [];
                foreach ($provider->listEvents($account, $from, $to) as $ev) {
                    // Skip events we created ourselves so a pushed booking
                    // doesn't double-block its own (already-held) slot.
                    if (str_starts_with((string) ($ev['summary'] ?? ''), '[Sayzio] ')) {
                        continue;
                    }
                    $blocks[] = [
                        Carbon::parse($ev['start'])->toIso8601String(),
                        Carbon::parse($ev['end'])->toIso8601String(),
                    ];
                }

                return $blocks;
            } catch (\Throwable $e) {
                Log::warning('service_booking calendar busy fetch failed: ' . $e->getMessage(), [
                    'account' => $account->id,
                ]);

                return [];
            }
        });

        return array_map(
            fn ($b) => [Carbon::parse($b[0]), Carbon::parse($b[1])],
            is_array($raw) ? $raw : [],
        );
    }

    /**
     * Push / update / remove the calendar event for a booking based on its
     * current status. Safe to call on every status transition.
     */
    public function syncBookingEvent(ServiceBookingRequest $request): void
    {
        $request->loadMissing(['serviceBooking', 'link', 'items', 'staff']);
        $config = $request->serviceBooking;
        if (!$config || !$config->calendarSyncEnabled() || !$this->planAllows($config)) {
            return;
        }

        $accountId = $request->staff?->calendar_account_id ?: $config->calendarSyncAccountId();
        // Fall back to the account the event was originally pushed to.
        $accountId = $accountId ?: (int) ($request->meta['calendar_account_id'] ?? 0);
        if (!$accountId) {
            return;
        }
        $account = $this->resolveAccount($config, (int) $accountId);
        if (!$account) {
            return;
        }

        $eventId = (string) ($request->meta['calendar_event_id'] ?? '');

        try {
            $provider = $this->providers->get($account->provider);

            if ($request->status === ServiceBookingRequest::STATUS_CONFIRMED) {
                $payload = $this->eventPayload($config, $request);
                if ($eventId !== '') {
                    $provider->updateEvent($account, $eventId, $payload);
                } else {
                    $created = $provider->createEvent($account, $payload);
                    $meta = $request->meta ?? [];
                    $meta['calendar_event_id']   = $created['external_event_id'] ?? null;
                    $meta['calendar_account_id'] = $account->id;
                    $request->meta = $meta;
                    $request->save();
                }
            } elseif ($eventId !== '' && in_array($request->status, [
                ServiceBookingRequest::STATUS_CANCELLED,
                ServiceBookingRequest::STATUS_DECLINED,
            ], true)) {
                $provider->deleteEvent($account, $eventId);
                $meta = $request->meta ?? [];
                unset($meta['calendar_event_id']);
                $request->meta = $meta;
                $request->save();
            }
        } catch (\Throwable $e) {
            Log::warning('service_booking calendar push failed: ' . $e->getMessage(), [
                'request' => $request->id,
                'status'  => $request->status,
            ]);
        }
    }

    /** Normalised event payload for a confirmed booking. */
    protected function eventPayload(ServiceBooking $config, ServiceBookingRequest $request): array
    {
        $tz = $config->effectiveTimezone();
        $services = $request->items->pluck('name')->implode(', ') ?: 'a service';
        $lines = [
            'Booking for ' . $request->customer_name,
            'Services: ' . $services,
        ];
        if ($request->customer_email) {
            $lines[] = 'Email: ' . $request->customer_email;
        }
        if ($request->customer_phone) {
            $lines[] = 'Phone: ' . $request->customer_phone;
        }
        if ($request->staff) {
            $lines[] = 'Staff: ' . $request->staff->name;
        }

        return [
            'summary'     => '[Sayzio] ' . $request->customer_name . ' · ' . $services,
            'description' => implode("\n", $lines),
            'start'       => Carbon::parse($request->slot_start),
            'end'         => Carbon::parse($request->slot_end),
            'timezone'    => $tz,
            'all_day'     => false,
        ];
    }

    /** The account must belong to the page owner (never a foreign account). */
    protected function resolveAccount(ServiceBooking $config, int $accountId): ?CalendarAccount
    {
        return CalendarAccount::withoutGlobalScopes()
            ->where('id', $accountId)
            ->where('user_id', $config->user_id)
            ->first();
    }
}
