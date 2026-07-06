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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\AiResourceShareScenarioMatrix;
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
 *
 * The owner/audience guard scenarios (non-owner create, EDIT-editor
 * reshare, foreign team, badge-not-held, non-owner delete, suspended
 * member loses access, platform mind not manageable) come from the shared
 * {@see AiResourceShareScenarioMatrix} and are asserted IDENTICALLY here
 * and on the web surface ({@see AiResourceShareOwnershipTest}) so the two
 * can't drift apart (Task #2935). This class supplies the API transport
 * (JSON: 403 on ownership failure, 422 on audience failure) plus the
 * API-only positive/read tests below.
 */
class MobileAiResourceShareApiTest extends TestCase
{
    use RefreshDatabase;
    use AiResourceShareScenarioMatrix;

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setEnabled(true);
    }

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
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
    private function team(User $owner, ?User $member = null): Workspace
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

    public function test_non_owner_cannot_read_the_manage_payload(): void
    {
        // API-specific: the web edit screen has no equivalent read endpoint,
        // so the GET manage payload guard is asserted only here.
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $mind  = $this->mind($owner);

        $this->asUser($other)->getJson("/api/v1/ai/minds/{$mind->id}/shares")->assertStatus(403);
    }

    public function test_endpoints_404_when_ai_engine_disabled(): void
    {
        AiEngineSettings::setEnabled(false);
        $user = $this->makeUser();

        $this->asUser($user)->getJson('/api/v1/ai/shared')->assertStatus(404);
    }

    // ===================================================================
    // Shared-matrix transport hooks (API surface)
    // ===================================================================

    protected function shareService(): AiResourceShareService
    {
        return app(AiResourceShareService::class);
    }

    protected function newUser(): User
    {
        return $this->makeUser();
    }

    protected function newMind(User $owner): AiMind
    {
        return $this->mind($owner);
    }

    protected function newPersona(User $owner): AiPersonaAgent
    {
        return $this->persona($owner);
    }

    protected function newTeam(User $owner, ?User $member = null, ?string $suspendedAt = null): Workspace
    {
        $team = $owner->ownedWorkspaces()->create([
            'name' => 'Team ' . Str::random(4), 'slug' => 'team-' . Str::random(6), 'is_personal' => false,
        ]);
        if ($member) {
            WorkspaceMember::create([
                'workspace_id' => $team->id,
                'user_id'      => $member->id,
                'role'         => 'member',
                'suspended_at' => $suspendedAt,
            ]);
        }
        return $team;
    }

    protected function newBadge(): AccountBadge
    {
        return AccountBadge::create(['name' => 'b' . Str::random(5), 'color' => '#3b82f6']);
    }

    /** Map a scenario kind to its API share route base. */
    private function routeBase(string $kind, Model $resource): string
    {
        return $kind === 'mind'
            ? "/api/v1/ai/minds/{$resource->id}"
            : "/api/v1/ai/personas/{$resource->id}";
    }

    protected function shareForbidden(User $actor, string $kind, Model $resource, string $audience): void
    {
        $base = $this->routeBase($kind, $resource);
        $this->asUser($actor)->postJson("{$base}/shares", [
            'audience' => $audience,
            'access'   => 'use',
        ])->assertStatus(403);
    }

    protected function shareAudienceRejected(User $actor, string $kind, Model $resource, string $audience): void
    {
        $base = $this->routeBase($kind, $resource);
        $this->asUser($actor)->postJson("{$base}/shares", [
            'audience' => $audience,
            'access'   => 'use',
        ])->assertStatus(422);
    }

    protected function deleteForbidden(User $actor, string $kind, Model $resource, AiResourceShare $share): void
    {
        $base = $this->routeBase($kind, $resource);
        $this->asUser($actor)
            ->deleteJson("{$base}/shares/{$share->id}")
            ->assertStatus(403);
    }

    protected function deleteSucceeds(User $actor, string $kind, Model $resource, AiResourceShare $share): void
    {
        $base = $this->routeBase($kind, $resource);
        $this->asUser($actor)
            ->deleteJson("{$base}/shares/{$share->id}")
            ->assertNoContent();
    }

    // ===================================================================
    // Off-boarding revokes shared AI access LIVE over the API (Task #2936).
    //
    // Access is resolved against the recipient's CURRENT memberships /
    // badges on every request, so suspending a workspace seat or detaching
    // a badge must immediately cut a teammate's view of previously shared
    // Minds / Personas — no per-share cleanup, resolved on the next call.
    // ===================================================================

    public function test_suspending_a_member_revokes_their_shared_mind_over_the_api(): void
    {
        $owner  = $this->makeUser();
        $member = $this->makeUser();
        $team   = $this->team($owner, $member);
        $mind   = $this->mind($owner, ['name' => 'Team KB']);

        $this->asUser($owner)->postJson("/api/v1/ai/minds/{$mind->id}/shares", [
            'audience' => "workspace:{$team->id}",
            'access'   => 'use',
        ])->assertCreated();

        // The member sees it while their seat is active.
        $resp = $this->asUser($member)->getJson('/api/v1/ai/shared');
        $resp->assertOk();
        $this->assertCount(1, collect($resp->json('data.minds')));

        // Suspend the member's seat — no share row is touched.
        WorkspaceMember::where('workspace_id', $team->id)
            ->where('user_id', $member->id)
            ->update(['suspended_at' => now()]);

        // The share row still exists; only LIVE resolution drops it.
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $mind->id,
            'audience_type' => AiResourceShare::AUDIENCE_WORKSPACE,
            'audience_id'   => $team->id,
        ]);

        $resp = $this->asUser($member)->getJson('/api/v1/ai/shared');
        $resp->assertOk();
        $this->assertCount(0, collect($resp->json('data.minds')));
    }

    public function test_suspending_a_member_revokes_their_shared_persona_over_the_api(): void
    {
        $owner   = $this->makeUser();
        $member  = $this->makeUser();
        $team    = $this->team($owner, $member);
        $persona = $this->persona($owner, ['name' => 'Concierge']);

        $this->asUser($owner)->postJson("/api/v1/ai/personas/{$persona->id}/shares", [
            'audience' => "workspace:{$team->id}",
            'access'   => 'edit',
        ])->assertCreated();

        $resp = $this->asUser($member)->getJson('/api/v1/ai/shared');
        $resp->assertOk();
        $this->assertCount(1, collect($resp->json('data.personas')));

        WorkspaceMember::where('workspace_id', $team->id)
            ->where('user_id', $member->id)
            ->update(['suspended_at' => now()]);

        $resp = $this->asUser($member)->getJson('/api/v1/ai/shared');
        $resp->assertOk();
        $this->assertCount(0, collect($resp->json('data.personas')));
    }

    public function test_detaching_a_badge_revokes_badge_shared_resources_over_the_api(): void
    {
        $owner  = $this->makeUser();
        $member = $this->makeUser();
        $badge  = AccountBadge::create(['name' => 'VIP ' . Str::random(3), 'color' => '#3b82f6']);
        $owner->accountBadges()->attach($badge->id);
        $member->accountBadges()->attach($badge->id);

        $persona = $this->persona($owner, ['name' => 'Concierge']);

        $this->asUser($owner)->postJson("/api/v1/ai/personas/{$persona->id}/shares", [
            'audience' => "badge:{$badge->id}",
            'access'   => 'use',
        ])->assertCreated();

        // Badge holder sees the shared persona.
        $resp = $this->asUser($member)->getJson('/api/v1/ai/shared');
        $resp->assertOk();
        $this->assertCount(1, collect($resp->json('data.personas')));

        // Detach the badge from the member — share row untouched.
        $member->accountBadges()->detach($badge->id);

        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_PERSONA,
            'resource_id'   => $persona->id,
            'audience_type' => AiResourceShare::AUDIENCE_BADGE,
            'audience_id'   => $badge->id,
        ]);

        $resp = $this->asUser($member)->getJson('/api/v1/ai/shared');
        $resp->assertOk();
        $this->assertCount(0, collect($resp->json('data.personas')));
    }
}
