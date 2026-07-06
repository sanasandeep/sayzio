<?php

namespace Tests\Feature;

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
use App\Modules\User\Services\WorkspaceContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pins the read-side contract of the Leads review queue ({@see LeadAggregator}):
 * a person captured across any of the 8 sources shows up as "pending" until a
 * {@see Lead} state row (approved OR dismissed) exists for its
 * (source_type, source_id) pair, at which point it must disappear from the
 * queue, the per-source counts, and search — without ever mutating the source
 * record itself.
 *
 * These tests exist because the aggregator does all of its
 * filtering/exclusion/counting/search/pagination in SQL across 8 tables with
 * no automated coverage, so a future change could silently re-surface an
 * already-handled lead, mis-order the merged list, or break search. See
 * .agents/memory/leads-review-queue.md.
 */
class LeadAggregatorQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name'     => 'Owner',
            'email'    => 'owner' . Str::random(6) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        // Bind the workspace so BelongsToWorkspace-scoped sources
        // (Subscriber / Form / FormSubmission) attach + resolve correctly.
        $ws = app(WorkspaceContext::class)->resolve($this->owner);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $this->owner);
    }

    private function aggregator(): LeadAggregator
    {
        return new LeadAggregator($this->owner->id);
    }

    /** Force a deterministic created_at (not fillable) so cross-source ordering is testable. */
    private function at(Model $model, Carbon $ts): Model
    {
        $model->created_at = $ts;
        $model->save();

        return $model;
    }

    private function link(): Link
    {
        return Link::create([
            'user_id'   => $this->owner->id,
            'type'      => 'biolink',
            'alias'     => 'lk' . Str::random(8),
            'title'     => 'Event',
            'is_active' => true,
        ]);
    }

    /** Write the sparse Lead state row that marks a source item as handled. */
    private function markHandled(string $source, int $id, string $status = 'approved'): Lead
    {
        return Lead::create([
            'user_id'     => $this->owner->id,
            'source_type' => $source,
            'source_id'   => $id,
            'status'      => $status,
            ($status === 'dismissed' ? 'dismissed_at' : 'approved_at') => now(),
        ]);
    }

    private function rsvp(array $attrs = []): Rsvp
    {
        return Rsvp::create(array_merge([
            'link_id'  => $this->link()->id,
            'name'     => 'Rsvp Person',
            'email'    => 'rsvp@ex.com',
            'phone'    => '+15550000001',
            'response' => 'yes',
            'status'   => 'confirmed',
        ], $attrs));
    }

    private function subscriber(array $attrs = []): Subscriber
    {
        return Subscriber::create(array_merge([
            'user_id'       => $this->owner->id,
            'type'          => 'email',
            'email'         => 'sub@ex.com',
            'name'          => 'Sub Person',
            'status'        => 'active',
            'is_spam'       => false,
            'subscribed_at' => now(),
        ], $attrs));
    }

    private function formSubmission(array $data, array $attrs = []): FormSubmission
    {
        // user_id is guarded on Form, so set it directly rather than via mass-assignment.
        $form = new Form([
            'slug'  => 'f' . Str::random(8),
            'title' => 'Contact Form',
        ]);
        $form->user_id = $this->owner->id;
        $form->save();

        return FormSubmission::create(array_merge([
            'form_id' => $form->id,
            'data'    => $data,
            'is_spam' => false,
        ], $attrs));
    }

    private function storeOrder(array $attrs = []): StoreOrder
    {
        $link = $this->link();
        $menu = StoreMenu::create([
            'link_id'  => $link->id,
            'user_id'  => $this->owner->id,
            'mode'     => 'order',
            'currency' => 'USD',
        ]);

        return StoreOrder::create(array_merge([
            'menu_id'          => $menu->id,
            'link_id'          => $link->id,
            'status'           => 'new',
            'customer_name'    => 'Store Buyer',
            'customer_contact' => 'buyer@ex.com',
        ], $attrs));
    }

    private function restaurantOrder(array $attrs = []): RestaurantOrder
    {
        $link = $this->link();
        $menu = RestaurantMenu::create([
            'link_id'  => $link->id,
            'user_id'  => $this->owner->id,
            'mode'     => 'order',
            'currency' => 'USD',
        ]);

        return RestaurantOrder::create(array_merge([
            'menu_id'       => $menu->id,
            'link_id'       => $link->id,
            'status'        => 'new',
            'customer_name' => 'Diner',
        ], $attrs));
    }

    private function serviceBooking(array $attrs = []): ServiceBookingRequest
    {
        $link = $this->link();
        $booking = ServiceBooking::create([
            'link_id'  => $link->id,
            'user_id'  => $this->owner->id,
            'mode'     => 'booking',
            'currency' => 'USD',
        ]);

        return ServiceBookingRequest::create(array_merge([
            'service_booking_id' => $booking->id,
            'link_id'            => $link->id,
            'status'             => 'pending',
            'customer_name'      => 'Booker',
            'customer_email'     => 'booker@ex.com',
            'customer_phone'     => '+15550000002',
            'slot_start'         => now()->addDay(),
            'slot_end'           => now()->addDay()->addHour(),
        ], $attrs));
    }

    private function review(array $attrs = []): Review
    {
        return Review::create(array_merge([
            'user_id'      => $this->owner->id,
            'author_name'  => 'Reviewer',
            'author_email' => 'reviewer@ex.com',
            'rating'       => 5,
            'body'         => 'Great!',
            'status'       => 'approved',
            'is_spam'      => false,
        ], $attrs));
    }

    private function eventInterest(array $attrs = []): EventInterest
    {
        return EventInterest::create(array_merge([
            'link_id'     => $this->link()->id,
            'guest_email' => 'interested@ex.com',
            'status'      => EventInterest::INTERESTED,
        ], $attrs));
    }

    /** @return list<array{string,int}> [source_type, source_id] pairs of the paginated page. */
    private function pageKeys(array $filters = []): array
    {
        return collect($this->aggregator()->paginate($filters, 50, 1)->items())
            ->map(fn ($r) => [$r['source_type'], $r['source_id']])
            ->all();
    }

    public function test_pending_items_from_every_source_surface_in_the_queue(): void
    {
        $this->rsvp();
        $this->subscriber();
        $this->formSubmission(['name' => 'Formy', 'email' => 'formy@ex.com']);
        $this->storeOrder();
        $this->restaurantOrder();
        $this->serviceBooking();
        $this->review();
        $this->eventInterest();

        $counts = $this->aggregator()->countsBySource();

        foreach (LeadAggregator::SOURCES as $source) {
            $this->assertSame(1, $counts[$source], "expected one pending {$source}");
        }
        $this->assertSame(8, $this->aggregator()->pendingCount());
    }

    public function test_approved_and_dismissed_items_are_excluded(): void
    {
        $approvedSub  = $this->subscriber(['email' => 'gone-approved@ex.com']);
        $dismissedRsvp = $this->rsvp(['email' => 'gone-dismissed@ex.com']);
        $pendingReview = $this->review(['author_email' => 'still-here@ex.com']);

        $this->markHandled(LeadAggregator::SOURCE_SUBSCRIBER, $approvedSub->id, 'approved');
        $this->markHandled(LeadAggregator::SOURCE_RSVP, $dismissedRsvp->id, 'dismissed');

        $counts = $this->aggregator()->countsBySource();
        $this->assertSame(0, $counts[LeadAggregator::SOURCE_SUBSCRIBER]);
        $this->assertSame(0, $counts[LeadAggregator::SOURCE_RSVP]);
        $this->assertSame(1, $counts[LeadAggregator::SOURCE_REVIEW]);
        $this->assertSame(1, $this->aggregator()->pendingCount());

        // The handled source rows themselves are untouched — only the queue view drops them.
        $this->assertDatabaseHas('subscribers', ['id' => $approvedSub->id]);
        $this->assertDatabaseHas('rsvps', ['id' => $dismissedRsvp->id]);

        $keys = $this->pageKeys();
        $this->assertSame([[LeadAggregator::SOURCE_REVIEW, $pendingReview->id]], $keys);

        // A handled item can no longer be resolved as a pending lead either.
        $this->assertNull($this->aggregator()->find(LeadAggregator::SOURCE_SUBSCRIBER, $approvedSub->id));
        $this->assertNotNull($this->aggregator()->find(LeadAggregator::SOURCE_REVIEW, $pendingReview->id));
    }

    public function test_paginated_list_is_created_at_desc_across_sources(): void
    {
        $base = now()->subDays(2);

        // Interleave sources so a per-source-then-merge bug would mis-order.
        $oldest  = $this->at($this->rsvp(['email' => 'o@ex.com']), (clone $base));
        // Subscriber's queue timestamp is subscribed_at (falls back to created_at), so pin both.
        $mid1    = $this->at($this->subscriber(['email' => 'm1@ex.com', 'subscribed_at' => (clone $base)->addHours(2)]), (clone $base)->addHours(2));
        $mid2    = $this->at($this->review(['author_email' => 'm2@ex.com']), (clone $base)->addHours(4));
        $newest  = $this->at($this->storeOrder(), (clone $base)->addHours(6));

        $keys = $this->pageKeys();

        $this->assertSame([
            [LeadAggregator::SOURCE_STORE_ORDER, $newest->id],
            [LeadAggregator::SOURCE_REVIEW, $mid2->id],
            [LeadAggregator::SOURCE_SUBSCRIBER, $mid1->id],
            [LeadAggregator::SOURCE_RSVP, $oldest->id],
        ], $keys);
    }

    public function test_source_filter_returns_only_that_source(): void
    {
        $sub = $this->subscriber(['email' => 'only@ex.com']);
        $this->rsvp();
        $this->review();

        $keys = $this->pageKeys(['source' => LeadAggregator::SOURCE_SUBSCRIBER]);

        $this->assertSame([[LeadAggregator::SOURCE_SUBSCRIBER, $sub->id]], $keys);
    }

    public function test_search_matches_identity_fields_but_not_handled_rows(): void
    {
        $match   = $this->subscriber(['name' => 'Aurora Nightingale', 'email' => 'aurora@ex.com']);
        $byPhone = $this->rsvp(['name' => 'PhoneOnly', 'email' => 'p@ex.com', 'phone' => '+15559998888']);
        $this->subscriber(['name' => 'Someone Else', 'email' => 'else@ex.com']);

        // name match
        $this->assertSame(
            [[LeadAggregator::SOURCE_SUBSCRIBER, $match->id]],
            $this->pageKeys(['q' => 'aurora'])
        );
        // phone match
        $this->assertSame(
            [[LeadAggregator::SOURCE_RSVP, $byPhone->id]],
            $this->pageKeys(['q' => '9998888'])
        );

        // Once handled, the row is gone from search too.
        $this->markHandled(LeadAggregator::SOURCE_SUBSCRIBER, $match->id);
        $this->assertSame([], $this->pageKeys(['q' => 'aurora']));
    }

    public function test_search_matches_form_json_payload(): void
    {
        $sub = $this->formSubmission([
            'Full Name' => 'Zephyr Quill',
            'Email'     => 'zephyr@ex.com',
            'Message'   => 'I need a pangolin consultation',
        ]);
        $this->formSubmission(['Full Name' => 'Nobody', 'Email' => 'nobody@ex.com']);

        // A word that only lives inside the JSON payload still matches.
        $this->assertSame(
            [[LeadAggregator::SOURCE_FORM, $sub->id]],
            $this->pageKeys(['q' => 'pangolin'])
        );

        // Handling it removes it from JSON search results as well.
        $this->markHandled(LeadAggregator::SOURCE_FORM, $sub->id, 'dismissed');
        $this->assertSame([], $this->pageKeys(['q' => 'pangolin']));
    }

    public function test_leads_are_scoped_to_the_owner(): void
    {
        $mine = $this->review(['author_email' => 'mine@ex.com']);

        // Another user's captured people must never leak into this queue.
        $other = User::create([
            'name' => 'Other', 'email' => 'other' . Str::random(6) . '@ex.com',
            'password' => Hash::make('x'), 'status' => 'active',
        ]);
        Review::create([
            'user_id' => $other->id, 'author_name' => 'Theirs',
            'author_email' => 'theirs@ex.com', 'rating' => 4, 'body' => 'ok',
            'status' => 'approved', 'is_spam' => false,
        ]);

        $keys = $this->pageKeys();
        $this->assertSame([[LeadAggregator::SOURCE_REVIEW, $mine->id]], $keys);
        $this->assertSame(1, $this->aggregator()->countsBySource()[LeadAggregator::SOURCE_REVIEW]);
    }

    public function test_index_route_renders_counts_and_filter_chips(): void
    {
        $this->rsvp();
        $this->subscriber();
        $this->review();

        $this->actingAs($this->owner, 'web')
            ->get(route('user.leads.index'))
            ->assertOk()
            ->assertViewHas('totalPending', 3)
            ->assertViewHas('counts', fn ($counts) => $counts[LeadAggregator::SOURCE_RSVP] === 1
                && $counts[LeadAggregator::SOURCE_SUBSCRIBER] === 1
                && $counts[LeadAggregator::SOURCE_REVIEW] === 1
                && $counts[LeadAggregator::SOURCE_STORE_ORDER] === 0);
    }
}
