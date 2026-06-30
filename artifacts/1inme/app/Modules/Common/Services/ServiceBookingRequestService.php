<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\ServiceBookingRequestItem;
use App\Modules\User\Models\ServiceBookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Core booking-request placement logic for the Service Booking page (Task #3085).
 * Mirrors RestaurantOrderService: validates the requested services against the
 * live catalog, snapshots their name/price/duration, re-checks the chosen slot
 * is still genuinely free, persists the request, then fires the
 * `service_booking.new_request` notification to the owner across in-app + push
 * + email per prefs. No payment is taken — the total is an estimate only.
 */
class ServiceBookingRequestService
{
    public function __construct(
        protected NotificationService $notifications,
        protected ServiceBookingEstimateCalculator $calculator,
        protected SlotAvailabilityService $slots,
    ) {
    }

    /**
     * @param array{customer_name:string, customer_email?:?string, customer_phone?:?string, customer_note?:?string, slot_start:string, services:array<int,array{service_id:int,quantity?:int}>} $data
     */
    public function place(Link $link, ServiceBooking $config, array $data): ServiceBookingRequest
    {
        // Pull all referenced services in one query and validate availability.
        $serviceIds = collect($data['services'])->pluck('service_id')->map(fn ($i) => (int) $i)->all();
        $services = ServiceBookingService::where('service_booking_id', $config->id)
            ->whereIn('id', $serviceIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = 0.0;
        $totalDuration = 0;
        foreach ($data['services'] as $row) {
            $service = $services->get((int) $row['service_id']);
            if (!$service) {
                throw new \InvalidArgumentException('One or more services are no longer available.');
            }
            if ($service->is_unavailable) {
                throw new \InvalidArgumentException($service->name . ' is currently unavailable.');
            }
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $lineTotal = round(((float) $service->price) * $qty, 2);
            $subtotal += $lineTotal;
            $totalDuration += max(1, (int) $service->duration_minutes) * $qty;
            $lines[] = [
                'service_id'       => $service->id,
                'name'             => $service->name,
                'unit_price'       => $service->price,
                'duration_minutes' => $service->duration_minutes,
                'quantity'         => $qty,
                'line_total'       => $lineTotal,
            ];
        }

        if (!$lines) {
            throw new \InvalidArgumentException('Pick at least one service to book.');
        }

        $totalDuration = max(1, $totalDuration);

        // Parse + re-validate the chosen slot is still free for the full
        // combined duration. Server-side check so a stale/tampered slot can
        // never be trusted.
        try {
            $slotStart = Carbon::parse($data['slot_start']);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('That time slot is invalid.');
        }
        if (!$this->slots->slotIsAvailable($config, $totalDuration, $slotStart)) {
            throw new \InvalidArgumentException('That time slot is no longer available. Please pick another.');
        }

        $tz = $config->effectiveTimezone();
        $slotStart = $slotStart->copy()->setTimezone($tz);
        $slotEnd = $slotStart->copy()->addMinutes($totalDuration);

        // Estimated bill (tax only, no coupons).
        $bill = $this->calculator->compute($config, $subtotal);

        $request = DB::transaction(function () use ($config, $link, $data, $lines, $slotStart, $slotEnd, $totalDuration, $subtotal, $bill) {
            $request = ServiceBookingRequest::create([
                'service_booking_id' => $config->id,
                'link_id'            => $link->id,
                'status'             => ServiceBookingRequest::STATUS_PENDING,
                'customer_name'      => $data['customer_name'],
                'customer_email'     => $data['customer_email'] ?? null,
                'customer_phone'     => $data['customer_phone'] ?? null,
                'customer_note'      => $data['customer_note'] ?? null,
                'slot_start'         => $slotStart,
                'slot_end'           => $slotEnd,
                'duration_minutes'   => $totalDuration,
                'subtotal'           => round($subtotal, 2),
                'tax_rate'           => $bill['tax_rate'],
                'tax_inclusive'      => $bill['tax_inclusive'],
                'tax_amount'         => $bill['tax_amount'],
                'total'              => $bill['total'],
                'currency'           => $config->currency,
            ]);

            foreach ($lines as $line) {
                ServiceBookingRequestItem::create(array_merge($line, ['request_id' => $request->id]));
            }

            return $request;
        });

        $this->notifyOwner($link, $config, $request->fresh('items'));

        return $request;
    }

    /** Fan a new-request alert to the page owner across all channels. */
    protected function notifyOwner(Link $link, ServiceBooking $config, ServiceBookingRequest $request): void
    {
        $owner = $link->user;
        if (!$owner) {
            return;
        }

        $tz = $config->effectiveTimezone();
        $when = Carbon::parse($request->slot_start)->setTimezone($tz)->format('D, M j · g:i A');
        $serviceNames = $request->items->pluck('name')->implode(', ');
        if ($serviceNames === '') {
            $serviceNames = 'a service';
        }
        $estimated = (float) ($request->total ?: $request->subtotal);

        $subject = "New booking request · {$when}";
        $body = "{$request->customer_name} requested {$serviceNames} for {$when} · est. {$request->currency} "
            . number_format($estimated, 2)
            . " on \"{$link->title}\".";

        $bookingsUrl = route('user.links.service-booking.bookings', $link);
        $notification = null;
        try {
            $notification = $this->notifications->notify($owner, 'service_booking.new_request', [
                'subject'     => $subject,
                'message'     => $body,
                'link_id'     => $link->id,
                'link_alias'  => $link->alias,
                'request_id'  => $request->id,
                'slot_start'  => Carbon::parse($request->slot_start)->toIso8601String(),
                'total'       => $request->total,
                'currency'    => $request->currency,
                'url'         => $bookingsUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('service_booking new_request in-app notify failed: ' . $e->getMessage());
        }

        if ($owner->email && $this->notifications->prefersChannel($owner->id, 'service_booking.new_request', 'email')) {
            try {
                Emailer::send('service_booking.new_request', $owner->email, [
                    'customer'     => $request->customer_name,
                    'services'     => $serviceNames,
                    'when'         => $when,
                    'currency'     => $request->currency,
                    'total'        => number_format($estimated, 2),
                    'link_title'   => $link->title,
                    'bookings_url' => $bookingsUrl,
                ], ['user' => $owner->id, 'related' => $request]);
            } catch (\Throwable $e) {
                Log::warning('service_booking new_request email failed: ' . $e->getMessage());
            }
        }

        $this->notifications->pushToUser(
            $owner,
            'service_booking.new_request',
            $subject,
            $body,
            array_merge(
                [
                    'link_id'    => $link->id,
                    'request_id' => $request->id,
                    'url'        => $bookingsUrl,
                ],
                $notification ? ['notification_id' => $notification->id] : [],
            ),
        );
    }
}
