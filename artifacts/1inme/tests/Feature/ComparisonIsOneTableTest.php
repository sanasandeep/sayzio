<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The comparison section renders ONE table.
 *
 * It used to render two, stacked. A Sayzio-vs-selected-tool table, and
 * directly beneath it the same twenty-four rows again across all six tools,
 * because the "show full matrix" toggle revealed a second table rather than
 * widening the first. On /pricing both were open by default, so a visitor
 * scrolled past the same rows twice. Sana: "both of them are same...
 * need only 1".
 *
 * The toggle changes the column count of the one table now. This guards that,
 * because the failure mode is somebody re-adding a "simple" second table for
 * a narrow case and nobody noticing until it is live.
 */
class ComparisonIsOneTableTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(
            resource_path('views/public/partials/_compare.blade.php')
        );
    }

    public function test_only_one_table_is_declared(): void
    {
        $source = $this->source();

        $this->assertSame(
            1,
            substr_count($source, 'class="cmp-matrix"'),
            'the comparison section declares more than one table again; the toggle is '
            . 'supposed to widen the one table, not reveal a second copy of it'
        );

        // The old second table was a hand-rolled grid of its own.
        $this->assertStringNotContainsString(
            'cmp-h2h-card',
            $source,
            'the two-column head-to-head table is back alongside the matrix'
        );
    }

    public function test_the_toggle_changes_columns_rather_than_revealing_a_table(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'gridStyle()',
            $source,
            'the table no longer sizes its own columns, so `showAll` has nothing to widen'
        );
        $this->assertStringContainsString(
            "colShown('",
            $source,
            'competitor columns are no longer shown or hidden individually'
        );

        // `showAll` must not gate a whole block any more.
        $this->assertDoesNotMatchRegularExpression(
            '/x-show="showAll"/',
            $source,
            'something is still shown or hidden wholesale by `showAll`; that is how the '
            . 'second table came back'
        );
    }

    /**
     * The two things the single table can do that the pair could not.
     *
     * Worth guarding because they are the reason this is more than a
     * deletion: fourteen of twenty-four rows are ticked by every tool, and a
     * row only Sayzio has is the most persuasive fact in the data.
     */
    public function test_it_can_filter_to_differences_and_marks_what_is_ours_alone(): void
    {
        $source = $this->source();

        $this->assertStringContainsString('diffOnly', $source, 'the differences-only filter is gone');
        $this->assertStringContainsString('rowDiffers(', $source, 'the filter no longer decides per row');
        $this->assertStringContainsString('groupShown(', $source, 'a group whose rows are all hidden now shows an empty heading');
        $this->assertStringContainsString('onlyOurs', $source, 'rows no other tool has are no longer marked');
    }
}
