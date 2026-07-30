<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Modules\User\Support\SettingsTabs;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6143 — paid users pick their own chat model per AI feature.
 *
 * Covers the resolution precedence (user override → admin feature_models
 * → gpt-4o-mini default), the free-plan / disabled-model / non-chat
 * fallbacks, the web Settings → AI Models tab (gate + save + reset) and
 * the /api/v1/me/ai-models REST parity endpoints.
 */
class UserAiModelChoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AiEngineSettings::setModels([
            ['name' => 'gpt-4o-mini', 'kind' => 'chat', 'enabled' => true,  'in_coins_per_1k' => 1,  'out_coins_per_1k' => 2],
            ['name' => 'gpt-4o',      'kind' => 'chat', 'enabled' => true,  'in_coins_per_1k' => 5,  'out_coins_per_1k' => 10],
            ['name' => 'gpt-4-slow',  'kind' => 'chat', 'enabled' => false, 'in_coins_per_1k' => 9,  'out_coins_per_1k' => 9],
            ['name' => 'embed-3',     'kind' => 'embedding', 'enabled' => true, 'in_coins_per_1k' => 1, 'out_coins_per_1k' => 0],
        ]);
    }

    protected function makePlan(bool $free): Plan
    {
        return Plan::create([
            'name'   => ($free ? 'Free' : 'Paid') . ' ' . Str::random(4),
            'slug'   => ($free ? 'free-' : 'paid-') . Str::lower(Str::random(8)),
            'status' => true,
        ]);
    }

    protected function makeUser(bool $free = false): User
    {
        $plan = $this->makePlan($free);
        $user = User::factory()->create(['plan_id' => $free ? null : $plan->id])->fresh();
        if ($free) {
            $this->assertTrue($user->isOnFreePlan());
        } else {
            $this->assertFalse($user->isOnFreePlan());
        }

        return $user;
    }

    // ── Resolution precedence ────────────────────────────────────────

    public function test_paid_user_override_wins_over_admin_default(): void
    {
        AiEngineSettings::setFeatureModels(['companion' => 'gpt-4o-mini']);
        $user = $this->makeUser();
        AiEngineSettings::setUserFeatureModels($user, ['companion' => 'gpt-4o']);

        $this->assertSame('gpt-4o', AiEngineSettings::featureModel('companion', $user->fresh()));
        // Features without an override still follow the admin map / default.
        $this->assertSame('gpt-4o-mini', AiEngineSettings::featureModel('coach', $user->fresh()));
    }

    public function test_free_user_override_is_ignored(): void
    {
        $user = $this->makeUser();
        AiEngineSettings::setUserFeatureModels($user, ['companion' => 'gpt-4o']);

        // Simulate a downgrade to free: stored choice survives but is ignored.
        $user->plan_id = null;
        $user->save();
        $user = $user->fresh();
        $this->assertTrue($user->isOnFreePlan());
        $this->assertSame('gpt-4o', AiEngineSettings::userFeatureModels($user)['companion'] ?? null);
        $this->assertSame('gpt-4o-mini', AiEngineSettings::featureModel('companion', $user));
    }

    public function test_disabled_or_removed_model_falls_back_silently(): void
    {
        $user = $this->makeUser();
        AiEngineSettings::setUserFeatureModels($user, ['companion' => 'gpt-4o']);

        // Admin later disables the chosen model.
        AiEngineSettings::setModels([
            ['name' => 'gpt-4o-mini', 'kind' => 'chat', 'enabled' => true, 'in_coins_per_1k' => 1, 'out_coins_per_1k' => 2],
            ['name' => 'gpt-4o',      'kind' => 'chat', 'enabled' => false, 'in_coins_per_1k' => 5, 'out_coins_per_1k' => 10],
        ]);
        $this->assertSame('gpt-4o-mini', AiEngineSettings::featureModel('companion', $user->fresh()));

        // Admin removes it from the catalog entirely.
        AiEngineSettings::setModels([
            ['name' => 'gpt-4o-mini', 'kind' => 'chat', 'enabled' => true, 'in_coins_per_1k' => 1, 'out_coins_per_1k' => 2],
        ]);
        $this->assertSame('gpt-4o-mini', AiEngineSettings::featureModel('companion', $user->fresh()));
    }

    public function test_non_chat_model_cannot_be_chosen(): void
    {
        $user = $this->makeUser();

        $this->expectException(\InvalidArgumentException::class);
        AiEngineSettings::setUserFeatureModels($user, ['companion' => 'embed-3']);
    }

    public function test_selectable_chat_models_excludes_disabled_and_embeddings(): void
    {
        $names = array_column(AiEngineSettings::selectableChatModels(), 'name');
        $this->assertSame(['gpt-4o-mini', 'gpt-4o'], $names);
    }

    // ── Web settings tab ─────────────────────────────────────────────

    public function test_settings_tab_visible_only_for_paid_users(): void
    {
        $paid = $this->makeUser();
        $this->be($paid, 'web');
        $this->assertArrayHasKey('ai-models', SettingsTabs::visibleTabs());

        $free = $this->makeUser(true);
        $this->be($free, 'web');
        $this->assertArrayNotHasKey('ai-models', SettingsTabs::visibleTabs());
    }

    public function test_web_page_renders_models_and_rates_for_paid_user(): void
    {
        $user = $this->makeUser();

        $res = $this->actingAs($user, 'web')->get('/user/settings/ai-models');
        $res->assertOk();
        $res->assertSee('AI Models');
        $res->assertSee('gpt-4o');
        $res->assertSee('Platform default');
    }

    public function test_web_page_shows_upgrade_prompt_for_free_user(): void
    {
        $user = $this->makeUser(true);

        $res = $this->actingAs($user, 'web')->get('/user/settings/ai-models');
        $res->assertOk();
        $res->assertSee('paid perk');
        $res->assertDontSee('Save model choices');
    }

    public function test_web_update_saves_and_reset_clears(): void
    {
        $user = $this->makeUser();

        $res = $this->actingAs($user, 'web')->put('/user/settings/ai-models', [
            'feature_models' => ['companion' => 'gpt-4o', 'coach' => ''],
        ]);
        $res->assertRedirect();
        $this->assertSame(['companion' => 'gpt-4o'], AiEngineSettings::userFeatureModels($user->fresh()));

        $res = $this->actingAs($user, 'web')->delete('/user/settings/ai-models');
        $res->assertRedirect();
        $this->assertSame([], AiEngineSettings::userFeatureModels($user->fresh()));
    }

    public function test_web_update_forbidden_for_free_user(): void
    {
        $user = $this->makeUser(true);

        $this->actingAs($user, 'web')
            ->put('/user/settings/ai-models', ['feature_models' => ['companion' => 'gpt-4o']])
            ->assertForbidden();
    }

    // ── REST API parity ──────────────────────────────────────────────

    protected function apiHeaders(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }

    public function test_api_show_returns_models_and_choices(): void
    {
        $user = $this->makeUser();
        AiEngineSettings::setUserFeatureModels($user, ['companion' => 'gpt-4o']);

        $res = $this->getJson('/api/v1/me/ai-models', $this->apiHeaders($user->fresh()));
        $res->assertOk();
        $res->assertJsonPath('data.is_paid', true);
        $res->assertJsonPath('data.choices.companion', 'gpt-4o');
        $res->assertJsonPath('data.default_model', 'gpt-4o-mini');
        $this->assertContains('gpt-4o-mini', array_column($res->json('data.models'), 'name'));
    }

    public function test_api_update_is_partial_and_validates_model(): void
    {
        $user = $this->makeUser();
        AiEngineSettings::setUserFeatureModels($user, ['coach' => 'gpt-4o']);

        $res = $this->putJson('/api/v1/me/ai-models', [
            'feature_models' => ['companion' => 'gpt-4o'],
        ], $this->apiHeaders($user));
        $res->assertOk();
        $res->assertJsonPath('data.choices.companion', 'gpt-4o');
        $res->assertJsonPath('data.choices.coach', 'gpt-4o');

        // Disabled model is rejected with the unified error envelope.
        $res = $this->putJson('/api/v1/me/ai-models', [
            'feature_models' => ['companion' => 'gpt-4-slow'],
        ], $this->apiHeaders($user->fresh()));
        $res->assertStatus(422);
        $res->assertJsonPath('error.code', 'invalid_model');
    }

    public function test_api_write_forbidden_for_free_user_but_read_allowed(): void
    {
        $user = $this->makeUser(true);
        $headers = $this->apiHeaders($user);

        $this->getJson('/api/v1/me/ai-models', $headers)
            ->assertOk()
            ->assertJsonPath('data.is_paid', false);

        $this->putJson('/api/v1/me/ai-models', ['feature_models' => ['companion' => 'gpt-4o']], $headers)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'paid_plan_required');

        $this->deleteJson('/api/v1/me/ai-models', [], $headers)
            ->assertStatus(403);
    }

    public function test_api_reset_clears_all(): void
    {
        $user = $this->makeUser();
        AiEngineSettings::setUserFeatureModels($user, ['companion' => 'gpt-4o']);

        $this->deleteJson('/api/v1/me/ai-models', [], $this->apiHeaders($user->fresh()))
            ->assertOk();
        $this->assertSame([], AiEngineSettings::userFeatureModels($user->fresh()));
    }

    // ── Admin page still renders after the redesign ──────────────────

    public function test_admin_ai_engine_page_renders_redesigned_sections(): void
    {
        $role = \App\Modules\Admin\Models\Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $admin = \App\Modules\Admin\Models\Admin::create([
            'name'     => 'AI Admin',
            'email'    => 'ai-admin-' . uniqid() . '@example.com',
            'password' => 'secret-password',
            'status'   => 'active',
            'role_id'  => $role->id,
        ]);

        $res = $this->actingAs($admin, 'admin')->get('/admin/ai-engine');
        $res->assertOk();
        $res->assertSee('Models &amp; rates', false);
        $res->assertSee('the catalog');
        $res->assertSee('the assignments');
        $res->assertSee('feature-search');
        $res->assertSee('feature_models[companion]', false);
        $res->assertSee('Settings → AI Models');
    }
}
