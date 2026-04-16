<?php

namespace Tests\Feature\Analytics;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;

/**
 * Covers the one-click Performance Coach action endpoint.
 *
 * The endpoint supports three destructive action types — deactivate_blocks,
 * promote_block, enable_block — and must enforce both link-level ownership
 * and per-request block scoping (block ids belonging to another link must be
 * silently ignored instead of mutating that link's blocks). The endpoint
 * returns a redirect with a success/error session flash; validation failures
 * on a JSON request return 422.
 */
class CoachActionTest extends AnalyticsTestCase
{
    private function makeBiolink(User $user): Link
    {
        return $this->makeLink($user, [
            'type'  => 'biolink',
            'alias' => 'bl' . bin2hex(random_bytes(3)),
            'long_url' => null,
        ]);
    }

    private function makeBlock(Link $link, array $overrides = []): BiolinkBlock
    {
        return BiolinkBlock::create(array_merge([
            'link_id'    => $link->id,
            'type'       => 'link',
            'settings'   => ['title' => 'Block', 'url' => 'https://example.com'],
            'sort_order' => 0,
            'is_active'  => true,
        ], $overrides));
    }

    public function test_deactivate_blocks_hides_only_valid_ids_and_ignores_unknown(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeBiolink($owner);

        $b1 = $this->makeBlock($link, ['sort_order' => 0]);
        $b2 = $this->makeBlock($link, ['sort_order' => 1]);
        $b3 = $this->makeBlock($link, ['sort_order' => 2]); // should NOT be touched.

        $response = $this->actingAs($owner)->post(
            route('user.links.coach-action', $link),
            [
                'action_type' => 'deactivate_blocks',
                'block_ids'   => [$b1->id, $b2->id, 999999], // 999999 is bogus
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertFalse($b1->fresh()->is_active);
        $this->assertFalse($b2->fresh()->is_active);
        $this->assertTrue($b3->fresh()->is_active);
    }

    public function test_promote_block_sets_sort_order_zero_and_renumbers_siblings(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeBiolink($owner);

        $b0 = $this->makeBlock($link, ['sort_order' => 0]);
        $b1 = $this->makeBlock($link, ['sort_order' => 1]);
        $b2 = $this->makeBlock($link, ['sort_order' => 2]); // to be promoted
        $b3 = $this->makeBlock($link, ['sort_order' => 3]);

        $response = $this->actingAs($owner)->post(
            route('user.links.coach-action', $link),
            ['action_type' => 'promote_block', 'block_id' => $b2->id]
        );

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertSame(0, $b2->fresh()->sort_order);
        // The other three siblings should be renumbered 1..3 preserving order.
        $this->assertSame(1, $b0->fresh()->sort_order);
        $this->assertSame(2, $b1->fresh()->sort_order);
        $this->assertSame(3, $b3->fresh()->sort_order);
    }

    public function test_enable_block_flips_is_active_true(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($link, ['is_active' => false, 'type' => 'socials']);

        $response = $this->actingAs($owner)->post(
            route('user.links.coach-action', $link),
            ['action_type' => 'enable_block', 'block_id' => $block->id]
        );

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertTrue($block->fresh()->is_active);
    }

    public function test_non_owner_receives_403(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $link  = $this->makeBiolink($owner);
        $block = $this->makeBlock($link);

        $this->actingAs($other)
            ->postJson(route('user.links.coach-action', $link), [
                'action_type' => 'enable_block',
                'block_id'    => $block->id,
            ])
            ->assertStatus(403);

        // The block must not have been touched.
        $this->assertTrue($block->fresh()->is_active);
    }

    public function test_block_ids_scoped_to_another_link_are_silently_ignored(): void
    {
        $owner       = $this->makeUser();
        $victim      = $this->makeUser();
        $ownerLink   = $this->makeBiolink($owner);
        $victimLink  = $this->makeBiolink($victim);

        $ownerBlock  = $this->makeBlock($ownerLink);
        $victimBlock = $this->makeBlock($victimLink); // belongs to someone else.

        // Owner passes THEIR link plus a victim block id. The endpoint must
        // silently ignore the foreign id (scoped to link_id = ownerLink) and
        // not hide the victim's block.
        $response = $this->actingAs($owner)->post(
            route('user.links.coach-action', $ownerLink),
            [
                'action_type' => 'deactivate_blocks',
                'block_ids'   => [$ownerBlock->id, $victimBlock->id],
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertFalse($ownerBlock->fresh()->is_active);
        $this->assertTrue($victimBlock->fresh()->is_active, 'Victim block must remain active.');
    }

    public function test_promote_block_on_another_users_block_does_nothing(): void
    {
        $owner      = $this->makeUser();
        $victim     = $this->makeUser();
        $ownerLink  = $this->makeBiolink($owner);
        $victimLink = $this->makeBiolink($victim);
        $victimBlock = $this->makeBlock($victimLink, ['sort_order' => 5]);

        // Owner tries to promote a block belonging to another user's link.
        // Scoping to ownerLink means the block isn't found — endpoint flashes
        // an error and the victim's block stays put.
        $this->actingAs($owner)->post(
            route('user.links.coach-action', $ownerLink),
            ['action_type' => 'promote_block', 'block_id' => $victimBlock->id]
        )->assertStatus(302);

        $this->assertSame(5, $victimBlock->fresh()->sort_order);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeBiolink($owner);

        $this->actingAs($owner)
            ->postJson(route('user.links.coach-action', $link), ['action_type' => 'nuke_everything'])
            ->assertStatus(422);
    }
}
