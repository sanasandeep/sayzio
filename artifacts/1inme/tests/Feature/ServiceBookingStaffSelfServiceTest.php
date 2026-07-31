<?php

namespace Tests\Feature;

use App\Modules\Common\Services\SlotAvailabilityService;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingAvailabilityRule;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\ServiceBookingService;
use App\Modules\User\Models\ServiceBookingStaff;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the Task #6325 Service Booking extensions: staff / team
 * members (per-staff availability + "any available" auto-assign), buffers,
 * group capacity per slot, and visitor self-service reschedule / cancel with
 * the owner-set cutoff — exercised both against SlotAvailabilityService and
 * the public /api/v1 surface.
 *
 * Time is frozen to a Wednesday 08:00 like ServiceBookingFlowTest so the
 * Wed 09:00–17:00 schedule below is deterministic.
 */
class ServiceBookingStaffSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-07-08 08:00:00';   // Wednesday
    private const TODAY = '2026-07-08';

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

    private function makePage(array $config = []): array
    {
        $owner = User::factory()->create(['name' => 'Owner', 'role' => 'user']);

        $link = Link::create([
            'user_id'   => $owner->id,
            'type'      => Link::TYPE_SERVICE_BOOKING,
            'alias'     => Link::generateAlias(),
            'title'     => 'Studio Sessions',
            'is_active' => true,
        ]);

        $booking = ServiceBooking::create(array_merge([
            'link_id'             => $link->id,
            'user_id'             => $owner->id,
            'mode'                => ServiceBooking::MODE_BOOKING,
            'currency'            => 'USD',
            'slot_length_minutes' => 30,
            'lead_time_minutes'   => 0,
            'max_days_ahead'      => 7,
            'timezone'            => 'UTC',
        ], $config));

        ServiceBookingAvailabilityRule::create([
            'service_booking_id' => $booking->id,
            'day_of_week'        => 3,
            'start_time'         => '09:00',
            'end_time'           => '17:00',
            'is_active'          => true,
        ]);

        $service = ServiceBookingService::create([
            'service_booking_id' => $booking->id,
            'name'               => 'Haircut',
            'price'              => 50,
            'duration_minutes'   => 30,
            'is_active'          => true,
            'is_unavailable'     => false,
        ]);

        return [$owner, $link, $booking, $service];
    }

    private function addStaff(ServiceBooking $config, string $name = 'Priya', array $attrs = []): ServiceBookingStaff
    {
        return ServiceBookingStaff::create(array_merge([
            'service_booking_id' => $config->id,
            'name'               => $name,
            'is_active'          => true,
            'sort_order'         => 0,
        ], $attrs));
    }

    private function hold(Link $link, ServiceBooking $config, string $start, array $attrs = []): ServiceBookingRequest
    {
        $slotStart = Carbon::parse($start);

        return ServiceBookingRequest::create(array_merge([
            'service_booking_id' => $config->id,
            'link_id'            => $link->id,
            'status'             => ServiceBookingRequest::STATUS_PENDING,
            'customer_name'      => 'Existing',
            'slot_start'         => $slotStart,
            'slot_end'           => $slotStart->copy()->addMinutes(30),
            'duration_minutes'   => 30,
            'subtotal'           => 0,
            'total'              => 0,
            'currency'           => 'USD',
        ], $attrs));
    }

    private function slots(): SlotAvailabilityService
    {
        return app(SlotAvailabilityService::class);
    }

    private function starts(array $days, string $date): array
    {
        foreach ($days as $day) {
            if ($day['date'] === $date) {
                return array_map(fn ($s) => Carbon::parse($s['start'])->format('H:i'), $day['slots']);
            }
        }

        return [];
    }

    private function remainingAt(array $days, string $date, string $hm): ?int
    {
        foreach ($days as $day) {
            if ($day['date'] !== $date) {
                continue;
            }
            foreach ($day['slots'] as $s) {
                if (Carbon::parse($s['start'])->format('H:i') === $hm) {
                    return $s['remaining'];
                }
            }
        }

        return null;
    }

    // ── Staff availability ────────────────────────────────────────────

    public function test_staff_member_own_rules_override_page_hours(): void
    {
        [, , $config] = $this->makePage();
        $member = $this->addStaff($config);
        // Priya only works Wed 10:00–12:00.
        ServiceBookingAvailabilityRule::create([
            'service_booking_id' => $config->id,
            'staff_id'           => $member->id,
            'day_of_week'        => 3,
            'start_time'         => '10:00',
            'end_time'           => '12:00',
            'is_active'          => true,
        ]);

        $days = $this->slots()->freeSlots($config, 30, null, ['staff_id' => $member->id]);
        $starts = $this->starts($days, self::TODAY);

        $this->assertSame('10:00', $starts[0]);
        $this->assertSame('11:30', end($starts));
        $this->assertNotContains('09:00', $starts);
    }

    public function test_any_available_keeps_slot_free_while_one_of_two_staff_is_booked(): void
    {
        [, $link, $config] = $this->makePage();
        $a = $this->addStaff($config, 'A');
        $this->addStaff($config, 'B');

        $this->hold($link, $config, self::TODAY . ' 10:00:00', ['staff_id' => $a->id]);

        // A is busy at 10:00 — "any available" still offers it (via B) …
        $days = $this->slots()->freeSlots($config, 30);
        $this->assertContains('10:00', $this->starts($days, self::TODAY));

        // … but A specifically no longer has it.
        $daysA = $this->slots()->freeSlots($config, 30, null, ['staff_id' => $a->id]);
        $this->assertNotContains('10:00', $this->starts($daysA, self::TODAY));
    }

    public function test_booking_with_any_available_auto_assigns_a_free_member(): void
    {
        [, $link, $config, $service] = $this->makePage();
        $a = $this->addStaff($config, 'A');
        $b = $this->addStaff($config, 'B');
        $this->hold($link, $config, self::TODAY . ' 10:00:00', ['staff_id' => $a->id]);

        $res = $this->postJson('/api/v1/service-booking/' . $link->alias . '/book', [
            'customer_name' => 'Ada',
            'slot_start'    => self::TODAY . 'T10:00:00Z',
            'services'      => [['service_id' => $service->id, 'quantity' => 1]],
        ]);

        $res->assertCreated();
        $this->assertSame('B', $res->json('data.booking.staff.name'));
        $this->assertSame($b->id, $res->json('data.booking.staff.id'));
    }

    public function test_staff_service_assignment_filters_eligibility(): void
    {
        [, $link, $config, $service] = $this->makePage();
        $other = ServiceBookingService::create([
            'service_booking_id' => $config->id,
            'name'               => 'Massage',
            'price'              => 80,
            'duration_minutes'   => 30,
            'is_active'          => true,
            'is_unavailable'     => false,
        ]);
        $member = $this->addStaff($config);
        $member->services()->sync([$other->id]); // can NOT do Haircut

        // Requesting Haircut with this member yields no slots.
        $days = $this->slots()->freeSlots($config, 30, null, [
            'staff_id'    => $member->id,
            'service_ids' => [$service->id],
        ]);
        $this->assertSame([], $days);

        $res = $this->postJson('/api/v1/service-booking/' . $link->alias . '/book', [
            'customer_name' => 'Ada',
            'slot_start'    => self::TODAY . 'T10:00:00Z',
            'services'      => [['service_id' => $service->id]],
            'staff_id'      => $member->id,
        ]);
        $res->assertStatus(422);
    }

    // ── Capacity + buffers ────────────────────────────────────────────

    public function test_group_capacity_allows_parallel_bookings_until_full(): void
    {
        [, $link, $config, $service] = $this->makePage();
        $service->update(['capacity' => 2]);

        $days = $this->slots()->freeSlots($config, 30, null, ['service_ids' => [$service->id], 'capacity' => 2]);
        $this->assertSame(2, $this->remainingAt($days, self::TODAY, '10:00'));

        $this->hold($link, $config, self::TODAY . ' 10:00:00');
        $days = $this->slots()->freeSlots($config, 30, null, ['service_ids' => [$service->id], 'capacity' => 2]);
        $this->assertSame(1, $this->remainingAt($days, self::TODAY, '10:00'));

        $this->hold($link, $config, self::TODAY . ' 10:00:00');
        $days = $this->slots()->freeSlots($config, 30, null, ['service_ids' => [$service->id], 'capacity' => 2]);
        $this->assertNull($this->remainingAt($days, self::TODAY, '10:00'), 'full slot must disappear');
    }

    public function test_buffers_block_adjacent_slots(): void
    {
        [, $link, $config] = $this->makePage();
        // 30 min buffer after every booking (page-level).
        $config->update(['settings' => ['buffers' => ['before' => 0, 'after' => 30]]]);

        $this->hold($link, $config, self::TODAY . ' 10:00:00', [
            'buffer_after_minutes' => 30,
        ]);

        $starts = $this->starts($this->slots()->freeSlots($config, 30), self::TODAY);
        $this->assertNotContains('10:00', $starts);
        $this->assertNotContains('10:30', $starts, 'the after-buffer must hold the next slot');
        $this->assertContains('11:00', $starts);
    }

    // ── Visitor self-service via the public API ───────────────────────

    public function test_visitor_can_cancel_before_cutoff(): void
    {
        [, $link, $config] = $this->makePage();
        $config->update(['settings' => ['self_service' => [
            'allow_cancel' => true, 'allow_reschedule' => true, 'cutoff_hours' => 2,
        ]]]);
        $token = (string) Str::uuid();
        $booking = $this->hold($link, $config, self::TODAY . ' 15:00:00', [
            'public_token' => $token,
        ]);

        $res = $this->postJson('/api/v1/service-booking/bookings/' . $token . '/cancel');

        $res->assertOk();
        $this->assertSame('cancelled', $res->json('data.booking.status'));
        $this->assertSame(
            ServiceBookingRequest::STATUS_CANCELLED,
            $booking->fresh()->status,
        );
    }

    public function test_cancel_inside_cutoff_window_is_rejected(): void
    {
        [, $link, $config] = $this->makePage();
        $config->update(['settings' => ['self_service' => [
            'allow_cancel' => true, 'allow_reschedule' => true, 'cutoff_hours' => 24,
        ]]]);
        $token = (string) Str::uuid();
        $booking = $this->hold($link, $config, self::TODAY . ' 15:00:00', [
            'public_token' => $token,
        ]);

        $res = $this->postJson('/api/v1/service-booking/bookings/' . $token . '/cancel');

        $res->assertStatus(422);
        $this->assertSame(
            ServiceBookingRequest::STATUS_PENDING,
            $booking->fresh()->status,
        );
    }

    public function test_cancel_rejected_when_owner_disables_self_service(): void
    {
        [, $link, $config] = $this->makePage();
        $config->update(['settings' => ['self_service' => [
            'allow_cancel' => false, 'allow_reschedule' => false, 'cutoff_hours' => 0,
        ]]]);
        $token = (string) Str::uuid();
        $this->hold($link, $config, self::TODAY . ' 15:00:00', [
            'public_token' => $token,
        ]);

        $this->postJson('/api/v1/service-booking/bookings/' . $token . '/cancel')
            ->assertStatus(422);
    }

    public function test_visitor_reschedule_moves_booking_to_a_free_slot(): void
    {
        [, $link, $config] = $this->makePage();
        $config->update(['settings' => ['self_service' => [
            'allow_cancel' => true, 'allow_reschedule' => true, 'cutoff_hours' => 2,
        ]]]);
        $token = (string) Str::uuid();
        $booking = $this->hold($link, $config, self::TODAY . ' 15:00:00', [
            'public_token' => $token,
        ]);

        $slotsRes = $this->postJson('/api/v1/service-booking/bookings/' . $token . '/reschedule-slots');
        $slotsRes->assertOk();
        $this->assertNotEmpty($slotsRes->json('data.days'));

        $res = $this->postJson('/api/v1/service-booking/bookings/' . $token . '/reschedule', [
            'slot_start' => self::TODAY . 'T12:00:00Z',
        ]);

        $res->assertOk();
        $this->assertSame(
            '12:00',
            $booking->fresh()->slot_start->format('H:i'),
        );
    }

    public function test_reschedule_to_a_held_slot_is_rejected(): void
    {
        [, $link, $config] = $this->makePage();
        $config->update(['settings' => ['self_service' => [
            'allow_cancel' => true, 'allow_reschedule' => true, 'cutoff_hours' => 0,
        ]]]);
        $this->hold($link, $config, self::TODAY . ' 12:00:00'); // occupies 12:00
        $token = (string) Str::uuid();
        $booking = $this->hold($link, $config, self::TODAY . ' 15:00:00', [
            'public_token' => $token,
        ]);

        $this->postJson('/api/v1/service-booking/bookings/' . $token . '/reschedule', [
            'slot_start' => self::TODAY . 'T12:00:00Z',
        ])->assertStatus(422);

        $this->assertSame('15:00', $booking->fresh()->slot_start->format('H:i'));
    }

    // ── Owner staff CRUD + plan cap on /api/v1 ────────────────────────

    public function test_owner_staff_crud_respects_plan_cap(): void
    {
        [$owner, $link, $config] = $this->makePage();
        $plan = Plan::create([
            'name'               => 'Starter',
            'slug'               => 'plan-' . Str::lower(Str::random(6)),
            'monthly_price'      => 0,
            'annual_price'       => 0,
            'trial_days'         => 0,
            'grace_days'         => 0,
            'refund_window_days' => 0,
            'status'             => 'active',
            'sort_order'         => 1,
            'features'           => ['max_service_booking_staff' => 1],
        ]);
        $owner->update(['plan_id' => $plan->id]);
        $owner = $owner->fresh();
        $headers = [
            'Authorization' => 'Bearer ' . $owner->createToken('test', ['*'])->plainTextToken,
            'Accept'        => 'application/json',
        ];
        $base = '/api/v1/service-booking/links/' . $link->id . '/config/staff';
        $cap = 1;

        // Fill up to the cap.
        for ($i = 0; $i < $cap; $i++) {
            $this->postJson($base, ['name' => 'Member ' . $i], $headers)->assertCreated();
        }
        $this->assertSame($cap, $config->staff()->count());

        // One over the cap → plan_limit error.
        $over = $this->postJson($base, ['name' => 'Too Many'], $headers);
        $over->assertStatus(422);
        $this->assertSame('plan_limit', $over->json('error.code'));

        // Update + delete round-trip.
        $staffId = $config->staff()->first()->id;
        $this->putJson($base . '/' . $staffId, ['title' => 'Senior'], $headers)
            ->assertOk()
            ->assertJsonPath('data.staff.title', 'Senior');
        $this->deleteJson($base . '/' . $staffId, [], $headers)->assertOk();
        $this->assertNull(ServiceBookingStaff::find($staffId));
    }

    public function test_owner_staff_notification_email_persists_and_is_validated(): void
    {
        [$owner, $link, $config] = $this->makePage();
        $plan = Plan::create([
            'name'               => 'Team',
            'slug'               => 'plan-' . Str::lower(Str::random(6)),
            'monthly_price'      => 0,
            'annual_price'       => 0,
            'trial_days'         => 0,
            'grace_days'         => 0,
            'refund_window_days' => 0,
            'status'             => 'active',
            'sort_order'         => 1,
            'features'           => ['max_service_booking_staff' => -1],
        ]);
        $owner->update(['plan_id' => $plan->id]);
        $owner = $owner->fresh();
        $headers = [
            'Authorization' => 'Bearer ' . $owner->createToken('test', ['*'])->plainTextToken,
            'Accept'        => 'application/json',
        ];
        $base = '/api/v1/service-booking/links/' . $link->id . '/config/staff';

        // Bad email rejected.
        $this->postJson($base, ['name' => 'Priya', 'email' => 'not-an-email'], $headers)
            ->assertStatus(422);

        // Valid email persists and round-trips in the payload.
        $created = $this->postJson($base, ['name' => 'Priya', 'email' => 'priya@example.com'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.staff.email', 'priya@example.com');
        $staffId = $created->json('data.staff.id');
        $this->assertSame('priya@example.com', ServiceBookingStaff::find($staffId)->email);

        // Update can clear it.
        $this->putJson($base . '/' . $staffId, ['email' => null], $headers)
            ->assertOk()
            ->assertJsonPath('data.staff.email', null);
        $this->assertNull(ServiceBookingStaff::find($staffId)->fresh()->email);
    }

    public function test_owner_can_manage_per_staff_hours_and_blocked_dates(): void
    {
        [$owner, $link, $config] = $this->makePage();
        $member = $this->addStaff($config);
        $other = ServiceBookingStaff::create([
            'service_booking_id' => ServiceBooking::create([
                'link_id' => Link::create([
                    'user_id' => User::factory()->create()->id,
                    'type' => Link::TYPE_SERVICE_BOOKING,
                    'alias' => Link::generateAlias(),
                    'title' => 'Other',
                    'is_active' => true,
                ])->id,
                'user_id' => User::factory()->create()->id,
                'mode' => ServiceBooking::MODE_BOOKING,
                'currency' => 'USD',
                'slot_length_minutes' => 30,
                'lead_time_minutes' => 0,
                'max_days_ahead' => 7,
                'timezone' => 'UTC',
            ])->id,
            'name' => 'Foreign',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $headers = [
            'Authorization' => 'Bearer ' . $owner->createToken('test', ['*'])->plainTextToken,
            'Accept'        => 'application/json',
        ];
        $base = '/api/v1/service-booking/links/' . $link->id . '/config';

        // Per-staff weekly rule.
        $res = $this->postJson($base . '/availability', [
            'day_of_week' => 3, 'start_time' => '12:00', 'end_time' => '15:00',
            'staff_id' => $member->id,
        ], $headers);
        $res->assertCreated()->assertJsonPath('data.rule.staff_id', $member->id);

        // Foreign staff_id rejected.
        $bad = $this->postJson($base . '/availability', [
            'day_of_week' => 3, 'start_time' => '12:00', 'end_time' => '15:00',
            'staff_id' => $other->id,
        ], $headers);
        $bad->assertStatus(422);
        $this->assertSame('invalid_staff', $bad->json('error.code'));

        // Per-staff blocked date; same date may still be blocked page-wide.
        $this->postJson($base . '/blocked-dates', [
            'date' => self::TODAY, 'staff_id' => $member->id,
        ], $headers)->assertCreated()->assertJsonPath('data.blocked_date.staff_id', $member->id);
        $this->postJson($base . '/blocked-dates', [
            'date' => self::TODAY, 'staff_id' => $member->id,
        ], $headers)->assertStatus(422);
        $this->postJson($base . '/blocked-dates', [
            'date' => self::TODAY,
        ], $headers)->assertCreated()->assertJsonPath('data.blocked_date.staff_id', null);

        // Owner config payload surfaces staff_id on both lists.
        $cfg = $this->getJson($base, $headers)->assertOk()->json('data.config');
        $ruleStaffIds = collect($cfg['availability_rules'])->pluck('staff_id')->all();
        $this->assertContains($member->id, $ruleStaffIds);
        $blockedStaffIds = collect($cfg['blocked_dates'])->pluck('staff_id')->all();
        $this->assertContains($member->id, $blockedStaffIds);
        $this->assertContains(null, $blockedStaffIds);
    }

    public function test_per_staff_blocked_date_removes_only_that_member_slots(): void
    {
        [, , $config, $service] = $this->makePage();
        $priya = $this->addStaff($config, 'Priya');
        $noah = $this->addStaff($config, 'Noah');

        \App\Modules\User\Models\ServiceBookingBlockedDate::create([
            'service_booking_id' => $config->id,
            'staff_id'           => $priya->id,
            'date'               => self::TODAY,
        ]);

        $opts = ['capacity' => 1];

        // Priya has no slots today; Noah still does; "any" still does.
        $priyaDays = $this->slots()->freeSlots($config->fresh(), 30, null, array_merge($opts, ['staff_id' => $priya->id]));
        $this->assertSame([], $this->starts($priyaDays, self::TODAY));

        $noahDays = $this->slots()->freeSlots($config->fresh(), 30, null, array_merge($opts, ['staff_id' => $noah->id]));
        $this->assertNotEmpty($this->starts($noahDays, self::TODAY));

        $anyDays = $this->slots()->freeSlots($config->fresh(), 30, null, $opts);
        $this->assertNotEmpty($this->starts($anyDays, self::TODAY));
    }

    public function test_public_page_payload_lists_active_staff(): void
    {
        [, $link, $config, $service] = $this->makePage();
        $member = $this->addStaff($config, 'Priya', ['title' => 'Stylist']);
        $member->services()->sync([$service->id]);
        $this->addStaff($config, 'Hidden', ['is_active' => false]);

        $res = $this->getJson('/api/v1/service-booking/' . $link->alias);

        $res->assertOk();
        $staff = $res->json('data.staff');
        $this->assertCount(1, $staff);
        $this->assertSame('Priya', $staff[0]['name']);
        $this->assertSame([$service->id], $staff[0]['service_ids']);
    }
}
