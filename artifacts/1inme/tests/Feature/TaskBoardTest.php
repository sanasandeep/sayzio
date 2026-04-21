<?php

namespace Tests\Feature;

use App\Modules\User\Models\TaskAttachment;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskColumn;
use App\Modules\User\Models\TaskSubtask;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $tag = 'u'): User
    {
        return User::create([
            'name'     => $tag . ' ' . Str::random(4),
            'email'    => $tag . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function bindWorkspace(User $user): Workspace
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
        return $ws;
    }

    public function test_index_auto_seeds_personal_board_for_first_visit(): void
    {
        $user = $this->makeUser('alice');
        $this->actingAs($user)
            ->get('/user/tasks')
            ->assertStatus(200)
            ->assertSee('Personal Boards');

        $this->bindWorkspace($user);
        $this->assertSame(1, TaskBoard::query()
            ->where('scope', 'personal')->where('owner_user_id', $user->id)->count());
    }

    public function test_owner_can_create_team_board_with_starter_columns(): void
    {
        $user = $this->makeUser('alice');
        $this->actingAs($user)
            ->post('/user/tasks/boards', ['name' => 'Sprint 1', 'scope' => 'team'])
            ->assertRedirect();

        $this->bindWorkspace($user);
        $board = TaskBoard::where('name', 'Sprint 1')->first();
        $this->assertNotNull($board);
        $this->assertSame('team', $board->scope);
        // Spec calls for the canonical Todo / Doing / Done starter set.
        $names = $board->columns()->orderBy('position')->pluck('name')->all();
        $this->assertSame(['Todo', 'Doing', 'Done'], $names);
        $this->assertTrue($board->columns()->where('is_done', true)->exists());
    }

    public function test_create_card_and_move_between_columns_updates_completion(): void
    {
        $user = $this->makeUser('alice');
        $this->bindWorkspace($user);
        // Create board via service path so columns exist.
        $this->actingAs($user)->post('/user/tasks/boards', ['name' => 'B', 'scope' => 'team']);
        $board = TaskBoard::where('name', 'B')->first();
        $first = $board->columns()->orderBy('position')->first();
        $done  = $board->columns()->where('is_done', true)->first();

        $this->actingAs($user)
            ->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $first->id, 'title' => 'Ship feature']);
        $card = TaskCard::where('title', 'Ship feature')->first();
        $this->assertNotNull($card);
        $this->assertNull($card->completed_at);

        // Move to Done column → auto-complete
        $this->actingAs($user)
            ->post("/user/tasks/cards/{$card->id}/move", ['column_id' => $done->id, 'position' => 0])
            ->assertOk();
        $this->assertNotNull($card->fresh()->completed_at);

        // Move back to first column → reopen
        $this->actingAs($user)
            ->post("/user/tasks/cards/{$card->id}/move", ['column_id' => $first->id, 'position' => 0])
            ->assertOk();
        $this->assertNull($card->fresh()->completed_at);
    }

    public function test_personal_board_is_invisible_to_other_workspace_members(): void
    {
        $alice = $this->makeUser('alice');
        $bob   = $this->makeUser('bob');
        $aliceWs = $alice->ensureDefaultWorkspace();
        WorkspaceMember::create(['workspace_id' => $aliceWs->id, 'user_id' => $bob->id, 'role' => 'editor']);

        // Alice creates a personal board in her workspace.
        session(['active_workspace_id' => $aliceWs->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'Alice Private', 'scope' => 'personal']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')
            ->where('name', 'Alice Private')->first();
        $this->assertNotNull($board);

        // Bob (editor in Alice's workspace) opens it — must 404.
        session(['active_workspace_id' => $aliceWs->id]);
        $this->actingAs($bob)
            ->get("/user/tasks/boards/{$board->id}")
            ->assertNotFound();
    }

    public function test_viewer_cannot_create_board(): void
    {
        $alice = $this->makeUser('alice');
        $bob   = $this->makeUser('bob');
        $aliceWs = $alice->ensureDefaultWorkspace();
        WorkspaceMember::create(['workspace_id' => $aliceWs->id, 'user_id' => $bob->id, 'role' => 'viewer']);

        session(['active_workspace_id' => $aliceWs->id]);
        $this->actingAs($bob)
            ->post('/user/tasks/boards', ['name' => 'Hacky', 'scope' => 'team'])
            ->assertForbidden();
    }

    public function test_assignee_receives_notification(): void
    {
        $alice = $this->makeUser('alice');
        $bob   = $this->makeUser('bob');
        $aliceWs = $alice->ensureDefaultWorkspace();
        WorkspaceMember::create(['workspace_id' => $aliceWs->id, 'user_id' => $bob->id, 'role' => 'editor']);

        session(['active_workspace_id' => $aliceWs->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'Team', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'Team')->first();
        $col = $board->columns()->orderBy('position')->first();
        $this->actingAs($alice)
            ->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'Help bob']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'Help bob')->first();

        $this->actingAs($alice)
            ->post("/user/tasks/cards/{$card->id}/assign", ['user_id' => $bob->id])
            ->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $bob->id,
            'type'    => 'task_assigned',
        ]);
    }

    public function test_cannot_assign_user_outside_workspace(): void
    {
        $alice   = $this->makeUser('alice');
        $outsider = $this->makeUser('out');
        $aliceWs = $alice->ensureDefaultWorkspace();

        session(['active_workspace_id' => $aliceWs->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'X', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'X')->first();
        $col = $board->columns()->orderBy('position')->first();
        $this->actingAs($alice)
            ->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'T']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'T')->first();

        $this->actingAs($alice)
            ->post("/user/tasks/cards/{$card->id}/assign", ['user_id' => $outsider->id])
            ->assertStatus(422);
    }

    public function test_personal_board_owner_can_create_cards_even_as_viewer(): void
    {
        // A user added to someone else's workspace as a viewer should still
        // be able to maintain their own personal board on their personal
        // workspace, regardless of role on the shared one.
        $alice = $this->makeUser('alice');
        $bob   = $this->makeUser('bob');
        $aliceWs = $alice->ensureDefaultWorkspace();
        WorkspaceMember::create(['workspace_id' => $aliceWs->id, 'user_id' => $bob->id, 'role' => 'viewer']);

        // Bob acts inside his own personal workspace.
        $bobWs = $bob->ensureDefaultWorkspace();
        session(['active_workspace_id' => $bobWs->id]);

        // Bob creates a personal board on his workspace and adds a card.
        $this->actingAs($bob)
            ->post('/user/tasks/boards', ['name' => 'Bob TODO', 'scope' => 'personal'])
            ->assertRedirect();
        $board = TaskBoard::query()->withoutGlobalScope('workspace')
            ->where('name', 'Bob TODO')->first();
        $this->assertNotNull($board);
        $col = $board->columns()->orderBy('position')->first();

        $this->actingAs($bob)
            ->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'Buy milk'])
            ->assertRedirect();
        $this->assertDatabaseHas('task_cards', ['title' => 'Buy milk', 'board_id' => $board->id]);
    }

    public function test_cross_workspace_subtask_returns_404_not_500(): void
    {
        // Subtask resolved through global model binding for a card belonging
        // to another workspace must 404 cleanly, not crash on null relation.
        $alice = $this->makeUser('alice');
        $bob   = $this->makeUser('bob');
        $aliceWs = $alice->ensureDefaultWorkspace();
        $bobWs   = $bob->ensureDefaultWorkspace();

        session(['active_workspace_id' => $aliceWs->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'A', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'A')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $this->actingAs($alice)->post("/user/tasks/boards/{$board->id}/cards",
            ['column_id' => $col->id, 'title' => 'Card']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'Card')->first();
        $this->actingAs($alice)->post("/user/tasks/cards/{$card->id}/subtasks", ['title' => 'Sub'])->assertOk();
        $sub = TaskSubtask::query()->withoutGlobalScope('workspace')->first();

        // Bob is signed-in to *his* workspace; the subtask doesn't belong here.
        session(['active_workspace_id' => $bobWs->id]);
        $this->actingAs($bob)
            ->post("/user/tasks/subtasks/{$sub->id}/toggle")
            ->assertNotFound();
    }

    public function test_attachment_upload_and_size_limit(): void
    {
        Storage::fake('public');
        $alice = $this->makeUser('alice');
        session(['active_workspace_id' => $alice->ensureDefaultWorkspace()->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'Att', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'Att')->first();
        $col = $board->columns()->orderBy('position')->first();
        $this->actingAs($alice)->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'C']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'C')->first();

        // Happy path
        $this->actingAs($alice)
            ->post("/user/tasks/cards/{$card->id}/attachments", [
                'file' => UploadedFile::fake()->create('spec.pdf', 200, 'application/pdf'),
            ])
            ->assertOk();
        $this->assertSame(1, TaskAttachment::query()->withoutGlobalScope('workspace')->count());

        // Oversized file (11MB) gets rejected by validation.
        $this->actingAs($alice)
            ->post("/user/tasks/cards/{$card->id}/attachments", [
                'file' => UploadedFile::fake()->create('big.bin', 11 * 1024),
            ])
            ->assertStatus(302); // validation redirect
        $this->assertSame(1, TaskAttachment::query()->withoutGlobalScope('workspace')->count());
    }

    public function test_mention_in_comment_pings_workspace_member(): void
    {
        $alice = $this->makeUser('alice');
        $bob   = $this->makeUser('bob');
        $bob->update(['name' => 'bobster']); // single-token name for the @bobster mention
        $aliceWs = $alice->ensureDefaultWorkspace();
        WorkspaceMember::create(['workspace_id' => $aliceWs->id, 'user_id' => $bob->id, 'role' => 'editor']);

        session(['active_workspace_id' => $aliceWs->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'M', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'M')->first();
        $col = $board->columns()->orderBy('position')->first();
        $this->actingAs($alice)->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'X']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'X')->first();

        $this->actingAs($alice)
            ->post("/user/tasks/cards/{$card->id}/comments", ['body' => 'hey @bobster please review'])
            ->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $bob->id,
            'type'    => 'task_mention',
        ]);
    }

    public function test_due_reminder_command_notifies_assignees_and_dedupes(): void
    {
        $alice = $this->makeUser('alice');
        $bob   = $this->makeUser('bob');
        $ws    = $alice->ensureDefaultWorkspace();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $bob->id, 'role' => 'editor']);

        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'Due', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'Due')->first();
        $col = $board->columns()->orderBy('position')->first();
        $this->actingAs($alice)->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'Late']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'Late')->first();
        $card->update(['due_date' => now()->subDays(2), 'completed_at' => null]);
        $card->assignees()->attach($bob->id);

        // Drop the workspace binding to mimic CLI execution context.
        app()->forgetInstance('current_workspace');
        session()->forget('active_workspace_id');

        // The scheduler is now hourly + workspace-tz gated (only fires at
        // local 8 AM); --force bypasses the gate for ad-hoc CLI runs and
        // for this test, which only cares about the dedupe contract.
        $this->artisan('tasks:send-due-reminders', ['--force' => true])->assertSuccessful();
        $this->artisan('tasks:send-due-reminders', ['--force' => true])->assertSuccessful(); // dedupe

        $count = UserNotification::where('user_id', $bob->id)
            ->where('type', 'task_overdue')
            ->count();
        $this->assertSame(1, $count, 'reminder should fire once per day per card per assignee');
    }

    public function test_editor_can_delete_card_and_board_but_viewer_cannot(): void
    {
        // Spec: team mutations (create / edit / delete) are open to admin
        // and editor; viewers are rejected.
        $owner  = $this->makeUser('owner');
        $editor = $this->makeUser('ed');
        $viewer = $this->makeUser('vw');
        $ws     = $owner->ensureDefaultWorkspace();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $editor->id, 'role' => 'editor']);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $viewer->id, 'role' => 'viewer']);

        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($owner)->post('/user/tasks/boards', ['name' => 'EdBoard', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'EdBoard')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $this->actingAs($owner)->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'Killable']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'Killable')->first();

        // Viewer cannot delete a card or a board.
        $this->actingAs($viewer)->delete("/user/tasks/cards/{$card->id}")->assertStatus(403);

        // Editor can delete a card.
        $this->actingAs($editor)->delete("/user/tasks/cards/{$card->id}")->assertOk();
        $this->assertDatabaseMissing('task_cards', ['id' => $card->id]);

        // Editor can also delete the entire team board (controller redirects
        // back to the boards index on success).
        $this->actingAs($editor)->delete("/user/tasks/boards/{$board->id}")->assertRedirect();
        $this->assertDatabaseMissing('task_boards', ['id' => $board->id]);
    }

    public function test_subtask_reorder_persists_new_positions(): void
    {
        $alice = $this->makeUser('reord');
        $ws    = $alice->ensureDefaultWorkspace();
        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'ReordB', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'ReordB')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $this->actingAs($alice)->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'Reord']);
        $card  = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'Reord')->first();

        // Seed three subtasks in order A, B, C.
        $ids = [];
        foreach (['A', 'B', 'C'] as $t) {
            $r = $this->actingAs($alice)->post("/user/tasks/cards/{$card->id}/subtasks", ['title' => $t])->json();
            $ids[$t] = $r['subtask']['id'];
        }
        // Reverse to C, B, A.
        $this->actingAs($alice)
            ->post("/user/tasks/cards/{$card->id}/subtasks/reorder", ['order' => [$ids['C'], $ids['B'], $ids['A']]])
            ->assertOk()->assertJson(['ok' => true, 'updated' => 3]);

        $titles = $card->subtasks()->orderBy('position')->pluck('title')->all();
        $this->assertSame(['C', 'B', 'A'], $titles);

        // Foreign subtask IDs (not belonging to this card) must be silently
        // dropped — the controller intersects against the card's own ids,
        // so a crafted payload cannot reorder another card's checklist.
        $foreignFakeId = 999999;
        $this->actingAs($alice)
            ->post("/user/tasks/cards/{$card->id}/subtasks/reorder",
                  ['order' => [$foreignFakeId, $ids['A'], $ids['B'], $ids['C']]])
            ->assertOk()->assertJson(['ok' => true, 'updated' => 3]);
        // Order is now A, B, C again.
        $this->assertSame(['A', 'B', 'C'],
            $card->subtasks()->orderBy('position')->pluck('title')->all());
    }

    public function test_personal_board_cannot_be_created_in_team_workspace(): void
    {
        $owner = $this->makeUser('teamown');
        $other = $this->makeUser('other');
        // Owner creates a non-personal team workspace and adds `other`.
        $teamWs = \App\Modules\User\Models\Workspace::create([
            'owner_user_id' => $owner->id, 'name' => 'TeamWS',
            'slug' => 'teamws-' . $owner->id, 'is_personal' => false,
        ]);
        WorkspaceMember::create(['workspace_id' => $teamWs->id, 'user_id' => $other->id, 'role' => 'editor']);

        // `other` activates the team workspace and tries to create a
        // personal board there: must be rejected.
        session(['active_workspace_id' => $teamWs->id]);
        $this->actingAs($other)->post('/user/tasks/boards',
            ['name' => 'StealthMine', 'scope' => 'personal'])->assertStatus(422);
        $this->assertDatabaseMissing('task_boards', [
            'workspace_id' => $teamWs->id, 'name' => 'StealthMine',
        ]);

        // The team-workspace index must NOT have auto-seeded a personal board.
        $this->actingAs($other)->get('/user/tasks')->assertOk();
        $this->assertDatabaseMissing('task_boards', [
            'workspace_id' => $teamWs->id, 'scope' => 'personal',
        ]);
    }

    public function test_personal_board_provisioner_uses_todo_doing_done(): void
    {
        $u = $this->makeUser('newprov');
        // ensureDefaultWorkspace runs the provisioner inside makeUser.
        $board = TaskBoard::query()->withoutGlobalScope('workspace')
            ->where('owner_user_id', $u->id)->where('scope', 'personal')->first();
        $this->assertNotNull($board);
        $this->assertSame(['Todo', 'Doing', 'Done'],
            $board->columns()->orderBy('position')->pluck('name')->all());
    }

    public function test_due_reminder_dedupe_is_workspace_timezone_safe(): void
    {
        // Owner in UTC+14 (Pacific/Kiritimati); a single local day spans
        // two UTC days, so a server-day-keyed dedupe would let this run
        // twice. The UTC-window dedupe must collapse it to one notification.
        $owner = $this->makeUser('tzowner');
        $owner->forceFill(['timezone' => 'Pacific/Kiritimati'])->save();
        $assignee = $this->makeUser('tzbob');
        $ws       = $owner->ensureDefaultWorkspace();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $assignee->id, 'role' => 'editor']);

        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($owner)->post('/user/tasks/boards', ['name' => 'TzBoard', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'TzBoard')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $this->actingAs($owner)->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'TzLate']);
        $card  = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'TzLate')->first();
        $card->update(['due_date' => now()->subDays(2), 'completed_at' => null]);
        $card->assignees()->attach($assignee->id);

        app()->forgetInstance('current_workspace');
        session()->forget('active_workspace_id');

        // Two forced runs in quick succession must produce exactly one row.
        $this->artisan('tasks:send-due-reminders', ['--force' => true])->assertSuccessful();
        $this->artisan('tasks:send-due-reminders', ['--force' => true])->assertSuccessful();

        $count = UserNotification::where('user_id', $assignee->id)
            ->where('type', 'task_overdue')
            ->where('data->card_id', $card->id)
            ->count();
        $this->assertSame(1, $count, 'tz-window dedupe must collapse repeated runs');
    }

    public function test_show_card_returns_activities_with_ui_contract_keys(): void
    {
        // Locks the wire format the drawer's Activity tab depends on:
        // each row must expose `type`, `data`, and `created_at` (with the
        // user relation eager-loaded), ordered newest-first.
        $alice = $this->makeUser('alice');
        $ws    = $alice->ensureDefaultWorkspace();
        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'ActB', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'ActB')->first();
        $col   = $board->columns()->orderBy('position')->first();
        $this->actingAs($alice)->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'Activated']);
        $card  = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'Activated')->first();
        // Add a comment so we get a second activity row to test ordering.
        $this->actingAs($alice)->post("/user/tasks/cards/{$card->id}/comments", ['body' => 'first note']);

        $resp = $this->actingAs($alice)->get("/user/tasks/cards/{$card->id}")->assertOk()->json('card');
        $this->assertIsArray($resp['activities'] ?? null);
        $this->assertGreaterThanOrEqual(2, count($resp['activities']));
        foreach ($resp['activities'] as $row) {
            $this->assertArrayHasKey('type', $row,       'activity must expose `type` for the UI');
            $this->assertArrayHasKey('data', $row,       'activity must expose `data` for the UI');
            $this->assertArrayHasKey('created_at', $row, 'activity must expose `created_at` for the UI');
        }
        // Newest-first ordering (commented row should precede created row).
        $this->assertSame('commented', $resp['activities'][0]['type']);
    }

    public function test_archive_and_unarchive_board_authz(): void
    {
        // Spec: editors are part of "team mutations" — they can archive
        // and unarchive. Viewers must still be rejected.
        $owner  = $this->makeUser('arc');
        $editor = $this->makeUser('arced');
        $viewer = $this->makeUser('arcvw');
        $ws     = $owner->ensureDefaultWorkspace();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $editor->id, 'role' => 'editor']);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $viewer->id, 'role' => 'viewer']);

        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($owner)->post('/user/tasks/boards', ['name' => 'Arc1', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'Arc1')->first();

        // Viewer is rejected.
        $this->actingAs($viewer)->post("/user/tasks/boards/{$board->id}/archive")->assertStatus(403);
        $this->assertNull($board->fresh()->archived_at);

        // Editor can archive and unarchive.
        $this->actingAs($editor)->post("/user/tasks/boards/{$board->id}/archive")->assertRedirect();
        $this->assertNotNull($board->fresh()->archived_at);
        $this->actingAs($editor)->post("/user/tasks/boards/{$board->id}/unarchive")->assertRedirect();
        $this->assertNull($board->fresh()->archived_at);
    }

    public function test_attachment_blocks_disallowed_mime_and_extension(): void
    {
        Storage::fake('local');
        $alice = $this->makeUser('alice');
        session(['active_workspace_id' => $alice->ensureDefaultWorkspace()->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'Sec', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'Sec')->first();
        $col = $board->columns()->orderBy('position')->first();
        $this->actingAs($alice)->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'C']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'C')->first();

        // SVG is a known same-origin XSS vector — must be rejected.
        $this->actingAs($alice)
            ->post("/user/tasks/cards/{$card->id}/attachments", [
                'file' => UploadedFile::fake()->createWithContent('evil.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            ])
            ->assertStatus(302); // validation redirect
        $this->assertSame(0, TaskAttachment::query()->withoutGlobalScope('workspace')->count());

        // .html disguised as text/plain — extension blocklist must still kill it.
        $this->actingAs($alice)
            ->post("/user/tasks/cards/{$card->id}/attachments", [
                'file' => UploadedFile::fake()->createWithContent('payload.html', '<script>alert(1)</script>'),
            ])
            ->assertStatus(302);
        $this->assertSame(0, TaskAttachment::query()->withoutGlobalScope('workspace')->count());
    }

    public function test_html_sanitizer_strips_xss_payloads(): void
    {
        $alice = $this->makeUser('alice');
        session(['active_workspace_id' => $alice->ensureDefaultWorkspace()->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'X', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'X')->first();
        $col = $board->columns()->orderBy('position')->first();
        $this->actingAs($alice)->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'C']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'C')->first();

        $payload = '<p>Hello <b>world</b></p>'
            . '<script>alert(1)</script>'
            . '<img src=x onerror=alert(1)>'
            . '<a href="javascript:alert(1)" onclick=alert(2) onmouseover="alert(3)">click</a>'
            . '<a href="JaVaScRiPt&#58;alert(4)">enc</a>'
            . '<iframe src="https://evil.example"></iframe>';

        $this->actingAs($alice)
            ->patch("/user/tasks/cards/{$card->id}", ['description_html' => $payload])
            ->assertOk();

        $stored = $card->fresh()->description_html;
        $this->assertStringNotContainsStringIgnoringCase('<script', $stored);
        $this->assertStringNotContainsStringIgnoringCase('<iframe', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onmouseover', $stored);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $stored);
        // Safe content survives.
        $this->assertStringContainsString('Hello', $stored);
        $this->assertStringContainsString('<b>world</b>', $stored);
    }

    public function test_personal_board_auto_creates_for_new_user(): void
    {
        // Creating a brand-new user should also create their personal board
        // via the User::created hook (PersonalTaskBoardProvisioner).
        $u = $this->makeUser('newbie');
        $this->assertSame(1, TaskBoard::query()->withoutGlobalScope('workspace')
            ->where('owner_user_id', $u->id)
            ->where('scope', 'personal')
            ->count());
    }

    public function test_deleting_column_moves_cards_to_fallback_column(): void
    {
        $alice = $this->makeUser('alice');
        session(['active_workspace_id' => $alice->ensureDefaultWorkspace()->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'Move', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'Move')->first();
        $first  = $board->columns()->orderBy('position')->first();
        $second = $board->columns()->orderBy('position')->skip(1)->first();
        $this->actingAs($alice)->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $first->id, 'title' => 'Keep me']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'Keep me')->first();

        $this->actingAs($alice)
            ->delete("/user/tasks/columns/{$first->id}")
            ->assertRedirect();

        $this->assertSame($second->id, $card->fresh()->column_id);
    }
}
