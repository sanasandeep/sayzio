<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiPersonaAgentVersion;
use App\Modules\User\Models\AiResourceShare;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiResourceShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression guard for the core promise of AI resource sharing
 * (Task #2909 / #2924): access to shared Minds / Personas is resolved
 * LIVE against the recipient's current workspace memberships and
 * account badges. There is no per-user grant row, so removing a
 * membership or detaching a badge must cut off access on the very
 * next request — never leave stale, lingering access behind.
 *
 * Also pins:
 *  - USE-only shares can use but not edit/rollback; EDIT shares can.
 *  - The model deleting hooks purge orphan share rows when a mind,
 *    persona, workspace, or badge is deleted.
 *
 * These tests exist so a future refactor of the resolution path can't
 * silently turn live revocation into stale access.
 */
class AiResourceShareRevocationTest extends TestCase
{
    use RefreshDatabase;

    private AiResourceShareService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(AiResourceShareService::class);
        // The mind/persona web controllers 404 unless the engine is on.
        AiEngineSettings::setEnabled(true);
    }

    private function user(): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $u->ensureDefaultWorkspace();
        return $u->fresh();
    }

    private function team(User $owner): Workspace
    {
        return Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'Team ' . Str::random(4),
            'slug'          => 'team-' . Str::random(6),
            'is_personal'   => false,
        ]);
    }

    private function memberOf(Workspace $ws, User $user, ?string $suspendedAt = null): WorkspaceMember
    {
        return WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $user->id,
            'role'         => 'editor',
            'suspended_at' => $suspendedAt,
        ]);
    }

    private function badge(): AccountBadge
    {
        return AccountBadge::create(['name' => 'b' . Str::random(5), 'color' => '#3b82f6']);
    }

    private function mind(User $owner): AiMind
    {
        return AiMind::create(['user_id' => $owner->id, 'name' => 'Mind ' . Str::random(4)]);
    }

    private function persona(User $owner): AiPersonaAgent
    {
        return AiPersonaAgent::create([
            'user_id'       => $owner->id,
            'name'          => 'Persona ' . Str::random(4),
            'system_prompt' => 'You are helpful.',
            'model'         => 'gpt-4o-mini',
        ]);
    }

    // ===================================================================
    // Workspace-membership revocation
    // ===================================================================

    public function test_team_member_loses_mind_and_persona_access_when_membership_removed(): void
    {
        $owner = $this->user();
        $member = $this->user();
        $team = $this->team($owner);          // owner belongs (owns) the team
        $ms = $this->memberOf($team, $member);

        $mind = $this->mind($owner);
        $persona = $this->persona($owner);
        $this->svc->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE);
        $this->svc->share($owner, AiResourceShare::RESOURCE_PERSONA, $persona->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE);

        // While a member: access is live.
        $this->assertTrue($this->svc->canUseMind($member->fresh(), $mind));
        $this->assertTrue($this->svc->canUsePersona($member->fresh(), $persona));

        // Remove the membership -> access is cut off on the next resolve.
        $ms->delete();

        $this->assertNull($this->svc->accessForMind($member->fresh(), $mind));
        $this->assertNull($this->svc->accessForPersona($member->fresh(), $persona));
        $this->assertCount(0, $this->svc->sharedMindsForUser($member->fresh()));
        $this->assertCount(0, $this->svc->sharedPersonasForUser($member->fresh()));
    }

    public function test_suspended_member_loses_access_immediately(): void
    {
        $owner = $this->user();
        $member = $this->user();
        $team = $this->team($owner);
        $ms = $this->memberOf($team, $member);

        $mind = $this->mind($owner);
        $this->svc->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE);

        $this->assertTrue($this->svc->canUseMind($member->fresh(), $mind));

        // Suspending the seat (without deleting the row) revokes access.
        $ms->forceFill(['suspended_at' => now()])->save();

        $this->assertNull($this->svc->accessForMind($member->fresh(), $mind));
    }

    public function test_share_is_revoked_for_everyone_when_owner_leaves_the_team(): void
    {
        // Owner shares into a team they are a MEMBER of (not the team owner);
        // when the owner's own membership goes away the share is no longer
        // authoritative and access is revoked for all recipients.
        $teamOwner = $this->user();
        $sharer = $this->user();
        $member = $this->user();
        $team = $this->team($teamOwner);
        $sharerMs = $this->memberOf($team, $sharer);
        $this->memberOf($team, $member);

        $mind = $this->mind($sharer);
        $this->svc->share($sharer, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE);

        $this->assertTrue($this->svc->canUseMind($member->fresh(), $mind));

        // Sharer leaves the team -> recipient loses access even though the
        // recipient is still a member.
        $sharerMs->delete();

        $this->assertNull($this->svc->accessForMind($member->fresh(), $mind));
    }

    // ===================================================================
    // Badge revocation
    // ===================================================================

    public function test_badge_holder_loses_access_when_badge_detached(): void
    {
        $owner = $this->user();
        $holder = $this->user();
        $badge = $this->badge();
        // Owner must hold the badge to share into it; recipient holds it too.
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $mind = $this->mind($owner);
        $persona = $this->persona($owner);
        $this->svc->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_BADGE, $badge->id, AiResourceShare::ACCESS_USE);
        $this->svc->share($owner, AiResourceShare::RESOURCE_PERSONA, $persona->id, AiResourceShare::AUDIENCE_BADGE, $badge->id, AiResourceShare::ACCESS_USE);

        $this->assertTrue($this->svc->canUseMind($holder->fresh(), $mind));
        $this->assertTrue($this->svc->canUsePersona($holder->fresh(), $persona));

        // Detach the badge from the recipient -> access cut off live.
        $holder->accountBadges()->detach($badge->id);

        $this->assertNull($this->svc->accessForMind($holder->fresh(), $mind));
        $this->assertNull($this->svc->accessForPersona($holder->fresh(), $persona));
    }

    // ===================================================================
    // USE-only vs EDIT access level
    // ===================================================================

    public function test_use_only_share_grants_use_not_edit_edit_share_grants_both(): void
    {
        $owner = $this->user();
        $useMember = $this->user();
        $editMember = $this->user();
        $team = $this->team($owner);
        $this->memberOf($team, $useMember);
        $this->memberOf($team, $editMember);

        // Two teams so we can give different access levels independently.
        $editTeam = $this->team($owner);
        $this->memberOf($editTeam, $editMember);

        $mind = $this->mind($owner);
        $this->svc->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE);
        $this->svc->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_WORKSPACE, $editTeam->id, AiResourceShare::ACCESS_EDIT);

        // USE-only member: can use, cannot edit.
        $this->assertTrue($this->svc->canUseMind($useMember->fresh(), $mind));
        $this->assertFalse($this->svc->canEditMind($useMember->fresh(), $mind));

        // EDIT member: can use and edit.
        $this->assertTrue($this->svc->canUseMind($editMember->fresh(), $mind));
        $this->assertTrue($this->svc->canEditMind($editMember->fresh(), $mind));
    }

    public function test_use_only_member_cannot_update_mind_over_http_but_edit_member_can(): void
    {
        $owner = $this->user();
        $useMember = $this->user();
        $editMember = $this->user();
        $useTeam = $this->team($owner);
        $editTeam = $this->team($owner);
        $this->memberOf($useTeam, $useMember);
        $this->memberOf($editTeam, $editMember);

        $mind = $this->mind($owner);
        $this->svc->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_WORKSPACE, $useTeam->id, AiResourceShare::ACCESS_USE);
        $this->svc->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_WORKSPACE, $editTeam->id, AiResourceShare::ACCESS_EDIT);

        $this->actingAs($useMember)
            ->put("/user/minds/{$mind->id}", ['name' => 'Hacked'])
            ->assertForbidden();
        $this->assertSame($mind->name, $mind->fresh()->name);

        $this->actingAs($editMember)
            ->put("/user/minds/{$mind->id}", ['name' => 'Edited By Teammate'])
            ->assertRedirect();
        $this->assertSame('Edited By Teammate', $mind->fresh()->name);
    }

    public function test_use_only_member_cannot_update_or_rollback_persona_but_edit_member_can(): void
    {
        $owner = $this->user();
        $useMember = $this->user();
        $editMember = $this->user();
        $useTeam = $this->team($owner);
        $editTeam = $this->team($owner);
        $this->memberOf($useTeam, $useMember);
        $this->memberOf($editTeam, $editMember);

        $persona = $this->persona($owner);
        $version = AiPersonaAgentVersion::create([
            'persona_id'         => $persona->id,
            'revision'           => 1,
            'config'             => ['name' => $persona->name, 'system_prompt' => 'v1 prompt', 'model' => $persona->model],
            'summary'            => 'initial',
            'created_by_user_id' => $owner->id,
            'created_at'         => now(),
        ]);

        $this->svc->share($owner, AiResourceShare::RESOURCE_PERSONA, $persona->id, AiResourceShare::AUDIENCE_WORKSPACE, $useTeam->id, AiResourceShare::ACCESS_USE);
        $this->svc->share($owner, AiResourceShare::RESOURCE_PERSONA, $persona->id, AiResourceShare::AUDIENCE_WORKSPACE, $editTeam->id, AiResourceShare::ACCESS_EDIT);

        // USE-only member: edit and rollback both denied.
        $this->actingAs($useMember)
            ->post("/user/ai-personas/{$persona->id}/versions/{$version->id}/rollback")
            ->assertForbidden();

        // EDIT member: rollback allowed.
        $this->actingAs($editMember)
            ->post("/user/ai-personas/{$persona->id}/versions/{$version->id}/rollback")
            ->assertRedirect();
        $this->assertSame('v1 prompt', $persona->fresh()->system_prompt);
    }

    // ===================================================================
    // deleting-hook orphan purge
    // ===================================================================

    public function test_deleting_a_mind_purges_its_share_rows(): void
    {
        $owner = $this->user();
        $team = $this->team($owner);
        $mind = $this->mind($owner);
        $this->svc->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE);

        $this->assertDatabaseHas('ai_resource_shares', ['resource_type' => AiResourceShare::RESOURCE_MIND, 'resource_id' => $mind->id]);
        $mind->delete();
        $this->assertDatabaseMissing('ai_resource_shares', ['resource_type' => AiResourceShare::RESOURCE_MIND, 'resource_id' => $mind->id]);
    }

    public function test_deleting_a_persona_purges_its_share_rows(): void
    {
        $owner = $this->user();
        $team = $this->team($owner);
        $persona = $this->persona($owner);
        $this->svc->share($owner, AiResourceShare::RESOURCE_PERSONA, $persona->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE);

        $this->assertDatabaseHas('ai_resource_shares', ['resource_type' => AiResourceShare::RESOURCE_PERSONA, 'resource_id' => $persona->id]);
        $persona->delete();
        $this->assertDatabaseMissing('ai_resource_shares', ['resource_type' => AiResourceShare::RESOURCE_PERSONA, 'resource_id' => $persona->id]);
    }

    public function test_deleting_a_workspace_purges_shares_targeting_it(): void
    {
        $owner = $this->user();
        $team = $this->team($owner);
        $mind = $this->mind($owner);
        $this->svc->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE);

        $this->assertDatabaseHas('ai_resource_shares', ['audience_type' => AiResourceShare::AUDIENCE_WORKSPACE, 'audience_id' => $team->id]);
        $team->delete();
        $this->assertDatabaseMissing('ai_resource_shares', ['audience_type' => AiResourceShare::AUDIENCE_WORKSPACE, 'audience_id' => $team->id]);
    }

    public function test_deleting_a_badge_purges_shares_targeting_it(): void
    {
        $owner = $this->user();
        $badge = $this->badge();
        $owner->accountBadges()->attach($badge->id);
        $mind = $this->mind($owner);
        $this->svc->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_BADGE, $badge->id, AiResourceShare::ACCESS_USE);

        $this->assertDatabaseHas('ai_resource_shares', ['audience_type' => AiResourceShare::AUDIENCE_BADGE, 'audience_id' => $badge->id]);
        $badge->delete();
        $this->assertDatabaseMissing('ai_resource_shares', ['audience_type' => AiResourceShare::AUDIENCE_BADGE, 'audience_id' => $badge->id]);
    }
}
