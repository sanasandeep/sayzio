<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspacePermissions;
use App\Modules\User\Services\WorkspaceRoleMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GranularWorkspacePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function setupTeam(): array
    {
        $owner = User::factory()->create();
        $ws = $owner->ownedWorkspaces()->first();
        $member = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);

        return [$owner, $ws, $member];
    }

    private function link($owner, $ws, $creator): Link
    {
        $link = new Link(['user_id' => $owner->id, 'type' => 'url', 'alias' => 'permission-'.Str::random(8), 'title' => 'Permission fixture', 'long_url' => 'https://example.com']);
        $link->workspace_id = $ws->id;
        $link->created_by_user_id = $creator->id;
        $link->saveQuietly();

        return $link;
    }

    public function test_feature_permissions_are_independent_and_saved_json_is_enforced(): void
    {
        [$owner,$ws,$member] = $this->setupTeam();
        $matrix = WorkspaceRoleMatrix::defaults();
        $matrix['editor']['links.edit'] = false;
        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $page = $this->get('/user/team/roles')->assertOk();
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/workspace-permissions.html', $page->getContent());
        }
        $this->put('/user/team/roles', ['matrix_json' => json_encode($matrix)])->assertRedirect();
        $this->assertFalse($member->canInWorkspace($ws, 'links.edit'));
        $this->assertTrue($member->canInWorkspace($ws, 'forms.edit'));
        $link = $this->link($owner, $ws, $member);
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->post('/user/links/'.$link->id.'/toggle-active')->assertForbidden();
        $this->get('/user/links')->assertOk();
    }

    public function test_view_others_filters_list_and_direct_record_access(): void
    {
        [$owner,$ws,$member] = $this->setupTeam();
        $own = $this->link($owner, $ws, $member);
        $other = $this->link($owner, $ws, $owner);
        $matrix = WorkspaceRoleMatrix::defaults();
        $matrix['editor']['links.view_others'] = false;
        WorkspaceRoleMatrix::save($ws, $matrix, $owner);
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $response = $this->get('/user/links')->assertOk();
        $response->assertSee($own->alias)->assertDontSee($other->alias);
        $this->get('/user/links/'.$other->id)->assertNotFound();
        $this->get('/user/links/'.$own->id)->assertOk();
    }

    public function test_edit_others_revocation_blocks_mutation_without_hiding_read_access(): void
    {
        [$owner,$ws,$member] = $this->setupTeam();
        $link = $this->link($owner, $ws, $owner);
        $matrix = WorkspaceRoleMatrix::defaults();
        $matrix['editor']['links.edit_others'] = false;
        WorkspaceRoleMatrix::save($ws, $matrix, $owner);
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->get('/user/links/'.$link->id)->assertOk();
        $this->post('/user/links/'.$link->id.'/toggle-active')->assertNotFound();
    }

    public function test_duplicate_role_can_be_assigned_and_is_workspace_specific(): void
    {
        [$owner,$ws,$member] = $this->setupTeam();
        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->post('/user/team/roles/duplicate', ['source' => 'editor', 'name' => 'Campaign assistant'])->assertRedirect();
        $matrix = WorkspaceRoleMatrix::forWorkspace($ws);
        $this->assertArrayHasKey('custom_campaign_assistant', $matrix);
        $matrix['custom_campaign_assistant']['forms.view'] = false;
        WorkspaceRoleMatrix::save($ws, $matrix, $owner);
        $membership = $member->membershipFor($ws);
        $this->put('/user/team/members/'.$membership->id, ['role' => 'custom_campaign_assistant'])->assertRedirect();
        $this->assertSame('custom_campaign_assistant', $member->membershipFor($ws)->role);
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->get('/user/forms')->assertForbidden();
        $this->get('/user/links')->assertOk();
        $this->assertFalse(WorkspacePermissions::roleCan('custom_campaign_assistant', 'links.view', $member->ownedWorkspaces()->first()));
    }

    public function test_suspended_admin_cannot_edit_or_duplicate_roles(): void
    {
        [$owner,$ws,$member] = $this->setupTeam();
        $member->membershipFor($ws)->update(['role' => 'admin', 'suspended_at' => now()]);
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->get('/user/team/roles')->assertForbidden();
        $this->post('/user/team/roles/duplicate', ['source' => 'admin', 'name' => 'Another admin'])->assertForbidden();
    }

    public function test_unknown_permission_keys_are_rejected_and_unknown_roles_deny_access(): void
    {
        [$owner,$ws] = $this->setupTeam();
        $matrix = WorkspaceRoleMatrix::defaults();
        $matrix['editor']['forms.arbitrary'] = true;
        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->put('/user/team/roles', ['matrix_json' => json_encode($matrix)])->assertSessionHas('error');
        $this->assertFalse(WorkspacePermissions::roleCan('missing_role', 'links.view', $ws));
    }

    public function test_mobile_api_cannot_bypass_feature_permissions(): void
    {
        [$owner,$ws,$member] = $this->setupTeam();
        $matrix = WorkspaceRoleMatrix::defaults();
        $matrix['editor']['links.view'] = false;
        WorkspaceRoleMatrix::save($ws, $matrix, $owner);
        $member->forceFill(['active_workspace_id' => $ws->id])->save();
        $this->withToken($member->createToken('permissions')->plainTextToken);
        $this->getJson('/api/v1/links')->assertForbidden();
    }

    public function test_bulk_delete_only_deletes_permitted_own_records(): void
    {
        [$owner,$ws,$member] = $this->setupTeam();
        $own = $this->link($owner, $ws, $member);
        $other = $this->link($owner, $ws, $owner);
        $matrix = WorkspaceRoleMatrix::defaults();
        $matrix['editor']['links.delete'] = true;
        $matrix['editor']['links.delete_others'] = false;
        WorkspaceRoleMatrix::save($ws, $matrix, $owner);
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->post('/user/links/delete-bulk', ['link_ids' => [$own->id, $other->id]])->assertRedirect();
        $this->assertDatabaseMissing('links', ['id' => $own->id]);
        $this->assertDatabaseHas('links', ['id' => $other->id]);
    }

    public function test_exports_can_be_revoked_without_revoking_view(): void
    {
        [$owner,$ws,$member] = $this->setupTeam();
        $matrix = WorkspaceRoleMatrix::defaults();
        $matrix['editor']['links.export'] = false;
        WorkspaceRoleMatrix::save($ws, $matrix, $owner);
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->get('/user/links/export')->assertForbidden();
        $this->get('/user/links')->assertOk();
    }
}
