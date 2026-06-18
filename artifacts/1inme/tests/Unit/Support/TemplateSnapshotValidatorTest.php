<?php

namespace Tests\Unit\Support;

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
