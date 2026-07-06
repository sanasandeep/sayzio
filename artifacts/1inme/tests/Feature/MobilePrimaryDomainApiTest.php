<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\PlatformHosts;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage for the mobile primary-domain flows backed by the
 * sanctum REST API used by the Expo app:
 *
 *   GET  /api/v1/domains/available   (DomainPicker + create/edit pre-select)
 *   POST /api/v1/links               (create with a chosen domain_id)
 *   PATCH/PUT /api/v1/links/{id}     (edit the chosen domain_id)
 *   POST /api/v1/domains/{id}/primary (admin "Make primary")
 *
 * Mirrors what the mobile screens exercise:
 *   - components/DomainPicker.tsx        (renders items + primary flag)
 *   - app/links/create/[kind].tsx        (pre-selects primary_domain_id)
 *   - app/domains.tsx                    (can_manage gates "Make primary")
 *   - lib/api/domains.ts                 (envelope shapes)
 */
class MobilePrimaryDomainApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    /**
     * Authenticate the test client as the given user using a REAL Sanctum
     * personal access token (Bearer header), exactly like the Expo app.
     * We deliberately avoid Sanctum::actingAs here: it injects a Mockery
     * mock as the current access token, which the TouchSessionToken
     * middleware can't forceFill()->save() on. A real token exercises the
     * genuine auth + token-touch path end-to-end.
     */
    private function asUser(User $user): self
    {
        $plain = $user->createToken('mobile-test')->plainTextToken;
        $this->withToken($plain);
        return $this;
    }

    /** A user holding the web-guard `settings.manage` permission. */
    private function makeAdmin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'platform-settings'],
            ['name' => 'Platform Settings', 'guard' => 'web']
        );
        $perm = Permission::firstOrCreate(
            ['slug' => 'settings.manage'],
            ['name' => 'Manage Settings', 'group' => 'settings']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = $this->makeUser();
        $user->roles()->attach($role->id);
        $user->flushPermissionCache();
        return $user->fresh();
    }

    /** An admin-global, verified+active, untagged (open to all) domain. */
    private function globalDomain(string $host, bool $primary = false): Domain
    {
        return Domain::create([
            'user_id'     => null,
            'domain'      => $host,
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
            'is_primary'  => $primary,
        ]);
    }

    public function test_available_reports_primary_default_host_and_no_manage_for_regular_user(): void
    {
        $user = $this->makeUser();
        $primary   = $this->globalDomain('go.1inme.test', primary: true);
        $secondary = $this->globalDomain('s.1inme.test');

        $this->asUser($user);
        $resp = $this->getJson('/api/v1/domains/available');

        $resp->assertOk();
        $resp->assertJsonPath('data.primary_domain_id', $primary->id);
        $resp->assertJsonPath('data.default_host', PlatformHosts::primary());
        $resp->assertJsonPath('data.can_manage', false);

        // Both global domains are attachable (verified+active+untagged).
        $ids = array_column($resp->json('data.items'), 'id');
        $this->assertContains($primary->id, $ids);
        $this->assertContains($secondary->id, $ids);

        // The primary global domain is flagged so DomainPicker can badge it.
        $primaryItem = collect($resp->json('data.items'))->firstWhere('id', $primary->id);
        $this->assertTrue($primaryItem['is_global']);
        $this->assertTrue($primaryItem['is_primary']);
    }

    public function test_available_falls_back_to_env_default_host_when_no_primary_configured(): void
    {
        $user = $this->makeUser();
        // A global domain exists but none is primary.
        $this->globalDomain('s.1inme.test');

        $this->asUser($user);
        $resp = $this->getJson('/api/v1/domains/available');

        $resp->assertOk();
        // No admin-chosen primary → create/edit flows fall back to the
        // env default host (domainId stays null in the picker).
        $resp->assertJsonPath('data.primary_domain_id', null);
        $resp->assertJsonPath('data.default_host', PlatformHosts::primary());
    }

    public function test_available_reports_can_manage_true_for_admin(): void
    {
        $admin = $this->makeAdmin();

        $this->asUser($admin);
        $this->getJson('/api/v1/domains/available')
            ->assertOk()
            ->assertJsonPath('data.can_manage', true);
    }

    public function test_create_link_persists_chosen_domain_then_edit_changes_it(): void
    {
        $user = $this->makeUser();
        $primary = $this->globalDomain('go.1inme.test', primary: true);
        $other   = $this->globalDomain('s.1inme.test');

        $this->asUser($user);

        // Create a short link on the pre-selected primary domain (what the
        // mobile create screen sends after pre-selecting primary_domain_id).
        $create = $this->postJson('/api/v1/links', [
            'type'      => 'short',
            'title'     => 'My link',
            'long_url'  => 'https://example.com/very/long/path',
            'domain_id' => $primary->id,
        ]);
        $create->assertCreated();
        $create->assertJsonPath('data.link.domain_id', $primary->id);
        $create->assertJsonPath('data.link.domain', $primary->domain);
        $linkId = $create->json('data.link.id');

        // Edit it onto a different available domain.
        $edit = $this->patchJson("/api/v1/links/{$linkId}", [
            'domain_id' => $other->id,
        ]);
        $edit->assertOk();
        $edit->assertJsonPath('data.link.domain_id', $other->id);
        $edit->assertJsonPath('data.link.domain', $other->domain);

        // And clear it back to the env default host (domain_id = null).
        $clear = $this->patchJson("/api/v1/links/{$linkId}", [
            'domain_id' => null,
        ]);
        $clear->assertOk();
        $clear->assertJsonPath('data.link.domain_id', null);
        $clear->assertJsonPath('data.link.domain', null);
    }

    public function test_create_link_rejects_a_domain_not_available_to_the_user(): void
    {
        $user = $this->makeUser();
        // Another user's private (non-global) domain — not attachable.
        $stranger = $this->makeUser();
        $foreign = Domain::create([
            'user_id'     => $stranger->id,
            'domain'      => 'private.example.test',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $this->asUser($user);
        $resp = $this->postJson('/api/v1/links', [
            'type'      => 'short',
            'long_url'  => 'https://example.com',
            'domain_id' => $foreign->id,
        ]);
        $resp->assertStatus(422);
        $this->assertArrayHasKey('domain_id', (array) $resp->json('error.details'));
    }

    public function test_admin_make_primary_switches_the_platform_primary(): void
    {
        $admin = $this->makeAdmin();
        $current = $this->globalDomain('go.1inme.test', primary: true);
        $next    = $this->globalDomain('s.1inme.test');

        $this->asUser($admin);
        $resp = $this->postJson("/api/v1/domains/{$next->id}/primary");

        $resp->assertOk();
        $resp->assertJsonPath('data.domain.id', $next->id);
        $resp->assertJsonPath('data.domain.is_primary', true);

        // Exactly one primary at a time — the old one is demoted.
        $this->assertTrue($next->fresh()->is_primary);
        $this->assertFalse($current->fresh()->is_primary);

        // available now pre-selects the new primary for create/edit.
        $this->getJson('/api/v1/domains/available')
            ->assertOk()
            ->assertJsonPath('data.primary_domain_id', $next->id);
    }

    public function test_non_admin_cannot_make_primary(): void
    {
        $user = $this->makeUser();
        $a = $this->globalDomain('go.1inme.test', primary: true);
        $b = $this->globalDomain('s.1inme.test');

        $this->asUser($user);
        $this->postJson("/api/v1/domains/{$b->id}/primary")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        // Nothing changed.
        $this->assertTrue($a->fresh()->is_primary);
        $this->assertFalse($b->fresh()->is_primary);
    }

    public function test_make_primary_404s_for_a_non_global_domain(): void
    {
        $admin = $this->makeAdmin();
        // A user-owned (non-global) domain can never be primary.
        $owned = Domain::create([
            'user_id'     => $admin->id,
            'domain'      => 'mine.example.test',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $this->asUser($admin);
        $this->postJson("/api/v1/domains/{$owned->id}/primary")
            ->assertStatus(404);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/domains/available')->assertStatus(401);
        $this->postJson('/api/v1/domains/1/primary')->assertStatus(401);
    }
}
