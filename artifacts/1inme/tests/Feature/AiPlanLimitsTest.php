<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiPlanAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the 8 first-class per-plan AI features (Task #2211):
 *  - quantity caps: AI Minds / Personas / Companions (-1 = unlimited)
 *  - availability bools: Site Assistant, Voice Assistant, Ask Coach,
 *    Card & Brochure Scanner, AI Resume Tools
 *
 * Exercises the central AiPlanAccess helper, the seeder's per-plan values,
 * the additive fallback when a plan predates the keys, and the creation
 * chokepoints (CheckPlanLimit middleware) for the quantity features.
 */
class AiPlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = [], ?string $slug = null): Plan
    {
        $slug = $slug ?: ('p' . Str::random(6));
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

    // ===== Helper: quantity caps =====

    public function test_quantity_cap_reads_per_plan_value(): void
    {
        $u = $this->user($this->plan(['max_minds' => 3, 'max_personas' => 3, 'max_companions' => 2]));
        $this->assertSame(3, AiPlanAccess::quantityCap($u, 'minds'));
        $this->assertSame(3, AiPlanAccess::quantityCap($u, 'personas'));
        $this->assertSame(2, AiPlanAccess::quantityCap($u, 'companions'));
    }

    public function test_under_quantity_cap_honors_finite_and_unlimited(): void
    {
        $finite = $this->user($this->plan(['max_minds' => 2]));
        $this->assertTrue(AiPlanAccess::underQuantityCap($finite, 'minds', 0));
        $this->assertTrue(AiPlanAccess::underQuantityCap($finite, 'minds', 1));
        $this->assertFalse(AiPlanAccess::underQuantityCap($finite, 'minds', 2));

        $unlimited = $this->user($this->plan(['max_companions' => -1]));
        $this->assertTrue(AiPlanAccess::underQuantityCap($unlimited, 'companions', 999_999));
    }

    // ===== Helper: availability bools =====

    public function test_availability_feature_reads_per_plan_flag(): void
    {
        $off = $this->user($this->plan([
            'ask_coach' => false, 'card_scan' => false, 'ai_resume_tools' => false,
        ]));
        $this->assertFalse(AiPlanAccess::featureAllowed($off, 'ask_coach'));
        $this->assertFalse(AiPlanAccess::featureAllowed($off, 'card_scan'));
        $this->assertFalse(AiPlanAccess::featureAllowed($off, 'ai_resume_tools'));

        $on = $this->user($this->plan([
            'ask_coach' => true, 'card_scan' => true, 'ai_resume_tools' => true,
        ]));
        $this->assertTrue(AiPlanAccess::featureAllowed($on, 'ask_coach'));
        $this->assertTrue(AiPlanAccess::featureAllowed($on, 'card_scan'));
        $this->assertTrue(AiPlanAccess::featureAllowed($on, 'ai_resume_tools'));
    }

    public function test_availability_falls_back_open_when_plan_predates_key(): void
    {
        // A plan with NO AI availability keys at all must keep the legacy
        // behaviour (card_scan / ai_resume_tools were always-on) so an
        // un-backfilled plan never regresses mid-rollout.
        $u = $this->user($this->plan(['max_links' => 10]));
        $this->assertTrue(AiPlanAccess::featureAllowed($u, 'card_scan'));
        $this->assertTrue(AiPlanAccess::featureAllowed($u, 'ai_resume_tools'));
    }

    // ===== Route gating: quantity chokepoints =====

    public function test_minds_create_blocked_when_at_cap(): void
    {
        $u = $this->user($this->plan(['max_minds' => 1]));
        AiMind::create(['user_id' => $u->id, 'name' => 'first']);
        $resp = $this->actingAs($u)->get('/user/minds/create');
        $resp->assertSessionHas('error');
    }

    public function test_minds_create_allowed_when_under_cap(): void
    {
        $u = $this->user($this->plan(['max_minds' => 3]));
        $resp = $this->actingAs($u)->get('/user/minds/create');
        $err = session('error');
        $this->assertTrue($err === null || !str_contains((string) $err, 'limit'), 'Quantity gate unexpectedly triggered: ' . (string) $err);
    }

    public function test_minds_create_allowed_when_unlimited(): void
    {
        $u = $this->user($this->plan(['max_minds' => -1]));
        for ($i = 0; $i < 5; $i++) {
            AiMind::create(['user_id' => $u->id, 'name' => 'm' . $i]);
        }
        $resp = $this->actingAs($u)->get('/user/minds/create');
        $err = session('error');
        $this->assertTrue($err === null || !str_contains((string) $err, 'limit'), 'Unlimited cap was treated as a limit: ' . (string) $err);
    }

    public function test_limit_message_names_target_plan(): void
    {
        $this->artisan('db:seed', ['--class' => \Database\Seeders\PlansAndAddonsSeeder::class])->assertSuccessful();
        $free = Plan::where('slug', 'free')->first();
        $this->assertNotNull($free);
        $u = $this->user($free);
        AiMind::create(['user_id' => $u->id, 'name' => 'only']);
        $resp = $this->actingAs($u)->get('/user/minds/create');
        $msg = (string) session('error');
        $this->assertNotEmpty($msg);
        $this->assertStringContainsString('Upgrade to the', $msg, 'Rejection message did not name the target plan');
    }

    // ===== Seeder: per-plan AI values =====

    public function test_seeder_sets_per_plan_ai_limits(): void
    {
        $this->artisan('db:seed', ['--class' => \Database\Seeders\PlansAndAddonsSeeder::class])->assertSuccessful();

        $free = Plan::where('slug', 'free')->first();
        $this->assertNotNull($free);
        $this->assertSame(1, (int) ($free->features['max_minds'] ?? null));
        $this->assertSame(1, (int) ($free->features['max_personas'] ?? null));
        $this->assertSame(1, (int) ($free->features['max_companions'] ?? null));
        $this->assertFalse((bool) ($free->features['ask_coach'] ?? true));
        $this->assertFalse((bool) ($free->features['card_scan'] ?? true));
        $this->assertFalse((bool) ($free->features['ai_resume_tools'] ?? true));

        $business = Plan::where('slug', 'business')->first();
        $this->assertNotNull($business);
        $this->assertSame(-1, (int) ($business->features['max_minds'] ?? null));
        $this->assertSame(-1, (int) ($business->features['max_personas'] ?? null));
        $this->assertSame(-1, (int) ($business->features['max_companions'] ?? null));
        $this->assertTrue((bool) ($business->features['ask_coach'] ?? false));
        $this->assertTrue((bool) ($business->features['card_scan'] ?? false));
        $this->assertTrue((bool) ($business->features['ai_resume_tools'] ?? false));
        $this->assertTrue((bool) ($business->features['ai_voice_assistant'] ?? false));
    }

    public function test_seeder_preserves_curator_ai_edits(): void
    {
        $this->artisan('db:seed', ['--class' => \Database\Seeders\PlansAndAddonsSeeder::class])->assertSuccessful();
        $free = Plan::where('slug', 'free')->first();
        $features = $free->features;
        $features['max_minds'] = 42;
        $free->features = $features;
        $free->save();

        $this->artisan('db:seed', ['--class' => \Database\Seeders\PlansAndAddonsSeeder::class])->assertSuccessful();
        $free->refresh();
        $this->assertSame(42, (int) $free->features['max_minds'], 'Seeder clobbered curator AI edit');
    }
}
