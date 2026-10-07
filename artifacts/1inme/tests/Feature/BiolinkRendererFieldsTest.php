<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use Tests\TestCase;

class BiolinkRendererFieldsTest extends TestCase
{
    public function test_video_honors_loop_and_muted_without_autoplay(): void
    {
        $html = view('common.blocks.video', ['s' => ['url' => '/demo.mp4', 'loop' => true, 'muted' => true, 'autoplay' => false]])->render();
        $this->assertStringContainsString(' loop ', $html);
        $this->assertStringContainsString(' muted ', $html);
        $this->assertStringNotContainsString(' autoplay ', $html);
    }

    public function test_native_product_displays_badge_price_and_checkout_action(): void
    {
        $block = new BiolinkBlock;
        $block->id = 123;
        foreach ([false => 'buy', true => 'add'] as $multiple => $action) {
            $html = view('common.blocks.product', [
                's' => ['name' => 'Design Kit', 'badge' => 'New', 'native_checkout' => true,
                    'price_cents' => 2900, 'currency' => 'USD', 'url' => 'https://example.com'],
                'block' => $block, 'fontColor' => '#172033',
                '__storeMultiple' => (bool) $multiple,
            ])->render();
            $this->assertStringContainsString('New', $html);
            $this->assertStringContainsString('USD 29.00', $html);
            $this->assertStringContainsString('$store.bioStore.'.$action.'(123)', $html);
            $this->assertStringNotContainsString('href="https://example.com"', $html);
        }
    }
}
