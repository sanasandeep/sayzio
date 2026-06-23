<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\BiolinkWizardQuestions;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
}
