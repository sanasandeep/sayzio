<?php

namespace Tests\Feature;

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
