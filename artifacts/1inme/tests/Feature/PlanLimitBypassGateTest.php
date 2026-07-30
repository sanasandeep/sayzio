<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression guard for the CheckPlanLimit admin-bypass contract:
 * holders of the `user.plan_limits.bypass` permission bypass ALL plan
 * gating (the same contract User::getPlanFeature documents), so the
 * CheckPlanLimit middleware must let them through every gate — even on
 * a free plan that explicitly disables the feature — while a plain
 * free-plan user is still rejected by the same gate.
 */
class PlanLimitBypassGateTest extends TestCase
{
    use RefreshDatabase;

    /** Free-ish plan that explicitly disables buzz popups and custom domains. */
    private function makeGatedPlan(): Plan
    {
        $slug = 'p' . Str::lower(Str::random(6));

        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => [
                'buzz_popups'    => false,
                'max_buzz_items' => 0,
                'custom_domains' => false,
            ],
        ]);
    }

    private function makeUser(Plan $plan): User
    {
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'plan_id'      => $plan->id,
            'onboarded_at' => now(),
        ]);
    }

    private function grantBypass(User $user): User
    {
        $role = Role::create([
            'name'  => 'Bypass ' . Str::random(4),
            'slug'  => 'r-' . Str::lower(Str::random(8)),
            'guard' => 'web',
        ]);
        $perm = Permission::firstOrCreate(
            ['slug' => 'user.plan_limits.bypass'],
            ['name' => 'Bypass plan limits', 'group' => 'user']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->flushPermissionCache();

        return $user->fresh();
    }

    /** Bind the user's workspace so workspace.can-gated routes resolve. */
    private function bindWorkspace(User $user): void
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
    }

    public function test_bypass_user_passes_the_buzz_popups_gate_on_a_free_plan(): void
    {
        $plan = $this->makeGatedPlan();
        $user = $this->grantBypass($this->makeUser($plan));
        $this->bindWorkspace($user);

        $res = $this->actingAs($user, 'web')
            ->post('/user/social-proofs', ['name' => 'Bypass campaign']);

        $proof = SocialProof::where('user_id', $user->id)->first();
        $this->assertNotNull(
            $proof,
            'A user.plan_limits.bypass holder must pass the CheckPlanLimit:buzz_popups ' .
            'gate even when the plan disables buzz popups — the campaign was not created.'
        );
        $res->assertRedirect(route('user.social-proofs.edit', $proof));
        $res->assertSessionHas('success');
        $res->assertSessionMissing('error');
    }

    public function test_plain_free_plan_user_is_still_rejected_by_the_same_gate(): void
    {
        $plan = $this->makeGatedPlan();
        $user = $this->makeUser($plan);
        $this->bindWorkspace($user);

        $res = $this->actingAs($user, 'web')
            ->from('/user/social-proofs')
            ->post('/user/social-proofs', ['name' => 'Gated campaign']);

        $res->assertSessionHas('error');
        $this->assertSame(
            0,
            SocialProof::where('user_id', $user->id)->count(),
            'A free-plan user without the bypass permission must remain gated.'
        );
    }

    public function test_bypass_user_passes_the_custom_domains_gate(): void
    {
        $plan = $this->makeGatedPlan();
        $user = $this->grantBypass($this->makeUser($plan));
        $this->bindWorkspace($user);

        $res = $this->actingAs($user, 'web')->get('/user/settings/domains');
        $res->assertOk();
        $res->assertSessionMissing('error');
    }

    public function test_plain_user_is_rejected_by_the_custom_domains_gate(): void
    {
        $plan = $this->makeGatedPlan();
        $user = $this->makeUser($plan);
        $this->bindWorkspace($user);

        $res = $this->actingAs($user, 'web')
            ->from('/user/dashboard')
            ->get('/user/settings/domains');

        $res->assertRedirect('/user/dashboard');
        $res->assertSessionHas('error');
    }
}
