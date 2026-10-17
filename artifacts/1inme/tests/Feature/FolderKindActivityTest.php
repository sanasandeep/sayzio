<?php

namespace Tests\Feature;

use App\Modules\User\Models\Project;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Planner\FolderKindActivity;
use App\Services\Planner\FolderPeople;
use App\Services\Planner\PlannerResources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The map says who has been working in each station. These guards are about
 * that claim being true: scoped to this folder, owner left out on purpose,
 * and every recorded change accounted for rather than quietly dropped.
 *
 * The ledger has no folder column, so folder scoping is done by resource id.
 * That is the assumption most likely to rot, and the one most likely to be
 * wrong in a way nobody notices -- a member who edited a form in a different
 * folder appearing here looks perfectly plausible on screen.
 */
class FolderKindActivityTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceFor(User $owner)
    {
        $ws = app(WorkspaceContext::class)->resolve($owner);
        app()->instance('current_workspace', $ws);

        return $ws;
    }

    private function recordChange($ws, ?User $actor, string $kind, int $objectId, string $label = 'Thing'): void
    {
        DB::table('workspace_activity_events')->insert([
            'workspace_id' => $ws->id,
            'actor_user_id' => $actor?->id,
            'action' => $kind.'.update',
            'object_type' => $kind,
            'object_id' => $objectId,
            'object_label' => $label,
            'created_at' => now(),
        ]);
    }

    /** @return array{0:object,1:array} a folder and the $resources array the map draws from */
    private function folderHolding(User $owner, string $kind, array $rows): array
    {
        $project = Project::create(['user_id' => $owner->id, 'name' => 'Launch']);
        $resources = [];
        foreach ($rows as $row) {
            $resources[] = ['kind' => $kind, 'row' => $row, 'label' => 'x', 'url' => '#'];
        }

        return [$project, $resources];
    }

    /**
     * The seam between this service and the observer: both have to agree that
     * a form's activity is filed under the kind 'form'. Written through the
     * real path rather than an insert, so a change to either side shows up.
     */
    public function test_a_members_real_change_lands_on_that_kinds_station(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $ws = $this->workspaceFor($owner);

        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $form = new \App\Modules\User\Models\Form(['title' => 'Partner signup']);
        $form->user_id = $member->id;
        $form->save();

        [$project, $resources] = $this->folderHolding($owner, 'form', [$form]);
        $data = (new FolderKindActivity)->data($project, $resources);

        $this->assertArrayHasKey('form', $data['byKind'],
            'a form the observer recorded did not reach the Forms station');
        $people = $data['byKind']['form']['people'];
        $this->assertCount(1, $people);
        $this->assertSame((int) $member->id, $people[0]['id']);
        $this->assertSame($member->name, $people[0]['name']);
    }

    /**
     * The scoping guard. Same kind, same workspace, different folder: it must
     * not appear here, or every station eventually shows everybody.
     */
    public function test_work_in_another_folder_does_not_appear_on_this_one(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $ws = $this->workspaceFor($owner);

        $mine = new \App\Modules\User\Models\Form(['title' => 'In this folder']);
        $mine->user_id = $owner->id;
        $mine->save();

        $theirs = new \App\Modules\User\Models\Form(['title' => 'In another folder']);
        $theirs->user_id = $owner->id;
        $theirs->save();

        $this->recordChange($ws, $member, 'form', (int) $theirs->id);

        [$project, $resources] = $this->folderHolding($owner, 'form', [$mine]);
        $data = (new FolderKindActivity)->data($project, $resources);

        $this->assertSame([], $data['byKind'],
            'a change to a form this folder does not hold was attributed to this folder');
        $this->assertSame(0, $data['counted']);
    }

    /**
     * Sana: the owner "is always" there, so counting them says nothing about
     * who else is. Excluded from the faces, but counted, so the figures still
     * add up and the view can say what it left out.
     */
    public function test_the_owners_own_changes_are_excluded_but_still_counted(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $ws = $this->workspaceFor($owner);

        $form = new \App\Modules\User\Models\Form(['title' => 'Shared form']);
        $form->user_id = $owner->id;
        $form->save();

        DB::table('workspace_activity_events')->delete();

        $this->recordChange($ws, $owner, 'form', (int) $form->id);
        $this->recordChange($ws, $owner, 'form', (int) $form->id);
        $this->recordChange($ws, $member, 'form', (int) $form->id);
        $this->recordChange($ws, null, 'form', (int) $form->id);

        [$project, $resources] = $this->folderHolding($owner, 'form', [$form]);
        $data = (new FolderKindActivity)->data($project, $resources);

        $this->assertCount(1, $data['byKind']['form']['people'],
            'only the member should have a face on the station');
        $this->assertSame((int) $member->id, $data['byKind']['form']['people'][0]['id']);

        $this->assertSame(2, $data['owner_changes']);
        $this->assertSame(1, $data['counted']);
        $this->assertSame(1, $data['unattributed']);
        $this->assertSame(
            $data['total'],
            $data['counted'] + $data['owner_changes'] + $data['unattributed'],
            'every recorded change must land in exactly one bucket',
        );
    }

    /**
     * An empty station has two possible meanings -- nobody worked here, or
     * nothing was being recorded yet -- and the view can only tell them apart
     * if the service hands it the date coverage began.
     */
    public function test_coverage_start_is_reported_so_empty_does_not_read_as_nobody(): void
    {
        $owner = User::factory()->create();
        $ws = $this->workspaceFor($owner);

        $project = Project::create(['user_id' => $owner->id, 'name' => 'Quiet']);

        DB::table('workspace_activity_events')->delete();
        $this->assertNull((new FolderKindActivity)->data($project, [])['first_recorded_at'],
            'with nothing recorded the view must be able to say so');

        $this->recordChange($ws, $owner, 'form', 1);
        $this->assertNotNull((new FolderKindActivity)->data($project, [])['first_recorded_at'],
            'once something is recorded the view must be able to date the coverage');
    }

    /**
     * The People panel used to count task-card events only, and said so
     * nowhere. Once the map started showing faces from the ledger, a member
     * could be on four stations and read "0 actions" on the same page. The
     * two numbers are now separate and separately named -- never summed,
     * because TaskActivity and the ledger both record task work and adding
     * them would count it twice.
     */
    public function test_a_card_counts_task_actions_and_ledger_changes_separately(): void
    {
        $owner = User::factory()->create();
        $ws = $this->workspaceFor($owner);

        $form = new \App\Modules\User\Models\Form(['title' => 'Owner edits this']);
        $form->user_id = $owner->id;
        $form->save();

        DB::table('workspace_activity_events')->delete();
        $this->recordChange($ws, $owner, 'form', (int) $form->id);
        $this->recordChange($ws, $owner, 'form', (int) $form->id);

        [$project, $resources] = $this->folderHolding($owner, 'form', [$form]);
        $activity = (new FolderKindActivity)->data($project, $resources);
        $people = (new FolderPeople)->data($project, $resources, $activity)['people'];

        $card = collect($people)->firstWhere('id', $owner->id);
        $this->assertNotNull($card, 'the owner is always on the roster');

        // The owner is kept off the stations on purpose. Their card must not
        // inherit that exclusion and read zero -- they are not idle.
        $this->assertSame(2, $card['changes'],
            "the owner's own changes should show on their card even though they are left off the map");
        $this->assertSame(0, $card['actions'],
            'task actions are a different count from ledger changes, not the same one');
    }

    /** With no ledger to read, a card says nothing rather than claiming zero. */
    public function test_a_card_shows_no_change_count_when_there_is_no_ledger_to_read(): void
    {
        $owner = User::factory()->create();
        $this->workspaceFor($owner);
        $project = \App\Modules\User\Models\Project::create(['user_id' => $owner->id, 'name' => 'No ledger']);

        $people = (new FolderPeople)->data($project, [])['people'];
        $card = collect($people)->firstWhere('id', $owner->id);

        $this->assertNotNull($card);
        $this->assertNull($card['changes'],
            'without the ledger the card must not assert that nobody changed anything');
    }

    /**
     * `link` is deliberately left out of the coverage date: LinkController has
     * recorded it for far longer, so counting it would date the ledger years
     * before it really started covering the other eighteen kinds.
     */
    public function test_the_coverage_date_ignores_the_kind_recorded_long_before(): void
    {
        $covered = FolderKindActivity::newlyCoveredKinds();

        $this->assertNotContains('link', $covered,
            'link predates the observer; including it would misdate the coverage');
        $this->assertCount(count(PlannerResources::TYPES) - 1, $covered);

        foreach ($covered as $kind) {
            $this->assertArrayHasKey($kind, PlannerResources::TYPES,
                "'{$kind}' is treated as newly covered but is not a resource kind");
        }
    }
}
