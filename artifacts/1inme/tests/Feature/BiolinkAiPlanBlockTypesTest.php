<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BiolinkWizardDraft;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pins that the AI biolink builder honours each plan's block-type allowance.
 *
 * AiBiolinkBuilderService::allowedTypesFor() intersects the curated AI block
 * catalog with the user's plan `block_types_allowed` list, and snapshotFromAi()
 * drops any block (top-level or card child) whose type isn't in that set. If
 * that filtering regressed, a lower-tier user could end up with premium blocks
 * their plan forbids, or paid-for types could be silently stripped.
 *
 * These tests give a user a plan whose features set `block_types_allowed` to a
 * small list, feed a faked OpenAI response containing BOTH allowed and
 * disallowed block types, and assert only the allowed types are persisted —
 * across both the API ai-generate and the web ai-draft surfaces.
 *
 * The real builder runs (only the OpenAI HTTP layer is faked) and the API
 * surface is authenticated with a REAL Sanctum bearer token (NOT
 * Sanctum::actingAs, which 500s under the TouchSessionToken middleware).
 */
class BiolinkAiPlanBlockTypesTest extends TestCase
{
    use RefreshDatabase;

    /** The block types this plan is allowed to use. */
    private const ALLOWED = ['profile_card_v1', 'heading', 'paragraph', 'card', 'link'];

    /** Curated catalog types the plan forbids — must never be persisted. */
    private const DISALLOWED = ['cta_button', 'faq', 'divider', 'image'];

    protected function setUp(): void
    {
        parent::setUp();

        // Real engine + real (priced) models so the genuine builder runs.
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('sk-test-fake-key');
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
    }

    /** A plan that restricts block_types_allowed to a small allow-list. */
    private function restrictedPlan(): Plan
    {
        return Plan::create([
            'name'          => 'Restricted Plan',
            'slug'          => 'restricted-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => [
                'max_links'           => 100,
                'max_biolinks'        => 100,
                'block_types_allowed' => self::ALLOWED,
            ],
        ]);
    }

    private function makeUser(Plan $plan): User
    {
        $user = User::create([
            'name'     => 'Plan ' . Str::random(4),
            'email'    => 'plan-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan->id,
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

    private function seedCoins(User $user, int $coins = 10_000): void
    {
        app(WalletService::class)->credit($user, $coins, ['reason' => 'test seed']);
    }

    private function businessAnswers(): array
    {
        return ['business_name' => 'Bob Bakes', 'address' => '1 Pastry Lane'];
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

    private function fakeOpenAi(string $content): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response($this->fakeChatEnvelope($content)),
            'api.openai.com/*'                   => Http::response($this->fakeChatEnvelope($content)),
        ]);
    }

    /**
     * A page JSON mixing allowed and disallowed block types — including a
     * `card` (allowed) that itself nests an allowed child (`link`) and a
     * disallowed child (`cta_button`) so child filtering is covered too.
     */
    private function mixedPageJson(): string
    {
        return json_encode([
            'page'   => ['theme_color' => '#3d6bff'],
            'blocks' => [
                ['type' => 'profile_card_v1', 'settings' => ['name' => 'Bob Bakes', 'title' => 'Bakery', 'bio' => 'Fresh daily.']],
                ['type' => 'heading',    'settings' => ['text' => 'Visit Us']],
                ['type' => 'paragraph',  'settings' => ['text' => '1 Pastry Lane, open daily.']],
                // Disallowed top-level types — must be dropped:
                ['type' => 'cta_button', 'settings' => ['text' => 'Order now', 'url' => 'https://example.com/order']],
                ['type' => 'faq',        'settings' => ['items' => [['question' => 'Open?', 'answer' => 'Yes']]]],
                ['type' => 'divider',    'settings' => []],
                ['type' => 'image',      'settings' => ['url' => 'https://example.com/x.png', 'alt' => 'x']],
                // Allowed card with one allowed child + one disallowed child:
                ['type' => 'card', 'settings' => ['title' => 'Links'], 'children' => [
                    ['type' => 'link',       'settings' => ['url' => 'https://example.com', 'text' => 'Website']],
                    ['type' => 'cta_button', 'settings' => ['text' => 'Buy', 'url' => 'https://example.com/buy']],
                ]],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Every persisted block type for a link. Card children are stored as
     * their own rows (parent_id set, same link_id), so a flat type query
     * already covers both top-level blocks and nested card children.
     */
    private function persistedTypes(Link $link): array
    {
        return BiolinkBlock::where('link_id', $link->id)
            ->pluck('type')
            ->all();
    }

    /** Assert the persisted page keeps allowed types and drops disallowed ones. */
    private function assertRespectsAllowList(Link $link): void
    {
        $types = $this->persistedTypes($link);
        $this->assertNotEmpty($types, 'the AI draft must paint real blocks');

        foreach (self::DISALLOWED as $banned) {
            $this->assertNotContains($banned, $types,
                "disallowed block type [{$banned}] must be stripped from the AI page");
        }

        // The allowed core types the model emitted must survive.
        $this->assertContains('profile_card_v1', $types, 'allowed profile card must persist');
        $this->assertContains('heading', $types, 'allowed heading must persist');
        $this->assertContains('paragraph', $types, 'allowed paragraph must persist');

        // Every persisted type must be within the plan's allow-list.
        foreach ($types as $t) {
            $this->assertContains($t, self::ALLOWED,
                "persisted block type [{$t}] is outside the plan's block_types_allowed");
        }
    }

    // ── API ai-generate ──────────────────────────────────────────────

    public function test_api_ai_generate_persists_only_plan_allowed_block_types(): void
    {
        $user = $this->makeUser($this->restrictedPlan());
        $this->seedCoins($user);
        $this->fakeOpenAi($this->mixedPageJson());

        $this->withToken($this->token($user));
        $resp = $this->postJson('/api/v1/links/wizard/ai-generate', [
            'category'  => 'business',
            'page_type' => 'local_shop',
            'answers'   => $this->businessAnswers(),
        ]);

        $resp->assertCreated();

        $link = Link::where('user_id', $user->id)->sole();
        $this->assertRespectsAllowList($link);
    }

    // ── Web ai-draft ─────────────────────────────────────────────────

    public function test_web_ai_draft_persists_only_plan_allowed_block_types(): void
    {
        $user = $this->makeUser($this->restrictedPlan());
        $this->seedCoins($user);
        $this->fakeOpenAi($this->mixedPageJson());

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

        $this->assertRespectsAllowList($link);
    }

    // ── Mobile ai-generate handoff (with grounding) ───────────────────

    /**
     * The mobile app reaches the AI builder through the same Sanctum endpoint
     * as the generic API surface, but its real-world entry is the stateless
     * "scan → handoff" flow: a card/brochure scan seeds the wizard via
     * prefillCategory/prefillAnswers, the client carries those answers in
     * memory, and the AI-draft button POSTs them to /links/wizard/ai-generate
     * together with grounding inputs (selected vault files / AI Brains) that
     * the instant generator never sends.
     *
     * This pins that the mobile-originated path — including the extra grounding
     * channel that funnels through WizardAiDraftService → AiBiolinkBuilderService
     * — still drops block types the plan forbids. A regression that let the
     * mobile entry point skip allowedTypesFor() would hand a restricted plan
     * premium blocks it never paid for.
     */
    public function test_mobile_ai_generate_handoff_persists_only_plan_allowed_block_types(): void
    {
        $user = $this->makeUser($this->restrictedPlan());
        $this->seedCoins($user);
        $this->fakeOpenAi($this->mixedPageJson());

        // Grounding input the mobile draft picker can attach: a vault image.
        // It flows in as an extra image URL to the builder (no embeddings
        // call — only AI Brains trigger those), exercising the resource
        // channel that the plain API ai-generate test doesn't cover.
        $vaultImage = UserFile::create([
            'user_id'       => $user->id,
            'original_name' => 'storefront.jpg',
            'filename'      => 'storefront-' . Str::random(6) . '.jpg',
            'type'          => 'image',
            'mime_type'     => 'image/jpeg',
            'size_bytes'    => 2048,
            'disk'          => 'public',
            'path'          => 'files/storefront.jpg',
        ]);
        $vaultImage->workspace_id = $this->activeWorkspaceId($user);
        $vaultImage->save();

        // Mirror the mobile client: its User-Agent / X-1INME-Client headers and
        // the scan-handoff answers carried in memory, posted in one shot with
        // the grounding file id the draft picker selected.
        $this->withToken($this->token($user));
        $this->withHeaders([
            'User-Agent'      => '1INMEMobileApp/1.0.0 (ios; expo)',
            'X-1INME-Client'  => '1INMEMobileApp/1.0.0 (ios; expo)',
        ]);
        $resp = $this->postJson('/api/v1/links/wizard/ai-generate', [
            'category'  => 'business',
            'page_type' => 'local_shop',
            'answers'   => $this->businessAnswers(),
            'file_ids'  => [$vaultImage->id],
        ]);

        $resp->assertCreated();

        $link = Link::where('user_id', $user->id)->sole();
        $this->assertRespectsAllowList($link);

        // The selected vault file should be recorded as a build resource,
        // confirming the grounding channel actually ran on this path.
        $this->assertContains(
            $vaultImage->id,
            $link->fresh()->settings['wizard_resources']['file_ids'] ?? [],
            'the mobile draft must record the grounding vault file it built from',
        );
    }
}
