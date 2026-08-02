<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\BlockVariantCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Rich countdown block redesign — covers the sanitizer round-trip of the
 * new configurable settings (unit toggles, label style, expired behaviour,
 * CTA) plus applying one of the 10 curated design variants.
 */
class CountdownBlockSettingsTest extends TestCase
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
        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'countdown']);
        $resp->assertOk();

        return BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
    }

    private function updateBlock(User $owner, Link $link, BiolinkBlock $block, array $payload)
    {
        return $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$link->id}/blocks/{$block->id}", $payload);
    }

    public function test_new_countdown_settings_round_trip(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link);

        $this->updateBlock($owner, $link, $block, [
            'settings' => [
                'title'           => 'Launch',
                'subtitle'        => 'Almost there',
                'target_date'     => '2030-01-01T00:00',
                'show_days'       => '1',
                'show_hours'      => '1',
                'show_minutes'    => '0', // hidden hidden-input value = unchecked
                'show_seconds'    => '0',
                'label_style'     => 'short',
                'expired_action'  => 'hide_block',
                'expired_message' => 'Done!',
                'button_text'     => 'Buy now',
                'button_url'      => 'https://shop.example.com',
            ],
        ])->assertOk();

        $s = $block->fresh()->settings;
        $this->assertSame('Launch', $s['title']);
        $this->assertSame('Almost there', $s['subtitle']);
        $this->assertTrue($s['show_days']);
        $this->assertTrue($s['show_hours']);
        $this->assertFalse($s['show_minutes']);
        $this->assertFalse($s['show_seconds']);
        $this->assertSame('short', $s['label_style']);
        $this->assertSame('hide_block', $s['expired_action']);
        $this->assertSame('Done!', $s['expired_message']);
        $this->assertSame('Buy now', $s['button_text']);
        $this->assertSame('https://shop.example.com', $s['button_url']);
    }

    public function test_invalid_enums_fall_back_and_empty_expired_message_defaults(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link);

        $this->updateBlock($owner, $link, $block, [
            'settings' => [
                'label_style'     => 'rainbow',
                'expired_action'  => 'explode',
                'expired_message' => '   ',
            ],
        ])->assertOk();

        $s = $block->fresh()->settings;
        $this->assertSame('full', $s['label_style']);
        $this->assertSame('message', $s['expired_action']);
        $this->assertSame("Time's up!", $s['expired_message']);
    }

    public function test_countdown_color_style_keys_round_trip(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link);

        $this->updateBlock($owner, $link, $block, [
            'style' => [
                '_countdown_digit_color' => '#facc15',
                '_countdown_label_color' => '#a1a1aa',
                '_countdown_box_bg'      => '#18181b',
                // A bogus color key value is dropped by the sanitizer.
                'text_color'             => 'not-a-color',
            ],
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertSame('#facc15', $style['_countdown_digit_color'] ?? null);
        $this->assertSame('#a1a1aa', $style['_countdown_label_color'] ?? null);
        $this->assertSame('#18181b', $style['_countdown_box_bg'] ?? null);
        $this->assertArrayNotHasKey('text_color', $style);
    }

    public function test_catalog_exposes_ten_distinct_countdown_variants(): void
    {
        $expected = ['flip_clock', 'pixel_clock', 'minimal_inline', 'glass_cards',
                     'neon_glow', 'gradient_pop_cd', 'soft_pastel_cd', 'bold_blocks',
                     'outline_ring', 'elegant_serif'];
        $keys = BlockVariantCatalog::validKeys('countdown');
        // Countdown adds 10 type-specific variants on top of the shared
        // common-variant pool; assert each resolves to a real entry that
        // carries the countdown-specific color overrides.
        $this->assertCount(10, $expected);
        foreach ($expected as $key) {
            $this->assertContains($key, $keys, "missing countdown variant {$key}");
            $variant = BlockVariantCatalog::find('countdown', $key);
            $this->assertNotNull($variant, "find() returned null for {$key}");
            $this->assertArrayHasKey('_countdown_digit_color', $variant['style'], "{$key} missing digit color");
        }
    }

    public function test_applying_a_variant_writes_expected_style(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createBlock($owner, $link);

        $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks/{$block->id}/apply-variant", [
                'variant' => 'gradient_pop_cd',
            ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertSame('gradient_pop_cd', $style['_variant'] ?? null);
        $this->assertStringContainsString('linear-gradient', (string) ($style['bg_color'] ?? ''));
        $this->assertSame('#ffffff', $style['_countdown_digit_color'] ?? null);
    }

    /**
     * Regression: subtitle / unit labels must never append a hex-alpha
     * suffix (e.g. "b3", "99") to the label color. Several variants use an
     * rgba(...) label color, where a suffix would emit invalid CSS and hide
     * the text. Render the partial for every variant (plus a raw rgba label)
     * and assert the emitted styles are well-formed and use `opacity`.
     */
    public function test_rgba_label_colors_render_valid_css_for_all_variants(): void
    {
        $block = new BiolinkBlock(['type' => 'countdown']);
        $block->id = 4242;

        foreach (BlockVariantCatalog::validKeys('countdown') as $key) {
            $variant = BlockVariantCatalog::find('countdown', $key);
            $this->assertNotNull($variant);

            $settings = [
                'title'       => 'Launch',
                'subtitle'    => 'Almost there',
                'target_date' => '2030-01-01T00:00',
                'label_style' => 'full',
                'button_text' => 'Buy',
                'button_url'  => 'https://example.com',
                '_style'      => $variant['style'],
            ];
            $block->settings = $settings;

            $html = view('common.blocks.countdown', [
                'block'     => $block,
                's'         => $settings,
                'link'      => null,
                'fontColor' => '#ffffff',
                'btnInline' => '',
            ])->render();

            // The old bug appended a hex-alpha suffix to the label color. If
            // that color is an rgba(...) value, the suffix produced the
            // literal, always-invalid `rgba(...)b3` / `rgba(...)99`.
            $this->assertStringNotContainsString(')b3', $html, "variant {$key} emitted rgba(...)b3");
            $this->assertStringNotContainsString(')99', $html, "variant {$key} emitted rgba(...)99");
            // Dimmed labels use CSS opacity, not color math — and the label
            // color is emitted verbatim (never with a trailing alpha).
            $labelColor = $variant['style']['_countdown_label_color'] ?? '';
            if ($labelColor !== '' && $labelColor !== 'transparent') {
                $this->assertStringContainsString("color:{$labelColor};opacity:0.7;", $html, "variant {$key} subtitle color/opacity");
                $this->assertStringContainsString("color:{$labelColor};opacity:0.6;", $html, "variant {$key} unit-label color/opacity");
            }
            $this->assertStringContainsString('opacity:0.7;', $html, "variant {$key} subtitle opacity missing");
            $this->assertStringContainsString('opacity:0.6;', $html, "variant {$key} unit-label opacity missing");
            // CTA text color must never be a gradient string.
            $this->assertDoesNotMatchRegularExpression(
                '/color:\s*(linear|radial|conic)-gradient\(/i',
                $html,
                "variant {$key} used a gradient as a CSS color value"
            );

            // CTA button must be visible: bg and text color must differ, and
            // neither may be a gradient. Regression for the "white pill,
            // white text" invisible button on glass/gradient variants.
            if (preg_match('/style="background:([^;]+);color:([^;]+);padding:10px 22px/', $html, $m)) {
                $ctaBg = strtolower(trim($m[1]));
                $ctaText = strtolower(trim($m[2]));
                $this->assertNotSame($ctaBg, $ctaText, "variant {$key} CTA bg equals text color (invisible)");
                $this->assertStringNotContainsString('gradient(', $ctaBg, "variant {$key} CTA bg is a gradient");
                $this->assertStringNotContainsString('gradient(', $ctaText, "variant {$key} CTA text is a gradient");
            } else {
                $this->fail("variant {$key} did not render a CTA button");
            }
        }
    }

    /**
     * Regression: glass/gradient variants previously derived the CTA from the
     * (white) digit color + a near-white box bg, producing an invisible
     * "white pill, white text" button. They now ship explicit CTA colors.
     */
    public function test_cta_colors_are_high_contrast_for_glass_and_gradient(): void
    {
        foreach (['glass_cards', 'gradient_pop_cd', 'minimal_inline'] as $key) {
            $variant = BlockVariantCatalog::find('countdown', $key);
            $this->assertNotNull($variant);
            $this->assertArrayHasKey('_countdown_cta_bg', $variant['style'], "{$key} missing CTA bg");
            $this->assertArrayHasKey('_countdown_cta_text', $variant['style'], "{$key} missing CTA text");
            $this->assertNotSame(
                strtolower($variant['style']['_countdown_cta_bg']),
                strtolower($variant['style']['_countdown_cta_text']),
                "{$key} CTA bg equals text"
            );
        }
    }

    public function test_explicit_rgba_label_color_uses_opacity_not_hex_suffix(): void
    {
        $block = new BiolinkBlock(['type' => 'countdown']);
        $block->id = 99;
        $settings = [
            'title'       => 'Sale',
            'subtitle'    => 'Ends soon',
            'target_date' => '2030-01-01T00:00',
            '_style'      => [
                '_countdown_label_color' => 'rgba(255,255,255,0.7)',
                '_countdown_digit_color' => '#ffffff',
            ],
        ];
        $block->settings = $settings;

        $html = view('common.blocks.countdown', [
            'block'     => $block,
            's'         => $settings,
            'link'      => null,
            'fontColor' => '#ffffff',
            'btnInline' => '',
        ])->render();

        $this->assertStringNotContainsString('rgba(255,255,255,0.7)b3', $html);
        $this->assertStringContainsString('color:rgba(255,255,255,0.7);opacity:0.7;', $html);
    }
}
