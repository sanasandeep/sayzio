<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Admin\Models\GatewaySetting;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use App\Services\Billing\WalletService;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checkout-path coverage for the v1 → v2 coin-package lineup change.
 *
 * CoinPackagesSeederTest proves legacy packages are archived and hidden
 * from the active() listing; these tests pin the CHECKOUT side: both the
 * web wallet buy-handoff and the mobile /api/v1/wallet/purchase endpoint
 * must resolve the package through the active() scope, so a stale link,
 * cached shop page, or crafted request against an archived package ID is
 * rejected with 404 (never quoted, never invoiced) while an active v2
 * package still checks out normally.
 */
class CoinPackageCheckoutArchivedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Master wallet toggle + an enabled gateway so requests get past
        // the feature gates and reach the package lookup.
        AppSetting::put(WalletService::FEATURE_KEY, true);
        GatewaySetting::firstOrCreate(
            ['gateway_slug' => 'offline'],
            [
                'display_name' => 'Pay manually',
                'mode'         => 'test',
                'is_enabled'   => true,
                'sort_order'   => 0,
            ]
        );
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'role'    => 'user',
            'country' => 'US',
        ]);
    }

    /** Archived legacy v1 package (e.g. starter-pack) with its old price. */
    private function makeArchivedLegacyPackage(): CoinPackage
    {
        $pkg = CoinPackage::create([
            'slug'        => 'starter-pack',
            'name'        => 'Starter Pack',
            'description' => 'Legacy v1 package',
            'coin_amount' => 100,
            'bonus_coins' => 10,
            'status'      => 'inactive',
            'is_archived' => true,
            'sort_order'  => 10,
        ]);
        // The old (cheap) price is still on the row — checkout must never
        // reach it.
        PricingResolver::upsertFromMinor($pkg, 'USD', 'monthly', 199);

        return $pkg;
    }

    /** Active v2 package priced with the $0.96/coin formula. */
    private function makeActiveV2Package(): CoinPackage
    {
        $pkg = CoinPackage::create([
            'slug'        => 'ai-credits-100',
            'name'        => 'AI Credits 100',
            'description' => 'v2 package',
            'coin_amount' => 100,
            'bonus_coins' => 0,
            'status'      => 'active',
            'is_archived' => false,
            'sort_order'  => 30,
        ]);
        PricingResolver::upsertFromMinor($pkg, 'USD', 'monthly', 9600);

        return $pkg;
    }

    // ------------------------------------------------------------------
    // Web checkout (POST /user/wallet/buy)
    // ------------------------------------------------------------------

    public function test_web_checkout_rejects_archived_package(): void
    {
        $user = $this->makeUser();
        $pkg  = $this->makeArchivedLegacyPackage();

        $response = $this->actingAs($user)->post(route('user.wallet.buy.handoff'), [
            'coin_package_id' => $pkg->id,
            'gateway'         => 'offline',
        ]);

        $response->assertNotFound();
        $this->assertSame(0, Invoice::where('user_id', $user->id)->count(),
            'No invoice may be issued for an archived package.');
    }

    public function test_web_checkout_succeeds_for_active_v2_package(): void
    {
        $user = $this->makeUser();
        $pkg  = $this->makeActiveV2Package();

        $response = $this->actingAs($user)->post(route('user.wallet.buy.handoff'), [
            'coin_package_id' => $pkg->id,
            'gateway'         => 'offline',
        ]);

        // Offline adapter renders the bank-transfer instructions view.
        $response->assertOk();

        $invoice = Invoice::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($invoice, 'Active package checkout must issue an invoice.');
        $this->assertSame(9600, (int) $invoice->grand_total_minor,
            'Invoice must carry the current v2 price.');
        $meta = $invoice->line_items[0]['meta'] ?? [];
        $this->assertSame('coin_package', $meta['kind'] ?? null);
        $this->assertSame($pkg->id, (int) ($meta['coin_package_id'] ?? 0));
    }

    // ------------------------------------------------------------------
    // API checkout (POST /api/v1/wallet/purchase)
    // ------------------------------------------------------------------

    public function test_api_purchase_rejects_archived_package(): void
    {
        $user  = $this->makeUser();
        $pkg   = $this->makeArchivedLegacyPackage();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/wallet/purchase', [
                'coin_package_id' => $pkg->id,
                'gateway'         => 'offline',
            ])
            ->assertNotFound();

        $this->assertSame(0, Invoice::where('user_id', $user->id)->count(),
            'No invoice may be issued for an archived package via the API.');
    }

    public function test_api_purchase_succeeds_for_active_v2_package(): void
    {
        $user  = $this->makeUser();
        $pkg   = $this->makeActiveV2Package();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/wallet/purchase', [
                'coin_package_id' => $pkg->id,
                'gateway'         => 'offline',
            ]);

        $response->assertOk();
        $this->assertSame(9600, (int) $response->json('data.amount_minor'));
        $this->assertNotNull($response->json('data.invoice_id'));
    }

    public function test_archived_package_is_hidden_from_api_package_listing(): void
    {
        $user  = $this->makeUser();
        $this->makeArchivedLegacyPackage();
        $active = $this->makeActiveV2Package();
        $token  = $user->createToken('test')->plainTextToken;

        $slugs = collect(
            $this->withToken($token)->getJson('/api/v1/wallet/packages')
                ->assertOk()
                ->json('data.items')
        )->pluck('slug')->all();

        $this->assertContains($active->slug, $slugs);
        $this->assertNotContains('starter-pack', $slugs,
            'Archived packages must not appear in the shop listing.');
    }
}
