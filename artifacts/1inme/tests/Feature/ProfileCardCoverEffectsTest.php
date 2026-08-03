<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\BlockStyleSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6585 — cover blur & color overlay for profile-card blocks.
 * `_cover_blur`, `_cover_overlay_color` and `_cover_overlay_opacity`
 * live in `_style`, are clamped/color-sanitized on save, and render as
 * a blur filter + tint layer over the cover image on the public page.
 */
class ProfileCardCoverEffectsTest extends TestCase
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

    private function createBlock(User $owner, Link $link): BiolinkBlock
    {
        return BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => 'profile_card',
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

    public function test_sanitizer_keeps_valid_values_and_clamps_bounds(): void
    {
        $out = BlockStyleSanitizer::sanitize([
            '_cover_blur'            => '12',
            '_cover_overlay_color'   => '#112233',
            '_cover_overlay_opacity' => '40',
        ]);
        $this->assertEquals(12, $out['_cover_blur']);
        $this->assertSame('#112233', $out['_cover_overlay_color']);
        $this->assertEquals(40, $out['_cover_overlay_opacity']);

        // Out-of-range values clamp to the bounds instead of erroring.
        $out = BlockStyleSanitizer::sanitize([
            '_cover_blur'            => '999',
            '_cover_overlay_opacity' => '150',
        ]);
        $this->assertEquals(40, $out['_cover_blur']);
        $this->assertEquals(100, $out['_cover_overlay_opacity']);
    }

    public function test_sanitizer_drops_zero_and_malformed_values(): void
    {
        // 0 = "off" — the editor's range inputs always submit a value, so
        // the default must never be stamped onto every profile-card save.
        $out = BlockStyleSanitizer::sanitize([
            '_cover_blur'            => '0',
            '_cover_overlay_opacity' => '0',
            '_cover_overlay_color'   => 'javascript:alert(1)',
        ]);
        $this->assertArrayNotHasKey('_cover_blur', $out);
        $this->assertArrayNotHasKey('_cover_overlay_opacity', $out);
        $this->assertArrayNotHasKey('_cover_overlay_color', $out);
    }

    public function test_values_round_trip_through_the_web_editor(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link);

        $this->updateBlock($owner, $link, $block, [
            'settings' => ['name' => 'Jane'],
            'style'    => [
                '_cover_blur'            => '8',
                '_cover_overlay_color'   => '#3d2b1f',
                '_cover_overlay_opacity' => '35',
            ],
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertEquals(8, $style['_cover_blur'] ?? null);
        $this->assertSame('#3d2b1f', $style['_cover_overlay_color'] ?? null);
        $this->assertEquals(35, $style['_cover_overlay_opacity'] ?? null);

        // Empty submits clear the keys again.
        $this->updateBlock($owner, $link, $block, [
            'style' => [
                '_cover_blur'            => '',
                '_cover_overlay_color'   => '',
                '_cover_overlay_opacity' => '',
            ],
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertArrayNotHasKey('_cover_blur', $style);
        $this->assertArrayNotHasKey('_cover_overlay_color', $style);
        $this->assertArrayNotHasKey('_cover_overlay_opacity', $style);
    }

    /**
     * Cover-based layouts render the blur filter on the cover layer and
     * the tint overlay directly above it.
     */
    public function test_public_page_renders_blur_and_tint_for_cover_layouts(): void
    {
        foreach (['arch_band', 'cover_hero', 'classic_creator'] as $layout) {
            $owner = $this->makeOwner();
            $link  = $this->makeBiolink($owner);
            $block = $this->createBlock($owner, $link);

            $settings = $block->settings;
            $settings['name']  = 'Jane Doe';
            $settings['cover'] = 'https://example.com/cover.jpg';
            $settings['_style']['_profile_layout'] = $layout;
            $settings['_style']['_cover_blur'] = 6;
            $settings['_style']['_cover_overlay_color'] = '#8a5a3b';
            $settings['_style']['_cover_overlay_opacity'] = 45;
            unset($settings['_placeholder']);
            $block->update(['settings' => $settings, 'is_active' => true]);

            app()->forgetInstance('current_workspace');
            app()->forgetInstance('workspace_owner');
            $resp = $this->get('/' . $link->alias);
            $resp->assertOk();
            $html = $resp->getContent();
            $this->assertStringContainsString('filter:blur(6px)', $html, "layout {$layout} renders the blur");
            $this->assertStringContainsString('background:#8a5a3b;opacity:0.45', $html, "layout {$layout} renders the tint overlay");
        }
    }

    /**
     * A layout with a built-in overlay (founder) keeps its default wash
     * when the new keys are absent — and swaps it for the user tint when
     * set.
     */
    public function test_builtin_overlay_layout_defaults_unchanged_and_overridable(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link);

        $settings = $block->settings;
        $settings['name']  = 'Jane Doe';
        $settings['cover'] = 'https://example.com/cover.jpg';
        $settings['_style']['_profile_layout'] = 'founder';
        unset($settings['_placeholder']);
        $block->update(['settings' => $settings, 'is_active' => true]);

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        $this->assertStringContainsString('linear-gradient(160deg,rgba(0,0,0,0.75),rgba(0,0,0,0.92))', $html);
        // No blur set: the founder cover layer's style ends at its
        // built-in opacity (other page CSS legitimately contains
        // `filter:blur(`, so assert on the cover element itself).
        $this->assertStringContainsString("opacity:.35;\"", $html);

        $settings['_style']['_cover_overlay_color'] = '#123456';
        $settings['_style']['_cover_overlay_opacity'] = 60;
        $block->update(['settings' => $settings]);

        $html = $this->get('/' . $link->alias)->assertOk()->getContent();
        $this->assertStringContainsString('background:#123456;opacity:0.6', $html);
        $this->assertStringNotContainsString('linear-gradient(160deg,rgba(0,0,0,0.75),rgba(0,0,0,0.92))', $html);
    }
}
