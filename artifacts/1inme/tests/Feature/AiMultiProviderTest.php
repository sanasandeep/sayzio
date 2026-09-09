<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\Providers\AiProviderException;
use App\Services\AI\Providers\AiProviderRegistry;
use App\Services\AI\Providers\AnthropicDriver;
use App\Services\AI\Providers\OpenRouterDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The multi-provider AI engine: OpenAI, OpenRouter and Claude API behind one
 * gateway, with the model chosen per plan per module and a fallback chain
 * when a vendor is down.
 *
 * These tests fake the HTTP layer rather than calling a vendor. What is being
 * guarded is the translation and the routing -- that an OpenAI-shaped request
 * becomes a valid Anthropic one and back again, that the right key goes to
 * the right host, and that a failure moves to the next provider only when
 * another vendor could actually help. Whether Anthropic itself is up is not
 * this suite's business.
 */
class AiMultiProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Drivers memoise, and each test rewires keys.
        AiProviderRegistry::flush();

        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('sk-openai-test');
        AiEngineSettings::setOpenRouterKey('sk-or-test');
        AiEngineSettings::setAnthropicKey('sk-ant-test');

        AiEngineSettings::setModels([
            ['name' => 'gpt-4o-mini', 'kind' => 'chat', 'enabled' => true, 'provider' => 'openai',
             'in_coins_per_1k' => 0, 'out_coins_per_1k' => 0],
            ['name' => 'anthropic/claude-sonnet-4', 'kind' => 'chat', 'enabled' => true, 'provider' => 'openrouter',
             'in_coins_per_1k' => 0, 'out_coins_per_1k' => 0],
            ['name' => 'claude-sonnet-4-latest', 'kind' => 'chat', 'enabled' => true, 'provider' => 'anthropic',
             'in_coins_per_1k' => 0, 'out_coins_per_1k' => 0],
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $plan ??= Plan::create([
            'name' => 'P', 'slug' => 'p' . Str::random(6),
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active', 'features' => [],
        ]);

        return User::factory()->create(['plan_id' => $plan->id]);
    }

    // ── model registry carries the provider ───────────────────────

    public function test_a_model_row_without_a_provider_is_treated_as_openai(): void
    {
        // Every row written before providers existed looks like this, and
        // all of them are OpenAI models. Defaulting rather than throwing is
        // what lets this ship without rewriting stored settings.
        AiEngineSettings::setModels([
            ['name' => 'legacy-model', 'kind' => 'chat', 'enabled' => true,
             'in_coins_per_1k' => 0, 'out_coins_per_1k' => 0],
        ]);

        $this->assertSame('openai', AiEngineSettings::model('legacy-model')['provider']);
    }

    public function test_an_unknown_provider_slug_resolves_to_openai_rather_than_throwing(): void
    {
        $this->assertSame('openai', AiProviderRegistry::normalise('bananas'));
        $this->assertSame('openai', AiProviderRegistry::normalise(null));
        $this->assertSame('anthropic', AiProviderRegistry::normalise('ANTHROPIC'));
    }

    // ── each provider is reached at its own host, with its own key ──

    public function test_openrouter_calls_openrouter_with_its_own_key_and_attribution(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => 'hi']]],
            'usage'   => ['prompt_tokens' => 3, 'completion_tokens' => 2],
        ])]);

        $out = (new OpenRouterDriver())->chat('anthropic/claude-sonnet-4', [
            ['role' => 'user', 'content' => 'hello'],
        ]);

        $this->assertSame('hi', $out['content']);
        $this->assertSame(3, $out['tokens_in']);

        Http::assertSent(function ($req) {
            return str_contains($req->url(), 'openrouter.ai/api/v1/chat/completions')
                && $req->hasHeader('Authorization', 'Bearer sk-or-test')
                // OpenRouter's attribution headers; optional to them, and the
                // difference between being credited and not.
                && $req->hasHeader('X-Title');
        });
    }

    public function test_anthropic_uses_x_api_key_and_a_pinned_version_header(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'hello there']],
            'usage'   => ['input_tokens' => 7, 'output_tokens' => 4],
        ])]);

        $out = (new AnthropicDriver())->chat('claude-sonnet-4-latest', [
            ['role' => 'user', 'content' => 'hi'],
        ]);

        $this->assertSame('hello there', $out['content']);
        $this->assertSame(7, $out['tokens_in']);
        $this->assertSame(4, $out['tokens_out']);

        Http::assertSent(function ($req) {
            return str_contains($req->url(), 'api.anthropic.com/v1/messages')
                && $req->hasHeader('x-api-key', 'sk-ant-test')
                && $req->hasHeader('anthropic-version');
        });
    }

    // ── the translation Anthropic actually requires ───────────────

    public function test_system_messages_are_hoisted_out_of_the_message_array(): void
    {
        // Anthropic rejects role:"system" inside messages outright, so this
        // is not a nicety -- leaving it in place is a 400 on every call that
        // has a system prompt, which is very nearly all of them.
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
            'usage'   => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);

        (new AnthropicDriver())->chat('claude-sonnet-4-latest', [
            ['role' => 'system', 'content' => 'You are Zio.'],
            ['role' => 'user', 'content' => 'hi'],
            ['role' => 'system', 'content' => 'Be brief.'],
        ]);

        Http::assertSent(function ($req) {
            $body = $req->data();

            // Both system messages survive, joined rather than the second
            // one being dropped on the floor.
            return $body['system'] === "You are Zio.\n\nBe brief."
                && count($body['messages']) === 1
                && $body['messages'][0]['role'] === 'user';
        });
    }

    public function test_max_tokens_is_always_sent_because_anthropic_requires_it(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
            'usage'   => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);

        // Caller omits max_tokens, which is legal for OpenAI and a 400 here.
        (new AnthropicDriver())->chat('claude-sonnet-4-latest', [
            ['role' => 'user', 'content' => 'hi'],
        ]);

        Http::assertSent(fn ($req) => ($req->data()['max_tokens'] ?? 0) > 0);
    }

    public function test_tool_calls_are_translated_in_both_directions(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [[
                'type'  => 'tool_use',
                'id'    => 'toolu_1',
                'name'  => 'lookup_order',
                'input' => ['order_id' => 42],
            ]],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 3],
        ])]);

        $out = (new AnthropicDriver())->chat('claude-sonnet-4-latest', [
            ['role' => 'user', 'content' => 'where is my order'],
        ], [
            'tools' => [[
                'type'     => 'function',
                'function' => [
                    'name'        => 'lookup_order',
                    'description' => 'Look an order up',
                    'parameters'  => ['type' => 'object', 'properties' => ['order_id' => ['type' => 'integer']]],
                ],
            ]],
        ]);

        // Request: OpenAI's nested {function:{name,parameters}} becomes
        // Anthropic's flat {name,input_schema}.
        Http::assertSent(function ($req) {
            $tool = $req->data()['tools'][0] ?? [];

            return ($tool['name'] ?? null) === 'lookup_order'
                && isset($tool['input_schema']['properties']['order_id']);
        });

        // Response: back to the OpenAI shape callers already parse, with
        // arguments as a JSON *string* even though Anthropic sent an object.
        $this->assertCount(1, $out['tool_calls']);
        $this->assertSame('lookup_order', $out['tool_calls'][0]['function']['name']);
        $this->assertSame(
            ['order_id' => 42],
            json_decode($out['tool_calls'][0]['function']['arguments'], true),
        );
    }

    public function test_a_data_uri_image_becomes_an_anthropic_base64_source_block(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'a cat']],
            'usage'   => ['input_tokens' => 9, 'output_tokens' => 2],
        ])]);

        (new AnthropicDriver())->chat('claude-sonnet-4-latest', [[
            'role'    => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'what is this'],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,AAAB']],
            ],
        ]]);

        Http::assertSent(function ($req) {
            $blocks = $req->data()['messages'][0]['content'];

            return $blocks[1]['type'] === 'image'
                && $blocks[1]['source']['type'] === 'base64'
                && $blocks[1]['source']['media_type'] === 'image/png'
                && $blocks[1]['source']['data'] === 'AAAB';
        });
    }

    // ── embeddings stay where they exist ──────────────────────────

    public function test_providers_without_embeddings_say_so_rather_than_returning_nothing(): void
    {
        $this->assertFalse((new AnthropicDriver())->supportsEmbeddings());
        $this->assertFalse((new OpenRouterDriver())->supportsEmbeddings());
        $this->assertTrue(AiProviderRegistry::driver('openai')->supportsEmbeddings());

        $this->expectException(AiProviderException::class);
        (new AnthropicDriver())->embed('whatever', ['text']);
    }

    // ── which failures are worth another vendor ───────────────────

    public function test_only_failures_another_vendor_could_survive_trigger_fallback(): void
    {
        // Ours to fix: every vendor rejects a malformed request identically,
        // so walking the chain just multiplies latency before the same error.
        $this->assertFalse((new AiProviderException('bad', 400))->isWorthFallingBackFrom());
        $this->assertFalse((new AiProviderException('gone', 404))->isWorthFallingBackFrom());

        // Theirs: another vendor plausibly answers.
        $this->assertTrue((new AiProviderException('busy', 429))->isWorthFallingBackFrom());
        $this->assertTrue((new AiProviderException('down', 503))->isWorthFallingBackFrom());
        $this->assertTrue((new AiProviderException('conn', 0))->isWorthFallingBackFrom());

        // A bad key is worth trying elsewhere AND worth telling the admin.
        $keyProblem = new AiProviderException('nope', 401);
        $this->assertTrue($keyProblem->isWorthFallingBackFrom());
        $this->assertTrue($keyProblem->isCredentialProblem());
    }

    public function test_the_fallback_chain_skips_providers_with_no_key(): void
    {
        AiEngineSettings::setAnthropicKey(null);
        AiProviderRegistry::flush();
        AiEngineSettings::setProviderFallbackOrder(['anthropic', 'openrouter']);

        $chain = AiProviderRegistry::fallbackChain('openai');

        $this->assertSame('openai', $chain[0], 'the requested provider is always tried first');
        $this->assertNotContains('anthropic', $chain, 'a provider with no key cannot answer');
        $this->assertContains('openrouter', $chain);
    }

    // ── per-plan, per-module model selection ──────────────────────

    public function test_a_plans_model_beats_the_site_wide_default_for_that_module(): void
    {
        $pro = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-' . Str::random(4),
            'monthly_price' => 19, 'annual_price' => 190,
            'trial_days' => 0, 'status' => 'active', 'features' => [],
        ]);

        AiEngineSettings::setFeatureModels(['coach' => 'gpt-4o-mini']);
        AiEngineSettings::setPlanFeatureModels([
            $pro->slug => ['coach' => 'claude-sonnet-4-latest'],
        ]);

        $onPro  = $this->user($pro);
        $onFree = $this->user();

        $this->assertSame('claude-sonnet-4-latest', AiEngineSettings::featureModel('coach', $onPro));
        $this->assertSame('gpt-4o-mini', AiEngineSettings::featureModel('coach', $onFree));
    }

    public function test_an_empty_cell_means_no_opinion_rather_than_an_empty_model_name(): void
    {
        AiEngineSettings::setFeatureModels(['coach' => 'gpt-4o-mini']);
        AiEngineSettings::setPlanFeatureModels(['pro' => ['coach' => '   ']]);

        // Storing the blank would leave '' as a model name and fail every
        // call for that plan.
        $this->assertSame([], AiEngineSettings::planFeatureModels());
        $this->assertNull(AiEngineSettings::planFeatureModel('pro', 'coach'));
    }

    public function test_a_plan_pointing_at_a_disabled_model_falls_back_instead_of_failing(): void
    {
        AiEngineSettings::setFeatureModels(['coach' => 'gpt-4o-mini']);
        AiEngineSettings::setPlanFeatureModels(['pro' => ['coach' => 'claude-sonnet-4-latest']]);

        // The admin disables that model months after wiring it into the plan.
        AiEngineSettings::setModels([
            ['name' => 'gpt-4o-mini', 'kind' => 'chat', 'enabled' => true, 'provider' => 'openai',
             'in_coins_per_1k' => 0, 'out_coins_per_1k' => 0],
            ['name' => 'claude-sonnet-4-latest', 'kind' => 'chat', 'enabled' => false, 'provider' => 'anthropic',
             'in_coins_per_1k' => 0, 'out_coins_per_1k' => 0],
        ]);

        // One stale cell must not take every user on that plan down with it.
        $this->assertNull(AiEngineSettings::planFeatureModel('pro', 'coach'));
    }

    public function test_the_fallback_model_for_a_provider_is_picked_automatically_when_unset(): void
    {
        // A chain nobody configured a second grid for should still work.
        $this->assertSame('claude-sonnet-4-latest', AiEngineSettings::fallbackModelFor('anthropic'));
        $this->assertSame('anthropic/claude-sonnet-4', AiEngineSettings::fallbackModelFor('openrouter'));
    }

    // ── the admin surface ─────────────────────────────────────────

    private function admin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'staff-settings-manage'],
            ['name' => 'Staff (settings.manage)', 'guard' => 'admin'],
        );
        $perm = Permission::firstOrCreate(
            ['slug' => 'settings.manage'],
            ['name' => 'settings.manage', 'group' => 'settings'],
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        return Admin::create([
            'name'     => 'Admin ' . Str::random(4),
            'email'    => 'a' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    public function test_the_admin_page_offers_every_provider_and_the_plan_grid(): void
    {
        Plan::create([
            'name' => 'Pro', 'slug' => 'pro-' . Str::random(4),
            'monthly_price' => 19, 'annual_price' => 190,
            'trial_days' => 0, 'status' => 'active', 'features' => [],
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/ai-engine')
            ->assertOk()
            ->assertSee('openrouter_api_key', false)
            ->assertSee('anthropic_api_key', false)
            ->assertSee('provider_fallback[]', false)
            ->assertSee('plan_feature_models[', false)
            // Each model row names the vendor that serves it.
            ->assertSee('[provider]', false);
    }

    public function test_saving_the_grid_stores_only_the_cells_that_were_filled(): void
    {
        $pro = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-' . Str::random(4),
            'monthly_price' => 19, 'annual_price' => 190,
            'trial_days' => 0, 'status' => 'active', 'features' => [],
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->put('/admin/ai-engine', [
                'enabled'             => 1,
                'provider_fallback'   => ['anthropic', 'openrouter'],
                'plan_feature_models' => [
                    $pro->slug => [
                        'coach'   => 'claude-sonnet-4-latest',
                        // Left on "Default" in the UI. Storing it would put
                        // an empty string where a model name belongs.
                        'companion' => '',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(
            [$pro->slug => ['coach' => 'claude-sonnet-4-latest']],
            AiEngineSettings::planFeatureModels(),
        );
        $this->assertSame(['anthropic', 'openrouter'], AiEngineSettings::providerFallbackOrder());
    }

    public function test_a_provider_key_is_stored_encrypted_and_never_echoed_back(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put('/admin/ai-engine', [
                'enabled'            => 1,
                'anthropic_api_key'  => 'sk-ant-secret-value',
            ])
            ->assertRedirect();

        $this->assertSame('sk-ant-secret-value', AiEngineSettings::anthropicKey());

        // At rest it is ciphertext, and the page never renders it back.
        $raw = \App\Modules\Admin\Models\AppSetting::get(AiEngineSettings::KEY_ANTHROPIC_KEY_ENC);
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('sk-ant-secret-value', $raw);

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/ai-engine')
            ->assertOk()
            ->assertDontSee('sk-ant-secret-value', false);
    }
}
