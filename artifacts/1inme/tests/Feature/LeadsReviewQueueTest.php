<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\EventInterest;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Lead;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Services\LeadAggregator;
use App\Modules\User\Services\LeadApprover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the Leads review queue (Task #3728).
 *
 * The feature was verified manually via tinker + curl during development
 * but had no automated tests, so any change to one of the 8 source models,
 * {@see \App\Modules\Common\Services\ContactCandidateValidator}, or the
 * plan-cap logic could silently break it. These tests pin the contract of
 * the three moving parts:
 *
 *   - {@see LeadAggregator}: a captured person from EACH of the 8 sources
 *     surfaces as a pending lead, and drops out once a {@see Lead} state
 *     row exists (approve/dismiss) — the source record itself is never
 *     mutated.
 *   - {@see LeadApprover}: approve either CREATES a new Contact or MERGES
 *     into an existing one (dedup by email/phone); the plan's contact cap
 *     blocks the create-new path but NOT a merge; dismiss writes a state
 *     row without ever creating a Contact.
 *   - {@see \App\Modules\User\Controllers\LeadController}: the AJAX index,
 *     approve/dismiss, and bulk endpoints return the right JSON shape and
 *     status codes.
 */
class LeadsReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'     => 'Owner',
            'email'    => 'owner-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
        ], $overrides));
    }

    /** A user whose plan caps contacts at $cap. */
    private function makeUserWithContactCap(int $cap): User
    {
        $plan = Plan::create([
            'name'          => 'Capped ' . Str::random(4),
            'slug'          => 'capped-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'status'        => 'active',
            'features'      => ['contacts_max' => $cap],
        ]);

        return $this->makeUser(['plan_id' => $plan->id]);
    }

    private function makeLink(User $owner, string $type = 'short'): Link
    {
        return Link::create([
            'user_id'   => $owner->id,
            'type'      => $type,
            'alias'     => 'ld-' . Str::random(8),
            'title'     => 'A link',
            'is_active' => true,
        ]);
    }

    // ── Each of the 8 sources surfaces a pending lead ─────────────────

    public function test_rsvp_surfaces_as_a_pending_lead(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeLink($owner, 'ics');
        $rsvp  = Rsvp::create([
            'link_id'  => $link->id,
            'name'     => 'Rsvp Guest',
            'email'    => 'rsvp@example.com',
            'response' => 'yes',
            'status'   => 'confirmed',
        ]);

        $item = (new LeadAggregator($owner->id))->find(LeadAggregator::SOURCE_RSVP, $rsvp->id);

        $this->assertNotNull($item);
        $this->assertSame('Rsvp Guest', $item['name']);
        $this->assertSame('rsvp@example.com', $item['email']);
        $this->assertSame(LeadAggregator::SOURCE_RSVP, $item['source_type']);
    }

    public function test_form_submission_surfaces_as_a_pending_lead(): void
    {
        $owner = $this->makeUser();
        $form  = new Form(['title' => 'Contact form']);
        $form->user_id = $owner->id;
        $form->save();
        $sub = FormSubmission::create([
            'form_id' => $form->id,
            'data'    => ['Name' => 'Form Person', 'Email' => 'form@example.com', 'Phone' => '5551230000'],
            'is_spam' => false,
        ]);

        $item = (new LeadAggregator($owner->id))->find(LeadAggregator::SOURCE_FORM, $sub->id);

        $this->assertNotNull($item);
        $this->assertSame('Form Person', $item['name']);
        $this->assertSame('form@example.com', $item['email']);
        $this->assertSame('5551230000', $item['phone']);
    }

    public function test_subscriber_surfaces_as_a_pending_lead(): void
    {
        $owner = $this->makeUser();
        $sub   = Subscriber::create([
            'user_id' => $owner->id,
            'name'    => 'Sub Person',
            'email'   => 'sub@example.com',
            'status'  => 'active',
            'is_spam' => false,
        ]);

        $item = (new LeadAggregator($owner->id))->find(LeadAggregator::SOURCE_SUBSCRIBER, $sub->id);

        $this->assertNotNull($item);
        $this->assertSame('Sub Person', $item['name']);
        $this->assertSame('sub@example.com', $item['email']);
    }

    public function test_store_order_surfaces_as_a_pending_lead(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeLink($owner, 'store_menu');
        $menu  = StoreMenu::create(['link_id' => $link->id, 'user_id' => $owner->id, 'mode' => 'order']);
        $order = StoreOrder::create([
            'menu_id'          => $menu->id,
            'link_id'          => $link->id,
            'public_token'     => Str::random(40),
            'customer_name'    => 'Store Buyer',
            'customer_contact' => 'buyer@example.com',
            'total'            => 10.00,
        ]);

        $item = (new LeadAggregator($owner->id))->find(LeadAggregator::SOURCE_STORE_ORDER, $order->id);

        $this->assertNotNull($item);
        $this->assertSame('Store Buyer', $item['name']);
        $this->assertSame('buyer@example.com', $item['email']);
    }

    public function test_restaurant_order_surfaces_as_a_pending_lead(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeLink($owner, 'restaurant_menu');
        $menu  = RestaurantMenu::create(['link_id' => $link->id, 'user_id' => $owner->id, 'mode' => 'order']);
        $order = RestaurantOrder::create([
            'menu_id'       => $menu->id,
            'link_id'       => $link->id,
            'public_token'  => Str::random(40),
            'customer_name' => 'Diner Dan',
            'total'         => 25.00,
        ]);

        $item = (new LeadAggregator($owner->id))->find(LeadAggregator::SOURCE_RESTAURANT_ORDER, $order->id);

        $this->assertNotNull($item);
        $this->assertSame('Diner Dan', $item['name']);
    }

    public function test_service_booking_surfaces_as_a_pending_lead(): void
    {
        $owner   = $this->makeUser();
        $link    = $this->makeLink($owner, 'service_booking');
        $booking = ServiceBooking::create([
            'link_id'  => $link->id,
            'user_id'  => $owner->id,
            'mode'     => ServiceBooking::MODE_BOOKING,
            'currency' => 'USD',
        ]);
        $req = ServiceBookingRequest::create([
            'service_booking_id' => $booking->id,
            'link_id'            => $link->id,
            'customer_name'      => 'Booker Bob',
            'customer_email'     => 'bob@example.com',
            'customer_phone'     => '5559990000',
            'slot_start'         => now()->addDay(),
            'slot_end'           => now()->addDay()->addMinutes(30),
        ]);

        $item = (new LeadAggregator($owner->id))->find(LeadAggregator::SOURCE_SERVICE_BOOKING, $req->id);

        $this->assertNotNull($item);
        $this->assertSame('Booker Bob', $item['name']);
        $this->assertSame('bob@example.com', $item['email']);
        $this->assertSame('5559990000', $item['phone']);
    }

    public function test_review_surfaces_as_a_pending_lead(): void
    {
        $owner  = $this->makeUser();
        $link   = $this->makeLink($owner, 'reviews');
        $review = Review::create([
            'user_id'      => $owner->id,
            'link_id'      => $link->id,
            'author_name'  => 'Review Rita',
            'author_email' => 'rita@example.com',
            'rating'       => 5,
            'body'         => 'Great!',
            'is_spam'      => false,
        ]);

        $item = (new LeadAggregator($owner->id))->find(LeadAggregator::SOURCE_REVIEW, $review->id);

        $this->assertNotNull($item);
        $this->assertSame('Review Rita', $item['name']);
        $this->assertSame('rita@example.com', $item['email']);
    }

    public function test_event_interest_surfaces_as_a_pending_lead(): void
    {
        $owner    = $this->makeUser();
        $link     = $this->makeLink($owner, 'ics');
        $interest = EventInterest::create([
            'link_id'     => $link->id,
            'user_id'     => null,
            'guest_email' => 'interested@example.com',
            'status'      => EventInterest::INTERESTED,
        ]);

        $item = (new LeadAggregator($owner->id))->find(LeadAggregator::SOURCE_EVENT_INTEREST, $interest->id);

        $this->assertNotNull($item);
        $this->assertSame('interested@example.com', $item['email']);
        $this->assertSame(LeadAggregator::SOURCE_EVENT_INTEREST, $item['source_type']);
    }

    public function test_counts_by_source_tallies_all_eight_sources(): void
    {
        $owner = $this->makeUser();
        $this->seedOneLeadPerSource($owner);

        $counts = (new LeadAggregator($owner->id))->countsBySource();

        foreach (LeadAggregator::SOURCES as $source) {
            $this->assertSame(1, $counts[$source], "Expected exactly one pending lead for {$source}");
        }
        $this->assertSame(8, array_sum($counts));
    }

    public function test_only_this_owners_leads_are_visible(): void
    {
        $owner   = $this->makeUser();
        $other   = $this->makeUser();
        $link    = $this->makeLink($other, 'ics');
        $foreign = Rsvp::create([
            'link_id'  => $link->id,
            'name'     => 'Not Mine',
            'email'    => 'notmine@example.com',
            'response' => 'yes',
            'status'   => 'confirmed',
        ]);

        $this->assertNull(
            (new LeadAggregator($owner->id))->find(LeadAggregator::SOURCE_RSVP, $foreign->id),
            "Another owner's RSVP must not surface as my lead"
        );
    }

    // ── Approve: create / merge / cap ─────────────────────────────────

    public function test_approve_creates_a_new_contact_with_email_and_phone(): void
    {
        $owner = $this->makeUser();
        $item  = $this->pendingItem(['name' => 'New Person', 'email' => 'new@example.com', 'phone' => '5551112222']);

        $result = (new LeadApprover())->approve($owner, $item, $owner->id);

        $this->assertSame(LeadApprover::RESULT_CREATED, $result['result']);
        $contact = $result['contact'];
        $this->assertSame('New Person', $contact->display_name);
        $this->assertSame($owner->id, $contact->user_id);
        $this->assertSame('new@example.com', $contact->emails()->first()->value);
        $this->assertSame('5551112222', $contact->phones()->first()->value);

        // Source provenance tag recorded on the contact.
        $this->assertContains('lead:' . $item['source_type'], $contact->fresh()->sources ?? []);

        // A state row is written so the lead drops out of "pending".
        $this->assertDatabaseHas('leads', [
            'source_type' => $item['source_type'],
            'source_id'   => $item['source_id'],
            'status'      => Lead::STATUS_APPROVED,
            'contact_id'  => $contact->id,
        ]);
    }

    public function test_approve_merges_into_an_existing_contact_by_email(): void
    {
        $owner    = $this->makeUser();
        $existing = Contact::create(['user_id' => $owner->id, 'display_name' => 'Existing']);
        $existing->emails()->create(['label' => 'Other', 'value' => 'dup@example.com', 'is_primary' => true]);

        $item = $this->pendingItem(['name' => 'Dup Person', 'email' => 'dup@example.com', 'phone' => '5553334444']);

        $before  = Contact::where('user_id', $owner->id)->count();
        $result  = (new LeadApprover())->approve($owner, $item, $owner->id);
        $contact = $result['contact'];

        $this->assertSame(LeadApprover::RESULT_MERGED, $result['result']);
        $this->assertSame($existing->id, $contact->id, 'Merge must reuse the existing contact');
        $this->assertSame($before, Contact::where('user_id', $owner->id)->count(), 'Merge must not create a new contact');

        // The newly-captured phone is enriched onto the existing contact.
        $this->assertTrue(
            $contact->fresh()->phones()->where('value', '5553334444')->exists(),
            'Merge should add the new phone the existing contact lacked'
        );
    }

    public function test_plan_cap_blocks_creating_a_new_contact_but_not_a_merge(): void
    {
        $owner = $this->makeUserWithContactCap(1);

        // Fill the single-contact cap with a matching contact.
        $existing = Contact::create(['user_id' => $owner->id, 'display_name' => 'Only Contact']);
        $existing->emails()->create(['label' => 'Other', 'value' => 'match@example.com', 'is_primary' => true]);

        // A brand-new person (no dedup match) is blocked by the cap.
        $fresh = $this->pendingItem(['name' => 'Overflow', 'email' => 'overflow@example.com', 'phone' => null]);
        try {
            (new LeadApprover())->approve($owner, $fresh, $owner->id);
            $this->fail('Expected the contact cap to block a brand-new contact.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('limit', $e->getMessage());
        }
        $this->assertSame(1, Contact::where('user_id', $owner->id)->count());
        $this->assertDatabaseMissing('leads', [
            'source_type' => $fresh['source_type'],
            'source_id'   => $fresh['source_id'],
        ]);

        // A lead that dedupes into the existing contact still succeeds — the
        // cap never blocks a merge.
        $mergeable = $this->pendingItem(['name' => 'Match', 'email' => 'match@example.com', 'phone' => '5550001111']);
        $result    = (new LeadApprover())->approve($owner, $mergeable, $owner->id);

        $this->assertSame(LeadApprover::RESULT_MERGED, $result['result']);
        $this->assertSame(1, Contact::where('user_id', $owner->id)->count(), 'Merge must not grow the contact count past the cap');
    }

    // ── Dismiss ───────────────────────────────────────────────────────

    public function test_dismiss_writes_a_state_row_without_creating_a_contact(): void
    {
        $owner = $this->makeUser();
        $item  = $this->pendingItem(['name' => 'Bye', 'email' => 'bye@example.com', 'phone' => null]);

        (new LeadApprover())->dismiss($owner, $item, $owner->id);

        $this->assertSame(0, Contact::where('user_id', $owner->id)->count());
        $this->assertDatabaseHas('leads', [
            'source_type' => $item['source_type'],
            'source_id'   => $item['source_id'],
            'status'      => Lead::STATUS_DISMISSED,
            'contact_id'  => null,
        ]);
    }

    public function test_acted_on_lead_drops_out_of_the_pending_queue(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeLink($owner, 'ics');
        $rsvp  = Rsvp::create([
            'link_id'  => $link->id,
            'name'     => 'Once Pending',
            'email'    => 'once@example.com',
            'response' => 'yes',
            'status'   => 'confirmed',
        ]);

        $agg  = new LeadAggregator($owner->id);
        $item = $agg->find(LeadAggregator::SOURCE_RSVP, $rsvp->id);
        $this->assertNotNull($item);

        (new LeadApprover())->dismiss($owner, $item, $owner->id);

        // Re-query with a fresh aggregator — the item is gone, and the source
        // record itself is untouched.
        $this->assertNull((new LeadAggregator($owner->id))->find(LeadAggregator::SOURCE_RSVP, $rsvp->id));
        $this->assertDatabaseHas('rsvps', ['id' => $rsvp->id, 'name' => 'Once Pending']);
    }

    // ── Controller: AJAX index + approve/dismiss + bulk JSON ──────────

    public function test_ajax_index_returns_html_and_total_shape(): void
    {
        $owner = $this->makeUser();

        // Seed inside the owner's active-workspace context so the
        // workspace-scoped sources (form submissions, subscribers) inherit
        // the same workspace_id the request's SetActiveWorkspace middleware
        // will scope to — mirroring how these rows are created in-app.
        $ws = $owner->ensureDefaultWorkspace();
        app()->instance('workspace_owner', $owner);
        app()->instance('current_workspace', $ws);
        $this->seedOneLeadPerSource($owner);
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $resp = $this->actingAs($owner)
            ->getJson('/user/leads', ['X-Requested-With' => 'XMLHttpRequest']);

        $resp->assertOk()
            ->assertJsonStructure(['html', 'total']);
        $this->assertSame(8, $resp->json('total'));
    }

    public function test_approve_route_returns_contact_json(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeLink($owner, 'ics');
        $rsvp  = Rsvp::create([
            'link_id'  => $link->id,
            'name'     => 'Route Guest',
            'email'    => 'route@example.com',
            'response' => 'yes',
            'status'   => 'confirmed',
        ]);

        $resp = $this->actingAs($owner)->postJson('/user/leads/approve', [
            'source_type' => LeadAggregator::SOURCE_RSVP,
            'source_id'   => $rsvp->id,
        ]);

        $resp->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'message', 'contact_id', 'contact_url']);
        $this->assertDatabaseHas('contacts', ['id' => $resp->json('contact_id'), 'user_id' => $owner->id]);
    }

    public function test_approve_route_on_a_stale_lead_returns_422(): void
    {
        $owner = $this->makeUser();

        $resp = $this->actingAs($owner)->postJson('/user/leads/approve', [
            'source_type' => LeadAggregator::SOURCE_RSVP,
            'source_id'   => 999999,
        ]);

        $resp->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_dismiss_route_returns_success_json(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeLink($owner, 'ics');
        $rsvp  = Rsvp::create([
            'link_id'  => $link->id,
            'name'     => 'Dismiss Guest',
            'email'    => 'dismiss@example.com',
            'response' => 'yes',
            'status'   => 'confirmed',
        ]);

        $resp = $this->actingAs($owner)->postJson('/user/leads/dismiss', [
            'source_type' => LeadAggregator::SOURCE_RSVP,
            'source_id'   => $rsvp->id,
        ]);

        $resp->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('leads', [
            'source_type' => LeadAggregator::SOURCE_RSVP,
            'source_id'   => $rsvp->id,
            'status'      => Lead::STATUS_DISMISSED,
        ]);
    }

    public function test_bulk_approve_returns_success_and_writes_all_state_rows(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeLink($owner, 'ics');
        $a = Rsvp::create(['link_id' => $link->id, 'name' => 'Bulk A', 'email' => 'a@example.com', 'response' => 'yes', 'status' => 'confirmed']);
        $b = Rsvp::create(['link_id' => $link->id, 'name' => 'Bulk B', 'email' => 'b@example.com', 'response' => 'yes', 'status' => 'confirmed']);

        $resp = $this->actingAs($owner)->postJson('/user/leads/bulk', [
            'action' => 'approve',
            'items'  => [
                ['source_type' => LeadAggregator::SOURCE_RSVP, 'source_id' => $a->id],
                ['source_type' => LeadAggregator::SOURCE_RSVP, 'source_id' => $b->id],
            ],
        ]);

        $resp->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'message']);
        $this->assertStringContainsString('Approved 2', $resp->json('message'));
        $this->assertSame(2, Lead::where('user_id', $owner->id)->where('status', Lead::STATUS_APPROVED)->count());
        $this->assertSame(2, Contact::where('user_id', $owner->id)->count());
    }

    public function test_bulk_partial_failure_reports_success_false(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeLink($owner, 'ics');
        $a = Rsvp::create(['link_id' => $link->id, 'name' => 'Bulk A', 'email' => 'a@example.com', 'response' => 'yes', 'status' => 'confirmed']);

        $resp = $this->actingAs($owner)->postJson('/user/leads/bulk', [
            'action' => 'dismiss',
            'items'  => [
                ['source_type' => LeadAggregator::SOURCE_RSVP, 'source_id' => $a->id],
                ['source_type' => LeadAggregator::SOURCE_RSVP, 'source_id' => 987654], // stale
            ],
        ]);

        $resp->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString('1 failed', $resp->json('message'));
    }

    public function test_bulk_rejects_an_invalid_action(): void
    {
        $owner = $this->makeUser();

        $this->actingAs($owner)->postJson('/user/leads/bulk', [
            'action' => 'delete',
            'items'  => [['source_type' => LeadAggregator::SOURCE_RSVP, 'source_id' => 1]],
        ])->assertStatus(422);
    }

    // ── Fixtures ──────────────────────────────────────────────────────

    /**
     * Build an in-memory pending item shaped exactly like LeadAggregator
     * emits (the shape LeadApprover consumes). Backed by a real RSVP so the
     * (source_type, source_id) key is unique per call.
     */
    private function pendingItem(array $identity): array
    {
        $link = $this->makeLink($this->currentOwner(), 'ics');
        $rsvp  = Rsvp::create([
            'link_id'  => $link->id,
            'name'     => $identity['name'] ?? 'X',
            'email'    => $identity['email'] ?? null,
            'phone'    => $identity['phone'] ?? null,
            'response' => 'yes',
            'status'   => 'confirmed',
        ]);

        return [
            'source_type'  => LeadAggregator::SOURCE_RSVP,
            'source_id'    => $rsvp->id,
            'name'         => $identity['name'] ?? null,
            'email'        => $identity['email'] ?? null,
            'phone'        => $identity['phone'] ?? null,
            'context'      => 'Yes',
            'created_at'   => now(),
            'source_label' => 'Event RSVP',
        ];
    }

    private ?User $currentOwner = null;

    private function currentOwner(): User
    {
        return $this->currentOwner ??= $this->makeUser();
    }

    private function seedOneLeadPerSource(User $owner): void
    {
        $ics       = $this->makeLink($owner, 'ics');
        $storeLink = $this->makeLink($owner, 'store_menu');
        $restLink  = $this->makeLink($owner, 'restaurant_menu');
        $svcLink   = $this->makeLink($owner, 'service_booking');
        $revLink   = $this->makeLink($owner, 'reviews');

        Rsvp::create(['link_id' => $ics->id, 'name' => 'R', 'email' => 'r@example.com', 'response' => 'yes', 'status' => 'confirmed']);

        $form = new Form(['title' => 'F']);
        $form->user_id = $owner->id;
        $form->save();
        FormSubmission::create(['form_id' => $form->id, 'data' => ['Email' => 'f@example.com'], 'is_spam' => false]);

        Subscriber::create(['user_id' => $owner->id, 'email' => 's@example.com', 'status' => 'active', 'is_spam' => false]);

        $storeMenu = StoreMenu::create(['link_id' => $storeLink->id, 'user_id' => $owner->id, 'mode' => 'order']);
        StoreOrder::create(['menu_id' => $storeMenu->id, 'link_id' => $storeLink->id, 'public_token' => Str::random(40), 'customer_name' => 'SO', 'customer_contact' => 'so@example.com', 'total' => 5]);

        $restMenu = RestaurantMenu::create(['link_id' => $restLink->id, 'user_id' => $owner->id, 'mode' => 'order']);
        RestaurantOrder::create(['menu_id' => $restMenu->id, 'link_id' => $restLink->id, 'public_token' => Str::random(40), 'customer_name' => 'RO', 'total' => 5]);

        $booking = ServiceBooking::create(['link_id' => $svcLink->id, 'user_id' => $owner->id, 'mode' => ServiceBooking::MODE_BOOKING, 'currency' => 'USD']);
        ServiceBookingRequest::create(['service_booking_id' => $booking->id, 'link_id' => $svcLink->id, 'customer_name' => 'SB', 'customer_email' => 'sb@example.com', 'slot_start' => now()->addDay(), 'slot_end' => now()->addDay()->addMinutes(30)]);

        Review::create(['user_id' => $owner->id, 'link_id' => $revLink->id, 'author_name' => 'RV', 'author_email' => 'rv@example.com', 'rating' => 5, 'body' => 'ok', 'is_spam' => false]);

        EventInterest::create(['link_id' => $ics->id, 'guest_email' => 'ei@example.com', 'status' => EventInterest::INTERESTED]);
    }
}
