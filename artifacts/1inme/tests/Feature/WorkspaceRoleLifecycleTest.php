<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceInvite;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspaceRoleDirectory;
use App\Modules\User\Services\WorkspaceRoleMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceRoleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_lifecycle_preserves_assignments_and_deleted_defaults_stay_deleted(): void
    {
        $owner = User::factory()->create();
        $ws = $owner->ownedWorkspaces()->first();
        $member = User::factory()->create();
        $seat = WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->assertCount(5, WorkspaceRoleDirectory::names($ws));
        $this->post('/user/team/roles/create', ['name' => 'Campaign reviewer'])->assertRedirect();
        $this->assertFalse(WorkspaceRoleMatrix::forWorkspace($ws)['custom_campaign_reviewer']['links.view']);
        $this->post('/user/team/roles/rename', ['role' => 'editor', 'name' => 'Content creator'])->assertRedirect();
        $this->assertSame('editor', $seat->fresh()->role);
        $this->assertSame('Content creator', WorkspaceRoleDirectory::names($ws)['editor']);
        $invite = WorkspaceInvite::create(['workspace_id' => $ws->id, 'inviter_user_id' => $owner->id, 'email' => 'pending@example.com', 'role' => 'editor', 'token' => 'pending-test', 'expires_at' => now()->addDay()]);
        $this->delete('/user/team/roles/delete', ['role' => 'editor', 'reassign_to' => 'viewer'])->assertRedirect();
        $this->assertSame('viewer', $seat->fresh()->role);
        $this->assertSame('viewer', $invite->fresh()->role);
        WorkspaceRoleMatrix::forget($ws);
        $this->assertArrayNotHasKey('editor', WorkspaceRoleMatrix::forWorkspace($ws));
        $this->post('/user/team/roles/reset')->assertRedirect();
        $this->assertArrayNotHasKey('editor', WorkspaceRoleMatrix::forWorkspace($ws));
        $this->delete('/user/team/roles/delete', ['role' => 'admin', 'reassign_to' => 'viewer'])->assertUnprocessable();
        $this->assertDatabaseHas('workspace_members', ['id' => $seat->id, 'role' => 'viewer']);
    }

    public function test_saved_selection_and_permissions_survive_reload_and_member_actions_obey_them(): void
    {
        $owner = User::factory()->create();
        $ws = $owner->ownedWorkspaces()->first();
        $member = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $matrix = WorkspaceRoleMatrix::forWorkspace($ws);
        $matrix['editor']['links.edit'] = false;
        $response = $this->put('/user/team/roles', ['matrix_json' => json_encode($matrix), 'selected_role' => 'editor']);
        $response->assertRedirect(route('user.team.roles.index', ['role' => 'editor']));
        $page = $this->get('/user/team/roles?role=editor')->assertOk()->assertViewHas('selectedRole', 'editor');
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/workspace-role-editor.html', $page->getContent());
        }
        $this->assertFalse($member->canInWorkspace($ws, 'links.edit'));
        $this->put('/user/team/roles', [])->assertSessionHasErrors();
        $this->actingAs($member)->post('/user/team/roles/create', ['name' => 'Forbidden'])->assertForbidden();
    }
}
