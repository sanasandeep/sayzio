<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WaitlistPromotionService;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Waitlist auto-promotion (Task waitlist): when a confirmed guest frees a
 * seat, the oldest waitlisted guest that fits is promoted to confirmed and
 * emailed. Race-safe (no overbooking under plus-ones), per-event toggle,
 * and paid tiers get an invite email rather than an auto-promote.
 *
 * Per repo memory: Mail::fake() never records Mail::raw(), so we assert
 * in-app state (RSVP status) rather than mail spooling; the service is the
 * single promotion authority so verifying status flips proves the behavior.
 */
class WaitlistPromotionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Ada Organizer'): User
    {
        $u = User::factory()->create(['name' => $name]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function makeEvent(User $user, array $rsvpSettings = []): Link
    {
        $link = Link::create([
            'user_id'    => $user->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => 'Launch Party',
            'settings'   => [
                'rsvp_enabled'         => true,
                'rsvp_allow_plus_ones' => true,
                'rsvp_settings'        => array_merge([
                    'capacity'         => 2,
                    'waitlist_enabled' => true,
                ], $rsvpSettings),
            ],
            'visibility' => 'public',
            'is_active'  => true,
        ]);
        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => '2035-06-01 09:00:00',
            'end_date'   => '2035-06-01 10:00:00',
            'timezone'   => 'UTC',
            'all_day'    => false,
        ]);
        return $link;
    }

    private function makeRsvp(Link $link, string $status, string $name, int $plusOnes = 0, ?string $createdAt = null): Rsvp
    {
        $r = Rsvp::create([
            'link_id'   => $link->id,
            'name'      => $name,
            'email'     => strtolower(str_replace(' ', '', $name)) . '@ex.com',
            'response'  => 'yes',
            'status'    => $status,
            'plus_ones' => $plusOnes,
        ]);
        if ($createdAt) {
            // Control FIFO ordering deterministically.
            $r->forceFill(['created_at' => $createdAt])->saveQuietly();
        }
        return $r->fresh();
    }

    public function test_cancel_promotes_oldest_waitlisted_guest(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        $link = $this->makeEvent($host); // capacity 2

        $confirmed = $this->makeRsvp($link, 'confirmed', 'Confirmed Guest');
        $w1 = $this->makeRsvp($link, 'waitlist', 'Older Wait', 0, '2020-01-01 00:00:00');
        $w2 = $this->makeRsvp($link, 'waitlist', 'Newer Wait', 0, '2020-06-01 00:00:00');

        // Two confirmed seats used up; drop one so exactly one seat frees.
        $this->makeRsvp($link, 'confirmed', 'Second Confirmed');

        // Cancel the first confirmed guest via the guest manage token.
        $resp = $this->post('/' . $link->alias . '/rsvp/manage/' . $confirmed->manage_token . '/cancel');
        $resp->assertRedirect();

        $this->assertSame('confirmed', $w1->fresh()->status, 'Oldest waitlisted guest should be promoted.');
        $this->assertSame('waitlist', $w2->fresh()->status, 'Newer waitlisted guest stays waiting (capacity 2).');
    }

    public function test_no_overbooking_with_plus_ones(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        // Capacity 3.
        $link = $this->makeEvent($host, ['capacity' => 3]);

        // One confirmed guest with a +1 = 2 seats. One seat free after they exist.
        $confirmed = $this->makeRsvp($link, 'confirmed', 'Party Of Two', 1); // 2 seats
        // A confirmed solo taking the 3rd seat.
        $solo = $this->makeRsvp($link, 'confirmed', 'Solo Confirmed', 0); // total 3/3

        // Oldest waitlisted guest brings a +1 (needs 2 seats); newer needs 1.
        $wBig  = $this->makeRsvp($link, 'waitlist', 'Big Party', 1, '2020-01-01 00:00:00'); // 2 seats
        $wTiny = $this->makeRsvp($link, 'waitlist', 'Tiny One', 0, '2020-06-01 00:00:00');  // 1 seat

        // Cancel the solo → frees exactly 1 seat.
        $solo->update(['status' => 'cancelled', 'response' => 'no']);
        $promoted = app(WaitlistPromotionService::class)->promoteForLink($link->fresh());

        // The oldest waitlisted party needs 2 seats but only 1 is free, so
        // FIFO fairness stops there — nobody gets over-promoted.
        $this->assertSame(0, $promoted, 'Nobody fits without overbooking.');
        $this->assertSame('waitlist', $wBig->fresh()->status);
        $this->assertSame('waitlist', $wTiny->fresh()->status);

        // Total confirmed seats must never exceed capacity.
        $used = $link->fresh()->rsvps()->where('status', 'confirmed')->get()
            ->sum(fn (Rsvp $r) => $r->seatsConsumed());
        $this->assertLessThanOrEqual(3, $used);
    }

    public function test_toggle_off_disables_promotion(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        $link = $this->makeEvent($host, ['capacity' => 1, 'waitlist_auto_promote' => false]);

        $confirmed = $this->makeRsvp($link, 'confirmed', 'The Confirmed');
        $wait = $this->makeRsvp($link, 'waitlist', 'The Waiter');

        $confirmed->update(['status' => 'cancelled', 'response' => 'no']);
        $promoted = app(WaitlistPromotionService::class)->promoteForLink($link->fresh());

        $this->assertSame(0, $promoted, 'Toggle off → no promotion.');
        $this->assertSame('waitlist', $wait->fresh()->status);
    }

    public function test_promotion_promotes_when_a_seat_opens(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        $link = $this->makeEvent($host, ['capacity' => 1]);

        $confirmed = $this->makeRsvp($link, 'confirmed', 'First In');
        $wait = $this->makeRsvp($link, 'waitlist', 'Next Up');

        $confirmed->update(['status' => 'cancelled', 'response' => 'no']);
        $promoted = app(WaitlistPromotionService::class)->promoteForLink($link->fresh());

        $this->assertSame(1, $promoted);
        $this->assertSame('confirmed', $wait->fresh()->status);
        // Promotion should mint a check-in ticket for the now-confirmed guest.
        $this->assertNotNull($wait->fresh()->ticket, 'Promoted guest gets a check-in ticket.');
    }

    public function test_paid_tier_invites_but_does_not_auto_promote(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        $link = $this->makeEvent($host, ['capacity' => 1]);

        $tier = EventTicketTier::create([
            'link_id'     => $link->id,
            'name'        => 'General',
            'price_cents' => 2500, // PAID
            'currency'    => 'USD',
            'capacity'    => 10,
            'sold_count'  => 10, // full
            'is_active'   => true,
            'sort_order'  => 1,
        ]);

        $wait = $this->makeRsvp($link, 'waitlist', 'Paid Waiter');

        // Free a paid seat / raise capacity and run the tier promotion path.
        // Per repo memory Mail::fake can't record Mail::raw, so we assert the
        // in-app outcome: paid tiers never flip a guest to confirmed.
        $tier->update(['sold_count' => 9, 'capacity' => 11]);
        $promoted = app(WaitlistPromotionService::class)->promoteForTier($link->fresh(), $tier->fresh());

        // Paid tier: guest is NOT auto-confirmed.
        $this->assertSame(0, $promoted, 'Paid tiers never auto-promote.');
        $this->assertSame('waitlist', $wait->fresh()->status, 'Paid waitlisted guest stays on the waitlist.');
        // …but the "spot opened" invite marker must be stamped.
        $this->assertNotNull($wait->fresh()->waitlist_invited_at, 'Paid invite should stamp waitlist_invited_at.');
    }

    public function test_paid_invite_is_idempotent_across_double_trigger(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        $link = $this->makeEvent($host, ['capacity' => 1]);

        $tier = EventTicketTier::create([
            'link_id'     => $link->id,
            'name'        => 'General',
            'price_cents' => 2500, // PAID
            'currency'    => 'USD',
            'capacity'    => 5,
            'sold_count'  => 4, // one seat free
            'is_active'   => true,
            'sort_order'  => 1,
        ]);

        $wait = $this->makeRsvp($link, 'waitlist', 'Paid Waiter');

        $svc = app(WaitlistPromotionService::class);

        // Two back-to-back triggers (simulating concurrent/repeated capacity
        // events) must send exactly ONE invite.
        $svc->invitePaidWaitlist($link->fresh(), $tier->fresh());
        $firstStamp = $wait->fresh()->waitlist_invited_at;
        $this->assertNotNull($firstStamp);

        $svc->invitePaidWaitlist($link->fresh(), $tier->fresh());

        // The second trigger falls inside the 24h cooldown → no re-stamp,
        // no second email.
        $this->assertEquals(
            $firstStamp->toDateTimeString(),
            $wait->fresh()->waitlist_invited_at->toDateTimeString(),
            'Second trigger within cooldown must not re-invite.'
        );
        Mail::assertSent(\App\Mail\EventWaitlistPromotedMail::class, 1);

        // Once the cooldown has elapsed a fresh seat re-invites again.
        $wait->forceFill(['waitlist_invited_at' => now()->subHours(25)])->saveQuietly();
        $svc->invitePaidWaitlist($link->fresh(), $tier->fresh());
        Mail::assertSent(\App\Mail\EventWaitlistPromotedMail::class, 2);
    }
}
