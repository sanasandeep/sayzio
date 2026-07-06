<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\BiolinkWizardQuestions;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use App\Services\Biolink\AiBiolinkBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Pre-release regression coverage for the redesigned 4-step guided wizard.
 *
 * The wizard gained per-step validation (inline errors that block advancing),
 * an AI auto-draft path, and the AI-resource picker — across both the web flow
 * and the mobile Sanctum API. These tests pin the behaviours that have no other
 * automated coverage:
 *
 *   1. validateAnswers() flags missing required fields and bad-typed values.
 *   2. The web finish()/finishAi() gate populates the `wizard` error bag.
 *   3. The API generate()/aiGenerate() gate returns a 422 `validation_failed`
 *      envelope with a per-field `details` map.
 *   4. The AI auto-draft path is correctly gated when the AI engine is OFF
 *      (the default in dev) — web redirects with an error, API returns 503
 *      `ai_unavailable` — and the gate fires BEFORE any answer validation.
 *   5. The resources endpoint returns minds / files / ai_enabled.
 *
 * The API surface is authenticated with a REAL Sanctum bearer token:
 * `Sanctum::actingAs` injects a mock current-access-token that the
 * TouchSessionToken middleware then ->save()s, 500-ing every request.
 */
class BiolinkWizardValidationTest extends TestCase
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
            'name'          => 'Test Plan',
            'slug'          => 'test-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => [
                'max_links'    => 100,
                'max_biolinks' => 100,
            ],
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

    // ── 1. validateAnswers() service contract ────────────────────────

    /**
     * business/local_shop requires business_name + address. A name-only
     * answer set must flag the missing required field but NOT the satisfied
     * one, and the message must reference the field's human label.
     */
    public function test_validate_answers_flags_missing_required_fields(): void
    {
        $errors = BiolinkWizardQuestions::validateAnswers(
            'business', 'local_shop', null,
            ['business_name' => 'Bob Bakes'], // address missing
        );

        $this->assertArrayHasKey('address', $errors);
        $this->assertArrayNotHasKey('business_name', $errors);
        $this->assertStringContainsString('required', strtolower($errors['address']));
    }

    /** A complete + well-typed answer set yields zero errors. */
    public function test_validate_answers_passes_when_required_present(): void
    {
        $errors = BiolinkWizardQuestions::validateAnswers(
            'business', 'local_shop', null,
            ['business_name' => 'Bob Bakes', 'address' => '1 Pastry Lane'],
        );

        $this->assertSame([], $errors);
    }

    /** A present-but-malformed typed value (bad URL) is flagged too. */
    public function test_validate_answers_flags_bad_typed_value(): void
    {
        // business/online_store requires store_name (text) + store_url (url).
        $errors = BiolinkWizardQuestions::validateAnswers(
            'business', 'online_store', null,
            ['store_name' => 'Shop', 'store_url' => 'not a url with spaces'],
        );

        $this->assertArrayHasKey('store_url', $errors);
        $this->assertStringContainsString('url', strtolower($errors['store_url']));
    }

    // ── 2. Web finish(): inline 'wizard' error bag ───────────────────

    /**
     * Web finish() must bounce back with the per-field errors in the named
     * `wizard` error bag (so the Blade view can render them inline) instead
     * of generating a page from incomplete answers.
     */
    public function test_web_finish_blocks_on_missing_required_with_wizard_error_bag(): void
    {
        $user = $this->makeUser($this->plan());

        BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'category'      => 'business',
            'page_type'     => 'local_shop',
            'industry'      => null,
            'step'          => 4,
            'answers'       => ['business_name' => 'Bob Bakes'], // address missing
        ]);

        $resp = $this->actingAs($user)->post('/user/links/wizard/finish');

        $resp->assertRedirect();
        // Errors land in the 'wizard' bag, keyed by the missing field.
        $resp->assertSessionHasErrors(['address'], null, 'wizard');
        // Nothing should have been generated.
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
    }

    // ── 3. API generate(): 422 validation_failed + details ───────────

    /**
     * The mobile API generate() must surface the same per-field validation as
     * a 422 with `code = validation_failed` and a `details` map keyed by field.
     */
    public function test_api_generate_returns_422_validation_failed_with_details(): void
    {
        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->postJson('/api/v1/links/wizard/generate', [
            'category'  => 'business',
            'page_type' => 'local_shop',
            'answers'   => ['business_name' => 'Bob Bakes'], // address missing
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
        $resp->assertJsonPath('error.details.address', fn ($m) => is_string($m) && $m !== '');
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
    }

    // ── 4. AI auto-draft gated when the engine is OFF ────────────────

    /**
     * API aiGenerate() must short-circuit with 503 `ai_unavailable` when the
     * AI engine is disabled — and it must do so BEFORE validating answers, so
     * an OFF engine is reported even on an otherwise-incomplete payload (the
     * client should offer "generate instantly" instead).
     */
    public function test_api_ai_generate_returns_ai_unavailable_when_engine_off(): void
    {
        $this->assertFalse(AiEngineSettings::isEnabled(), 'AI engine should be OFF by default in tests');

        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->postJson('/api/v1/links/wizard/ai-generate', [
            'category'  => 'business',
            'page_type' => 'local_shop',
            // Intentionally incomplete — the AI gate must fire first.
            'answers'   => ['business_name' => 'Bob Bakes'],
        ]);

        $resp->assertStatus(503);
        $resp->assertJsonPath('error.code', 'ai_unavailable');
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
    }

    /**
     * Web finishAi() must redirect back with a flashed error (not generate)
     * when the AI engine is disabled.
     */
    public function test_web_finish_ai_redirects_with_error_when_engine_off(): void
    {
        $this->assertFalse(AiEngineSettings::isEnabled());

        $user = $this->makeUser($this->plan());

        BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'category'      => 'business',
            'page_type'     => 'local_shop',
            'industry'      => null,
            'step'          => 4,
            'answers'       => ['business_name' => 'Bob Bakes', 'address' => '1 Pastry Lane'],
        ]);

        $resp = $this->actingAs($user)->post('/user/links/wizard/ai-draft');

        $resp->assertRedirect();
        $resp->assertSessionHas('error');
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
    }

    // ── 5. AI-resource picker ────────────────────────────────────────

    /**
     * The resources endpoint must expose the AI-engine flag plus the user's
     * minds and vault files so the auto-draft picker can render its inputs.
     */
    public function test_api_resources_returns_minds_files_and_ai_enabled(): void
    {
        $user = $this->makeUser($this->plan());

        $mind = AiMind::create([
            'user_id'     => $user->id,
            'name'        => 'My Brain',
            'is_disabled' => false,
            'is_default'  => false,
        ]);

        $file = UserFile::create([
            'user_id'       => $user->id,
            'original_name' => 'resume.pdf',
            'filename'      => 'resume-' . Str::random(6) . '.pdf',
            'type'          => 'document',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => 1024,
            'disk'          => 'public',
            'path'          => 'files/resume.pdf',
        ]);
        $file->workspace_id = $this->activeWorkspaceId($user);
        $file->save();

        $this->withToken($this->token($user));
        $resp = $this->getJson('/api/v1/links/wizard/resources');

        $resp->assertOk();
        $resp->assertJsonStructure([
            'data' => ['ai_enabled', 'my_minds', 'platform_minds', 'vault_files'],
        ]);
        // AI is OFF by default in dev/tests.
        $resp->assertJsonPath('data.ai_enabled', false);
        // The user's own mind + file should surface.
        $this->assertContains($mind->id, array_column($resp->json('data.my_minds'), 'id'));
        $this->assertContains($file->id, array_column($resp->json('data.vault_files'), 'id'));
    }

    // ── 6. AI auto-draft happy path (engine ON) ──────────────────────

    /**
     * Swap the real builder for a Mockery double that simulates a successful
     * AI build — it paints a single block onto the link it is handed (so the
     * page is genuinely "built") and never calls OpenAI. Returns the closure's
     * call-count holder so a test can assert the builder actually ran.
     *
     * The mind-grounding path also reaches for OpenAiService::embed when a
     * Brain is selected, so we stub that too (no network).
     *
     * @return object{count:int,link_id:?int,grounding:?string}
     */
    private function fakeSuccessfulBuilder(): object
    {
        $spy = new class {
            public int $count = 0;
            public ?int $link_id = null;
            public ?string $grounding = null;
        };

        $builder = Mockery::mock(AiBiolinkBuilderService::class);
        $builder->shouldReceive('generate')
            ->andReturnUsing(function ($user, $link, $description, $links, $images, $files, $grounding = '') use ($spy) {
                $spy->count++;
                $spy->link_id = (int) $link->id;
                $spy->grounding = (string) $grounding;
                // Simulate a real build: paint a block so the link is a page.
                BiolinkBlock::create([
                    'link_id'    => $link->id,
                    'type'       => 'heading',
                    'settings'   => ['text' => 'Built by AI'],
                    'sort_order' => 0,
                    'is_active'  => true,
                ]);
                return ['credits_spent' => 0, 'blocks' => 1, 'model' => 'gpt-4o-mini'];
            });
        $this->app->instance(AiBiolinkBuilderService::class, $builder);

        // Stub the embedding call used by mind-grounding retrieval.
        $openai = Mockery::mock(OpenAiService::class);
        $openai->shouldReceive('embed')->andReturn([
            'vectors'       => [[0.1]],
            'credits_spent' => 0,
        ]);
        $this->app->instance(OpenAiService::class, $openai);

        return $spy;
    }

    /** Bind a builder double whose generate() throws, to drive the failure path. */
    private function fakeFailingBuilder(): void
    {
        $builder = Mockery::mock(AiBiolinkBuilderService::class);
        $builder->shouldReceive('generate')
            ->andThrow(new \RuntimeException('The assistant returned an unexpected response.'));
        $this->app->instance(AiBiolinkBuilderService::class, $builder);

        $openai = Mockery::mock(OpenAiService::class);
        $openai->shouldReceive('embed')->andReturn(['vectors' => [[0.1]], 'credits_spent' => 0]);
        $this->app->instance(OpenAiService::class, $openai);
    }

    /**
     * With the AI engine ON, the API aiGenerate() must build a real biolink
     * Link from valid answers, ground it in the selected Brain + vault file,
     * and persist those ids under settings['wizard_resources'].
     */
    public function test_api_ai_generate_builds_page_and_records_resources_when_engine_on(): void
    {
        AiEngineSettings::setEnabled(true);
        $spy = $this->fakeSuccessfulBuilder();

        $user = $this->makeUser($this->plan());

        $mind = AiMind::create([
            'user_id'     => $user->id,
            'name'        => 'My Brain',
            'is_disabled' => false,
            'is_default'  => false,
        ]);
        $file = UserFile::create([
            'user_id'       => $user->id,
            'original_name' => 'resume.pdf',
            'filename'      => 'resume-' . Str::random(6) . '.pdf',
            'type'          => 'document',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => 1024,
            'disk'          => 'public',
            'path'          => 'files/resume.pdf',
        ]);
        $file->workspace_id = $this->activeWorkspaceId($user);
        $file->save();

        $this->withToken($this->token($user));
        $resp = $this->postJson('/api/v1/links/wizard/ai-generate', [
            'category'    => 'business',
            'page_type'   => 'local_shop',
            'answers'     => ['business_name' => 'Bob Bakes', 'address' => '1 Pastry Lane'],
            'ai_mind_ids' => [$mind->id],
            'file_ids'    => [$file->id],
        ]);

        $resp->assertCreated();

        // Exactly one biolink Link was created and the builder painted it.
        $link = Link::where('user_id', $user->id)->sole();
        $this->assertSame('biolink', $link->type);
        $this->assertSame(1, $spy->count, 'The AI builder should have run exactly once.');
        $this->assertSame((int) $link->id, $spy->link_id);
        $this->assertSame(1, BiolinkBlock::where('link_id', $link->id)->count());

        // The selected Brain + file are recorded as the page's AI resources.
        $resources = $link->settings['wizard_resources'] ?? null;
        $this->assertIsArray($resources);
        $this->assertSame('wizard_ai_draft', $resources['source']);
        $this->assertContains($mind->id, $resources['ai_mind_ids']);
        $this->assertContains($file->id, $resources['file_ids']);
    }

    /**
     * Web parity: finishAi() must build a real biolink Link from a completed
     * draft (engine ON), ground it in the draft's selected Brain + vault file,
     * record them under settings['wizard_resources'], discard the draft, and
     * land the user in the block editor.
     */
    public function test_web_finish_ai_builds_page_and_records_resources_when_engine_on(): void
    {
        AiEngineSettings::setEnabled(true);
        $spy = $this->fakeSuccessfulBuilder();

        $user = $this->makeUser($this->plan());

        $mind = AiMind::create([
            'user_id'     => $user->id,
            'name'        => 'My Brain',
            'is_disabled' => false,
            'is_default'  => false,
        ]);
        $file = UserFile::create([
            'user_id'       => $user->id,
            'original_name' => 'resume.pdf',
            'filename'      => 'resume-' . Str::random(6) . '.pdf',
            'type'          => 'document',
            'mime_type'     => 'application/pdf',
            'size_bytes'    => 1024,
            'disk'          => 'public',
            'path'          => 'files/resume.pdf',
        ]);
        $file->workspace_id = $this->activeWorkspaceId($user);
        $file->save();

        $draft = BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'category'      => 'business',
            'page_type'     => 'local_shop',
            'industry'      => null,
            'step'          => 4,
            'answers'       => ['business_name' => 'Bob Bakes', 'address' => '1 Pastry Lane'],
            'ai_mind_ids'   => [$mind->id],
            'file_ids'      => [$file->id],
        ]);

        $resp = $this->actingAs($user)->post('/user/links/wizard/ai-draft');

        $resp->assertRedirect();

        $link = Link::where('user_id', $user->id)->sole();
        $this->assertSame('biolink', $link->type);
        $this->assertSame(1, $spy->count);
        $this->assertSame(1, BiolinkBlock::where('link_id', $link->id)->count());

        $resources = $link->settings['wizard_resources'] ?? null;
        $this->assertIsArray($resources);
        $this->assertContains($mind->id, $resources['ai_mind_ids']);
        $this->assertContains($file->id, $resources['file_ids']);

        // The single-shot draft is consumed once the page exists.
        $this->assertNull(BiolinkWizardDraft::find($draft->id));
    }

    // ── 7. AI auto-draft build failure → auto-cleanup ────────────────

    /**
     * When the build throws (e.g. the model returns unparseable JSON), the
     * half-created Link must be deleted so it never lingers in the dashboard,
     * and the API must surface a 500 `ai_generation_failed` envelope.
     */
    public function test_api_ai_generate_cleans_up_link_and_fails_on_build_error(): void
    {
        AiEngineSettings::setEnabled(true);
        $this->fakeFailingBuilder();

        $user = $this->makeUser($this->plan());
        $this->withToken($this->token($user));

        $resp = $this->postJson('/api/v1/links/wizard/ai-generate', [
            'category'  => 'business',
            'page_type' => 'local_shop',
            'answers'   => ['business_name' => 'Bob Bakes', 'address' => '1 Pastry Lane'],
        ]);

        $resp->assertStatus(500);
        $resp->assertJsonPath('error.code', 'ai_generation_failed');
        // The empty link created up-front must have been rolled back.
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
    }

    /**
     * Web parity: a failed build redirects back with a flashed error and
     * leaves no orphaned Link behind.
     */
    public function test_web_finish_ai_cleans_up_link_on_build_error(): void
    {
        AiEngineSettings::setEnabled(true);
        $this->fakeFailingBuilder();

        $user = $this->makeUser($this->plan());

        BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'category'      => 'business',
            'page_type'     => 'local_shop',
            'industry'      => null,
            'step'          => 4,
            'answers'       => ['business_name' => 'Bob Bakes', 'address' => '1 Pastry Lane'],
        ]);

        $resp = $this->actingAs($user)->post('/user/links/wizard/ai-draft');

        $resp->assertRedirect();
        $resp->assertSessionHas('error');
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
    }
}
