<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceInvite;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspacePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs)->fresh();
    }

    /** Stamp a member with a role; the permissions blob is now informational only. */
    private function memberOf(Workspace $ws, User $user, string $role): WorkspaceMember
    {
        return WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $user->id,
            'role'         => $role,
            'permissions'  => WorkspacePermissions::roleActions()[$role] ?? [],
        ]);
    }

    public function test_each_user_gets_a_default_workspace(): void
    {
        $user = $this->makeUser();
        $this->assertDatabaseHas('workspaces', ['owner_user_id' => $user->id]);
        $this->assertNotNull($user->ownedWorkspaces()->first());
    }

    public function test_plan_max_workspaces_enforced(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $resp = $this->post('/user/workspaces', ['name' => 'Second WS']);
        $resp->assertRedirect();
        $this->assertEquals(1, $user->ownedWorkspaces()->count());
    }

    public function test_owner_can_invite_and_member_joins_workspace(): void
    {
        $owner = $this->makeUser(['email' => 'owner@example.com']);
        $ws = $owner->ownedWorkspaces()->first();

        $plan = Plan::firstOrCreate(
            ['slug' => 'test-team'],
            ['name' => 'Test Team', 'price' => 0, 'currency' => 'USD', 'is_active' => true,
             'features' => ['max_workspaces' => 5, 'max_seats_per_workspace' => 10]]
        );
        if (!isset(($plan->features ?? [])['max_seats_per_workspace'])) {
            $plan->features = array_merge((array) $plan->features, [
                'max_workspaces' => 5, 'max_seats_per_workspace' => 10,
            ]);
            $plan->save();
        }
        $owner->plan_id = $plan->id; $owner->save();
        $owner->refresh()->load('plan');

        $this->actingAs($owner);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $resp = $this->post('/user/team/invite', [
            'email' => 'invitee@example.com',
            'role'  => 'editor',
        ]);
        $resp->assertRedirect();
        $this->assertDatabaseHas('workspace_invites', [
            'workspace_id' => $ws->id,
            'email'        => 'invitee@example.com',
            'role'         => 'editor',
        ]);

        $invite = WorkspaceInvite::where('email', 'invitee@example.com')->first();
        $member = $this->makeUser(['email' => 'invitee@example.com']);

        $this->actingAs($member);
        $this->post('/user/workspaces/invites/' . $invite->token . '/accept')
             ->assertRedirect(route('user.dashboard'));

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'editor',
        ]);
    }

    public function test_invite_rejects_legacy_custom_role(): void
    {
        // The old per-feature checkbox 'custom' role is gone — validation
        // must reject it now that the role drives all gating.
        $owner = $this->makeUser();
        $ws = $owner->ownedWorkspaces()->first();
        $plan = Plan::firstOrCreate(['slug' => 'test-team'],
            ['name' => 'Test Team', 'price' => 0, 'currency' => 'USD', 'is_active' => true,
             'features' => ['max_workspaces' => 5, 'max_seats_per_workspace' => 10]]);
        $owner->plan_id = $plan->id; $owner->save();

        $this->actingAs($owner);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $resp = $this->post('/user/team/invite', [
            'email' => 'foo@example.com',
            'role'  => 'custom',
        ]);
        $resp->assertSessionHasErrors('role');
    }

    public function test_role_grants_apply_uniformly_across_workspace_resources(): void
    {
        // The whole point of the new model: a member's role gives the
        // SAME action set on every resource in the workspace. An editor
        // can create/edit links, posts, forms, subscribers, etc., but
        // never delete (delete is admin-only).
        foreach (['links', 'posts', 'inbox', 'subscribers', 'forms', 'qr', 'projects'] as $f) {
            $this->assertTrue(WorkspacePermissions::roleCan('editor', $f . '.view'));
            $this->assertTrue(WorkspacePermissions::roleCan('editor', $f . '.create'));
            $this->assertTrue(WorkspacePermissions::roleCan('editor', $f . '.edit'));
            $this->assertFalse(WorkspacePermissions::roleCan('editor', $f . '.delete'),
                "editor must not have delete on {$f}");
            $this->assertTrue(WorkspacePermissions::roleCan('admin', $f . '.delete'),
                "admin must have delete on {$f}");
            $this->assertTrue(WorkspacePermissions::roleCan('viewer', $f . '.view'),
                "viewer must have view on {$f}");
            $this->assertFalse(WorkspacePermissions::roleCan('viewer', $f . '.edit'),
                "viewer must not have edit on {$f}");
        }
    }

    public function test_legacy_feature_prefix_is_ignored_in_role_can(): void
    {
        // Both 'edit' and 'links.edit' must resolve identically — the
        // feature prefix is stripped because role permissions are universal.
        $this->assertSame(
            WorkspacePermissions::roleCan('editor', 'edit'),
            WorkspacePermissions::roleCan('editor', 'links.edit'),
        );
        $this->assertSame(
            WorkspacePermissions::roleCan('replier', 'reply'),
            WorkspacePermissions::roleCan('replier', 'inbox.reply'),
        );
        $this->assertSame(
            WorkspacePermissions::roleCan('viewer', 'view'),
            WorkspacePermissions::roleCan('viewer', 'subscribers.view'),
        );
    }

    public function test_unknown_role_falls_back_to_viewer_semantics(): void
    {
        // Old rows with role='custom' (or any unrecognised role) must
        // safely degrade to view-only — never silently grant elevated
        // access.
        $this->assertTrue(WorkspacePermissions::roleCan('custom', 'view'));
        $this->assertFalse(WorkspacePermissions::roleCan('custom', 'edit'));
        $this->assertFalse(WorkspacePermissions::roleCan('custom', 'delete'));
        $this->assertFalse(WorkspacePermissions::roleCan('custom', 'reply'));
    }

    public function test_permission_gate_blocks_role_without_action(): void
    {
        // analyst has only 'view' across the workspace — POST/DELETE
        // routes that require create/edit/delete must 403.
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();
        $this->memberOf($ws, $member, 'analyst');

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        // analyst can VIEW posts (universal view)…
        $this->get('/user/posts')->assertOk();
        // …but cannot mutate them.
        $this->post('/user/inbox/bulk', ['ids' => [], 'action' => 'mark_read'])->assertForbidden();
    }

    public function test_permission_gate_allows_member_with_role_action(): void
    {
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();
        $this->memberOf($ws, $member, 'editor');

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $resp = $this->get('/user/posts');
        $this->assertNotEquals(403, $resp->status());
    }

    public function test_sidebar_shows_all_entries_for_owner(): void
    {
        $owner = $this->makeUser();
        $ws    = $owner->ownedWorkspaces()->first();

        $this->actingAs($owner);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $html = $this->followingRedirects()->get('/user/dashboard')->getContent();
        // Owner sees the full menu — sample a few that are gated for members.
        $this->assertStringContainsString('user/integrations', $html);
        $this->assertStringContainsString('user/calendar', $html);
        $this->assertStringContainsString('user/contacts', $html);
        $this->assertStringContainsString('user/verification', $html);
        $this->assertStringContainsString('user/referrals', $html);
        $this->assertStringContainsString('user/pixels', $html);
        $this->assertStringContainsString('user/followers', $html);
    }

    public function test_owner_bypasses_permission_gate(): void
    {
        $owner = $this->makeUser();
        $ws    = $owner->ownedWorkspaces()->first();

        $this->actingAs($owner);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $resp = $this->get('/user/posts');
        $this->assertNotEquals(403, $resp->status());
    }

    public function test_workspace_switch_only_allows_accessible_workspaces(): void
    {
        $owner   = $this->makeUser();
        $owner2  = $this->makeUser();
        $ws2     = $owner2->ownedWorkspaces()->first();

        $this->actingAs($owner);
        $resp = $this->post('/user/workspaces/' . $ws2->id . '/switch');
        $this->assertContains($resp->status(), [403, 404]);
    }

    public function test_invite_revoke_makes_token_invalid(): void
    {
        $owner = $this->makeUser();
        $ws    = $owner->ownedWorkspaces()->first();

        $invite = WorkspaceInvite::create([
            'workspace_id'    => $ws->id,
            'inviter_user_id' => $owner->id,
            'email'           => 'foo@example.com',
            'role'            => 'viewer',
            'permissions'     => WorkspacePermissions::roleActions()['viewer'] ?? [],
            'token'           => WorkspaceInvite::newToken(),
            'expires_at'      => now()->addDays(7),
        ]);

        $this->actingAs($owner);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->delete('/user/team/invites/' . $invite->id)->assertRedirect();

        $this->assertNotNull($invite->fresh()->revoked_at);
        $this->assertFalse($invite->fresh()->isPending());
    }

    public function test_resources_are_workspace_scoped_via_global_scope(): void
    {
        $ownerA = $this->makeUser();
        $wsA    = $ownerA->ownedWorkspaces()->first();
        $ownerB = $this->makeUser();
        $wsB    = $ownerB->ownedWorkspaces()->first();

        $a = (new \App\Modules\User\Models\CreatorPost)->forceFill([
            'user_id' => $ownerA->id, 'workspace_id' => $wsA->id, 'body' => 'A-post',
        ]);
        $a->saveQuietly();
        $b = (new \App\Modules\User\Models\CreatorPost)->forceFill([
            'user_id' => $ownerB->id, 'workspace_id' => $wsB->id, 'body' => 'B-post',
        ]);
        $b->saveQuietly();

        app()->instance('current_workspace', $wsA);
        $rows = \App\Modules\User\Models\CreatorPost::all();
        $this->assertCount(1, $rows);
        $this->assertSame('A-post', $rows->first()->body);
        $this->assertCount(2, \App\Modules\User\Models\CreatorPost::withoutGlobalScope('workspace')->get());
        app()->forgetInstance('current_workspace');
    }

    public function test_member_cannot_access_other_workspaces_data_via_route(): void
    {
        $ownerA = $this->makeUser();
        $wsA    = $ownerA->ownedWorkspaces()->first();
        $ownerB = $this->makeUser();

        $member = $this->makeUser();
        $this->memberOf($wsA, $member, 'editor');

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $wsA->id]);
        $this->get('/user/posts')->assertOk();

        $resp = $this->post('/user/workspaces/' . $ownerB->ownedWorkspaces()->first()->id . '/switch');
        $this->assertContains($resp->status(), [403, 404]);
    }

    public function test_invite_accept_redirects_unauthenticated_user_to_signup(): void
    {
        $owner = $this->makeUser();
        $ws    = $owner->ownedWorkspaces()->first();

        $invite = WorkspaceInvite::create([
            'workspace_id'    => $ws->id,
            'inviter_user_id' => $owner->id,
            'email'           => 'newbie@example.com',
            'role'            => 'viewer',
            'permissions'     => WorkspacePermissions::roleActions()['viewer'] ?? [],
            'token'           => WorkspaceInvite::newToken(),
            'expires_at'      => now()->addDays(7),
        ]);

        $resp = $this->post('/user/workspaces/invites/' . $invite->token . '/accept');
        $resp->assertRedirect();
        $this->assertSame($invite->token, session('pending_workspace_invite'));
    }

    public function test_admin_member_can_pin_and_delete_owners_posts(): void
    {
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();
        $this->memberOf($ws, $member, 'admin');

        $post = (new \App\Modules\User\Models\CreatorPost)->forceFill([
            'user_id'             => $owner->id,
            'workspace_id'        => $ws->id,
            'created_by_user_id'  => $owner->id,
            'body'                => 'Owner post body',
            'published_at'        => now()->subMinute(),
        ]);
        $post->saveQuietly();

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $this->get('/user/posts')->assertOk()->assertSee('Owner post body');

        $this->post('/user/posts/' . $post->id . '/pin')->assertRedirect();
        $this->assertNotNull(
            \App\Modules\User\Models\CreatorPost::withoutGlobalScope('workspace')->find($post->id)->pinned_at,
        );

        $this->post('/user/posts/' . $post->id . '/unpin')->assertRedirect();
        $this->assertNull(
            \App\Modules\User\Models\CreatorPost::withoutGlobalScope('workspace')->find($post->id)->pinned_at
        );

        $this->delete('/user/posts/' . $post->id)->assertRedirect();
        $this->assertNull(
            \App\Modules\User\Models\CreatorPost::withoutGlobalScope('workspace')->find($post->id),
        );
    }

    public function test_replier_can_view_and_reply_but_not_mutate_inbox(): void
    {
        // Replier role: view + reply across the whole workspace, no edit/delete.
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();
        $this->memberOf($ws, $member, 'replier');

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $this->get('/user/inbox')->assertOk();
        $this->post('/user/inbox/bulk', ['ids' => [], 'action' => 'mark_read'])->assertForbidden();
        $this->post('/user/inbox/spam-settings', [])->assertForbidden();
    }

    public function test_viewer_cannot_create_or_delete_links(): void
    {
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();
        $this->memberOf($ws, $member, 'viewer');

        $link = (new \App\Modules\User\Models\Link)->forceFill([
            'user_id'            => $owner->id,
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $owner->id,
            'type'               => 'url',
            'alias'              => 'demo-' . Str::random(6),
            'long_url'           => 'https://example.com',
            'title'              => 'Owner link',
        ]);
        $link->saveQuietly();

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $this->get('/user/links')->assertOk();
        $this->delete('/user/links/' . $link->id)->assertForbidden();
        $this->post('/user/links/' . $link->id . '/toggle-active')->assertForbidden();
    }

    public function test_editor_can_modify_owner_link(): void
    {
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();
        $this->memberOf($ws, $member, 'editor');

        $link = (new \App\Modules\User\Models\Link)->forceFill([
            'user_id'            => $owner->id,
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $owner->id,
            'type'               => 'url',
            'alias'              => 'demo-' . Str::random(6),
            'long_url'           => 'https://example.com',
            'title'              => 'Owner link',
            'is_active'          => true,
        ]);
        $link->saveQuietly();

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $this->get('/user/links')->assertOk();
        $this->assertNotNull(\App\Modules\User\Models\Link::find($link->id));
        $resp = $this->post('/user/links/' . $link->id . '/toggle-active');
        $this->assertNotEquals(403, $resp->status());
    }

    public function test_billing_remains_owner_only_under_role_model(): void
    {
        // Even an admin member must NOT reach billing — that's owner-only
        // and is gated by the `workspace.owner` middleware.
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();
        $this->memberOf($ws, $member, 'admin');

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $this->get('/user/billing')->assertForbidden();
        $this->get('/user/upgrade')->assertForbidden();
        $this->get('/user/checkout')->assertForbidden();

        // The owner can reach those.
        $this->actingAs($owner);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->get('/user/billing')->assertOk();
    }

    public function test_admin_member_can_now_invite_and_non_admin_member_cannot(): void
    {
        // Admins are allowed to manage teammates (invite, edit role, remove).
        // Editor (and below) members still get a 403.
        $owner = $this->makeUser();
        $ws    = $owner->ownedWorkspaces()->first();
        $plan  = Plan::firstOrCreate(['slug' => 'test-team'],
            ['name' => 'Test Team', 'price' => 0, 'currency' => 'USD', 'is_active' => true,
             'features' => ['max_workspaces' => 5, 'max_seats_per_workspace' => 10]]);
        $owner->plan_id = $plan->id; $owner->save();

        $admin  = $this->makeUser();
        $editor = $this->makeUser();
        $this->memberOf($ws, $admin, 'admin');
        $this->memberOf($ws, $editor, 'editor');

        $this->actingAs($admin);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $resp = $this->post('/user/team/invite', ['email' => 'newhire@example.com', 'role' => 'viewer']);
        $resp->assertRedirect();
        $this->assertDatabaseHas('workspace_invites', [
            'workspace_id' => $ws->id, 'email' => 'newhire@example.com',
        ]);

        $this->actingAs($editor);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->post('/user/team/invite', ['email' => 'x@y.z', 'role' => 'viewer'])->assertForbidden();
    }

    public function test_admin_member_can_open_roles_screen_and_save_matrix(): void
    {
        $owner = $this->makeUser();
        $ws    = $owner->ownedWorkspaces()->first();
        $admin = $this->makeUser();
        $this->memberOf($ws, $admin, 'admin');

        $this->actingAs($admin);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $this->get('/user/team/roles')->assertOk();

        // Grant editor the delete action — would 403 by default.
        $matrix = \App\Modules\User\Services\WorkspaceRoleMatrix::defaults();
        $matrix['editor']['delete'] = true;
        $this->put('/user/team/roles', ['matrix' => $matrix])->assertRedirect();

        $this->assertTrue(
            \App\Modules\User\Services\WorkspacePermissions::roleCan('editor', 'delete', $ws->fresh()),
        );

        // Audit row recorded.
        $this->assertDatabaseHas('workspace_role_permission_audits', [
            'workspace_id' => $ws->id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_non_admin_member_cannot_open_or_save_roles(): void
    {
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $editor = $this->makeUser();
        $this->memberOf($ws, $editor, 'editor');

        $this->actingAs($editor);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $this->get('/user/team/roles')->assertForbidden();
        $this->put('/user/team/roles', ['matrix' => []])->assertForbidden();
    }

    public function test_locked_admin_view_cell_cannot_be_revoked(): void
    {
        $owner = $this->makeUser();
        $ws    = $owner->ownedWorkspaces()->first();

        $this->actingAs($owner);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $matrix = \App\Modules\User\Services\WorkspaceRoleMatrix::defaults();
        $matrix['admin']['view'] = false; // attempt to lock admin out
        $this->put('/user/team/roles', ['matrix' => $matrix])->assertRedirect();

        $this->assertTrue(
            \App\Modules\User\Services\WorkspacePermissions::roleCan('admin', 'view', $ws->fresh()),
            'Admin row view cell must remain enabled even if posted as false.',
        );
    }

    public function test_unknown_roles_or_actions_are_rejected_on_save(): void
    {
        $owner = $this->makeUser();
        $ws    = $owner->ownedWorkspaces()->first();

        $this->actingAs($owner);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $bad = \App\Modules\User\Services\WorkspaceRoleMatrix::defaults();
        $bad['mystery'] = ['view' => true];
        $resp = $this->put('/user/team/roles', ['matrix' => $bad]);
        $resp->assertSessionHas('error');
    }

    public function test_reset_to_defaults_restores_baseline(): void
    {
        $owner = $this->makeUser();
        $ws    = $owner->ownedWorkspaces()->first();

        $this->actingAs($owner);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $matrix = \App\Modules\User\Services\WorkspaceRoleMatrix::defaults();
        $matrix['viewer']['edit'] = true;
        $this->put('/user/team/roles', ['matrix' => $matrix]);
        $this->assertTrue(\App\Modules\User\Services\WorkspacePermissions::roleCan('viewer', 'edit', $ws->fresh()));

        $this->post('/user/team/roles/reset')->assertRedirect();
        $this->assertFalse(\App\Modules\User\Services\WorkspacePermissions::roleCan('viewer', 'edit', $ws->fresh()));
    }

    public function test_matrix_changes_are_scoped_per_workspace(): void
    {
        $ownerA = $this->makeUser();
        $wsA    = $ownerA->ownedWorkspaces()->first();
        $ownerB = $this->makeUser();
        $wsB    = $ownerB->ownedWorkspaces()->first();

        $matrix = \App\Modules\User\Services\WorkspaceRoleMatrix::defaults();
        $matrix['editor']['delete'] = true;
        \App\Modules\User\Services\WorkspaceRoleMatrix::save($wsA, $matrix, $ownerA);

        $this->assertTrue(\App\Modules\User\Services\WorkspacePermissions::roleCan('editor', 'delete', $wsA));
        $this->assertFalse(\App\Modules\User\Services\WorkspacePermissions::roleCan('editor', 'delete', $wsB));
    }

    public function test_billing_remains_owner_only_for_admin_members_after_role_changes(): void
    {
        // Even after we let admins manage the team, owner-only routes
        // (billing, upgrade, checkout) must stay 403 for admins.
        $owner = $this->makeUser();
        $ws    = $owner->ownedWorkspaces()->first();
        $admin = $this->makeUser();
        $this->memberOf($ws, $admin, 'admin');

        $this->actingAs($admin);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->get('/user/billing')->assertForbidden();
        $this->get('/user/upgrade')->assertForbidden();
        $this->delete('/user/workspaces/' . $ws->id)->assertForbidden();
    }

    public function test_role_actions_table_is_stable(): void
    {
        $actions = WorkspacePermissions::roleActions();

        // Expected role table — single source of truth that the team UI,
        // middleware, and tests all rely on.
        $this->assertSame(
            ['view' => true, 'create' => true, 'edit' => true, 'delete' => true, 'reply' => true],
            $actions['admin'],
        );
        $this->assertSame(
            ['view' => true, 'create' => true, 'edit' => true, 'delete' => false, 'reply' => true],
            $actions['editor'],
        );
        $this->assertSame(
            ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'reply' => true],
            $actions['replier'],
        );
        $this->assertSame(
            ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'reply' => false],
            $actions['analyst'],
        );
        $this->assertSame(
            ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'reply' => false],
            $actions['viewer'],
        );
    }

    public function test_public_subscriber_write_inherits_workspace_id_from_link(): void
    {
        $owner = $this->makeUser();
        $ws    = $owner->ownedWorkspaces()->first();

        $link = (new \App\Modules\User\Models\Link)->forceFill([
            'user_id'            => $owner->id,
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $owner->id,
            'type'               => 'biolink',
            'alias'              => 'pubsub-' . Str::random(6),
            'long_url'           => 'https://example.com',
            'title'              => 'Public link',
            'is_active'          => true,
        ]);
        $link->saveQuietly();

        $sub = new \App\Modules\User\Models\Subscriber;
        $sub->user_id = $owner->id;
        $sub->link_id = $link->id;
        $sub->type = 'newsletter';
        $sub->email = 'visitor@example.com';
        $sub->status = 'active';
        $sub->subscribed_at = now();
        $sub->save();

        $this->assertEquals($ws->id, $sub->fresh()->workspace_id,
            'Public subscriber write should inherit workspace_id from parent link.');
    }
}
