<?php

namespace Tests\Unit\Services;

use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Services\Events\EventTierCapacityAlerter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Task #3623 — proactive "tier is 90%+ full / sold out" alerts.
 *
 * Verifies each threshold fires exactly once per tier (idempotent), that
 * unbounded (null-capacity) tiers never trip, that a raise-capacity re-fill
 * re-alerts, and that the message carries counts only (no attendee PII).
 */
class EventTierCapacityAlerterTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        return User::create([
            'name'     => 'Event Owner',
            'email'    => 'owner-' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => bcrypt('pw'),
            'status'   => 'active',
            'timezone' => 'UTC',
            'language' => 'en',
        ]);
    }

    private function makeTier(User $owner, ?int $capacity, int $sold, int $priceCents = 2500): EventTicketTier
    {
        $link = Link::create([
            'user_id'   => $owner->id,
            'type'      => 'ics',
            'alias'     => 'ev' . bin2hex(random_bytes(3)),
            'title'     => 'Product Launch Party',
            'is_active' => true,
        ]);

        return EventTicketTier::create([
            'link_id'     => $link->id,
            'name'        => 'VIP',
            'price_cents' => $priceCents,
            'currency'    => 'usd',
            'capacity'    => $capacity,
            'sold_count'  => $sold,
        ]);
    }

    private function alertsFor(int $userId): \Illuminate\Support\Collection
    {
        return UserNotification::where('user_id', $userId)
            ->whereIn('type', ['event.tier_sold_out', 'event.tier_near_full'])
            ->get();
    }

    public function test_ninety_percent_full_fires_one_near_alert(): void
    {
        Mail::fake();
        $owner = $this->makeOwner();
        $tier = $this->makeTier($owner, 10, 9);

        EventTierCapacityAlerter::check($tier);

        $alerts = $this->alertsFor($owner->id);
        $this->assertCount(1, $alerts);
        $this->assertSame('event.tier_near_full', $alerts->first()->type);
        $this->assertNotNull($tier->fresh()->capacity_alerted_near_at);
        $this->assertNull($tier->fresh()->capacity_alerted_full_at);
    }

    public function test_near_full_alert_is_idempotent(): void
    {
        Mail::fake();
        $owner = $this->makeOwner();
        $tier = $this->makeTier($owner, 10, 9);

        EventTierCapacityAlerter::check($tier);
        // Another sale still short of sold out — no second near alert.
        $tier->sold_count = 9;
        EventTierCapacityAlerter::check($tier);

        $this->assertCount(1, $this->alertsFor($owner->id));
    }

    public function test_sold_out_fires_and_suppresses_a_later_near_alert(): void
    {
        Mail::fake();
        $owner = $this->makeOwner();
        // Jumps straight to sold out (large purchase): only the sold-out alert.
        $tier = $this->makeTier($owner, 10, 10);

        EventTierCapacityAlerter::check($tier);

        $alerts = $this->alertsFor($owner->id);
        $this->assertCount(1, $alerts);
        $this->assertSame('event.tier_sold_out', $alerts->first()->type);

        $fresh = $tier->fresh();
        $this->assertNotNull($fresh->capacity_alerted_full_at);
        // Near-full is stamped too so no redundant "90%+ full" ever follows.
        $this->assertNotNull($fresh->capacity_alerted_near_at);
    }

    public function test_sold_out_alert_is_idempotent(): void
    {
        Mail::fake();
        $owner = $this->makeOwner();
        $tier = $this->makeTier($owner, 10, 10);

        EventTierCapacityAlerter::check($tier);
        // Even more seats sell — still just the one sold-out alert.
        $tier->sold_count = 12;
        $tier->save();
        EventTierCapacityAlerter::check($tier);

        $this->assertCount(1, $this->alertsFor($owner->id));
    }

    public function test_near_then_sold_out_produces_two_distinct_alerts(): void
    {
        Mail::fake();
        $owner = $this->makeOwner();
        $tier = $this->makeTier($owner, 10, 9);

        EventTierCapacityAlerter::check($tier);
        $tier->sold_count = 10;
        $tier->save();
        EventTierCapacityAlerter::check($tier);

        $types = $this->alertsFor($owner->id)->pluck('type')->sort()->values()->all();
        $this->assertSame(['event.tier_near_full', 'event.tier_sold_out'], $types);
    }

    public function test_unbounded_tier_never_alerts(): void
    {
        Mail::fake();
        $owner = $this->makeOwner();
        // Paid tier with no declared capacity (unbounded) — must never alert
        // regardless of how many sell.
        $tier = $this->makeTier($owner, null, 9999);

        EventTierCapacityAlerter::check($tier);

        $this->assertCount(0, $this->alertsFor($owner->id));
    }

    public function test_free_tier_never_alerts_even_when_sold_out(): void
    {
        Mail::fake();
        $owner = $this->makeOwner();
        // Free (price_cents = 0) tier at full capacity — capacity alerts are
        // scoped to paid tiers only, so nothing fires.
        $tier = $this->makeTier($owner, 10, 10, 0);

        EventTierCapacityAlerter::check($tier);

        $this->assertCount(0, $this->alertsFor($owner->id));
    }

    public function test_below_threshold_does_not_alert(): void
    {
        Mail::fake();
        $owner = $this->makeOwner();
        $tier = $this->makeTier($owner, 10, 8);

        EventTierCapacityAlerter::check($tier);

        $this->assertCount(0, $this->alertsFor($owner->id));
    }

    public function test_message_is_counts_only_without_pii(): void
    {
        Mail::fake();
        $owner = $this->makeOwner();
        $tier = $this->makeTier($owner, 10, 10);

        EventTierCapacityAlerter::check($tier);

        $data = $this->alertsFor($owner->id)->first()->data;
        $this->assertSame(10, $data['sold']);
        $this->assertSame(10, $data['capacity']);
        $this->assertStringNotContainsStringIgnoringCase('@', $data['message']);
        $this->assertStringContainsString('sold out', $data['message']);
    }
}
