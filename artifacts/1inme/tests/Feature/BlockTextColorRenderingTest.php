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
}
