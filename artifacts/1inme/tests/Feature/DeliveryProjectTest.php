<?php

namespace Tests\Feature;

use App\Modules\User\Models\ClientPortal;
use App\Modules\User\Models\ClientPortalLink;
use App\Modules\User\Models\ClientPortalShare;
use App\Modules\User\Models\DeliveryProject;
use App\Modules\User\Models\DeliveryProjectTask;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #3567 — Delivery Projects across the three access paths:
 *   1. workspace team members (gated by tasks.view / tasks.edit),
 *   2. logged-in clients via a ClientPortalShare, and
 *   3. anonymous buyers via the unguessable share_token page.
 *
 * Also covers task CRUD driving overall progress, project auto-complete
 * (completed_at set/cleared), and the warranty date/reminder command.
 */
class DeliveryProjectTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $tag = 'u'): User
    {
        $user = User::create([
            'name'     => $tag . ' ' . Str::random(4),
            'email'    => $tag . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function memberOf(Workspace $ws, User $user, string $role): void
    {
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $user->id,
            'role'         => $role,
        ]);
    }

    private function makeProject(Workspace $ws, array $attrs = []): DeliveryProject
    {
        return DeliveryProject::create(array_merge([
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $ws->owner_user_id,
            'title'              => 'Proj ' . Str::random(4),
            'status'             => DeliveryProject::STATUS_ACTIVE,
        ], $attrs));
    }

    // ----- 1) Workspace-member access gating --------------------------------

    public function test_member_with_tasks_view_can_open_index_and_project(): void
    {
        $owner  = $this->makeUser('owner');
        $ws     = $owner->ownedWorkspaces()->first();
        $viewer = $this->makeUser('vw');
        $this->memberOf($ws, $viewer, 'viewer'); // universal view

        $project = $this->makeProject($ws, ['title' => 'Viewer sees me']);

        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($viewer)->get('/user/delivery-projects')->assertOk();
        $this->actingAs($viewer)
            ->get("/user/delivery-projects/{$project->id}")
            ->assertOk()
            ->assertSee('Viewer sees me');
    }

    public function test_viewer_cannot_create_or_mutate_project(): void
    {
        $owner  = $this->makeUser('owner');
        $ws     = $owner->ownedWorkspaces()->first();
        $viewer = $this->makeUser('vw');
        $this->memberOf($ws, $viewer, 'viewer');
        $project = $this->makeProject($ws);

        session(['active_workspace_id' => $ws->id]);

        // create/store require tasks.edit → 403 for a viewer.
        $this->actingAs($viewer)->get('/user/delivery-projects/create')->assertForbidden();
        $this->actingAs($viewer)->post('/user/delivery-projects', ['title' => 'Nope'])->assertForbidden();
        $this->actingAs($viewer)
            ->post("/user/delivery-projects/{$project->id}/tasks", ['title' => 'Nope'])
            ->assertForbidden();
    }

    public function test_editor_can_create_project_in_active_workspace(): void
    {
        $owner  = $this->makeUser('owner');
        $ws     = $owner->ownedWorkspaces()->first();
        $editor = $this->makeUser('ed');
        $this->memberOf($ws, $editor, 'editor');

        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($editor)
            ->post('/user/delivery-projects', ['title' => 'Editor project'])
            ->assertRedirect();

        $this->assertDatabaseHas('delivery_projects', [
            'workspace_id' => $ws->id,
            'title'        => 'Editor project',
        ]);
    }

    // ----- Task CRUD → overall progress + auto-complete ---------------------

    public function test_task_crud_updates_overall_progress(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws);

        session(['active_workspace_id' => $ws->id]);

        $this->actingAs($owner)
            ->post("/user/delivery-projects/{$project->id}/tasks", ['title' => 'A'])
            ->assertRedirect();
        $this->actingAs($owner)
            ->post("/user/delivery-projects/{$project->id}/tasks", ['title' => 'B'])
            ->assertRedirect();

        $this->assertSame(0, $project->fresh()->progressPercent());

        $taskA = DeliveryProjectTask::query()->withoutGlobalScope('workspace')
            ->where('project_id', $project->id)->where('title', 'A')->first();

        // Mark A done → 100%; project average across A(100) + B(0) = 50.
        $this->actingAs($owner)
            ->patchJson("/user/delivery-projects/tasks/{$taskA->id}", ['status' => 'done'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('done', $taskA->fresh()->status);
        $this->assertSame(100, (int) $taskA->fresh()->progress);
        $this->assertSame(50, $project->fresh()->progressPercent());
    }

    public function test_update_flips_completed_at_on_status_change(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws);

        session(['active_workspace_id' => $ws->id]);

        $this->assertNull($project->completed_at);

        $this->actingAs($owner)
            ->put("/user/delivery-projects/{$project->id}", ['status' => 'completed'])
            ->assertRedirect();
        $this->assertNotNull($project->fresh()->completed_at);

        // Re-opening clears the completion timestamp.
        $this->actingAs($owner)
            ->put("/user/delivery-projects/{$project->id}", ['status' => 'active'])
            ->assertRedirect();
        $this->assertNull($project->fresh()->completed_at);
    }

    // ----- 3) Anonymous buyer via share_token -------------------------------

    public function test_anonymous_share_token_page_is_public(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws, ['title' => 'Public delivery']);

        // No auth, no session — the token in the URL is the only authenticator.
        $this->get('/dp/' . $project->share_token)
            ->assertOk()
            ->assertSee('Public delivery');
    }

    public function test_invalid_share_token_returns_404(): void
    {
        $this->get('/dp/' . str_repeat('z', 48))->assertNotFound();
    }

    public function test_regenerating_share_token_invalidates_the_old_link(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws, ['title' => 'Rotate me']);
        $oldToken = $project->share_token;

        session(['active_workspace_id' => $ws->id]);
        $this->actingAs($owner)
            ->post("/user/delivery-projects/{$project->id}/share-token")
            ->assertRedirect();

        $newToken = $project->fresh()->share_token;
        $this->assertNotSame($oldToken, $newToken);

        $this->get('/dp/' . $oldToken)->assertNotFound();
        $this->get('/dp/' . $newToken)->assertOk()->assertSee('Rotate me');
    }

    // ----- 2) Logged-in client via ClientPortalShare ------------------------

    public function test_client_portal_access_requires_a_share(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws, ['title' => 'Portal delivery']);

        $portal = ClientPortal::create([
            'workspace_id' => $ws->id,
            'name'         => 'Acme portal',
            'is_enabled'   => true,
        ]);
        $link = ClientPortalLink::create([
            'portal_id'    => $portal->id,
            'workspace_id' => $ws->id,
            'email'        => 'client@example.com',
            'token'        => ClientPortalLink::newToken(),
        ]);

        // Without a share for this project the portal must 404.
        $this->withSession(['portal_link_id' => $link->id])
            ->get(route('portal.delivery-project', $project->id))
            ->assertNotFound();

        // Once shared, the client can view it.
        ClientPortalShare::create([
            'portal_id'      => $portal->id,
            'workspace_id'   => $ws->id,
            'shareable_type' => ClientPortalShare::TYPE_DELIVERY_PROJECT,
            'shareable_id'   => $project->id,
            'label'          => 'Your delivery',
        ]);

        $this->withSession(['portal_link_id' => $link->id])
            ->get(route('portal.delivery-project', $project->id))
            ->assertOk()
            ->assertSee('Portal delivery');
    }

    public function test_portal_without_session_redirects_to_gone(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws);

        // No portal_link_id in session → the portal session middleware bounces.
        $this->get(route('portal.delivery-project', $project->id))
            ->assertRedirect(route('portal.gone'));
    }

    // ----- Warranty date / reminder command ---------------------------------

    public function test_warranty_ending_soon_notifies_creator_once(): void
    {
        $owner = $this->makeUser('owner');
        $ws    = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws, [
            'title'                  => 'Under warranty',
            'warranty_expires_at'    => now()->addDays(3),
            'warranty_reminder_days' => 7, // lead date already reached
        ]);

        $this->artisan('delivery-projects:warranty-reminders', ['--force' => true])->assertSuccessful();
        $this->artisan('delivery-projects:warranty-reminders', ['--force' => true])->assertSuccessful(); // dedupe

        $count = UserNotification::where('user_id', $owner->id)
            ->where('type', 'delivery_project_warranty_ending')
            ->count();
        $this->assertSame(1, $count);
        $this->assertNotNull($project->fresh()->warranty_reminder_sent_at);
    }

    public function test_warranty_expired_notifies_creator_once(): void
    {
        $owner = $this->makeUser('owner');
        $ws    = $owner->ownedWorkspaces()->first();
        $project = $this->makeProject($ws, [
            'title'                  => 'Expired warranty',
            'warranty_expires_at'    => now()->subDay(),
            'warranty_reminder_days' => 7,
        ]);

        $this->artisan('delivery-projects:warranty-reminders', ['--force' => true])->assertSuccessful();
        $this->artisan('delivery-projects:warranty-reminders', ['--force' => true])->assertSuccessful(); // dedupe

        $expired = UserNotification::where('user_id', $owner->id)
            ->where('type', 'delivery_project_warranty_expired')
            ->count();
        $this->assertSame(1, $expired);

        // The expired path suppresses a now-pointless "ending soon" reminder.
        $ending = UserNotification::where('user_id', $owner->id)
            ->where('type', 'delivery_project_warranty_ending')
            ->count();
        $this->assertSame(0, $ending);

        $fresh = $project->fresh();
        $this->assertNotNull($fresh->warranty_expired_notified_at);
        $this->assertNotNull($fresh->warranty_reminder_sent_at);
    }
}
