<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiResourceShare;
use App\Modules\User\Models\AskCoachThread;
use App\Modules\User\Models\CompanionThread;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindFeatureAdapter;
use App\Services\AI\AiMindQueryService;
use App\Services\AI\AiResourceShareService;
use App\Services\AI\OpenAiService;
use App\Services\AI\SiteAssistantRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Off-boarding must not leak a shared Mind's KB through the GROUNDING
 * surfaces of OTHER AI features (Task #2942, sibling of Task #2941's
 * MindChatController::ask coverage).
 *
 * Task #2941 pinned that a suspended teammate / detached-badge holder is
 * dropped from both the focused Mind and the 'also' set of the "Test this
 * Mind" chat. But shared Minds can also be selected as knowledge-base
 * grounding for OTHER AI features — AI Coach (POST /user/ai/coach/suggest)
 * and Persona test (POST /user/ai/persona/generate). The worry: if those
 * surfaces folded a shared Mind into the grounding context without a live
 * access check, a revoked teammate could keep grounding an AI call on the
 * owner's KB (content leaked, cost charged to the teammate).
 *
 * Finding: every grounding surface (Coach, Persona, Ask Coach, Brand Kit,
 * Companion, Site Assistant) resolves picked Minds through
 * AiMindQueryService::resolveMindsForUser(), which is OWNER-ONLY — it
 * only ever returns Minds the asking user owns (plus the opt-in platform
 * default). A Mind merely SHARED with the user (via team seat or badge
 * group) is never resolvable as grounding, so it can never be folded into
 * the context — a guarantee strictly stronger than canUseMind, and one
 * that holds regardless of whether the teammate still has live USE access.
 * No production gate change was required; these tests lock that in.
 *
 * Each test proves the shared Mind is excluded from the grounding query
 * (a) WHILE the teammate still has live USE access — so the exclusion is
 * the owner-only grounding policy, not a missing share — and (b) AFTER
 * seat suspension / badge detach. The acting user's OWN Mind anchors the
 * call so retrieval still runs, letting us inspect exactly which Minds
 * survived. AiMindQueryService::retrieveContext and OpenAiService::chat
 * are replaced with doubles so no embedding / chat network call is made.
 */
class AiGroundingSharedMindRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Coach / Persona 404 unless the engine is on.
        AiEngineSettings::setEnabled(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ===================================================================
    // Fixtures
    // ===================================================================

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

    private function shareMind(User $owner, AiMind $mind, string $audienceType, int $audienceId): void
    {
        app(AiResourceShareService::class)->share(
            $owner, AiResourceShare::RESOURCE_MIND, $mind->id,
            $audienceType, $audienceId, AiResourceShare::ACCESS_USE
        );
    }

    /** The member's own personal workspace (so Coach resolves them as owner). */
    private function personalWorkspace(User $user): Workspace
    {
        return Workspace::where('owner_user_id', $user->id)
            ->where('is_personal', true)
            ->firstOrFail();
    }

    /**
     * Swap the runtime AI services for doubles:
     *   - AiMindQueryService is a PARTIAL mock so the real (owner-only)
     *     resolveMindsForUser() still runs, while retrieveContext() is
     *     captured and returns empty — proving exactly which Minds the
     *     grounding actually queried, with no embedding network call.
     *   - OpenAiService::chat is stubbed so the feature completes without
     *     a chat completion call.
     *
     * @return object bucket exposing lastIds(): the Mind ids handed to the
     *                most recent retrieveContext() call.
     */
    private function captureGroundingMinds(): object
    {
        $bucket = new class {
            /** @var array<int, array<int, AiMind>> */
            public array $calls = [];

            public function lastIds(): array
            {
                $last = empty($this->calls) ? [] : end($this->calls);
                return array_map(static fn ($m) => (int) $m->id, $last);
            }
        };

        $ai = Mockery::mock(OpenAiService::class);
        $ai->shouldReceive('chat')->andReturn([
            'content'       => 'A grounded answer.',
            'credits_spent' => 0,
            'model'         => 'gpt-4o-mini',
        ]);
        $this->app->instance(OpenAiService::class, $ai);

        $svc = Mockery::mock(AiMindQueryService::class, [
            $ai,
            app(AiMindFeatureAdapter::class),
        ])->makePartial();
        $svc->shouldReceive('retrieveContext')->andReturnUsing(
            function ($user, $minds, $query) use ($bucket) {
                $bucket->calls[] = array_values($minds);
                return [
                    'context'           => 'KB context.',
                    'citations'         => [],
                    'feature_snapshots' => [],
                    'mind_stats'        => [],
                    'credits_spent'     => 0,
                ];
            }
        );
        $this->app->instance(AiMindQueryService::class, $svc);

        return $bucket;
    }

    private function callCoach(User $member, Link $link, array $mindIds)
    {
        return $this->actingAs($member->fresh())
            ->withSession(['active_workspace_id' => $this->personalWorkspace($member)->id])
            ->from(route('user.ai.coach.show'))
            ->post(route('user.ai.coach.suggest'), [
                'link_id'  => $link->id,
                'goal'     => 'increase engagement',
                'mind_ids' => $mindIds,
            ]);
    }

    private function callPersona(User $member, array $mindIds)
    {
        return $this->actingAs($member->fresh())
            ->from(route('user.ai.persona.show'))
            ->post(route('user.ai.persona.generate'), [
                'audience' => 'Indie makers',
                'goals'    => 'Grow a newsletter',
                'tone'     => 'Friendly',
                'mind_ids' => $mindIds,
            ]);
    }

    private function ownLink(User $user): Link
    {
        return Link::create([
            'user_id'      => $user->id,
            'workspace_id' => $this->personalWorkspace($user)->id,
            'type'         => 'biolink',
            'alias'        => Link::generateAlias(),
            'title'        => 'My Link',
            'is_active'    => true,
        ]);
    }

    // ===================================================================
    // Coach
    // ===================================================================

    public function test_coach_grounding_excludes_shared_mind_before_and_after_seat_suspension(): void
    {
        $owner  = $this->user();
        $member = $this->user();
        $team   = $this->team($owner);
        $ms     = $this->memberOf($team, $member);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_WORKSPACE, $team->id);
        $ownMind = $this->mind($member);
        $link    = $this->ownLink($member);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureGroundingMinds();

        // The teammate genuinely has LIVE USE access right now…
        $this->assertTrue($shares->canUseMind($member->fresh(), $shared));

        // …yet Coach grounding still drops the shared Mind (owner-only),
        // querying only the member's own Mind.
        $this->callCoach($member, $link, [$ownMind->id, $shared->id]);
        $this->assertEquals(
            [$ownMind->id],
            $captured->lastIds(),
            'Coach must ground only on the member\'s own Mind, never a shared one.'
        );

        // Suspend the seat — share row untouched, live access lost.
        $ms->forceFill(['suspended_at' => now()])->save();
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $shared->id,
        ]);
        $this->assertFalse($shares->canUseMind($member->fresh(), $shared));

        // Still excluded after revocation — never folded in.
        $this->callCoach($member, $link, [$ownMind->id, $shared->id]);
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }

    public function test_coach_grounding_excludes_shared_mind_before_and_after_badge_detach(): void
    {
        $owner  = $this->user();
        $holder = $this->user();
        $badge  = $this->badge();
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_BADGE, $badge->id);
        $ownMind = $this->mind($holder);
        $link    = $this->ownLink($holder);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureGroundingMinds();

        $this->assertTrue($shares->canUseMind($holder->fresh(), $shared));

        $this->callCoach($holder, $link, [$ownMind->id, $shared->id]);
        $this->assertEquals([$ownMind->id], $captured->lastIds());

        $holder->accountBadges()->detach($badge->id);
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $shared->id,
            'audience_type' => AiResourceShare::AUDIENCE_BADGE,
            'audience_id'   => $badge->id,
        ]);
        $this->assertFalse($shares->canUseMind($holder->fresh(), $shared));

        $this->callCoach($holder, $link, [$ownMind->id, $shared->id]);
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }

    // ===================================================================
    // Persona
    // ===================================================================

    public function test_persona_grounding_excludes_shared_mind_before_and_after_seat_suspension(): void
    {
        $owner  = $this->user();
        $member = $this->user();
        $team   = $this->team($owner);
        $ms     = $this->memberOf($team, $member);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_WORKSPACE, $team->id);
        $ownMind = $this->mind($member);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureGroundingMinds();

        $this->assertTrue($shares->canUseMind($member->fresh(), $shared));

        $this->callPersona($member, [$ownMind->id, $shared->id]);
        $this->assertEquals(
            [$ownMind->id],
            $captured->lastIds(),
            'Persona must ground only on the member\'s own Mind, never a shared one.'
        );

        $ms->forceFill(['suspended_at' => now()])->save();
        $this->assertFalse($shares->canUseMind($member->fresh(), $shared));

        $this->callPersona($member, [$ownMind->id, $shared->id]);
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }

    public function test_persona_grounding_excludes_shared_mind_before_and_after_badge_detach(): void
    {
        $owner  = $this->user();
        $holder = $this->user();
        $badge  = $this->badge();
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_BADGE, $badge->id);
        $ownMind = $this->mind($holder);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureGroundingMinds();

        $this->assertTrue($shares->canUseMind($holder->fresh(), $shared));

        $this->callPersona($holder, [$ownMind->id, $shared->id]);
        $this->assertEquals([$ownMind->id], $captured->lastIds());

        $holder->accountBadges()->detach($badge->id);
        $this->assertFalse($shares->canUseMind($holder->fresh(), $shared));

        $this->callPersona($holder, [$ownMind->id, $shared->id]);
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }

    // ===================================================================
    // Ask Coach
    //
    // Ask Coach's chat send accepts a Mind picker directly in the request
    // body (mind_ids), so a revoked teammate could literally name the
    // owner's shared Mind id — yet AskCoachController::resolveKb resolves
    // it through the owner-only resolveMindsForUser(), so it never reaches
    // retrieveContext.
    // ===================================================================

    /** Create a fresh Ask Coach thread for $user via the real store route. */
    private function newAskCoachThread(User $user): AskCoachThread
    {
        $this->actingAs($user->fresh())->post(route('user.ai.ask-coach.store'))->assertRedirect();
        return AskCoachThread::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    private function callAskCoach(User $user, AskCoachThread $thread, array $mindIds)
    {
        return $this->actingAs($user->fresh())->post(
            route('user.ai.ask-coach.send', $thread->id),
            ['message' => 'How are my links doing?', 'mind_ids' => $mindIds]
        );
    }

    public function test_ask_coach_grounding_excludes_shared_mind_before_and_after_seat_suspension(): void
    {
        $owner  = $this->user();
        $member = $this->user();
        $team   = $this->team($owner);
        $ms     = $this->memberOf($team, $member);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_WORKSPACE, $team->id);
        $ownMind = $this->mind($member);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureGroundingMinds();
        $thread   = $this->newAskCoachThread($member);

        $this->assertTrue($shares->canUseMind($member->fresh(), $shared));

        $this->callAskCoach($member, $thread, [$ownMind->id, $shared->id]);
        $this->assertEquals(
            [$ownMind->id],
            $captured->lastIds(),
            'Ask Coach must ground only on the member\'s own Mind, never a shared one.'
        );

        $ms->forceFill(['suspended_at' => now()])->save();
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $shared->id,
        ]);
        $this->assertFalse($shares->canUseMind($member->fresh(), $shared));

        $this->callAskCoach($member, $thread, [$ownMind->id, $shared->id]);
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }

    public function test_ask_coach_grounding_excludes_shared_mind_before_and_after_badge_detach(): void
    {
        $owner  = $this->user();
        $holder = $this->user();
        $badge  = $this->badge();
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_BADGE, $badge->id);
        $ownMind = $this->mind($holder);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureGroundingMinds();
        $thread   = $this->newAskCoachThread($holder);

        $this->assertTrue($shares->canUseMind($holder->fresh(), $shared));

        $this->callAskCoach($holder, $thread, [$ownMind->id, $shared->id]);
        $this->assertEquals([$ownMind->id], $captured->lastIds());

        $holder->accountBadges()->detach($badge->id);
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $shared->id,
            'audience_type' => AiResourceShare::AUDIENCE_BADGE,
            'audience_id'   => $badge->id,
        ]);
        $this->assertFalse($shares->canUseMind($holder->fresh(), $shared));

        $this->callAskCoach($holder, $thread, [$ownMind->id, $shared->id]);
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }

    // ===================================================================
    // Brand Kit
    //
    // The AI Brand Kit generator also takes a Mind picker (mind_ids) in
    // the request body. The acting user needs a plan whose
    // `max_brand_kits` cap is positive to pass the quantity gate, after
    // which BrandKitController::generate resolves the picked Minds through
    // the owner-only resolveMindsForUser() before retrieval.
    // ===================================================================

    private function planWithBrandKits(int $max = 5): Plan
    {
        return Plan::create([
            'name'          => 'BK ' . Str::random(4),
            'slug'          => 'bk-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => ['max_brand_kits' => $max],
        ]);
    }

    private function assignPlan(User $user, Plan $plan): User
    {
        $user->forceFill(['plan_id' => $plan->id])->save();
        return $user->fresh();
    }

    private function callBrandKit(User $user, array $mindIds)
    {
        return $this->actingAs($user->fresh())
            ->withSession(['active_workspace_id' => $this->personalWorkspace($user)->id])
            ->postJson(route('user.brand-kits.generate'), [
                'prompt'   => 'A modern studio for creators.',
                'mind_ids' => $mindIds,
            ]);
    }

    public function test_brand_kit_grounding_excludes_shared_mind_before_and_after_seat_suspension(): void
    {
        $owner  = $this->user();
        $member = $this->assignPlan($this->user(), $this->planWithBrandKits());
        $team   = $this->team($owner);
        $ms     = $this->memberOf($team, $member);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_WORKSPACE, $team->id);
        $ownMind = $this->mind($member);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureGroundingMinds();

        $this->assertTrue($shares->canUseMind($member->fresh(), $shared));

        $this->callBrandKit($member, [$ownMind->id, $shared->id]);
        $this->assertEquals(
            [$ownMind->id],
            $captured->lastIds(),
            'Brand Kit must ground only on the member\'s own Mind, never a shared one.'
        );

        $ms->forceFill(['suspended_at' => now()])->save();
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $shared->id,
        ]);
        $this->assertFalse($shares->canUseMind($member->fresh(), $shared));

        $this->callBrandKit($member, [$ownMind->id, $shared->id]);
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }

    public function test_brand_kit_grounding_excludes_shared_mind_before_and_after_badge_detach(): void
    {
        $owner  = $this->user();
        $holder = $this->assignPlan($this->user(), $this->planWithBrandKits());
        $badge  = $this->badge();
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_BADGE, $badge->id);
        $ownMind = $this->mind($holder);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureGroundingMinds();

        $this->assertTrue($shares->canUseMind($holder->fresh(), $shared));

        $this->callBrandKit($holder, [$ownMind->id, $shared->id]);
        $this->assertEquals([$ownMind->id], $captured->lastIds());

        $holder->accountBadges()->detach($badge->id);
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $shared->id,
            'audience_type' => AiResourceShare::AUDIENCE_BADGE,
            'audience_id'   => $badge->id,
        ]);
        $this->assertFalse($shares->canUseMind($holder->fresh(), $shared));

        $this->callBrandKit($holder, [$ownMind->id, $shared->id]);
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }

    // ===================================================================
    // AI Companion
    //
    // Companion carries its Mind selection on the THREAD (set at store
    // time), and CompanionController::send re-resolves those ids through
    // the owner-only resolveMindsForUser() every turn. We seed the thread
    // model directly with BOTH ids (bypassing the store-time sanitizer) so
    // the exclusion is proven at the send-time grounding boundary itself.
    // ===================================================================

    private function newCompanionThread(User $user, array $mindIds): CompanionThread
    {
        return CompanionThread::create([
            'user_id'          => $user->id,
            'workspace_id'     => $this->personalWorkspace($user)->id,
            'title'            => 'New conversation',
            'mind_ids'         => $mindIds,
            'include_platform' => false,
        ]);
    }

    private function callCompanion(User $user, CompanionThread $thread)
    {
        return $this->actingAs($user->fresh())
            ->withSession(['active_workspace_id' => $this->personalWorkspace($user)->id])
            ->post(route('user.ai.companion.send', $thread->id), [
                'message' => 'How are my links doing?',
            ]);
    }

    public function test_companion_grounding_excludes_shared_mind_before_and_after_seat_suspension(): void
    {
        $owner  = $this->user();
        $member = $this->user();
        $team   = $this->team($owner);
        $ms     = $this->memberOf($team, $member);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_WORKSPACE, $team->id);
        $ownMind = $this->mind($member);
        $thread  = $this->newCompanionThread($member, [$ownMind->id, $shared->id]);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureGroundingMinds();

        $this->assertTrue($shares->canUseMind($member->fresh(), $shared));

        $this->callCompanion($member, $thread);
        $this->assertEquals(
            [$ownMind->id],
            $captured->lastIds(),
            'Companion must ground only on the member\'s own Mind, never a shared one.'
        );

        $ms->forceFill(['suspended_at' => now()])->save();
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $shared->id,
        ]);
        $this->assertFalse($shares->canUseMind($member->fresh(), $shared));

        $this->callCompanion($member, $thread);
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }

    public function test_companion_grounding_excludes_shared_mind_before_and_after_badge_detach(): void
    {
        $owner  = $this->user();
        $holder = $this->user();
        $badge  = $this->badge();
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_BADGE, $badge->id);
        $ownMind = $this->mind($holder);
        $thread  = $this->newCompanionThread($holder, [$ownMind->id, $shared->id]);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureGroundingMinds();

        $this->assertTrue($shares->canUseMind($holder->fresh(), $shared));

        $this->callCompanion($holder, $thread);
        $this->assertEquals([$ownMind->id], $captured->lastIds());

        $holder->accountBadges()->detach($badge->id);
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $shared->id,
            'audience_type' => AiResourceShare::AUDIENCE_BADGE,
            'audience_id'   => $badge->id,
        ]);
        $this->assertFalse($shares->canUseMind($holder->fresh(), $shared));

        $this->callCompanion($holder, $thread);
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }

    // ===================================================================
    // Site Assistant runtime
    //
    // The Site Assistant never takes a Mind picker from the visitor — its
    // knowledgeMinds() resolves only the signed-in member's OWN Minds
    // (through the owner-only resolveMindsForUser()) plus admin-pinned
    // platform Minds. A Mind merely shared with the member can therefore
    // never enter grounding, before OR after their seat / badge is pulled.
    // We drive SiteAssistantRuntime::turn() directly as the member.
    // ===================================================================

    private function callSiteAssistant(User $member): array
    {
        return app(SiteAssistantRuntime::class)->turn(
            'sa_' . Str::random(28),
            $member->fresh(),
            'app',
            ['route' => 'dashboard', 'path' => '/user/dashboard'],
            'Tell me about my account.'
        );
    }

    public function test_site_assistant_grounding_excludes_shared_mind_before_and_after_seat_suspension(): void
    {
        $owner  = $this->user();
        $member = $this->user();
        $team   = $this->team($owner);
        $ms     = $this->memberOf($team, $member);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_WORKSPACE, $team->id);
        $ownMind = $this->mind($member);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureGroundingMinds();

        $this->assertTrue($shares->canUseMind($member->fresh(), $shared));

        $this->callSiteAssistant($member);
        $this->assertEquals(
            [$ownMind->id],
            $captured->lastIds(),
            'Site Assistant must ground only on the member\'s own Mind, never a shared one.'
        );

        $ms->forceFill(['suspended_at' => now()])->save();
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $shared->id,
        ]);
        $this->assertFalse($shares->canUseMind($member->fresh(), $shared));

        $this->callSiteAssistant($member);
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }

    public function test_site_assistant_grounding_excludes_shared_mind_before_and_after_badge_detach(): void
    {
        $owner  = $this->user();
        $holder = $this->user();
        $badge  = $this->badge();
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_BADGE, $badge->id);
        $ownMind = $this->mind($holder);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureGroundingMinds();

        $this->assertTrue($shares->canUseMind($holder->fresh(), $shared));

        $this->callSiteAssistant($holder);
        $this->assertEquals([$ownMind->id], $captured->lastIds());

        $holder->accountBadges()->detach($badge->id);
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $shared->id,
            'audience_type' => AiResourceShare::AUDIENCE_BADGE,
            'audience_id'   => $badge->id,
        ]);
        $this->assertFalse($shares->canUseMind($holder->fresh(), $shared));

        $this->callSiteAssistant($holder);
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }
}
