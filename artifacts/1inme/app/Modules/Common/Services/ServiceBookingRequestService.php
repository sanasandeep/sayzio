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

        // Parse + re-validate the chosen slot is still free.
        try {
            $slotStart = Carbon::parse($data['slot_start']);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('That time slot is invalid.');
        }
        if (!$this->slots->slotIsAvailable($config, $totalDuration, $slotStart)) {
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
            $subtotal, $bill, $currency, $requiresPayment, $paymentCents, $aggMode
        ) {
            $initialStatus = $requiresPayment && $paymentCents > 0
                ? ServiceBookingRequest::STATUS_AWAITING_PAYMENT
                : ServiceBookingRequest::STATUS_PENDING;

            $request = ServiceBookingRequest::create([
                'service_booking_id'  => $config->id,
                'link_id'             => $link->id,
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
