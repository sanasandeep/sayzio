<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\OpenAiService;
use App\Services\AI\PersonaRuntime;
use App\Services\Biolink\AiBiolinkBuilderService;
use App\Services\Brand\AiBrandKitService;
use App\Services\Brand\BrandConsistencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Coverage for AI Branding Specials (Task #2664), built atop the AI Brand Kit
 * (#2662). Two features under test, both verified with the AI engine OFF —
 * the assertions check WIRING, not live generation:
 *
 *   1. Brand Consistency Score — a pure transformer that audits a creator's
 *      biolinks against their saved Brand Kit. A page that had the kit applied
 *      scores 100; an untouched page surfaces plain-English findings with a
 *      one-click apply-fix that round-trips back to 100.
 *   2. On-Brand AI Everywhere — the saved kit's voice/palette directives are
 *      injected into the biolink builder prompt and the AI Companion system
 *      prompt, honoring the per-request / per-persona opt-out and the
 *      legacy-safe `brand_consistency` plan gate.
 */
class AiBrandingSpecialsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function plan(): Plan
    {
        return Plan::create([
            'name'          => 'Branding Plan',
            'slug'          => 'branding-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => [
                'max_links'      => 100,
                'max_biolinks'   => 100,
                'max_brand_kits' => 5,
            ],
        ]);
    }

    private function makeUser(): User
    {
        $user = User::create([
            'name'     => 'Brand ' . Str::random(4),
            'email'    => 'brand-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
            'plan_id'  => $this->plan()->id,
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function biolink(User $user): Link
    {
        return Link::create([
            'user_id'      => $user->id,
            'workspace_id' => app(WorkspaceContext::class)->resolve($user)?->id,
            'type'         => 'biolink',
            'alias'        => Str::random(8),
            'title'        => 'My page',
            'is_active'    => true,
        ]);
    }

    private function kitFor(User $user): BrandKit
    {
        return BrandKit::create([
            'user_id'    => $user->id,
            'name'       => 'Aurora Studio',
            'slug'       => 'aurora-' . Str::random(6),
            'is_default' => true,
            'config'     => [
                'palette' => [
                    'primary'   => '#3B5BDB',
                    'secondary' => '#5C7CFA',
                    'accent'    => '#F783AC',
                    'neutrals'  => ['#F8F9FA', '#212529'],
                ],
                'fonts'       => ['heading' => 'Poppins', 'body' => 'Inter'],
                'voice'       => ['tone' => 'Warm and confident', 'descriptors' => ['friendly', 'premium']],
                'taglines'    => ['Shine brighter', 'Your brand, elevated'],
                'bio'         => 'A modern studio helping creators look the part.',
                'block_theme' => 'minimal',
            ],
        ]);
    }

    // ── Brand Consistency Score ────────────────────────────────────────

    public function test_audit_untouched_biolink_produces_findings(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);
        $link = $this->biolink($user);

        $audit = app(BrandConsistencyService::class)
            ->audit($kit, collect([$link->fresh()]));

        // Nothing on the page matches the kit ⇒ off-brand, with a finding.
        $this->assertLessThan(100, $audit['score']);
        $this->assertSame(1, $audit['links_total']);
        $this->assertSame(0, $audit['links_on_brand']);
        $this->assertNotEmpty($audit['findings']);

        $finding = $audit['findings'][0];
        $this->assertSame($link->id, $finding['link_id']);
        $this->assertStringContainsString('on-brand', $finding['headline']);
        $this->assertNotEmpty($finding['reason']);
        // The apply-fix reuses the existing brand-kit apply route.
        $this->assertSame(
            route('user.brand-kits.apply.biolink', [$kit->id, $link->id]),
            $finding['apply_url'],
        );
    }

    public function test_applied_biolink_scores_100(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);
        $link = $this->biolink($user);

        // Applying the kit is exactly what the audit measures against.
        app(AiBrandKitService::class)->applyToBiolink($kit, $link->fresh());

        $audit = app(BrandConsistencyService::class)
            ->audit($kit, collect([$link->fresh()]));

        $this->assertSame(100, $audit['score']);
        $this->assertSame(1, $audit['links_on_brand']);
        $this->assertEmpty($audit['findings']);
    }

    public function test_apply_fix_brings_a_finding_back_to_on_brand(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);
        $link = $this->biolink($user);

        $before = app(BrandConsistencyService::class)
            ->audit($kit, collect([$link->fresh()]));
        $this->assertNotEmpty($before['findings']);

        // The one-click "apply fix" path.
        app(AiBrandKitService::class)->applyToBiolink($kit, $link->fresh());

        $after = app(BrandConsistencyService::class)
            ->audit($kit, collect([$link->fresh()]));
        $this->assertSame(100, $after['score']);
    }

    public function test_sparse_kit_never_produces_phantom_findings(): void
    {
        $user = $this->makeUser();
        // A kit that defines nothing the audit checks.
        $kit  = BrandKit::create([
            'user_id'    => $user->id,
            'name'       => 'Empty Kit',
            'slug'       => 'empty-' . Str::random(6),
            'is_default' => true,
            'config'     => ['voice' => ['tone' => 'Calm']],
        ]);
        $link = $this->biolink($user);

        $audit = app(BrandConsistencyService::class)
            ->audit($kit, collect([$link->fresh()]));

        $this->assertSame(100, $audit['score']);
        $this->assertEmpty($audit['findings']);
    }

    // ── promptDirectives content ───────────────────────────────────────

    public function test_prompt_directives_carry_voice_and_optionally_colors(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);

        $withColors = $kit->promptDirectives(true);
        $this->assertStringContainsString('Warm and confident', $withColors);
        $this->assertStringContainsString('#3B5BDB', $withColors);
        $this->assertStringContainsString('Poppins', $withColors);

        $noColors = $kit->promptDirectives(false);
        $this->assertStringContainsString('Warm and confident', $noColors);
        $this->assertStringNotContainsString('#3B5BDB', $noColors);
        $this->assertStringNotContainsString('Poppins', $noColors);

        // Default-on plan gate for the feature (legacy-safe).
        $this->assertTrue(AiPlanAccess::featureAllowed($user, 'brand_consistency'));
    }

    // ── On-Brand AI: biolink builder ───────────────────────────────────

    public function test_builder_messages_inject_brand_directives_when_provided(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);
        $builder = app(AiBiolinkBuilderService::class);

        $directives = $kit->promptDirectives(true);
        $messages = $builder->buildMessages(
            $user,
            'A landing page for my studio.',
            [], [], [], '', $directives,
        );

        $joined = collect($messages)->pluck('content')->implode("\n");
        $this->assertStringContainsString('Warm and confident', $joined);

        // Without directives the prompt is unchanged for everyone else.
        $plain = $builder->buildMessages(
            $user,
            'A landing page for my studio.',
            [], [], [], '', '',
        );
        $joinedPlain = collect($plain)->pluck('content')->implode("\n");
        $this->assertStringNotContainsString('Warm and confident', $joinedPlain);
    }

    // ── On-Brand AI: AI Companion (PersonaRuntime) ─────────────────────

    private function persona(User $user, bool $useBrandKit): AiPersonaAgent
    {
        return AiPersonaAgent::create([
            'user_id'          => $user->id,
            'slug'             => 'p-' . Str::random(6),
            'name'             => 'Helper',
            'system_prompt'    => 'You help visitors.',
            'use_brand_kit'    => $useBrandKit,
            'model'            => 'gpt-4o-mini',
            'temperature_x100' => 50,
            'max_tokens'       => 300,
            'languages'        => [],
            'allowed_actions'  => [],
            'fallback_behavior'=> 'clarify',
            'use_default_mind' => false,
            'is_disabled'      => false,
        ]);
    }

    private function bindChatCapturingSystemPrompt(): void
    {
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')->andReturnUsing(function ($user, $model, $messages, $opts = []) {
            return [
                'content'       => 'ok',
                'tool_calls'    => [],
                'finish_reason' => 'stop',
                'tokens_in'     => 0,
                'tokens_out'    => 0,
                'credits_spent' => 0,
                'model'         => $model,
                'raw'           => [],
            ];
        });
        $this->app->instance(OpenAiService::class, $mock);
    }

    public function test_companion_system_prompt_includes_brand_directives_when_opted_in(): void
    {
        $user = $this->makeUser();
        $this->kitFor($user);
        $persona = $this->persona($user, true);

        $this->bindChatCapturingSystemPrompt();

        $result = app(PersonaRuntime::class)->turn($user, $persona, [], 'Hello');

        $this->assertStringContainsString('Warm and confident', $result['system_prompt']);
        // Colors are moot in chat ⇒ excluded.
        $this->assertStringNotContainsString('Poppins', $result['system_prompt']);
    }

    public function test_companion_system_prompt_omits_brand_directives_when_opted_out(): void
    {
        $user = $this->makeUser();
        $this->kitFor($user);
        $persona = $this->persona($user, false);

        $this->bindChatCapturingSystemPrompt();

        $result = app(PersonaRuntime::class)->turn($user, $persona, [], 'Hello');

        $this->assertStringNotContainsString('Warm and confident', $result['system_prompt']);
    }
}
