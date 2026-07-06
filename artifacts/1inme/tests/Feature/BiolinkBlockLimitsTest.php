<?php

namespace Tests\Feature;

use App\Modules\Common\Services\LinkTrackingService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Task #1094 — server-side enforcement for the per-block scarcity feature.
 *
 * Two flavors are covered here:
 *   1. Once a block has exhausted its `max_clicks` cap (or its `end_date`
 *      has passed), neither the web redirect nor the mobile tap endpoint
 *      may track another click or send the visitor onward to the URL.
 *   2. The public limits snapshot endpoint must respect the parent
 *      biolink's visibility setting so private/follower-only/subscriber
 *      pages do not leak countdown or click-cap metadata to anonymous
 *      callers.
 */
class BiolinkBlockLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeBiolink(User $user, array $attrs = []): Link
    {
        return Link::create(array_merge([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'My Bio',
            'is_active' => true,
        ], $attrs));
    }

    public function test_web_redirect_refuses_click_after_cap_reached(): void
    {
        $user  = $this->makeUser();
        $bio   = $this->makeBiolink($user);
        $block = BiolinkBlock::create([
            'link_id'     => $bio->id,
            'type'        => 'link',
            'sort_order'  => 0,
            'is_active'   => true,
            'max_clicks'  => 3,
            'click_count' => 3, // already at cap
            'settings'    => ['_link' => ['url' => 'https://example.com/promo']],
        ]);

        $resp = $this->get("/{$bio->alias}/b/{$block->id}");

        // No 302 to the destination — caller sees the expired view (410)
        // or the configured expiry redirect, never the underlying URL.
        $this->assertNotEquals(302, $resp->getStatusCode(), 'Click should not redirect to destination after cap');
        if ($resp->getStatusCode() === 302) {
            $this->assertStringNotContainsString('example.com/promo', (string) $resp->headers->get('Location'));
        }

        // Cap counter must not be incremented past the configured maximum.
        $block->refresh();
        $this->assertSame(3, (int) $block->click_count);
    }

    public function test_mobile_tap_refuses_click_after_expiry(): void
    {
        $user  = $this->makeUser();
        $bio   = $this->makeBiolink($user);
        $block = BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'link',
            'sort_order' => 0,
            'is_active'  => true,
            'end_date'   => now()->subMinute(),
            'settings'   => ['_link' => ['url' => 'https://example.com/promo']],
        ]);

        $resp = $this->postJson("/api/v1/biolinks/{$bio->alias}/blocks/{$block->id}/tap", [
            'destination_url' => 'https://example.com/promo',
        ]);

        $resp->assertStatus(410);
        $this->assertSame('block_expired', $resp->json('error.code') ?? $resp->json('code'));

        // Click counter stays at zero — the tap was rejected before tracking.
        $block->refresh();
        $this->assertSame(0, (int) ($block->click_count ?? 0));
    }

    public function test_public_limits_endpoint_does_not_leak_for_follower_only_biolink(): void
    {
        $user = $this->makeUser();
        $bio  = $this->makeBiolink($user, ['visibility' => 'followers']);
        BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'link',
            'sort_order' => 0,
            'is_active'  => true,
            'max_clicks' => 100,
            'end_date'   => now()->addHour(),
            'settings'   => ['_link' => ['url' => 'https://example.com']],
        ]);

        $this->getJson("/api/v1/biolinks/{$bio->alias}/blocks/limits")
            ->assertStatus(404);
    }

    public function test_track_block_click_never_overshoots_max_clicks(): void
    {
        $user  = $this->makeUser();
        $bio   = $this->makeBiolink($user);
        $cap   = 5;
        $block = BiolinkBlock::create([
            'link_id'     => $bio->id,
            'type'        => 'link',
            'sort_order'  => 0,
            'is_active'   => true,
            'max_clicks'  => $cap,
            'click_count' => 0,
            'settings'    => ['_link' => ['url' => 'https://example.com']],
        ]);

        // Simulate a burst of concurrent click attempts. PHPUnit can't
        // truly fork workers here, but the cap-reservation step is a
        // single conditional UPDATE — the contract we care about is
        // that more attempts than the cap can fire and the persisted
        // counter still tops out at exactly `cap`, with the surplus
        // calls returning null (refused) instead of recording clicks.
        $service  = app(LinkTrackingService::class);
        $accepted = 0;
        $rejected = 0;
        $attempts = $cap + 7;

        for ($i = 0; $i < $attempts; $i++) {
            $request = Request::create("/{$bio->alias}/b/{$block->id}", 'GET');
            $request->headers->set('User-Agent', 'Mozilla/5.0 BurstTest/' . $i);
            // Reload so each attempt sees the latest counter — mirrors
            // independent web workers each loading the row fresh.
            $fresh = BiolinkBlock::find($block->id);
            $result = $service->trackBlockClick($bio, $fresh, 'https://example.com', $request, $bio->alias, 'web');
            if ($result === null) {
                $rejected++;
            } else {
                $accepted++;
            }
        }

        $block->refresh();
        $this->assertSame($cap, (int) $block->click_count, 'Counter must not exceed the configured cap');
        $this->assertSame($cap, $accepted, 'Exactly cap many clicks should have been accepted');
        $this->assertSame($attempts - $cap, $rejected, 'All clicks past the cap must be refused');
        $this->assertSame($cap, LinkClick::where('block_id', $block->id)->count(), 'Refused clicks must not create LinkClick rows');
    }

    public function test_public_limits_endpoint_returns_data_for_public_biolink(): void
    {
        $user = $this->makeUser();
        $bio  = $this->makeBiolink($user, ['visibility' => 'public']);
        $block = BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'link',
            'sort_order' => 0,
            'is_active'  => true,
            'max_clicks' => 10,
            'end_date'   => now()->addHour(),
            'settings'   => ['_link' => ['url' => 'https://example.com']],
        ]);

        $resp = $this->getJson("/api/v1/biolinks/{$bio->alias}/blocks/limits");
        $resp->assertOk();

        $items = $resp->json('data.items');
        $this->assertIsArray($items);
        $this->assertCount(1, $items);
        $this->assertSame($block->id, $items[0]['id'] ?? $items[0]['block_id']);
    }
}
