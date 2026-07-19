<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for the native Tip Jar biolink block.
 *
 * The tip_jar block lets visitors tip a creator directly through their
 * connected payout account at 0% platform fee. The block lives alongside
 * buy_me_coffee / ko_fi / patreon in the "business" category but routes
 * tips through MonetizationCheckout::startTip rather than an external URL.
 */
class TipJarBlockTest extends TestCase
{
    use RefreshDatabase;

    // ─── Fixtures ────────────────────────────────────────────────────────────

    private function makeCreatorWithConnection(): array
    {
        $creator = User::factory()->create(['preferred_currency' => 'USD']);

        $conn = CreatorPaymentConnection::create([
            'user_id'         => $creator->id,
            'provider'        => 'stripe',
            'account_id'      => 'acct_' . Str::random(8),
            'status'          => 'active',
            'payouts_enabled' => true,
            'charges_enabled' => true,
            'is_default'      => true,
        ]);

        return [$creator, $conn];
    }

    private function makeCreatorWithoutConnection(): User
    {
        return User::factory()->create();
    }

    private function makeBiolinkWithTipJarBlock(User $creator, array $blockSettings = []): array
    {
        $link = Link::create([
            'user_id'      => $creator->id,
            'workspace_id' => $creator->default_workspace_id,
            'alias'        => 'tiptest-' . Str::random(6),
            'type'         => 'biolink',
            'title'        => 'Test Biolink',
            'url'          => 'https://example.com',
        ]);

        $block = BiolinkBlock::create([
            'link_id'    => $link->id,
            'user_id'    => $creator->id,
            'type'       => 'tip_jar',
            'settings'   => array_merge([
                'title'        => 'Send me a tip',
                'message'      => 'Thanks for your support!',
                'amounts'      => [3, 5, 10],
                'allow_custom' => true,
                'button_text'  => 'Send Tip',
            ], $blockSettings),
            'sort_order' => 0,
            'is_active'  => true,
        ]);

        return [$link, $block];
    }

    // ─── BlockDefaults / Registry ─────────────────────────────────────────────

    public function test_tip_jar_is_registered_in_block_types(): void
    {
        $types = BiolinkBlock::TYPES;
        $this->assertArrayHasKey('tip_jar', $types);
        $this->assertSame('business', $types['tip_jar']['category']);
    }

    public function test_block_defaults_exist_for_tip_jar(): void
    {
        $content = \App\Modules\User\Support\BlockDefaults::contentForType('tip_jar');
        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('title', $content);
        $this->assertArrayHasKey('amounts', $content);
        $this->assertIsArray($content['amounts']);
        $this->assertNotEmpty($content['amounts']);
    }

    // ─── BiolinkBlockController: amounts_csv handling ────────────────────────

    public function test_amounts_csv_is_parsed_for_tip_jar(): void
    {
        [$creator, $conn] = $this->makeCreatorWithConnection();
        [$link, $block]   = $this->makeBiolinkWithTipJarBlock($creator);

        $this->actingAs($creator)
            ->put(route('user.links.blocks.update', ['link' => $link->id, 'block' => $block->id]), [
                'settings' => [
                    'title'        => 'Tip me',
                    'amounts_csv'  => '2, 5, 15',
                    'allow_custom' => '1',
                    'button_text'  => 'Send Tip',
                ],
            ])
            ->assertRedirect();

        $updated = $block->fresh();
        $this->assertSame([2, 5, 15], $updated->settings['amounts'] ?? null);
        $this->assertArrayNotHasKey('amounts_csv', $updated->settings);
    }

    // ─── Web: POST /{alias}/tip-jar ───────────────────────────────────────────

    public function test_tip_jar_checkout_requires_viewer(): void
    {
        [$creator, $conn] = $this->makeCreatorWithConnection();
        [$link, $block]   = $this->makeBiolinkWithTipJarBlock($creator);

        $this->post(route('biolink.tip-jar', ['alias' => $link->alias]), [
            'block_id' => $block->id,
            'amount'   => 5,
        ])->assertRedirect(url('/' . $link->alias));
    }

    public function test_tip_jar_checkout_redirects_to_checkout_url(): void
    {
        config(['monetization.allow_preview_checkout' => true]);

        [$creator, $conn] = $this->makeCreatorWithConnection();
        [$link, $block]   = $this->makeBiolinkWithTipJarBlock($creator);
        $fan = User::factory()->create();

        $response = $this->actingAs($fan)
            ->post(route('biolink.tip-jar', ['alias' => $link->alias]), [
                'block_id' => $block->id,
                'amount'   => 5,
            ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertNotNull($location, 'should redirect to checkout URL');
    }

    public function test_tip_jar_returns_error_when_no_payout_connection(): void
    {
        $creator = $this->makeCreatorWithoutConnection();
        [$link, $block] = $this->makeBiolinkWithTipJarBlock($creator);
        $fan = User::factory()->create();

        $this->actingAs($fan)
            ->post(route('biolink.tip-jar', ['alias' => $link->alias]), [
                'block_id' => $block->id,
                'amount'   => 5,
            ])
            ->assertRedirect(url('/' . $link->alias));
    }

    public function test_tip_jar_rejects_block_not_on_link(): void
    {
        config(['monetization.allow_preview_checkout' => true]);

        [$creator, $conn] = $this->makeCreatorWithConnection();
        [$link]           = $this->makeBiolinkWithTipJarBlock($creator);

        // Create a block on a *different* link and try to POST it against our link
        $other = User::factory()->create();
        [, $alienBlock] = $this->makeBiolinkWithTipJarBlock($other);

        $fan = User::factory()->create();

        $this->actingAs($fan)
            ->post(route('biolink.tip-jar', ['alias' => $link->alias]), [
                'block_id' => $alienBlock->id,
                'amount'   => 5,
            ])
            ->assertNotFound();
    }

    public function test_tip_jar_validates_amount_range(): void
    {
        config(['monetization.allow_preview_checkout' => true]);

        [$creator, $conn] = $this->makeCreatorWithConnection();
        [$link, $block]   = $this->makeBiolinkWithTipJarBlock($creator);
        $fan = User::factory()->create();

        $this->actingAs($fan)
            ->post(route('biolink.tip-jar', ['alias' => $link->alias]), [
                'block_id' => $block->id,
                'amount'   => 9999,
            ])
            ->assertSessionHasErrors('amount');
    }

    // ─── API: POST /api/v1/biolinks/{alias}/tip-jar ───────────────────────────

    public function test_api_tip_jar_requires_auth(): void
    {
        [$creator, $conn] = $this->makeCreatorWithConnection();
        [$link, $block]   = $this->makeBiolinkWithTipJarBlock($creator);

        $this->postJson("/api/v1/biolinks/{$link->alias}/tip-jar", [
            'block_id' => $block->id,
            'amount'   => 5,
        ])->assertStatus(401);
    }

    public function test_api_tip_jar_returns_checkout_url(): void
    {
        config(['monetization.allow_preview_checkout' => true]);

        [$creator, $conn] = $this->makeCreatorWithConnection();
        [$link, $block]   = $this->makeBiolinkWithTipJarBlock($creator);
        $fan = User::factory()->create();
        $token = $fan->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/biolinks/{$link->alias}/tip-jar", [
                'block_id' => $block->id,
                'amount'   => 5,
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['checkout_url', 'tip_id']]);
    }

    public function test_api_tip_jar_returns_422_when_no_connection(): void
    {
        $creator = $this->makeCreatorWithoutConnection();
        [$link, $block] = $this->makeBiolinkWithTipJarBlock($creator);
        $fan = User::factory()->create();
        $token = $fan->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/biolinks/{$link->alias}/tip-jar", [
                'block_id' => $block->id,
                'amount'   => 5,
            ])
            ->assertStatus(422);
    }

    // ─── Earnings breakdown: tip_jar source (Task) ───────────────────────────

    public function test_web_tip_jar_records_tip_with_tip_jar_source(): void
    {
        config(['monetization.allow_preview_checkout' => true]);

        [$creator, $conn] = $this->makeCreatorWithConnection();
        [$link, $block]   = $this->makeBiolinkWithTipJarBlock($creator);
        $fan = User::factory()->create();

        $this->actingAs($fan)
            ->post(route('biolink.tip-jar', ['alias' => $link->alias]), [
                'block_id' => $block->id,
                'amount'   => 5,
            ])
            ->assertRedirect();

        $tip = \App\Modules\User\Models\CreatorTip::query()
            ->where('creator_user_id', $creator->id)
            ->where('fan_user_id', $fan->id)
            ->latest('id')->first();
        $this->assertNotNull($tip);
        $this->assertSame('tip_jar', $tip->source);
    }

    public function test_confirmed_tip_jar_tip_logs_tip_jar_ledger_event(): void
    {
        config(['monetization.allow_preview_checkout' => true]);

        [$creator, $conn] = $this->makeCreatorWithConnection();
        [$link, $block]   = $this->makeBiolinkWithTipJarBlock($creator);
        $fan = User::factory()->create();

        $response = $this->actingAs($fan)
            ->post(route('biolink.tip-jar', ['alias' => $link->alias]), [
                'block_id' => $block->id,
                'amount'   => 5,
            ]);
        $response->assertRedirect();

        // Preview checkout URL carries kind/reference/token; confirm via
        // the return handler exactly like the hosted flow would.
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?: '', $q);
        $this->assertNotEmpty($q['token'] ?? null);
        $this->get(route('checkout.return', [
            'kind'      => 'tip',
            'reference' => $q['reference'],
            'token'     => $q['token'],
        ]))->assertRedirect();

        $this->assertDatabaseHas('creator_payment_events', [
            'creator_user_id' => $creator->id,
            'fan_user_id'     => $fan->id,
            'source'          => 'tip_jar',
            'type'            => \App\Modules\User\Models\CreatorPaymentEvent::TYPE_TIP_RECEIVED,
            'amount_cents'    => 500,
        ]);

        // The mobile earnings API surfaces the same breakdown.
        $token = $creator->createToken('test')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/v1/me/creator/earnings')
            ->assertOk()
            ->assertJsonPath('data.by_source.tip_jar', 500);
    }

    public function test_profile_tip_still_records_plain_tip_source(): void
    {
        config(['monetization.allow_preview_checkout' => true]);

        [$creator, $conn] = $this->makeCreatorWithConnection();
        $creator->forceFill(['handle' => 'tipcreator' . random_int(1000, 9999)])->save();
        $fan = User::factory()->create();

        $r = app(MonetizationCheckout::class)->startTip($fan, $creator, 500, 'USD');
        $this->assertNull($r['tip']->source);

        parse_str(parse_url($r['url'], PHP_URL_QUERY) ?: '', $q);
        $this->get(route('checkout.return', [
            'kind'      => 'tip',
            'reference' => $q['reference'],
            'token'     => $q['token'],
        ]))->assertRedirect();

        $this->assertDatabaseHas('creator_payment_events', [
            'creator_user_id' => $creator->id,
            'source'          => 'tip',
            'type'            => \App\Modules\User\Models\CreatorPaymentEvent::TYPE_TIP_RECEIVED,
        ]);
    }
}
