<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\BgTemplate;
use App\Modules\Admin\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the one-click "Restore default templates" admin action
 * (POST /admin/bg-templates/restore-defaults):
 *
 *   - Re-seeds the full default background template catalog when the
 *     bg_templates table is empty (the incident that motivated the
 *     bg-templates:check-library watchdog).
 *   - Is idempotent: re-running against a populated library never creates
 *     duplicate slugs.
 *   - Re-activates deactivated defaults so the library health check clears.
 *   - Never deletes custom (non-default-slug) templates.
 *   - Requires admin authentication.
 */
class BgTemplateRestoreDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->post('/admin/bg-templates/restore-defaults')->assertRedirect();
        $this->assertSame(0, BgTemplate::count());
    }

    public function test_restores_full_catalog_when_library_is_empty(): void
    {
        $this->assertSame(0, BgTemplate::count());

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post('/admin/bg-templates/restore-defaults')
            ->assertRedirect(route('admin.bg-templates.index'))
            ->assertSessionHas('success');

        $active = BgTemplate::where('is_active', true)->count();
        $this->assertGreaterThanOrEqual(
            \App\Console\Commands\CheckBgTemplateLibrary::MIN_ACTIVE,
            $active,
            'Restore should refill the library above the health-check floor'
        );
    }

    public function test_is_idempotent_and_reactivates_hidden_defaults(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')->post('/admin/bg-templates/restore-defaults');
        $countAfterFirst = BgTemplate::count();

        // Simulate a partial outage: deactivate everything and delete a few.
        BgTemplate::query()->update(['is_active' => false]);
        BgTemplate::query()->limit(3)->get()->each->delete();

        $this->actingAs($admin, 'admin')
            ->post('/admin/bg-templates/restore-defaults')
            ->assertRedirect(route('admin.bg-templates.index'))
            ->assertSessionHas('success');

        $this->assertSame($countAfterFirst, BgTemplate::count(), 'No duplicates on re-run');
        $this->assertSame(0, BgTemplate::where('is_active', false)->count(), 'All defaults re-activated');

        // Slugs stay unique.
        $this->assertSame(
            BgTemplate::count(),
            BgTemplate::distinct('slug')->count('slug')
        );
    }

    public function test_custom_templates_survive_restore(): void
    {
        $custom = BgTemplate::create([
            'name'          => 'My Custom BG',
            'slug'          => 'my-custom-bg',
            'preview_color' => '#222222',
            'css'           => '.bg-template-my-custom-bg{position:fixed;inset:0;z-index:-1;background:#222;}',
            'category'      => 'pattern',
            'is_active'     => true,
            'sort_order'    => 999,
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post('/admin/bg-templates/restore-defaults')
            ->assertRedirect(route('admin.bg-templates.index'));

        $this->assertDatabaseHas('bg_templates', [
            'id'   => $custom->id,
            'name' => 'My Custom BG',
        ]);
    }

    public function test_hidden_custom_templates_stay_hidden_after_restore(): void
    {
        $hiddenCustom = BgTemplate::create([
            'name'          => 'Hidden Custom BG',
            'slug'          => 'hidden-custom-bg',
            'preview_color' => '#333333',
            'css'           => '.bg-template-hidden-custom-bg{position:fixed;inset:0;z-index:-1;background:#333;}',
            'category'      => 'pattern',
            'is_active'     => false,
            'sort_order'    => 998,
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post('/admin/bg-templates/restore-defaults')
            ->assertRedirect(route('admin.bg-templates.index'))
            ->assertSessionHas('success');

        // Re-activation is scoped to default (seeded) slugs only — the
        // admin's intentionally-hidden custom template must stay hidden.
        $this->assertDatabaseHas('bg_templates', [
            'id'        => $hiddenCustom->id,
            'is_active' => false,
        ]);
    }
}
