<?php

namespace Tests\Feature\Concerns;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiResourceShare;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Services\AI\AiResourceShareService;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared owner/audience guard scenario matrix for AI resource sharing
 * (Task #2935).
 *
 * AI Mind / Persona sharing is enforced by ONE service guard
 * ({@see AiResourceShareService::share()}) behind TWO parallel
 * controllers — the web {@see \App\Modules\User\Controllers\AiResourceShareController}
 * and the API {@see \App\Modules\Api\Controllers\AiResourceShareController}.
 * Before this trait the web and API suites asserted slightly different
 * scenarios, so a guard could be loosened on one surface without a
 * failing test on the other.
 *
 * This trait is the single source of truth for those scenarios. Both
 * suites `use` it, so every rule below is asserted IDENTICALLY on the web
 * and API surfaces. Adding a scenario here automatically covers both
 * controllers; one surface can no longer silently diverge.
 *
 * The transport differs (web: form POST → redirect + flash error /
 * abort(403); API: JSON → 422 / 403), so each suite implements the small
 * set of transport hooks at the bottom. Everything model-level
 * (ownership, audience membership, DB state) lives here and is shared.
 */
trait AiResourceShareScenarioMatrix
{
    // ===================================================================
    // 1. A non-owner cannot create a share (mind + persona)
    // ===================================================================

    public function test_matrix_non_owner_cannot_create_a_mind_share(): void
    {
        $owner    = $this->newUser();
        $stranger = $this->newUser();
        $mind     = $this->newMind($owner);
        // The stranger owns their own team, so the audience itself is valid —
        // the only thing stopping the share is that they don't own the mind.
        $strangerTeam = $this->newTeam($stranger);

        $this->shareForbidden($stranger, 'mind', $mind, 'workspace:' . $strangerTeam->id);

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $mind->id,
        ]);
    }

    public function test_matrix_non_owner_cannot_create_a_persona_share(): void
    {
        $owner    = $this->newUser();
        $stranger = $this->newUser();
        $persona  = $this->newPersona($owner);
        $strangerTeam = $this->newTeam($stranger);

        $this->shareForbidden($stranger, 'persona', $persona, 'workspace:' . $strangerTeam->id);

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_PERSONA,
            'resource_id'   => $persona->id,
        ]);
    }

    // ===================================================================
    // 2. An EDIT-access shared editor cannot re-share what they don't own
    // ===================================================================

    public function test_matrix_edit_shared_editor_cannot_reshare_a_mind_they_dont_own(): void
    {
        // An EDIT-access teammate can edit the mind, but must NOT be able to
        // re-share it into a team/badge of their own — sharing is owner-only.
        $owner     = $this->newUser();
        $editor    = $this->newUser();
        $ownerTeam = $this->newTeam($owner, $editor);
        $mind      = $this->newMind($owner);

        $this->shareService()->share(
            $owner, AiResourceShare::RESOURCE_MIND, (int) $mind->id,
            AiResourceShare::AUDIENCE_WORKSPACE, (int) $ownerTeam->id, AiResourceShare::ACCESS_EDIT
        );

        // Sanity: the editor really does have EDIT access via the share.
        $this->assertTrue($this->shareService()->canEditMind($editor->fresh(), $mind));

        // A team the editor owns — a perfectly valid audience for THEM, so the
        // 403 can only come from the ownership guard, not the audience guard.
        $editorTeam = $this->newTeam($editor);

        $this->shareForbidden($editor, 'mind', $mind, 'workspace:' . $editorTeam->id);

        // No new audience row leaked in: only the owner's original EDIT share exists.
        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $mind->id,
            'audience_id'   => $editorTeam->id,
        ]);
        $this->assertSame(1, AiResourceShare::where('resource_type', AiResourceShare::RESOURCE_MIND)
            ->where('resource_id', $mind->id)->count());
    }

    // ===================================================================
    // 3. The owner can only share into a team they actually belong to
    //    (foreign team → clean rejection, never a 500), mind + persona
    // ===================================================================

    public function test_matrix_owner_cannot_share_a_mind_into_a_foreign_team(): void
    {
        $owner    = $this->newUser();
        $stranger = $this->newUser();
        $mind     = $this->newMind($owner);
        // A team owned by someone else that the owner is NOT a member of.
        $foreignTeam = $this->newTeam($stranger);

        $this->shareAudienceRejected($owner, 'mind', $mind, 'workspace:' . $foreignTeam->id);

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $mind->id,
            'audience_id'   => $foreignTeam->id,
        ]);
    }

    public function test_matrix_owner_cannot_share_a_persona_into_a_foreign_team(): void
    {
        $owner    = $this->newUser();
        $stranger = $this->newUser();
        $persona  = $this->newPersona($owner);
        $foreignTeam = $this->newTeam($stranger);

        $this->shareAudienceRejected($owner, 'persona', $persona, 'workspace:' . $foreignTeam->id);

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_PERSONA,
            'resource_id'   => $persona->id,
            'audience_id'   => $foreignTeam->id,
        ]);
    }

    // ===================================================================
    // 4. The owner can only share into a badge group they hold
    //    (badge-not-held → clean rejection, never a 500), mind + persona
    // ===================================================================

    public function test_matrix_owner_cannot_share_a_mind_into_a_badge_they_dont_hold(): void
    {
        $owner = $this->newUser();
        $mind  = $this->newMind($owner);
        // A badge the owner does NOT hold.
        $badge = $this->newBadge();

        $this->shareAudienceRejected($owner, 'mind', $mind, 'badge:' . $badge->id);

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $mind->id,
            'audience_id'   => $badge->id,
        ]);
    }

    public function test_matrix_owner_cannot_share_a_persona_into_a_badge_they_dont_hold(): void
    {
        $owner   = $this->newUser();
        $persona = $this->newPersona($owner);
        $badge   = $this->newBadge();

        $this->shareAudienceRejected($owner, 'persona', $persona, 'badge:' . $badge->id);

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_PERSONA,
            'resource_id'   => $persona->id,
            'audience_id'   => $badge->id,
        ]);
    }

    // ===================================================================
    // 5. Only the owner can delete (unshare) a share row (mind + persona)
    // ===================================================================

    public function test_matrix_only_the_owner_can_delete_a_mind_share(): void
    {
        $owner  = $this->newUser();
        $member = $this->newUser();
        $team   = $this->newTeam($owner, $member);
        $mind   = $this->newMind($owner);

        $share = $this->shareService()->share(
            $owner, AiResourceShare::RESOURCE_MIND, (int) $mind->id,
            AiResourceShare::AUDIENCE_WORKSPACE, (int) $team->id, AiResourceShare::ACCESS_USE
        );

        // A recipient member cannot remove the owner's share.
        $this->deleteForbidden($member, 'mind', $mind, $share);
        $this->assertDatabaseHas('ai_resource_shares', ['id' => $share->id]);

        // The owner can.
        $this->deleteSucceeds($owner, 'mind', $mind, $share);
        $this->assertDatabaseMissing('ai_resource_shares', ['id' => $share->id]);
    }

    public function test_matrix_only_the_owner_can_delete_a_persona_share(): void
    {
        $owner  = $this->newUser();
        $member = $this->newUser();
        $team   = $this->newTeam($owner, $member);
        $persona = $this->newPersona($owner);

        $share = $this->shareService()->share(
            $owner, AiResourceShare::RESOURCE_PERSONA, (int) $persona->id,
            AiResourceShare::AUDIENCE_WORKSPACE, (int) $team->id, AiResourceShare::ACCESS_USE
        );

        $this->deleteForbidden($member, 'persona', $persona, $share);
        $this->assertDatabaseHas('ai_resource_shares', ['id' => $share->id]);

        $this->deleteSucceeds($owner, 'persona', $persona, $share);
        $this->assertDatabaseMissing('ai_resource_shares', ['id' => $share->id]);
    }

    // ===================================================================
    // 6. A suspended workspace member loses shared access (resolved live)
    // ===================================================================

    public function test_matrix_suspended_member_loses_shared_access(): void
    {
        $owner  = $this->newUser();
        $member = $this->newUser();
        $team   = $this->newTeam($owner, $member);
        $mind   = $this->newMind($owner);

        $this->shareService()->share(
            $owner, AiResourceShare::RESOURCE_MIND, (int) $mind->id,
            AiResourceShare::AUDIENCE_WORKSPACE, (int) $team->id, AiResourceShare::ACCESS_USE
        );

        // While active, the member can use the shared mind.
        $this->assertTrue($this->shareService()->canUseMind($member->fresh(), $mind));

        // Suspending the seat revokes access on the next resolution — no
        // per-user grant to clean up.
        WorkspaceMember::where('workspace_id', $team->id)
            ->where('user_id', $member->id)
            ->update(['suspended_at' => now()]);

        $this->assertFalse($this->shareService()->canUseMind($member->fresh(), $mind));
    }

    // ===================================================================
    // 7. A platform mind has no owner and is not manageable by anyone
    // ===================================================================

    public function test_matrix_platform_mind_is_not_manageable(): void
    {
        $actor = $this->newUser();
        // The actor owns a valid team, so the only thing stopping the share is
        // that a platform mind has no owner and can never be managed.
        $team  = $this->newTeam($actor);
        $platformMind = $this->newPlatformMind();

        $this->assertTrue($platformMind->isPlatform());

        $this->shareForbidden($actor, 'mind', $platformMind, 'workspace:' . $team->id);

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $platformMind->id,
        ]);
    }

    // ===================================================================
    // Shared data helper (transport-agnostic): a platform mind has a null
    // owner and is the default, mirroring AiMind::isPlatform().
    // ===================================================================

    protected function newPlatformMind(): AiMind
    {
        return AiMind::create([
            'user_id'    => null,
            'name'       => 'Platform Mind',
            'is_default' => true,
        ]);
    }

    // ===================================================================
    // Transport hooks — implemented per surface (web vs API).
    // ===================================================================

    /** The shared service instance under test. */
    abstract protected function shareService(): AiResourceShareService;

    /** A fresh active user with a default workspace. */
    abstract protected function newUser(): User;

    /** A mind owned by $owner. */
    abstract protected function newMind(User $owner): AiMind;

    /** A persona owned by $owner. */
    abstract protected function newPersona(User $owner): AiPersonaAgent;

    /**
     * A non-personal team owned by $owner; if $member is given they are
     * added as an (optionally suspended) member.
     */
    abstract protected function newTeam(User $owner, ?User $member = null, ?string $suspendedAt = null): Workspace;

    /** An account badge held by nobody. */
    abstract protected function newBadge(): AccountBadge;

    /**
     * Attempt to create a share as a user who is NOT the resource owner
     * (or for an unmanageable resource) and assert the surface forbids it.
     *
     * @param  'mind'|'persona'  $kind
     */
    abstract protected function shareForbidden(User $actor, string $kind, Model $resource, string $audience): void;

    /**
     * Attempt to create a share as the owner into an audience they do not
     * belong to and assert the surface rejects it cleanly (never a 500).
     *
     * @param  'mind'|'persona'  $kind
     */
    abstract protected function shareAudienceRejected(User $actor, string $kind, Model $resource, string $audience): void;

    /**
     * Attempt to delete a share as a non-owner and assert the surface
     * forbids it.
     *
     * @param  'mind'|'persona'  $kind
     */
    abstract protected function deleteForbidden(User $actor, string $kind, Model $resource, AiResourceShare $share): void;

    /**
     * Delete a share as the owner and assert the surface allows it.
     *
     * @param  'mind'|'persona'  $kind
     */
    abstract protected function deleteSucceeds(User $actor, string $kind, Model $resource, AiResourceShare $share): void;
}
