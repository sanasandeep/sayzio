<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingAvailabilityRule;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\ServiceBookingService;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Paid Service Booking gating (Task #5345): a visitor booking a service with
 * payment_mode 'deposit' or 'full' must NOT be able to end up with a pending /
 * confirmed booking without paying. The request is created in
 * STATUS_AWAITING_PAYMENT and only checkout confirmation (checkout.return with
 * the cached one-time token) advances it to paid + confirmed.
 *
 * Covered:
 *   - book → awaiting_payment (not pending/confirmed), correct deposit /
 *     percent / full amounts, checkout_expires_at hold, no premature owner
 *     notification.
 *   - skipping payment: guest status stays awaiting_payment; the owner status
 *     API refuses awaiting_payment → confirmed (only cancel is allowed).
 *   - a forged / missing checkout token cannot confirm the booking; the real
 *     token (parsed from the returned preview checkout_url) does, exactly once.
 *   - the awaiting_payment hold blocks the slot until checkout_expires_at
 *     passes, then the slot frees up again.
 *   - no payout connection → graceful fallback to a free pending booking.
 *   - the appointment reminder command (`bookings:send-reminders`) reminds
 *     confirmed bookings once (deduped) and never reminds awaiting_payment.
 */
class ServiceBookingPaymentGatingTest extends TestCase
{
    use RefreshDatabase;

    /** A Wednesday 08:00 — before the 09:00–17:00 schedule used below. */
    private const NOW = '2026-07-08 08:00:00';
    private const TODAY = '2026-07-08'; // Wednesday

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::NOW);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Fixtures ──────────────────────────────────────────────────────

    private function makeOwner(bool $withPayout = true): User
    {
        $owner = User::factory()->create(['name' => 'Owner', 'role' => 'user']);

        if ($withPayout) {
            CreatorPaymentConnection::create([
                'user_id'         => $owner->id,
                'provider'        => 'stripe',
                'status'          => 'active',
                'payouts_enabled' => true,
                'charges_enabled' => true,
                'is_default'      => true,
            ]);
        }

        return $owner;
    }

    /** @return array{0:Link,1:ServiceBooking} */
    private function makePage(User $owner): array
    {
        $link = Link::create([
            'user_id'   => $owner->id,
            'type'      => Link::TYPE_SERVICE_BOOKING,
            'alias'     => Link::generateAlias(),
            'title'     => 'Studio Sessions',
            'is_active' => true,
        ]);

        $booking = ServiceBooking::create([
            'link_id'             => $link->id,
            'user_id'             => $owner->id,
            'mode'                => ServiceBooking::MODE_BOOKING,
            'currency'            => 'USD',
            'slot_length_minutes' => 30,
            'lead_time_minutes'   => 0,
            'max_days_ahead'      => 30,
            'timezone'            => 'UTC',
        ]);

        ServiceBookingAvailabilityRule::create([
            'service_booking_id' => $booking->id,
            'day_of_week'        => 3, // Wednesday
            'start_time'         => '09:00',
            'end_time'           => '17:00',
            'is_active'          => true,
        ]);

        return [$link, $booking];
    }

    private function addService(ServiceBooking $config, array $attrs = []): ServiceBookingService
    {
        return ServiceBookingService::create(array_merge([
            'service_booking_id' => $config->id,
            'name'               => 'Haircut',
            'price'              => 100.0,
            'duration_minutes'   => 30,
            'is_active'          => true,
            'is_unavailable'     => false,
        ], $attrs));
    }

    private function depositService(ServiceBooking $config, float $value = 25.0, string $type = 'fixed'): ServiceBookingService
    {
        return $this->addService($config, [
            'payment_mode'  => ServiceBookingService::PAYMENT_MODE_DEPOSIT,
            'deposit_type'  => $type,
            'deposit_value' => $value,
        ]);
    }

    private function book(Link $link, ServiceBookingService $service, array $overrides = [])
    {
        return $this->postJson("/api/v1/service-booking/{$link->alias}/book", array_merge([
            'customer_name'  => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'slot_start'     => self::TODAY . ' 10:00:00',
            'services'       => [['service_id' => $service->id]],
        ], $overrides), ['Accept' => 'application/json']);
    }

    private function auth(User $user): array
    {
        return [
            'Authorization' => 'Bearer ' . $user->createToken('test', ['*'])->plainTextToken,
            'Accept'        => 'application/json',
        ];
    }

    // ── Booking with a required deposit gates on payment ─────────────

    public function test_deposit_booking_is_created_awaiting_payment_not_confirmed(): void
    {
        $owner = $this->makeOwner();
        [$link, $config] = $this->makePage($owner);
        $service = $this->depositService($config, 25.0);

        $res = $this->book($link, $service);

        $res->assertCreated();
        $this->assertSame(ServiceBookingRequest::STATUS_AWAITING_PAYMENT, $res->json('data.booking.status'));

        $booking = ServiceBookingRequest::firstOrFail();
        $this->assertSame(ServiceBookingRequest::STATUS_AWAITING_PAYMENT, $booking->status);
        $this->assertSame(ServiceBookingRequest::PAYMENT_STATUS_PENDING, $booking->payment_status);
        $this->assertSame(ServiceBookingService::PAYMENT_MODE_DEPOSIT, $booking->payment_mode);
        $this->assertSame(2500, $booking->payment_amount_cents);
        $this->assertNotNull($booking->checkout_expires_at);
        $this->assertTrue($booking->checkout_expires_at->isFuture());

        // Visitor is handed a checkout URL to complete payment.
        $this->assertNotEmpty($res->json('data.checkout_url'));
        $this->assertNotEmpty($res->json('data.checkout_expires_at'));

        // Owner must NOT be notified until the payment lands.
        $this->assertFalse(
            UserNotification::where('user_id', $owner->id)
                ->where('type', 'service_booking.new_request')
                ->exists(),
        );
    }

    public function test_percent_deposit_amount_is_computed_from_the_line_total(): void
    {
        $owner = $this->makeOwner();
        [$link, $config] = $this->makePage($owner);
        // 20% of (100 × 2) = 40.00
        $service = $this->depositService($config, 20.0, 'percent');

        $this->book($link, $service, [
            'services' => [['service_id' => $service->id, 'quantity' => 2]],
        ])->assertCreated();

        $this->assertSame(4000, ServiceBookingRequest::firstOrFail()->payment_amount_cents);
    }

    public function test_full_payment_mode_charges_the_full_line_total(): void
    {
        $owner = $this->makeOwner();
        [$link, $config] = $this->makePage($owner);
        $service = $this->addService($config, [
            'payment_mode' => ServiceBookingService::PAYMENT_MODE_FULL,
        ]);

        $this->book($link, $service)->assertCreated();

        $booking = ServiceBookingRequest::firstOrFail();
        $this->assertSame(ServiceBookingService::PAYMENT_MODE_FULL, $booking->payment_mode);
        $this->assertSame(10000, $booking->payment_amount_cents);
        $this->assertSame(ServiceBookingRequest::STATUS_AWAITING_PAYMENT, $booking->status);
    }

    public function test_without_a_payout_connection_the_booking_falls_back_to_free_pending(): void
    {
        $owner = $this->makeOwner(withPayout: false);
        [$link, $config] = $this->makePage($owner);
        $service = $this->depositService($config, 25.0);

        $res = $this->book($link, $service);

        $res->assertCreated();
        $this->assertNull($res->json('data.checkout_url'));

        $booking = ServiceBookingRequest::firstOrFail();
        $this->assertSame(ServiceBookingRequest::STATUS_PENDING, $booking->status);
        $this->assertSame(ServiceBookingRequest::PAYMENT_STATUS_NONE, $booking->payment_status);
        $this->assertSame(0, $booking->payment_amount_cents);
    }

    // ── Skipping payment cannot yield a confirmed booking ────────────

    public function test_guest_status_stays_awaiting_payment_when_checkout_is_skipped(): void
    {
        $owner = $this->makeOwner();
        [$link, $config] = $this->makePage($owner);
        $token = $this->book($link, $this->depositService($config))->json('data.booking.public_token');

        $res = $this->getJson("/api/v1/service-booking/bookings/{$token}/status");

        $res->assertOk();
        $this->assertSame(ServiceBookingRequest::STATUS_AWAITING_PAYMENT, $res->json('data.booking.status'));
    }

    public function test_owner_cannot_confirm_an_awaiting_payment_booking(): void
    {
        $owner = $this->makeOwner();
        [$link, $config] = $this->makePage($owner);
        $this->book($link, $this->depositService($config))->assertCreated();
        $booking = ServiceBookingRequest::firstOrFail();

        $res = $this->postJson(
            "/api/v1/service-booking/links/{$link->id}/bookings/{$booking->id}/status",
            ['status' => ServiceBookingRequest::STATUS_CONFIRMED],
            $this->auth($owner),
        );

        $res->assertStatus(422);
        $this->assertSame('invalid_transition', $res->json('error.code'));
        $this->assertSame(ServiceBookingRequest::STATUS_AWAITING_PAYMENT, $booking->fresh()->status);
    }

    public function test_owner_can_cancel_an_awaiting_payment_booking(): void
    {
        $owner = $this->makeOwner();
        [$link, $config] = $this->makePage($owner);
        $this->book($link, $this->depositService($config))->assertCreated();
        $booking = ServiceBookingRequest::firstOrFail();

        $this->postJson(
            "/api/v1/service-booking/links/{$link->id}/bookings/{$booking->id}/status",
            ['status' => ServiceBookingRequest::STATUS_CANCELLED],
            $this->auth($owner),
        )->assertOk();

        $this->assertSame(ServiceBookingRequest::STATUS_CANCELLED, $booking->fresh()->status);
    }

    public function test_forged_checkout_token_does_not_confirm_the_booking(): void
    {
        $owner = $this->makeOwner();
        [$link, $config] = $this->makePage($owner);
        $this->book($link, $this->depositService($config))->assertCreated();
        $booking = ServiceBookingRequest::firstOrFail();

        $res = $this->get(route('checkout.return', [
            'kind'      => 'booking',
            'reference' => 'booking_' . $booking->id,
            'token'     => str_repeat('x', 32),
        ]));

        $res->assertRedirect('/');
        $booking->refresh();
        $this->assertSame(ServiceBookingRequest::STATUS_AWAITING_PAYMENT, $booking->status);
        $this->assertSame(ServiceBookingRequest::PAYMENT_STATUS_PENDING, $booking->payment_status);
    }

    public function test_completing_checkout_marks_the_booking_paid_and_confirmed(): void
    {
        $owner = $this->makeOwner();
        [$link, $config] = $this->makePage($owner);
        $res = $this->book($link, $this->depositService($config, 25.0));
        $res->assertCreated();
        $booking = ServiceBookingRequest::firstOrFail();

        // The preview checkout URL carries the one-time token; extract it the
        // same way the provider return redirect would echo it back.
        $checkoutUrl = (string) $res->json('data.checkout_url');
        parse_str((string) parse_url($checkoutUrl, PHP_URL_QUERY), $query);
        $token = $query['token'] ?? null;
        $this->assertNotEmpty($token, 'checkout_url should carry the one-time token');

        $return = $this->get(route('checkout.return', [
            'kind'      => 'booking',
            'reference' => 'booking_' . $booking->id,
            'token'     => $token,
        ]));
        $return->assertRedirect();

        $booking->refresh();
        $this->assertSame(ServiceBookingRequest::STATUS_CONFIRMED, $booking->status);
        $this->assertSame(ServiceBookingRequest::PAYMENT_STATUS_PAID, $booking->payment_status);
        $this->assertNull($booking->checkout_expires_at);

        // Owner is notified only now that payment landed.
        $this->assertTrue(
            UserNotification::where('user_id', $owner->id)
                ->where('type', 'service_booking.new_request')
                ->exists(),
        );

        // The one-time token is consumed — replay cannot double-process.
        $this->get(route('checkout.return', [
            'kind'      => 'booking',
            'reference' => 'booking_' . $booking->id,
            'token'     => $token,
        ]))->assertRedirect('/');
        $this->assertSame(ServiceBookingRequest::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    // ── Slot holding while payment is pending ────────────────────────

    public function test_awaiting_payment_booking_holds_its_slot(): void
    {
        $owner = $this->makeOwner();
        [$link, $config] = $this->makePage($owner);
        $service = $this->depositService($config);
        $this->book($link, $service)->assertCreated();

        $res = $this->book($link, $service, ['customer_name' => 'Grace Hopper']);

        $res->assertStatus(422);
        $this->assertSame('invalid_request', $res->json('error.code'));
        $this->assertSame(1, ServiceBookingRequest::count());
    }

    public function test_expired_checkout_hold_frees_the_slot(): void
    {
        $owner = $this->makeOwner();
        [$link, $config] = $this->makePage($owner);
        $service = $this->depositService($config);
        $this->book($link, $service)->assertCreated();

        // The 30-minute checkout hold lapses.
        Carbon::setTestNow(Carbon::parse(self::NOW)->addMinutes(45));

        $res = $this->book($link, $service, ['customer_name' => 'Grace Hopper']);

        $res->assertCreated();
        $this->assertSame(2, ServiceBookingRequest::count());
    }

    // ── Appointment reminders (bookings:send-reminders) ─────────────

    private function makeConfirmedBooking(Link $link, ServiceBooking $config, array $attrs = []): ServiceBookingRequest
    {
        // Default lead is 1440 min; slot 23.5h out lands inside the
        // [lead-60, lead] dispatch window.
        $slotStart = Carbon::parse(self::NOW)->addMinutes(1410);

        return ServiceBookingRequest::create(array_merge([
            'service_booking_id' => $config->id,
            'link_id'            => $link->id,
            'status'             => ServiceBookingRequest::STATUS_CONFIRMED,
            'customer_name'      => 'Ada Lovelace',
            'customer_email'     => 'ada@example.com',
            'slot_start'         => $slotStart,
            'slot_end'           => $slotStart->copy()->addMinutes(30),
            'duration_minutes'   => 30,
            'subtotal'           => 100,
            'total'              => 100,
            'currency'           => 'USD',
        ], $attrs));
    }

    public function test_reminder_command_reminds_a_confirmed_booking_once(): void
    {
        $owner = $this->makeOwner(withPayout: false);
        [$link, $config] = $this->makePage($owner);
        $booking = $this->makeConfirmedBooking($link, $config);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        $booking->refresh();
        $this->assertTrue($booking->wasReminderSent(1440));
        $this->assertCount(1, $booking->meta['reminders_sent'] ?? []);

        // A second run must not double-send (dedup via meta).
        $this->artisan('bookings:send-reminders')->assertSuccessful();
        $this->assertCount(1, $booking->fresh()->meta['reminders_sent'] ?? []);
    }

    public function test_reminder_command_skips_awaiting_payment_and_out_of_window_bookings(): void
    {
        $owner = $this->makeOwner(withPayout: false);
        [$link, $config] = $this->makePage($owner);

        $unpaid = $this->makeConfirmedBooking($link, $config, [
            'status'              => ServiceBookingRequest::STATUS_AWAITING_PAYMENT,
            'payment_status'      => ServiceBookingRequest::PAYMENT_STATUS_PENDING,
            'checkout_expires_at' => now()->addMinutes(30),
        ]);
        // A confirmed booking far outside the reminder window (only 2h away).
        $early = $this->makeConfirmedBooking($link, $config, [
            'slot_start' => Carbon::parse(self::NOW)->addMinutes(120),
            'slot_end'   => Carbon::parse(self::NOW)->addMinutes(150),
        ]);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        $this->assertFalse($unpaid->fresh()->wasReminderSent(1440), 'unpaid bookings must never get reminders');
        $this->assertFalse($early->fresh()->wasReminderSent(1440), 'out-of-window bookings must wait');
    }

    public function test_reminder_respects_custom_lead_minutes_setting(): void
    {
        $owner = $this->makeOwner(withPayout: false);
        [$link, $config] = $this->makePage($owner);
        $config->update(['settings' => ['reminder_lead_minutes' => 120]]);

        // Slot 90 minutes out — inside the [60, 120] window for a 2h lead.
        $booking = $this->makeConfirmedBooking($link, $config, [
            'slot_start' => Carbon::parse(self::NOW)->addMinutes(90),
            'slot_end'   => Carbon::parse(self::NOW)->addMinutes(120),
        ]);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        $booking->refresh();
        $this->assertTrue($booking->wasReminderSent(120));
        $this->assertFalse($booking->wasReminderSent(1440));
    }
}
