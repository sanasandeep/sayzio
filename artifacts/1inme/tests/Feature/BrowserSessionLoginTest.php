<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zio Browser web-session bridge.
 *
 * POST /api/v1/auth/browser-session (Sanctum bearer) mints a short-lived
 * signed one-time login URL; GET-ing that URL establishes a web-guard
 * session for the token's own user and redirects to the dashboard. Each
 * URL must work exactly once (nonce burn) and reject tampering.
 */
class BrowserSessionLoginTest extends TestCase
{
    use RefreshDatabase;

    private function mintLoginUrl(User $user): string
    {
        // Real bearer token — never Sanctum::actingAs (mock breaks middleware).
        $plain = $user->createToken('zio-browser')->plainTextToken;

        $resp = $this->withToken($plain)
            ->postJson('/api/v1/auth/browser-session')
            ->assertOk()
            ->assertJsonStructure(['data' => ['login_url', 'expires_in']]);

        // withToken persists as a default header onto later web requests;
        // clear it so the signed-URL GETs run as a plain browser would.
        $this->flushHeaders();

        return $resp->json('data.login_url');
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/browser-session')->assertUnauthorized();
    }

    public function test_signed_url_logs_the_user_in_once(): void
    {
        $user = User::factory()->create();
        $url  = $this->mintLoginUrl($user);

        $path = parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);

        $this->get($path)->assertRedirect('/user/dashboard');
        $this->assertAuthenticatedAs($user, 'web');

        // Second use of the same URL: nonce already burned → 403.
        $post = $this->app['auth'];
        $post->guard('web')->logout();
        $this->flushSession();
        $this->get($path)->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $url   = $this->mintLoginUrl($user);

        // Point the URL at a different user without re-signing.
        $tampered = preg_replace('/user=' . $user->id . '\b/', 'user=' . $other->id, $url);
        $path = parse_url($tampered, PHP_URL_PATH) . '?' . parse_url($tampered, PHP_URL_QUERY);

        $this->get($path)->assertStatus(403);
        $this->assertGuest('web');
    }
}
