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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspacePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name'     => 'Test ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
        $user->ensureDefaultWorkspace();
        return $user->fresh();
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
        // Default plan (free) typically caps at 1.
        $this->actingAs($user);
        $resp = $this->post('/user/workspaces', ['name' => 'Second WS']);
        // Should fail (limit reached) and redirect back with an error flash.
        $resp->assertRedirect();
        $this->assertEquals(1, $user->ownedWorkspaces()->count());
    }

    public function test_owner_can_invite_and_member_joins_workspace(): void
    {
        $owner = $this->makeUser(['email' => 'owner@example.com']);
        $ws = $owner->ownedWorkspaces()->first();

        // Bump owner's plan so seats are allowed (free defaults to 1 seat).
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

        // New user signs up and accepts.
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

    public function test_permission_gate_blocks_member_without_permission(): void
    {
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();

        // Add as analyst (stats.view only).
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'analyst',
            'permissions'  => WorkspacePermissions::preset('analyst'),
        ]);

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        // posts.view is not in analyst preset → 403
        $this->get('/user/posts')->assertForbidden();
    }

    public function test_permission_gate_allows_member_with_permission(): void
    {
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'editor',
            'permissions'  => WorkspacePermissions::preset('editor'),
        ]);

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        // posts.view IS in the editor preset → not forbidden
        $resp = $this->get('/user/posts');
        $this->assertNotEquals(403, $resp->status());
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
        // Should not be allowed (not a member, not owner).
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
            'permissions'     => WorkspacePermissions::preset('viewer'),
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

        // Create one CreatorPost in each workspace, bypassing the active scope.
        $a = (new \App\Modules\User\Models\CreatorPost)->forceFill([
            'user_id' => $ownerA->id, 'workspace_id' => $wsA->id, 'body' => 'A-post',
        ]);
        $a->saveQuietly();
        $b = (new \App\Modules\User\Models\CreatorPost)->forceFill([
            'user_id' => $ownerB->id, 'workspace_id' => $wsB->id, 'body' => 'B-post',
        ]);
        $b->saveQuietly();

        // Bind workspace A as active and verify the global scope hides B's row.
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

        // ownerA invites a member as editor.
        $member = $this->makeUser();
        WorkspaceMember::create([
            'workspace_id' => $wsA->id, 'user_id' => $member->id,
            'role' => 'editor', 'permissions' => WorkspacePermissions::preset('editor'),
        ]);

        // While member is scoped to wsA, posts.view is allowed (gate passes).
        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $wsA->id]);
        $this->get('/user/posts')->assertOk();

        // Member tries to switch into ownerB's workspace — disallowed.
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
            'permissions'     => WorkspacePermissions::preset('viewer'),
            'token'           => WorkspaceInvite::newToken(),
            'expires_at'      => now()->addDays(7),
        ]);

        // Hit the accept endpoint as a guest — should redirect to register
        // and stash the invite token in session for post-OTP attachment.
        $resp = $this->post('/user/workspaces/invites/' . $invite->token . '/accept');
        $resp->assertRedirect();
        $this->assertSame($invite->token, session('pending_workspace_invite'));
    }

    public function test_member_with_admin_can_pin_and_delete_owners_posts(): void
    {
        // The whole point of workspace scoping: a team member must be able
        // to operate on resources the OWNER created. This regression-tests
        // the previous bug where controllers used `where('user_id', auth()->id())`
        // and `abort_unless($post->user_id === auth()->id())`, which silently
        // hid the owner's posts from members and 403'd any mutation.
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'admin',
            'permissions'  => WorkspacePermissions::preset('admin'),
        ]);

        // Owner-authored post in the workspace.
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

        // Member sees owner's post in the listing.
        $this->get('/user/posts')->assertOk()->assertSee('Owner post body');

        // Member pins the owner's post.
        $this->post('/user/posts/' . $post->id . '/pin')->assertRedirect();
        $this->assertNotNull(
            \App\Modules\User\Models\CreatorPost::withoutGlobalScope('workspace')->find($post->id)->pinned_at,
            'Member with admin should be able to pin owner-authored posts.'
        );

        // Member unpins.
        $this->post('/user/posts/' . $post->id . '/unpin')->assertRedirect();
        $this->assertNull(
            \App\Modules\User\Models\CreatorPost::withoutGlobalScope('workspace')->find($post->id)->pinned_at
        );

        // Member deletes the owner's post.
        $this->delete('/user/posts/' . $post->id)->assertRedirect();
        $this->assertNull(
            \App\Modules\User\Models\CreatorPost::withoutGlobalScope('workspace')->find($post->id),
            'Member with admin should be able to delete owner-authored posts.'
        );
    }

    public function test_inbox_view_only_member_cannot_perform_mutations(): void
    {
        // Replier preset has inbox.view + inbox.reply but NOT inbox.edit/delete.
        // Action-level gating must reject bulk/spam-settings/destroy attempts
        // even though the parent prefix is reachable via inbox.view.
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'replier',
            'permissions'  => WorkspacePermissions::preset('replier'),
        ]);

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        // Read endpoints allowed (inbox.view).
        $this->get('/user/inbox')->assertOk();
        // Bulk mutation requires inbox.edit — must 403.
        $this->post('/user/inbox/bulk', ['ids' => [], 'action' => 'mark_read'])->assertForbidden();
        // Spam settings update requires inbox.edit — must 403.
        $this->post('/user/inbox/spam-settings', [])->assertForbidden();
    }

    public function test_referrals_view_only_member_cannot_change_code(): void
    {
        // Editor preset doesn't include referrals.edit; the prefix is gated by
        // referrals.view but the mutation must additionally require edit.
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();

        // Hand-roll permissions: only referrals.view, no edit.
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'custom',
            'permissions'  => ['referrals.view' => true],
        ]);

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $this->get('/user/referrals')->assertOk();
        $this->put('/user/referrals/code', ['code' => 'NEWCODE'])->assertForbidden();
    }

    public function test_links_view_only_member_cannot_create_or_delete(): void
    {
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();

        // links.view only, no create/edit/delete.
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'custom',
            'permissions'  => ['links.view' => true],
        ]);

        // Owner-authored link in this workspace.
        $link = (new \App\Modules\User\Models\Link)->forceFill([
            'user_id'            => $owner->id,
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $owner->id,
            'type'               => 'url',
            'alias'              => 'demo-' . \Illuminate\Support\Str::random(6),
            'long_url'           => 'https://example.com',
            'title'              => 'Owner link',
        ]);
        $link->saveQuietly();

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        // Listing/show allowed.
        $this->get('/user/links')->assertOk();
        // Mutation must be forbidden.
        $this->delete('/user/links/' . $link->id)->assertForbidden();
        $this->post('/user/links/' . $link->id . '/toggle-active')->assertForbidden();
    }

    public function test_member_with_links_edit_can_modify_owner_link(): void
    {
        // Proves the workspace_owner_id() refactor: a member with links.edit
        // can list and toggle an owner-authored link without 403.
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'editor',
            'permissions'  => WorkspacePermissions::preset('editor'),
        ]);

        $link = (new \App\Modules\User\Models\Link)->forceFill([
            'user_id'            => $owner->id,
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $owner->id,
            'type'               => 'url',
            'alias'              => 'demo-' . \Illuminate\Support\Str::random(6),
            'long_url'           => 'https://example.com',
            'title'              => 'Owner link',
            'is_active'          => true,
        ]);
        $link->saveQuietly();

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        // Member can reach the listing without a 403.
        $this->get('/user/links')->assertOk();
        // The owner's link is visible to the member through the workspace
        // global scope (i.e. workspace_owner_id() / global scope work end-to-end).
        $this->assertNotNull(\App\Modules\User\Models\Link::find($link->id));
        // Member can toggle it without a 403 from the legacy ownership check.
        $resp = $this->post('/user/links/' . $link->id . '/toggle-active');
        $this->assertNotEquals(403, $resp->status());
    }

    public function test_settings_gates_block_editor_from_billing_and_contacts(): void
    {
        // Editor preset includes links/posts/inbox/followers/digests but NOT
        // settings — so contacts, billing, integrations and verification must
        // all 403 even though the routes used to be ungated.
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'editor',
            'permissions'  => WorkspacePermissions::preset('editor'),
        ]);

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $this->get('/user/contacts')->assertForbidden();
        $this->get('/user/billing')->assertForbidden();
        $this->get('/user/integrations')->assertForbidden();
        $this->get('/user/calendar')->assertForbidden();
        $this->get('/user/verification')->assertForbidden();
        // Owner with implicit bypass should still reach the same pages.
        $this->actingAs($owner);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->get('/user/contacts')->assertOk();
    }

    public function test_public_subscriber_write_inherits_workspace_id_from_link(): void
    {
        // Visitor-origin write (no current_workspace bound) — Subscriber
        // creation must still receive a workspace_id derived from the parent
        // link, otherwise the global scope would hide it from the owner's
        // inbox after we render the workspace context.
        $owner = $this->makeUser();
        $ws    = $owner->ownedWorkspaces()->first();

        $link = (new \App\Modules\User\Models\Link)->forceFill([
            'user_id'            => $owner->id,
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $owner->id,
            'type'               => 'biolink',
            'alias'              => 'pubsub-' . \Illuminate\Support\Str::random(6),
            'long_url'           => 'https://example.com',
            'title'              => 'Public link',
            'is_active'          => true,
        ]);
        $link->saveQuietly();

        // Simulate a public-origin Subscriber write — like RedirectController
        // does — without binding `current_workspace`.
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

    public function test_digest_preview_uses_digests_feature_not_settings(): void
    {
        // Editor preset has digests.view but NOT settings.view, so the
        // follower-digest preview must still load (proving the route is
        // gated under the dedicated digests feature, not settings).
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'editor',
            'permissions'  => WorkspacePermissions::preset('editor'),
        ]);

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        // Editor (digests.view present) — allowed.
        $resp = $this->get('/user/profile/digest/preview');
        $this->assertNotEquals(403, $resp->status());

        // Editor (digests.view present) — sending a sample to themselves
        // is also allowed, since the action only emails the signed-in
        // user and is therefore a QA/preview action gated under view.
        $sample = $this->post('/user/profile/digest/sample');
        $this->assertNotEquals(403, $sample->status());
    }

    public function test_digest_routes_block_member_without_digests_view(): void
    {
        // Replier preset has neither digests.view nor settings.view —
        // both digest routes must 403.
        $owner  = $this->makeUser();
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser();
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'replier',
            'permissions'  => WorkspacePermissions::preset('replier'),
        ]);

        $this->actingAs($member);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);

        $this->get('/user/profile/digest/preview')->assertForbidden();
        $this->post('/user/profile/digest/sample')->assertForbidden();
    }

    public function test_permissions_presets_are_stable(): void
    {
        $editor = WorkspacePermissions::preset('editor');
        $this->assertTrue($editor['posts.view'] ?? false);
        $this->assertTrue($editor['posts.create'] ?? false);
        $this->assertFalse($editor['posts.delete'] ?? false);

        $analyst = WorkspacePermissions::preset('analyst');
        $this->assertSame(['stats.view' => true], $analyst);

        $viewer = WorkspacePermissions::preset('viewer');
        $this->assertTrue($viewer['posts.view'] ?? false);
        $this->assertFalse($viewer['posts.create'] ?? false);
    }
}
