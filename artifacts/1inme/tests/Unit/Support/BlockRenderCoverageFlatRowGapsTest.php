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
 * Placement-exclusive types are discovered from the live renderers (not
 * hard-coded) so the test keeps working as renderer branches drift.
 */
class BlockRenderCoverageFlatRowGapsTest extends TestCase
{
    /**
     * @return array{0:?string,1:?string} [childOnly, topOnly]
     */
    private function exclusiveTypes(): array
    {
        $known = array_merge(
            array_keys(BiolinkBlock::TYPES),
            array_keys(BlockTypeRegistry::newTypes())
        );

        $childOnly = null;
        $topOnly = null;

        foreach ($known as $type) {
            $top = BlockRenderCoverage::rendersTopLevel($type);
            $child = BlockRenderCoverage::rendersAsChild($type);

            if ($child && ! $top && $childOnly === null) {
                $childOnly = $type;
            }
            if ($top && ! $child && $topOnly === null) {
                $topOnly = $type;
            }
        }

        return [$childOnly, $topOnly];
    }

    public function test_flags_child_only_block_at_page_root(): void
    {
        [$childOnly] = $this->exclusiveTypes();
        $this->assertNotNull($childOnly);

        $gaps = BlockRenderCoverage::flatRowGaps([
            ['id' => 1, 'type' => $childOnly, 'parent_id' => null],
        ]);

        $this->assertCount(1, $gaps);
        $this->assertSame(1, $gaps[0]['id']);
        $this->assertSame($childOnly, $gaps[0]['type']);
        $this->assertSame('top-level', $gaps[0]['placement']);
    }

    public function test_flags_top_only_block_inside_a_container(): void
    {
        [, $topOnly] = $this->exclusiveTypes();
        $this->assertNotNull($topOnly);

        $gaps = BlockRenderCoverage::flatRowGaps([
            ['id' => 10, 'type' => 'card', 'parent_id' => null],
            ['id' => 11, 'type' => $topOnly, 'parent_id' => 10],
        ]);

        $this->assertCount(1, $gaps);
        $this->assertSame(11, $gaps[0]['id']);
        $this->assertSame($topOnly, $gaps[0]['type']);
        $this->assertSame('child', $gaps[0]['placement']);
    }

    public function test_ignores_correctly_placed_blocks(): void
    {
        [$childOnly, $topOnly] = $this->exclusiveTypes();
        $this->assertNotNull($childOnly);
        $this->assertNotNull($topOnly);

        $gaps = BlockRenderCoverage::flatRowGaps([
            ['id' => 1, 'type' => $topOnly, 'parent_id' => null],
            ['id' => 2, 'type' => 'card', 'parent_id' => null],
            ['id' => 3, 'type' => $childOnly, 'parent_id' => 2],
            ['id' => 4, 'type' => 'link', 'parent_id' => 2],
            ['id' => 5, 'type' => 'heading', 'parent_id' => null],
        ]);

        $this->assertSame([], $gaps);
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

    public function test_block_under_non_container_parent_is_treated_as_top_level(): void
    {
        [, $topOnly] = $this->exclusiveTypes();
        $this->assertNotNull($topOnly);

        // Parent is a plain link (not a container), so the child dispatch never
        // runs for it — placement collapses to top-level, where $topOnly is fine.
        $gaps = BlockRenderCoverage::flatRowGaps([
            ['id' => 1, 'type' => 'link', 'parent_id' => null],
            ['id' => 2, 'type' => $topOnly, 'parent_id' => 1],
        ]);

        $this->assertSame([], $gaps);
    }
}
