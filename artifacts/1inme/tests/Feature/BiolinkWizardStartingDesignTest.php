<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the unified wizard's persona + "Pick a starting design" flow.
 *
 * The wizard was unified onto the PersonaCatalog taxonomy with a new starting-
 * design step. The mobile side has a source-driven guard; the web Blade ladder
 * + controller template layering had no equivalent automated coverage. These
 * tests lock in the deterministic layering done by BiolinkWizardGenerator:
 *
 *   1. With a chosen starting design (template_id), the template snapshot is
 *      seeded first (replace=true), the user's identity is merged into the
 *      template's first profile_card_v1 block (NOT a duplicate profile), the
 *      recipe's remaining content blocks are appended (biolink=[] so the
 *      template's page theme is preserved).
 *   2. "Start from scratch" (template_id=null) leaves the recipe path verbatim
 *      — no template marker block, theme from the recipe brand color.
 *   3. The API generate()/aiGenerate() `required_without:persona` branch: a
 *      `persona` resolves the (category, page_type) combo so neither is
 *      required; omitting all three fails validation.
 *
 * The API surface is authenticated with a REAL Sanctum bearer token (NOT
 * Sanctum::actingAs, which 500s under the TouchSessionToken middleware).
 */
class BiolinkWizardStartingDesignTest extends TestCase
{
    use RefreshDatabase;

    /** A distinctive theme color so we can prove the template's theme survives. */
    private const TEMPLATE_THEME_COLOR = '#123456';

    /** A distinctive block marker so we can prove the template was seeded. */
    private const TEMPLATE_HEADING_TEXT = 'TEMPLATE_MARKER_HEADING';

    private function plan(array $features = ['max_links' => 100, 'max_biolinks' => 100]): Plan
    {
        return Plan::create([
            'name'          => 'Test Plan',
            'slug'          => 'test-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => $features,
        ]);
    }

    private function makeUser(?Plan $plan = null): User
    {
        $user = User::create([
            'name'     => 'Wiz ' . Str::random(4),
            'email'    => 'wiz-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan?->id,
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function activeWorkspaceId(User $user): ?int
    {
        return app(WorkspaceContext::class)->resolve($user)?->id;
    }

    /** A complete, valid creator/influencer answer set. */
    private function creatorAnswers(): array
    {
        return [
            'display_name'   => 'Demo Creator',
            'headline'       => 'Stories, art, and good vibes',
            'bio'            => 'Sharing my creative journey.',
            'instagram'      => 'demo',
            'featured_url'   => 'https://example.com/sub',
            'featured_label' => 'Subscribe',
        ];
    }

    /**
     * Create an active starting-design template tagged for the creator persona,
     * carrying a placeholder profile card + a distinctive heading marker and a
     * distinctive page theme color. Deliberately contains NO cta_button so the
     * recipe's appended cta_button proves the recipe was layered on top. The
     * `creator` persona is recommended so it surfaces for that persona.
     */
    private function startingDesignTemplate(): PageTemplate
    {
        return PageTemplate::create([
            'name'                 => 'Marker Template',
            'slug'                 => 'marker-template-' . Str::random(6),
            'category'             => 'creator',
            'description'          => 'A starting design used to pin the layering.',
            'plan_tier'            => null, // open to all plans
            'recommended_personas' => ['creator'],
            'is_active'            => true,
            'sort_order'           => 0,
            'snapshot'             => [
                'biolink' => [
                    'theme_color'      => self::TEMPLATE_THEME_COLOR,
                    'background_type'  => 'solid',
                    'background_color' => '#101010',
                ],
                'blocks' => [
                    [
                        'type'     => 'profile_card_v1',
                        'settings' => [
                            'name'         => 'Template Placeholder Name',
                            'bio'          => 'Template placeholder bio.',
                            '_placeholder' => true,
                        ],
                        'is_active' => true,
                    ],
                    [
                        'type'      => 'heading',
                        'settings'  => ['text' => self::TEMPLATE_HEADING_TEXT],
                        'is_active' => true,
                    ],
                ],
            ],
        ]);
    }

    // ── Web finish(): persona + template_id → seeded then layered ──────

    /**
     * finish() with a persona-resolved draft + a chosen starting design must
     * seed the template (theme + blocks), personalise the template's profile
     * card with the user's answers (single profile, not a duplicate), and
     * append the recipe's remaining content blocks while preserving the
     * template's page theme.
     */
    public function test_web_finish_seeds_template_then_layers_wizard_answers(): void
    {
        $user = $this->makeUser($this->plan());
        $template = $this->startingDesignTemplate();

        $draft = BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'persona'       => 'creator',
            'persona_group' => 'Creators',
            'category'      => 'creator',
            'page_type'     => 'influencer',
            'industry'      => null,
            'template_id'   => $template->id,
            'step'          => 4,
            'answers'       => $this->creatorAnswers(),
        ]);

        $resp = $this->actingAs($user)->post('/user/links/wizard/finish');

        $link = Link::where('user_id', $user->id)->where('type', 'biolink')->first();
        $this->assertNotNull($link, 'finish() must create a biolink link');
        $resp->assertRedirect(route('user.links.blocks.editor', $link));

        // The single-shot draft is consumed once the page exists.
        $this->assertNull(BiolinkWizardDraft::find($draft->id));

        $blocks = BiolinkBlock::where('link_id', $link->id)->get();

        // The template was seeded — its distinctive heading survives.
        $headings = $blocks->where('type', 'heading')
            ->filter(fn ($b) => ($b->settings['text'] ?? null) === self::TEMPLATE_HEADING_TEXT);
        $this->assertCount(1, $headings,
            'the chosen template snapshot must be seeded onto the link');

        // Exactly ONE profile card — the template's, personalised with the
        // user's identity rather than a duplicated recipe profile.
        $profiles = $blocks->where('type', 'profile_card_v1')->values();
        $this->assertCount(1, $profiles,
            'identity must be merged into the template profile, not duplicated');
        $profile = $profiles[0]->settings ?? [];
        $this->assertSame('Demo Creator', $profile['name'] ?? null,
            'the template profile card must be personalised with the wizard answer');
        $this->assertArrayNotHasKey('_placeholder', $profile,
            'merging real identity must clear the placeholder flag');

        // The recipe content was layered on top — its cta_button (absent from
        // the template) appears beneath the seeded design.
        $this->assertTrue($blocks->contains('type', 'cta_button'),
            'the recipe content blocks must be appended beneath the template design');

        // The template's page-level theme is preserved (recipe append passes
        // biolink=[] so it never overwrites the design theme).
        $link->refresh();
        $this->assertSame(self::TEMPLATE_THEME_COLOR,
            $link->settings['biolink']['theme_color'] ?? null,
            'the template page theme must survive the recipe layering');
    }

    // ── Web finish(): start from scratch (template_id=null) ───────────

    /**
     * With template_id=null ("Start from scratch") the recipe path is unchanged
     * — the page is built from the recipe verbatim: no template marker block,
     * and the theme comes from the recipe brand color, not a template.
     */
    public function test_web_finish_start_from_scratch_uses_recipe_verbatim(): void
    {
        $user = $this->makeUser($this->plan());
        // A template exists but is NOT chosen — must not leak into the page.
        $this->startingDesignTemplate();

        $draft = BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'persona'       => 'creator',
            'persona_group' => 'Creators',
            'category'      => 'creator',
            'page_type'     => 'influencer',
            'industry'      => null,
            'template_id'   => null,
            'step'          => 4,
            'answers'       => $this->creatorAnswers(),
        ]);

        $resp = $this->actingAs($user)->post('/user/links/wizard/finish');

        $link = Link::where('user_id', $user->id)->where('type', 'biolink')->first();
        $this->assertNotNull($link);
        $resp->assertRedirect(route('user.links.blocks.editor', $link));

        $blocks = BiolinkBlock::where('link_id', $link->id)->get();

        // No template marker — the recipe ran on its own.
        $this->assertFalse(
            $blocks->contains(fn ($b) => ($b->settings['text'] ?? null) === self::TEMPLATE_HEADING_TEXT),
            'start-from-scratch must not seed any template block',
        );

        // The recipe still produced the populated profile + cta.
        $profiles = $blocks->where('type', 'profile_card_v1')->values();
        $this->assertCount(1, $profiles);
        $this->assertSame('Demo Creator', $profiles[0]->settings['name'] ?? null);
        $this->assertTrue($blocks->contains('type', 'cta_button'));

        // The theme is the recipe brand color, NOT the (unchosen) template's.
        $link->refresh();
        $this->assertNotSame(self::TEMPLATE_THEME_COLOR,
            $link->settings['biolink']['theme_color'] ?? null,
            'scratch must use the recipe theme, never an unchosen template theme');
    }

    // ── API generate(): persona + template_id parity ──────────────────

    /**
     * API parity: generate() driven by a `persona` (no explicit category/
     * page_type) + a template_id seeds the template then layers the recipe,
     * exactly like the web finish() path.
     */
    public function test_api_generate_with_persona_and_template_seeds_then_layers(): void
    {
        $user = $this->makeUser($this->plan());
        $template = $this->startingDesignTemplate();
        $this->withToken($this->token($user));

        $resp = $this->postJson('/api/v1/links/wizard/generate', [
            'persona'     => 'creator',
            'template_id' => $template->id,
            'answers'     => $this->creatorAnswers(),
        ]);

        $resp->assertStatus(201);
        $resp->assertJsonPath('data.link.type', 'biolink');
        $resp->assertJsonPath('data.link.title', 'Demo Creator');

        $link = Link::where('user_id', $user->id)->where('type', 'biolink')->sole();
        $blocks = BiolinkBlock::where('link_id', $link->id)->get();

        // Template seeded (heading marker), single personalised profile, recipe
        // cta appended, template theme preserved.
        $this->assertTrue(
            $blocks->contains(fn ($b) => ($b->settings['text'] ?? null) === self::TEMPLATE_HEADING_TEXT),
            'template must be seeded via the persona-driven API path',
        );
        $profiles = $blocks->where('type', 'profile_card_v1')->values();
        $this->assertCount(1, $profiles);
        $this->assertSame('Demo Creator', $profiles[0]->settings['name'] ?? null);
        $this->assertTrue($blocks->contains('type', 'cta_button'));

        $this->assertSame(self::TEMPLATE_THEME_COLOR,
            $link->settings['biolink']['theme_color'] ?? null);
    }

    // ── API generate(): required_without:persona validation branch ────

    /**
     * A `persona` resolves the (category, page_type) combo, so neither is
     * required on the request — generate() succeeds with a persona alone.
     */
    public function test_api_generate_accepts_persona_without_category(): void
    {
        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->postJson('/api/v1/links/wizard/generate', [
            'persona' => 'creator', // resolves to creator/influencer
            'answers' => $this->creatorAnswers(),
        ]);

        $resp->assertStatus(201);
        $resp->assertJsonPath('data.link.type', 'biolink');
        $this->assertSame(1, Link::where('user_id', $user->id)->where('type', 'biolink')->count());
    }

    /**
     * Omitting persona AND category/page_type trips the `required_without`
     * rules — a 422 validation_failed envelope with both fields in `details`,
     * and nothing is created.
     */
    public function test_api_generate_requires_category_without_persona(): void
    {
        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->postJson('/api/v1/links/wizard/generate', [
            // no persona, no category, no page_type
            'answers' => $this->creatorAnswers(),
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
        $resp->assertJsonPath('error.details.category', fn ($m) => is_array($m) || is_string($m));
        $resp->assertJsonPath('error.details.page_type', fn ($m) => is_array($m) || is_string($m));
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
    }

    // ── API aiGenerate(): required_without:persona validation branch ──

    /**
     * aiGenerate() runs the same `required_without:persona` validation first.
     * Omitting persona + category/page_type fails validation (422) before the
     * AI-engine gate is even consulted.
     */
    public function test_api_ai_generate_requires_category_without_persona(): void
    {
        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->postJson('/api/v1/links/wizard/ai-generate', [
            'answers' => $this->creatorAnswers(),
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
        $resp->assertJsonPath('error.details.category', fn ($m) => is_array($m) || is_string($m));
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
    }

    /**
     * A `persona` satisfies the required_without rule, so validation passes and
     * the flow advances to the AI-engine gate — which is OFF by default in
     * tests, surfacing 503 ai_unavailable (proving the persona resolved rather
     * than tripping validation).
     */
    public function test_api_ai_generate_accepts_persona_then_hits_engine_gate(): void
    {
        $this->assertFalse(\App\Services\AI\AiEngineSettings::isEnabled(),
            'AI engine should be OFF by default in tests');

        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->postJson('/api/v1/links/wizard/ai-generate', [
            'persona' => 'creator',
            'answers' => $this->creatorAnswers(),
        ]);

        $resp->assertStatus(503);
        $resp->assertJsonPath('error.code', 'ai_unavailable');
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
    }
}
