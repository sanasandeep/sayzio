<?php

namespace App\Modules\Common\Controllers;

use App\Modules\Common\Services\ServiceBookingEstimateCalculator;
use App\Modules\Common\Services\ServiceBookingRequestService;
use App\Modules\Common\Services\SlotAvailabilityService;
use App\Modules\Common\Services\WhatsappOrderLink;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\ServiceBookingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Visitor-facing Service Booking endpoints (Task #3085). Mounted under the
 * `/sb/` prefix so they never collide with the catch-all `/{alias}` route.
 * No authentication and no online payment — the visitor settles with the
 * provider directly; every total is an *estimate* only.
 */
class PublicServiceBookingController extends Controller
{
    public function __construct(
        protected ServiceBookingRequestService $requests,
        protected ServiceBookingEstimateCalculator $calculator,
        protected SlotAvailabilityService $slots,
    ) {
    }

    /**
     * Genuinely-free upcoming slots for the combined duration of the chosen
     * services. Re-priced and re-computed server-side so a tampered cart can
     * never widen availability.
     */
    public function slotsFor(Request $request, string $alias)
    {
        [$link, $config] = $this->resolveConfig($alias);
        if (!$link || !$config || !$link->isAccessible() || !$config->isBookingMode()) {
            return response()->json(['error' => ['message' => 'Booking page not found', 'code' => 'not_found']], 404);
        }

        $data = $request->validate([
            'services'              => 'required|array|min:1',
            'services.*.service_id' => 'required|integer',
            'services.*.quantity'   => 'nullable|integer|min:1|max:99',
        ]);

        [$duration] = $this->priceCart($config, $data['services']);
        if ($duration < 1) {
            return response()->json(['error' => ['message' => 'Pick at least one available service.', 'code' => 'no_services']], 422);
        }

        return response()->json(['data' => [
            'duration_minutes' => $duration,
            'timezone'         => $config->effectiveTimezone(),
            'days'             => $this->slots->freeSlots($config, $duration),
        ]]);
    }

    /**
     * Live estimated-price quote for the cart the visitor is building. No
     * request is created.
     */
    public function quote(Request $request, string $alias)
    {
        [$link, $config] = $this->resolveConfig($alias);
        if (!$link || !$config || !$link->isAccessible() || !$config->isBookingMode()) {
            return response()->json(['error' => ['message' => 'Booking page not found', 'code' => 'not_found']], 404);
        }

        $data = $request->validate([
            'services'              => 'required|array|min:1',
            'services.*.service_id' => 'required|integer',
            'services.*.quantity'   => 'nullable|integer|min:1|max:99',
        ]);

        [$duration, $subtotal, $lines] = $this->priceCart($config, $data['services']);
        if ($duration < 1) {
            return response()->json(['error' => ['message' => 'Pick at least one available service.', 'code' => 'no_services']], 422);
        }

        $bill = $this->calculator->compute($config, $subtotal);

        return response()->json(['data' => [
            'duration_minutes' => $duration,
            'lines'            => $lines,
            'bill'             => ServiceBookingEstimateCalculator::serialize($bill),
        ]]);
    }

    /** Submit a booking request (booking mode only). */
    public function book(Request $request, string $alias)
    {
        [$link, $config] = $this->resolveConfig($alias);
        if (!$link || !$config || !$link->isAccessible()) {
            return response()->json(['error' => ['message' => 'Booking page not found', 'code' => 'not_found']], 404);
        }
        if ($gate = $this->bookingVisibilityGate($request, $link)) {
            return $gate;
        }
        if (!$config->isBookingMode()) {
            return response()->json(['error' => ['message' => 'Booking is not enabled for this page', 'code' => 'booking_disabled']], 422);
        }

        $data = $request->validate([
            'customer_name'         => 'required|string|max:120',
            'customer_email'        => 'nullable|email|max:160',
            'customer_phone'        => 'nullable|string|max:40',
            'customer_note'         => 'nullable|string|max:1000',
            'slot_start'            => 'required|string|max:64',
            'services'              => 'required|array|min:1',
            'services.*.service_id' => 'required|integer',
            'services.*.quantity'   => 'nullable|integer|min:1|max:99',
        ]);

        try {
            $result = $this->requests->place($link, $config, $data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => ['message' => $e->getMessage(), 'code' => 'invalid_request']], 422);
        }

        $booking = $result['request'];
        $booking->loadMissing('items');

        // Paid booking: start a checkout and return the provider URL.
        if ($result['requires_payment']) {
            $owner = $link->user;
            $responseData = ['booking' => $this->serializeGuestBooking($booking)];
            try {
                $checkout = app(\App\Services\Monetization\MonetizationCheckout::class)->startBooking(
                    $owner,
                    $booking,
                    $data['customer_email'] ?? '',
                );
                $responseData['checkout_url']      = $checkout['url'];
                $responseData['checkout_expires_at'] = optional($booking->checkout_expires_at)->toIso8601String();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('booking.checkout.start_failed', [
                    'booking' => $booking->id, 'err' => $e->getMessage(),
                ]);
            }
            return response()->json(['data' => $responseData], 201);
        }

        return response()->json(['data' => [
            'booking' => $this->serializeGuestBooking($booking),
        ]], 201);
    }

    /** Visitor polls their own booking with the public token (JSON). */
    public function bookingStatus(Request $request, string $token)
    {
        $booking = ServiceBookingRequest::with('items')->where('public_token', $token)->first();
        if (!$booking) {
            return response()->json(['error' => ['message' => 'Booking not found', 'code' => 'not_found']], 404);
        }

        return response()->json(['data' => ['booking' => $this->serializeGuestBooking($booking)]]);
    }

    /** Tokenized HTML status page the visitor can bookmark. */
    public function bookingPage(Request $request, string $token)
    {
        $booking = ServiceBookingRequest::with(['items', 'serviceBooking', 'link'])
            ->where('public_token', $token)
            ->first();
        abort_unless($booking, 404);

        $link = $booking->link;
        $config = $booking->serviceBooking;

        $whatsapp = ($config && $link)
            ? WhatsappOrderLink::build($config, $booking, $link->title)
            : null;

        return response()->view('common.service-booking-status', compact('booking', 'link', 'config', 'whatsapp'));
    }

    /**
     * Price + total-duration the requested cart against the live catalog,
     * skipping unavailable/inactive services.
     *
     * @return array{0:int,1:float,2:array<int,array<string,mixed>>}
     */
    protected function priceCart(ServiceBooking $config, array $items): array
    {
        $ids = collect($items)->pluck('service_id')->map(fn ($i) => (int) $i)->all();
        $rows = ServiceBookingService::where('service_booking_id', $config->id)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $duration = 0;
        $subtotal = 0.0;
        $lines = [];
        foreach ($items as $row) {
            $service = $rows->get((int) $row['service_id']);
            if (!$service || $service->is_unavailable) {
                continue;
            }
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $lineTotal = round(((float) $service->price) * $qty, 2);
            $subtotal += $lineTotal;
            $duration += max(1, (int) $service->duration_minutes) * $qty;
            $lines[] = [
                'service_id'       => $service->id,
                'name'             => $service->name,
                'unit_price'       => (float) $service->price,
                'duration_minutes' => (int) $service->duration_minutes,
                'quantity'         => $qty,
                'line_total'       => $lineTotal,
            ];
        }

        return [$duration, round($subtotal, 2), $lines];
    }

    /** Public guest-booking shape, including the estimated breakdown. */
    public static function serializeGuestBooking(ServiceBookingRequest $booking): array
    {
        $config = $booking->relationLoaded('serviceBooking')
            ? $booking->serviceBooking
            : $booking->serviceBooking()->first();
        $link = $booking->relationLoaded('link')
            ? $booking->link
            : $booking->link()->first();
        $whatsapp = ($config && $link)
            ? WhatsappOrderLink::build($config, $booking, $link->title)
            : null;

        return [
            'public_token'          => $booking->public_token,
            'status'                => $booking->status,
            'status_label'          => $booking->status_label,
            'whatsapp'              => $whatsapp,
            'customer_name'         => $booking->customer_name,
            'slot_start'            => optional($booking->slot_start)->toIso8601String(),
            'slot_end'              => optional($booking->slot_end)->toIso8601String(),
            'duration_minutes'      => $booking->duration_minutes,
            'subtotal'              => $booking->subtotal,
            'tax_inclusive'         => (bool) $booking->tax_inclusive,
            'tax_rate'              => $booking->tax_rate,
            'tax_amount'            => $booking->tax_amount,
            'total'                 => $booking->total,
            'currency'              => $booking->currency,
            'is_estimate'           => true,
            'payment_mode'          => $booking->payment_mode ?? 'none',
            'payment_status'        => $booking->payment_status ?? 'none',
            'payment_amount_cents'  => (int) ($booking->payment_amount_cents ?? 0),
            'payment_currency'      => $booking->payment_currency,
            'checkout_expires_at'   => optional($booking->checkout_expires_at)->toIso8601String(),
            'created_at'            => optional($booking->created_at)->toIso8601String(),
            'items'                 => $booking->relationLoaded('items')
                ? $booking->items->map(fn ($i) => [
                    'name'       => $i->name,
                    'quantity'   => $i->quantity,
                    'line_total' => $i->line_total,
                ])->all()
                : [],
        ];
    }

    protected function resolveConfig(string $alias): array
    {
        $link = Link::resolveByAlias($alias, request()->getHost());

        if (!$link || $link->type !== Link::TYPE_SERVICE_BOOKING || !$link->is_active) {
            return [null, null];
        }

        return [$link, $link->serviceBooking()->first()];
    }

    /**
     * Enforce the link's visibility tier on the booking POST, mirroring
     * PublicRestaurantController::orderVisibilityGate and the public page gate.
     * Returns a JSON error response when gated, or null to proceed.
     */
    protected function bookingVisibilityGate(Request $request, Link $link)
    {
        $vis = $link->visibility ?? 'public';
        if ($vis === 'public') {
            return null;
        }

        $viewer   = $request->user();
        $viewerId = \App\Modules\Common\Services\ViewerSession::id() ?: optional($viewer)->id;
        if ($viewerId && (int) $viewerId === (int) $link->user_id) {
            return null;
        }

        if (!$viewerId) {
            return response()->json(['error' => ['message' => 'Sign in required to book on this page', 'code' => 'auth_required']], 401);
        }
        if ($vis === 'registered') {
            return null;
        }

        $owner = $link->user;
        if ($vis === 'followers') {
            $ok = \App\Modules\User\Models\Follow::where('follower_id', $viewerId)
                ->where('creator_id', $owner->id)->exists();
            return $ok ? null : response()->json(['error' => ['message' => 'Follow this creator to book', 'code' => 'follow_required']], 403);
        }
        if ($vis === 'subscribers') {
            $email = $viewer?->email;
            $ok = $email && \App\Modules\User\Models\Subscriber::where('user_id', $owner->id)
                ->where('email', $email)->where('status', 'active')->exists();
            return $ok ? null : response()->json(['error' => ['message' => 'Subscribe to this creator to book', 'code' => 'subscribe_required']], 403);
        }

        return response()->json(['error' => ['message' => 'Not allowed', 'code' => 'forbidden']], 403);
    }
}
