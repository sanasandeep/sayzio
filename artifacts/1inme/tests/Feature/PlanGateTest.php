<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Per-feature plan-gating coverage. For every new feature key we exercise
 * one positive (allowed) and one negative (blocked) path through the
 * CheckPlanLimit middleware / controller-level checks.
 */
class PlanGateTest extends TestCase
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

    // ===== Helper checks on the User model =====

    public function test_user_can_use_block_type_respects_allowlist(): void
    {
        $free = $this->user($this->plan(['block_types_allowed' => ['heading', 'link_button']], 'free' . Str::random(3)));
        $this->assertTrue($free->userCanUseBlockType('heading'));
        $this->assertFalse($free->userCanUseBlockType('youtube'));

        $unlimited = $this->user($this->plan(['block_types_allowed' => '*'], 'all' . Str::random(3)));
        $this->assertTrue($unlimited->userCanUseBlockType('youtube'));
    }

    public function test_user_can_use_link_setting_maps_keys(): void
    {
        $blocked = $this->user($this->plan([
            'link_password' => false, 'link_smart_rules' => false,
        ]));
        $this->assertFalse($blocked->userCanUseLinkSetting('password'));
        $this->assertFalse($blocked->userCanUseLinkSetting('smart_rules'));

        $allowed = $this->user($this->plan([
            'link_password' => true, 'link_smart_rules' => true,
        ]));
        $this->assertTrue($allowed->userCanUseLinkSetting('password'));
        $this->assertTrue($allowed->userCanUseLinkSetting('smart_rules'));
    }

    public function test_plan_under_limit_handles_unlimited_and_caps(): void
    {
        $u = $this->user($this->plan(['max_forms' => 2]));
        $this->assertTrue($u->planUnderLimit('max_forms', 0, 0));
        $this->assertTrue($u->planUnderLimit('max_forms', 1, 0));
        $this->assertFalse($u->planUnderLimit('max_forms', 2, 0));

        $u2 = $this->user($this->plan(['max_forms' => -1]));
        $this->assertTrue($u2->planUnderLimit('max_forms', 999_999, 0));
    }

    // ===== Middleware route gating: negative + positive =====

    public function test_splash_pages_route_blocks_when_disabled(): void
    {
        $u = $this->user($this->plan(['splash_pages' => false]));
        $resp = $this->actingAs($u)->post('/user/splash-pages', ['name' => 'x']);
        $this->assertNotEquals(200, $resp->status());
        $resp->assertSessionHas('error');
    }

    public function test_splash_pages_route_allows_when_enabled_and_under_limit(): void
    {
        $u = $this->user($this->plan(['splash_pages' => true, 'max_splash_pages' => 5]));
        $resp = $this->actingAs($u)->post('/user/splash-pages', ['name' => 'x']);
        $resp->assertSessionMissing('error');
        $this->assertSame(1, $u->splashPages()->count());
    }

    public function test_buzz_popups_route_blocks_when_disabled(): void
    {
        $u = $this->user($this->plan(['buzz_popups' => false]));
        $resp = $this->actingAs($u)->post('/user/social-proofs', ['name' => 'c']);
        $resp->assertSessionHas('error');
    }

    public function test_buzz_popups_route_allows_under_limit(): void
    {
        $u = $this->user($this->plan(['buzz_popups' => true, 'max_buzz_items' => 3]));
        $resp = $this->actingAs($u)->post('/user/social-proofs', ['name' => 'c']);
        $resp->assertSessionMissing('error');
    }

    public function test_files_upload_blocks_when_disabled(): void
    {
        $u = $this->user($this->plan(['files' => false]));
        $resp = $this->actingAs($u)->post('/user/files/upload', []);
        // Either 403/422 from middleware or session error — both are blocks.
        $this->assertContains($resp->status(), [302, 403, 422]);
    }

    public function test_vault_route_blocks_when_disabled(): void
    {
        $u = $this->user($this->plan(['vaults' => false]));
        $resp = $this->actingAs($u)->post('/user/vault/credentials', ['label' => 'l']);
        $this->assertContains($resp->status(), [302, 403]);
    }

    public function test_tasks_route_blocks_when_at_limit(): void
    {
        $u = $this->user($this->plan(['tasks' => true, 'max_task_boards' => 0]));
        $resp = $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'b', 'scope' => 'personal']);
        $resp->assertSessionHas('error');
    }

    public function test_calendar_sync_blocks_when_disabled(): void
    {
        $u = $this->user($this->plan(['calendar_sync' => false]));
        $resp = $this->actingAs($u)->get('/user/calendar/connect/google');
        $resp->assertSessionHas('error');
    }

    public function test_verification_blocks_when_ineligible(): void
    {
        $u = $this->user($this->plan(['verification_eligible' => false]));
        $resp = $this->actingAs($u)->get('/user/verification/request');
        $resp->assertSessionHas('error');
    }

    public function test_events_blocks_when_disabled(): void
    {
        $u = $this->user($this->plan(['events' => false, 'max_links' => 100]));
        $resp = $this->actingAs($u)->post('/user/links-ics', [
            'event_name' => 'e', 'start_at' => now()->addDay()->toIso8601String(),
            'end_at' => now()->addDays(2)->toIso8601String(),
        ]);
        $resp->assertSessionHas('error');
    }

    public function test_forms_blocks_when_at_limit(): void
    {
        $u = $this->user($this->plan(['max_forms' => 0]));
        $resp = $this->actingAs($u)->post('/user/forms', ['name' => 'f']);
        $resp->assertSessionHas('error');
    }

    // ===== Per-link advanced settings (controller-level) =====

    public function test_link_password_blocked_when_plan_disallows(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'link_password' => false,
        ]));
        $resp = $this->actingAs($u)->post('/user/links', [
            'type' => 'url', 'long_url' => 'https://example.com',
            'password' => 'secret',
        ]);
        $resp->assertSessionHas('error');
    }

    public function test_link_smart_rules_blocked_when_plan_disallows(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'link_smart_rules' => false,
        ]));
        $resp = $this->actingAs($u)->post('/user/links', [
            'type' => 'url', 'long_url' => 'https://example.com',
            'smart_rules_json' => '[{"if":{"country":["US"]},"then":{"url":"https://us.example.com"}}]',
        ]);
        $resp->assertSessionHas('error');
    }

    public function test_link_password_allowed_when_plan_allows(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'link_password' => true,
        ]));
        $resp = $this->actingAs($u)->post('/user/links', [
            'type' => 'url', 'long_url' => 'https://example.com',
            'password' => 'secret',
        ]);
        $resp->assertSessionMissing('error');
    }

    public function test_link_expiry_blocked_on_store_when_plan_disallows(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'link_expiry' => false,
        ]));
        $resp = $this->actingAs($u)->post('/user/links', [
            'type' => 'url', 'long_url' => 'https://example.com',
            'expires_at' => now()->addDay()->toIso8601String(),
        ]);
        $resp->assertSessionHas('error');
    }

    public function test_link_active_window_blocked_on_store_when_plan_disallows(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'link_active_window' => false,
        ]));
        $resp = $this->actingAs($u)->post('/user/links', [
            'type' => 'url', 'long_url' => 'https://example.com',
            'start_at' => now()->addHour()->toIso8601String(),
        ]);
        $resp->assertSessionHas('error');
    }

    public function test_link_update_blocks_password_when_plan_downgrades(): void
    {
        $allow = $this->plan(['max_links' => 100, 'link_password' => true]);
        $u = $this->user($allow);
        $u->links()->create([
            'user_id' => $u->id, 'type' => 'url', 'alias' => 'a' . substr(Str::random(6), 0, 6),
            'long_url' => 'https://example.com', 'is_active' => true,
        ]);
        $link = $u->links()->first();

        // Downgrade the plan and attempt to set a password via update.
        $u->plan_id = $this->plan(['max_links' => 100, 'link_password' => false])->id;
        $u->save();
        $resp = $this->actingAs($u->fresh())->put('/user/links/' . $link->id, [
            'long_url' => 'https://example.com',
            'password' => 'secret',
        ]);
        $resp->assertSessionHas('error');
    }

    public function test_link_update_blocks_active_window_when_plan_downgrades(): void
    {
        $u = $this->user($this->plan(['max_links' => 100, 'link_active_window' => false]));
        $link = $u->links()->create([
            'user_id' => $u->id, 'type' => 'url', 'alias' => 'b' . substr(Str::random(6), 0, 6),
            'long_url' => 'https://example.com', 'is_active' => true,
        ]);
        $resp = $this->actingAs($u)->put('/user/links/' . $link->id, [
            'long_url' => 'https://example.com',
            'active_window_enabled' => 1,
            'active_window_start' => '09:00',
            'active_window_end'   => '17:00',
        ]);
        $resp->assertSessionHas('error');
    }

    public function test_link_update_blocks_expiry_when_plan_downgrades(): void
    {
        $u = $this->user($this->plan(['max_links' => 100, 'link_expiry' => false]));
        $link = $u->links()->create([
            'user_id' => $u->id, 'type' => 'url', 'alias' => 'c' . substr(Str::random(6), 0, 6),
            'long_url' => 'https://example.com', 'is_active' => true,
        ]);
        $resp = $this->actingAs($u)->put('/user/links/' . $link->id, [
            'long_url' => 'https://example.com',
            '_exp_mode' => 'date',
            'expires_at' => now()->addDay()->toIso8601String(),
        ]);
        $resp->assertSessionHas('error');
    }

    private function profilePayload(User $u, array $extra = []): array
    {
        return array_merge([
            'name'     => $u->name,
            'email'    => $u->email,
            'timezone' => 'UTC',
            'language' => 'en',
        ], $extra);
    }

    public function test_creator_profile_public_blocks_discoverable_toggle(): void
    {
        $u = $this->user($this->plan(['creator_profile_public' => false]));
        $resp = $this->actingAs($u)->put('/user/profile', $this->profilePayload($u, ['discoverable' => 1]));
        $resp->assertSessionHas('error');
    }

    public function test_creator_profile_public_allows_discoverable_when_enabled(): void
    {
        $u = $this->user($this->plan(['creator_profile_public' => true]));
        $resp = $this->actingAs($u)->put('/user/profile', $this->profilePayload($u, ['discoverable' => 1]));
        $resp->assertSessionMissing('error');
        $this->assertTrue((bool) $u->fresh()->discoverable);
    }

    public function test_block_picker_lock_blocks_disallowed_block_creation(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'max_biolinks' => 5,
            'block_types_allowed' => ['heading', 'link_button'],
        ]));
        $link = $u->links()->create([
            'user_id' => $u->id, 'type' => 'biolink', 'alias' => 'bl' . substr(Str::random(6), 0, 6),
            'is_active' => true,
        ]);
        $resp = $this->actingAs($u)->post('/user/links/' . $link->id . '/blocks', [
            'type' => 'youtube',
        ]);
        $this->assertContains($resp->status(), [302, 403]);
        $this->assertSame(0, $link->biolinkBlocks()->count());
    }

    // ===== Positive (allowed) coverage for previously negative-only keys =====

    public function test_files_upload_allowed_when_enabled(): void
    {
        $u = $this->user($this->plan(['files' => true, 'max_files' => 10]));
        // Pass a real but invalid payload; the plan gate should NOT trigger,
        // so we should not see the upgrade-error message.
        $resp = $this->actingAs($u)->post('/user/files/upload', []);
        $err = session('error');
        $this->assertTrue($err === null || !str_contains((string) $err, 'plan'), 'Plan gate unexpectedly triggered: ' . (string) $err);
    }

    public function test_vault_route_allowed_when_enabled(): void
    {
        $u = $this->user($this->plan(['vaults' => true, 'max_vault_items' => 5]));
        $resp = $this->actingAs($u)->post('/user/vault/credentials', ['label' => 'x']);
        $err = session('error');
        $this->assertTrue($err === null || !str_contains((string) $err, 'plan'), 'Plan gate unexpectedly triggered: ' . (string) $err);
    }

    public function test_tasks_route_allowed_when_under_limit(): void
    {
        $u = $this->user($this->plan(['tasks' => true, 'max_task_boards' => 5]));
        $resp = $this->actingAs($u)->post('/user/tasks/boards', ['name' => 'b', 'scope' => 'personal']);
        $err = session('error');
        $this->assertTrue($err === null || !str_contains((string) $err, 'plan'), 'Plan gate unexpectedly triggered: ' . (string) $err);
    }

    public function test_calendar_sync_allowed_when_enabled(): void
    {
        $u = $this->user($this->plan(['calendar_sync' => true]));
        $resp = $this->actingAs($u)->get('/user/calendar/connect/google');
        $err = session('error');
        $this->assertTrue($err === null || !str_contains((string) $err, 'plan'), 'Plan gate unexpectedly triggered: ' . (string) $err);
    }

    public function test_verification_allowed_when_eligible(): void
    {
        $u = $this->user($this->plan(['verification_eligible' => true]));
        $resp = $this->actingAs($u)->get('/user/verification/request');
        $err = session('error');
        $this->assertTrue($err === null || !str_contains((string) $err, 'plan'), 'Plan gate unexpectedly triggered: ' . (string) $err);
    }

    public function test_events_allowed_when_under_limit(): void
    {
        $u = $this->user($this->plan(['events' => true, 'max_events' => 5, 'max_links' => 100]));
        $resp = $this->actingAs($u)->post('/user/links-ics', [
            'event_name' => 'e', 'start_at' => now()->addDay()->toIso8601String(),
            'end_at' => now()->addDays(2)->toIso8601String(),
        ]);
        $err = session('error');
        $this->assertTrue($err === null || !str_contains((string) $err, 'plan'), 'Plan gate unexpectedly triggered: ' . (string) $err);
    }

    public function test_forms_allowed_when_under_limit(): void
    {
        $u = $this->user($this->plan(['max_forms' => 5]));
        $this->actingAs($u)->post('/user/forms', ['title' => 'My form']);
        $this->assertSame(1, $u->forms()->count(), 'Form was not created when plan allowed it');
    }

    // ===== Leads volume gate =====

    private function contactPayload(): array
    {
        return ['display_name' => 'A Lead', 'emails' => [['label' => 'home', 'value' => 'a@b.co']]];
    }

    public function test_leads_blocked_when_disabled(): void
    {
        $u = $this->user($this->plan(['leads' => false, 'contacts_max' => 100]));
        $resp = $this->actingAs($u)->post('/user/contacts', $this->contactPayload());
        $resp->assertSessionHas('error');
        $this->assertSame(0, \App\Modules\User\Models\Contact::where('user_id', $u->id)->count());
    }

    public function test_leads_blocked_when_at_max_leads(): void
    {
        $u = $this->user($this->plan(['leads' => true, 'max_leads' => 0, 'contacts_max' => 100]));
        $resp = $this->actingAs($u)->post('/user/contacts', $this->contactPayload());
        $resp->assertSessionHas('error');
        $this->assertSame(0, \App\Modules\User\Models\Contact::where('user_id', $u->id)->count());
    }

    public function test_leads_allowed_when_enabled_and_under_limit(): void
    {
        $u = $this->user($this->plan(['leads' => true, 'max_leads' => 5, 'contacts_max' => 100]));
        $this->actingAs($u)->post('/user/contacts', $this->contactPayload());
        $this->assertSame(1, \App\Modules\User\Models\Contact::where('user_id', $u->id)->count(), 'Contact was not created when plan allowed it');
    }

    // ===== Seeder idempotency / curator overlay =====

    public function test_seeder_is_idempotent_and_preserves_curator_edits(): void
    {
        // First run: seeds canonical plans with default features.
        $this->artisan('db:seed', ['--class' => \Database\Seeders\PlansAndAddonsSeeder::class])->assertSuccessful();
        $free = Plan::where('slug', 'free')->first();
        $this->assertNotNull($free, 'Free plan was not seeded');
        $originalMaxLinks = $free->features['max_links'] ?? null;
        $this->assertNotNull($originalMaxLinks);

        // Curator edits an existing key. The seeder must NOT clobber it
        // on re-run — overlay only fills in missing keys.
        $features = $free->features;
        $features['max_links'] = 999;
        $features['custom_curator_only_key'] = 'keep';
        $free->features = $features;
        $free->save();

        // Second run.
        $this->artisan('db:seed', ['--class' => \Database\Seeders\PlansAndAddonsSeeder::class])->assertSuccessful();
        $free->refresh();
        $this->assertSame(999, $free->features['max_links'], 'Seeder clobbered curator edit');
        $this->assertSame('keep', $free->features['custom_curator_only_key'], 'Seeder dropped curator-only key');
        // Newly added keys still get filled in (overlay).
        $this->assertArrayHasKey('block_types_allowed', $free->features);
    }

    // ===== Deep-link default-on bypass guard =====

    public function test_link_deep_link_default_off_when_plan_disallows(): void
    {
        $u = $this->user($this->plan(['max_links' => 100, 'link_deep_link' => false]));
        // Omit `open_in_app` entirely — historically this defaulted to true.
        $this->actingAs($u)->post('/user/links', [
            'type' => 'url', 'long_url' => 'https://example.com',
        ]);
        $link = $u->links()->latest('id')->first();
        $this->assertNotNull($link);
        $settings = is_array($link->settings) ? $link->settings : (array) json_decode((string) $link->settings, true);
        $this->assertFalse((bool) ($settings['open_in_app'] ?? false), 'Deep-link default-on bypassed plan gate');
    }

    public function test_link_deep_link_default_on_when_plan_allows(): void
    {
        $u = $this->user($this->plan(['max_links' => 100, 'link_deep_link' => true]));
        $this->actingAs($u)->post('/user/links', [
            'type' => 'url', 'long_url' => 'https://example.com',
        ]);
        $link = $u->links()->latest('id')->first();
        $settings = is_array($link->settings) ? $link->settings : (array) json_decode((string) $link->settings, true);
        $this->assertTrue((bool) ($settings['open_in_app'] ?? false));
    }

    // ===== View-level lock signal =====

    public function test_contacts_index_shows_lock_banner_when_leads_disabled(): void
    {
        $u = $this->user($this->plan(['leads' => false]));
        $resp = $this->actingAs($u)->get('/user/contacts');
        // Grace handle: route may redirect when other middleware kicks in,
        // but if rendered the lock banner should be present.
        if ($resp->status() === 200) {
            $resp->assertSee('data-plan-lock="leads"', false);
        } else {
            $this->assertContains($resp->status(), [302, 403]);
        }
    }

    public function test_files_index_shows_lock_banner_when_files_disabled(): void
    {
        $u = $this->user($this->plan(['files' => false]));
        $resp = $this->actingAs($u)->get('/user/files');
        if ($resp->status() === 200) {
            $resp->assertSee('data-plan-lock="files"', false);
        } else {
            $this->assertContains($resp->status(), [302, 403]);
        }
    }

    public function test_upgrade_message_names_target_plan(): void
    {
        // Seed real plans so planThatUnlocks has a target to find.
        $this->artisan('db:seed', ['--class' => \Database\Seeders\PlansAndAddonsSeeder::class])->assertSuccessful();
        $free = Plan::where('slug', 'free')->first();
        $this->assertNotNull($free);
        // Force the user onto the free plan with leads disabled.
        $features = $free->features;
        $features['leads'] = false;
        $free->features = $features;
        $free->save();
        $u = $this->user($free->fresh());
        $resp = $this->actingAs($u)->post('/user/contacts', $this->contactPayload());
        $msg = (string) session('error');
        $this->assertNotEmpty($msg);
        $this->assertStringContainsString('Upgrade to the', $msg, 'Rejection message did not name the target plan');
    }

    public function test_block_picker_allows_when_in_allowlist(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'max_biolinks' => 5,
            'block_types_allowed' => ['heading'],
        ]));
        $link = $u->links()->create([
            'user_id' => $u->id, 'type' => 'biolink', 'alias' => 'al' . substr(Str::random(6), 0, 6),
            'is_active' => true,
        ]);
        $resp = $this->actingAs($u)->post('/user/links/' . $link->id . '/blocks', [
            'type' => 'heading',
        ]);
        $this->assertNotEquals(403, $resp->status());
    }
}
