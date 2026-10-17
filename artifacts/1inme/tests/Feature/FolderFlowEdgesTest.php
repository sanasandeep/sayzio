<?php

namespace Tests\Feature;

use App\Services\Planner\FolderFlowEdges;
use App\Services\Planner\FolderFlowStages;
use App\Services\Planner\PlannerResources;
use Tests\TestCase;

/**
 * The Flow tab's connectors used to be five identical dashed arrows that
 * meant nothing -- drawn the same whether or not anyone had connected two
 * items in the folder. The `folder_relationships` rows users save under
 * Resources were loaded by FolderWorkspace and drawn nowhere.
 *
 * FolderFlowEdges sorts those rows onto the rail. The property that matters
 * is conservation: a connector that quietly dropped the connections it could
 * not draw would be worse than the dashed line it replaced, because it would
 * look measured.
 */
class FolderFlowEdgesTest extends TestCase
{
    /** @return object a `folder_relationships` row, as DB::table returns one. */
    private function relation(string $sourceKind, string $targetKind): object
    {
        return (object) [
            'source_kind' => $sourceKind,
            'source_id'   => 1,
            'target_kind' => $targetKind,
            'target_id'   => 2,
            'label'       => 'Related to',
        ];
    }

    /** A plan (Prepare) supporting a link (Create) is the rail's own case. */
    public function test_a_connection_between_adjacent_stages_lands_on_the_rail(): void
    {
        $edges = FolderFlowEdges::from([
            $this->relation('plan', 'link'),
            $this->relation('brand', 'form'),
        ]);

        $this->assertSame(2, FolderFlowEdges::recordedBetween($edges, 'prepare', 'create'));
        $this->assertSame([], $edges['offRail']);
        $this->assertSame([], $edges['inside']);
    }

    /**
     * Two links connected to each other are a real connection that the rail
     * cannot draw as an arrow between stages -- it belongs to one stage.
     */
    public function test_a_connection_inside_one_stage_is_counted_inside_not_on_the_rail(): void
    {
        $edges = FolderFlowEdges::from([
            $this->relation('link', 'form'),
            $this->relation('link', 'qr'),
        ]);

        $this->assertSame(['create' => 2], $edges['inside']);
        $this->assertSame(0, array_sum($edges['rail']), 'an inside connection must not inflate a rail connector');
    }

    /** A plan connected straight to a task skips Create, Serve and Reach. */
    public function test_a_connection_that_skips_stages_is_kept_off_the_rail_not_dropped(): void
    {
        $edges = FolderFlowEdges::from([$this->relation('plan', 'task')]);

        $this->assertSame(0, array_sum($edges['rail']));
        $this->assertSame(1, FolderFlowEdges::offRailCount($edges));
        $this->assertSame('prepare', $edges['offRail'][0]['from']);
        $this->assertSame('act', $edges['offRail'][0]['to']);
        $this->assertFalse($edges['offRail'][0]['backwards']);
    }

    /** A task feeding back into a plan runs against the rail, and says so. */
    public function test_a_connection_against_the_flow_is_marked_backwards(): void
    {
        $edges = FolderFlowEdges::from([$this->relation('task', 'plan')]);

        $this->assertSame(1, FolderFlowEdges::offRailCount($edges));
        $this->assertTrue($edges['offRail'][0]['backwards'],
            'act → prepare runs against the suggested order and must not be drawn as if it followed it');
    }

    /**
     * The guard this class exists for. Whatever mix comes in, every row comes
     * out in exactly one bucket. A rail that loses connections looks measured
     * while being wrong.
     */
    public function test_every_relationship_is_counted_exactly_once(): void
    {
        $edges = FolderFlowEdges::from([
            $this->relation('plan', 'link'),        // rail: prepare → create
            $this->relation('link', 'companion'),   // rail: create → serve
            $this->relation('link', 'qr'),          // inside: create
            $this->relation('plan', 'task'),        // off-rail: skips ahead
            $this->relation('task', 'plan'),        // off-rail: backwards
            $this->relation('nonsense', 'link'),    // unplaced: not a kind
        ]);

        $counted = array_sum($edges['rail'])
            + array_sum($edges['inside'])
            + FolderFlowEdges::offRailCount($edges)
            + $edges['unplaced'];

        $this->assertSame(6, $edges['total']);
        $this->assertSame($edges['total'], $counted,
            'every saved connection must be counted once and only once');
        $this->assertSame(5, $edges['recorded'], 'the unplaced row is not a connection the folder recorded');
    }

    /** Nothing to sort is the ordinary case, and must not look like data. */
    public function test_an_empty_folder_leaves_every_connector_suggested(): void
    {
        $edges = FolderFlowEdges::from([]);

        $this->assertSame(0, $edges['total']);
        $this->assertSame(0, array_sum($edges['rail']));
        $this->assertSame([], $edges['inside']);
        $this->assertSame([], $edges['offRail']);
        $this->assertNotSame([], $edges['rail'], 'the rail still has its connectors, all at zero');
    }

    /**
     * The rail only ever joins stages that sit next to each other. If someone
     * reorders FolderFlowStages, the pairs follow -- they are not a second
     * copy of the order that can drift from the first.
     */
    public function test_the_rail_only_joins_adjacent_stages(): void
    {
        $order = array_keys(FolderFlowStages::all());
        $pairs = FolderFlowEdges::railPairs();

        $this->assertCount(count($order) - 1, $pairs, 'a rail of N stages has N-1 connectors');

        foreach ($pairs as $i => [$from, $to]) {
            $this->assertSame($order[$i], $from);
            $this->assertSame($order[$i + 1], $to);
        }
    }

    /**
     * With every kind placed (FolderFlowMapTest holds that), no real
     * relationship between two real kinds can come out unplaced.
     */
    public function test_no_connection_between_real_kinds_comes_out_unplaced(): void
    {
        $kinds = array_keys(PlannerResources::TYPES);

        $relations = [];
        foreach ($kinds as $kind) {
            $relations[] = $this->relation($kind, 'link');
            $relations[] = $this->relation('link', $kind);
        }

        $edges = FolderFlowEdges::from($relations);

        $this->assertSame(0, $edges['unplaced'],
            'a kind a folder can hold must map to a stage, or its connections silently stop being counted');
        $this->assertSame(count($relations), $edges['recorded']);
    }

    /**
     * No Blade directive in the planner views is glued to a word character.
     *
     * Blade matches directives with a regex that begins `\B@`, so an `@if`
     * written immediately after `@endif` is NOT compiled -- it is emitted as
     * literal text, while its own `@endif` IS compiled, and the view dies with
     * "syntax error, unexpected token endif". This PR shipped exactly that and
     * three FolderProjectHubTest cases caught it only at render time.
     *
     * The views here are deliberately dense one-liners, so the adjacency is
     * easy to reintroduce and invisible on review. This is cheaper than
     * reading for it.
     */
    public function test_no_blade_directive_is_glued_to_a_word_character(): void
    {
        $pattern = '/[A-Za-z0-9_]@(?:if|endif|foreach|endforeach|php|endphp|forelse|endforelse|else|elseif)\b/';

        $offenders = [];
        foreach (glob(resource_path('views/user/planner/*.blade.php')) as $view) {
            if (preg_match_all($pattern, (string) file_get_contents($view), $m)) {
                $offenders[] = basename($view).': '.implode(', ', $m[0]);
            }
        }

        $this->assertSame([], $offenders,
            'Blade will not compile these as directives because a word character precedes the @: '.implode(' | ', $offenders));
    }
}
