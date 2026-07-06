<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Services\LeadApprover;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the bulk-approve plan-cap warning on the Leads queue.
 *
 * A creator selecting "approve all" should be told up-front how many of the
 * selected leads can actually be approved under their plan's contact cap
 * (the dry-run preview), and the actual bulk response should break results
 * into created / merged / blocked buckets instead of one opaque "failed".
 */
class LeadBulkApproveCapTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = []): Plan
    {
        $slug = 'p' . Str::random(6);
        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan?->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function subscriber(User $u, string $email): Subscriber
    {
        return Subscriber::create([
            'user_id'       => $u->id,
            'type'          => 'email',
            'email'         => $email,
            'name'          => 'Lead ' . Str::random(3),
            'status'        => 'active',
            'is_spam'       => false,
            'subscribed_at' => now(),
        ]);
    }

    /** @return array<int,array{source_type:string,source_id:int}> */
    private function items(array $subs): array
    {
        return array_map(fn (Subscriber $s) => [
            'source_type' => 'subscriber',
            'source_id'   => $s->id,
        ], $subs);
    }

    public function test_plan_batch_reports_blocked_over_cap(): void
    {
        // Cap of 1 contact, none used yet: only 1 of 3 fresh leads fits.
        $owner = $this->user($this->plan(['contacts_max' => 1]));
        $subs = [
            $this->subscriber($owner, 'a@ex.com'),
            $this->subscriber($owner, 'b@ex.com'),
            $this->subscriber($owner, 'c@ex.com'),
        ];

        $plan = app(LeadApprover::class)->planBatch($owner, $this->items($subs));

        $this->assertSame(3, $plan['total']);
        $this->assertSame(1, $plan['created']);
        $this->assertSame(0, $plan['merged']);
        $this->assertSame(2, $plan['blocked']);
        $this->assertSame(1, $plan['approvable']);
        $this->assertSame(1, $plan['cap']);
    }

    public function test_plan_batch_merges_do_not_count_against_cap(): void
    {
        // Cap of 1, already at the cap via an existing contact that one of the
        // leads dedupes into. The merge should still be approvable; the other
        // two fresh leads are blocked.
        $owner = $this->user($this->plan(['contacts_max' => 1]));
        $contact = Contact::create([
            'user_id'      => $owner->id,
            'display_name' => 'Existing',
        ]);
        $contact->emails()->create(['label' => 'Other', 'value' => 'dup@ex.com', 'is_primary' => true]);

        $subs = [
            $this->subscriber($owner, 'dup@ex.com'), // merges into existing
            $this->subscriber($owner, 'new1@ex.com'),
            $this->subscriber($owner, 'new2@ex.com'),
        ];

        $plan = app(LeadApprover::class)->planBatch($owner, $this->items($subs));

        $this->assertSame(0, $plan['created']);
        $this->assertSame(1, $plan['merged']);
        $this->assertSame(2, $plan['blocked']);
        $this->assertSame(1, $plan['approvable']);
    }

    public function test_bulk_preview_endpoint_returns_counts(): void
    {
        $owner = $this->user($this->plan(['contacts_max' => 1]));
        $subs = [
            $this->subscriber($owner, 'x@ex.com'),
            $this->subscriber($owner, 'y@ex.com'),
        ];

        $this->actingAs($owner, 'web')
            ->postJson(route('user.leads.bulk-preview'), ['items' => $this->items($subs)])
            ->assertOk()
            ->assertJson([
                'success'    => true,
                'total'      => 2,
                'created'    => 1,
                'blocked'    => 1,
                'approvable' => 1,
                'cap'        => 1,
            ]);
    }

    public function test_bulk_approve_separates_created_and_blocked_counts(): void
    {
        $owner = $this->user($this->plan(['contacts_max' => 1]));
        $subs = [
            $this->subscriber($owner, 'one@ex.com'),
            $this->subscriber($owner, 'two@ex.com'),
        ];

        $resp = $this->actingAs($owner, 'web')
            ->postJson(route('user.leads.bulk'), [
                'action' => 'approve',
                'items'  => $this->items($subs),
            ]);

        // Blocked items make the batch a 422 (not fully successful) but the
        // successful ones are still committed.
        $resp->assertStatus(422)->assertJson([
            'created' => 1,
            'merged'  => 0,
            'blocked' => 1,
            'gone'    => 0,
        ]);

        $this->assertSame(1, Contact::where('user_id', $owner->id)->count());
    }

    public function test_bulk_approve_all_fit_is_successful(): void
    {
        $owner = $this->user($this->plan(['contacts_max' => 100]));
        $subs = [
            $this->subscriber($owner, 'p@ex.com'),
            $this->subscriber($owner, 'q@ex.com'),
        ];

        $this->actingAs($owner, 'web')
            ->postJson(route('user.leads.bulk'), [
                'action' => 'approve',
                'items'  => $this->items($subs),
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'created' => 2, 'blocked' => 0]);

        $this->assertSame(2, Contact::where('user_id', $owner->id)->count());
    }
}
