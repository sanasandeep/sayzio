<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiResourceShare;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindFeatureAdapter;
use App\Services\AI\AiMindQueryService;
use App\Services\AI\AiResourceShareService;
use App\Services\AI\OpenAiService;
use App\Services\Biolink\AiBiolinkBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Mobile (Sanctum /api/v1) parity for Task #2942's web grounding-revocation
 * proof (Tests\Feature\AiGroundingSharedMindRevocationTest): a Knowledge Base
 * merely SHARED with a teammate must never be folded into AI grounding through
 * the mobile app, before OR after the teammate's seat / badge is revoked.
 *
 * Scope note / deviation from the task brief: the brief named the mobile Ask
 * Coach send, Brand Kit generate, and Companion send endpoints as Mind-picker
 * surfaces. In the current code they are NOT — the mobile AskCoachController and
 * AiCompanionController never resolve KB grounding at all, and the mobile
 * BrandKitController::generate accepts only prompt/website_url/logo_url (no
 * mind_ids). The ONE mobile /api/v1 AI surface that accepts a user-supplied
 * Mind picker and grounds through the owner-only
 * AiMindQueryService::resolveMindsForUser() is the guided Link-in-Bio wizard's
 * AI auto-draft: POST /api/v1/links/wizard/ai-generate (ai_mind_ids /
 * include_platform_mind), via WizardAiDraftService::resolveGrounding(). That is
 * the real-world leak risk — a revoked teammate could literally name the
 * owner's shared Mind id in ai_mind_ids — so this is the surface pinned here.
 *
 * Finding (mirrors the web suite): resolveMindsForUser() is OWNER-ONLY. It only
 * ever returns Minds the asking user owns (plus the opt-in platform default); a
 * Mind merely shared via team seat or badge group is never resolvable, so it
 * can never reach retrieveContext() — a guarantee strictly stronger than
 * canUseMind, holding regardless of whether the teammate still has live USE
 * access. No production gate change was required; this test locks it in for the
 * mobile path.
 *
 * Each test proves the shared Mind is excluded from the grounding query (a)
 * WHILE the teammate still has live USE access — so the exclusion is the
 * owner-only grounding policy, not a missing share — and (b) AFTER seat
 * suspension / badge detach. The acting user's OWN Mind anchors the call so
 * retrieval still runs, letting us inspect exactly which Minds survived.
 * AiMindQueryService::retrieveContext, OpenAiService, and AiBiolinkBuilderService
 * are replaced with doubles so no embedding / chat / build network call is made.
 *
 * Authenticated with a REAL Sanctum bearer token (NOT Sanctum::actingAs, which
 * 500s under the TouchSessionToken middleware — see memory note
 * "Sanctum API feature tests").
 */
class ApiAiGroundingSharedMindRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The wizard's AI auto-draft 503s unless the engine is on.
        AiEngineSettings::setEnabled(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ===================================================================
    // Fixtures (mirroring the web suite)
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

    /** A user with generous link caps so the wizard's plan gate never trips. */
    private function actor(): User
    {
        $plan = Plan::create([
            'name'          => 'Wiz ' . Str::random(4),
            'slug'          => 'wiz-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => ['max_links' => 100, 'max_biolinks' => 100],
        ]);
        $u = $this->user();
        $u->forceFill(['plan_id' => $plan->id])->save();
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

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * Swap the runtime AI services for doubles:
     *   - AiMindQueryService is a PARTIAL mock so the real (owner-only)
     *     resolveMindsForUser() still runs, while retrieveContext() is
     *     captured and returns empty — proving exactly which Minds the
     *     grounding actually queried, with no embedding network call.
     *   - AiBiolinkBuilderService::generate is stubbed so the wizard's
     *     auto-draft completes without an OpenAI build/charge.
     *
     * @return object bucket exposing lastIds(): the Mind ids handed to the
     *                most recent retrieveContext() call.
     */
    private function captureWizardGroundingMinds(): object
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

        // The real builder would call OpenAI and charge coins; stub it so the
        // auto-draft path completes deterministically after grounding resolves.
        $builder = Mockery::mock(AiBiolinkBuilderService::class);
        $builder->shouldReceive('generate')->andReturn([
            'blocks'        => [],
            'credits_spent' => 0,
        ]);
        $this->app->instance(AiBiolinkBuilderService::class, $builder);

        return $bucket;
    }

    private function callAiWizard(User $member, array $mindIds)
    {
        return $this->withToken($this->token($member->fresh()))
            ->postJson('/api/v1/links/wizard/ai-generate', [
                'category'    => 'business',
                'page_type'   => 'local_shop',
                'answers'     => ['business_name' => 'Bob Bakes', 'address' => '1 Pastry Lane'],
                'ai_mind_ids' => $mindIds,
            ]);
    }

    // ===================================================================
    // AI biolink wizard auto-draft (the mobile Mind-picker surface)
    // ===================================================================

    public function test_ai_wizard_grounding_excludes_shared_mind_before_and_after_seat_suspension(): void
    {
        $owner  = $this->user();
        $member = $this->actor();
        $team   = $this->team($owner);
        $ms     = $this->memberOf($team, $member);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_WORKSPACE, $team->id);
        $ownMind = $this->mind($member);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureWizardGroundingMinds();

        // The teammate genuinely has LIVE USE access right now…
        $this->assertTrue($shares->canUseMind($member->fresh(), $shared));

        // …yet the wizard grounding still drops the shared Mind (owner-only),
        // querying only the member's own Mind even though they named both ids.
        $this->callAiWizard($member, [$ownMind->id, $shared->id])->assertCreated();
        $this->assertEquals(
            [$ownMind->id],
            $captured->lastIds(),
            'AI wizard must ground only on the member\'s own Mind, never a shared one.'
        );

        // Suspend the seat — share row untouched, live access lost.
        $ms->forceFill(['suspended_at' => now()])->save();
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $shared->id,
        ]);
        $this->assertFalse($shares->canUseMind($member->fresh(), $shared));

        // Still excluded after revocation — never folded in.
        $this->callAiWizard($member, [$ownMind->id, $shared->id])->assertCreated();
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }

    public function test_ai_wizard_grounding_excludes_shared_mind_before_and_after_badge_detach(): void
    {
        $owner  = $this->user();
        $holder = $this->actor();
        $badge  = $this->badge();
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $shared  = $this->mind($owner);
        $this->shareMind($owner, $shared, AiResourceShare::AUDIENCE_BADGE, $badge->id);
        $ownMind = $this->mind($holder);

        $shares   = app(AiResourceShareService::class);
        $captured = $this->captureWizardGroundingMinds();

        $this->assertTrue($shares->canUseMind($holder->fresh(), $shared));

        $this->callAiWizard($holder, [$ownMind->id, $shared->id])->assertCreated();
        $this->assertEquals([$ownMind->id], $captured->lastIds());

        $holder->accountBadges()->detach($badge->id);
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $shared->id,
            'audience_type' => AiResourceShare::AUDIENCE_BADGE,
            'audience_id'   => $badge->id,
        ]);
        $this->assertFalse($shares->canUseMind($holder->fresh(), $shared));

        $this->callAiWizard($holder, [$ownMind->id, $shared->id])->assertCreated();
        $this->assertEquals([$ownMind->id], $captured->lastIds());
    }
}
