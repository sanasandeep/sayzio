<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The mobile contact screen deep-links activity rows straight to the exact
 * record (Task: per-record highlight). Covers the per-record ids that
 * ContactActivityService::timeline() now emits in `refs`: order_id for
 * restaurant/store orders, booking_id for bookings, rsvp_id (+ best-effort
 * user_id) for RSVPs, and ticket_id (+ buyer user_id) for event tickets.
 */
class ApiContactActivityRecordRefsTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeLink(User $owner, string $type, ?string $alias = null): Link
    {
        return Link::withoutGlobalScope('workspace')->create([
            'user_id' => $owner->id,
            'type'    => $type,
            'alias'   => $alias ?? ('t' . Str::lower(Str::random(10))),
            'url'     => null,
        ]);
    }

    private function timelineGroups(User $owner, Contact $contact): array
    {
        return $this->withToken($this->token($owner))
            ->getJson('/api/v1/contacts/' . $contact->id . '/activity')
            ->assertOk()
            ->json('data.groups');
    }

    private function group(array $groups, string $key): ?array
    {
        foreach ($groups as $g) {
            if (($g['key'] ?? null) === $key) {
                return $g;
            }
        }

        return null;
    }

    public function test_order_and_booking_refs_carry_record_ids(): void
    {
        $owner   = User::factory()->create();
        $contact = Contact::create(['user_id' => $owner->id, 'display_name' => 'Diner Dan']);

        $restaurantLink = $this->makeLink($owner, 'restaurant_menu');
        $order          = RestaurantOrder::withoutGlobalScope('workspace')->create([
            'menu_id' => 1,
            'link_id' => $restaurantLink->id,
            'status'  => 'completed',
        ]);
        $order->forceFill(['contact_id' => $contact->id])->saveQuietly();

        $storeLink  = $this->makeLink($owner, 'store_menu');
        $storeOrder = StoreOrder::withoutGlobalScope('workspace')->create([
            'menu_id' => 1,
            'link_id' => $storeLink->id,
            'status'  => 'new',
        ]);
        $storeOrder->forceFill(['contact_id' => $contact->id])->saveQuietly();

        $bookingLink = $this->makeLink($owner, 'service_booking');
        $sbId        = DB::table('service_bookings')->insertGetId([
            'link_id'    => $bookingLink->id,
            'user_id'    => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $booking = ServiceBookingRequest::withoutGlobalScope('workspace')->create([
            'service_booking_id' => $sbId,
            'link_id'            => $bookingLink->id,
            'public_token'       => (string) Str::uuid(),
            'customer_name'      => 'Diner Dan',
            'slot_start'         => now()->addDay(),
            'slot_end'           => now()->addDay()->addHour(),
        ]);
        $booking->forceFill(['contact_id' => $contact->id])->saveQuietly();

        $groups = $this->timelineGroups($owner, $contact);

        $refs = $this->group($groups, 'restaurant_orders')['items'][0]['refs'];
        $this->assertSame($restaurantLink->id, $refs['link_id']);
        $this->assertSame($order->id, $refs['order_id']);

        $refs = $this->group($groups, 'store_orders')['items'][0]['refs'];
        $this->assertSame($storeLink->id, $refs['link_id']);
        $this->assertSame($storeOrder->id, $refs['order_id']);

        $refs = $this->group($groups, 'bookings')['items'][0]['refs'];
        $this->assertSame($bookingLink->id, $refs['link_id']);
        $this->assertSame($booking->id, $refs['booking_id']);
    }

    public function test_rsvp_and_ticket_refs_carry_record_and_user_ids(): void
    {
        $owner    = User::factory()->create();
        $attendee = User::factory()->create();

        $contact = Contact::create(['user_id' => $owner->id, 'display_name' => 'Guest Gwen']);
        $contact->forceFill(['biolink_user_id' => $attendee->id])->saveQuietly();

        $eventLink = $this->makeLink($owner, 'event', 'ev' . Str::lower(Str::random(8)));

        $rsvp = Rsvp::withoutGlobalScope('workspace')->create([
            'link_id'  => $eventLink->id,
            'name'     => 'Guest Gwen',
            'response' => 'yes',
        ]);
        $rsvp->forceFill(['contact_id' => $contact->id])->saveQuietly();

        $tierId = DB::table('event_ticket_tiers')->insertGetId([
            'link_id'    => $eventLink->id,
            'name'       => 'GA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ticketId = DB::table('event_tickets')->insertGetId([
            'tier_id'       => $tierId,
            'link_id'       => $eventLink->id,
            'buyer_user_id' => $attendee->id,
            'contact_id'    => $contact->id,
            'code'          => 'T-' . Str::upper(Str::random(10)),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $groups = $this->timelineGroups($owner, $contact);

        $refs = $this->group($groups, 'rsvps')['items'][0]['refs'];
        $this->assertSame($eventLink->id, $refs['link_id']);
        $this->assertSame($eventLink->alias, $refs['alias']);
        $this->assertSame($rsvp->id, $refs['rsvp_id']);
        $this->assertSame($attendee->id, $refs['user_id']);

        $refs = $this->group($groups, 'event_tickets')['items'][0]['refs'];
        $this->assertSame($eventLink->id, $refs['link_id']);
        $this->assertSame($eventLink->alias, $refs['alias']);
        $this->assertSame($ticketId, $refs['ticket_id']);
        $this->assertSame($attendee->id, $refs['user_id']);
    }
}
