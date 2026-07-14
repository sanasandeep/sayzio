<?php

namespace Tests\Feature\AI;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AskCoachMessage;
use App\Modules\User\Models\AskCoachThread;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the AI Coach advanced admin settings hub (/admin/ask-coach).
 *
 * Covers:
 *   1. Admin can save and reset each settings group.
 *   2. Cooldown enforcement short-circuits send() before message creation.
 *   3. Per-plan daily cap enforcement.
 *   4. Per-plan monthly cap enforcement.
 *   5. Banned-topic decline fires before any AI call.
 *   6. Snapshot category exclusion filters the enabled-tool set.
 *   7. Blank/null settings always fall back to the platform default.
 *   8. Greeting is emitted as the first message on new thread creation.
 *   9. Behavior directives are appended to the system prompt.
 */
class AskCoachAdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('sk-test-key');
    }

    // ── helpers ───────────────────────────────────────────────────

    private function admin(): Admin
    {
        return Admin::create([
            'name'     => 'Admin ' . Str::random(4),
            'email'    => 'a' . Str::random(8) . '@admin.test',
            'password' => Hash::make('adminpass'),
        ]);
    }

    private function plan(string $slug = '', array $features = []): Plan
    {
        $slug = $slug ?: 'p' . Str::lower(Str::random(8));
        return Plan::create([
            'name'          => $slug,
            'slug'          => $slug,
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => array_merge(['ask_coach' => true], $features),
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => ($plan ?? $this->plan())->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function thread(User $user): AskCoachThread
    {
        return AskCoachThread::create([
            'user_id'      => $user->id,
            'workspace_id' => app()->bound('current_workspace') ? (int) app('current_workspace')->id : null,
            'title'        => 'Test chat',
        ]);
    }

    private function seedMessage(AskCoachThread $thread, string $role = 'user', ?string $createdAt = null): AskCoachMessage
    {
        return AskCoachMessage::create([
            'thread_id'  => $thread->id,
            'role'       => $role,
            'content'    => 'hello',
            'created_at' => $createdAt ?? now()->toDateTimeString(),
        ]);
    }

    // ── 1. Admin settings save / reset ────────────────────────────

    public function test_admin_can_save_behavior_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.ask-coach.update'), [
                'tone'            => 'professional',
                'response_length' => 'short',
                'reply_language'  => 'es',
                'temperature'     => '0.8',
            ])
            ->assertRedirect(route('admin.ask-coach.index'));

        $this->assertSame('professional', AiEngineSettings::askCoachTone());
        $this->assertSame('short', AiEngineSettings::askCoachResponseLength());
        $this->assertSame('es', AiEngineSettings::askCoachReplyLanguage());
        $this->assertEqualsWithDelta(0.8, AiEngineSettings::askCoachTemperature(), 0.001);
    }

    public function test_admin_can_save_usage_limit_settings(): void
    {
        $admin = $this->admin();
        $plan  = $this->plan('gold');

        $this->actingAs($admin, 'admin')
            ->put(route('admin.ask-coach.update'), [
                'plan_caps' => [
                    'gold' => ['period' => 'daily', 'cap' => '50'],
                    'free' => ['period' => 'monthly', 'cap' => '10'],
                ],
                'cooldown_seconds'  => '30',
                'credit_multiplier' => '1.5',
            ])
            ->assertRedirect();

        $caps = AiEngineSettings::askCoachPlanCaps();
        $this->assertSame(['period' => 'daily', 'cap' => 50], $caps['gold']);
        $this->assertSame(['period' => 'monthly', 'cap' => 10], $caps['free']);
        $this->assertSame(30, AiEngineSettings::askCoachCooldownSeconds());
        $this->assertEqualsWithDelta(1.5, AiEngineSettings::askCoachCreditMultiplier(), 0.001);
    }

    public function test_admin_can_save_content_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.ask-coach.update'), [
                'banned_topics'   => "crypto\ngambling",
                'greeting'        => 'Hello from Coach!',
                'fallback_message' => 'Something went wrong.',
                'escalation_note' => 'Contact support@example.com',
            ])
            ->assertRedirect();

        $this->assertSame(['crypto', 'gambling'], AiEngineSettings::askCoachBannedTopics());
        $this->assertSame('Hello from Coach!', AiEngineSettings::askCoachGreeting());
        $this->assertSame('Something went wrong.', AiEngineSettings::askCoachFallbackMessage());
        $this->assertSame('Contact support@example.com', AiEngineSettings::askCoachEscalationNote());
    }

    public function test_admin_can_save_model_data_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.ask-coach.update'), [
                'max_tokens'          => '800',
                'snapshot_categories' => ['links', 'analytics'],
            ])
            ->assertRedirect();

        $this->assertSame(800, AiEngineSettings::askCoachMaxTokens());
        $this->assertSame(['links', 'analytics'], AiEngineSettings::askCoachSnapshotCategories());
    }

    public function test_blank_settings_restore_platform_defaults(): void
    {
        // Pre-seed non-default values
        AiEngineSettings::setAskCoachTone('concise');
        AiEngineSettings::setAskCoachCooldownSeconds(60);
        AiEngineSettings::setAskCoachBannedTopics(['bad word']);

        $admin = $this->admin();

        // Submitting blanks / empty arrays resets to defaults
        $this->actingAs($admin, 'admin')
            ->put(route('admin.ask-coach.update'), [
                'tone'             => '',
                'cooldown_seconds' => '0',
                'banned_topics'    => '',
            ])
            ->assertRedirect();

        // Defaults kick back in
        $this->assertSame('friendly', AiEngineSettings::askCoachTone());
        $this->assertSame(0, AiEngineSettings::askCoachCooldownSeconds());
        $this->assertSame([], AiEngineSettings::askCoachBannedTopics());
    }

    // ── 2. Cooldown enforcement ────────────────────────────────────

    public function test_cooldown_blocks_send_within_window(): void
    {
        AiEngineSettings::setAskCoachCooldownSeconds(60);

        $user   = $this->user();
        $thread = $this->thread($user);
        $this->seedMessage($thread, 'user', now()->subSeconds(10)->toDateTimeString());

        $resp = $this->actingAs($user, 'web')
            ->post(route('user.ai.ask-coach.send', $thread->id), [
                'message' => 'Another question',
            ]);

        // Should redirect back with an error, not proceed to AI
        $resp->assertSessionHasErrors([], null, 'default');
        // Cooldown error surfaced via session 'error' flash key
        $this->assertTrue(
            $resp->isRedirect() || $resp->status() === 422,
            'Expected a redirect or 422 when cooldown active'
        );

        // No new user message should have been written
        $this->assertSame(1, AskCoachMessage::where('thread_id', $thread->id)->where('role', 'user')->count());
    }

    public function test_cooldown_passes_after_window_expires(): void
    {
        AiEngineSettings::setAskCoachCooldownSeconds(30);

        $user   = $this->user();
        $thread = $this->thread($user);
        // Last message was 60s ago — outside the 30s window
        $this->seedMessage($thread, 'user', now()->subSeconds(60)->toDateTimeString());

        // The preflight check should NOT block (pass = null return)
        $controller = $this->app->make(\App\Modules\User\Controllers\AI\AskCoachController::class);
        $request    = \Illuminate\Http\Request::create('/send', 'POST');
        $request->setUserResolver(fn() => $user);

        $result = $this->callProtected($controller, 'checkPreflightErrors', [$request, $user, 'follow up']);
        $this->assertNull($result, 'Cooldown guard should return null when the window has expired');
    }

    // ── 3. Per-plan daily cap enforcement ─────────────────────────

    public function test_daily_plan_cap_blocks_when_limit_reached(): void
    {
        $plan = $this->plan('starter');
        AiEngineSettings::setAskCoachPlanCaps(['starter' => ['period' => 'daily', 'cap' => 2]]);

        $user   = $this->user($plan);
        $thread = $this->thread($user);

        // Seed 2 user messages today — cap is reached
        $this->seedMessage($thread, 'user', now()->toDateTimeString());
        $this->seedMessage($thread, 'user', now()->toDateTimeString());

        $controller = $this->app->make(\App\Modules\User\Controllers\AI\AskCoachController::class);
        $request    = \Illuminate\Http\Request::create('/send', 'POST');
        $request->setUserResolver(fn() => $user);

        $result = $this->callProtected($controller, 'checkPreflightErrors', [$request, $user, 'third message']);
        $this->assertNotNull($result, 'Plan cap guard should block the request when limit reached');
    }

    public function test_daily_plan_cap_allows_when_under_limit(): void
    {
        $plan = $this->plan('starter');
        AiEngineSettings::setAskCoachPlanCaps(['starter' => ['period' => 'daily', 'cap' => 5]]);

        $user   = $this->user($plan);
        $thread = $this->thread($user);
        $this->seedMessage($thread, 'user', now()->toDateTimeString());

        $controller = $this->app->make(\App\Modules\User\Controllers\AI\AskCoachController::class);
        $request    = \Illuminate\Http\Request::create('/send', 'POST');
        $request->setUserResolver(fn() => $user);

        $result = $this->callProtected($controller, 'checkPreflightErrors', [$request, $user, 'second message']);
        $this->assertNull($result, 'Plan cap guard should pass when under the limit');
    }

    // ── 4. Per-plan monthly cap enforcement ───────────────────────

    public function test_monthly_plan_cap_counts_messages_in_current_month(): void
    {
        $plan = $this->plan('pro');
        AiEngineSettings::setAskCoachPlanCaps(['pro' => ['period' => 'monthly', 'cap' => 1]]);

        $user   = $this->user($plan);
        $thread = $this->thread($user);
        // Seed 1 message this month
        $this->seedMessage($thread, 'user', now()->startOfMonth()->addHours(2)->toDateTimeString());

        $controller = $this->app->make(\App\Modules\User\Controllers\AI\AskCoachController::class);
        $request    = \Illuminate\Http\Request::create('/send', 'POST');
        $request->setUserResolver(fn() => $user);

        $result = $this->callProtected($controller, 'checkPreflightErrors', [$request, $user, 'second this month']);
        $this->assertNotNull($result, 'Monthly plan cap should block when limit is reached');
    }

    public function test_monthly_plan_cap_ignores_messages_from_last_month(): void
    {
        $plan = $this->plan('pro');
        AiEngineSettings::setAskCoachPlanCaps(['pro' => ['period' => 'monthly', 'cap' => 2]]);

        $user   = $this->user($plan);
        $thread = $this->thread($user);
        // Message from last month — outside the monthly window
        $this->seedMessage($thread, 'user', now()->subMonth()->toDateTimeString());

        $controller = $this->app->make(\App\Modules\User\Controllers\AI\AskCoachController::class);
        $request    = \Illuminate\Http\Request::create('/send', 'POST');
        $request->setUserResolver(fn() => $user);

        $result = $this->callProtected($controller, 'checkPreflightErrors', [$request, $user, 'fresh message']);
        $this->assertNull($result, 'Monthly cap should not count messages from the previous month');
    }

    // ── 5. Banned-topic enforcement ───────────────────────────────

    public function test_banned_topic_declines_matching_message(): void
    {
        AiEngineSettings::setAskCoachBannedTopics(['crypto', 'gambling']);

        $user       = $this->user();
        $controller = $this->app->make(\App\Modules\User\Controllers\AI\AskCoachController::class);
        $request    = \Illuminate\Http\Request::create('/send', 'POST');
        $request->setUserResolver(fn() => $user);

        $result = $this->callProtected($controller, 'checkPreflightErrors', [$request, $user, 'tell me about crypto investing']);
        $this->assertNotNull($result, 'Banned topic guard should block the message');
    }

    public function test_banned_topic_is_case_insensitive(): void
    {
        AiEngineSettings::setAskCoachBannedTopics(['Crypto']);

        $user       = $this->user();
        $controller = $this->app->make(\App\Modules\User\Controllers\AI\AskCoachController::class);
        $request    = \Illuminate\Http\Request::create('/send', 'POST');
        $request->setUserResolver(fn() => $user);

        $result = $this->callProtected($controller, 'checkPreflightErrors', [$request, $user, 'CRYPTO advice please']);
        $this->assertNotNull($result, 'Banned topic check must be case-insensitive');
    }

    public function test_banned_topic_escalation_note_is_included_in_decline(): void
    {
        AiEngineSettings::setAskCoachBannedTopics(['hacking']);
        AiEngineSettings::setAskCoachEscalationNote('Please email support@example.com for help.');

        $user       = $this->user();
        $controller = $this->app->make(\App\Modules\User\Controllers\AI\AskCoachController::class);
        $request    = \Illuminate\Http\Request::create('/send', 'POST');
        $request->setUserResolver(fn() => $user);
        $request->headers->set('Accept', 'application/json');

        $result = $this->callProtected($controller, 'checkPreflightErrors', [$request, $user, 'hacking tips']);
        $this->assertNotNull($result);
        $content = $result->getContent();
        $this->assertStringContainsString('support@example.com', $content, 'Escalation note should appear in the decline response');
    }

    public function test_clean_message_passes_banned_topic_check(): void
    {
        AiEngineSettings::setAskCoachBannedTopics(['crypto', 'hacking']);

        $user       = $this->user();
        $controller = $this->app->make(\App\Modules\User\Controllers\AI\AskCoachController::class);
        $request    = \Illuminate\Http\Request::create('/send', 'POST');
        $request->setUserResolver(fn() => $user);

        $result = $this->callProtected($controller, 'checkPreflightErrors', [$request, $user, 'How do I add a biolink?']);
        $this->assertNull($result, 'Clean messages must pass the banned-topic guard');
    }

    // ── 6. Snapshot category exclusion ────────────────────────────

    public function test_enabled_tools_returns_all_when_categories_empty(): void
    {
        AiEngineSettings::setAskCoachSnapshotCategories([]);

        $controller   = $this->app->make(\App\Modules\User\Controllers\AI\AskCoachController::class);
        $enabledTools = $this->callProtected($controller, 'enabledTools', []);

        $allToolNames = array_keys(
            $this->app->make(\App\Services\AI\AskCoach\AskCoachToolRegistry::class)->tools()
        );
        sort($enabledTools);
        sort($allToolNames);
        $this->assertSame($allToolNames, $enabledTools, 'Empty categories setting should enable all tools');
    }

    public function test_enabled_tools_filters_by_selected_categories(): void
    {
        AiEngineSettings::setAskCoachSnapshotCategories(['links', 'analytics']);

        $controller   = $this->app->make(\App\Modules\User\Controllers\AI\AskCoachController::class);
        $enabledTools = $this->callProtected($controller, 'enabledTools', []);

        $this->assertContains('biolinks',  $enabledTools);
        $this->assertContains('links',     $enabledTools);
        $this->assertContains('analytics', $enabledTools);
        $this->assertNotContains('payments',     $enabledTools, 'billing category not enabled');
        $this->assertNotContains('account',      $enabledTools, 'billing category not enabled');
        $this->assertNotContains('audience',     $enabledTools, 'audience category not enabled');
        $this->assertNotContains('event_lookup', $enabledTools, 'events category not enabled');
    }

    public function test_filtered_function_definitions_excludes_disabled_category(): void
    {
        // Only enable analytics; billing (payments + account) must be absent
        AiEngineSettings::setAskCoachSnapshotCategories(['analytics']);

        $controller = $this->app->make(\App\Modules\User\Controllers\AI\AskCoachController::class);
        $defs       = $this->callProtected($controller, 'filteredFunctionDefinitions', []);

        $names = array_column(array_column($defs, 'function'), 'name');
        $this->assertContains('analytics', $names);
        $this->assertNotContains('payments',     $names);
        $this->assertNotContains('account',      $names);
        $this->assertNotContains('event_lookup', $names);
    }

    // ── 7. Blank settings fall back to platform defaults ──────────

    public function test_temperature_defaults_when_not_set(): void
    {
        AppSetting::where('key', AiEngineSettings::KEY_ASK_COACH_TEMPERATURE)->delete();
        Cache::forget('app_setting:' . AiEngineSettings::KEY_ASK_COACH_TEMPERATURE);

        $this->assertEqualsWithDelta(
            AiEngineSettings::DEFAULT_ASK_COACH_TEMPERATURE,
            AiEngineSettings::askCoachTemperature(),
            0.001
        );
    }

    public function test_max_tokens_defaults_when_not_set(): void
    {
        AppSetting::where('key', AiEngineSettings::KEY_ASK_COACH_MAX_TOKENS)->delete();
        Cache::forget('app_setting:' . AiEngineSettings::KEY_ASK_COACH_MAX_TOKENS);

        $this->assertSame(AiEngineSettings::DEFAULT_ASK_COACH_MAX_TOKENS, AiEngineSettings::askCoachMaxTokens());
    }

    public function test_tone_defaults_to_friendly_when_not_set(): void
    {
        AppSetting::where('key', AiEngineSettings::KEY_ASK_COACH_TONE)->delete();
        Cache::forget('app_setting:' . AiEngineSettings::KEY_ASK_COACH_TONE);

        $this->assertSame('friendly', AiEngineSettings::askCoachTone());
    }

    public function test_reply_language_defaults_to_match_user_when_not_set(): void
    {
        AppSetting::where('key', AiEngineSettings::KEY_ASK_COACH_REPLY_LANGUAGE)->delete();
        Cache::forget('app_setting:' . AiEngineSettings::KEY_ASK_COACH_REPLY_LANGUAGE);

        $this->assertSame('match_user', AiEngineSettings::askCoachReplyLanguage());
    }

    // ── 8. Greeting is emitted on new thread creation ─────────────

    public function test_greeting_is_created_as_first_message_on_store(): void
    {
        AiEngineSettings::setAskCoachGreeting('Welcome to Coach! How can I help?');

        $user = $this->user();

        $this->actingAs($user, 'web')
            ->post(route('user.ai.ask-coach.store'))
            ->assertRedirect();

        // A thread was created
        $thread = AskCoachThread::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($thread);

        // And it has an assistant message with the greeting content
        $greeting = AskCoachMessage::where('thread_id', $thread->id)
            ->where('role', 'assistant')
            ->first();
        $this->assertNotNull($greeting, 'Greeting message should be persisted');
        $this->assertSame('Welcome to Coach! How can I help?', $greeting->content);
        $this->assertTrue((bool) ($greeting->meta['is_greeting'] ?? false));
    }

    public function test_no_greeting_message_when_greeting_is_blank(): void
    {
        AiEngineSettings::setAskCoachGreeting(null);

        $user = $this->user();

        $this->actingAs($user, 'web')
            ->post(route('user.ai.ask-coach.store'))
            ->assertRedirect();

        $thread = AskCoachThread::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($thread);
        $this->assertSame(0, AskCoachMessage::where('thread_id', $thread->id)->count());
    }

    // ── 9. Behavior directives appended to system prompt ──────────

    public function test_professional_tone_adds_directive_to_system_prompt(): void
    {
        AiEngineSettings::setAskCoachTone('professional');

        $directives = AiEngineSettings::askCoachBehaviorDirectives();
        $this->assertStringContainsString('professional', strtolower($directives));
    }

    public function test_short_length_adds_word_cap_directive(): void
    {
        AiEngineSettings::setAskCoachResponseLength('short');

        $directives = AiEngineSettings::askCoachBehaviorDirectives();
        $this->assertStringContainsString('short', strtolower($directives));
    }

    public function test_non_default_language_adds_language_directive(): void
    {
        AiEngineSettings::setAskCoachReplyLanguage('fr');

        $directives = AiEngineSettings::askCoachBehaviorDirectives();
        $this->assertStringContainsString('fr', $directives);
    }

    public function test_all_defaults_produce_empty_directives(): void
    {
        // Explicitly set to defaults
        AiEngineSettings::setAskCoachTone('friendly');
        AiEngineSettings::setAskCoachResponseLength('medium');
        AiEngineSettings::setAskCoachReplyLanguage('match_user');

        // Friendly + medium + match_user → no directives needed
        $directives = AiEngineSettings::askCoachBehaviorDirectives();
        $this->assertSame('', $directives, 'Default behavior should produce no directives');
    }

    // ── helpers ───────────────────────────────────────────────────

    /**
     * Call a protected method on an object via reflection so tests can
     * exercise internal helpers without an HTTP round-trip.
     */
    private function callProtected(object $obj, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($obj, $args);
    }
}
