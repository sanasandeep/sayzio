<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6038 — advanced borders: per-corner radius + per-side
 * style/width/color, falling back field-by-field to the shorthand values.
 */
class BiolinkBlockAdvancedBordersTest extends TestCase
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
        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => $type]);

        $resp->assertOk();

        return BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
    }

    private function updateBlock(User $owner, Link $link, BiolinkBlock $block, array $payload)
    {
        return $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$link->id}/blocks/{$block->id}", $payload);
    }

    public function test_advanced_border_keys_round_trip_through_sanitizer(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'heading');

        $this->updateBlock($owner, $link, $block, [
            'style' => [
                'border_radius_tl'    => '20',
                'border_radius_br'    => '0',
                'border_top_style'    => 'dashed',
                'border_top_width'    => '3',
                'border_top_color'    => '#112233',
                'border_bottom_style' => 'none',
            ],
        ])->assertOk();

        $style = $block->fresh()->settings['_style'];
        $this->assertSame(20, (int) $style['border_radius_tl']);
        $this->assertSame(0, (int) $style['border_radius_br']);
        $this->assertSame('dashed', $style['border_top_style']);
        $this->assertSame(3, (int) $style['border_top_width']);
        $this->assertSame('#112233', $style['border_top_color']);
        $this->assertSame('none', $style['border_bottom_style']);
        // Untouched keys stay absent.
        $this->assertArrayNotHasKey('border_left_width', $style);
    }

    public function test_out_of_bounds_and_invalid_values_are_sanitized(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'heading');

        $this->updateBlock($owner, $link, $block, [
            'style' => [
                'border_radius_tr'  => '5000',        // clamped to 999
                'border_left_width' => '99',          // clamped to 10
                'border_top_style'  => 'wavy',        // not in enum → dropped
                'border_top_color'  => 'javascript:x',// not a color → dropped
            ],
        ])->assertOk();

        $style = $block->fresh()->settings['_style'];
        $this->assertSame(999, (int) $style['border_radius_tr']);
        $this->assertSame(10, (int) $style['border_left_width']);
        $this->assertArrayNotHasKey('border_top_style', $style);
        $this->assertArrayNotHasKey('border_top_color', $style);
    }

    public function test_inline_style_per_corner_falls_back_to_shorthand(): void
    {
        $css = BiolinkBlock::buildInlineStyle(array_merge(BiolinkBlock::STYLE_DEFAULTS, [
            'display_mode'     => 'card',
            'border_radius'    => '12',
            'border_radius_tr' => '0',
        ]));

        $this->assertStringContainsString('border-top-left-radius:12px', $css);
        $this->assertStringContainsString('border-top-right-radius:0px', $css);
        $this->assertStringContainsString('border-bottom-left-radius:12px', $css);
        $this->assertStringContainsString('border-bottom-right-radius:12px', $css);
        $this->assertStringNotContainsString('border-radius:12px', $css);
    }

    public function test_inline_style_per_side_overrides_and_none_removes_a_side(): void
    {
        $css = BiolinkBlock::buildInlineStyle(array_merge(BiolinkBlock::STYLE_DEFAULTS, [
            'display_mode'        => 'card',
            'border_style'        => 'solid',
            'border_width'        => '2',
            'border_color'        => '#ff0000',
            'border_bottom_style' => 'none',
            'border_left_width'   => '5',
            'border_left_color'   => '#00ff00',
        ]));

        $this->assertStringContainsString('border-top:2px solid #ff0000', $css);
        $this->assertStringContainsString('border-right:2px solid #ff0000', $css);
        $this->assertStringContainsString('border-bottom:none', $css);
        $this->assertStringContainsString('border-left:5px solid #00ff00', $css);
    }

    public function test_inline_style_shorthand_only_keeps_legacy_output(): void
    {
        $css = BiolinkBlock::buildInlineStyle(array_merge(BiolinkBlock::STYLE_DEFAULTS, [
            'display_mode'  => 'card',
            'border_radius' => '12',
            'border_style'  => 'solid',
            'border_width'  => '2',
            'border_color'  => '#ff0000',
        ]));

        $this->assertStringContainsString('border-radius:12px', $css);
        $this->assertStringContainsString('border:2px solid #ff0000', $css);
        $this->assertStringNotContainsString('border-top-left-radius', $css);
        $this->assertStringNotContainsString('border-top:', $css);
    }

    public function test_public_page_renders_advanced_border_css(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'heading');

        $this->updateBlock($owner, $link, $block, [
            'settings' => ['text' => 'Bordered heading'],
            'style'    => [
                'display_mode'     => 'card',
                'border_radius_tl' => '24',
                'border_top_style' => 'solid',
                'border_top_width' => '2',
                'border_top_color' => '#123456',
            ],
        ])->assertOk();

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $resp = $this->get('/' . $link->alias);
        $resp->assertOk();
        $resp->assertSee('border-top-left-radius:24px', false);
        $resp->assertSee('border-top:2px solid #123456', false);
    }
}
