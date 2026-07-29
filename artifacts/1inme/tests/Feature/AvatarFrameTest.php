<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\AvatarFrameCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #5910 — decorative avatar frames for biolink profile-card blocks.
 * The frame key + optional tint live in the block's `_style`
 * (`_avatar_frame` / `_avatar_frame_color`), are enum/color-sanitized on
 * save, and render as an inline SVG wrapper behind circular avatars on
 * the public page.
 */
class AvatarFrameTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $user = User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'own' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($user);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $user);

        return $user;
    }

    private function makeBiolink(User $owner): Link
    {
        return Link::create([
            'user_id'   => $owner->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'My Bio',
            'is_active' => true,
        ]);
    }

    private function createBlock(User $owner, Link $link, string $type): BiolinkBlock
    {
        // Created directly (the create endpoint's plan gating is out of
        // scope here) — what matters is the update path's sanitizer.
        return BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => $type,
            'sort_order' => 0,
            'is_active'  => true,
            'settings'   => ['name' => 'Owner', '_style' => []],
        ]);
    }

    private function updateBlock(User $owner, Link $link, BiolinkBlock $block, array $payload)
    {
        return $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$link->id}/blocks/{$block->id}", $payload);
    }

    public function test_catalog_exposes_the_expected_frames(): void
    {
        $this->assertSame(
            ['starburst', 'scalloped', 'zigzag', 'wavy', 'double_ring', 'dotted_ring', 'petal'],
            AvatarFrameCatalog::keys()
        );
        $this->assertTrue(AvatarFrameCatalog::isValid('starburst'));
        $this->assertFalse(AvatarFrameCatalog::isValid('hexagon'));

        foreach (AvatarFrameCatalog::keys() as $key) {
            $svg = AvatarFrameCatalog::svg($key, '#7d9bff');
            $this->assertStringContainsString('<svg', $svg, "frame {$key} renders an svg");
            $this->assertStringContainsString('#7d9bff', $svg, "frame {$key} carries the tint");
        }

        // Unsafe colors are rejected outright rather than injected verbatim.
        $this->assertNull(AvatarFrameCatalog::svg('starburst', '"><script>alert(1)</script>'));
    }

    public function test_valid_frame_and_color_persist_via_the_web_editor(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'profile_card');

        $this->updateBlock($owner, $link, $block, [
            'settings' => ['name' => 'Jane'],
            'style'    => [
                '_avatar_frame'       => 'scalloped',
                '_avatar_frame_color' => '#ff8800',
            ],
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertSame('scalloped', $style['_avatar_frame'] ?? null);
        $this->assertSame('#ff8800', $style['_avatar_frame_color'] ?? null);
    }

    public function test_unknown_frame_key_is_stripped_silently(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'profile_card');

        $this->updateBlock($owner, $link, $block, [
            'style' => ['_avatar_frame' => 'not_a_frame'],
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertArrayNotHasKey('_avatar_frame', $style);
    }

    public function test_empty_value_clears_a_saved_frame(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'profile_card');

        $this->updateBlock($owner, $link, $block, [
            'style' => [
                '_avatar_frame'       => 'petal',
                '_avatar_frame_color' => '#123456',
            ],
        ])->assertOk();
        $this->assertSame('petal', $block->fresh()->settings['_style']['_avatar_frame'] ?? null);

        $this->updateBlock($owner, $link, $block, [
            'style' => [
                '_avatar_frame'       => '',
                '_avatar_frame_color' => '',
            ],
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertArrayNotHasKey('_avatar_frame', $style);
        $this->assertArrayNotHasKey('_avatar_frame_color', $style);
    }

    public function test_public_page_renders_the_frame_wrapper(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'profile_card');

        $settings = $block->settings;
        $settings['name'] = 'Jane Doe';
        $settings['_style']['_avatar_frame'] = 'starburst';
        $settings['_style']['_avatar_frame_color'] = '#ff8800';
        unset($settings['_placeholder']);
        $block->update(['settings' => $settings, 'is_active' => true]);

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
        $resp = $this->get('/' . $link->alias);
        $resp->assertOk();
        $html = $resp->getContent();
        $this->assertStringContainsString('data-avatar-frame="starburst"', $html);
        $this->assertStringContainsString('#ff8800', $html);
    }

    public function test_public_page_has_no_frame_wrapper_by_default(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'profile_card');

        $settings = $block->settings;
        $settings['name'] = 'Jane Doe';
        unset($settings['_placeholder']);
        $block->update(['settings' => $settings, 'is_active' => true]);

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
        $resp = $this->get('/' . $link->alias);
        $resp->assertOk();
        $this->assertStringNotContainsString('data-avatar-frame', $resp->getContent());
    }
}
