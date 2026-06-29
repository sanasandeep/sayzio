<?php

namespace Tests\Feature;

use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Models\SiteAssistantConversation;
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
 * Each test toggles BOTH surface flags off (mirroring the admin "pause
 * the assistant everywhere" switch), authenticates a real user, and
 * asserts:
 *   - message / choice return the "currently disabled" envelope and
 *     persist NO conversation or message rows.
 *   - handoff returns the "not available here" envelope and creates NO
 *     Contact Inbox thread.
 *   - OpenAI is never touched on any of these branches.
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
        return User::create([
            'name'     => 'Disabled Surface Tester',
            'email'    => 'disabled-' . Str::random(6) . '@example.com',
            'password' => bcrypt('x'),
            'status'   => 'active',
            'role'     => 'user',
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
}
