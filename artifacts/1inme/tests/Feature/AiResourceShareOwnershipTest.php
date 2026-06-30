<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
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
 * Owner-only management guard for AI resource sharing (Task #2931).
 *
 * Task #2924 pinned that revoking a teammate/badge instantly cuts shared
 * access. This sibling pins the *granting/removing* side handled by the
 * web AiResourceShareController:
 *
 *  - A non-owner (any user, including a USE/EDIT shared editor) cannot
 *    create a share for a resource they don't own — the route 403s and
 *    no ai_resource_shares row is written.
 *  - The owner can only share into a team they belong to / a badge they
 *    hold; the AiResourceShareService::share() guards surface as a
 *    user-facing flash error (redirect back), never a 500, and write no
 *    row.
 *  - Only the owner can delete a share via the unshare endpoints; another
 *    user cannot remove someone else's share row.
 */
class AiResourceShareOwnershipTest extends TestCase
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
    // A non-owner cannot create a share
    // ===================================================================

    public function test_non_owner_cannot_create_a_mind_share(): void
    {
        $owner = $this->user();
        $stranger = $this->user();
        $mind = $this->mind($owner);

        // The stranger owns their own team, so the audience itself is valid —
        // the only thing stopping the share is that they don't own the mind.
        $strangerTeam = $this->team($stranger);

        $this->actingAs($stranger)
            ->from("/user/minds/{$mind->id}")
            ->post("/user/minds/{$mind->id}/shares", [
                'audience' => 'workspace:' . $strangerTeam->id,
                'access'   => 'use',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $mind->id,
        ]);
    }

    public function test_edit_shared_editor_cannot_reshare_a_mind_they_dont_own(): void
    {
        // An EDIT-access teammate can edit the mind, but must NOT be able to
        // re-share it into a team/badge of their own — sharing is owner-only.
        $owner = $this->user();
        $editor = $this->user();
        $ownerTeam = $this->team($owner);
        $this->memberOf($ownerTeam, $editor);

        $mind = $this->mind($owner);
        $this->svc->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_WORKSPACE, $ownerTeam->id, AiResourceShare::ACCESS_EDIT);

        // Sanity: the editor really does have EDIT access via the share.
        $this->assertTrue($this->svc->canEditMind($editor->fresh(), $mind));

        // A team the editor owns — a perfectly valid audience for THEM, so the
        // 403 can only come from the ownership guard, not the audience guard.
        $editorTeam = $this->team($editor);

        $this->actingAs($editor)
            ->from("/user/minds/{$mind->id}")
            ->post("/user/minds/{$mind->id}/shares", [
                'audience' => 'workspace:' . $editorTeam->id,
                'access'   => 'use',
            ])
            ->assertForbidden();

        // No new audience row leaked in: only the owner's original EDIT share exists.
        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $mind->id,
            'audience_id'   => $editorTeam->id,
        ]);
        $this->assertSame(1, AiResourceShare::where('resource_type', AiResourceShare::RESOURCE_MIND)
            ->where('resource_id', $mind->id)->count());
    }

    public function test_non_owner_cannot_create_a_persona_share(): void
    {
        $owner = $this->user();
        $stranger = $this->user();
        $persona = $this->persona($owner);
        $strangerTeam = $this->team($stranger);

        $this->actingAs($stranger)
            ->from("/user/ai-personas/{$persona->id}")
            ->post("/user/ai-personas/{$persona->id}/shares", [
                'audience' => 'workspace:' . $strangerTeam->id,
                'access'   => 'use',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_PERSONA,
            'resource_id'   => $persona->id,
        ]);
    }

    // ===================================================================
    // Owner can only share into audiences they belong to
    // (service guards surface as a flash error, never a 500)
    // ===================================================================

    public function test_owner_sharing_into_a_team_they_dont_belong_to_errors_without_500(): void
    {
        $owner = $this->user();
        $stranger = $this->user();
        $mind = $this->mind($owner);
        // A team owned by someone else that the owner is NOT a member of.
        $foreignTeam = $this->team($stranger);

        $this->actingAs($owner)
            ->from("/user/minds/{$mind->id}")
            ->post("/user/minds/{$mind->id}/shares", [
                'audience' => 'workspace:' . $foreignTeam->id,
                'access'   => 'use',
            ])
            ->assertRedirect("/user/minds/{$mind->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $mind->id,
            'audience_id'   => $foreignTeam->id,
        ]);
    }

    public function test_owner_sharing_into_a_badge_they_dont_hold_errors_without_500(): void
    {
        $owner = $this->user();
        $persona = $this->persona($owner);
        // A badge the owner does NOT hold.
        $badge = $this->badge();

        $this->actingAs($owner)
            ->from("/user/ai-personas/{$persona->id}")
            ->post("/user/ai-personas/{$persona->id}/shares", [
                'audience' => 'badge:' . $badge->id,
                'access'   => 'use',
            ])
            ->assertRedirect("/user/ai-personas/{$persona->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_PERSONA,
            'resource_id'   => $persona->id,
            'audience_id'   => $badge->id,
        ]);
    }

    // ===================================================================
    // Only the owner can delete (unshare) a share row
    // ===================================================================

    public function test_only_the_owner_can_delete_a_mind_share(): void
    {
        $owner = $this->user();
        $member = $this->user();
        $team = $this->team($owner);
        $this->memberOf($team, $member);

        $mind = $this->mind($owner);
        $share = $this->svc->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE);

        // A recipient member cannot remove the owner's share.
        $this->actingAs($member)
            ->from("/user/minds/{$mind->id}")
            ->delete("/user/minds/{$mind->id}/shares/{$share->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('ai_resource_shares', ['id' => $share->id]);

        // The owner can.
        $this->actingAs($owner)
            ->from("/user/minds/{$mind->id}")
            ->delete("/user/minds/{$mind->id}/shares/{$share->id}")
            ->assertRedirect();
        $this->assertDatabaseMissing('ai_resource_shares', ['id' => $share->id]);
    }

    public function test_only_the_owner_can_delete_a_persona_share(): void
    {
        $owner = $this->user();
        $member = $this->user();
        $team = $this->team($owner);
        $this->memberOf($team, $member);

        $persona = $this->persona($owner);
        $share = $this->svc->share($owner, AiResourceShare::RESOURCE_PERSONA, $persona->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE);

        $this->actingAs($member)
            ->from("/user/ai-personas/{$persona->id}")
            ->delete("/user/ai-personas/{$persona->id}/shares/{$share->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('ai_resource_shares', ['id' => $share->id]);

        $this->actingAs($owner)
            ->from("/user/ai-personas/{$persona->id}")
            ->delete("/user/ai-personas/{$persona->id}/shares/{$share->id}")
            ->assertRedirect();
        $this->assertDatabaseMissing('ai_resource_shares', ['id' => $share->id]);
    }
}
