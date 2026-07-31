<?php

namespace Tests\Feature;

use App\Modules\Common\Services\ServiceBookingRequestService;
use App\Modules\Common\Services\SlotAvailabilityService;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingAvailabilityRule;
use App\Modules\User\Models\ServiceBookingBlockedDate;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\ServiceBookingService;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage for the Service Booking flow (Task #3085) before launch.
 *
 * The highest-risk area is slot availability: a regression there would let a
 * visitor book an unavailable time or hide a genuinely-free slot. These tests
 * freeze time (Carbon::setTestNow) to a known Wednesday so the weekly schedule,
 * lead time, booking window, blocked dates and double-booking guard are all
 * deterministic.
 *
 * Three layers are covered:
 *   - SlotAvailabilityService directly: freeSlots() generation + slotIsAvailable()
 *     re-validation across lead time, max-days window, blocked dates, slot-grid
 *     alignment, duration-fit and overlapping (slot-holding) requests.
 *   - ServiceBookingRequestService::place(): server-side slot re-check on submit,
 *     line-item snapshotting, estimated-bill math and the owner notification.
 *   - the public API (slots / quote / book + guest status) and the owner status
 *     workflow on /api/v1, mirroring the web ServiceBookingController.
 *
 * Sanctum note: Sanctum::actingAs breaks the TouchSessionToken middleware, so
 * owner calls use a real Bearer token (see memory `sanctum-api-tests`).
 */
class ServiceBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    /** A Wednesday 08:00 — before the 09:00–17:00 schedule used below. */
    private const NOW = '2026-07-08 08:00:00';
    private const TODAY = '2026-07-08';      // Wednesday
    private const NEXT_WED = '2026-07-15';   // following Wednesday

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::NOW);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Fixtures ──────────────────────────────────────────────────────

    private function makeUser(): User
    {
        return User::factory()->create([
            'name' => 'Owner',
            'role' => 'user',
        ]);
    }

    /**
     * Build a booking-mode page with a Wed 09:00–17:00 schedule and one
     * 30-minute service. Overrides let individual tests tweak scheduling.
     *
     * @return array{0:Link,1:ServiceBooking,2:ServiceBookingService}
     */
    private function makePage(User $owner, array $config = [], array $tax = []): array
    {
        $link = Link::create([
            'user_id'   => $owner->id,
            'type'      => Link::TYPE_SERVICE_BOOKING,
            'alias'     => Link::generateAlias(),
            'title'     => 'Studio Sessions',
            'is_active' => true,
        ]);

        $settings = [];
        if ($tax !== []) {
            $settings['tax'] = $tax;
        }

        $booking = ServiceBooking::create(array_merge([
            'link_id'             => $link->id,
            'user_id'             => $owner->id,
            'mode'                => ServiceBooking::MODE_BOOKING,
            'currency'            => 'USD',
            'slot_length_minutes' => 30,
            'lead_time_minutes'   => 0,
            'max_days_ahead'      => 30,
            'timezone'            => 'UTC',
            'settings'            => $settings,
        ], $config));

        $service = $this->addService($booking, 50.0, 30);

        return [$link, $booking, $service];
    }

    private function addRule(ServiceBooking $config, int $dow = 3, string $start = '09:00', string $end = '17:00'): ServiceBookingAvailabilityRule
    {
        return ServiceBookingAvailabilityRule::create([
            'service_booking_id' => $config->id,
            'day_of_week'        => $dow,
            'start_time'         => $start,
            'end_time'           => $end,
            'is_active'          => true,
        ]);
    }

    private function addService(ServiceBooking $config, float $price = 50.0, int $duration = 30, array $attrs = []): ServiceBookingService
    {
        return ServiceBookingService::create(array_merge([
            'service_booking_id' => $config->id,
            'name'               => 'Haircut',
            'price'              => $price,
            'duration_minutes'   => $duration,
            'is_active'          => true,
            'is_unavailable'     => false,
        ], $attrs));
    }

    private function holdSlot(Link $link, ServiceBooking $config, string $start, int $minutes = 30, string $status = ServiceBookingRequest::STATUS_PENDING): ServiceBookingRequest
    {
        $slotStart = Carbon::parse($start);

        return ServiceBookingRequest::create([
            'service_booking_id' => $config->id,
            'link_id'            => $link->id,
            'status'             => $status,
            'customer_name'      => 'Existing',
            'slot_start'         => $slotStart,
            'slot_end'           => $slotStart->copy()->addMinutes($minutes),
            'duration_minutes'   => $minutes,
            'subtotal'           => 0,
            'total'              => 0,
            'currency'           => 'USD',
        ]);
    }

    private function slots(): SlotAvailabilityService
    {
        return app(SlotAvailabilityService::class);
    }

    /** Pull the generated day entry for a given Y-m-d, or null. */
    private function dayFor(array $days, string $date): ?array
    {
        foreach ($days as $day) {
            if ($day['date'] === $date) {
                return $day;
            }
        }

        return null;
    }

    private function slotStarts(array $day): array
    {
        return array_map(fn ($s) => Carbon::parse($s['start'])->format('H:i'), $day['slots']);
    }

    private function token(User $user): string
    {
        return $user->createToken('test', ['*'])->plainTextToken;
    }

    private function auth(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->token($user), 'Accept' => 'application/json'];
    }

    // ── SlotAvailabilityService::freeSlots ───────────────────────────

    public function test_free_slots_are_generated_on_the_grid_and_fit_the_duration(): void
    {
        $owner = $this->makeUser();
        [, $config] = $this->makePage($owner);
        $this->addRule($config);

        $days = $this->slots()->freeSlots($config, 30);

        $today = $this->dayFor($days, self::TODAY);
        $this->assertNotNull($today, 'today should have slots');

        // 09:00 → 16:30 on a 30-min grid where [start, start+30) fits before 17:00.
        $starts = $this->slotStarts($today);
        $this->assertSame('09:00', $starts[0]);
        $this->assertSame('16:30', end($starts));
        $this->assertCount(16, $starts);
        $this->assertNotContains('16:45', $starts);
    }

    public function test_free_slots_respect_lead_time(): void
    {
        $owner = $this->makeUser();
        // now is 08:00, a 3h lead pushes the earliest bookable to 11:00.
        [, $config] = $this->makePage($owner, ['lead_time_minutes' => 180]);
        $this->addRule($config);

        $today = $this->dayFor($this->slots()->freeSlots($config, 30), self::TODAY);

        $starts = $this->slotStarts($today);
        $this->assertSame('11:00', $starts[0]);
        $this->assertNotContains('10:30', $starts);
    }

    public function test_free_slots_respect_max_days_ahead(): void
    {
        $owner = $this->makeUser();
        // Only today (+1 day window) is reachable; next Wednesday is out of range.
        [, $config] = $this->makePage($owner, ['max_days_ahead' => 1]);
        $this->addRule($config);

        $days = $this->slots()->freeSlots($config, 30);

        $this->assertCount(1, $days);
        $this->assertSame(self::TODAY, $days[0]['date']);
        $this->assertNull($this->dayFor($days, self::NEXT_WED));
    }

    public function test_free_slots_skip_blocked_dates(): void
    {
        $owner = $this->makeUser();
        [, $config] = $this->makePage($owner);
        $this->addRule($config);
        ServiceBookingBlockedDate::create([
            'service_booking_id' => $config->id,
            'date'               => self::TODAY,
            'reason'             => 'Closed',
        ]);

        $days = $this->slots()->freeSlots($config, 30);

        $this->assertNull($this->dayFor($days, self::TODAY), 'blocked day must not appear');
        $this->assertNotNull($this->dayFor($days, self::NEXT_WED));
    }

    public function test_free_slots_exclude_overlapping_held_requests(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);
        // A pending request holds the 10:00 slot.
        $this->holdSlot($link, $config, self::TODAY . ' 10:00:00');

        $today = $this->dayFor($this->slots()->freeSlots($config, 30), self::TODAY);

        $starts = $this->slotStarts($today);
        $this->assertContains('09:30', $starts);
        $this->assertNotContains('10:00', $starts, 'a held slot must disappear from availability');
        $this->assertContains('10:30', $starts);
    }

    public function test_cancelled_request_does_not_hold_its_slot(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);
        $this->holdSlot($link, $config, self::TODAY . ' 10:00:00', 30, ServiceBookingRequest::STATUS_CANCELLED);

        $today = $this->dayFor($this->slots()->freeSlots($config, 30), self::TODAY);

        // Cancelled / declined statuses don't block — the slot stays bookable.
        $this->assertContains('10:00', $this->slotStarts($today));
    }

    public function test_free_slots_empty_without_availability_rules(): void
    {
        $owner = $this->makeUser();
        [, $config] = $this->makePage($owner); // no addRule()

        $this->assertSame([], $this->slots()->freeSlots($config, 30));
    }

    // ── SlotAvailabilityService::slotIsAvailable ─────────────────────

    public function test_slot_is_available_for_a_grid_aligned_in_window_slot(): void
    {
        $owner = $this->makeUser();
        [, $config] = $this->makePage($owner);
        $this->addRule($config);

        $this->assertTrue(
            $this->slots()->slotIsAvailable($config, 30, Carbon::parse(self::TODAY . ' 10:00:00')),
        );
    }

    public function test_slot_before_lead_time_is_unavailable(): void
    {
        $owner = $this->makeUser();
        [, $config] = $this->makePage($owner, ['lead_time_minutes' => 180]); // earliest 11:00
        $this->addRule($config);

        $this->assertFalse(
            $this->slots()->slotIsAvailable($config, 30, Carbon::parse(self::TODAY . ' 09:30:00')),
        );
    }

    public function test_slot_beyond_max_days_is_unavailable(): void
    {
        $owner = $this->makeUser();
        [, $config] = $this->makePage($owner, ['max_days_ahead' => 1]);
        $this->addRule($config);

        $this->assertFalse(
            $this->slots()->slotIsAvailable($config, 30, Carbon::parse(self::NEXT_WED . ' 10:00:00')),
        );
    }

    public function test_slot_on_a_blocked_date_is_unavailable(): void
    {
        $owner = $this->makeUser();
        [, $config] = $this->makePage($owner);
        $this->addRule($config);
        ServiceBookingBlockedDate::create([
            'service_booking_id' => $config->id,
            'date'               => self::TODAY,
        ]);

        $this->assertFalse(
            $this->slots()->slotIsAvailable($config, 30, Carbon::parse(self::TODAY . ' 10:00:00')),
        );
    }

    public function test_slot_not_aligned_to_the_grid_is_unavailable(): void
    {
        $owner = $this->makeUser();
        [, $config] = $this->makePage($owner);
        $this->addRule($config);

        // 09:15 is inside the window but off the 30-min grid from 09:00.
        $this->assertFalse(
            $this->slots()->slotIsAvailable($config, 30, Carbon::parse(self::TODAY . ' 09:15:00')),
        );
    }

    public function test_slot_whose_duration_overruns_the_window_is_unavailable(): void
    {
        $owner = $this->makeUser();
        [, $config] = $this->makePage($owner);
        $this->addRule($config);

        // 16:00 + 120 min = 18:00, past the 17:00 window end.
        $this->assertFalse(
            $this->slots()->slotIsAvailable($config, 120, Carbon::parse(self::TODAY . ' 16:00:00')),
        );
    }

    public function test_slot_overlapping_a_held_request_is_unavailable(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);
        $this->holdSlot($link, $config, self::TODAY . ' 10:00:00');

        $this->assertFalse(
            $this->slots()->slotIsAvailable($config, 30, Carbon::parse(self::TODAY . ' 10:00:00')),
        );
    }

    public function test_ignore_request_id_frees_the_slot_for_reschedule(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);
        $held = $this->holdSlot($link, $config, self::TODAY . ' 10:00:00');

        // Excluding the request itself (owner reschedule) makes the slot free again.
        $this->assertFalse(
            $this->slots()->slotIsAvailable($config, 30, Carbon::parse(self::TODAY . ' 10:00:00')),
        );
        $this->assertTrue(
            $this->slots()->slotIsAvailable($config, 30, Carbon::parse(self::TODAY . ' 10:00:00'), $held->id),
        );
    }

    // ── ServiceBookingRequestService::place ──────────────────────────

    private function placer(): ServiceBookingRequestService
    {
        return app(ServiceBookingRequestService::class);
    }

    public function test_place_creates_request_snapshots_items_and_notifies_owner(): void
    {
        $owner = $this->makeUser();
        [$link, $config, $service] = $this->makePage($owner, [], ['enabled' => true, 'rate' => 10, 'inclusive' => false]);
        $this->addRule($config);

        $result = $this->placer()->place($link, $config, [
            'customer_name'  => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'slot_start'     => self::TODAY . ' 10:00:00',
            'services'       => [['service_id' => $service->id, 'quantity' => 2]],
        ]);

        // place() returns an array since the paid-bookings refactor; a page
        // whose services have payment_mode=none stays a free booking.
        $this->assertFalse($result['requires_payment']);
        $booking = $result['request'];

        $this->assertSame(ServiceBookingRequest::STATUS_PENDING, $booking->status);
        $this->assertSame('Ada Lovelace', $booking->customer_name);
        // subtotal 50*2 = 100, +10% tax = 110.
        $this->assertSame('100.00', (string) $booking->subtotal);
        $this->assertSame('10.00', (string) $booking->tax_amount);
        $this->assertSame('110.00', (string) $booking->total);
        // Combined duration drives the held slot: 30 * 2 = 60 minutes.
        $this->assertSame(60, $booking->duration_minutes);
        $this->assertTrue(Carbon::parse($booking->slot_end)->equalTo(Carbon::parse(self::TODAY . ' 11:00:00')));

        // Line item is snapshotted off the live service.
        $item = $booking->items()->first();
        $this->assertSame('Haircut', $item->name);
        $this->assertSame('50.00', (string) $item->unit_price);
        $this->assertSame(30, (int) $item->duration_minutes);
        $this->assertSame(2, (int) $item->quantity);
        $this->assertSame('100.00', (string) $item->line_total);

        // Owner gets an in-app new-request notification.
        $this->assertTrue(
            UserNotification::where('user_id', $owner->id)
                ->where('type', 'service_booking.new_request')
                ->exists(),
        );
    }

    public function test_place_rejects_a_slot_that_is_not_available(): void
    {
        $owner = $this->makeUser();
        [$link, $config, $service] = $this->makePage($owner);
        $this->addRule($config);

        $this->expectException(\InvalidArgumentException::class);
        $this->placer()->place($link, $config, [
            'customer_name' => 'Grace',
            'slot_start'    => self::TODAY . ' 03:00:00', // before the 09:00 window
            'services'      => [['service_id' => $service->id]],
        ]);
    }

    public function test_place_rejects_an_already_held_slot(): void
    {
        $owner = $this->makeUser();
        [$link, $config, $service] = $this->makePage($owner);
        $this->addRule($config);
        $this->holdSlot($link, $config, self::TODAY . ' 10:00:00');

        $this->expectException(\InvalidArgumentException::class);
        $this->placer()->place($link, $config, [
            'customer_name' => 'Grace',
            'slot_start'    => self::TODAY . ' 10:00:00',
            'services'      => [['service_id' => $service->id]],
        ]);
    }

    public function test_place_rejects_an_unavailable_service(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);
        $soldOut = $this->addService($config, 50.0, 30, ['name' => 'Sold Out', 'is_unavailable' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->placer()->place($link, $config, [
            'customer_name' => 'Grace',
            'slot_start'    => self::TODAY . ' 10:00:00',
            'services'      => [['service_id' => $soldOut->id]],
        ]);
    }

    // ── Public API parity: slots / quote / book / status ─────────────

    public function test_api_slots_returns_free_slots(): void
    {
        $owner = $this->makeUser();
        [$link, $config, $service] = $this->makePage($owner);
        $this->addRule($config);

        $res = $this->postJson("/api/v1/service-booking/{$link->alias}/slots", [
            'services' => [['service_id' => $service->id]],
        ], ['Accept' => 'application/json']);

        $res->assertOk();
        $this->assertSame(30, $res->json('data.duration_minutes'));
        $days = $res->json('data.days');
        $this->assertNotEmpty($days);
        $this->assertSame(self::TODAY, $days[0]['date']);
    }

    public function test_api_quote_returns_estimated_bill(): void
    {
        $owner = $this->makeUser();
        [$link, $config, $service] = $this->makePage($owner, [], ['enabled' => true, 'rate' => 10, 'inclusive' => false]);
        $this->addRule($config);

        $res = $this->postJson("/api/v1/service-booking/{$link->alias}/quote", [
            'services' => [['service_id' => $service->id, 'quantity' => 2]],
        ], ['Accept' => 'application/json']);

        $res->assertOk();
        $bill = $res->json('data.bill');
        $this->assertEqualsWithDelta(100.0, $bill['subtotal'], 0.001);
        $this->assertEqualsWithDelta(10.0, $bill['tax_amount'], 0.001);
        $this->assertEqualsWithDelta(110.0, $bill['total'], 0.001);
        $this->assertTrue($bill['is_estimate']);
    }

    public function test_api_book_creates_a_request_for_a_free_slot(): void
    {
        $owner = $this->makeUser();
        [$link, $config, $service] = $this->makePage($owner);
        $this->addRule($config);

        // Take a real slot from the live availability endpoint.
        $slotsRes = $this->postJson("/api/v1/service-booking/{$link->alias}/slots", [
            'services' => [['service_id' => $service->id]],
        ], ['Accept' => 'application/json']);
        $slotStart = $slotsRes->json('data.days.0.slots.0.start');
        $this->assertNotNull($slotStart);

        $res = $this->postJson("/api/v1/service-booking/{$link->alias}/book", [
            'customer_name' => 'Ada',
            'slot_start'    => $slotStart,
            'services'      => [['service_id' => $service->id]],
        ], ['Accept' => 'application/json']);

        $res->assertCreated();
        $token = $res->json('data.booking.public_token');
        $this->assertNotNull($token);
        $this->assertSame('pending', $res->json('data.booking.status'));

        $this->assertDatabaseHas('service_booking_requests', [
            'public_token'       => $token,
            'service_booking_id' => $config->id,
            'customer_name'      => 'Ada',
            'status'             => 'pending',
        ]);
    }

    public function test_api_book_rejects_an_already_taken_slot(): void
    {
        $owner = $this->makeUser();
        [$link, $config, $service] = $this->makePage($owner);
        $this->addRule($config);
        $this->holdSlot($link, $config, self::TODAY . ' 10:00:00');

        $res = $this->postJson("/api/v1/service-booking/{$link->alias}/book", [
            'customer_name' => 'Ada',
            'slot_start'    => self::TODAY . ' 10:00:00',
            'services'      => [['service_id' => $service->id]],
        ], ['Accept' => 'application/json']);

        $res->assertStatus(422);
        $this->assertSame('invalid_request', $res->json('error.code'));
    }

    public function test_api_guest_can_poll_booking_status_by_token(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);
        $held = $this->holdSlot($link, $config, self::TODAY . ' 10:00:00');

        $res = $this->getJson("/api/v1/service-booking/bookings/{$held->public_token}/status", [
            'Accept' => 'application/json',
        ]);

        $res->assertOk();
        $this->assertSame($held->public_token, $res->json('data.booking.public_token'));
        $this->assertSame('pending', $res->json('data.booking.status'));
    }

    // ── Owner API: status workflow ───────────────────────────────────

    public function test_owner_can_advance_a_booking_through_a_valid_transition(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);
        $held = $this->holdSlot($link, $config, self::TODAY . ' 10:00:00');

        $res = $this->postJson(
            "/api/v1/service-booking/links/{$link->id}/bookings/{$held->id}/status",
            ['status' => ServiceBookingRequest::STATUS_CONFIRMED],
            $this->auth($owner),
        );

        $res->assertOk();
        $this->assertSame('confirmed', $res->json('data.booking.status'));
        $this->assertDatabaseHas('service_booking_requests', [
            'id'     => $held->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_owner_cannot_make_an_invalid_status_jump(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);
        $held = $this->holdSlot($link, $config, self::TODAY . ' 10:00:00');

        // pending → completed is not a permitted transition (must confirm first).
        $res = $this->postJson(
            "/api/v1/service-booking/links/{$link->id}/bookings/{$held->id}/status",
            ['status' => ServiceBookingRequest::STATUS_COMPLETED],
            $this->auth($owner),
        );

        $res->assertStatus(422);
        $this->assertSame('invalid_transition', $res->json('error.code'));
        $this->assertDatabaseHas('service_booking_requests', [
            'id'     => $held->id,
            'status' => 'pending',
        ]);
    }

    public function test_owner_status_update_is_scoped_to_the_owner(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);
        $held = $this->holdSlot($link, $config, self::TODAY . ' 10:00:00');

        $res = $this->postJson(
            "/api/v1/service-booking/links/{$link->id}/bookings/{$held->id}/status",
            ['status' => ServiceBookingRequest::STATUS_CONFIRMED],
            $this->auth($stranger),
        );

        $res->assertNotFound();
        $this->assertDatabaseHas('service_booking_requests', [
            'id'     => $held->id,
            'status' => 'pending',
        ]);
    }

    // ── Payment mode on services (Task #5284) ────────────────────────────────

    public function test_service_stores_with_payment_mode_none_by_default(): void
    {
        $owner = $this->makeUser();
        [$link] = $this->makePage($owner);

        $res = $this->postJson(
            "/api/v1/service-booking/links/{$link->id}/config/services",
            [
                'name'             => 'Trim',
                'price'            => 20,
                'duration_minutes' => 30,
            ],
            $this->auth($owner),
        );

        $res->assertCreated();
        $this->assertSame('none', $res->json('data.service.payment_mode'));
    }

    public function test_service_stores_deposit_mode_with_percent(): void
    {
        $owner = $this->makeUser();
        [$link] = $this->makePage($owner);

        $res = $this->postJson(
            "/api/v1/service-booking/links/{$link->id}/config/services",
            [
                'name'             => 'Cut',
                'price'            => 100,
                'duration_minutes' => 60,
                'payment_mode'     => 'deposit',
                'deposit_type'     => 'percent',
                'deposit_value'    => 30,
            ],
            $this->auth($owner),
        );

        $res->assertCreated();
        $this->assertSame('deposit', $res->json('data.service.payment_mode'));
        $this->assertSame('percent', $res->json('data.service.deposit_type'));
        $this->assertEquals(30, $res->json('data.service.deposit_value'));
    }

    public function test_service_update_changes_payment_mode_to_full(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $service = $this->addService($config, 80, 45);

        $res = $this->putJson(
            "/api/v1/service-booking/links/{$link->id}/config/services/{$service->id}",
            ['payment_mode' => 'full'],
            $this->auth($owner),
        );

        $res->assertOk();
        $this->assertSame('full', $res->json('data.service.payment_mode'));
        $this->assertDatabaseHas('service_booking_services', [
            'id'           => $service->id,
            'payment_mode' => 'full',
        ]);
    }

    // ── Payment mode persists into booking request ────────────────────────────

    public function test_free_booking_request_places_with_status_pending(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);

        // Service defaults to payment_mode=none — no payment required.
        $res = $this->postJson("/sb/{$link->alias}/book", [
            'customer_name' => 'Visitor',
            'customer_email' => 'visitor@example.com',
            'slot_start'    => self::TODAY . 'T10:00:00Z',
            'services'      => [['service_id' => $config->services()->first()->id]],
        ]);

        $res->assertCreated();
        $this->assertSame('pending', $res->json('data.booking.status'));
        $this->assertSame('none', $res->json('data.booking.payment_status'));
        $this->assertNull($res->json('data.checkout_url'));
    }

    public function test_awaiting_payment_slot_is_blocked_while_checkout_not_expired(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);

        // Create an AWAITING_PAYMENT request with an active checkout window.
        ServiceBookingRequest::create([
            'service_booking_id'   => $config->id,
            'link_id'              => $link->id,
            'status'               => ServiceBookingRequest::STATUS_AWAITING_PAYMENT,
            'customer_name'        => 'Paying',
            'slot_start'           => Carbon::parse(self::TODAY . ' 10:00:00'),
            'slot_end'             => Carbon::parse(self::TODAY . ' 10:30:00'),
            'duration_minutes'     => 30,
            'subtotal'             => 50,
            'total'                => 50,
            'currency'             => 'USD',
            'checkout_expires_at'  => now()->addMinutes(25), // still valid
        ]);

        $slots = $this->slots()->freeSlots($config, 30);
        $today = $this->dayFor($slots, self::TODAY);
        $starts = $this->slotStarts($today);

        // 10:00 should be blocked by the in-checkout hold.
        $this->assertNotContains('10:00', $starts);
    }

    public function test_awaiting_payment_slot_is_free_after_checkout_expires(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);

        // Create an AWAITING_PAYMENT request with an EXPIRED checkout window.
        ServiceBookingRequest::create([
            'service_booking_id'   => $config->id,
            'link_id'              => $link->id,
            'status'               => ServiceBookingRequest::STATUS_AWAITING_PAYMENT,
            'customer_name'        => 'Expired Checkout',
            'slot_start'           => Carbon::parse(self::TODAY . ' 11:00:00'),
            'slot_end'             => Carbon::parse(self::TODAY . ' 11:30:00'),
            'duration_minutes'     => 30,
            'subtotal'             => 50,
            'total'                => 50,
            'currency'             => 'USD',
            'checkout_expires_at'  => now()->subMinutes(5), // already expired
        ]);

        $slots = $this->slots()->freeSlots($config, 30);
        $today = $this->dayFor($slots, self::TODAY);
        $starts = $this->slotStarts($today);

        // 11:00 should be available because the checkout expired.
        $this->assertContains('11:00', $starts);
    }

    // ── Reminder lead-time settings ───────────────────────────────────────────

    public function test_saveSettings_stores_single_reminder_lead_minutes(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);

        $res = $this->postJson(
            "/api/v1/service-booking/links/{$link->id}/config/settings",
            [
                'mode'                  => 'booking',
                'currency'              => 'USD',
                'slot_length_minutes'   => 30,
                'lead_time_minutes'     => 0,
                'max_days_ahead'        => 30,
                'reminder_lead_minutes' => 1440,
            ],
            $this->auth($owner),
        );

        $res->assertOk();
        $this->assertSame(1440, $res->json('data.config.reminder_lead_minutes'));
        $config->refresh();
        $this->assertSame(1440, $config->settings['reminder_lead_minutes']);
    }

    public function test_saveSettings_stores_array_of_reminder_lead_minutes(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);

        $res = $this->postJson(
            "/api/v1/service-booking/links/{$link->id}/config/settings",
            [
                'mode'                  => 'booking',
                'currency'              => 'USD',
                'slot_length_minutes'   => 30,
                'lead_time_minutes'     => 0,
                'max_days_ahead'        => 30,
                'reminder_lead_minutes' => [1440, 60],
            ],
            $this->auth($owner),
        );

        $res->assertOk();
        $this->assertSame([1440, 60], $res->json('data.config.reminder_lead_minutes'));
    }

    public function test_saveSettings_clears_reminder_lead_minutes_when_empty(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $config->update(['settings' => array_merge($config->settings ?? [], ['reminder_lead_minutes' => 720])]);

        $res = $this->postJson(
            "/api/v1/service-booking/links/{$link->id}/config/settings",
            [
                'mode'                  => 'booking',
                'currency'              => 'USD',
                'slot_length_minutes'   => 30,
                'lead_time_minutes'     => 0,
                'max_days_ahead'        => 30,
                'reminder_lead_minutes' => '',
            ],
            $this->auth($owner),
        );

        $res->assertOk();
        $this->assertNull($res->json('data.config.reminder_lead_minutes'));
        $config->refresh();
        $this->assertArrayNotHasKey('reminder_lead_minutes', $config->settings ?? []);
    }

    // ── wasReminderSent / markReminderSent deduplication ─────────────────────

    public function test_reminder_dedup_prevents_double_send(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $req = $this->holdSlot($link, $config, self::TODAY . ' 10:00:00', 30, ServiceBookingRequest::STATUS_CONFIRMED);

        $this->assertFalse($req->wasReminderSent(1440));
        $req->markReminderSent(1440);
        $this->assertTrue($req->fresh()->wasReminderSent(1440));

        // A different lead time is not yet marked.
        $this->assertFalse($req->fresh()->wasReminderSent(60));
    }

    // ── Owner booking serializer exposes payment fields (API parity) ──────────

    public function test_owner_booking_list_exposes_payment_fields(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addRule($config);
        $held = $this->holdSlot($link, $config, self::TODAY . ' 10:00:00');

        $res = $this->getJson(
            "/api/v1/service-booking/links/{$link->id}/bookings",
            $this->auth($owner),
        );

        $res->assertOk();
        $booking = $res->json('data.bookings.0');
        $this->assertArrayHasKey('payment_mode', $booking);
        $this->assertArrayHasKey('payment_status', $booking);
        $this->assertArrayHasKey('payment_amount_cents', $booking);
        $this->assertArrayHasKey('is_refundable', $booking);
        $this->assertSame('none', $booking['payment_mode']);
        $this->assertFalse($booking['is_refundable']);
    }

    public function test_owner_service_list_exposes_payment_mode(): void
    {
        $owner = $this->makeUser();
        [$link, $config] = $this->makePage($owner);
        $this->addService($config, 60, 30, [
            'payment_mode'  => 'deposit',
            'deposit_type'  => 'fixed',
            'deposit_value' => 15,
        ]);

        $res = $this->getJson(
            "/api/v1/service-booking/links/{$link->id}/config",
            $this->auth($owner),
        );

        $res->assertOk();
        // uncategorized has 2 services (1 from makePage + 1 above)
        $services = $res->json('data.config.uncategorized');
        $depositService = collect($services)->firstWhere('payment_mode', 'deposit');
        $this->assertNotNull($depositService);
        $this->assertSame('fixed', $depositService['deposit_type']);
        $this->assertEquals(15, $depositService['deposit_value']);
    }
}
