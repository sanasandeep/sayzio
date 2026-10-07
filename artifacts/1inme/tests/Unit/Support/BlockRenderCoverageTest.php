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
    public function test_every_registered_type_renders_at_root_and_inside_a_container(): void
    {
        $known = array_unique(array_merge(array_keys(BiolinkBlock::TYPES), array_keys(BlockTypeRegistry::newTypes())));
        foreach ($known as $type) {
            $this->assertTrue(BlockRenderCoverage::rendersTopLevel($type), "Missing root renderer: {$type}");
            $this->assertTrue(BlockRenderCoverage::rendersAsChild($type), "Missing child renderer: {$type}");
            $this->assertSame([], BlockRenderCoverage::renderGaps([
                ['type' => $type], ['type' => 'card', 'children' => [['type' => $type]]],
            ]));
            $this->assertSame([], BlockRenderCoverage::flatRowGaps([
                ['id' => 1, 'type' => $type, 'parent_id' => null],
                ['id' => 2, 'type' => 'card', 'parent_id' => null],
                ['id' => 3, 'type' => $type, 'parent_id' => 2],
            ]));
        }
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

    public function test_render_gaps_skips_unknown_types(): void
    {
        // Unknown types are reported separately by the callers' type check, so
        // the render-gap pass stays silent about them to avoid double-noise.
        $this->assertSame([], BlockRenderCoverage::renderGaps([['type' => 'totally_unknown_type']]));
    }
}
