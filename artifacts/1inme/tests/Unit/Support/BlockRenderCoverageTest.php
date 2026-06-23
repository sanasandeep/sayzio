<?php

namespace Tests\Unit\Support;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockRenderCoverage;
use App\Modules\User\Support\BlockTypeRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Guards the placement-aware render-gap detector against the silent
 * blank-render bug: a type that renders in one placement but not the other.
 *
 * Coverage is derived by reading the actual blade renderers, so the
 * placement-exclusive types used below are discovered at runtime rather than
 * hard-coded — a specific type's placement can drift as branches are added,
 * and the detector must keep working regardless of which types happen to be
 * exclusive today.
 */
class BlockRenderCoverageTest extends TestCase
{
    /**
     * Discover one child-only and one top-level-only known type from the live
     * renderers.
     *
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

    public function test_placement_exclusive_types_are_detected_per_placement(): void
    {
        [$childOnly, $topOnly] = $this->exclusiveTypes();

        $this->assertNotNull($childOnly, 'expected at least one child-only block type');
        $this->assertTrue(BlockRenderCoverage::rendersAsChild($childOnly));
        $this->assertFalse(BlockRenderCoverage::rendersTopLevel($childOnly));

        $this->assertNotNull($topOnly, 'expected at least one top-level-only block type');
        $this->assertTrue(BlockRenderCoverage::rendersTopLevel($topOnly));
        $this->assertFalse(BlockRenderCoverage::rendersAsChild($topOnly));
    }

    public function test_common_types_render_in_both_placements(): void
    {
        // Structural workhorse blocks have branches in BOTH renderers; this also
        // guards against a stale "type X is exclusive" assumption regressing.
        foreach (['avatar', 'heading', 'link', 'image', 'paragraph'] as $type) {
            $this->assertTrue(BlockRenderCoverage::rendersTopLevel($type), "{$type} should render top-level");
            $this->assertTrue(BlockRenderCoverage::rendersAsChild($type), "{$type} should render as a child");
        }
    }

    public function test_in_array_branch_types_are_detected(): void
    {
        // list / list_numbered share a single in_array() branch.
        $this->assertTrue(BlockRenderCoverage::rendersTopLevel('list'));
        $this->assertTrue(BlockRenderCoverage::rendersTopLevel('list_numbered'));
    }

    public function test_prefix_and_container_branches_are_detected(): void
    {
        // str_starts_with($block->type, 'profile_card')
        $this->assertTrue(BlockRenderCoverage::rendersTopLevel('profile_card_v1'));
        $this->assertTrue(BlockRenderCoverage::rendersTopLevel('profile_card_anything'));

        // isContainerType() branch covers every CONTAINER_TYPES entry.
        $this->assertTrue(BlockRenderCoverage::rendersTopLevel('card'));
        $this->assertTrue(BlockRenderCoverage::rendersTopLevel('grid'));
        $this->assertTrue(BlockRenderCoverage::rendersTopLevel('grid_auto'));
    }

    public function test_unknown_types_render_nowhere(): void
    {
        $this->assertFalse(BlockRenderCoverage::rendersTopLevel('definitely_not_a_block'));
        $this->assertFalse(BlockRenderCoverage::rendersAsChild('definitely_not_a_block'));
    }

    public function test_render_gaps_flags_child_only_type_at_page_root(): void
    {
        [$childOnly] = $this->exclusiveTypes();
        $this->assertNotNull($childOnly);

        $gaps = BlockRenderCoverage::renderGaps([['type' => $childOnly]]);

        $this->assertNotEmpty($gaps);
        $this->assertStringContainsString($childOnly, $gaps[0]);
        $this->assertStringContainsString('page root', $gaps[0]);
    }

    public function test_render_gaps_flags_top_only_type_inside_container(): void
    {
        [, $topOnly] = $this->exclusiveTypes();
        $this->assertNotNull($topOnly);

        $gaps = BlockRenderCoverage::renderGaps([
            ['type' => 'card', 'children' => [['type' => $topOnly]]],
        ]);

        $this->assertNotEmpty($gaps);
        $this->assertStringContainsString($topOnly, $gaps[0]);
        $this->assertStringContainsString('container', $gaps[0]);
    }

    public function test_render_gaps_ignores_correctly_placed_blocks(): void
    {
        [$childOnly, $topOnly] = $this->exclusiveTypes();
        $this->assertNotNull($childOnly);
        $this->assertNotNull($topOnly);

        // top-only at the root + child-only as a card child = both fine.
        $gaps = BlockRenderCoverage::renderGaps([
            ['type' => $topOnly],
            ['type' => 'card', 'children' => [['type' => $childOnly], ['type' => 'link']]],
        ]);

        $this->assertSame([], $gaps);
    }

    public function test_render_gaps_skips_unknown_types(): void
    {
        // Unknown types are reported separately by the callers' type check, so
        // the render-gap pass stays silent about them to avoid double-noise.
        $this->assertSame([], BlockRenderCoverage::renderGaps([['type' => 'totally_unknown_type']]));
    }
}
