<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiResourceShare;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile parity for AI resource sharing (Task #2923, mirroring the
 * web-only Task #2909):
 *
 *   GET    /api/v1/ai/shared
 *   GET    /api/v1/ai/minds/{mind}/shares
 *   POST   /api/v1/ai/minds/{mind}/shares
 *   DELETE /api/v1/ai/minds/{mind}/shares/{share}
 *   (+ persona equivalents)
 *
 * Auth uses a real Bearer token — Sanctum::actingAs breaks the
 * TouchSessionToken middleware on this API path.
 */
class MobileAiResourceShareApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setEnabled(true);
    }

    private function makeUser(): User
    {
        $user = User::create([
            'name'     => 'RS ' . Str::random(4),
            'email'    => 'rs-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function asUser(User $user): self
    {
        $this->withToken($user->createToken('mobile-test')->plainTextToken);
        return $this;
    }

    private function mind(User $user, array $overrides = []): AiMind
    {
        return AiMind::create(array_merge([
            'user_id' => $user->id,
            'name'    => 'KB ' . Str::random(4),
        ], $overrides));
    }

    private function persona(User $user, array $overrides = []): AiPersonaAgent
    {
        return AiPersonaAgent::create(array_merge([
            'user_id'           => $user->id,
            'slug'              => 'p-' . Str::random(6),
            'name'              => 'Helper',
            'system_prompt'     => 'You help visitors.',
            'use_brand_kit'     => true,
            'model'             => 'gpt-4o-mini',
            'temperature_x100'  => 50,
            'max_tokens'        => 300,
            'languages'         => [],
            'allowed_actions'   => [],
            'fallback_behavior' => 'clarify',
            'use_default_mind'  => false,
            'is_disabled'       => false,
        ], $overrides));
    }

    /** A non-personal team workspace owned by $owner with $member inside. */
    private function team(User $owner, ?User $member = null): \App\Modules\User\Models\Workspace
    {
        $team = $owner->ownedWorkspaces()->create([
            'name' => 'Team ' . Str::random(4), 'slug' => 'team-' . Str::random(6), 'is_personal' => false,
        ]);
        if ($member) {
            WorkspaceMember::create([
                'workspace_id' => $team->id,
                'user_id'      => $member->id,
                'role'         => 'member',
            ]);
        }
        return $team;
    }

    public function test_owner_can_share_a_mind_into_their_team(): void
    {
        $owner  = $this->makeUser();
        $member = $this->makeUser();
        $team   = $this->team($owner, $member);
        $mind   = $this->mind($owner);

        $resp = $this->asUser($owner)->postJson("/api/v1/ai/minds/{$mind->id}/shares", [
            'audience' => "workspace:{$team->id}",
            'access'   => 'edit',
        ]);

        $resp->assertCreated();
        $resp->assertJsonPath('data.share.access', 'edit');
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => 'mind',
            'resource_id'   => $mind->id,
            'audience_type' => 'workspace',
            'audience_id'   => $team->id,
            'access'        => 'edit',
        ]);
    }

    public function test_shared_mind_appears_for_the_member_with_access_level(): void
    {
        $owner  = $this->makeUser();
        $member = $this->makeUser();
        $team   = $this->team($owner, $member);
        $mind   = $this->mind($owner, ['name' => 'Team KB']);

        $this->asUser($owner)->postJson("/api/v1/ai/minds/{$mind->id}/shares", [
            'audience' => "workspace:{$team->id}",
            'access'   => 'use',
        ])->assertCreated();

        $resp = $this->asUser($member)->getJson('/api/v1/ai/shared');
        $resp->assertOk();
        $minds = collect($resp->json('data.minds'));
        $this->assertCount(1, $minds);
        $this->assertSame('Team KB', $minds->first()['name']);
        $this->assertSame('use', $minds->first()['access']);
        $this->assertFalse($minds->first()['can_edit']);
    }

    public function test_shared_persona_appears_for_a_badge_holder(): void
    {
        $owner  = $this->makeUser();
        $member = $this->makeUser();
        $badge  = AccountBadge::create(['name' => 'VIP ' . Str::random(3), 'color' => '#3b82f6']);
        $owner->accountBadges()->attach($badge->id);
        $member->accountBadges()->attach($badge->id);

        $persona = $this->persona($owner, ['name' => 'Concierge']);

        $this->asUser($owner)->postJson("/api/v1/ai/personas/{$persona->id}/shares", [
            'audience' => "badge:{$badge->id}",
            'access'   => 'edit',
        ])->assertCreated();

        $resp = $this->asUser($member)->getJson('/api/v1/ai/shared');
        $resp->assertOk();
        $personas = collect($resp->json('data.personas'));
        $this->assertCount(1, $personas);
        $this->assertSame('Concierge', $personas->first()['name']);
        $this->assertSame('edit', $personas->first()['access']);
        $this->assertTrue($personas->first()['can_edit']);
    }

    public function test_owner_manage_payload_lists_audiences_and_existing_shares(): void
    {
        $owner = $this->makeUser();
        $team  = $this->team($owner);
        $badge = AccountBadge::create(['name' => 'Crew ' . Str::random(3), 'color' => '#3b82f6']);
        $owner->accountBadges()->attach($badge->id);
        $mind  = $this->mind($owner);

        $this->asUser($owner)->postJson("/api/v1/ai/minds/{$mind->id}/shares", [
            'audience' => "workspace:{$team->id}",
            'access'   => 'use',
        ])->assertCreated();

        $resp = $this->asUser($owner)->getJson("/api/v1/ai/minds/{$mind->id}/shares");
        $resp->assertOk();
        $this->assertNotEmpty($resp->json('data.workspaces'));
        $this->assertNotEmpty($resp->json('data.badges'));
        $shares = collect($resp->json('data.shares'));
        $this->assertCount(1, $shares);
        $this->assertSame('workspace', $shares->first()['audience_type']);
        $this->assertSame($team->id, $shares->first()['audience_id']);
    }

    public function test_owner_can_remove_a_share(): void
    {
        $owner  = $this->makeUser();
        $member = $this->makeUser();
        $team   = $this->team($owner, $member);
        $mind   = $this->mind($owner);

        $this->asUser($owner)->postJson("/api/v1/ai/minds/{$mind->id}/shares", [
            'audience' => "workspace:{$team->id}",
            'access'   => 'use',
        ])->assertCreated();

        $share = AiResourceShare::where('resource_id', $mind->id)->firstOrFail();

        $this->asUser($owner)
            ->deleteJson("/api/v1/ai/minds/{$mind->id}/shares/{$share->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('ai_resource_shares', ['id' => $share->id]);

        // Member no longer sees it.
        $resp = $this->asUser($member)->getJson('/api/v1/ai/shared');
        $this->assertCount(0, collect($resp->json('data.minds')));
    }

    public function test_non_owner_cannot_share_or_manage(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $team  = $this->team($other, $owner); // `other` owns a team `owner` is in
        $mind  = $this->mind($owner);

        // `other` is not the mind owner — cannot manage its shares.
        $this->asUser($other)->getJson("/api/v1/ai/minds/{$mind->id}/shares")->assertStatus(403);
        $this->asUser($other)->postJson("/api/v1/ai/minds/{$mind->id}/shares", [
            'audience' => "workspace:{$team->id}",
            'access'   => 'use',
        ])->assertStatus(403);
    }

    public function test_cannot_share_into_an_audience_you_dont_belong_to(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();
        $foreign  = $this->team($stranger); // a team the owner is NOT in
        $mind     = $this->mind($owner);

        $resp = $this->asUser($owner)->postJson("/api/v1/ai/minds/{$mind->id}/shares", [
            'audience' => "workspace:{$foreign->id}",
            'access'   => 'use',
        ]);

        $resp->assertStatus(422);
        $this->assertDatabaseMissing('ai_resource_shares', ['resource_id' => $mind->id]);
    }

    public function test_endpoints_404_when_ai_engine_disabled(): void
    {
        AiEngineSettings::setEnabled(false);
        $user = $this->makeUser();

        $this->asUser($user)->getJson('/api/v1/ai/shared')->assertStatus(404);
    }

    // ===================================================================
    // Owner-only share/unshare guards over the API (Task #2934).
    //
    // Mirrors the web AiResourceShareOwnershipTest (Task #2931) for the
    // /api/v1 controller: a non-owner — including an EDIT-access shared
    // editor — must never grant or remove shares for someone else's
    // Mind/Persona, and the owner can only share into audiences they
    // actually belong to (clean 422, never a 500).
    // ===================================================================

    public function test_edit_shared_editor_cannot_reshare_a_mind_they_dont_own(): void
    {
        // An EDIT-access teammate can edit the mind, but must NOT be able to
        // re-share it — sharing is owner-only.
        $owner  = $this->makeUser();
        $editor = $this->makeUser();
        $team   = $this->team($owner, $editor);
        $mind   = $this->mind($owner);

        // Grant the editor real EDIT access via a share.
        $this->asUser($owner)->postJson("/api/v1/ai/minds/{$mind->id}/shares", [
            'audience' => "workspace:{$team->id}",
            'access'   => 'edit',
        ])->assertCreated();

        // A team the editor owns — a perfectly valid audience for THEM, so the
        // 403 can only come from the ownership guard, not the audience guard.
        $editorTeam = $this->team($editor);

        $this->asUser($editor)->postJson("/api/v1/ai/minds/{$mind->id}/shares", [
            'audience' => "workspace:{$editorTeam->id}",
            'access'   => 'use',
        ])->assertStatus(403);

        // No new audience row leaked in: only the owner's original EDIT share exists.
        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $mind->id,
            'audience_id'   => $editorTeam->id,
        ]);
        $this->assertSame(1, AiResourceShare::where('resource_type', AiResourceShare::RESOURCE_MIND)
            ->where('resource_id', $mind->id)->count());
    }

    public function test_non_owner_cannot_create_a_mind_share_and_writes_no_row(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();
        $mind     = $this->mind($owner);
        // The stranger owns their own team, so the audience itself is valid —
        // the only thing stopping the share is that they don't own the mind.
        $strangerTeam = $this->team($stranger);

        $this->asUser($stranger)->postJson("/api/v1/ai/minds/{$mind->id}/shares", [
            'audience' => "workspace:{$strangerTeam->id}",
            'access'   => 'use',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $mind->id,
        ]);
    }

    public function test_non_owner_cannot_create_a_persona_share_and_writes_no_row(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();
        $persona  = $this->persona($owner);
        $strangerTeam = $this->team($stranger);

        $this->asUser($stranger)->getJson("/api/v1/ai/personas/{$persona->id}/shares")->assertStatus(403);
        $this->asUser($stranger)->postJson("/api/v1/ai/personas/{$persona->id}/shares", [
            'audience' => "workspace:{$strangerTeam->id}",
            'access'   => 'use',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_PERSONA,
            'resource_id'   => $persona->id,
        ]);
    }

    public function test_owner_sharing_into_a_badge_they_dont_hold_errors_without_500(): void
    {
        $owner   = $this->makeUser();
        $persona = $this->persona($owner);
        // A badge the owner does NOT hold.
        $badge = AccountBadge::create(['name' => 'Stray ' . Str::random(3), 'color' => '#3b82f6']);

        $resp = $this->asUser($owner)->postJson("/api/v1/ai/personas/{$persona->id}/shares", [
            'audience' => "badge:{$badge->id}",
            'access'   => 'use',
        ]);

        $resp->assertStatus(422);
        $this->assertDatabaseMissing('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_PERSONA,
            'resource_id'   => $persona->id,
            'audience_id'   => $badge->id,
        ]);
    }

    public function test_non_owner_cannot_delete_a_mind_share_and_row_survives(): void
    {
        $owner  = $this->makeUser();
        $member = $this->makeUser();
        $team   = $this->team($owner, $member);
        $mind   = $this->mind($owner);

        $this->asUser($owner)->postJson("/api/v1/ai/minds/{$mind->id}/shares", [
            'audience' => "workspace:{$team->id}",
            'access'   => 'use',
        ])->assertCreated();
        $share = AiResourceShare::where('resource_id', $mind->id)->firstOrFail();

        // A recipient member cannot remove the owner's share.
        $this->asUser($member)
            ->deleteJson("/api/v1/ai/minds/{$mind->id}/shares/{$share->id}")
            ->assertStatus(403);
        $this->assertDatabaseHas('ai_resource_shares', ['id' => $share->id]);

        // The owner still can.
        $this->asUser($owner)
            ->deleteJson("/api/v1/ai/minds/{$mind->id}/shares/{$share->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('ai_resource_shares', ['id' => $share->id]);
    }

    public function test_non_owner_cannot_delete_a_persona_share_and_row_survives(): void
    {
        $owner   = $this->makeUser();
        $member  = $this->makeUser();
        $team    = $this->team($owner, $member);
        $persona = $this->persona($owner);

        $this->asUser($owner)->postJson("/api/v1/ai/personas/{$persona->id}/shares", [
            'audience' => "workspace:{$team->id}",
            'access'   => 'use',
        ])->assertCreated();
        $share = AiResourceShare::where('resource_type', AiResourceShare::RESOURCE_PERSONA)
            ->where('resource_id', $persona->id)->firstOrFail();

        $this->asUser($member)
            ->deleteJson("/api/v1/ai/personas/{$persona->id}/shares/{$share->id}")
            ->assertStatus(403);
        $this->assertDatabaseHas('ai_resource_shares', ['id' => $share->id]);

        $this->asUser($owner)
            ->deleteJson("/api/v1/ai/personas/{$persona->id}/shares/{$share->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('ai_resource_shares', ['id' => $share->id]);
    }
}
