<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceActivityEvent;
use App\Modules\User\Observers\PlannerResourceActivity;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Planner\PlannerResources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Before this, the workspace activity ledger recorded `link` and nothing else
 * among the nineteen resource kinds. A team member could delete a form or
 * rewrite a campaign and the workspace had no record of who did it.
 *
 * The observer is bound to the models rather than written into controllers,
 * so a new route cannot bypass it. These guards are about the mapping staying
 * true to the catalogue, and about the ledger not filling with rows that
 * cannot say who acted.
 *
 * A warning for anyone extending this file: an observer that is switched off
 * in the test environment makes every "nothing was recorded" assertion pass
 * for the wrong reason. The first version of this class guarded on
 * app()->runningInConsole(), which is true under PHPUnit, so it recorded
 * nothing in any test. Whenever you add a test that asserts an ABSENCE here,
 * make sure some other test in this class proves the observer is live.
 */
class PlannerResourceActivityTest extends TestCase
{
    use RefreshDatabase;

    /** Every kind maps to exactly one model, and every model back to its kind. */
    public function test_every_resource_kind_maps_to_its_own_model(): void
    {
        $map = PlannerResourceActivity::kindByModel();

        $this->assertCount(count(PlannerResources::TYPES), $map,
            'two kinds sharing a model class would make one of them unrecordable');

        foreach (PlannerResources::TYPES as $kind => $class) {
            $key = ltrim((string) $class, '\\');
            $this->assertArrayHasKey($key, $map, "{$kind} has no model in the observer's map");
            $this->assertSame($kind, $map[$key], "{$key} maps to the wrong kind");
        }
    }

    /**
     * The object_type written to the ledger has to be the same vocabulary the
     * folder map reads, or the map shows nothing for a kind that is in fact
     * being worked on.
     */
    public function test_the_recorded_object_type_is_a_real_resource_kind(): void
    {
        foreach (PlannerResourceActivity::kindByModel() as $class => $kind) {
            $this->assertArrayHasKey($kind, PlannerResources::TYPES,
                "the observer would write object_type '{$kind}', which is not a resource kind");
        }
    }

    /** A team member's change is recorded, against the right kind and actor. */
    public function test_a_members_change_is_recorded_against_its_kind(): void
    {
        $user = User::factory()->create();
        $ws = app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        app()->instance('current_workspace', $ws);

        // Form's fillable carries neither user_id nor workspace_id: the
        // BelongsToWorkspace trait supplies the workspace on creating, and
        // the slug generates itself from the title.
        $form = new \App\Modules\User\Models\Form(['title' => 'Partner signup']);
        $form->user_id = $user->id;
        $form->save();

        $event = WorkspaceActivityEvent::where('workspace_id', $ws->id)
            ->where('object_type', 'form')
            ->latest('id')
            ->first();

        $this->assertNotNull($event, 'creating a form must leave a record of who created it');
        $this->assertSame('form.create', $event->action);
        $this->assertSame((int) $form->id, (int) $event->object_id);
        $this->assertSame((int) $user->id, (int) $event->actor_user_id);
        $this->assertSame('Partner signup', $event->object_label,
            'the ledger should name the thing, not just its id');
    }

    /**
     * Console work has no actor, and a row that cannot say who acted is noise
     * in a ledger whose only purpose is attribution. Seeds, migrations and
     * imports must not fill it.
     */
    public function test_work_with_no_signed_in_user_is_not_recorded(): void
    {
        $user = User::factory()->create();
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);

        auth()->logout();

        $imported = new \App\Modules\User\Models\Form(['title' => 'Imported form']);
        $imported->user_id = $user->id;
        $imported->save();

        $this->assertSame(0, WorkspaceActivityEvent::where('workspace_id', $ws->id)
            ->where('object_type', 'form')->count(),
            'an unattributed change must not be written to the ledger');
    }
    /**
     * The exclusion list must only name kinds something else really records.
     * Left unchecked it is the easiest place for a kind to go unrecorded
     * while looking deliberate.
     */
    /**
     * test_a_members_change_is_recorded_against_its_kind proves the observer
     * works, but only for Form. This checks the registration loop in
     * AppServiceProvider actually reached all nineteen models, so a kind
     * cannot fall out of the ledger while the end-to-end test stays green.
     *
     * It is a presence check: it asserts something is listening for each
     * model's delete, not that the listener is this observer. That is enough
     * to catch the failure it is here for -- the loop silently skipping a
     * kind, for instance because a class was renamed and class_exists() went
     * false.
     */
    public function test_the_observer_is_registered_for_every_resource_kind(): void
    {
        $events = \Illuminate\Database\Eloquent\Model::getEventDispatcher();

        foreach (PlannerResourceActivity::kindByModel() as $class => $kind) {
            $this->assertTrue(class_exists($class),
                "{$kind} maps to {$class}, which does not exist, so nothing observes it");
            $this->assertTrue($events->hasListeners('eloquent.deleted: '.$class),
                "nothing is listening for {$kind} deletions: {$class} never got observed");
        }
    }

    public function test_the_exclusion_list_only_names_kinds_recorded_elsewhere(): void
    {
        foreach (PlannerResourceActivity::ALREADY_RECORDED as $kind) {
            $this->assertArrayHasKey($kind, PlannerResources::TYPES,
                "'{$kind}' is excluded from the observer but is not a resource kind");
        }

        $this->assertSame(['link'], PlannerResourceActivity::ALREADY_RECORDED,
            'only link is written by hand today (LinkController); adding to this list should be deliberate');
    }
}
