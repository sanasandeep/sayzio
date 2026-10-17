<?php

namespace Tests\Feature;

use App\Services\Planner\FolderFlowMeasures;
use App\Services\Planner\FolderFlowNetwork;
use App\Services\Planner\FolderFlowStages;
use App\Services\Planner\PlannerResources;
use Tests\TestCase;

/**
 * The Flow tab is a transit map: kinds are stations, stages are the lines,
 * junctions are saved connections. Its geometry is hand-placed constants, so
 * these guards are about the map staying coherent with the catalogue it draws
 * — a station that silently disappears, or a rail pointing at nothing, fails
 * invisibly on the page.
 */
class FolderFlowNetworkTest extends TestCase
{
    private function item(string $kind, array $attrs = []): array
    {
        return ['kind' => $kind, 'row' => (object) $attrs];
    }

    private function relation(string $from, string $to): object
    {
        return (object) ['source_kind' => $from, 'target_kind' => $to];
    }

    /** A kind with no station is a kind the map silently refuses to show. */
    public function test_every_resource_kind_has_a_station(): void
    {
        $stations = array_keys(FolderFlowNetwork::stations());
        $kinds = array_keys(PlannerResources::TYPES);

        sort($stations);
        sort($kinds);

        $this->assertSame($kinds, $stations,
            'every kind a folder can hold needs a station, and the map must not invent one that is not a kind');
    }

    public function test_no_two_stations_sit_in_the_same_cell(): void
    {
        $cells = [];
        foreach (FolderFlowNetwork::stations() as $kind => $place) {
            $cells[] = $place['col'].':'.$place['row'];
        }

        $this->assertSame(count($cells), count(array_unique($cells)),
            'two stations in one cell would overlap and hide each other');
    }

    public function test_every_station_is_inside_the_declared_grid(): void
    {
        foreach (FolderFlowNetwork::stations() as $kind => $place) {
            $this->assertGreaterThanOrEqual(1, $place['col'], "{$kind} sits left of the grid");
            $this->assertLessThanOrEqual(FolderFlowNetwork::COLS, $place['col'], "{$kind} sits past the right edge");
            $this->assertGreaterThanOrEqual(1, $place['row'], "{$kind} sits above the grid");
            $this->assertLessThanOrEqual(FolderFlowNetwork::ROWS, $place['row'], "{$kind} sits below the grid");
        }
    }

    /**
     * Icons are stroked so they take their line's colour. One with a baked-in
     * fill renders as a black blob on the dark theme, which is the kind of
     * thing nobody notices until a user mentions it.
     */
    public function test_every_station_has_a_stroke_only_icon(): void
    {
        foreach (FolderFlowNetwork::stations() as $kind => $place) {
            $this->assertNotSame('', trim($place['icon']), "{$kind} has no icon");
            $this->assertStringNotContainsString('fill=', $place['icon'],
                "{$kind}'s icon hardcodes a fill, so it will not follow the line colour or the dark theme");
        }
    }

    /** A rail drawn to a station that does not exist simply never appears. */
    public function test_every_rail_runs_between_real_stations_on_a_real_line(): void
    {
        $stations = FolderFlowNetwork::stations();
        $stages = FolderFlowStages::all();

        foreach (FolderFlowNetwork::rails() as $i => $rail) {
            $this->assertArrayHasKey($rail['stage'], $stages, "rail {$i} is drawn in a stage colour that does not exist");

            foreach ($rail['points'] as $point) {
                foreach (is_array($point) ? [$point['x'], $point['y']] : [$point] as $kind) {
                    $this->assertArrayHasKey($kind, $stations, "rail {$i} routes through '{$kind}', which has no station");
                }
            }
        }
    }

    /** The map's lines and the stage map cannot drift apart. */
    public function test_each_line_carries_exactly_its_stage_kinds(): void
    {
        foreach (FolderFlowStages::all() as $stageKey => $stage) {
            $onLine = [];
            foreach (array_keys(FolderFlowNetwork::stations()) as $kind) {
                if (FolderFlowNetwork::lineFor($kind) === $stageKey) {
                    $onLine[] = $kind;
                }
            }

            sort($onLine);
            $kinds = $stage['kinds'];
            sort($kinds);

            $this->assertSame($kinds, $onLine, "the {$stageKey} line and the {$stageKey} stage disagree about their kinds");
        }
    }

    public function test_a_station_reports_its_count_and_whether_the_folder_uses_it(): void
    {
        $resources = [$this->item('link', ['total_clicks' => 90]), $this->item('link', ['total_clicks' => 10])];
        $map = FolderFlowNetwork::map($resources, [], FolderFlowMeasures::all($resources));

        $by = array_column($map['stations'], null, 'kind');

        $this->assertCount(19, $map['stations']);
        $this->assertSame(2, $by['link']['count']);
        $this->assertTrue($by['link']['in_use']);
        $this->assertSame(0, $by['form']['count']);
        $this->assertFalse($by['form']['in_use'],
            'an unused kind keeps its station — the map holds its shape and shows what the folder is not using');
    }

    /**
     * A station shows a figure only where a stage measure covers that kind.
     * QR codes sit on the Create line with a count and no figure, because the
     * clicks figure is links only -- the same claim #416 makes in words.
     */
    public function test_a_station_only_shows_a_figure_its_stage_actually_counts(): void
    {
        $resources = [$this->item('link', ['total_clicks' => 100]), $this->item('qr')];
        $map = FolderFlowNetwork::map($resources, [], FolderFlowMeasures::all($resources));
        $by = array_column($map['stations'], null, 'kind');

        $this->assertSame(100, $by['link']['figure']['value']);
        $this->assertNull($by['qr']['figure'],
            'QR scans are already counted as their link\'s clicks; showing the clicks figure here would state it twice');
    }

    /** Every station links somewhere real, or the map is decoration. */
    public function test_every_station_links_to_a_folder_tab_that_exists(): void
    {
        $known = ['resources', 'links', 'marketing', 'tasks', 'remember', 'reports'];
        $map = FolderFlowNetwork::map([], [], FolderFlowMeasures::all([]));

        foreach ($map['stations'] as $station) {
            $this->assertContains($station['tab'], $known,
                "station {$station['kind']} links to the unknown folder tab '{$station['tab']}'");
        }
    }

    /** Conservation, as everywhere else in this scope: nothing is dropped. */
    public function test_connections_the_map_cannot_place_are_counted_not_dropped(): void
    {
        $edges = FolderFlowNetwork::edges([
            $this->relation('plan', 'link'),
            $this->relation('plan', 'link'),
            $this->relation('link', 'buzz'),
            $this->relation('gone', 'link'),
        ]);

        $this->assertSame(3, FolderFlowNetwork::drawnCount($edges));
        $this->assertSame(1, FolderFlowNetwork::undrawableCount($edges),
            'a connection pointing at a kind with no station must be reported, not silently omitted');
    }
}
