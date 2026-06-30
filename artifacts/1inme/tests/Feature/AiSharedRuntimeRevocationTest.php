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
use App\Services\AI\AiMindQueryService;
use App\Services\AI\AiResourceShareService;
use App\Services\AI\PersonaRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Off-boarding revokes shared AI access at the RUNTIME endpoints, not
 * just in the listing surface (Task #2938, the higher-stakes sibling of
 * Task #2936's GET /api/v1/ai/shared coverage).
 *
 * Task #2936 pinned that a suspended teammate / detached-badge holder
 * stops *seeing* a shared Mind / Persona. The more important guarantee
 * is that they can no longer *invoke* one — chatting against a shared
 * Mind ("Test this Mind", POST /user/minds/{mind}/ask) or running a
 * shared Persona ("Test this Persona", POST /user/ai-personas/{persona}
 * /test). Both controllers gate on AiResourceShareService::canUseMind /
 * accessForPersona, which resolve LIVE against current memberships /
 * badges, so suspending a seat or detaching a badge must turn a working
 * 200 into a 403 on the very next call — before any AI / coin spend.
 *
 * The AI services (AiMindQueryService::ask, PersonaRuntime::turn) are
 * replaced with doubles so the "still allowed" case proves the gate was
 * passed without any network call; the denial case 403s before the
 * runtime is ever reached.
 */
class AiSharedRuntimeRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The mind/persona runtime controllers 404 unless the engine is on.
        AiEngineSettings::setEnabled(true);
        $this->bindAiDoubles();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Swap the live AI services for doubles so the "still allowed" path
     * returns deterministically with no network call. If the access gate
     * ever 403s, these are never reached.
     */
    protected function bindAiDoubles(): void
    {
        $mindQuery = Mockery::mock(AiMindQueryService::class);
        $mindQuery->shouldReceive('ask')->andReturn([
            'answer'        => 'A grounded answer.',
            'sources'       => [],
            'credits_spent' => 0,
        ]);
        $this->app->instance(AiMindQueryService::class, $mindQuery);

        $personaRuntime = Mockery::mock(PersonaRuntime::class);
        $personaRuntime->shouldReceive('turn')->andReturn([
            'reply'         => 'Hi there!',
            'credits_spent' => 0,
        ]);
        $this->app->instance(PersonaRuntime::class, $personaRuntime);
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

    private function memberOf(Workspace $ws, User $user): WorkspaceMember
    {
        return WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $user->id,
            'role'         => 'member',
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

    private function askMind(User $user, AiMind $mind)
    {
        return $this->actingAs($user)->postJson("/user/minds/{$mind->id}/ask", [
            'question' => 'What can you tell me?',
        ]);
    }

    private function askMindWithAlso(User $user, AiMind $mind, array $alsoIds)
    {
        return $this->actingAs($user)->postJson("/user/minds/{$mind->id}/ask", [
            'question' => 'What can you tell me?',
            'also'     => $alsoIds,
        ]);
    }

    /**
     * Re-bind the Mind query service with a double that records the exact
     * set of Mind models handed to ::ask, so a test can assert which Minds
     * a chat call actually queried (and prove the dropped 'also' Mind was
     * not silently folded back in).
     */
    private function captureAskMinds(): object
    {
        $bucket = new class {
            /** @var array<int, array<int, AiMind>> */
            public array $calls = [];

            /** Mind ids passed to the most recent ::ask call. */
            public function lastIds(): array
            {
                $last = empty($this->calls) ? [] : end($this->calls);
                return array_map(static fn ($m) => (int) $m->id, $last);
            }
        };

        $mock = Mockery::mock(AiMindQueryService::class);
        $mock->shouldReceive('ask')->andReturnUsing(function ($user, $minds, $question) use ($bucket) {
            $bucket->calls[] = array_values($minds);
            return ['answer' => 'A grounded answer.', 'sources' => [], 'credits_spent' => 0];
        });
        $this->app->instance(AiMindQueryService::class, $mock);

        return $bucket;
    }

    private function runPersona(User $user, AiPersonaAgent $persona)
    {
        return $this->actingAs($user)->postJson("/user/ai-personas/{$persona->id}/test", [
            'message' => 'Hello!',
        ]);
    }

    // ===================================================================
    // Workspace-membership suspension
    // ===================================================================

    public function test_suspended_member_is_denied_at_the_mind_ask_runtime_endpoint(): void
    {
        $owner  = $this->user();
        $member = $this->user();
        $team   = $this->team($owner);
        $ms     = $this->memberOf($team, $member);

        $mind = $this->mind($owner);
        app(AiResourceShareService::class)->share(
            $owner, AiResourceShare::RESOURCE_MIND, $mind->id,
            AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE
        );

        // While the seat is active the teammate can actually chat against it.
        $this->askMind($member->fresh(), $mind)->assertOk();

        // Suspend the seat — the share row is untouched, only LIVE
        // resolution changes.
        $ms->forceFill(['suspended_at' => now()])->save();
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $mind->id,
        ]);

        // The very next runtime call is denied before any AI/coin spend.
        $this->askMind($member->fresh(), $mind)->assertForbidden();
    }

    public function test_suspended_member_is_denied_at_the_persona_test_runtime_endpoint(): void
    {
        $owner  = $this->user();
        $member = $this->user();
        $team   = $this->team($owner);
        $ms     = $this->memberOf($team, $member);

        $persona = $this->persona($owner);
        app(AiResourceShareService::class)->share(
            $owner, AiResourceShare::RESOURCE_PERSONA, $persona->id,
            AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE
        );

        $this->runPersona($member->fresh(), $persona)->assertOk();

        $ms->forceFill(['suspended_at' => now()])->save();
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_PERSONA,
            'resource_id'   => $persona->id,
        ]);

        $this->runPersona($member->fresh(), $persona)->assertForbidden();
    }

    // ===================================================================
    // Badge detach
    // ===================================================================

    public function test_detached_badge_holder_is_denied_at_the_mind_ask_runtime_endpoint(): void
    {
        $owner  = $this->user();
        $holder = $this->user();
        $badge  = $this->badge();
        // Owner must hold the badge to share into it; the recipient holds it too.
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $mind = $this->mind($owner);
        app(AiResourceShareService::class)->share(
            $owner, AiResourceShare::RESOURCE_MIND, $mind->id,
            AiResourceShare::AUDIENCE_BADGE, $badge->id, AiResourceShare::ACCESS_USE
        );

        $this->askMind($holder->fresh(), $mind)->assertOk();

        // Detach the badge from the recipient — share row untouched.
        $holder->accountBadges()->detach($badge->id);
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $mind->id,
            'audience_type' => AiResourceShare::AUDIENCE_BADGE,
            'audience_id'   => $badge->id,
        ]);

        $this->askMind($holder->fresh(), $mind)->assertForbidden();
    }

    public function test_detached_badge_holder_is_denied_at_the_persona_test_runtime_endpoint(): void
    {
        $owner  = $this->user();
        $holder = $this->user();
        $badge  = $this->badge();
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $persona = $this->persona($owner);
        app(AiResourceShareService::class)->share(
            $owner, AiResourceShare::RESOURCE_PERSONA, $persona->id,
            AiResourceShare::AUDIENCE_BADGE, $badge->id, AiResourceShare::ACCESS_USE
        );

        $this->runPersona($holder->fresh(), $persona)->assertOk();

        $holder->accountBadges()->detach($badge->id);
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_PERSONA,
            'resource_id'   => $persona->id,
            'audience_type' => AiResourceShare::AUDIENCE_BADGE,
            'audience_id'   => $badge->id,
        ]);

        $this->runPersona($holder->fresh(), $persona)->assertForbidden();
    }

    // ===================================================================
    // 'also' piggy-back: a revoked teammate must be dropped from the
    // optional extra-Minds list, not just from the focused Mind.
    // ===================================================================

    public function test_suspended_member_extra_also_mind_is_excluded_from_the_query(): void
    {
        $owner  = $this->user();
        $member = $this->user();
        $team   = $this->team($owner);
        $ms     = $this->memberOf($team, $member);

        // Two Minds shared with the member via the team seat (USE access).
        $sharedA = $this->mind($owner);
        $sharedB = $this->mind($owner);
        foreach ([$sharedA, $sharedB] as $m) {
            app(AiResourceShareService::class)->share(
                $owner, AiResourceShare::RESOURCE_MIND, $m->id,
                AiResourceShare::AUDIENCE_WORKSPACE, $team->id, AiResourceShare::ACCESS_USE
            );
        }

        // The member's own Mind anchors the focused call so the query still
        // runs after revocation — letting us inspect which Minds survived.
        $ownMind = $this->mind($member);

        $captured = $this->captureAskMinds();

        // While the seat is active: both shared 'also' Minds are folded in.
        $this->askMindWithAlso($member->fresh(), $ownMind, [$sharedA->id, $sharedB->id])
            ->assertOk();
        $this->assertEqualsCanonicalizing(
            [$ownMind->id, $sharedA->id, $sharedB->id],
            $captured->lastIds(),
            'While active, both shared Minds should be queryable via "also".'
        );

        // Suspend the seat — only LIVE resolution changes, share rows stay.
        $ms->forceFill(['suspended_at' => now()])->save();
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $sharedA->id,
        ]);

        // The very next chat call must silently drop BOTH shared Minds from
        // the 'also' set — the query runs against the own Mind alone.
        $this->askMindWithAlso($member->fresh(), $ownMind, [$sharedA->id, $sharedB->id])
            ->assertOk();
        $this->assertEquals(
            [$ownMind->id],
            $captured->lastIds(),
            'After suspension, the shared "also" Minds must be excluded — no piggy-backing.'
        );

        // And a direct focused call against a shared Mind is denied outright.
        $this->askMind($member->fresh(), $sharedA)->assertForbidden();
    }

    public function test_detached_badge_holder_extra_also_mind_is_excluded_from_the_query(): void
    {
        $owner  = $this->user();
        $holder = $this->user();
        $badge  = $this->badge();
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $sharedA = $this->mind($owner);
        $sharedB = $this->mind($owner);
        foreach ([$sharedA, $sharedB] as $m) {
            app(AiResourceShareService::class)->share(
                $owner, AiResourceShare::RESOURCE_MIND, $m->id,
                AiResourceShare::AUDIENCE_BADGE, $badge->id, AiResourceShare::ACCESS_USE
            );
        }

        $ownMind = $this->mind($holder);

        $captured = $this->captureAskMinds();

        $this->askMindWithAlso($holder->fresh(), $ownMind, [$sharedA->id, $sharedB->id])
            ->assertOk();
        $this->assertEqualsCanonicalizing(
            [$ownMind->id, $sharedA->id, $sharedB->id],
            $captured->lastIds(),
            'While holding the badge, both shared Minds should be queryable via "also".'
        );

        // Detach the badge — share rows untouched, live access lost.
        $holder->accountBadges()->detach($badge->id);
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $sharedA->id,
            'audience_type' => AiResourceShare::AUDIENCE_BADGE,
            'audience_id'   => $badge->id,
        ]);

        $this->askMindWithAlso($holder->fresh(), $ownMind, [$sharedA->id, $sharedB->id])
            ->assertOk();
        $this->assertEquals(
            [$ownMind->id],
            $captured->lastIds(),
            'After badge detach, the shared "also" Minds must be excluded — no piggy-backing.'
        );

        $this->askMind($holder->fresh(), $sharedA)->assertForbidden();
    }
}
