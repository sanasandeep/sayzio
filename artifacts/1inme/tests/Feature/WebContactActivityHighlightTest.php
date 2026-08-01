<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\ContactActivityService;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Web contact activity links jump to the exact record (Task #6531): timeline
 * `url` fields carry a ?highlight={record id} query param, and the destination
 * dashboards honour it — Alpine boards start on the "all" filter (so closed
 * records are visible) with the matching card outlined, and the RSVP/ticket
 * tables outline the matching row.
 */
class WebContactActivityHighlightTest extends TestCase
{
    use RefreshDatabase;

    private function makeLink(User $owner, string $type): Link
    {
        return Link::withoutGlobalScope('workspace')->create([
            'user_id' => $owner->id,
            'type'    => $type,
            'alias'   => 't' . Str::lower(Str::random(10)),
            'url'     => null,
        ]);
    }

    private function bindWorkspace(User $owner): void
    {
        $ws = app(WorkspaceContext::class)->resolve($owner);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $owner);
    }

    private function makeBooking(User $owner, Link $link, string $status = 'completed'): ServiceBookingRequest
    {
        $sbId = DB::table('service_bookings')->insertGetId([
            'link_id'    => $link->id,
            'user_id'    => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booking = ServiceBookingRequest::withoutGlobalScope('workspace')->create([
            'service_booking_id' => $sbId,
            'link_id'            => $link->id,
            'public_token'       => (string) Str::uuid(),
            'customer_name'      => 'Diner Dan',
            'slot_start'         => now()->addDay(),
            'slot_end'           => now()->addDay()->addHour(),
        ]);
        $booking->forceFill(['status' => $status])->saveQuietly();

        return $booking;
    }

    private function makeTicketId(Link $link, ?int $contactId = null): int
    {
        $tierId = DB::table('event_ticket_tiers')->insertGetId([
            'link_id'    => $link->id,
            'name'       => 'GA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('event_tickets')->insertGetId([
            'tier_id'    => $tierId,
            'link_id'    => $link->id,
            'contact_id' => $contactId,
            'code'       => 'T-' . Str::upper(Str::random(10)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_timeline_urls_carry_highlight_param_for_all_five_record_types(): void
    {
        $owner   = User::factory()->create();
        $contact = Contact::create(['user_id' => $owner->id, 'display_name' => 'Diner Dan']);

        $attach = fn ($model) => $model->forceFill(['contact_id' => $contact->id])->saveQuietly();

        $rLink = $this->makeLink($owner, 'restaurant_menu');
        $order = RestaurantOrder::withoutGlobalScope('workspace')->create(['menu_id' => 1, 'link_id' => $rLink->id, 'status' => 'completed']);
        $attach($order);

        $sLink = $this->makeLink($owner, 'store_menu');
        $storeOrder = StoreOrder::withoutGlobalScope('workspace')->create(['menu_id' => 1, 'link_id' => $sLink->id, 'status' => 'completed']);
        $attach($storeOrder);

        $bLink   = $this->makeLink($owner, 'service_booking');
        $booking = $this->makeBooking($owner, $bLink);
        $attach($booking);

        $eLink = $this->makeLink($owner, 'event');
        $rsvp = Rsvp::withoutGlobalScope('workspace')->create(['link_id' => $eLink->id, 'name' => 'Diner Dan', 'response' => 'yes']);
        $attach($rsvp);

        $ticketId = $this->makeTicketId($eLink, $contact->id);

        $groups = collect(app(ContactActivityService::class)->timeline($contact))->keyBy('key');

        $this->assertStringEndsWith('?highlight=' . $order->id, $groups['restaurant_orders']['items'][0]['url']);
        $this->assertStringEndsWith('?highlight=' . $storeOrder->id, $groups['store_orders']['items'][0]['url']);
        $this->assertStringEndsWith('?highlight=' . $booking->id, $groups['bookings']['items'][0]['url']);
        $this->assertStringEndsWith('?highlight=' . $rsvp->id, $groups['rsvps']['items'][0]['url']);
        $this->assertStringEndsWith('?highlight=' . $ticketId, $groups['event_tickets']['items'][0]['url']);
    }

    public function test_restaurant_orders_dashboard_defaults_to_all_filter_when_highlighting(): void
    {
        $owner = User::factory()->create();
        $this->bindWorkspace($owner);
        $link  = $this->makeLink($owner, 'restaurant_menu');
        $order = RestaurantOrder::withoutGlobalScope('workspace')->create(['menu_id' => 1, 'link_id' => $link->id, 'status' => 'completed']);

        $res = $this->actingAs($owner)
            ->get(route('user.links.restaurant.orders', $link) . '?highlight=' . $order->id)
            ->assertOk();

        $res->assertSee('highlight: ' . $order->id, false);
        $res->assertSee("filter: \"all\"", false);

        // Without the param, the board keeps its "open" default.
        $this->actingAs($owner)
            ->get(route('user.links.restaurant.orders', $link))
            ->assertOk()
            ->assertSee("filter: \"open\"", false);
    }

    public function test_booking_dashboard_defaults_to_all_filter_when_highlighting(): void
    {
        $owner = User::factory()->create();
        $this->bindWorkspace($owner);
        $link    = $this->makeLink($owner, 'service_booking');
        $booking = $this->makeBooking($owner, $link);

        $this->actingAs($owner)
            ->get(route('user.links.service-booking.bookings', $link) . '?highlight=' . $booking->id)
            ->assertOk()
            ->assertSee('highlight: ' . $booking->id, false)
            ->assertSee("filter: \"all\"", false);
    }

    public function test_rsvp_table_outlines_and_scrolls_to_highlighted_row(): void
    {
        $owner = User::factory()->create();
        $this->bindWorkspace($owner);
        $link = $this->makeLink($owner, 'ics');
        $rsvp = Rsvp::withoutGlobalScope('workspace')->create(['link_id' => $link->id, 'name' => 'Guest Gwen', 'response' => 'yes']);

        $this->actingAs($owner)
            ->get(route('user.links.rsvps.index', $link) . '?highlight=' . $rsvp->id)
            ->assertOk()
            ->assertSee('id="rsvp-' . $rsvp->id . '"', false)
            ->assertSee('outline: 2px solid', false)
            ->assertSee("getElementById('rsvp-" . $rsvp->id . "')", false);
    }

    public function test_rsvp_highlight_resolves_the_page_containing_an_older_record(): void
    {
        $owner = User::factory()->create();
        $this->bindWorkspace($owner);
        $link = $this->makeLink($owner, 'ics');

        $old = Rsvp::withoutGlobalScope('workspace')->create(['link_id' => $link->id, 'name' => 'Old Ollie', 'response' => 'yes']);
        $old->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();

        $newer = [];
        for ($i = 0; $i < 55; $i++) {
            $newer[] = [
                'link_id'    => $link->id,
                'name'       => 'Guest ' . $i,
                'response'   => 'yes',
                'created_at' => now()->subMinutes($i),
                'updated_at' => now(),
            ];
        }
        DB::table('rsvps')->insert($newer);

        $this->actingAs($owner)
            ->get(route('user.links.rsvps.index', $link) . '?highlight=' . $old->id)
            ->assertOk()
            ->assertSee('id="rsvp-' . $old->id . '"', false)
            ->assertSee('outline: 2px solid', false);
    }

    public function test_ticket_highlight_resolves_the_page_containing_an_older_record(): void
    {
        $owner = User::factory()->create();
        $this->bindWorkspace($owner);
        $link = $this->makeLink($owner, 'ics');

        $oldId = $this->makeTicketId($link);
        DB::table('event_tickets')->where('id', $oldId)->update(['created_at' => now()->subDays(30)]);

        $tierId = DB::table('event_ticket_tiers')->insertGetId([
            'link_id' => $link->id, 'name' => 'GA2', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $rows = [];
        for ($i = 0; $i < 55; $i++) {
            $rows[] = [
                'tier_id'    => $tierId,
                'link_id'    => $link->id,
                'code'       => 'T-' . Str::upper(Str::random(10)),
                'created_at' => now()->subMinutes($i),
                'updated_at' => now(),
            ];
        }
        DB::table('event_tickets')->insert($rows);

        $this->actingAs($owner)
            ->get(route('user.links.ics.tickets', $link) . '?highlight=' . $oldId)
            ->assertOk()
            ->assertSee('id="ticket-' . $oldId . '"', false)
            ->assertSee('outline: 2px solid', false);
    }
}
