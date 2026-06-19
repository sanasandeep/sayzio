<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\AiCompanion;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\User;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindIngestor;
use App\Services\AI\AiMindQueryService;
use App\Services\AI\CompanionRuntime;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\AI\OpenAiService;
use App\Services\AI\SiteAssistantRuntime;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for per-feature AI credit metering — i.e. WHO pays for each
 * AI call. The metering chokepoint is {@see OpenAiService}: it charges
 * the exact User it is handed and refuses (via the worst-case prepay
 * gate) before hitting OpenAI when that user can't afford the call.
 *
 * On top of that chokepoint, three documented billing exceptions decide
 * which account is handed to OpenAiService:
 *   - Site Assistant: signed-in visitor → their own account; anonymous
 *     marketing visitor → the platform-admin billing account.
 *   - AI Companion: every public turn is charged to the companion OWNER,
 *     never the visitor.
 *   - Platform AI Mind (no owner): embeddings are charged to the first
 *     user holding `user.ai_minds.manage_platform`.
 *
 * Strategy mirrors MindCreditTaggingTest: enable the real engine, fake
 * the OpenAI HTTP layer, and drive the real services so the charge flows
 * through OpenAiService → AiCreditService → ledger exactly as in prod.
 */
class AiCreditMeteringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('sk-test-fake-key');
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
    }

    private function makeUser(string $prefix = 'ai'): User
    {
        return User::create([
            'name'     => Str::title($prefix).' User '.Str::random(4),
            'email'    => $prefix.Str::random(6).'@example.test',
            'password' => bcrypt('secret'),
            'status'   => 'active',
            'role'     => 'user',
        ]);
    }

    /** Grant a permission to a user via a freshly-minted role. */
    private function grantPermission(User $user, string $slug): void
    {
        $perm = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'test']);
        $role = Role::create(['name' => 'Role '.Str::random(6), 'slug' => 'role-'.Str::random(8), 'guard' => 'web']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    private function fakeChatResponse(string $content = 'A grounded answer.'): array
    {
        return [
            'id'      => 'chatcmpl-fake-'.Str::random(8),
            'object'  => 'chat.completion',
            'choices' => [[
                'index'         => 0,
                'message'       => ['role' => 'assistant', 'content' => $content],
                'finish_reason' => 'stop',
            ]],
            'usage'   => ['prompt_tokens' => 200, 'completion_tokens' => 50, 'total_tokens' => 250],
            'model'   => 'gpt-4o-mini',
        ];
    }

    private function fakeEmbeddingResponse(int $inputs, int $tokens = 100): array
    {
        return [
            'object' => 'list',
            'data'   => array_map(
                fn ($i) => ['object' => 'embedding', 'index' => $i, 'embedding' => array_fill(0, 8, 0.1)],
                range(0, max(0, $inputs - 1))
            ),
            'model'  => 'text-embedding-3-small',
            'usage'  => ['prompt_tokens' => $tokens, 'total_tokens' => $tokens],
        ];
    }

    // ── The chokepoint: OpenAiService charges the user it is handed ──

    public function test_chat_charges_the_passed_users_own_balance_and_leaves_others_untouched(): void
    {
        $caller  = $this->makeUser('caller');
        $other   = $this->makeUser('other');
        app(WalletService::class)->credit($caller, 10_000, ['reason' => 'test seed']);
        app(WalletService::class)->credit($other, 10_000, ['reason' => 'test seed']);

        Http::fake(['api.openai.com/v1/chat/completions' => Http::response($this->fakeChatResponse())]);

        $result = app(OpenAiService::class)->chat($caller, 'gpt-4o-mini', [
            ['role' => 'user', 'content' => 'Hello there'],
        ], ['feature' => 'persona']);

        $this->assertGreaterThan(0, $result['credits_spent']);

        $callerSpend = WalletTransaction::where('user_id', $caller->id)->where('type', 'spend')->where('meta->ai', true)->sum('delta_coins');
        $otherSpend  = WalletTransaction::where('user_id', $other->id)->where('type', 'spend')->where('meta->ai', true)->count();

        $this->assertGreaterThan(0, abs((int) $callerSpend), 'Caller must be charged for their own call.');
        $this->assertSame(0, (int) $otherSpend, "A different user's balance must never be touched.");

        $this->assertLessThan(
            10_000,
            app(AiCreditService::class)->getBalance($caller),
            'Caller balance must drop by the chat cost.'
        );
        $this->assertSame(10_000, app(AiCreditService::class)->getBalance($other));
    }

    public function test_precall_gate_rejects_when_user_cannot_afford_and_never_calls_openai(): void
    {
        // Zero balance: the worst-case prepay gate must throw before any
        // HTTP request is made, so no charge and no OpenAI call happen.
        $broke = $this->makeUser('broke');

        Http::fake(['api.openai.com/*' => Http::response($this->fakeChatResponse())]);

        try {
            app(OpenAiService::class)->chat($broke, 'gpt-4o-mini', [
                ['role' => 'user', 'content' => str_repeat('expensive prompt ', 50)],
            ], ['feature' => 'persona']);
            $this->fail('Expected InsufficientAiCreditsException for a zero-balance user.');
        } catch (InsufficientAiCreditsException $e) {
            // expected
        }

        Http::assertNothingSent();
        $this->assertSame(
            0,
            WalletTransaction::where('user_id', $broke->id)->where('type', 'spend')->where('meta->ai', true)->count(),
            'A rejected pre-call gate must not write a spend row.'
        );
    }

    // ── Documented exception: anonymous Site Assistant → platform admin ──

    public function test_site_assistant_bills_signed_in_visitor_to_self_and_anonymous_to_platform_admin(): void
    {
        $admin = $this->makeUser('admin');
        $this->grantPermission($admin, 'user.platform.admin');

        $signedIn = $this->makeUser('member');

        $runtime = new class(
            app(OpenAiService::class),
            app(AiMindQueryService::class),
            app(AiCreditService::class),
        ) extends SiteAssistantRuntime {
            public function exposeBillingUser(?User $user): ?User
            {
                return $this->billingUser($user);
            }
        };

        // Signed-in visitor pays from their own account.
        $this->assertSame(
            $signedIn->id,
            $runtime->exposeBillingUser($signedIn)?->id,
            'A signed-in visitor must be billed to their own account.'
        );

        // Anonymous visitor falls back to the platform-admin billing pool.
        $this->assertSame(
            $admin->id,
            $runtime->exposeBillingUser(null)?->id,
            'An anonymous visitor must be billed to the platform-admin account.'
        );
    }

    // ── Documented exception: AI Companion turn → owner ──

    public function test_companion_turn_charges_the_owner_not_the_visitor(): void
    {
        $owner = $this->makeUser('owner');
        app(WalletService::class)->credit($owner, 10_000, ['reason' => 'test seed']);

        $persona = AiPersonaAgent::create([
            'user_id'          => $owner->id,
            'slug'             => 'persona-'.Str::random(6),
            'name'             => 'Helper',
            'system_prompt'    => 'You are helpful.',
            'model'            => 'gpt-4o-mini',
            'temperature_x100' => 70,
            'max_tokens'       => 256,
            'use_default_mind' => false,
            'is_disabled'      => false,
        ]);

        $companion = AiCompanion::create([
            'user_id'              => $owner->id,
            'persona_id'           => $persona->id,
            'public_id'            => 'cmp_'.Str::random(12),
            'name'                 => 'Site bot',
            'placement'            => AiCompanion::PLACEMENT_BIOLINK ?? 'biolink',
            'free_turns_per_month' => 0,
            'hard_cap_per_month'   => 0,
            'is_disabled'          => false,
        ]);

        // No Minds attached + use_default_mind=false → the turn skips
        // retrieval embedding and only runs the chat completion.
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response($this->fakeChatResponse())]);

        $result = app(CompanionRuntime::class)->turn(
            $companion,
            'visitor_'.Str::random(20),
            'Hi, what is this about?',
            ['ip' => '203.0.113.5'],
        );

        $this->assertTrue($result['ok'], 'Companion turn should succeed: '.($result['error'] ?? ''));

        $ownerSpend = WalletTransaction::where('user_id', $owner->id)->where('type', 'spend')->where('meta->ai', true)->count();
        $this->assertGreaterThan(0, $ownerSpend, 'The companion owner must be charged for the turn.');
        $this->assertLessThan(10_000, app(AiCreditService::class)->getBalance($owner));
    }

    public function test_companion_turn_fails_gracefully_when_owner_is_out_of_credits(): void
    {
        // Owner has zero balance → OpenAiService pre-call gate throws
        // InsufficientAiCreditsException, which CompanionRuntime catches
        // and converts into a friendly ok=false (no OpenAI call, no charge).
        $owner = $this->makeUser('poorowner');

        $persona = AiPersonaAgent::create([
            'user_id'          => $owner->id,
            'slug'             => 'persona-'.Str::random(6),
            'name'             => 'Helper',
            'system_prompt'    => 'You are helpful.',
            'model'            => 'gpt-4o-mini',
            'temperature_x100' => 70,
            'max_tokens'       => 256,
            'use_default_mind' => false,
            'is_disabled'      => false,
        ]);

        $companion = AiCompanion::create([
            'user_id'              => $owner->id,
            'persona_id'           => $persona->id,
            'public_id'            => 'cmp_'.Str::random(12),
            'name'                 => 'Site bot',
            'placement'            => AiCompanion::PLACEMENT_BIOLINK ?? 'biolink',
            'free_turns_per_month' => 0,
            'hard_cap_per_month'   => 0,
            'is_disabled'          => false,
        ]);

        Http::fake(['api.openai.com/*' => Http::response($this->fakeChatResponse())]);

        $result = app(CompanionRuntime::class)->turn(
            $companion,
            'visitor_'.Str::random(20),
            'Hi there',
            ['ip' => '203.0.113.6'],
        );

        $this->assertFalse($result['ok'], 'Turn must fail when the owner is out of credits.');
        Http::assertNothingSent();
        $this->assertSame(0, WalletTransaction::where('user_id', $owner->id)->where('type', 'spend')->where('meta->ai', true)->count());
    }

    // ── Documented exception: platform AI Mind ingest → admin ──

    public function test_platform_mind_ingest_charges_the_manage_platform_admin(): void
    {
        // The platform mind has no owner (user_id null). Its embedding
        // spend must land on the first user holding the manage-platform
        // AI permission, not on some arbitrary account.
        $admin = $this->makeUser('mindadmin');
        $this->grantPermission($admin, 'user.ai_minds.manage_platform');
        app(WalletService::class)->credit($admin, 10_000, ['reason' => 'test seed']);

        $mind = AiMind::create([
            'user_id'     => null,
            'name'        => 'Platform Mind',
            'slug'        => 'platform-mind-'.Str::random(6),
            'description' => null,
            'is_default'  => true,
        ]);

        $source = AiMindSource::create([
            'mind_id' => $mind->id,
            'type'    => AiMindSource::TYPE_TEXT,
            'title'   => 'Platform note',
            'body'    => str_repeat('The platform knows many helpful things. ', 30),
            'status'  => AiMindSource::STATUS_QUEUED,
        ]);

        Http::fake([
            'api.openai.com/v1/embeddings' => function ($request) {
                $body   = $request->data();
                $inputs = is_array($body['input'] ?? null) ? count($body['input']) : 1;
                return Http::response($this->fakeEmbeddingResponse($inputs));
            },
        ]);

        app(AiMindIngestor::class)->ingest($source);

        $source->refresh();
        $this->assertSame(
            AiMindSource::STATUS_READY,
            $source->status,
            "Ingest should succeed. status_message: {$source->status_message}"
        );

        $adminSpend = WalletTransaction::where('user_id', $admin->id)->where('type', 'spend')->where('meta->ai', true)->count();
        $this->assertGreaterThan(0, $adminSpend, 'Platform-mind ingest must charge the manage-platform admin.');
        $this->assertLessThan(10_000, app(AiCreditService::class)->getBalance($admin));
    }

    // ── API path metering with a real Sanctum Bearer token ──

    public function test_ask_coach_api_charges_the_authenticated_callers_own_balance(): void
    {
        // Drive the metered AI feature through the real /api/v1 Sanctum
        // stack with a genuine Bearer token (never Sanctum::actingAs) so
        // the charge is attributed to the authenticated caller.
        $caller = $this->makeUser('coach');
        app(WalletService::class)->credit($caller, 10_000, ['reason' => 'test seed']);

        $token = $caller->createToken('test')->plainTextToken;

        Http::fake([
            'api.openai.com/v1/embeddings'       => function ($request) {
                $body   = $request->data();
                $inputs = is_array($body['input'] ?? null) ? count($body['input']) : 1;
                return Http::response($this->fakeEmbeddingResponse($inputs));
            },
            'api.openai.com/v1/chat/completions' => Http::response($this->fakeChatResponse()),
        ]);

        $threadId = $this->withToken($token)
            ->postJson('/api/v1/ai/ask-coach/threads')
            ->assertSuccessful()
            ->json('thread.id');

        $this->assertNotNull($threadId, 'Thread creation should return an id.');

        $this->withToken($token)
            ->postJson("/api/v1/ai/ask-coach/threads/{$threadId}/send", ['message' => 'How are my links doing?'])
            ->assertSuccessful();

        $this->assertGreaterThan(
            0,
            WalletTransaction::where('user_id', $caller->id)->where('type', 'spend')->where('meta->ai', true)->count(),
            'The authenticated caller must be charged for their Ask Coach turn.'
        );
        $this->assertLessThan(10_000, app(AiCreditService::class)->getBalance($caller));
    }
}
