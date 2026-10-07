<?php

namespace Tests\Unit\Support;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockRenderCoverage;
use App\Modules\User\Support\BlockTypeRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Guards the FLAT-row placement check ({@see BlockRenderCoverage::flatRowGaps()})
 * that powers `biolink:check-block-placements` and the editor store/move
 * guards: it derives each persisted block's placement from its parent and
 * reports any block that would render blank where it sits.
 *
 * Root and child placements now share one renderer. Every registered type
 * must be supported in both placements.
 */
class BlockRenderCoverageFlatRowGapsTest extends TestCase
{
    public function test_every_registered_type_renders_at_root_and_inside_a_container(): void
    {
        $known = array_unique(array_merge(array_keys(BiolinkBlock::TYPES), array_keys(BlockTypeRegistry::newTypes())));
        foreach ($known as $type) {
            $this->assertSame([], BlockRenderCoverage::flatRowGaps([
                ['id' => 1, 'type' => $type, 'parent_id' => null],
                ['id' => 2, 'type' => 'card', 'parent_id' => null],
                ['id' => 3, 'type' => $type, 'parent_id' => 2],
            ]));
        }
    }

    public function test_skips_unknown_and_typeless_rows(): void
    {
        $gaps = BlockRenderCoverage::flatRowGaps([
            ['id' => 1, 'type' => 'totally_unknown_type', 'parent_id' => null],
            ['id' => 2, 'type' => null, 'parent_id' => null],
            ['id' => 3, 'parent_id' => null],
        ]);

        $this->assertSame([], $gaps);
    }

}
