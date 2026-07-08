<?php

namespace Tests\Feature;

use App\Console\Commands\CheckBgTemplateLibrary;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\BgTemplate;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\BgTemplateHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the admin-dashboard background-template shortage banner: it
 * mirrors the `bg-templates:check-library` watchdog on a persistent surface —
 * warning when the library is empty or below the floor, linking to the
 * bg-templates manager, and disappearing once the library recovers.
 */
class AdminBgTemplateBannerTest extends TestCase
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

    private function seedActiveTemplates(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            BgTemplate::create([
                'name'          => 'BG ' . Str::random(6),
                'slug'          => 'bg-' . Str::lower(Str::random(10)),
                'preview_color' => '#123456',
                'css'           => 'background: #123456;',
                'category'      => 'test',
                'is_active'     => true,
                'sort_order'    => $i,
            ]);
        }
    }

    public function test_banner_shows_when_library_is_empty(): void
    {
        $admin = $this->makeAdmin();
        BgTemplate::query()->delete();
        BgTemplateHealth::flush();

        $resp = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $resp->assertOk();
        $resp->assertSee('The background template library is empty');
        $resp->assertSee(route('admin.bg-templates.index'), false);
    }

    public function test_banner_shows_low_variant_below_floor(): void
    {
        $admin = $this->makeAdmin();
        BgTemplate::query()->delete();
        $this->seedActiveTemplates(3);
        BgTemplateHealth::flush();

        $resp = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $resp->assertOk();
        $resp->assertSee('The background template library is running low');
        $resp->assertSee(route('admin.bg-templates.index'), false);
    }

    public function test_banner_hidden_when_library_healthy(): void
    {
        $admin = $this->makeAdmin();
        BgTemplate::query()->delete();
        $this->seedActiveTemplates(CheckBgTemplateLibrary::MIN_ACTIVE);
        BgTemplateHealth::flush();

        $resp = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $resp->assertOk();
        $resp->assertDontSee('The background template library is empty');
        $resp->assertDontSee('The background template library is running low');
    }
}
