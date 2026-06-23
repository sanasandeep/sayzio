<?php

namespace Tests\Unit\Support;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockRenderCoverage;
use App\Modules\User\Support\BlockTypeRegistry;
use App\Modules\User\Support\TemplateSnapshotValidator;
use PHPUnit\Framework\TestCase;

/**
 * Guards the admin-side template save path against the same two classes
 * of silent-degradation bug the seeder test catches at CI time:
 * unresolved design-variant keys and unknown block types.
 */
class TemplateSnapshotValidatorTest extends TestCase
{
    public function test_valid_page_snapshot_has_no_issues(): void
    {
        $snapshot = [
            'blocks' => [
                ['type' => 'heading', 'settings' => []],
                ['type' => 'link', 'settings' => ['_style' => ['_variant' => 'classic']]],
            ],
        ];

        $this->assertSame([], TemplateSnapshotValidator::issues($snapshot, 'page'));
    }

    public function test_unknown_block_type_is_flagged(): void
    {
        $snapshot = ['blocks' => [['type' => 'not_a_real_block', 'settings' => []]]];

        $issues = TemplateSnapshotValidator::issues($snapshot, 'page');

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('not_a_real_block', $issues[0]);
    }

    public function test_unresolvable_variant_is_flagged(): void
    {
        $snapshot = [
            'blocks' => [
                ['type' => 'link', 'settings' => ['_style' => ['_variant' => 'totally_bogus_key']]],
            ],
        ];

        $issues = TemplateSnapshotValidator::issues($snapshot, 'page');

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('totally_bogus_key', $issues[0]);
    }

    public function test_missing_block_type_is_flagged(): void
    {
        $snapshot = ['blocks' => [['settings' => []]]];

        $issues = TemplateSnapshotValidator::issues($snapshot, 'page');

        $this->assertNotEmpty($issues);
    }

    public function test_nested_container_children_are_validated(): void
    {
        $snapshot = [
            'blocks' => [
                [
                    'type' => 'card',
                    'settings' => [],
                    'children' => [
                        ['type' => 'link', 'settings' => ['_style' => ['_variant' => 'still_bogus']]],
                    ],
                ],
            ],
        ];

        $issues = TemplateSnapshotValidator::issues($snapshot, 'page');

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('still_bogus', $issues[0]);
    }

    public function test_top_level_render_gap_is_flagged(): void
    {
        // A child-only type placed at the page root would render blank. The
        // exclusive type is discovered from the live renderers so the test does
        // not assume a specific type's placement (which can drift).
        [$childOnly] = $this->exclusiveTypes();
        $this->assertNotNull($childOnly, 'expected at least one child-only block type');

        $snapshot = ['blocks' => [['type' => $childOnly, 'settings' => []]]];

        $issues = TemplateSnapshotValidator::issues($snapshot, 'page');

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString($childOnly, implode(' ', $issues));
    }

    public function test_child_render_gap_is_flagged(): void
    {
        // A top-level-only type placed inside a container would fall through to a
        // generic placeholder instead of its real content.
        [, $topOnly] = $this->exclusiveTypes();
        $this->assertNotNull($topOnly, 'expected at least one top-level-only block type');

        $snapshot = [
            'blocks' => [
                ['type' => 'card', 'settings' => [], 'children' => [
                    ['type' => $topOnly, 'settings' => []],
                ]],
            ],
        ];

        $issues = TemplateSnapshotValidator::issues($snapshot, 'page');

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString($topOnly, implode(' ', $issues));
    }

    public function test_correctly_placed_blocks_have_no_render_gap(): void
    {
        [$childOnly, $topOnly] = $this->exclusiveTypes();
        $this->assertNotNull($childOnly);
        $this->assertNotNull($topOnly);

        $snapshot = [
            'blocks' => [
                ['type' => $topOnly, 'settings' => []],
                ['type' => 'card', 'settings' => [], 'children' => [
                    ['type' => $childOnly, 'settings' => []],
                ]],
            ],
        ];

        $this->assertSame([], TemplateSnapshotValidator::issues($snapshot, 'page'));
    }

    /**
     * Discover one child-only and one top-level-only known type from the live
     * renderers (see BlockRenderCoverageTest for the rationale).
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

    public function test_card_snapshot_treats_root_as_a_block(): void
    {
        $bad = ['type' => 'card', 'settings' => ['_style' => ['_variant' => 'nope_not_real']]];
        $this->assertNotEmpty(TemplateSnapshotValidator::issues($bad, 'card'));

        $good = ['type' => 'card', 'settings' => [], 'children' => [
            ['type' => 'link', 'settings' => []],
        ]];
        $this->assertSame([], TemplateSnapshotValidator::issues($good, 'card'));
    }

    public function test_empty_variant_string_is_ignored(): void
    {
        $snapshot = [
            'blocks' => [
                ['type' => 'link', 'settings' => ['_style' => ['_variant' => '']]],
            ],
        ];

        $this->assertSame([], TemplateSnapshotValidator::issues($snapshot, 'page'));
    }

    public function test_strip_stale_variants_removes_only_unresolved_keys(): void
    {
        $snapshot = [
            'blocks' => [
                ['type' => 'link', 'settings' => ['_style' => ['_variant' => 'classic', 'color' => '#fff']]],
                ['type' => 'link', 'settings' => ['_style' => ['_variant' => 'totally_bogus_key', 'color' => '#000']]],
            ],
        ];

        $cleaned = TemplateSnapshotValidator::stripStaleVariants($snapshot, 'page');

        // Valid variant + sibling styling untouched.
        $this->assertSame('classic', $cleaned['blocks'][0]['settings']['_style']['_variant']);
        // Stale variant gone, but other styling preserved.
        $this->assertArrayNotHasKey('_variant', $cleaned['blocks'][1]['settings']['_style']);
        $this->assertSame('#000', $cleaned['blocks'][1]['settings']['_style']['color']);
        // Stripping fully resolves a variant-only problem.
        $this->assertSame([], TemplateSnapshotValidator::issues($cleaned, 'page'));
    }

    public function test_strip_stale_variants_recurses_into_children(): void
    {
        $snapshot = [
            'blocks' => [
                [
                    'type' => 'card',
                    'settings' => [],
                    'children' => [
                        ['type' => 'link', 'settings' => ['_style' => ['_variant' => 'still_bogus']]],
                    ],
                ],
            ],
        ];

        $cleaned = TemplateSnapshotValidator::stripStaleVariants($snapshot, 'page');

        $this->assertArrayNotHasKey('_variant', $cleaned['blocks'][0]['children'][0]['settings']['_style']);
        $this->assertSame([], TemplateSnapshotValidator::issues($cleaned, 'page'));
    }

    public function test_strip_stale_variants_cannot_fix_unknown_block_type(): void
    {
        $snapshot = ['blocks' => [['type' => 'not_a_real_block', 'settings' => []]]];

        $cleaned = TemplateSnapshotValidator::stripStaleVariants($snapshot, 'page');

        // Unknown types survive stripping and remain flagged.
        $this->assertNotEmpty(TemplateSnapshotValidator::issues($cleaned, 'page'));
    }

    public function test_strip_stale_variants_handles_card_root(): void
    {
        $snapshot = ['type' => 'card', 'settings' => ['_style' => ['_variant' => 'nope_not_real']]];

        $cleaned = TemplateSnapshotValidator::stripStaleVariants($snapshot, 'card');

        $this->assertArrayNotHasKey('_variant', $cleaned['settings']['_style']);
        $this->assertSame([], TemplateSnapshotValidator::issues($cleaned, 'card'));
    }
}
