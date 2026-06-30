<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Badge-gating for admin-global domains + team-aware availability.
 *
 * Global domains are offered when the account (the active workspace OWNER)
 * matches ANY tagged plan OR ANY tagged badge; untagged ones stay open to
 * everyone. A team member acting inside the owner's workspace inherits the
 * owner's entitled globals plus the WORKSPACE's custom domains. Downgrades
 * hide custom domains but never delete the rows.
 */
class DomainBadgeGatingTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(?int $planId = null): User
    {
        $user = User::create([
            'name'     => 'DG ' . Str::random(4),
            'email'    => 'dg-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $planId,
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function planWith(array $features): Plan
    {
        return Plan::create([
            'name'          => 'Plan ' . Str::random(4),
            'slug'          => 'plan-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => $features,
        ]);
    }

    private function globalDomain(string $host): Domain
    {
        return Domain::create([
            'user_id'     => null,
            'domain'      => $host,
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);
    }

    /** Bind the active workspace + owner like SetActiveWorkspace does. */
    private function actingInWorkspace(Workspace $ws, User $owner): void
    {
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $owner);
    }

    private function clearWorkspaceBinding(): void
    {
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
    }

    public function test_untagged_global_domain_is_open_to_everyone(): void
    {
        $user = $this->makeUser();
        $open = $this->globalDomain('open.test');

        $ids = Domain::availableTo($user)->pluck('id')->all();
        $this->assertContains($open->id, $ids);
    }

    public function test_badge_tagged_domain_only_available_to_badge_holders(): void
    {
        $holder    = $this->makeUser();
        $outsider  = $this->makeUser();
        $badge     = AccountBadge::create(['name' => 'VIP ' . Str::random(3), 'color' => '#3b82f6']);
        $holder->accountBadges()->attach($badge->id);

        $vip = $this->globalDomain('vip.test');
        $vip->badges()->sync([$badge->id]);

        $holderIds   = Domain::availableTo($holder->fresh())->pluck('id')->all();
        $outsiderIds = Domain::availableTo($outsider->fresh())->pluck('id')->all();

        $this->assertContains($vip->id, $holderIds);
        $this->assertNotContains($vip->id, $outsiderIds);
    }

    public function test_plan_or_badge_match_unlocks_a_domain(): void
    {
        $plan  = $this->planWith([]);
        $badge = AccountBadge::create(['name' => 'Beta ' . Str::random(3), 'color' => '#3b82f6']);

        // Domain tagged with BOTH a plan and a badge.
        $dom = $this->globalDomain('mix.test');
        $dom->plans()->sync([$plan->id]);
        $dom->badges()->sync([$badge->id]);

        // A user on the plan (no badge) gets it.
        $byPlan = $this->makeUser($plan->id);
        $this->assertContains($dom->id, Domain::availableTo($byPlan->fresh())->pluck('id')->all());

        // A user with the badge (no plan) gets it.
        $byBadge = $this->makeUser();
        $byBadge->accountBadges()->attach($badge->id);
        $this->assertContains($dom->id, Domain::availableTo($byBadge->fresh())->pluck('id')->all());

        // A user with neither does not.
        $neither = $this->makeUser();
        $this->assertNotContains($dom->id, Domain::availableTo($neither->fresh())->pluck('id')->all());
    }

    public function test_team_member_inherits_owner_globals_and_workspace_custom_domains(): void
    {
        $plan  = $this->planWith(['custom_domains' => true]);
        $badge = AccountBadge::create(['name' => 'Team ' . Str::random(3), 'color' => '#3b82f6']);

        $owner = $this->makeUser($plan->id);
        $owner->accountBadges()->attach($badge->id);
        $team  = $owner->ownedWorkspaces()->create([
            'name' => 'Team WS', 'slug' => 'team-' . Str::random(5), 'is_personal' => false,
        ]);

        // A member with no plan/badge of their own.
        $member = $this->makeUser();
        WorkspaceMember::create([
            'workspace_id' => $team->id,
            'user_id'      => $member->id,
            'role'         => 'member',
        ]);

        // A badge-gated global domain the OWNER (not the member) qualifies for.
        $badged = $this->globalDomain('teambadge.test');
        $badged->badges()->sync([$badge->id]);

        // A custom domain owned by the TEAM workspace.
        $custom = Domain::create([
            'user_id'     => $owner->id,
            'domain'      => 'team-custom.test',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);
        $custom->workspace_id = $team->id;
        $custom->save();

        $this->actingInWorkspace($team, $owner);
        try {
            $ids = Domain::availableTo($member->fresh())->pluck('id')->all();
        } finally {
            $this->clearWorkspaceBinding();
        }

        $this->assertContains($badged->id, $ids, 'member should inherit owner badge-gated global');
        $this->assertContains($custom->id, $ids, 'member should see workspace custom domain');
    }

    public function test_downgrade_hides_custom_domain_but_keeps_the_row(): void
    {
        $freePlan = $this->planWith([]); // no custom_domains feature
        $user = $this->makeUser($freePlan->id);
        $ws   = $user->ownedWorkspaces()->first();

        $custom = Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'mine.test',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);
        $custom->workspace_id = $ws->id;
        $custom->save();

        $this->actingInWorkspace($ws, $user);
        try {
            $ids = Domain::availableTo($user->fresh())->pluck('id')->all();
        } finally {
            $this->clearWorkspaceBinding();
        }

        $this->assertNotContains($custom->id, $ids, 'downgraded user cannot attach their custom domain');
        // The row is never deleted — an upgrade restores access.
        $this->assertDatabaseHas('domains', ['id' => $custom->id]);
    }
}
