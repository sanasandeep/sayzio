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
 * Task #6173 — unified `_style` styling for card container blocks.
 *
 * Cards keep rendering their legacy chrome (bg_type/border/shadow/padding)
 * byte-identically when no `_style` is set; once the unified picker writes
 * `_style`, its inline CSS is appended after the legacy declarations so it
 * wins property-by-property.
 */
class CardContainerUnifiedStyleTest extends TestCase
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

    private function publicGet(Link $link)
    {
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        return $this->get('/' . $link->alias);
    }

    public function test_legacy_card_settings_render_unchanged_without_style(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'card');

        $this->updateBlock($owner, $link, $block, [
            'settings' => [
                'bg_type'       => 'color',
                'bg_color'      => '#1a1a2e',
                'padding'       => '20',
                'border_radius' => '10',
                'border_width'  => '2',
                'border_color'  => '#ff0000',
                'shadow'        => 'md',
                'shadow_color'  => '#00000040',
            ],
        ])->assertOk();

        $resp = $this->publicGet($link);
        $resp->assertOk();
        $resp->assertSee('card-container-render', false);
        $resp->assertSee('background:#1a1a2e;', false);
        $resp->assertSee('padding:20px; border-radius:10px; border:2px solid #ff0000; box-shadow:0 4px 12px #00000040;', false);
    }

    public function test_unified_style_appends_after_legacy_chrome(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'card');

        $this->updateBlock($owner, $link, $block, [
            'settings' => [
                'bg_type'  => 'color',
                'bg_color' => '#1a1a2e',
            ],
            'style' => [
                'display_mode'     => 'card',
                'bg_color'         => '#224466',
                'border_radius_tl' => '24',
                'border_top_style' => 'solid',
                'border_top_width' => '3',
                'border_top_color' => '#123456',
                'padding_top'      => '30',
                'margin_top'       => '18',
            ],
        ])->assertOk();

        $resp = $this->publicGet($link);
        $resp->assertOk();
        $html = $resp->getContent();

        // Legacy chrome still present, unified declarations appended after
        // it inside the same style attribute (CSS last-wins).
        $this->assertStringContainsString('background:#1a1a2e;', $html);
        $this->assertStringContainsString('background-color:#224466', $html);
        $legacyPos  = strpos($html, 'background:#1a1a2e;');
        $unifiedPos = strpos($html, 'background-color:#224466');
        $this->assertGreaterThan($legacyPos, $unifiedPos);

        $this->assertStringContainsString('border-top-left-radius:24px', $html);
        $this->assertStringContainsString('border-top:3px solid #123456', $html);
        $this->assertStringContainsString('padding-top:30px', $html);
        $this->assertStringContainsString('margin-top:18px', $html);
    }

    public function test_card_with_style_gets_no_block_styled_wrapper(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'card');

        $this->updateBlock($owner, $link, $block, [
            'style' => ['bg_color' => '#224466'],
        ])->assertOk();

        $resp = $this->publicGet($link);
        $resp->assertOk();
        $html = $resp->getContent();

        // The unified style rides on .card-container-render itself — a
        // .block-styled wrapper around the card would double-apply chrome.
        // The card is the page's only block, so no styled-wrapper div may
        // exist anywhere (CSS selectors mentioning .block-styled are fine).
        $this->assertStringContainsString('card-container-render', $html);
        $this->assertStringNotContainsString('<div class="block-styled"', $html);
    }

    public function test_glass_effect_and_gradient_background_from_unified_style(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link, 'card');

        $this->updateBlock($owner, $link, $block, [
            'style' => [
                'bg_color' => 'linear-gradient(135deg, #3d6bff, #ec4899)',
                'effect'   => 'glass',
            ],
        ])->assertOk();

        $resp = $this->publicGet($link);
        $resp->assertOk();
        $html = $resp->getContent();
        $this->assertStringContainsString('linear-gradient(135deg, #3d6bff, #ec4899)', $html);
        $this->assertStringContainsString('backdrop-filter', $html);
    }
}
