<?php

namespace Tests\Unit;

use App\Modules\User\Models\BiolinkBlock;
use PHPUnit\Framework\TestCase;

/**
 * Task #6114 — full-width blocks: the public page has no horizontal
 * padding; side spacing is a default per-block margin. An explicit 0
 * margin is a real value (edge-to-edge), and the top-level render path
 * skips horizontal margins (they live on the block wrap instead).
 */
class BiolinkBlockMarginInlineStyleTest extends TestCase
{
    public function test_explicit_zero_margins_are_emitted(): void
    {
        $css = BiolinkBlock::buildInlineStyle([
            'margin_top' => '0', 'margin_bottom' => '0',
            'margin_left' => '0', 'margin_right' => '0',
        ]);

        $this->assertStringContainsString('margin-top:0px', $css);
        $this->assertStringContainsString('margin-bottom:0px', $css);
        $this->assertStringContainsString('margin-left:0px', $css);
        $this->assertStringContainsString('margin-right:0px', $css);
    }

    public function test_empty_margins_are_not_emitted(): void
    {
        $css = BiolinkBlock::buildInlineStyle([
            'margin_top' => '', 'margin_bottom' => '',
            'margin_left' => '', 'margin_right' => '',
        ]);

        $this->assertStringNotContainsString('margin-', $css);
    }

    public function test_skip_horizontal_margins_keeps_vertical_only(): void
    {
        $css = BiolinkBlock::buildInlineStyle([
            'margin_top' => '8', 'margin_bottom' => '-4',
            'margin_left' => '0', 'margin_right' => '12',
        ], true);

        $this->assertStringContainsString('margin-top:8px', $css);
        $this->assertStringContainsString('margin-bottom:-4px', $css);
        $this->assertStringNotContainsString('margin-left', $css);
        $this->assertStringNotContainsString('margin-right', $css);
    }
}
