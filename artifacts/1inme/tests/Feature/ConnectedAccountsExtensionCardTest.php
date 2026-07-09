<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The knowledge base points users to "Settings → Connected Accounts & Apps"
 * for the browser extension install links, so the page must actually render
 * a Browser Extension card with Chrome / Edge / Firefox store links and a
 * signed-in / not-installed status pill (detected via the Sanctum token the
 * /extension/handshake page mints under the 'browser-extension' name).
 */
class ConnectedAccountsExtensionCardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $plan = Plan::firstOrCreate(['slug' => 'plan-' . Str::lower(Str::random(6))], [
            'name' => 'Test Plan', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => false,
        ]);

        return User::factory()->create(['plan_id' => $plan->id])->fresh();
    }

    public function test_extension_card_shows_store_links_and_not_installed_status(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('user.social-accounts.index'));

        $response->assertOk();
        $response->assertSee('Browser Extension');
        $response->assertSee('Not installed');
        $response->assertSee('https://chromewebstore.google.com/search/Sayzio');
        $response->assertSee('https://microsoftedge.microsoft.com/addons/Search/Sayzio');
        $response->assertSee('https://addons.mozilla.org/en-US/firefox/search/?q=Sayzio', false);
    }

    public function test_extension_card_uses_admin_configured_listing_urls(): void
    {
        AppSetting::put('extension_chrome_store_url', 'https://chromewebstore.google.com/detail/sayzio/abcdef');
        AppSetting::put('extension_firefox_store_url', 'https://addons.mozilla.org/en-US/firefox/addon/sayzio/');

        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('user.social-accounts.index'));

        $response->assertOk();
        $response->assertSee('https://chromewebstore.google.com/detail/sayzio/abcdef');
        $response->assertSee('https://addons.mozilla.org/en-US/firefox/addon/sayzio/', false);
        $response->assertDontSee('https://chromewebstore.google.com/search/Sayzio');
        // Edge is still unpublished, so its button keeps the search fallback.
        $response->assertSee('https://microsoftedge.microsoft.com/addons/Search/Sayzio');
    }

    public function test_public_api_returns_shared_store_links(): void
    {
        AppSetting::put('extension_edge_store_url', 'https://microsoftedge.microsoft.com/addons/detail/sayzio/xyz');

        $response = $this->getJson('/api/v1/extension/stores');

        $response->assertOk();
        $stores = collect($response->json('data.stores'))->keyBy('key');
        $this->assertSame('https://microsoftedge.microsoft.com/addons/detail/sayzio/xyz', $stores['edge']['url']);
        $this->assertTrue($stores['edge']['is_listing']);
        $this->assertSame('https://chromewebstore.google.com/search/Sayzio', $stores['chrome']['url']);
        $this->assertFalse($stores['chrome']['is_listing']);
        $this->assertFalse($stores['firefox']['is_listing']);
    }

    public function test_extension_card_shows_signed_in_when_handshake_token_exists(): void
    {
        $user = $this->makeUser();
        $user->createToken('browser-extension');

        $response = $this->actingAs($user)->get(route('user.social-accounts.index'));

        $response->assertOk();
        $response->assertSee('Signed in');
        $response->assertDontSee('Not installed');
    }
}
