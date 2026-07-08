<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\SitePagesContent;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the mobile Perfect Pairings parity endpoints:
 *
 *   GET  /api/v1/admin/link-type-pairings                   (catalog + enabled flags)
 *   PUT  /api/v1/admin/link-type-pairings                   (save the checkbox state)
 *   POST /api/v1/admin/link-type-pairings/restore-defaults  (re-enable everything)
 *
 * All three mirror the web Admin\LinkTypePairingsController: they persist to
 * the same SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY AppSetting and
 * are gated behind the same `settings.manage` permission, so a regular
 * sanctum token must be rejected.
 */
class MobileLinkTypePairingsApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
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

    private function asUser(User $user): self
    {
        $this->withToken($user->createToken('mobile-test')->plainTextToken);
        return $this;
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/link-type-pairings')->assertStatus(401);
        $this->putJson('/api/v1/admin/link-type-pairings')->assertStatus(401);
        $this->postJson('/api/v1/admin/link-type-pairings/restore-defaults')->assertStatus(401);
    }

    public function test_forbidden_for_a_non_admin_token(): void
    {
        $this->asUser($this->makeUser());

        $this->getJson('/api/v1/admin/link-type-pairings')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->putJson('/api/v1/admin/link-type-pairings', ['enabled' => []])
            ->assertStatus(403);
        $this->postJson('/api/v1/admin/link-type-pairings/restore-defaults')
            ->assertStatus(403);

        // A blocked caller must not have changed the setting.
        $this->assertNull(AppSetting::get(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY));
    }

    public function test_status_lists_every_page_type_and_card_with_enabled_flags(): void
    {
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY, [
            'biolink' => ['qr', 'vcf'],
        ]);

        $this->asUser($this->makeAdmin());

        $resp = $this->getJson('/api/v1/admin/link-type-pairings')->assertOk();

        $sections = collect($resp->json('data.sections'))->keyBy('key');
        $catalog = SitePagesContent::linkTypePairingsCatalog();

        $this->assertSame(array_keys($catalog), $sections->keys()->all());

        foreach ($catalog as $pageKey => $items) {
            $this->assertCount(count($items), $sections[$pageKey]['items']);
            $this->assertNotSame('', $sections[$pageKey]['label']);
        }

        $biolink = collect($sections['biolink']['items'])->keyBy('type');
        $this->assertFalse($biolink['qr']['enabled']);
        $this->assertFalse($biolink['vcf']['enabled']);
        $this->assertTrue($biolink['reviews']['enabled']);
        // Cards carry the display fields the mobile screen renders.
        $this->assertArrayHasKey('name', $biolink['qr']);
        $this->assertArrayHasKey('benefit', $biolink['qr']);
    }

    public function test_update_stores_unchecked_cards_as_disabled(): void
    {
        $enabled = [];
        foreach (SitePagesContent::linkTypePairingsCatalog() as $pageKey => $items) {
            $enabled[$pageKey] = array_column($items, 'type');
        }
        // Uncheck two biolink cards and ALL resume cards.
        $enabled['biolink'] = ['reviews', 'ics'];
        unset($enabled['resume']);

        $this->asUser($this->makeAdmin());

        $resp = $this->putJson('/api/v1/admin/link-type-pairings', ['enabled' => $enabled])
            ->assertOk();

        $stored = AppSetting::get(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY);
        $this->assertSame(['qr', 'vcf'], $stored['biolink']);
        $this->assertSame(
            array_column(SitePagesContent::linkTypePairingsCatalog()['resume'], 'type'),
            $stored['resume'],
        );
        $this->assertArrayNotHasKey('reviews', $stored);

        // Public consumers see the filtered list.
        $this->assertSame(
            ['reviews', 'ics'],
            array_column(SitePagesContent::linkTypePairingsFor('biolink'), 'type'),
        );
        $this->assertSame([], SitePagesContent::linkTypePairingsFor('resume'));

        // The response reflects the fresh state so the screen can re-seed.
        $biolink = collect($resp->json('data.sections'))->keyBy('key')['biolink'];
        $this->assertFalse(collect($biolink['items'])->keyBy('type')['qr']['enabled']);
    }

    public function test_restore_defaults_re_enables_everything(): void
    {
        AppSetting::put(SitePagesContent::LINK_TYPE_PAIRINGS_DISABLED_KEY, [
            'biolink' => ['qr'],
            'reviews' => ['ics'],
        ]);

        $this->asUser($this->makeAdmin());

        $resp = $this->postJson('/api/v1/admin/link-type-pairings/restore-defaults')
            ->assertOk();

        foreach (SitePagesContent::linkTypePairingsCatalog() as $pageKey => $items) {
            $this->assertSame($items, SitePagesContent::linkTypePairingsFor($pageKey));
        }

        foreach ($resp->json('data.sections') as $section) {
            foreach ($section['items'] as $item) {
                $this->assertTrue($item['enabled']);
            }
        }
    }
}
