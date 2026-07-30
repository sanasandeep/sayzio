<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Support\BillingFxRate;
use Database\Seeders\CoinPackagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the admin-editable INR-per-USD FX rate
 * (`billing.fx_rate_inr` app setting):
 *   1. Defaults to the legacy ₹90/$1 rate when unset.
 *   2. Admins can update it via the coin-packages FX form (validated > 0).
 *   3. CoinPackagesSeeder computes INR prices for NEW packages from the
 *      stored rate (and never rewrites existing price rows on re-run).
 *   4. The coin-package edit form surfaces the computed-INR hint.
 */
class AdminCoinPackageFxRateTest extends TestCase
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

    public function test_rate_defaults_to_90_when_unset(): void
    {
        $this->assertSame(90.0, BillingFxRate::get());
        $this->assertSame(86400, BillingFxRate::usdMinorToInrMinor(960));
    }

    public function test_admin_can_update_fx_rate(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.coin-packages.fx-rate'), ['fx_rate_inr' => 83.5])
            ->assertRedirect(route('admin.coin-packages.index'));

        $this->assertSame(83.5, BillingFxRate::get());
        $this->assertEquals(83.5, AppSetting::get(BillingFxRate::KEY));
    }

    public function test_fx_rate_rejects_non_positive_values(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.coin-packages.index'))
            ->post(route('admin.coin-packages.fx-rate'), ['fx_rate_inr' => 0])
            ->assertSessionHasErrors('fx_rate_inr');

        $this->assertSame(90.0, BillingFxRate::get());
    }

    public function test_seeder_computes_inr_from_stored_rate(): void
    {
        // A fresh DB is pre-seeded by the lineup data migration at the
        // default ₹90, and seedPriceIfMissing never rewrites existing rows —
        // wipe the tier so this run prices it from scratch at ₹80.
        CoinPackage::where('slug', 'coins-starter')->each(function ($p) {
            $p->prices()->delete();
            $p->delete();
        });
        BillingFxRate::put(80.0);

        $this->seed(CoinPackagesSeeder::class);

        $pkg = CoinPackage::where('slug', 'coins-starter')->firstOrFail();
        $inr = $pkg->prices()->where('currency', 'INR')->where('billing_cycle', 'monthly')->firstOrFail();
        $usd = $pkg->prices()->where('currency', 'USD')->where('billing_cycle', 'monthly')->firstOrFail();

        $this->assertSame(1000, (int) $usd->amount_minor_units);
        // ₹80/$1 → 1,000 cents * 80 = 80,000 paise.
        $this->assertSame(80000, (int) $inr->amount_minor_units);
    }

    public function test_seeder_rerun_does_not_overwrite_existing_inr_prices(): void
    {
        $this->seed(CoinPackagesSeeder::class); // seeds at default ₹90

        BillingFxRate::put(100.0);
        $this->seed(CoinPackagesSeeder::class); // re-run at new rate

        $pkg = CoinPackage::where('slug', 'coins-starter')->firstOrFail();
        $inr = $pkg->prices()->where('currency', 'INR')->where('billing_cycle', 'monthly')->firstOrFail();

        // Existing rows are preserved (₹90 * 1,000 = 90,000), not rewritten at ₹100.
        $this->assertSame(90000, (int) $inr->amount_minor_units);
    }

    public function test_edit_form_shows_computed_inr_hint(): void
    {
        $admin = $this->makeAdmin();
        BillingFxRate::put(85.0);
        $this->seed(CoinPackagesSeeder::class);
        $pkg = CoinPackage::where('slug', 'coins-starter')->firstOrFail();

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.coin-packages.edit', $pkg))
            ->assertOk();

        $resp->assertSee('data-fx-hint', false);
        // 1,000 cents at ₹85 → 85,000 paise.
        $resp->assertSee('data-fx-rate="85"', false);
        $resp->assertSee('85000');
    }

    public function test_index_shows_fx_rate_form(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.coin-packages.index'))
            ->assertOk()
            ->assertSee('fx_rate_inr', false)
            ->assertSee(route('admin.coin-packages.fx-rate'), false);
    }
}
