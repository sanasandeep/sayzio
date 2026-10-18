<?php

namespace Tests\Feature;

use App\Services\Planner\FolderFlowMesh;
use App\Services\Planner\FolderFlowNetwork;
use App\Services\Planner\PlannerResources;
use Tests\TestCase;

/**
 * The capability mesh is hand-written product knowledge, not derived from
 * anything, so nothing will notice if it drifts. These are the guards that
 * would otherwise be nobody's job: that it names only real kinds, that it is
 * symmetric, and that the claims it was built from still hold.
 */
class FolderFlowMeshTest extends TestCase
{
    /** A pair naming a kind that does not exist draws a line to nowhere. */
    public function test_every_kind_named_is_a_real_resource_kind(): void
    {
        $real = array_keys(PlannerResources::TYPES);

        foreach (FolderFlowMesh::kindsNamed() as $kind) {
            $this->assertContains($kind, $real,
                "the mesh names '{$kind}', which is not a resource kind -- it would draw to nothing");
        }
    }

    /** Hovering either end has to light the same line, or the map contradicts itself. */
    public function test_the_mesh_is_symmetric(): void
    {
        $reach = FolderFlowMesh::reach();

        foreach ($reach as $kind => $targets) {
            foreach ($targets as $target) {
                $this->assertContains($kind, $reach[$target],
                    "{$kind} reaches {$target} but {$target} does not reach {$kind}");
            }
        }
    }

    /** Every station gets an entry, even an empty one, so the view never reads a missing key. */
    public function test_every_station_has_an_entry(): void
    {
        $this->assertSame(
            array_keys(FolderFlowNetwork::stations()),
            array_keys(FolderFlowMesh::reach()),
            'the mesh and the map disagree about which stations exist',
        );
    }

    public function test_no_kind_reaches_itself(): void
    {
        foreach (FolderFlowMesh::pairs() as $pair) {
            $this->assertNotSame($pair['from'], $pair['to']);
        }
        $this->assertSame(
            count(FolderFlowMesh::pairs()),
            count(array_unique(array_map(
                fn ($p) => $p['from'] < $p['to'] ? $p['from'].$p['to'] : $p['to'].$p['from'],
                FolderFlowMesh::pairs(),
            ))),
            'the same pair is listed twice, so one line would be drawn on top of another',
        );
    }

    /**
     * The five claims this table was written from. If someone edits the table
     * and one of these stops being true, they have changed what the product
     * says about itself, which should be deliberate rather than accidental.
     */
    public function test_the_claims_it_was_built_from_still_hold(): void
    {
        $reach = FolderFlowMesh::reach();
        $all   = array_keys(FolderFlowNetwork::stations());

        foreach (['link', 'qr', 'form', 'buzz', 'campaign', 'studio', 'task'] as $kind) {
            $this->assertContains('mind', $reach[$kind],
                "performance from {$kind} is supposed to be able to train a mind");
        }

        foreach (['plan', 'studio'] as $everywhere) {
            $this->assertCount(count($all) - 1, $reach[$everywhere],
                "{$everywhere} is supposed to touch every other kind");
        }

        $this->assertGreaterThanOrEqual(6, count($reach['calendar']),
            'calendars are supposed to be linked in several places');
        $this->assertGreaterThanOrEqual(10, count($reach['link']),
            'links are supposed to reach almost everywhere');
    }
}
