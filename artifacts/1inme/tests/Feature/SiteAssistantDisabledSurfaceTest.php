<?php

namespace Tests\Feature;

use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Models\SiteAssistantConversation;
use App\Modules\Common\Models\SiteAssistantLowBalanceClick;
use App\Modules\Common\Models\SiteAssistantMessage;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use App\Services\AI\SiteAssistantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Pins the per-surface "assistant paused" toggle on the non-streamed
 * turn ({@see SiteAssistantController::message}), the button/list
 * selection ({@see SiteAssistantController::choice}), and the live
 * handoff ({@see SiteAssistantController::handoff}).
 *
 * Admins can pause the assistant per surface via the
 * `enabled_marketing` / `enabled_app` toggles. The streamed turn path
 * already has disabled-surface coverage (SiteAssistantStreamTest), and
 * the auth gate has its own coverage (SiteAssistantAuthGateTest) — but
 * these three NON-streamed entry points had nothing proving they refuse
 * to run when their surface is toggled off.
 *
 * The risk is specifically a SIGNED-IN caller: the auth gate lets them
 * through, so the only thing standing between them and a billable turn
 * (or a fresh Contact Inbox thread for handoff) is the runtime's
 * surface-enable check. A regression there would let logged-in users
 * keep chatting to — and racking up model spend on — an assistant an
 * admin believes they have paused.
 *
 * The first three tests toggle BOTH surface flags off (mirroring the
 * admin "pause the assistant everywhere" switch), authenticate a real
 * user, and assert:
 *   - message / choice return the "currently disabled" envelope and
 *     persist NO conversation or message rows.
 *   - handoff returns the "not available here" envelope and creates NO
 *     Contact Inbox thread.
 *   - session ({@see SiteAssistantController::session}) returns the
 *     runtime's disabled payload (`ok=false` / `is_disabled=true`) and
 *     never opens/seeds a conversation row.
 *   - OpenAI is never touched on any of these branches.
 *
 * Two further tests pin per-surface INDEPENDENCE — the asymmetric case
 * the both-off tests can't catch. With `enabled_app=false` but
 * `enabled_marketing=true`, a signed-in caller on surface=app is still
 * refused (persisting nothing), while the same caller on
 * surface=marketing completes a normal turn (OpenAI stubbed). This stops
 * a future change to `detectSurface`/`isEnabledFor` from silently
 * merging the two toggles.
 *
 * Finally, the low-balance click recorder
 * ({@see SiteAssistantController::lowBalanceClick}) is documented here as
 * an intentional EXCEPTION: it keeps recording click telemetry even when
 * the surface is paused (see the test for the rationale).
 */
class SiteAssistantDisabledSurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Engine on so the ONLY thing blocking a signed-in caller is the
        // disabled-surface check under test (not a dormant AI engine).
        AiEngineSettings::setEnabled(true);
        // Pause the assistant on every surface, as an admin would when
        // taking it offline platform-wide.
        SiteAssistantSettings::update([
            'enabled_marketing' => false,
            'enabled_app'       => false,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'name' => 'Disabled Surface Tester',
            'role' => 'user',
        ]);
    }

    /**
     * Fail the test if any code path reaches OpenAI — the disabled
     * check must short-circuit BEFORE the runtime ever bills a turn.
     */
    private function forbidOpenAi(): void
    {
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')->never();
        $mock->shouldReceive('chatStream')->never();
        $this->app->instance(OpenAiService::class, $mock);
    }

    /**
     * Stub OpenAI so a turn on a LIVE surface can complete without a
     * network call. Returns a fixed completion and records how many
     * times `chat` was invoked so the live-surface test can prove the
     * model was actually reached (the inverse of {@see forbidOpenAi}).
     *
     * @param array{count:int} $log
     */
    private function stubOpenAi(array &$log): void
    {
        $log = ['count' => 0];
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')
            ->andReturnUsing(function ($user, $model, $messages, $opts) use (&$log) {
                $log['count']++;
                return [
                    'content'       => 'Sure — here is what you can build.',
                    'tokens_in'     => 12,
                    'tokens_out'    => 7,
                    'credits_spent' => 2,
                    'model'         => $model,
                ];
            });
        $mock->shouldReceive('chatStream')->never();
        $this->app->instance(OpenAiService::class, $mock);
    }

    public function test_message_is_refused_for_signed_in_user_when_surface_disabled(): void
    {
        $this->forbidOpenAi();

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->postJson(route('site-assistant.message'), [
            'visitor_token' => $token,
            'surface'       => 'app',
            'message'       => 'What can I build?',
            'page'          => ['route' => 'home', 'path' => '/'],
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok'    => false,
            'error' => 'The assistant is currently disabled.',
        ]);

        // The disabled check fires before any DB write — no conversation
        // or message row should leak from a paused surface.
        $this->assertSame(0, SiteAssistantConversation::count());
        $this->assertSame(0, SiteAssistantMessage::count());
    }

    public function test_choice_is_refused_for_signed_in_user_when_surface_disabled(): void
    {
        $this->forbidOpenAi();

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->postJson(route('site-assistant.choice'), [
            'visitor_token' => $token,
            'surface'       => 'app',
            'choice'        => ['label' => 'See plans', 'value' => 'plans'],
            'page'          => ['route' => 'home', 'path' => '/'],
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok'    => false,
            'error' => 'The assistant is currently disabled.',
        ]);

        $this->assertSame(0, SiteAssistantConversation::count());
        $this->assertSame(0, SiteAssistantMessage::count());
    }

    public function test_handoff_is_refused_for_signed_in_user_when_surface_disabled(): void
    {
        $this->forbidOpenAi();

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->postJson(route('site-assistant.handoff'), [
            'visitor_token' => $token,
            'surface'       => 'app',
            'channel'       => 'email',
            'email'         => 'reach-me@example.com',
            'message'       => 'Please get in touch.',
            'page'          => ['route' => 'home', 'path' => '/'],
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok'    => false,
            'error' => 'The assistant is not available here.',
        ]);

        // A paused surface must not be able to open a Contact Inbox
        // thread, nor seed an assistant conversation.
        $this->assertSame(0, ContactMessage::count());
        $this->assertSame(0, SiteAssistantConversation::count());
    }

    /**
     * Per-surface independence — the ASYMMETRIC case the BOTH-flags-off
     * tests above can't catch. Admins can pause `app` while leaving
     * `marketing` live. A signed-in user defaults to the `app` surface,
     * so with only `enabled_app=false` they must STILL be refused — even
     * though marketing is up. This pins that a future change to
     * `detectSurface`/`isEnabledFor` can't silently merge the two
     * toggles into one and let app callers slip through on the back of a
     * live marketing surface.
     */
    public function test_message_on_app_is_refused_while_marketing_stays_live(): void
    {
        SiteAssistantSettings::update([
            'enabled_app'       => false,
            'enabled_marketing' => true,
        ]);

        $this->forbidOpenAi();

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->postJson(route('site-assistant.message'), [
            'visitor_token' => $token,
            'surface'       => 'app',
            'message'       => 'What can I build?',
            'page'          => ['route' => 'dashboard', 'path' => '/user'],
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok'    => false,
            'error' => 'The assistant is currently disabled.',
        ]);

        // The paused `app` surface must persist nothing, even though the
        // marketing surface is live.
        $this->assertSame(0, SiteAssistantConversation::count());
        $this->assertSame(0, SiteAssistantMessage::count());
    }

    /**
     * The other half of per-surface independence: with the SAME config
     * (`app` paused, `marketing` live) a signed-in user browsing a
     * marketing page (surface=marketing) must still get a normal turn.
     * If the toggles were ever merged, this caller would be wrongly
     * refused. OpenAI is stubbed so the turn completes offline.
     */
    public function test_message_on_marketing_completes_for_signed_in_user_while_app_paused(): void
    {
        SiteAssistantSettings::update([
            'enabled_app'       => false,
            'enabled_marketing' => true,
        ]);

        $log = ['count' => 0];
        $this->stubOpenAi($log);

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->postJson(route('site-assistant.message'), [
            'visitor_token' => $token,
            'surface'       => 'marketing',
            'message'       => 'What can I build?',
            'page'          => ['route' => 'home', 'path' => '/'],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        // The live marketing surface reached the model and persisted the
        // full turn: one conversation, a user + assistant message pair.
        $this->assertSame(1, $log['count']);
        $this->assertSame(1, SiteAssistantConversation::count());
        $this->assertSame(2, SiteAssistantMessage::count());

        $conv = SiteAssistantConversation::first();
        $this->assertSame('marketing', $conv->surface);
    }

    public function test_session_is_disabled_for_signed_in_user_when_surface_disabled(): void
    {
        $this->forbidOpenAi();

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->postJson(route('site-assistant.session'), [
            'visitor_token' => $token,
            'surface'       => 'app',
            'page'          => ['route' => 'home', 'path' => '/'],
        ]);

        // openSession short-circuits to the disabled envelope BEFORE it
        // resolves (and would otherwise create) a conversation row.
        $response->assertOk();
        $response->assertJson([
            'ok'          => false,
            'is_disabled' => true,
            'error'       => 'The assistant is not available here.',
        ]);

        // A paused surface must never open or seed a conversation: the
        // session row is what every later turn hangs off, so leaking one
        // here would defeat the pause entirely.
        $this->assertSame(0, SiteAssistantConversation::count());
        $this->assertSame(0, SiteAssistantMessage::count());
    }

    /**
     * The low-balance CTA click recorder is INTENTIONALLY exempt from the
     * surface-pause toggle, and this test pins that decision.
     *
     * Unlike session/message/choice/handoff, `low-balance-click` never
     * touches the assistant runtime: it opens no conversation, sends no
     * message, creates no Contact Inbox thread, and never calls OpenAI.
     * It is a fire-and-forget keepalive POST that records a click on a
     * Top-up / See-plans link the visitor *already* saw and followed —
     * pure navigation telemetry, with no model spend or billable side
     * effect. Refusing it would only drop analytics about a real
     * click-through that has already happened, so we let it through even
     * when the surface is paused. (Anonymous callers can't reach the
     * assistant here either way — the click just isn't attributable.)
     */
    public function test_low_balance_click_is_recorded_even_when_surface_disabled(): void
    {
        $this->forbidOpenAi();

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->postJson(route('site-assistant.low-balance-click'), [
            'visitor_token' => $token,
            'surface'       => 'app',
            'target_url'    => 'https://example.com/pricing',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        // The click telemetry row is written despite the paused surface;
        // it carries no conversation (none exists) and no model cost.
        $this->assertSame(1, SiteAssistantLowBalanceClick::count());
        $click = SiteAssistantLowBalanceClick::first();
        $this->assertSame('app', $click->surface);
        $this->assertSame((int) $user->id, (int) $click->user_id);
        $this->assertNull($click->conversation_id);

        // Recording a click must NOT seed an assistant conversation.
        $this->assertSame(0, SiteAssistantConversation::count());
    }
}
