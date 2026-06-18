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
}
