<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\ServiceBookingRequestItem;
use App\Modules\User\Models\ServiceBookingService;
use App\Modules\Common\Services\Emailer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Core booking-request placement logic for the Service Booking page.
 * Mirrors RestaurantOrderService: validates the requested services against the
 * live catalog, snapshots their name/price/duration, re-checks the chosen slot
 * is still genuinely free, persists the request, then fires notifications.
 *
 * Paid bookings (task #5284):
 *   When any booked service has payment_mode != 'none' AND the owner has a
 *   connected payout account, the request is created in STATUS_AWAITING_PAYMENT
 *   with a 30-minute checkout hold window. The caller is responsible for
 *   redirecting the visitor to checkout_url (returned as part of the result).
 *   confirmPayment() is called by MonetizationCheckout::confirmBooking() after
 *   the provider confirms the charge.
 */
class ServiceBookingRequestService
{
    public function __construct(
        protected NotificationService $notifications,
        protected ServiceBookingEstimateCalculator $calculator,
        protected SlotAvailabilityService $slots,
        protected ServiceBookingCalendarSync $calendarSync,
    ) {
    }

    /**
     * @param array{customer_name:string, customer_email?:?string, customer_phone?:?string, customer_note?:?string, slot_start:string, services:array<int,array{service_id:int,quantity?:int}>} $data
     * @return array{request:ServiceBookingRequest, requires_payment:bool, payment_amount_cents:int, payment_currency:string}
     */
    public function place(Link $link, ServiceBooking $config, array $data): array
    {
        // Pull all referenced services in one query and validate availability.
        $serviceIds = collect($data['services'])->pluck('service_id')->map(fn ($i) => (int) $i)->all();
        $services = ServiceBookingService::where('service_booking_id', $config->id)
            ->whereIn('id', $serviceIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $lines         = [];
        $subtotal      = 0.0;
        $totalDuration = 0;
        $paymentCents  = 0;
        $requiresPayment = false;

        foreach ($data['services'] as $row) {
            $service = $services->get((int) $row['service_id']);
            if (!$service) {
                throw new \InvalidArgumentException('One or more services are no longer available.');
            }
            if ($service->is_unavailable) {
                throw new \InvalidArgumentException($service->name . ' is currently unavailable.');
            }
            $qty       = max(1, (int) ($row['quantity'] ?? 1));
            $lineTotal = round(((float) $service->price) * $qty, 2);
            $subtotal  += $lineTotal;
            $totalDuration += max(1, (int) $service->duration_minutes) * $qty;

            if ($service->requiresPayment()) {
                $requiresPayment = true;
                $paymentCents   += $service->requiredPaymentCents($lineTotal);
            }

            // Track the per-service payment mode so the caller can determine
            // the aggregate mode for this booking.
            $paymentMode = $service->payment_mode ?? ServiceBookingService::PAYMENT_MODE_NONE;

            $lines[] = [
                'service_id'       => $service->id,
                'name'             => $service->name,
                'unit_price'       => $service->price,
                'duration_minutes' => $service->duration_minutes,
                'quantity'         => $qty,
                'line_total'       => $lineTotal,
                '_payment_mode'    => $paymentMode,
            ];
        }

        if (!$lines) {
            throw new \InvalidArgumentException('Pick at least one service to book.');
        }

        $totalDuration = max(1, $totalDuration);

        // If payment is required but the owner has no payout connection, fall
        // back to free mode (booking is placed without payment).
        $owner = $link->user;
        if ($requiresPayment && $paymentCents > 0) {
            if (!$owner || !$owner->defaultPaymentConnection()) {
                $requiresPayment = false;
                $paymentCents    = 0;
            }
        }

        // Derive aggregate payment mode label for the request row.
        $modes = collect($lines)->pluck('_payment_mode')->unique()->values()->all();
        $aggMode = ServiceBookingService::PAYMENT_MODE_NONE;
        if ($requiresPayment) {
            if (in_array(ServiceBookingService::PAYMENT_MODE_FULL, $modes, true)) {
                $aggMode = ServiceBookingService::PAYMENT_MODE_FULL;
            } else {
                $aggMode = ServiceBookingService::PAYMENT_MODE_DEPOSIT;
            }
        }

        // Strip internal tracking field before persisting line items.
        $lines = array_map(function ($l) {
            unset($l['_payment_mode']);
            return $l;
        }, $lines);

        // Cart-effective buffers (widest wins) and per-slot group capacity
        // (smallest wins) across the chosen services (Task #6325).
        $bufBefore = 0;
        $bufAfter  = 0;
        $capacity  = null;
        foreach ($services as $service) {
            $bufBefore = max($bufBefore, $service->effectiveBufferBefore($config));
            $bufAfter  = max($bufAfter, $service->effectiveBufferAfter($config));
            $svcCap    = max(1, (int) ($service->capacity ?? 1));
            $capacity  = $capacity === null ? $svcCap : min($capacity, $svcCap);
        }
        $capacity = $capacity ?? 1;

        // Parse + re-validate the chosen slot is still free.
        try {
            $slotStart = Carbon::parse($data['slot_start']);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('That time slot is invalid.');
        }

        $slotOpts = [
            'staff_id'      => isset($data['staff_id']) && $data['staff_id'] ? (int) $data['staff_id'] : null,
            'service_ids'   => $serviceIds,
            'buffer_before' => $bufBefore,
            'buffer_after'  => $bufAfter,
            'capacity'      => $capacity,
        ];

        // Resolve a concrete staff member: the requested one when free, the
        // first free eligible member for "any available", or null on pages
        // without staff. false = staff exist but nobody is free.
        $staff = $this->slots->resolveStaffForSlot($config, $totalDuration, $slotStart, $slotOpts);
        if ($staff === false) {
            throw new \InvalidArgumentException('That time slot is no longer available. Please pick another.');
        }
        if ($staff === null && !$this->slots->slotIsAvailable($config, $totalDuration, $slotStart, null, $slotOpts)) {
            throw new \InvalidArgumentException('That time slot is no longer available. Please pick another.');
        }

        $tz       = $config->effectiveTimezone();
        $slotStart = $slotStart->copy()->setTimezone($tz);
        $slotEnd   = $slotStart->copy()->addMinutes($totalDuration);

        // Estimated bill (tax only, no coupons).
        $bill = $this->calculator->compute($config, $subtotal);

        $currency = $config->currency ?: 'USD';

        $request = DB::transaction(function () use (
            $config, $link, $data, $lines, $slotStart, $slotEnd, $totalDuration,
            $subtotal, $bill, $currency, $requiresPayment, $paymentCents, $aggMode,
            $staff, $bufBefore, $bufAfter
        ) {
            $initialStatus = $requiresPayment && $paymentCents > 0
                ? ServiceBookingRequest::STATUS_AWAITING_PAYMENT
                : ServiceBookingRequest::STATUS_PENDING;

            $request = ServiceBookingRequest::create([
                'service_booking_id'  => $config->id,
                'link_id'             => $link->id,
                'staff_id'            => $staff?->id,
                'buffer_before_minutes' => $bufBefore,
                'buffer_after_minutes'  => $bufAfter,
                'status'              => $initialStatus,
                'customer_name'       => $data['customer_name'],
                'customer_email'      => $data['customer_email'] ?? null,
                'customer_phone'      => $data['customer_phone'] ?? null,
                'customer_note'       => $data['customer_note'] ?? null,
                'slot_start'          => $slotStart,
                'slot_end'            => $slotEnd,
                'duration_minutes'    => $totalDuration,
                'subtotal'            => round($subtotal, 2),
                'tax_rate'            => $bill['tax_rate'],
                'tax_inclusive'       => $bill['tax_inclusive'],
                'tax_amount'          => $bill['tax_amount'],
                'total'               => $bill['total'],
                'currency'            => $currency,
                'payment_mode'        => $aggMode,
                'payment_status'      => $requiresPayment && $paymentCents > 0
                    ? ServiceBookingRequest::PAYMENT_STATUS_PENDING
                    : ServiceBookingRequest::PAYMENT_STATUS_NONE,
                'payment_amount_cents' => $requiresPayment ? $paymentCents : 0,
                'payment_currency'     => $currency,
                'checkout_expires_at'  => $requiresPayment && $paymentCents > 0
                    ? now()->addMinutes(30)
                    : null,
            ]);

            foreach ($lines as $line) {
                ServiceBookingRequestItem::create(array_merge($line, ['request_id' => $request->id]));
            }

            return $request;
        });

        $fresh = $request->fresh('items');

        // Only notify owner immediately for free bookings; paid bookings notify
        // after payment confirmation via notifyPaymentConfirmed().
        if (!$requiresPayment || $paymentCents <= 0) {
            $this->notifyOwner($link, $config, $fresh);
            $this->notifyVisitorReceived($link, $config, $fresh);
            $this->notifyStaff($fresh, 'placed');
        }

        return [
            'request'              => $request,
            'requires_payment'     => $requiresPayment && $paymentCents > 0,
            'payment_amount_cents' => $paymentCents,
            'payment_currency'     => $currency,
        ];
    }

    /**
     * Called by MonetizationCheckout::confirmBooking() after the provider
     * confirms the charge. Fires the owner new-request alert and the visitor
     * "payment confirmed" email.
     */
    public function notifyPaymentConfirmed(ServiceBookingRequest $request): void
    {
        $request->loadMissing(['items', 'serviceBooking', 'link']);
        $config = $request->serviceBooking;
        $link   = $request->link;
        if (!$config || !$link) {
            return;
        }

        // Owner alert (new booking arrived + paid).
        $this->notifyOwner($link, $config, $request, isPaid: true);

        // Push to the linked calendar when payment auto-confirmed the booking.
        $this->calendarSync->syncBookingEvent($request);

        // Assigned team member alert (paid bookings notify after payment).
        $this->notifyStaff($request, 'placed');

        // Visitor confirmation email.
        $email = trim((string) ($request->customer_email ?? ''));
        if ($email === '') {
            return;
        }

        [$serviceNames, $when, $estimated] = $this->visitorTokens($config, $request);
        $amountFmt = number_format($request->payment_amount_cents / 100, 2);

        try {
            Emailer::send('service_booking.payment_confirmed', $email, [
                'customer'          => $request->customer_name,
                'services'          => $serviceNames,
                'when'              => $when,
                'currency'          => $request->payment_currency ?? $request->currency,
                'amount'            => $amountFmt,
                'link_title'        => $link->title,
                'status_url'        => AppModulesCommonSupportPlatformHosts::outboundUrl(route('sb.public.booking.page', ['token' => $request->public_token])),
            ], ['related' => $request, 'to_name' => $request->customer_name]);
        } catch (\Throwable $e) {
            Log::warning('service_booking payment_confirmed visitor email failed: ' . $e->getMessage());
        }
    }

    /**
     * Called by MonetizationCheckout::refundBookingRequest() to notify the
     * visitor that their booking payment was refunded. No-op when no email.
     */
    public function notifyRefund(ServiceBookingRequest $request): void
    {
        $request->loadMissing(['items', 'serviceBooking', 'link']);
        $config = $request->serviceBooking;
        $link   = $request->link;

        $email = trim((string) ($request->customer_email ?? ''));
        if ($email === '' || !$config || !$link) {
            return;
        }

        [$serviceNames, $when] = $this->visitorTokens($config, $request);
        $amountFmt = number_format($request->payment_amount_cents / 100, 2);

        try {
            Emailer::send('service_booking.status_changed', $email, [
                'customer'   => $request->customer_name,
                'services'   => $serviceNames,
                'when'       => $when,
                'status'     => 'cancelled (refunded ' . ($request->payment_currency ?? $request->currency) . ' ' . $amountFmt . ')',
                'link_title' => $link->title,
                'status_url' => AppModulesCommonSupportPlatformHosts::outboundUrl(route('sb.public.booking.page', ['token' => $request->public_token])),
            ], ['related' => $request, 'to_name' => $request->customer_name]);
        } catch (\Throwable $e) {
            Log::warning('service_booking refund visitor email failed: ' . $e->getMessage());
        }
    }

    /** Fan a new-request alert to the page owner across all channels. */
    protected function notifyOwner(Link $link, ServiceBooking $config, ServiceBookingRequest $request, bool $isPaid = false): void
    {
        $owner = $link->user;
        if (!$owner) {
            return;
        }

        $tz          = $config->effectiveTimezone();
        $when        = Carbon::parse($request->slot_start)->setTimezone($tz)->format('D, M j · g:i A');
        $serviceNames = $request->items->pluck('name')->implode(', ');
        if ($serviceNames === '') {
            $serviceNames = 'a service';
        }
        $estimated = (float) ($request->total ?: $request->subtotal);

        $paidNote    = $isPaid
            ? ' (payment received: ' . ($request->payment_currency ?? $request->currency)
              . ' ' . number_format($request->payment_amount_cents / 100, 2) . ')'
            : '';
        $subject  = "New booking request · {$when}";
        $body     = "{$request->customer_name} requested {$serviceNames} for {$when}"
            . " · est. {$request->currency} " . number_format($estimated, 2)
            . " on \"{$link->title}\"" . $paidNote . ".";

        $bookingsUrl = route('user.links.service-booking.bookings', $link);
        $notification = null;
        try {
            $notification = $this->notifications->notify($owner, 'service_booking.new_request', [
                'subject'    => $subject,
                'message'    => $body,
                'link_id'    => $link->id,
                'link_alias' => $link->alias,
                'request_id' => $request->id,
                'slot_start' => Carbon::parse($request->slot_start)->toIso8601String(),
                'total'      => $request->total,
                'currency'   => $request->currency,
                'url'        => $bookingsUrl,
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

    /**
     * Email the visitor a "request received" confirmation with the tokenized
     * status link. No-op when no email was supplied.
     */
    protected function notifyVisitorReceived(Link $link, ServiceBooking $config, ServiceBookingRequest $request): void
    {
        $email = trim((string) ($request->customer_email ?? ''));
        if ($email === '') {
            return;
        }

        [$serviceNames, $when, $estimated] = $this->visitorTokens($config, $request);

        try {
            Emailer::send('service_booking.request_received', $email, [
                'customer'   => $request->customer_name,
                'services'   => $serviceNames,
                'when'       => $when,
                'currency'   => $request->currency,
                'total'      => number_format($estimated, 2),
                'link_title' => $link->title,
                'status_url' => AppModulesCommonSupportPlatformHosts::outboundUrl(route('sb.public.booking.page', ['token' => $request->public_token])),
            ], ['related' => $request, 'to_name' => $request->customer_name]);
        } catch (\Throwable $e) {
            Log::warning('service_booking request_received visitor email failed: ' . $e->getMessage());
        }
    }

    /**
     * Email the visitor when the owner advances their booking to a new status
     * (confirmed / declined / completed / cancelled). Resolves the link/config
     * from the request so both the web + API status controllers can call it.
     * No-op when no email was supplied.
     */
    public function notifyStatusChange(ServiceBookingRequest $request): void
    {
        $request->loadMissing(['items', 'serviceBooking', 'link']);
        $config = $request->serviceBooking;
        $link   = $request->link;
        if (!$config || !$link) {
            return;
        }

        // Two-way calendar sync: push confirmed bookings, remove cancelled /
        // declined ones. Never blocks the status flow (Task #6325).
        $this->calendarSync->syncBookingEvent($request);

        // Tell the assigned team member when their appointment is called off.
        if (in_array($request->status, [
            ServiceBookingRequest::STATUS_CANCELLED,
            ServiceBookingRequest::STATUS_DECLINED,
        ], true)) {
            $this->notifyStaff($request, 'cancelled');
        }

        $email = trim((string) ($request->customer_email ?? ''));
        if ($email === '') {
            return;
        }

        [$serviceNames, $when] = $this->visitorTokens($config, $request);

        try {
            Emailer::send('service_booking.status_changed', $email, [
                'customer'   => $request->customer_name,
                'services'   => $serviceNames,
                'when'       => $when,
                'status'     => $request->status_label,
                'link_title' => $link->title,
                'status_url' => AppModulesCommonSupportPlatformHosts::outboundUrl(route('sb.public.booking.page', ['token' => $request->public_token])),
            ], ['related' => $request, 'to_name' => $request->customer_name]);
        } catch (\Throwable $e) {
            Log::warning('service_booking status_changed visitor email failed: ' . $e->getMessage());
        }
    }

    // ── Visitor self-service (Task #6325) ────────────────────────────

    /**
     * Why the visitor may NOT perform $action ('cancel'|'reschedule') on this
     * booking right now, or null when allowed. Enforces the owner's toggles,
     * the changeable statuses, and the owner-set cutoff window.
     */
    public function selfServiceBlocker(ServiceBookingRequest $request, string $action): ?string
    {
        $request->loadMissing('serviceBooking');
        $config = $request->serviceBooking;
        if (!$config) {
            return 'This booking can no longer be changed.';
        }

        $allowed = $action === 'cancel'
            ? $config->selfServiceAllowsCancel()
            : $config->selfServiceAllowsReschedule();
        if (!$allowed) {
            return $action === 'cancel'
                ? 'Online cancellation is not available for this booking — please contact the provider.'
                : 'Online rescheduling is not available for this booking — please contact the provider.';
        }

        if (!in_array($request->status, [
            ServiceBookingRequest::STATUS_PENDING,
            ServiceBookingRequest::STATUS_CONFIRMED,
        ], true)) {
            return 'This booking can no longer be changed.';
        }

        $cutoff = Carbon::parse($request->slot_start)->subHours($config->selfServiceCutoffHours());
        if (now()->gte($cutoff)) {
            return $config->selfServiceCutoffHours() > 0
                ? 'The change window for this booking has closed — please contact the provider.'
                : 'This booking has already started.';
        }

        return null;
    }

    /**
     * Visitor cancels their own booking from the tokenized status page.
     * Refunds a paid booking the same way an owner cancellation does.
     */
    public function visitorCancel(ServiceBookingRequest $request): void
    {
        $request->update(['status' => ServiceBookingRequest::STATUS_CANCELLED]);
        $fresh = $request->fresh(['items', 'serviceBooking', 'link', 'staff']);

        if ($fresh->isRefundable()) {
            try {
                app(\App\Services\Monetization\MonetizationCheckout::class)
                    ->refundBookingRequest($fresh->id);
            } catch (\Throwable $e) {
                Log::warning('booking.self_cancel.refund_failed', [
                    'booking' => $fresh->id, 'err' => $e->getMessage(),
                ]);
            }
        }

        $this->calendarSync->syncBookingEvent($fresh);
        $this->notifyOwnerVisitorChange($fresh, 'cancelled');
        $this->notifyStaff($fresh, 'cancelled');
    }

    /**
     * Visitor moves their own booking to a new (validated-free) slot. The
     * caller must have already re-checked availability. Confirmed bookings
     * stay confirmed; the calendar event follows the new time.
     */
    public function visitorReschedule(ServiceBookingRequest $request, Carbon $newStart): void
    {
        $config = $request->serviceBooking;
        $tz     = $config->effectiveTimezone();
        $start  = $newStart->copy()->setTimezone($tz);

        $request->update([
            'slot_start' => $start,
            'slot_end'   => $start->copy()->addMinutes(max(1, (int) $request->duration_minutes)),
        ]);

        $fresh = $request->fresh(['items', 'serviceBooking', 'link', 'staff']);
        $this->calendarSync->syncBookingEvent($fresh);
        $this->notifyOwnerVisitorChange($fresh, 'rescheduled');
        $this->notifyStaff($fresh, 'rescheduled');

        // Tell the visitor too (their confirmation email shows the old time).
        $this->notifyStatusChange($fresh);
    }

    /**
     * Email the assigned team member (when they have a notification email)
     * that a booking was placed / rescheduled / cancelled for them.
     * Never blocks the booking flow (Task #6338).
     */
    protected function notifyStaff(ServiceBookingRequest $request, string $action): void
    {
        $request->loadMissing(['staff', 'serviceBooking', 'link', 'items']);

        $staff = $request->staff;
        $email = trim((string) ($staff?->email ?? ''));
        if (!$staff || $email === '') {
            return;
        }

        $config = $request->serviceBooking;
        $link   = $request->link;
        if (!$config || !$link) {
            return;
        }

        [$serviceNames, $when] = $this->visitorTokens($config, $request);

        try {
            Emailer::send('service_booking.staff_booking', $email, [
                'staff_name' => $staff->name,
                'action'     => $action,
                'customer'   => $request->customer_name,
                'services'   => $serviceNames,
                'when'       => $when,
                'link_title' => $link->title,
            ], ['related' => $request, 'to_name' => $staff->name]);
        } catch (\Throwable $e) {
            Log::warning('service_booking staff email failed: ' . $e->getMessage(), [
                'booking' => $request->id, 'action' => $action,
            ]);
        }
    }

    /** Owner alert (in-app + email + push) about a visitor-made change. */
    protected function notifyOwnerVisitorChange(ServiceBookingRequest $request, string $action): void
    {
        $config = $request->serviceBooking;
        $link   = $request->link;
        $owner  = $link?->user;
        if (!$config || !$link || !$owner) {
            return;
        }

        [$serviceNames, $when] = $this->visitorTokens($config, $request);
        $subject = 'Booking ' . $action . ' · ' . $when;
        $body    = "{$request->customer_name} {$action} their booking for {$serviceNames} on \"{$link->title}\" ({$when}).";
        $bookingsUrl = route('user.links.service-booking.bookings', $link);

        $notification = null;
        try {
            $notification = $this->notifications->notify($owner, 'service_booking.visitor_change', [
                'subject'    => $subject,
                'message'    => $body,
                'link_id'    => $link->id,
                'link_alias' => $link->alias,
                'request_id' => $request->id,
                'action'     => $action,
                'slot_start' => Carbon::parse($request->slot_start)->toIso8601String(),
                'url'        => $bookingsUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('service_booking visitor_change in-app notify failed: ' . $e->getMessage());
        }

        if ($owner->email && $this->notifications->prefersChannel($owner->id, 'service_booking.visitor_change', 'email')) {
            try {
                Emailer::send('service_booking.visitor_change', $owner->email, [
                    'customer'     => $request->customer_name,
                    'action'       => $action,
                    'services'     => $serviceNames,
                    'when'         => $when,
                    'link_title'   => $link->title,
                    'bookings_url' => $bookingsUrl,
                ], ['user' => $owner->id, 'related' => $request]);
            } catch (\Throwable $e) {
                Log::warning('service_booking visitor_change email failed: ' . $e->getMessage());
            }
        }

        $this->notifications->pushToUser(
            $owner,
            'service_booking.visitor_change',
            $subject,
            $body,
            array_merge(
                ['link_id' => $link->id, 'request_id' => $request->id, 'url' => $bookingsUrl],
                $notification ? ['notification_id' => $notification->id] : [],
            ),
        );
    }

    /**
     * Shared visitor-email tokens: a human service list, the formatted slot in
     * the provider's timezone, and the estimated total.
     *
     * @return array{0:string,1:string,2:float}
     */
    protected function visitorTokens(ServiceBooking $config, ServiceBookingRequest $request): array
    {
        $tz   = $config->effectiveTimezone();
        $when = $request->slot_start
            ? Carbon::parse($request->slot_start)->setTimezone($tz)->format('D, M j · g:i A')
            : 'your requested time';

        $serviceNames = $request->items->pluck('name')->implode(', ');
        if ($serviceNames === '') {
            $serviceNames = 'a service';
        }

        $estimated = (float) ($request->total ?: $request->subtotal);

        return [$serviceNames, $when, $estimated];
    }
}
