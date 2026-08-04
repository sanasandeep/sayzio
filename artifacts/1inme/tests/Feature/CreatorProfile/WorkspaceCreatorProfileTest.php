<?php

namespace Tests\Feature\CreatorProfile;

use App\Modules\User\Models\CreatorProfile;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6618 — creator profiles are workspace-scoped.
 *
 *  - Each workspace owns its own profile + handle; /@handle resolves to
 *    the workspace profile (public page shows that workspace's content).
 *  - The editor and handle surfaces target the ACTIVE workspace.
 *  - Handle uniqueness + banned-name checks are enforced across profiles.
 *  - Personal-workspace saves mirror onto the legacy users.* columns.
 *  - API v1 read/write endpoints follow the active workspace.
 */
class WorkspaceCreatorProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    /** Owner user + personal workspace + a second (team) workspace. */
    private function makeUserWithTwoWorkspaces(): array
    {
        $user = $this->makeUser();
        $personal = $user->ensureDefaultWorkspace();
        $team = Workspace::create([
            'owner_user_id' => $user->id,
            'name'          => 'Brand Team',
            'slug'          => 'brand-' . Str::lower(Str::random(6)),
            'is_personal'   => false,
        ]);
        return [$user, $personal, $team];
    }

    private function actAsInWorkspace(User $user, Workspace $ws): void
    {
        $user->forceFill(['active_workspace_id' => $ws->id])->save();
        $this->be($user);
        // The WorkspaceContext singleton + session pointer persist across
        // requests inside one test — reset them so the switch is honoured
        // (mirrors what the real workspace-switch endpoint does).
        $this->app->forgetInstance(WorkspaceContext::class);
        session()->forget('active_workspace_id');
        app(WorkspaceContext::class)->set($ws);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
    }

    /** Stateless-API equivalent: drop cached workspace context entirely. */
    private function resetWorkspaceContext(): void
    {
        $this->app->forgetInstance(WorkspaceContext::class);
        session()->flush();
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
    }

    // ── public resolution ────────────────────────────────────────────

    public function test_each_workspace_handle_resolves_to_its_own_profile(): void
    {
        [$user, $personal, $team] = $this->makeUserWithTwoWorkspaces();
        $h1 = 'personal_' . Str::lower(Str::random(6));
        $h2 = 'team_' . Str::lower(Str::random(6));

        CreatorProfile::create([
            'workspace_id' => $personal->id, 'user_id' => $user->id,
            'handle' => $h1, 'tagline' => 'Personal tagline',
            'profile_published' => true,
        ]);
        CreatorProfile::create([
            'workspace_id' => $team->id, 'user_id' => $user->id,
            'handle' => $h2, 'tagline' => 'Team tagline',
            'profile_published' => true,
        ]);

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $this->get('/@' . $h1)->assertOk()->assertSee('Personal tagline');
        $this->get('/@' . $h2)->assertOk()->assertSee('Team tagline');
    }

    public function test_unpublished_workspace_profile_is_not_public(): void
    {
        [$user, , $team] = $this->makeUserWithTwoWorkspaces();
        $h = 'hidden_' . Str::lower(Str::random(6));
        CreatorProfile::create([
            'workspace_id' => $team->id, 'user_id' => $user->id,
            'handle' => $h, 'profile_published' => false,
        ]);

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $this->get('/@' . $h)->assertNotFound();
    }

    // ── editor scoping ───────────────────────────────────────────────

    public function test_claim_handle_targets_active_workspace_and_mirrors_personal(): void
    {
        [$user, $personal, $team] = $this->makeUserWithTwoWorkspaces();

        // Claim under the personal workspace → mirrored to users.handle.
        $this->actAsInWorkspace($user, $personal);
        $h1 = 'mine_' . Str::lower(Str::random(6));
        $this->post(route('user.creator-profile.handle.claim'), ['handle' => $h1])
            ->assertRedirect();
        $this->assertSame($h1, CreatorProfile::forWorkspace($personal)->handle);
        $this->assertSame($h1, $user->fresh()->handle);

        // Claim under the team workspace → personal handle untouched.
        $this->actAsInWorkspace($user, $team);
        $h2 = 'team_' . Str::lower(Str::random(6));
        $this->post(route('user.creator-profile.handle.claim'), ['handle' => $h2])
            ->assertRedirect();
        $this->assertSame($h2, CreatorProfile::forWorkspace($team)->handle);
        $this->assertSame($h1, $user->fresh()->handle);
        $this->assertSame($h1, CreatorProfile::forWorkspace($personal)->handle);
    }

    public function test_handle_uniqueness_is_enforced_across_workspace_profiles(): void
    {
        [$userA, $personalA] = $this->makeUserWithTwoWorkspaces();
        $taken = 'taken_' . Str::lower(Str::random(6));
        CreatorProfile::forWorkspace($personalA)->forceFill(['handle' => $taken])->save();

        $userB = $this->makeUser();
        $this->actAsInWorkspace($userB, $userB->ensureDefaultWorkspace());
        $this->post(route('user.creator-profile.handle.claim'), ['handle' => $taken])
            ->assertSessionHasErrors('handle');
        $this->assertNull(CreatorProfile::forWorkspace($userB->ensureDefaultWorkspace())->handle);
    }

    public function test_editor_shows_the_active_workspace_profile(): void
    {
        [$user, $personal, $team] = $this->makeUserWithTwoWorkspaces();
        CreatorProfile::create([
            'workspace_id' => $personal->id, 'user_id' => $user->id,
            'handle' => 'p_' . Str::lower(Str::random(6)), 'tagline' => 'Personal-only tagline',
        ]);
        CreatorProfile::create([
            'workspace_id' => $team->id, 'user_id' => $user->id,
            'tagline' => 'Team-only tagline',
        ]);

        $this->actAsInWorkspace($user, $personal);
        $this->get(route('user.creator-profile.edit'))
            ->assertOk()->assertSee('Personal-only tagline');

        $this->actAsInWorkspace($user, $team);
        $this->get(route('user.creator-profile.edit'))
            ->assertOk()->assertSee('Team-only tagline');
    }

    // ── API v1 parity ────────────────────────────────────────────────

    public function test_api_show_resolves_workspace_profile(): void
    {
        [$user, , $team] = $this->makeUserWithTwoWorkspaces();
        $h = 'api_' . Str::lower(Str::random(6));
        CreatorProfile::create([
            'workspace_id' => $team->id, 'user_id' => $user->id,
            'handle' => $h, 'tagline' => 'API team tagline', 'profile_published' => true,
        ]);

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $this->getJson('/api/v1/creator-profile/' . $h)
            ->assertOk()
            ->assertJsonPath('data.profile.handle', $h)
            ->assertJsonPath('data.profile.tagline', 'API team tagline');
    }

    public function test_api_owner_update_targets_active_workspace(): void
    {
        [$user, $personal, $team] = $this->makeUserWithTwoWorkspaces();
        $token = $user->createToken('t')->plainTextToken;

        $user->forceFill(['active_workspace_id' => $team->id])->save();
        $this->resetWorkspaceContext();
        $this->withToken($token)
            ->patchJson('/api/v1/me/creator-profile', ['tagline' => 'Team via API'])
            ->assertOk();

        $this->assertSame('Team via API', CreatorProfile::forWorkspace($team)->tagline);
        $this->assertNull(CreatorProfile::forWorkspace($personal)->tagline);
        // Team-workspace saves never leak onto the legacy users columns.
        $this->assertNotSame('Team via API', $user->fresh()->tagline);
    }

    public function test_api_settings_follows_active_workspace_switch(): void
    {
        [$user, $personal, $team] = $this->makeUserWithTwoWorkspaces();
        CreatorProfile::create([
            'workspace_id' => $personal->id, 'user_id' => $user->id, 'tagline' => 'P-tag',
        ]);
        CreatorProfile::create([
            'workspace_id' => $team->id, 'user_id' => $user->id, 'tagline' => 'T-tag',
        ]);
        $token = $user->createToken('t')->plainTextToken;

        $user->forceFill(['active_workspace_id' => $personal->id])->save();
        $this->resetWorkspaceContext();
        $this->withToken($token)->getJson('/api/v1/me/creator-profile')
            ->assertOk()->assertJsonPath('data.profile.tagline', 'P-tag');

        $user->forceFill(['active_workspace_id' => $team->id])->save();
        $this->resetWorkspaceContext();
        $this->withToken($token)->getJson('/api/v1/me/creator-profile')
            ->assertOk()->assertJsonPath('data.profile.tagline', 'T-tag');
    }

    // ── registration / claimed-handle flow ───────────────────────────

    public function test_claimed_handle_lands_on_personal_workspace_profile(): void
    {
        $user = $this->makeUser(['handle' => null]);
        $h = 'claimed_' . Str::lower(Str::random(6));

        $leftover = \App\Modules\Common\Support\ClaimedHandle::apply($user, $h);

        $this->assertNull($leftover);
        $personal = $user->ensureDefaultWorkspace();
        $this->assertSame($h, CreatorProfile::forWorkspace($personal)->handle);
        $this->assertSame($h, $user->fresh()->handle);
    }

    public function test_follow_is_keyed_to_the_workspace_profile(): void
    {
        [$creator, , $team] = $this->makeUserWithTwoWorkspaces();
        $h = 'follow_' . Str::lower(Str::random(6));
        $profile = CreatorProfile::create([
            'workspace_id' => $team->id, 'user_id' => $creator->id,
            'handle' => $h, 'profile_published' => true,
        ]);

        $viewer = $this->makeUser();
        $token = $viewer->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/follows/' . $creator->id, ['handle' => $h])
            ->assertStatus(201);

        $this->assertDatabaseHas('follows', [
            'follower_id'        => $viewer->id,
            'creator_id'         => $creator->id,
            'creator_profile_id' => $profile->id,
        ]);
        $this->assertSame(1, $profile->fresh()->followers_count);
    }

    public function test_web_unfollow_decrements_the_originally_followed_profile(): void
    {
        [$creator, $personal, $team] = $this->makeUserWithTwoWorkspaces();
        $personalProfile = CreatorProfile::forWorkspace($personal);
        $teamProfile = CreatorProfile::create([
            'workspace_id' => $team->id, 'user_id' => $creator->id,
            'handle' => 'tm_' . Str::lower(Str::random(6)), 'profile_published' => true,
        ]);
        $teamProfile->increment('followers_count'); // simulate an earlier follow

        $viewer = $this->makeUser();
        $follow = \App\Modules\User\Models\Follow::create([
            'follower_id'        => $viewer->id,
            'creator_id'         => $creator->id,
            'creator_profile_id' => $teamProfile->id,
            'created_at'         => now(),
        ]);
        $creator->increment('followers_count');

        // Unfollow WITHOUT any handle context (e.g. from a different page):
        // the decrement must land on the profile stored on the follow row,
        // never on the personal fallback.
        \App\Modules\Common\Services\ViewerSession::login($viewer);
        $this->post(route('viewer.follow.toggle', ['creator' => $creator->id]), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('following', false);

        $this->assertSame(0, $teamProfile->fresh()->followers_count);
        $this->assertSame(0, (int) $personalProfile->fresh()->followers_count);
        $this->assertDatabaseMissing('follows', ['id' => $follow->id]);
    }

    public function test_api_profile_payload_scopes_links_to_the_workspace(): void
    {
        [$creator, $personal, $team] = $this->makeUserWithTwoWorkspaces();
        $h = 'scope_' . Str::lower(Str::random(6));
        CreatorProfile::create([
            'workspace_id' => $team->id, 'user_id' => $creator->id,
            'handle' => $h, 'profile_published' => true,
        ]);

        // One public link in each workspace.
        foreach ([$personal, $team] as $ws) {
            $link = \App\Modules\User\Models\Link::create([
                'user_id' => $creator->id,
                'type' => 'url', 'alias' => 'l' . Str::lower(Str::random(8)),
                'url' => 'https://example.com', 'is_active' => true,
                'visibility' => 'public',
            ]);
            // workspace_id is stamped from the bound workspace context, not
            // mass-assignment — pin it explicitly for this test.
            $link->forceFill(['workspace_id' => $ws->id])->saveQuietly();
        }

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        // The team profile's payload must count ONLY the team workspace link.
        $this->getJson('/api/v1/creator-profile/' . $h)
            ->assertOk()
            ->assertJsonPath('data.profile.total_public_links', 1);
    }
}
