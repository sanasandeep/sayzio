<?php

namespace Tests\Feature;

use App\Modules\User\Support\DashboardFlowDestinations;
use App\Services\Planner\FolderFlowNetwork;
use App\Services\Planner\FolderFlowStages;
use App\Services\Planner\PlannerResources;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The dashboard draws every resource kind as a station you can click.
 *
 * This is the same class of guard as FolderFlowMapTest, for the same reason: a
 * kind added to PlannerResources::TYPES and forgotten in the destination map
 * would simply not appear on the dashboard, and nothing would fail.
 *
 * It also asserts the thing I could not verify by reading the routes file --
 * that every destination is a route that actually exists. Route::resource
 * generates names that no name('...') call declares, so grepping for them
 * finds nothing and proves nothing; only the router knows. One of the
 * candidates this caught was user.plans.index, which exists but is billing
 * plan MANAGEMENT behind a permission gate, not the planner's plans.
 */
class DashboardFlowPanelTest extends TestCase
{
    public function test_every_resource_kind_has_a_dashboard_destination(): void
    {
        $missing = DashboardFlowDestinations::missingKinds();
        sort($missing);

        $this->assertSame([], $missing, 'these kinds exist in PlannerResources::TYPES but have no dashboard destination, so they would be missing from the panel: '.implode(', ', $missing));
    }

    public function test_every_dashboard_destination_is_a_registered_route(): void
    {
        $broken = [];
        foreach (DashboardFlowDestinations::all() as $kind => $dest) {
            if (! Route::has($dest['route'])) {
                $broken[] = $kind.' -> '.$dest['route'];
            }
        }

        sort($broken);
        $this->assertSame([], $broken, 'these dashboard stations point at route names the router does not know: '.implode(', ', $broken));
    }

    /**
     * A station with no icon renders an empty 20x20 box. The icons come from
     * the Flow tab's own table, so a kind present there but absent here (or
     * the reverse) is a visible hole rather than a crash.
     */
    public function test_every_destination_kind_has_a_flow_tab_icon(): void
    {
        $stations = FolderFlowNetwork::stations();

        $iconless = [];
        foreach (array_keys(DashboardFlowDestinations::all()) as $kind) {
            if (($stations[$kind]['icon'] ?? '') === '') {
                $iconless[] = $kind;
            }
        }

        sort($iconless);
        $this->assertSame([], $iconless, 'these dashboard stations would draw an empty icon box: '.implode(', ', $iconless));
    }

    /**
     * The panel groups by the Flow tab's stages, so a kind that has a
     * destination but no stage would be built and then never rendered.
     */
    public function test_every_destination_kind_sits_in_a_flow_stage(): void
    {
        $placed = FolderFlowStages::placedKinds();

        $orphans = [];
        foreach (array_keys(DashboardFlowDestinations::all()) as $kind) {
            if (! in_array($kind, $placed, true)) {
                $orphans[] = $kind;
            }
        }

        sort($orphans);
        $this->assertSame([], $orphans, 'these kinds have a dashboard destination but belong to no stage, so the panel would never draw them: '.implode(', ', $orphans));
    }

    /**
     * permissionFor() was lifted out of query() so the panel can tell a kind
     * the viewer cannot see from a kind they have none of. If the two ever
     * drift apart, query() would gate on one permission while the panel hides
     * (or fails to hide) on another.
     */
    public function test_every_kind_resolves_a_view_permission(): void
    {
        foreach (array_keys(PlannerResources::TYPES) as $kind) {
            $permission = PlannerResources::permissionFor($kind);
            $this->assertNotSame('', $permission, "no view permission resolved for kind {$kind}");
            $this->assertStringEndsWith('.view', $permission, "the permission for {$kind} is not a view permission: {$permission}");
        }
    }
}
