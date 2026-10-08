<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use Tests\TestCase;

class BlockTextColorRenderingTest extends TestCase
{
    public function test_inner_text_uses_the_block_color_instead_of_page_ink(): void
    {
        $block = new BiolinkBlock(['type' => 'paragraph', 'settings' => ['text' => 'Readable text', '_style' => ['text_color' => '#123456']]]);
        $block->id = 123;
        $html = view('common.partials.biolink-block-render', ['link' => new Link, 'block' => $block, 's' => $block->settings, 'fontColor' => '#ffffff', 'globalTheme' => []])->render();
        $this->assertStringContainsString('color: #123456', $html);
        $this->assertStringNotContainsString('color: #ffffff', $html);
    }

    public function test_secondary_product_text_supports_non_hex_color_opacity(): void
    {
        foreach (['#123', 'rgb(12, 34, 56)', 'navy'] as $color) {
            $html = view('common.blocks.product', ['s' => ['name' => 'Kit', 'description' => 'Details'], 'fontColor' => $color])->render();
            $this->assertStringContainsString('color-mix(in srgb, '.$color.' 53.33%, transparent)', $html);
            $this->assertStringNotContainsString($color.'88', $html);
        }
    }

    public function test_accent_colors_remain_valid_in_shadows_and_pricing_variants(): void
    {
        foreach (['#123', 'rgb(12, 34, 56)', 'navy'] as $color) {
            $block = new BiolinkBlock(['type' => 'cta_button', 'settings' => []]);
            $html = view('common.blocks.cta-button', [
                'block' => $block, 's' => ['text' => 'Book a call', 'color' => $color],
            ])->render();
            $this->assertStringContainsString('color-mix(in srgb, '.$color.' 25.1%, transparent)', $html);
            foreach (['menu', 'cards', 'featured'] as $style) {
                $html = view('common.blocks.list-pricing', [
                    'fontColor' => '#172033', 'btnColor' => $color,
                    's' => ['style' => $style, 'items' => [
                        ['name' => 'Studio', 'price' => '$29', 'featured' => true, 'icon' => 'fa-star'],
                    ]],
                ])->render();
                $this->assertStringContainsString('Studio', $html);
                $this->assertStringContainsString('color-mix(in srgb, '.$color, $html);
                foreach (['22', 'dd', '88'] as $suffix) {
                    $this->assertStringNotContainsString($color.$suffix, $html);
                }
            }
        }
    }
    public function test_profile_details_colors_survive_sanitizing_and_render_in_every_layout(): void
    {
        $style = [
            'text_color' => '#123456', 'bg_color' => '#102030',
            '_profile_details_bg' => '#203040', '_profile_details_text' => '#fedcba',
            '_profile_accent_color' => '#abcdef', '_profile_cta_bg' => '#345678',
            '_profile_cta_text' => '#ffffff',
        ];
        $this->assertEquals($style, \App\Modules\User\Support\BlockStyleSanitizer::sanitize($style));
        foreach (['cover_hero', 'classic_creator', 'glass', 'founder', 'minimal_dark', 'business_card', 'id_badge', 'ticket_stub', 'polaroid', 'terminal', 'stats', 'badges'] as $layout) {
            $block = new BiolinkBlock(['type' => 'profile_card_v2', 'settings' => [
                'name' => 'Alex', 'title' => 'Designer', 'bio' => 'Studio founder',
                'location' => 'Hyderabad', 'website' => 'https://example.com',
                'cta_label' => 'Contact', 'cta_url' => 'https://example.com/contact',
                '_style' => $style + ['_profile_layout' => $layout],
            ]]);
            $html = view('common.biolink-profile-card', [
                'block' => $block, 's' => $block->settings, 'blockStyle' => $style,
                'blockInline' => 'background:#102030;color:#123456;',
                'fontColor' => '#123456', 'socialIcons' => [],
            ])->render();
            $this->assertStringContainsString('--profile-ink:#123456', $html, $layout);
            $this->assertStringContainsString('background:#203040;color:#fedcba', $html, $layout);
            $this->assertStringContainsString('background:#345678', $html, $layout);
            $this->assertStringContainsString('color:#ffffff', $html, $layout);
        }
    }

    public function test_inner_card_surfaces_honor_explicit_backgrounds(): void
    {
        foreach (['service', 'testimonials', 'product', 'booking-slots', 'file-list', 'audio-list', 'event-list', 'affiliate-links'] as $partial) {
            $block = new BiolinkBlock(['type' => 'product']);
            $block->id = 123;
            $html = view('common.blocks.'.$partial, [
                'block' => $block, 'link' => new Link, 'fontColor' => '#abcdef',
                's' => ['_style' => ['bg_color' => '#102030'], 'items' => [['name' => 'Alex', 'text' => 'Great work']]],
            ])->render();
            $this->assertStringContainsString('background:#102030;color:#abcdef', $html, $partial);
        }
    }

}
