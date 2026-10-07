<?php

namespace Tests\Unit;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockStyleSanitizer;
use PHPUnit\Framework\TestCase;

class BlockElementMotionTest extends TestCase
{
    public function test_motion_is_allowlisted_and_rendered(): void
    {
        foreach (['float', 'breathe', 'reveal'] as $motion) {
            $style = BlockStyleSanitizer::sanitize(['_motion' => $motion]);
            $this->assertSame($motion, $style['_motion']);
            $this->assertStringContainsString('animation:sz-element-'.$motion, BiolinkBlock::buildInlineStyle($style));
        }
        $this->assertArrayNotHasKey('_motion', BlockStyleSanitizer::sanitize(['_motion' => 'arbitrary-css']));
        $this->assertStringNotContainsString('animation:', BiolinkBlock::buildInlineStyle(['_motion' => 'none']));
    }
}
