<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Happy-path coverage for the wizard's *instant* (non-AI) generate.
 *
 * The validation suite (BiolinkWizardValidationTest) pins that bad input is
 * blocked; the recipe/template suites pin that BiolinkPageRecipes →
 * TemplateService paints populated blocks. What had no direct coverage is the
 * full request → Link round-trip on the happy path: that the API generate() and
 * web finish() endpoints, given a *complete, valid* answer set, actually create
 * a biolink Link with populated blocks reflecting the user's real answers — and
 * that the plan link/biolink caps return the correct plan-gate.
 *
 * The API surface is authenticated with a REAL Sanctum bearer token (NOT
 * Sanctum::actingAs, which mocks the access token and 500s under the
 * TouchSessionToken middleware).
 */
class BiolinkWizardGenerateTest extends TestCase
{
    use RefreshDatabase;

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
        return User::factory()->create([
            'plan_id' => $plan?->id,
        ])->fresh();
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
            'display_name' => 'Demo Creator',
            'headline'     => 'Stories, art, and good vibes',
            'bio'          => 'Sharing my creative journey.',
            'instagram'    => 'demo',
            'featured_url'   => 'https://example.com/sub',
            'featured_label' => 'Subscribe',
        ];
    }

    // ── API generate(): happy path ────────────────────────────────────

    /**
     * POST /api/v1/links/wizard/generate with a complete valid answer set
     * returns 201 with a biolink link payload AND persists a populated biolink
     * Link whose blocks reflect the user's actual answers (not placeholders).
     */
    public function test_api_generate_creates_populated_biolink_link(): void
    {
        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->postJson('/api/v1/links/wizard/generate', [
            'category'  => 'creator',
            'page_type' => 'influencer',
            'answers'   => $this->creatorAnswers(),
        ]);

        $resp->assertStatus(201);
        $resp->assertJsonPath('data.link.type', 'biolink');
        $linkId = $resp->json('data.link.id');
        $this->assertNotNull($linkId);
        // Title is derived from the answers (display_name), not a placeholder.
        $resp->assertJsonPath('data.link.title', 'Demo Creator');

        // The Link really exists for this user and is a biolink.
        $link = Link::find($linkId);
        $this->assertNotNull($link);
        $this->assertSame($user->id, $link->user_id);
        $this->assertSame('biolink', $link->type);
        $this->assertTrue((bool) $link->is_active);

        // Blocks were painted from the recipe with the user's real answers.
        $blocks = BiolinkBlock::where('link_id', $link->id)->get();
        $this->assertNotEmpty($blocks, 'generate() should paint blocks onto the link');

        $byType = $blocks->keyBy('type');
        $this->assertTrue($byType->has('profile_card_v1'), 'expected a profile card block');

        $profile = $byType['profile_card_v1']->settings ?? [];
        $this->assertSame('Demo Creator', $profile['name'] ?? null);
        $this->assertNotSame('Your Name', $profile['name'] ?? null,
            'profile name must be the answer, not the placeholder default');
    }

    // ── Web finish(): happy path ──────────────────────────────────────

    /**
     * POST /user/links/wizard/finish with a complete draft redirects to the
     * block editor, creates a populated biolink Link, and discards the draft.
     */
    public function test_web_finish_redirects_to_editor_and_discards_draft(): void
    {
        $user = $this->makeUser($this->plan());

        $draft = BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'category'      => 'creator',
            'page_type'     => 'influencer',
            'industry'      => null,
            'step'          => 4,
            'answers'       => $this->creatorAnswers(),
        ]);

        $resp = $this->actingAs($user)->post('/user/links/wizard/finish');

        $resp->assertRedirect();
        // Redirect lands on the block editor for the freshly created link.
        $link = Link::where('user_id', $user->id)->where('type', 'biolink')->first();
        $this->assertNotNull($link, 'finish() must create a biolink link');
        $resp->assertRedirect(route('user.links.blocks.editor', $link));

        // The single-shot draft is discarded once the page exists.
        $this->assertNull(BiolinkWizardDraft::find($draft->id),
            'finish() must delete the draft after generating the page');

        // The generated page is populated, not empty.
        $blocks = BiolinkBlock::where('link_id', $link->id)->get();
        $this->assertNotEmpty($blocks, 'finish() should paint blocks onto the link');
        $profile = $blocks->keyBy('type')['profile_card_v1']->settings ?? [];
        $this->assertSame('Demo Creator', $profile['name'] ?? null);
    }

    // ── Custom alias carried into the wizard ──────────────────────────

    /**
     * A valid custom alias passed to the wizard index (carried through from the
     * Create Link page hero) is stashed on the draft and used as the created
     * link's alias on finish() — instead of an auto-generated one.
     */
    public function test_custom_alias_carries_through_wizard_to_link(): void
    {
        $user = $this->makeUser($this->plan());

        // Land on the wizard with a typed custom alias.
        $this->actingAs($user)->get('/user/links/wizard?alias=my-custom-link')
            ->assertOk();

        // The alias is stashed on the auto-created draft.
        $draft = BiolinkWizardDraft::where('actor_user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($draft);
        $this->assertSame('my-custom-link', $draft->alias);

        // Complete the draft and finish — the link should use the custom alias.
        $draft->update([
            'category'  => 'creator',
            'page_type' => 'influencer',
            'step'      => 4,
            'answers'   => $this->creatorAnswers(),
        ]);

        $this->actingAs($user)->post('/user/links/wizard/finish')->assertRedirect();

        $link = Link::where('user_id', $user->id)->where('type', 'biolink')->first();
        $this->assertNotNull($link);
        $this->assertSame('my-custom-link', $link->alias);
    }

    /**
     * An already-taken alias never reaches the wizard: index() bounces back to
     * the Create Link page with a validation error and stashes nothing.
     */
    public function test_taken_alias_redirects_back_to_create_with_error(): void
    {
        $user = $this->makeUser($this->plan());

        Link::create([
            'user_id'   => $user->id,
            'type'      => 'short',
            'alias'     => 'already-taken',
            'long_url'  => 'https://example.com',
            'is_active' => true,
        ]);

        $resp = $this->actingAs($user)->get('/user/links/wizard?alias=already-taken');
        $resp->assertRedirect(route('user.links.create'));
        $resp->assertSessionHasErrors('alias');

        $this->assertNull(BiolinkWizardDraft::where('actor_user_id', $user->id)->first(),
            'an invalid alias must not create a draft');
    }

    /**
     * No alias param → the wizard auto-generates as before (regression guard).
     */
    public function test_blank_alias_auto_generates(): void
    {
        $user = $this->makeUser($this->plan());

        $draft = BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'category'      => 'creator',
            'page_type'     => 'influencer',
            'step'          => 4,
            'answers'       => $this->creatorAnswers(),
        ]);
        $this->assertNull($draft->alias);

        $this->actingAs($user)->post('/user/links/wizard/finish')->assertRedirect();

        $link = Link::where('user_id', $user->id)->where('type', 'biolink')->first();
        $this->assertNotNull($link);
        $this->assertNotEmpty($link->alias, 'a blank alias must still auto-generate');
    }

    // ── API custom alias passthrough ──────────────────────────────────

    /**
     * A valid custom alias posted to the API wizard generate() is used verbatim
     * as the created link's alias (mobile parity with the web Create Link flow),
     * instead of an auto-generated one.
     */
    public function test_api_generate_uses_custom_alias(): void
    {
        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->postJson('/api/v1/links/wizard/generate', [
            'category'  => 'creator',
            'page_type' => 'influencer',
            'alias'     => 'my-mobile-link',
            'answers'   => $this->creatorAnswers(),
        ]);

        $resp->assertStatus(201);
        $resp->assertJsonPath('data.link.alias', 'my-mobile-link');

        $link = Link::find($resp->json('data.link.id'));
        $this->assertSame('my-mobile-link', $link->alias);
    }

    /**
     * A blank/absent alias on the API generate() still auto-generates one
     * (regression guard — the original behaviour is unchanged).
     */
    public function test_api_generate_blank_alias_auto_generates(): void
    {
        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->postJson('/api/v1/links/wizard/generate', [
            'category'  => 'creator',
            'page_type' => 'influencer',
            'answers'   => $this->creatorAnswers(),
        ]);

        $resp->assertStatus(201);
        $link = Link::find($resp->json('data.link.id'));
        $this->assertNotEmpty($link->alias, 'a blank alias must still auto-generate');
    }

    /**
     * An already-taken custom alias is rejected with a 422 `invalid_alias`
     * envelope (with an `alias` field error) and creates no biolink — the same
     * unique guard the web flow enforces, surfaced as JSON for the mobile client.
     */
    public function test_api_generate_rejects_taken_alias(): void
    {
        $user = $this->makeUser($this->plan());

        Link::create([
            'user_id'   => $user->id,
            'type'      => 'short',
            'alias'     => 'already-taken',
            'long_url'  => 'https://example.com',
            'is_active' => true,
        ]);

        $this->withToken($this->token($user));
        $resp = $this->postJson('/api/v1/links/wizard/generate', [
            'category'  => 'creator',
            'page_type' => 'influencer',
            'alias'     => 'already-taken',
            'answers'   => $this->creatorAnswers(),
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'invalid_alias');
        $resp->assertJsonStructure(['error' => ['details' => ['alias']]]);
        // Nothing new was created — only the pre-existing short link survives.
        $this->assertSame(0, Link::where('user_id', $user->id)->where('type', 'biolink')->count());
    }

    // ── API live alias availability ───────────────────────────────────

    /**
     * GET /api/v1/links/wizard/alias-availability reports a free custom URL as
     * available so the mobile basics step can show an inline verdict before the
     * user reaches Generate.
     */
    public function test_api_alias_availability_reports_available(): void
    {
        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->getJson('/api/v1/links/wizard/alias-availability?alias=fresh-handle');

        $resp->assertStatus(200);
        $resp->assertJsonPath('data.status', 'available');
        $resp->assertJsonPath('data.available', true);
    }

    /**
     * An already-used alias is reported as taken — mirroring the unique guard the
     * generate() endpoint enforces, so the user fixes it before Generate.
     */
    public function test_api_alias_availability_reports_taken(): void
    {
        $user = $this->makeUser($this->plan());

        Link::create([
            'user_id'   => $user->id,
            'type'      => 'short',
            'alias'     => 'already-taken',
            'long_url'  => 'https://example.com',
            'is_active' => true,
        ]);

        $this->withToken($this->token($user));
        $resp = $this->getJson('/api/v1/links/wizard/alias-availability?alias=already-taken');

        $resp->assertStatus(200);
        $resp->assertJsonPath('data.status', 'taken');
        $resp->assertJsonPath('data.available', false);
    }

    /**
     * A malformed alias (illegal characters) is reported as invalid, surfacing
     * the same alpha_dash/length/banned rules generate() applies.
     */
    public function test_api_alias_availability_reports_invalid(): void
    {
        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->getJson('/api/v1/links/wizard/alias-availability?alias=' . urlencode('no spaces!'));

        $resp->assertStatus(200);
        $resp->assertJsonPath('data.status', 'invalid');
        $resp->assertJsonPath('data.available', false);
    }

    /**
     * A blank alias reports the empty status (auto-generate) without error.
     */
    public function test_api_alias_availability_blank_is_empty(): void
    {
        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->getJson('/api/v1/links/wizard/alias-availability?alias=');

        $resp->assertStatus(200);
        $resp->assertJsonPath('data.status', 'empty');
        $resp->assertJsonPath('data.available', false);
    }

    // ── Plan caps: API ────────────────────────────────────────────────

    /**
     * Hitting the plan's max_links cap returns 403 with code `link_limit`
     * (the plan-gate) and creates nothing.
     */
    public function test_api_generate_hits_link_limit(): void
    {
        $user = $this->makeUser($this->plan(['max_links' => 1, 'max_biolinks' => 100]));

        // Consume the single allowed link slot with a plain short link.
        Link::create([
            'user_id'   => $user->id,
            'type'      => 'short',
            'alias'     => Link::generateAlias(),
            'long_url'  => 'https://example.com',
            'is_active' => true,
        ]);

        $this->withToken($this->token($user));
        $resp = $this->postJson('/api/v1/links/wizard/generate', [
            'category'  => 'creator',
            'page_type' => 'influencer',
            'answers'   => $this->creatorAnswers(),
        ]);

        $resp->assertStatus(403);
        $resp->assertJsonPath('error.code', 'link_limit');
        // Only the pre-existing short link survives — nothing new was made.
        $this->assertSame(0, Link::where('user_id', $user->id)->where('type', 'biolink')->count());
    }

    /**
     * Hitting the plan's max_biolinks cap (while still under max_links)
     * returns 403 with code `biolink_limit`.
     */
    public function test_api_generate_hits_biolink_limit(): void
    {
        $user = $this->makeUser($this->plan(['max_links' => 100, 'max_biolinks' => 1]));

        // Consume the single allowed biolink slot.
        Link::create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'Existing Bio',
            'is_active' => true,
        ]);

        $this->withToken($this->token($user));
        $resp = $this->postJson('/api/v1/links/wizard/generate', [
            'category'  => 'creator',
            'page_type' => 'influencer',
            'answers'   => $this->creatorAnswers(),
        ]);

        $resp->assertStatus(403);
        $resp->assertJsonPath('error.code', 'biolink_limit');
        // No second biolink was created.
        $this->assertSame(1, Link::where('user_id', $user->id)->where('type', 'biolink')->count());
    }

    // ── Plan caps: web ────────────────────────────────────────────────

    /**
     * The web finish() redirects to the upgrade page (with a flashed error)
     * when the biolink cap is hit, and generates nothing.
     */
    public function test_web_finish_redirects_to_upgrade_on_biolink_cap(): void
    {
        $user = $this->makeUser($this->plan(['max_links' => 100, 'max_biolinks' => 1]));

        // Consume the single allowed biolink slot.
        Link::create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'Existing Bio',
            'is_active' => true,
        ]);

        $draft = BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'category'      => 'creator',
            'page_type'     => 'influencer',
            'industry'      => null,
            'step'          => 4,
            'answers'       => $this->creatorAnswers(),
        ]);

        $resp = $this->actingAs($user)->post('/user/links/wizard/finish');

        $resp->assertRedirect(route('user.upgrade'));
        $resp->assertSessionHas('error');
        // Cap hit before generation — still only the one pre-existing biolink,
        // and the draft survives so the user can retry after upgrading.
        // (Scope-free count: the in-process web request bound a current
        // workspace, which would otherwise filter the workspace-less fixture.)
        $this->assertSame(1, Link::withoutGlobalScope('workspace')->where('user_id', $user->id)->where('type', 'biolink')->count());
        $this->assertNotNull(BiolinkWizardDraft::find($draft->id));
    }
}
