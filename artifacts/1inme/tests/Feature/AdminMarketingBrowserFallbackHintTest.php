<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\ProductDownloadLinks;
use App\Modules\Common\Support\ZioBrowserRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin Marketing settings page shows, under each Zio Browser override
 * field, the live-release URL the site currently falls back to when the
 * field is blank (plus the release version), and marks filled fields as
 * active overrides.
 */
class AdminMarketingBrowserFallbackHintTest extends TestCase
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

    private function cacheRelease(array $overrides = []): void
    {
        Cache::forever(ZioBrowserRelease::CACHE_KEY, array_merge([
            'version'        => '9.9.9',
            'mac_arm64_dmg'  => 'https://example.com/rel/SayZio-arm64.dmg',
            'mac_x64_dmg'    => 'https://example.com/rel/SayZio-x64.dmg',
            'windows_exe'    => 'https://example.com/rel/SayZio-Setup.exe',
            'mac_arm64_zip'  => null,
            'mac_x64_zip'    => null,
            'linux_appimage' => 'https://example.com/rel/SayZio.AppImage',
            'linux_deb'      => null,
            'published_at'   => null,
        ], $overrides));
    }

    public function test_blank_fields_show_live_release_fallback_urls_and_version(): void
    {
        $this->cacheRelease();

        $res = $this->be($this->makeAdmin(), 'admin')
            ->get(route('admin.marketing-settings.index'));

        $res->assertOk();
        // Fallback URLs + version shown for blank override fields.
        $res->assertSee('live release v9.9.9');
        $res->assertSee('https://example.com/rel/SayZio-arm64.dmg');
        $res->assertSee('https://example.com/rel/SayZio-Setup.exe');
        $res->assertSee('https://example.com/rel/SayZio.AppImage');
        // Missing .deb asset → explicit "none" note.
        $res->assertSee('none — the live release has no installer for this platform');
        // No overrides set, so no field is marked active.
        $res->assertDontSee('Override active');
    }

    public function test_filled_field_is_marked_as_active_override(): void
    {
        $this->cacheRelease();
        AppSetting::put(ProductDownloadLinks::BROWSER_WIN_URL, 'https://cdn.example.com/custom-setup.exe');

        $res = $this->be($this->makeAdmin(), 'admin')
            ->get(route('admin.marketing-settings.index'));

        $res->assertOk();
        $res->assertSee('Override active');
        // The replaced fallback URL is still visible next to the override.
        $res->assertSee('https://example.com/rel/SayZio-Setup.exe');
    }
}
