<?php

namespace Tests\Feature;

use App\Services\Planner\FolderChartExport;
use App\Services\Planner\FolderFlowDemo;
use App\Services\Planner\FolderFlowNetwork;
use App\Services\Planner\PlannerResources;
use Tests\TestCase;

/**
 * The export buttons offer a view and a shape, and the view hands the browser
 * a two-level array. Until now the How-it-works tab had no entry at all, so
 * `exports[mode][shape]` was undefined and the download wrote a file
 * containing the word "undefined" -- silently, for every export that tab has
 * ever had, because nothing asserted that a button had anything behind it.
 *
 * These guards are about that: every button the page can show has to produce
 * a real picture, and the one made of invented numbers has to say so inside
 * the image, because a downloaded file travels without the page around it.
 */
class FolderExportShapesTest extends TestCase
{
    /** Every view the switch offers, not only the ones MODES knows about. */
    private const VIEWS = ['flow', 'people', 'guide'];

    private function export(string $view, string $shape): string
    {
        if ($shape === 'tree') {
            return FolderChartExport::renderTree($view, 'Partners', [], []);
        }

        return $view === 'guide'
            ? FolderChartExport::renderGuide()
            : FolderChartExport::render($view, 'Partners', [], []);
    }

    public function test_every_view_and_shape_produces_a_picture(): void
    {
        foreach (self::VIEWS as $view) {
            foreach (FolderChartExport::SHAPES as $shape) {
                $svg = $this->export($view, $shape);

                $this->assertStringStartsWith('<svg', $svg,
                    "the {$shape} export of the {$view} view is not an SVG, so the download would be a broken file");
                $this->assertStringEndsWith('</svg>', trim($svg));
                $this->assertGreaterThan(400, strlen($svg),
                    "the {$shape} export of the {$view} view is suspiciously small");
            }
        }
    }

    /** The worked example is invented; a picture of it has to carry that. */
    public function test_the_example_says_it_is_invented_inside_the_image(): void
    {
        foreach (FolderChartExport::SHAPES as $shape) {
            $this->assertStringContainsString('Worked example', $this->export('guide', $shape),
                "the {$shape} export of the example does not say it is invented, and a downloaded picture has no page around it to say so");
        }
    }

    /** ...and a real folder's export must not claim to be one. */
    public function test_a_real_export_does_not_carry_the_example_band(): void
    {
        foreach (['flow', 'people'] as $view) {
            foreach (FolderChartExport::SHAPES as $shape) {
                $this->assertStringNotContainsString('Worked example', $this->export($view, $shape));
                $this->assertStringNotContainsString(FolderFlowDemo::COMPANY, $this->export($view, $shape));
            }
        }
    }

    /**
     * The same conservation the map export is held to: an outline that quietly
     * leaves a kind out is worse than no outline, because it looks complete.
     */
    public function test_the_outline_lists_every_kind(): void
    {
        $labels = PlannerResources::typeLabels();

        foreach (['flow', 'guide'] as $view) {
            $tree = $this->export($view, 'tree');

            foreach (array_keys(FolderFlowNetwork::stations()) as $kind) {
                $this->assertStringContainsString(
                    htmlspecialchars($labels[$kind] ?? $kind, ENT_QUOTES | ENT_XML1, 'UTF-8'),
                    $tree,
                    "the {$view} outline never mentions {$kind}",
                );
            }
        }
    }
}
