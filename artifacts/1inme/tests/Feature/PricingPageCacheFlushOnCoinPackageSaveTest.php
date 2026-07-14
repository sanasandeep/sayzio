<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\PricingPageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The public /pricing page renders coin packages from the same cached
 * catalogue as plans (PricingPageCache). Admin coin-package writes —
 * create, update, archive/restore, delete — must flush that cache so an
 * edited package price/name shows on /pricing on the very next request
 * instead of serving stale data until the TTL or warm cadence catches up.
 *
 * Mirrors PricingPageCacheFlushOnPlanSaveTest for the coin-package
 * admin controller paths.
 */
class PricingPageCacheFlushOnCoinPackageSaveTest extends TestCase
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

    private function makePackage(): CoinPackage
    {
        return CoinPackage::create([
            'name'        => 'Pack ' . uniqid(),
            'slug'        => 'pack-' . uniqid(),
            'description' => 'x',
            'coin_amount' => 100,
            'bonus_coins' => 0,
            'status'      => 'active',
            'sort_order'  => 0,
        ]);
    }

    /** Minimal valid payload for the admin coin-package form. */
    private function payload(CoinPackage $pkg, array $overrides = []): array
    {
        return array_merge([
            'name'        => $pkg->name,
            'description' => $pkg->description,
            'coin_amount' => $pkg->coin_amount,
            'bonus_coins' => $pkg->bonus_coins,
            'status'      => 'active',
            'sort_order'  => 0,
            'price_usd'   => 500,
            'price_inr'   => 40000,
        ], $overrides);
    }

    private function warmCache(): void
    {
        // The coin-package section on /pricing only renders when the coin
        // wallet feature is enabled.
        AppSetting::put(\App\Services\Billing\WalletService::FEATURE_KEY, true);

        $this->get('/pricing')->assertOk();
        $this->assertNotNull(Cache::get(PricingPageCache::CATALOG_CACHE_KEY));
    }

    public function test_admin_coin_package_update_flushes_cache_and_pricing_shows_new_name(): void
    {
        $pkg   = $this->makePackage();
        $admin = $this->makeAdmin();

        $this->warmCache();

        $resp = $this->actingAs($admin, 'admin')->put(
            '/admin/coin-packages/' . $pkg->id,
            $this->payload($pkg, ['name' => 'Mega Coin Bundle 4321'])
        );
        $resp->assertSessionHasNoErrors();

        // The cached catalogue was invalidated by the save…
        $this->assertNull(Cache::get(PricingPageCache::CATALOG_CACHE_KEY));

        // …and the very next /pricing render shows the new name.
        $page = $this->get('/pricing');
        $page->assertOk();
        $page->assertSee('Mega Coin Bundle 4321', false);

        $this->assertSame('Mega Coin Bundle 4321', $pkg->fresh()->name);
    }

    public function test_admin_coin_package_store_flushes_cached_catalog(): void
    {
        $admin = $this->makeAdmin();
        $this->warmCache();

        $resp = $this->actingAs($admin, 'admin')->post(
            '/admin/coin-packages',
            $this->payload(new CoinPackage([
                'name'        => 'Fresh Pack ' . uniqid(),
                'description' => 'x',
                'coin_amount' => 50,
                'bonus_coins' => 0,
            ]))
        );
        $resp->assertSessionHasNoErrors();

        $this->assertNull(Cache::get(PricingPageCache::CATALOG_CACHE_KEY));
    }

    public function test_admin_coin_package_archive_flushes_cached_catalog(): void
    {
        $pkg   = $this->makePackage();
        $admin = $this->makeAdmin();
        $this->warmCache();

        $this->actingAs($admin, 'admin')
            ->post('/admin/coin-packages/' . $pkg->id . '/archive')
            ->assertSessionHasNoErrors();

        $this->assertNull(Cache::get(PricingPageCache::CATALOG_CACHE_KEY));
    }

    public function test_admin_coin_package_destroy_flushes_cached_catalog(): void
    {
        $pkg   = $this->makePackage();
        $admin = $this->makeAdmin();
        $this->warmCache();

        $this->actingAs($admin, 'admin')
            ->delete('/admin/coin-packages/' . $pkg->id)
            ->assertSessionHasNoErrors();

        $this->assertNull(Cache::get(PricingPageCache::CATALOG_CACHE_KEY));
        $this->assertNull(CoinPackage::find($pkg->id));
    }
}
