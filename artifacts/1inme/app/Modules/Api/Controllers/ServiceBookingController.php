<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Controllers\PublicServiceBookingController;
use App\Modules\Common\Services\ServiceBookingEstimateCalculator;
use App\Modules\Common\Services\ServiceBookingRequestService;
use App\Modules\Common\Services\SlotAvailabilityService;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingAvailabilityRule;
use App\Modules\User\Models\ServiceBookingBlockedDate;
use App\Modules\User\Models\ServiceBookingCategory;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\ServiceBookingService;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * REST API parity for the Service Booking page (Task #3085).
 *
 * Public (optional auth): fetch a booking page by alias, see genuinely-free
 * slots, get an estimated-price quote, submit a booking request, and poll a
 * guest booking's status by its public token.
 * Authenticated (Sanctum): owner builder (settings, categories, services,
 * availability rules, blocked dates) and a near-real-time bookings dashboard
 * with a server-enforced status workflow — mirrors the web ServiceBookingController.
 *
 * Unified `{data}` / `{error}` envelope via ApiResponses. No online payment —
 * every total is an *estimate*; the visitor settles with the provider directly.
 */
class ServiceBookingController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected ServiceBookingRequestService $requests,
        protected ServiceBookingEstimateCalculator $calculator,
        protected SlotAvailabilityService $slots,
    ) {
    }

    // ── Public ───────────────────────────────────────────────────

    /** Public booking-page fetch by alias. */
    public function show(Request $request, string $alias)
    {
        [$link, $config] = $this->resolvePublic($request, $alias);
        if (!$link) {
            return $this->notFound('Booking page not found');
        }
        if ($gate = $this->checkVisibility($link, $request->user())) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }
        if (!$config) {
            return $this->notFound('Booking page not found');
        }

        $config->load([
            'categories' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
            'services'   => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ]);

        $byCat = $config->services->groupBy('category_id');

        return $this->ok([
            'config' => [
                'mode'            => $config->mode,
                'currency'        => $config->currency,
                'accent_color'    => $config->accent_color,
                'booking_enabled' => $config->isBookingMode(),
                'timezone'        => $config->effectiveTimezone(),
                'tax'             => $this->taxPayload($config),
            ],
            'link' => [
                'alias'       => $link->alias,
                'title'       => $link->title,
                'description' => $link->description,
            ],
            'categories' => $config->categories->map(fn ($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'description' => $c->description,
                'services'    => ($byCat->get($c->id) ?? collect())->map(fn ($s) => $this->publicService($s))->values(),
            ])->values(),
            'uncategorized' => ($byCat->get(null) ?? collect())->map(fn ($s) => $this->publicService($s))->values(),
        ]);
    }

    /** Genuinely-free upcoming slots for the chosen services' combined duration. */
    public function slots(Request $request, string $alias)
    {
        [$link, $config] = $this->resolvePublic($request, $alias);
        if (!$link || !$config || !$config->isBookingMode()) {
            return $this->notFound('Booking page not found');
        }
        if ($gate = $this->checkVisibility($link, $request->user())) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $data = $this->validateCart($request);
        [$duration] = $this->priceCart($config, $data['services']);
        if ($duration < 1) {
            return $this->fail('Pick at least one available service.', 422, 'no_services');
        }

        return $this->ok([
            'duration_minutes' => $duration,
            'timezone'         => $config->effectiveTimezone(),
            'days'             => $this->slots->freeSlots($config, $duration),
        ]);
    }

    /** Live estimated-price quote for the cart. No request is created. */
    public function quote(Request $request, string $alias)
    {
        [$link, $config] = $this->resolvePublic($request, $alias);
        if (!$link || !$config || !$config->isBookingMode()) {
            return $this->notFound('Booking page not found');
        }
        if ($gate = $this->checkVisibility($link, $request->user())) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $data = $this->validateCart($request);
        [$duration, $subtotal, $lines] = $this->priceCart($config, $data['services']);
        if ($duration < 1) {
            return $this->fail('Pick at least one available service.', 422, 'no_services');
        }

        $bill = $this->calculator->compute($config, $subtotal);

        return $this->ok([
            'duration_minutes' => $duration,
            'lines'            => $lines,
            'bill'             => ServiceBookingEstimateCalculator::serialize($bill),
        ]);
    }

    /** Submit a booking request (booking mode only). */
    public function book(Request $request, string $alias)
    {
        [$link, $config] = $this->resolvePublic($request, $alias);
        if (!$link) {
            return $this->notFound('Booking page not found');
        }
        if ($gate = $this->checkVisibility($link, $request->user())) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }
        if (!$config || !$config->isBookingMode()) {
            return $this->fail('Booking is not enabled for this page', 422, 'booking_disabled');
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
            $booking = $this->requests->place($link, $config, $data);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422, 'invalid_request');
        }

        $booking->loadMissing('items');

        return $this->created(['booking' => PublicServiceBookingController::serializeGuestBooking($booking)]);
    }

    /** Guest polls their own booking status with the public token. */
    public function bookingStatus(Request $request, string $token)
    {
        $booking = ServiceBookingRequest::with('items')->where('public_token', $token)->first();
        if (!$booking) {
            return $this->notFound('Booking not found');
        }

        return $this->ok(['booking' => PublicServiceBookingController::serializeGuestBooking($booking)]);
    }

    // ── Owner (Sanctum) ──────────────────────────────────────────

    /** Owner: full config — settings, categories+services, availability, blocked dates. */
    public function ownerConfig(Request $request, Link $link)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config) {
            return $this->notFound('Booking page not found');
        }

        return $this->ok(['config' => $this->ownerConfigPayload($config, $link)]);
    }

    /** Owner: update booking settings (mode/currency/accent/scheduling/tax). */
    public function saveSettings(Request $request, Link $link)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config) {
            return $this->notFound('Booking page not found');
        }

        $data = $request->validate([
            'mode'                => 'required|in:display,booking',
            'currency'            => 'required|string|size:3',
            'accent_color'        => 'nullable|string|max:16',
            'slot_length_minutes' => 'required|integer|min:5|max:1440',
            'lead_time_minutes'   => 'required|integer|min:0|max:43200',
            'max_days_ahead'      => 'required|integer|min:1|max:365',
            'timezone'            => 'nullable|string|max:64',
            'tax_enabled'         => 'sometimes|boolean',
            'tax_rate'            => 'nullable|numeric|min:0|max:100',
            'tax_inclusive'       => 'sometimes|boolean',
            'tax_label'           => 'nullable|string|max:24',
            'whatsapp_number'     => 'sometimes|nullable|string|max:40',
        ]);

        $settings = $config->settings ?? [];

        // Optional WhatsApp click-to-chat number (Task #3102).
        if ($request->has('whatsapp_number')) {
            $wa = trim((string) ($data['whatsapp_number'] ?? ''));
            if ($wa === '') {
                unset($settings['whatsapp_number']);
            } else {
                $settings['whatsapp_number'] = $wa;
            }
        }

        if ($request->has('tax_enabled') || $request->has('tax_rate')
            || $request->has('tax_inclusive') || $request->has('tax_label')) {
            $settings['tax'] = [
                'enabled'   => (bool) ($data['tax_enabled'] ?? false),
                'rate'      => round((float) ($data['tax_rate'] ?? 0), 3),
                'inclusive' => (bool) ($data['tax_inclusive'] ?? false),
                'label'     => trim((string) ($data['tax_label'] ?? 'GST')) ?: 'GST',
            ];
        }

        $tz = trim((string) ($data['timezone'] ?? ''));
        if ($tz !== '' && !in_array($tz, timezone_identifiers_list(), true)) {
            return $this->fail('That timezone is not recognized.', 422, 'invalid_timezone');
        }

        $config->update([
            'mode'                => $data['mode'],
            'currency'            => strtoupper($data['currency']),
            'accent_color'        => $data['accent_color'] ?? $config->accent_color,
            'slot_length_minutes' => $data['slot_length_minutes'],
            'lead_time_minutes'   => $data['lead_time_minutes'],
            'max_days_ahead'      => $data['max_days_ahead'],
            'timezone'            => $tz !== '' ? $tz : null,
            'settings'            => $settings,
        ]);

        return $this->ok(['config' => $this->ownerConfigPayload($config->fresh(), $link)]);
    }

    /** Owner: upload a photo for a service; returns the public URL. */
    public function uploadServicePhoto(Request $request, Link $link)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config) {
            return $this->notFound('Booking page not found');
        }

        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $user = $request->user();

        try {
            $userFile = UserFile::createFromUpload($request->file('photo'), $user, [
                'max_size_mb'    => 5,
                'compress_image' => true,
                'max_width'      => 1000,
                'max_height'     => 1000,
                'quality'        => 85,
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'upload_failed');
        }

        // Sanctum API path doesn't bind the active workspace, so back-fill it.
        if ($userFile->workspace_id === null) {
            $userFile->workspace_id = $this->activeWorkspaceId($user);
            $userFile->save();
        }

        return $this->ok(['photo_url' => $userFile->url]);
    }

    // ── Categories ───────────────────────────────────────────────
    public function storeCategory(Request $request, Link $link)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config) {
            return $this->notFound('Booking page not found');
        }

        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
        ]);

        $category = ServiceBookingCategory::create([
            'service_booking_id' => $config->id,
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'sort_order'         => (int) ServiceBookingCategory::where('service_booking_id', $config->id)->max('sort_order') + 1,
        ]);

        return $this->created(['category' => $this->ownerCategory($category, collect())]);
    }

    public function updateCategory(Request $request, Link $link, ServiceBookingCategory $category)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config || (int) $category->service_booking_id !== (int) $config->id) {
            return $this->notFound('Category not found');
        }

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'sometimes|boolean',
        ]);

        $category->update($data);

        $services = ServiceBookingService::where('category_id', $category->id)->orderBy('sort_order')->get();

        return $this->ok(['category' => $this->ownerCategory($category->fresh(), $services)]);
    }

    public function destroyCategory(Request $request, Link $link, ServiceBookingCategory $category)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config || (int) $category->service_booking_id !== (int) $config->id) {
            return $this->notFound('Category not found');
        }

        // Detach services rather than deleting them (web parity).
        ServiceBookingService::where('category_id', $category->id)->update(['category_id' => null]);
        $category->delete();

        return $this->ok(['deleted' => true]);
    }

    // ── Services ─────────────────────────────────────────────────
    public function storeService(Request $request, Link $link)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config) {
            return $this->notFound('Booking page not found');
        }

        $data = $request->validate([
            'category_id'      => 'nullable|integer',
            'name'             => 'required|string|max:160',
            'description'      => 'nullable|string|max:800',
            'price'            => 'nullable|numeric|min:0|max:9999999',
            'currency'         => 'nullable|string|size:3',
            'duration_minutes' => 'required|integer|min:5|max:1440',
            'photo_url'        => 'nullable|string|max:1024',
            'is_unavailable'   => 'sometimes|boolean',
        ]);

        if (!empty($data['category_id'])) {
            $owned = ServiceBookingCategory::where('service_booking_id', $config->id)->find($data['category_id']);
            if (!$owned) {
                return $this->fail('Category not found', 422, 'invalid_category');
            }
        }

        $service = ServiceBookingService::create([
            'service_booking_id' => $config->id,
            'category_id'        => $data['category_id'] ?? null,
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'price'              => $data['price'] ?? 0,
            'currency'           => isset($data['currency']) ? strtoupper($data['currency']) : null,
            'duration_minutes'   => $data['duration_minutes'],
            'photo_url'          => $data['photo_url'] ?? null,
            'is_unavailable'     => (bool) ($data['is_unavailable'] ?? false),
            'sort_order'         => (int) ServiceBookingService::where('service_booking_id', $config->id)->max('sort_order') + 1,
        ]);

        return $this->created(['service' => $this->ownerService($service)]);
    }

    public function updateService(Request $request, Link $link, ServiceBookingService $service)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config || (int) $service->service_booking_id !== (int) $config->id) {
            return $this->notFound('Service not found');
        }

        $data = $request->validate([
            'category_id'      => 'nullable|integer',
            'name'             => 'sometimes|required|string|max:160',
            'description'      => 'nullable|string|max:800',
            'price'            => 'sometimes|numeric|min:0|max:9999999',
            'currency'         => 'nullable|string|size:3',
            'duration_minutes' => 'sometimes|integer|min:5|max:1440',
            'photo_url'        => 'nullable|string|max:1024',
            'is_unavailable'   => 'sometimes|boolean',
            'is_active'        => 'sometimes|boolean',
        ]);

        if (array_key_exists('category_id', $data) && !empty($data['category_id'])) {
            $owned = ServiceBookingCategory::where('service_booking_id', $config->id)->find($data['category_id']);
            if (!$owned) {
                return $this->fail('Category not found', 422, 'invalid_category');
            }
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $service->update($data);

        return $this->ok(['service' => $this->ownerService($service->fresh())]);
    }

    public function destroyService(Request $request, Link $link, ServiceBookingService $service)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config || (int) $service->service_booking_id !== (int) $config->id) {
            return $this->notFound('Service not found');
        }

        $service->delete();

        return $this->ok(['deleted' => true]);
    }

    // ── Weekly availability rules ────────────────────────────────
    public function storeAvailability(Request $request, Link $link)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config) {
            return $this->notFound('Booking page not found');
        }

        $data = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
        ]);

        $rule = ServiceBookingAvailabilityRule::create([
            'service_booking_id' => $config->id,
            'day_of_week'        => $data['day_of_week'],
            'start_time'         => $data['start_time'],
            'end_time'           => $data['end_time'],
            'is_active'          => true,
        ]);

        return $this->created(['rule' => $this->ownerRule($rule)]);
    }

    public function updateAvailability(Request $request, Link $link, ServiceBookingAvailabilityRule $rule)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config || (int) $rule->service_booking_id !== (int) $config->id) {
            return $this->notFound('Availability rule not found');
        }

        $data = $request->validate([
            'day_of_week' => 'sometimes|integer|min:0|max:6',
            'start_time'  => 'sometimes|date_format:H:i',
            'end_time'    => 'sometimes|date_format:H:i',
            'is_active'   => 'sometimes|boolean',
        ]);

        $start = $data['start_time'] ?? $rule->start_time;
        $end   = $data['end_time'] ?? $rule->end_time;
        if (strtotime($end) <= strtotime($start)) {
            return $this->fail('End time must be after start time.', 422, 'invalid_window');
        }

        $rule->update($data);

        return $this->ok(['rule' => $this->ownerRule($rule->fresh())]);
    }

    public function destroyAvailability(Request $request, Link $link, ServiceBookingAvailabilityRule $rule)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config || (int) $rule->service_booking_id !== (int) $config->id) {
            return $this->notFound('Availability rule not found');
        }

        $rule->delete();

        return $this->ok(['deleted' => true]);
    }

    // ── Blocked dates ────────────────────────────────────────────
    public function storeBlockedDate(Request $request, Link $link)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config) {
            return $this->notFound('Booking page not found');
        }

        $data = $request->validate([
            'date'   => 'required|date',
            'reason' => 'nullable|string|max:160',
        ]);

        $date = \Carbon\Carbon::parse($data['date'])->format('Y-m-d');
        if (ServiceBookingBlockedDate::where('service_booking_id', $config->id)->whereDate('date', $date)->exists()) {
            return $this->fail('That date is already blocked.', 422, 'duplicate_date');
        }

        $blocked = ServiceBookingBlockedDate::create([
            'service_booking_id' => $config->id,
            'date'               => $date,
            'reason'             => $data['reason'] ?? null,
        ]);

        return $this->created(['blocked_date' => $this->ownerBlockedDate($blocked)]);
    }

    public function destroyBlockedDate(Request $request, Link $link, ServiceBookingBlockedDate $blockedDate)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config || (int) $blockedDate->service_booking_id !== (int) $config->id) {
            return $this->notFound('Blocked date not found');
        }

        $blockedDate->delete();

        return $this->ok(['deleted' => true]);
    }

    // ── Bookings dashboard ───────────────────────────────────────
    public function ownerBookings(Request $request, Link $link)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config) {
            return $this->notFound('Booking page not found');
        }

        $bookings = ServiceBookingRequest::with('items')
            ->where('service_booking_id', $config->id)
            ->latest()
            ->limit(100)
            ->get();

        return $this->ok([
            'bookings'    => $bookings->map(fn ($b) => $this->ownerBooking($b))->values(),
            'open_count'  => $this->openCount($config->id),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function ownerPoll(Request $request, Link $link)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config) {
            return $this->notFound('Booking page not found');
        }

        $query = ServiceBookingRequest::with('items')->where('service_booking_id', $config->id);
        if ($since = $request->query('since')) {
            try {
                $query->where('updated_at', '>', \Carbon\Carbon::parse($since));
            } catch (\Throwable $e) {
                // ignore bad cursor
            }
        }

        $bookings = $query->latest('updated_at')->limit(100)->get();

        return $this->ok([
            'bookings'    => $bookings->map(fn ($b) => $this->ownerBooking($b))->values(),
            'open_count'  => $this->openCount($config->id),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function updateBookingStatus(Request $request, Link $link, ServiceBookingRequest $booking)
    {
        $config = $this->ownedConfig($request, $link);
        if (!$config || (int) $booking->service_booking_id !== (int) $config->id) {
            return $this->notFound('Booking not found');
        }

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', ServiceBookingRequest::STATUSES),
        ]);

        if (!$booking->canTransitionTo($data['status'])) {
            return $this->fail(
                "Can't move a booking from '{$booking->status}' to '{$data['status']}'",
                422,
                'invalid_transition',
            );
        }

        $changed = $data['status'] !== $booking->status;
        $booking->update(['status' => $data['status']]);

        if ($changed) {
            $this->requests->notifyStatusChange($booking->fresh(['items', 'serviceBooking', 'link']));
        }

        return $this->ok(['booking' => $this->ownerBooking($booking->fresh('items'))]);
    }

    // ── Resolution + gating ──────────────────────────────────────

    /** @return array{0:?Link,1:?ServiceBooking} */
    protected function resolvePublic(Request $request, string $alias): array
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || $link->type !== Link::TYPE_SERVICE_BOOKING || !$link->is_active || !$link->isAccessible()) {
            return [null, null];
        }

        return [$link, $link->serviceBooking()->first()];
    }

    /** Resolve an owned service-booking config, creating it on first edit. */
    protected function ownedConfig(Request $request, Link $link): ?ServiceBooking
    {
        if ($link->type !== Link::TYPE_SERVICE_BOOKING) {
            return null;
        }
        if ((int) $link->user_id !== (int) $request->user()->id) {
            return null;
        }

        return $link->serviceBooking()->first()
            ?? ServiceBooking::create([
                'link_id'             => $link->id,
                'user_id'             => $link->user_id,
                'mode'                => ServiceBooking::MODE_BOOKING,
                'currency'            => 'USD',
                'slot_length_minutes' => 30,
                'lead_time_minutes'   => 120,
                'max_days_ahead'      => 30,
                'timezone'            => \App\Support\PlatformTimezone::platformDefault(),
            ]);
    }

    /**
     * Visibility/access gating, mirroring RestaurantController so private,
     * registered-only, follower-only and subscriber-only pages are enforced
     * on the public API. Returns null when allowed.
     */
    protected function checkVisibility(Link $link, $viewer): ?array
    {
        $vis   = $link->visibility ?? 'public';
        $owner = $link->user;

        if ($vis === 'public') return null;
        if ($viewer && $owner && (int) $viewer->id === (int) $owner->id) return null;

        if (!$viewer) {
            return ['status' => 401, 'code' => 'auth_required', 'message' => 'Sign in required to view this page'];
        }
        if ($vis === 'registered') return null;

        if ($vis === 'followers') {
            $follows = Follow::where('follower_id', $viewer->id)->where('creator_id', $owner->id)->exists();
            if ($follows) return null;
            return ['status' => 403, 'code' => 'follow_required', 'message' => 'Follow this creator to view'];
        }

        if ($vis === 'subscribers') {
            $isSub = Subscriber::where('user_id', $owner->id)
                ->where('email', $viewer->email)
                ->where('status', 'active')
                ->exists();
            if ($isSub) return null;
            return ['status' => 403, 'code' => 'subscribe_required', 'message' => 'Subscribe to this creator to view'];
        }

        return ['status' => 403, 'code' => 'forbidden', 'message' => 'Not allowed'];
    }

    /** Validate a {services:[{service_id,quantity}]} cart payload. */
    protected function validateCart(Request $request): array
    {
        return $request->validate([
            'services'              => 'required|array|min:1',
            'services.*.service_id' => 'required|integer',
            'services.*.quantity'   => 'nullable|integer|min:1|max:99',
        ]);
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

    // ── Serializers ──────────────────────────────────────────────

    protected function publicService(ServiceBookingService $s): array
    {
        return [
            'id'               => $s->id,
            'name'             => $s->name,
            'description'      => $s->description,
            'price'            => $s->price,
            'duration_minutes' => (int) $s->duration_minutes,
            'photo_url'        => $s->photo_url,
            'is_unavailable'   => (bool) $s->is_unavailable,
        ];
    }

    /** Full owner-facing config (includes inactive rows the builder edits). */
    protected function ownerConfigPayload(ServiceBooking $config, Link $link): array
    {
        $config->load(['categories', 'services', 'availabilityRules', 'blockedDates']);
        $byCat = $config->services->groupBy('category_id');

        return [
            'mode'                => $config->mode,
            'currency'            => $config->currency,
            'accent_color'        => $config->accent_color,
            'booking_enabled'     => $config->isBookingMode(),
            'slot_length_minutes' => (int) $config->slot_length_minutes,
            'lead_time_minutes'   => (int) $config->lead_time_minutes,
            'max_days_ahead'      => (int) $config->max_days_ahead,
            'timezone'            => $config->effectiveTimezone(),
            'public_url'          => url('/' . $link->alias),
            'whatsapp_number'     => $config->settings['whatsapp_number'] ?? '',
            'tax'                 => $this->taxPayload($config),
            'categories' => $config->categories->sortBy('sort_order')->map(fn ($c) => $this->ownerCategory(
                $c,
                ($byCat->get($c->id) ?? collect())->sortBy('sort_order'),
            ))->values(),
            'uncategorized' => ($byCat->get(null) ?? collect())->sortBy('sort_order')
                ->map(fn ($s) => $this->ownerService($s))->values(),
            'availability_rules' => $config->availabilityRules->sortBy([['day_of_week', 'asc'], ['start_time', 'asc']])
                ->map(fn ($r) => $this->ownerRule($r))->values(),
            'blocked_dates' => $config->blockedDates->sortBy('date')
                ->map(fn ($d) => $this->ownerBlockedDate($d))->values(),
        ];
    }

    protected function ownerCategory(ServiceBookingCategory $category, $services): array
    {
        return [
            'id'          => $category->id,
            'name'        => $category->name,
            'description' => $category->description,
            'is_active'   => (bool) ($category->is_active ?? true),
            'services'    => collect($services)->map(fn ($s) => $this->ownerService($s))->values(),
        ];
    }

    protected function ownerService(ServiceBookingService $s): array
    {
        return [
            'id'               => $s->id,
            'category_id'      => $s->category_id,
            'name'             => $s->name,
            'description'      => $s->description,
            'price'            => $s->price,
            'currency'         => $s->currency,
            'duration_minutes' => (int) $s->duration_minutes,
            'photo_url'        => $s->photo_url,
            'is_unavailable'   => (bool) $s->is_unavailable,
            'is_active'        => (bool) ($s->is_active ?? true),
        ];
    }

    protected function ownerRule(ServiceBookingAvailabilityRule $rule): array
    {
        return [
            'id'          => $rule->id,
            'day_of_week' => (int) $rule->day_of_week,
            'start_time'  => substr((string) $rule->start_time, 0, 5),
            'end_time'    => substr((string) $rule->end_time, 0, 5),
            'is_active'   => (bool) ($rule->is_active ?? true),
        ];
    }

    protected function ownerBlockedDate(ServiceBookingBlockedDate $d): array
    {
        return [
            'id'     => $d->id,
            'date'   => \Carbon\Carbon::parse($d->date)->format('Y-m-d'),
            'reason' => $d->reason,
        ];
    }

    protected function ownerBooking(ServiceBookingRequest $b): array
    {
        return [
            'id'               => $b->id,
            'public_token'     => $b->public_token,
            'status'           => $b->status,
            'status_label'     => $b->status_label,
            'customer_name'    => $b->customer_name,
            'customer_email'   => $b->customer_email,
            'customer_phone'   => $b->customer_phone,
            'customer_note'    => $b->customer_note,
            'slot_start'       => optional($b->slot_start)->toIso8601String(),
            'slot_end'         => optional($b->slot_end)->toIso8601String(),
            'duration_minutes' => $b->duration_minutes,
            'subtotal'         => $b->subtotal,
            'tax_inclusive'    => (bool) $b->tax_inclusive,
            'tax_rate'         => $b->tax_rate,
            'tax_amount'       => $b->tax_amount,
            'total'            => $b->total,
            'currency'         => $b->currency,
            'is_estimate'      => true,
            'created_at'       => optional($b->created_at)->toIso8601String(),
            'updated_at'       => optional($b->updated_at)->toIso8601String(),
            'items'            => $b->relationLoaded('items')
                ? $b->items->map(fn ($i) => [
                    'id'         => $i->id,
                    'name'       => $i->name,
                    'quantity'   => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'line_total' => $i->line_total,
                ])->values()
                : [],
        ];
    }

    protected function taxPayload(ServiceBooking $config): array
    {
        return [
            'enabled'   => $config->taxEnabled(),
            'rate'      => $config->taxRate(),
            'inclusive' => $config->taxInclusive(),
            'label'     => $config->taxLabel(),
        ];
    }

    protected function openCount(int $configId): int
    {
        return ServiceBookingRequest::where('service_booking_id', $configId)
            ->whereIn('status', ServiceBookingRequest::OPEN_STATUSES)
            ->count();
    }
}
