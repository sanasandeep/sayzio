<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Services\BiolinkWizardQuestions;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Biolink\AiBiolinkBuilderService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Credit accounting for the AI auto-draft wizard path (web finishAi /
 * API aiGenerate), with the AI engine ENABLED.
 *
 * The engine-OFF gate is pinned in BiolinkWizardValidationTest, and that
 * suite also exercises the engine-ON happy/failure paths — but it swaps the
 * whole AiBiolinkBuilderService for a Mockery double that paints a block and
 * reports `credits_spent => 0`. That double never touches the real coin
 * ledger, so the actual `biolink_builder` charge, the auto-refund on a
 * parse/generation failure, and the insufficient-credits gate were untested.
 *
 * This suite fills that gap by leaving the REAL builder in place and faking
 * only the OpenAI HTTP layer (exactly like AiCreditMeteringTest). That drives
 * the genuine OpenAiService::chat → AiUsageCharger → WalletService flow, so we
 * can assert that a successful AI draft:
 *   - produces a populated biolink Link, and
 *   - debits the wallet with a `biolink_builder`-tagged AI spend,
 * that a failed build:
 *   - cleans up the empty Link and refunds the exact coins (net-zero charge),
 * and that an empty wallet:
 *   - surfaces 402 insufficient_credits (API) / an upgrade redirect (web)
 *     without ever calling OpenAI.
 *
 * The API surface is authenticated with a REAL Sanctum bearer token (NOT
 * Sanctum::actingAs, which 500s under the TouchSessionToken middleware).
 */
class BiolinkWizardAiCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Real engine + real (priced) models so a charge actually lands.
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('sk-test-fake-key');
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
    }

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

    private function seedCoins(User $user, int $coins = 10_000): void
    {
        app(WalletService::class)->credit($user, $coins, ['reason' => 'test seed']);
    }

    /**
     * A complete, valid business answer set the wizard accepts.
     *
     * Built FROM the question catalog rather than hard-coded. This fixture was
     * hard-coded as business_name + address, and when the four-step redesign
     * made display_name and headline required for every combo, every test that
     * used it started getting a 422 instead of a 201 -- for a contract change,
     * not a regression. Asking the catalog what it requires means the next
     * question added cannot silently rot these tests.
     *
     * The catalog is not under test here (BiolinkWizardValidationTest owns
     * that, with explicit fixtures); what is under test is what happens AFTER
     * a valid answer set arrives -- credits, plan gates, the draft itself.
     */
    private function businessAnswers(): array
    {
        return self::completeAnswersFor('business', 'local_shop');
    }

    /** @return array<string, string> every required key, filled plausibly. */
    public static function completeAnswersFor(string $category, string $pageType): array
    {
        $known = [
            'display_name'  => 'Bob Bakes',
            'business_name' => 'Bob Bakes',
            'headline'      => 'Fresh bread, every morning',
            'address'       => '1 Pastry Lane',
        ];

        $answers = [];

        foreach (BiolinkWizardQuestions::questions($category, $pageType, null) as $q) {
            if (empty($q['required']) || empty($q['key'])) {
                continue;
            }

            $key = (string) $q['key'];
            $answers[$key] = $known[$key] ?? match ($q['type'] ?? 'text') {
                'url'   => 'https://example.com',
                'email' => 'bob@example.com',
                'tel'   => '+15551234567',
                default => 'Bob Bakes',
            };
        }

        return $answers;
    }

    /** A well-formed OpenAI chat-completion envelope wrapping $content. */
    private function fakeChatEnvelope(string $content): array
    {
        return [
            'id'      => 'chatcmpl-fake-' . Str::random(8),
            'object'  => 'chat.completion',
            'choices' => [[
                'index'         => 0,
                'message'       => ['role' => 'assistant', 'content' => $content],
                'finish_reason' => 'stop',
            ]],
            'usage'   => ['prompt_tokens' => 800, 'completion_tokens' => 400, 'total_tokens' => 1200],
            'model'   => 'gpt-4o-mini',
        ];
    }

    /** A valid biolink-page JSON body the builder can materialise into blocks. */
    private function validPageJson(): string
    {
        return json_encode([
            'page'   => ['theme_color' => '#3d6bff'],
            'blocks' => [
                ['type' => 'profile_card_v1', 'settings' => [
                    'name'  => 'Bob Bakes',
                    'title' => 'Artisan Bakery',
                    'bio'   => 'Fresh sourdough every morning.',
                ]],
                ['type' => 'heading',   'settings' => ['text' => 'Visit Us']],
                ['type' => 'paragraph', 'settings' => ['text' => '1 Pastry Lane, open daily.']],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Fake the OpenAI endpoints this flow touches.
     *
     * The image endpoint matters as much as the chat one. When the user
     * attaches no images the builder auto-sources them: it AI-generates an
     * avatar and a cover, charging for each. With only chat/completions faked,
     * both image calls got a chat envelope back, failed, and were refunded --
     * two `biolink_builder` refunds on a build that had otherwise succeeded.
     * That is the code behaving correctly; the fake was simply older than the
     * auto-sourcing feature, and it made "a successful build must not be
     * refunded" fail for a reason that has nothing to do with refunds.
     */
    private function fakeOpenAi(string $content): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response($this->fakeChatEnvelope($content)),
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode($this->onePixelPng())]],
            ]),
            // mind-grounding embedding isn't reached here (no Brains selected),
            // but stub it defensively so any incidental call never hits network.
            'api.openai.com/*' => Http::response($this->fakeChatEnvelope($content)),
        ]);
    }

    /** The smallest valid PNG, so image auto-sourcing has real bytes to store. */
    private function onePixelPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    private function aiSpend(User $user): int
    {
        return (int) WalletTransaction::where('user_id', $user->id)
            ->where('type', 'spend')
            ->where('meta->ai', true)
            ->where('meta->feature', AiBiolinkBuilderService::FEATURE)
            ->sum('delta_coins');
    }

    private function aiRefunds(User $user): int
    {
        return WalletTransaction::where('user_id', $user->id)
            ->where('type', 'refund')
            ->where('meta->ai', true)
            ->where('meta->feature', AiBiolinkBuilderService::FEATURE)
            ->count();
    }

    // ── Success: real builder runs, page populated, credit charged ────

    /**
     * API aiGenerate with the real builder + a faked OpenAI response builds a
     * populated biolink Link and debits the wallet with a `biolink_builder`
     * AI spend.
     */
    public function test_api_ai_generate_populates_page_and_charges_biolink_builder_credit(): void
    {
        $user = $this->makeUser($this->plan());
        $this->seedCoins($user);
        $this->fakeOpenAi($this->validPageJson());

        $startBalance = app(AiUsageCharger::class)->getBalance($user);

        $this->withToken($this->token($user));
        $resp = $this->postJson('/api/v1/links/wizard/ai-generate', [
            'category'  => 'business',
            'page_type' => 'local_shop',
            'answers'   => $this->businessAnswers(),
        ]);

        $resp->assertCreated();
        $resp->assertJsonPath('data.link.type', 'biolink');

        // A single populated biolink Link was created from the AI output.
        $link = Link::where('user_id', $user->id)->sole();
        $this->assertSame('biolink', $link->type);

        $blocks = BiolinkBlock::where('link_id', $link->id)->get();
        $this->assertNotEmpty($blocks, 'the AI draft must paint real blocks');
        $profile = $blocks->keyBy('type')['profile_card_v1']->settings ?? [];
        $this->assertSame('Bob Bakes', $profile['name'] ?? null,
            'profile block must carry the AI-supplied name, not a placeholder');

        // The `biolink_builder` credit was actually charged against the wallet.
        $this->assertLessThan($startBalance, app(AiUsageCharger::class)->getBalance($user),
            'a successful AI draft must debit the coin wallet');
        $this->assertLessThan(0, $this->aiSpend($user),
            'a biolink_builder-tagged AI spend row must exist');
        $this->assertSame(0, $this->aiRefunds($user),
            'a successful build must not be refunded');

        Http::assertSent(fn ($req) => str_contains($req->url(), 'chat/completions'));
    }

    /**
     * Web finishAi parity: a completed draft + faked OpenAI response builds a
     * populated page, charges the credit, discards the draft, and lands the
     * user in the block editor.
     */
    public function test_web_ai_draft_populates_page_and_charges_biolink_builder_credit(): void
    {
        $user = $this->makeUser($this->plan());
        $this->seedCoins($user);
        $this->fakeOpenAi($this->validPageJson());

        $startBalance = app(AiUsageCharger::class)->getBalance($user);

        $draft = BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'category'      => 'business',
            'page_type'     => 'local_shop',
            'industry'      => null,
            'step'          => 4,
            'answers'       => $this->businessAnswers(),
        ]);

        $resp = $this->actingAs($user)->post('/user/links/wizard/ai-draft');

        $link = Link::where('user_id', $user->id)->sole();
        $resp->assertRedirect(route('user.links.blocks.editor', $link));

        $blocks = BiolinkBlock::where('link_id', $link->id)->get();
        $this->assertNotEmpty($blocks);
        $profile = $blocks->keyBy('type')['profile_card_v1']->settings ?? [];
        $this->assertSame('Bob Bakes', $profile['name'] ?? null);

        $this->assertLessThan($startBalance, app(AiUsageCharger::class)->getBalance($user));
        $this->assertLessThan(0, $this->aiSpend($user));

        // Single-shot draft is consumed once the page exists.
        $this->assertNull(BiolinkWizardDraft::find($draft->id));
    }

    // ── Failure: parse error → auto-refund + cleanup (net-zero) ───────

    /**
     * When OpenAI returns unparseable content, the real builder refunds the
     * exact coins it spent (net-zero charge), the half-built Link is removed,
     * and the API surfaces 500 ai_generation_failed.
     */
    public function test_api_ai_generate_refunds_credit_and_cleans_up_on_parse_failure(): void
    {
        $user = $this->makeUser($this->plan());
        $this->seedCoins($user);
        // Not JSON → json_decode() yields null → the builder throws after the
        // charge, triggering the auto-refund path.
        $this->fakeOpenAi('this is not json at all');

        $startBalance = app(AiUsageCharger::class)->getBalance($user);

        $this->withToken($this->token($user));
        $resp = $this->postJson('/api/v1/links/wizard/ai-generate', [
            'category'  => 'business',
            'page_type' => 'local_shop',
            'answers'   => $this->businessAnswers(),
        ]);

        $resp->assertStatus(500);
        $resp->assertJsonPath('error.code', 'ai_generation_failed');

        // The empty link created up-front was rolled back.
        $this->assertSame(0, Link::where('user_id', $user->id)->count());

        // Charge happened, then a matching refund — net wallet effect is zero.
        $this->assertLessThan(0, $this->aiSpend($user), 'the call must charge before failing');
        $this->assertGreaterThanOrEqual(1, $this->aiRefunds($user), 'a failed build must auto-refund');
        $this->assertSame($startBalance, app(AiUsageCharger::class)->getBalance($user),
            'charge + auto-refund must net to zero on a failed AI draft');
    }

    /**
     * Web parity: a parse failure refunds the credit (net-zero), leaves no
     * orphaned Link, and redirects back with a flashed error.
     */
    public function test_web_ai_draft_refunds_credit_on_parse_failure(): void
    {
        $user = $this->makeUser($this->plan());
        $this->seedCoins($user);
        $this->fakeOpenAi('totally not json');

        $startBalance = app(AiUsageCharger::class)->getBalance($user);

        $draft = BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'category'      => 'business',
            'page_type'     => 'local_shop',
            'industry'      => null,
            'step'          => 4,
            'answers'       => $this->businessAnswers(),
        ]);

        $resp = $this->actingAs($user)->post('/user/links/wizard/ai-draft');

        $resp->assertRedirect();
        $resp->assertSessionHas('error');
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
        $this->assertSame($startBalance, app(AiUsageCharger::class)->getBalance($user),
            'charge + auto-refund must net to zero on a failed AI draft');

        // The draft survives so the user can retry without re-entering answers.
        $this->assertNotNull(BiolinkWizardDraft::find($draft->id));
    }

    // ── Insufficient credits: gate before OpenAI ──────────────────────

    /**
     * An empty wallet must surface 402 insufficient_credits on the API, never
     * call OpenAI (the worst-case prepay gate rejects first), and leave no
     * orphaned Link behind.
     */
    public function test_api_ai_generate_returns_402_on_insufficient_credits(): void
    {
        $user = $this->makeUser($this->plan());
        // No coins seeded → zero balance.
        $this->fakeOpenAi($this->validPageJson());

        $this->withToken($this->token($user));
        $resp = $this->postJson('/api/v1/links/wizard/ai-generate', [
            'category'  => 'business',
            'page_type' => 'local_shop',
            'answers'   => $this->businessAnswers(),
        ]);

        $resp->assertStatus(402);
        $resp->assertJsonPath('error.code', 'insufficient_credits');

        // Pre-call gate fires before the HTTP request and the link is cleaned up.
        Http::assertNothingSent();
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
        $this->assertSame(0, $this->aiSpend($user));
    }

    /**
     * Web parity: an empty wallet redirects to the upgrade page with a flashed
     * error, never calls OpenAI, and creates nothing.
     */
    public function test_web_ai_draft_redirects_to_upgrade_on_insufficient_credits(): void
    {
        $user = $this->makeUser($this->plan());
        $this->fakeOpenAi($this->validPageJson());

        $draft = BiolinkWizardDraft::create([
            'user_id'       => $user->id,
            'actor_user_id' => $user->id,
            'workspace_id'  => $this->activeWorkspaceId($user),
            'category'      => 'business',
            'page_type'     => 'local_shop',
            'industry'      => null,
            'step'          => 4,
            'answers'       => $this->businessAnswers(),
        ]);

        $resp = $this->actingAs($user)->post('/user/links/wizard/ai-draft');

        $resp->assertRedirect(route('user.upgrade'));
        $resp->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame(0, Link::where('user_id', $user->id)->count());

        // Draft survives so the user can retry after topping up.
        $this->assertNotNull(BiolinkWizardDraft::find($draft->id));
    }
}
