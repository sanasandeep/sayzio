<?php

namespace Tests\Feature;

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
 * Pins the server-side login gate on the "Zio Bot" site assistant.
 *
 * The assistant is gated behind a signed-in account on EVERY surface
 * (the cross-origin marketing React widget is always anonymous, so a
 * UI-only gate is bypassable — the backend is authoritative). The
 * controller returns 401 on message/stream/choice/handoff for an
 * anonymous caller, and bootstrap advertises `auth_required` +
 * `login_url` + the localized note so both front-ends can swap the
 * composer for a login CTA.
 *
 * A regression here would silently re-open the assistant — and its
 * billing-charged model spend — to the entire public, so this test
 * asserts both halves of the contract:
 *
 *   - Every chat-driving endpoint rejects anonymous callers with 401
 *     and the `auth_required` envelope (and never touches OpenAI or
 *     persists a conversation/message).
 *   - bootstrap exposes auth_required + login_url + a non-empty note
 *     for anonymous visitors, and clears auth_required once signed in.
 *   - A signed-in user can still complete a normal (non-streamed) turn.
 */
class SiteAssistantAuthGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The assistant ships enabled on both surfaces by default; the
        // engine must be on so the only thing standing between an
        // anonymous caller and a turn is the auth gate under test.
        AiEngineSettings::setEnabled(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'name' => 'Gate Tester',
            'role' => 'user',
        ]);
    }

    /**
     * Fail the test if any code path reaches OpenAI — the auth gate
     * must short-circuit BEFORE the runtime is ever invoked, so an
     * anonymous caller can never cause billable model spend.
     */
    private function forbidOpenAi(): void
    {
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')->never();
        $mock->shouldReceive('chatStream')->never();
        $this->app->instance(OpenAiService::class, $mock);
    }

    public function test_anonymous_message_is_rejected_with_auth_required(): void
    {
        $this->forbidOpenAi();

        $response = $this->postJson(route('site-assistant.message'), [
            'visitor_token' => 'sa_' . Str::random(28),
            'message'       => 'Tell me about pricing.',
            'page'          => ['route' => 'home', 'path' => '/'],
        ]);

        $response->assertStatus(401);
        $response->assertJson(['ok' => false, 'auth_required' => true]);
        $this->assertStringContainsString('/login', (string) $response->json('login_url'));
        $this->assertNotEmpty($response->json('error'));

        // The gate fires before any DB write — no leaked conversation
        // or message row from an unauthenticated caller.
        $this->assertSame(0, SiteAssistantConversation::count());
        $this->assertSame(0, SiteAssistantMessage::count());
    }

    public function test_anonymous_stream_is_rejected_with_auth_required(): void
    {
        $this->forbidOpenAi();

        $response = $this->postJson(route('site-assistant.stream'), [
            'visitor_token' => 'sa_' . Str::random(28),
            'message'       => 'Stream me an answer.',
            'page'          => ['route' => 'home', 'path' => '/'],
        ]);

        $response->assertStatus(401);
        $response->assertJson(['ok' => false, 'auth_required' => true]);
        $this->assertStringContainsString('/login', (string) $response->json('login_url'));
        $this->assertNotEmpty($response->json('error'));

        $this->assertSame(0, SiteAssistantConversation::count());
        $this->assertSame(0, SiteAssistantMessage::count());
    }

    public function test_anonymous_choice_is_rejected_with_auth_required(): void
    {
        $this->forbidOpenAi();

        $response = $this->postJson(route('site-assistant.choice'), [
            'visitor_token' => 'sa_' . Str::random(28),
            'choice'        => ['label' => 'See plans', 'value' => 'plans'],
            'page'          => ['route' => 'home', 'path' => '/'],
        ]);

        $response->assertStatus(401);
        $response->assertJson(['ok' => false, 'auth_required' => true]);
        $this->assertStringContainsString('/login', (string) $response->json('login_url'));
        $this->assertNotEmpty($response->json('error'));

        $this->assertSame(0, SiteAssistantConversation::count());
        $this->assertSame(0, SiteAssistantMessage::count());
    }

    public function test_anonymous_handoff_is_rejected_with_auth_required(): void
    {
        $this->forbidOpenAi();

        $response = $this->postJson(route('site-assistant.handoff'), [
            'visitor_token' => 'sa_' . Str::random(28),
            'channel'       => 'email',
            'email'         => 'visitor@example.com',
            'message'       => 'Please call me back.',
            'page'          => ['route' => 'home', 'path' => '/'],
        ]);

        $response->assertStatus(401);
        $response->assertJson(['ok' => false, 'auth_required' => true]);
        $this->assertStringContainsString('/login', (string) $response->json('login_url'));
        $this->assertNotEmpty($response->json('error'));

        // No Contact Inbox thread should be created from an anonymous
        // assistant handoff (the standalone quick-contact widget is the
        // anonymous path — not this one).
        $this->assertSame(0, SiteAssistantConversation::count());
    }

    public function test_bootstrap_advertises_auth_required_for_anonymous_visitor(): void
    {
        $response = $this->getJson(route('site-assistant.bootstrap'));

        $response->assertOk();
        $response->assertJson([
            'enabled'       => true,
            'auth_required' => true,
        ]);
        $this->assertStringContainsString('/login', (string) $response->json('login_url'));
        $this->assertNotEmpty($response->json('auth_required_note'));
    }

    public function test_bootstrap_clears_auth_required_for_signed_in_user(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->getJson(route('site-assistant.bootstrap'));

        $response->assertOk();
        $response->assertJson([
            'enabled'       => true,
            'auth_required' => false,
        ]);
    }

    public function test_signed_in_user_can_complete_a_normal_turn(): void
    {
        // Stub OpenAI so the turn resolves without a network call; the
        // point of this test is that an AUTHENTICATED caller passes the
        // gate and drives a full turn end-to-end.
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content'       => 'Sayzio lets you build biolinks and short links.',
                'tokens_in'     => 12,
                'tokens_out'    => 9,
                'credits_spent' => 4,
                'model'         => 'gpt-test',
            ]);
        $this->app->instance(OpenAiService::class, $mock);

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->postJson(route('site-assistant.message'), [
            'visitor_token' => $token,
            'surface'       => 'app',
            'message'       => 'What can I build?',
            'page'          => ['route' => 'home', 'path' => '/'],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertSame(
            'Sayzio lets you build biolinks and short links.',
            $response->json('assistant_message.content')
        );

        // The turn was persisted against the signed-in user's
        // conversation: one user row + one assistant row.
        $conv = SiteAssistantConversation::where('visitor_token', $token)->firstOrFail();
        $this->assertSame((int) $user->id, (int) $conv->bound_user_id);

        $messages = SiteAssistantMessage::where('conversation_id', $conv->id)
            ->orderBy('id')->get();
        $this->assertCount(2, $messages);
        $this->assertSame('user',            $messages[0]->role);
        $this->assertSame('What can I build?', $messages[0]->content);
        $this->assertSame('assistant',       $messages[1]->role);
    }
}
