<?php

namespace Tests\Feature;

use App\Services\Planner\FolderChartExport;
use App\Services\Planner\FolderFlowNetwork;
use App\Services\Planner\FolderFlowStages;
use Tests\TestCase;

/**
 * The exported picture has to say the same thing the screen says.
 *
 * It did not. The Flow export carried its own stage list -- Prepare, Create,
 * Reach, Act -- written out by hand, while the map has five stages. Serve was
 * missing entirely, and with it companion, agent, staff and persona; calendar
 * and board were dropped too. Six of nineteen kinds could never appear in an
 * exported diagram, and nothing said so: the file downloaded fine and looked
 * plausible.
 *
 * These guards are the same conservation rule the rest of the Flow tab is
 * built on, applied to the export: every kind on a stage, every stage named,
 * nothing quietly left out.
 */
class FolderChartExportTest extends TestCase
{
    /** Every station on the map reaches the exported picture. */
    public function test_every_station_appears_in_the_flow_export(): void
    {
        $svg = FolderChartExport::render('flow', 'Launch', [], []);

        foreach (FolderFlowNetwork::stations() as $kind => $place) {
            // The icon markup is unique per kind, so it identifies the
            // station without depending on how its label is truncated.
            $this->assertStringContainsString($place['icon'], $svg,
                "{$kind} has no station in the exported picture, so exporting Flow loses it");
        }
    }

    /** Every stage is named in the legend, and has a colour to be drawn in. */
    public function test_every_stage_is_named_and_coloured(): void
    {
        $svg = FolderChartExport::render('flow', 'Launch', [], []);

        foreach (FolderFlowStages::all() as $key => $stage) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', (string) ($stage['color'] ?? ''),
                "stage '{$key}' has no colour, so the export and the stylesheet cannot agree");
            $this->assertStringContainsString('>'.$stage['eyebrow'].'<', $svg,
                "stage '{$key}' is missing from the export legend");
        }
    }

    /**
     * The whole point of the fix: the export is built from the map's tables,
     * so a kind added to a stage cannot be absent from the picture. If this
     * count and the station count ever disagree, something restated the
     * stage list instead of reading it.
     */
    public function test_the_export_covers_exactly_the_kinds_the_stages_hold(): void
    {
        $staged = [];
        foreach (FolderFlowStages::all() as $stage) {
            foreach ($stage['kinds'] as $kind) {
                $staged[$kind] = true;
            }
        }

        // Compared as sets. The two tables list kinds in different orders --
        // stations run down the map's spine, stages run in workflow order --
        // and neither order is the invariant. Asserting on it only produced
        // a test that failed while the thing it guards was perfectly true.
        $stationKinds = array_keys(FolderFlowNetwork::stations());
        $stagedKinds = array_keys($staged);
        sort($stationKinds);
        sort($stagedKinds);

        $this->assertSame($stationKinds, $stagedKinds,
            'a kind is on a station but on no stage, or on a stage but on no station');
    }

    /**
     * The rails are a suggested order, not a record of what happened. The
     * exported picture leaves the folder, so it has to carry that caveat on
     * its own face -- the note under the map does not travel with the file.
     *
     * This assertion used to live in FolderProjectHubTest, checking the page
     * for wording that only ever came from the export. It belongs here, on
     * the thing it is actually about.
     */
    public function test_the_export_carries_the_not_measured_caveat(): void
    {
        $svg = FolderChartExport::render('flow', 'Launch', [], []);

        $this->assertStringContainsString('not measured attribution', $svg,
            'an exported diagram of suggested rails must say that is what it is');
    }

    /** A malformed SVG downloads happily and opens in nothing. */
    public function test_the_exported_svg_is_well_formed_and_escapes_the_folder_name(): void
    {
        $svg = FolderChartExport::render('flow', 'Q1 <Launch> & "Partners"', [], []);

        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($svg);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($parsed, 'the exported SVG does not parse as XML');
        $this->assertStringNotContainsString('<Launch>', $svg,
            'the folder name reached the SVG unescaped and broke the document');
    }
}
