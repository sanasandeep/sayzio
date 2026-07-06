<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end regression coverage for the mobile OAuth round-trip.
 *
 * The mobile app's primary social-sign-in path is:
 *
 *   native SDK on device  ->  POST /auth/social  ->  session token  ->  tabs
 *
 * For Google the device returns an id_token from expo-auth-session; for Apple
 * the device returns an id_token from Sign in with Apple. Both are POSTed to
 * /auth/social where the backend verifies them against the provider's public
 * keys / hosted tokeninfo endpoint and mints a personal access token.
 *
 * Doing a *real* round-trip in CI requires real Google/Apple OAuth client
 * credentials and a real device — which we do not have in the test sandbox.
 * Instead we stub the upstream providers (HTTP::fake for Google, an in-test
 * RSA keypair + JWKS fake for Apple) and assert the rest of the round-trip
 * behaves end-to-end: token verified, user resolved/created, identifier
 * linked, Sanctum token returned.
 *
 * The 1inme://oauth-callback redirect URI is exercised separately on the
 * WebBrowser entry endpoint; it lives outside the auth middleware in every
 * environment and accepts the mobile source/return query without 4xx.
 */
class MobileSocialAuthRoundTripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The /auth/social handler stamps newly-created users with the
        // 'free' plan id; seed a default free plan so user creation works.
        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);

        // Pin the GOOGLE_CLIENT_ID so the controller's aud check has a
        // concrete value to compare against (and we can fake it).
        putenv('GOOGLE_CLIENT_ID=test-google-client.apps.googleusercontent.com');
        putenv('APPLE_BUNDLE_ID=com.oneinme.app.test');
    }

    /** Helper: build a user with a known email. */
    private function makeUser(string $email): User
    {
        return User::factory()->create([
            'email' => strtolower($email),
        ]);
    }

    // ----------------------------------------------------------------- Google

    #[\PHPUnit\Framework\Attributes\Test]
    public function google_native_round_trip_creates_a_new_user_and_returns_a_session_token(): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'iss'     => 'https://accounts.google.com',
                'aud'     => 'test-google-client.apps.googleusercontent.com',
                'sub'     => 'g-1001',
                'email'   => 'newcomer@example.com',
                'name'    => 'New Comer',
                'picture' => 'https://example.com/a.png',
                'exp'     => time() + 3600,
            ], 200),
        ]);

        $resp = $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'id_token' => 'fake-google-id-token',
            'device'   => 'mobile-test',
        ]);

        $resp->assertOk();
        $resp->assertJsonStructure(['data' => ['user', 'token', 'created']]);
        $this->assertTrue($resp->json('data.created'));
        $this->assertNotEmpty($resp->json('data.token'));

        $user = User::where('email', 'newcomer@example.com')->first();
        $this->assertNotNull($user, 'user should have been created');
        $this->assertDatabaseHas('linked_identifiers', [
            'user_id'     => $user->id,
            'kind'        => 'social',
            'provider'    => 'google',
            'external_id' => 'g-1001',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function google_native_round_trip_signs_in_an_existing_user_by_verified_email(): void
    {
        $user = $this->makeUser('returning@example.com');

        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'iss'   => 'https://accounts.google.com',
                'aud'   => 'test-google-client.apps.googleusercontent.com',
                'sub'   => 'g-2002',
                'email' => 'returning@example.com',
                'exp'   => time() + 3600,
            ], 200),
        ]);

        $resp = $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'id_token' => 'fake-google-id-token',
        ]);

        $resp->assertOk();
        $this->assertFalse($resp->json('data.created'));
        $this->assertSame($user->id, $resp->json('data.user.id'));
        $this->assertDatabaseHas('linked_identifiers', [
            'user_id'  => $user->id,
            'provider' => 'google',
            'kind'     => 'social',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function google_round_trip_rejects_aud_mismatch_so_a_token_for_another_app_cannot_log_in(): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'iss'   => 'https://accounts.google.com',
                'aud'   => 'someone-elses-client.apps.googleusercontent.com',
                'sub'   => 'g-evil',
                'email' => 'evil@example.com',
                'exp'   => time() + 3600,
            ], 200),
        ]);

        $resp = $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'id_token' => 'fake-other-app-id-token',
        ]);

        $resp->assertStatus(401);
        $this->assertDatabaseMissing('users', ['email' => 'evil@example.com']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function google_round_trip_rejects_when_tokeninfo_endpoint_says_no(): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response(['error' => 'invalid_token'], 400),
        ]);

        $resp = $this->postJson('/api/v1/auth/social', [
            'provider' => 'google',
            'id_token' => 'totally-bogus',
        ]);

        $resp->assertStatus(401);
    }

    // ------------------------------------------------------------------ Apple

    #[\PHPUnit\Framework\Attributes\Test]
    public function apple_native_round_trip_creates_a_new_user_and_returns_a_session_token(): void
    {
        [$privateKey, $jwk] = $this->makeRsaKeyAndJwk('apple-kid-1');

        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response(['keys' => [$jwk]], 200),
        ]);

        $idToken = $this->signRs256Jwt(
            ['kid' => 'apple-kid-1', 'alg' => 'RS256', 'typ' => 'JWT'],
            [
                'iss'   => 'https://appleid.apple.com',
                'aud'   => 'com.oneinme.app.test',
                'sub'   => 'apple-uid-3003',
                'email' => 'apple-newcomer@example.com',
                'exp'   => time() + 3600,
                'iat'   => time(),
            ],
            $privateKey,
        );

        $resp = $this->postJson('/api/v1/auth/social', [
            'provider' => 'apple',
            'id_token' => $idToken,
            'device'   => 'mobile-test-apple',
        ]);

        $resp->assertOk();
        $this->assertTrue($resp->json('data.created'));
        $this->assertNotEmpty($resp->json('data.token'));

        $user = User::where('email', 'apple-newcomer@example.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('linked_identifiers', [
            'user_id'     => $user->id,
            'kind'        => 'social',
            'provider'    => 'apple',
            'external_id' => 'apple-uid-3003',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function apple_round_trip_rejects_a_token_signed_by_an_attacker_key(): void
    {
        // The JWKS we publish is keypair A...
        [, $publishedJwk] = $this->makeRsaKeyAndJwk('apple-kid-1');
        // ...but the attacker signs the JWT with keypair B (same kid).
        [$attackerKey] = $this->makeRsaKeyAndJwk('apple-kid-1');

        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response(['keys' => [$publishedJwk]], 200),
        ]);

        $idToken = $this->signRs256Jwt(
            ['kid' => 'apple-kid-1', 'alg' => 'RS256', 'typ' => 'JWT'],
            [
                'iss'   => 'https://appleid.apple.com',
                'aud'   => 'com.oneinme.app.test',
                'sub'   => 'apple-uid-evil',
                'email' => 'evil@example.com',
                'exp'   => time() + 3600,
            ],
            $attackerKey,
        );

        $resp = $this->postJson('/api/v1/auth/social', [
            'provider' => 'apple',
            'id_token' => $idToken,
        ]);

        $resp->assertStatus(401);
        $this->assertDatabaseMissing('users', ['email' => 'evil@example.com']);
    }

    // ---------------------------------------------- WebBrowser entry endpoint

    #[\PHPUnit\Framework\Attributes\Test]
    public function redirect_uri_allowlist_accepts_the_mobile_callback_scheme(): void
    {
        $this->assertSame(
            '1inme://oauth-callback',
            \App\Modules\User\Controllers\SocialOAuthController::allowedMobileReturn('1inme://oauth-callback'),
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function redirect_uri_allowlist_rejects_arbitrary_schemes_so_a_stolen_state_cannot_redirect_elsewhere(): void
    {
        foreach ([
            'evil://harvest',
            'https://attacker.example.com/oauth',
            '1inme://something-else',
            'javascript:alert(1)',
            '',
            null,
        ] as $bad) {
            $this->assertNull(
                \App\Modules\User\Controllers\SocialOAuthController::allowedMobileReturn($bad),
                'allowlist must reject ' . var_export($bad, true),
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function mobile_login_callback_round_trips_back_to_the_deep_link_with_a_session_token(): void
    {
        // Configure Facebook (a real WebBrowser provider).
        putenv('FACEBOOK_CLIENT_ID=test-fb-client');
        putenv('FACEBOOK_CLIENT_SECRET=test-fb-secret');

        // Pre-link a user to a Facebook identity that the OAuth callback
        // will resolve back to. The login-mode handler looks up users by
        // (provider, external_id) via LinkedIdentifier::resolveUser.
        $user = $this->makeUser('mobile-fb@example.com');
        LinkedIdentifier::create([
            'user_id'     => $user->id,
            'kind'        => 'social',
            'value'       => LinkedIdentifier::normalize('social', '', 'facebook', 'fb-9001'),
            'provider'    => 'facebook',
            'external_id' => 'fb-9001',
            'verified_at' => now(),
            'is_primary'  => false,
        ]);

        // 1) Mobile entry: store state + mobile-source markers in session.
        $entry = $this->get(
            '/user/social-oauth/facebook/login'
            . '?source=mobile&return=' . rawurlencode('1inme://oauth-callback')
        );
        $entry->assertStatus(302);
        $authorize = (string) $entry->headers->get('Location');
        $this->assertStringContainsString('facebook.com', $authorize);

        // Recover the state Laravel just stamped in the session.
        $state = session('social_oauth_state_facebook');
        $this->assertNotEmpty($state, 'loginConnect must persist OAuth state');
        $this->assertSame('mobile', session('social_oauth_source_facebook'));
        $this->assertSame('1inme://oauth-callback', session('social_oauth_return_facebook'));

        // 2) Stub Facebook's token + profile endpoints so fetchProfile()
        // returns the linked external_id without leaving the test.
        Http::fake([
            'graph.facebook.com/v19.0/oauth/access_token' =>
                Http::response(['access_token' => 'fb-fake-token'], 200),
            'graph.facebook.com/v19.0/me*' =>
                Http::response(['id' => 'fb-9001', 'name' => 'Mobile FB User'], 200),
        ]);

        // 3) Provider bounces back to /user/social-oauth/facebook/callback
        // with code+state — controller must redirect to the deep link
        // with token+user payload.
        $callback = $this->withSession([
            'social_oauth_state_facebook'  => $state,
            'social_oauth_mode_facebook'   => 'login',
            'social_oauth_source_facebook' => 'mobile',
            'social_oauth_return_facebook' => '1inme://oauth-callback',
        ])->get('/user/social-oauth/facebook/callback?code=fake-code&state=' . urlencode($state));

        $callback->assertStatus(302);
        $location = (string) $callback->headers->get('Location');
        $this->assertStringStartsWith('1inme://oauth-callback?', $location);

        $query = [];
        parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $query);
        $this->assertArrayHasKey('token', $query);
        $this->assertNotEmpty($query['token'], 'must return a Sanctum token');
        $this->assertArrayHasKey('user', $query);
        $userPayload = json_decode($query['user'], true);
        $this->assertSame($user->id, $userPayload['id']);
        $this->assertSame('mobile-fb@example.com', $userPayload['email']);
        $this->assertSame('facebook', $query['provider']);

        // The minted Sanctum token actually authenticates against the API.
        $me = $this->withHeader('Authorization', 'Bearer ' . $query['token'])
            ->getJson('/api/v1/me');
        $this->assertNotEquals(401, $me->status(), 'mobile bearer token must be accepted by the API');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function mobile_login_callback_with_disallowed_return_falls_back_to_the_safe_web_route(): void
    {
        putenv('FACEBOOK_CLIENT_ID=test-fb-client');
        putenv('FACEBOOK_CLIENT_SECRET=test-fb-secret');

        // Entry with a hostile `?return=` value: the allowlist must drop
        // it. Session markers should remain unset, so the callback must
        // bounce to the safe web route — never to the attacker URL.
        $entry = $this->get(
            '/user/social-oauth/facebook/login'
            . '?source=mobile&return=' . rawurlencode('https://attacker.example.com/grab')
        );
        $entry->assertStatus(302);
        $this->assertNull(session('social_oauth_return_facebook'));
        $this->assertNull(session('social_oauth_source_facebook'));

        // A subsequent error callback (no code) must NOT redirect to
        // the attacker URL — it falls back to the user-login route.
        $state = session('social_oauth_state_facebook');
        $callback = $this->withSession([
            'social_oauth_state_facebook' => $state,
            'social_oauth_mode_facebook'  => 'login',
        ])->get('/user/social-oauth/facebook/callback?error=access_denied&state=' . urlencode($state));

        $callback->assertStatus(302);
        $location = (string) $callback->headers->get('Location');
        $this->assertStringNotContainsString('attacker.example.com', $location);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function web_browser_oauth_login_entry_is_publicly_reachable_for_the_mobile_callback_scheme(): void
    {
        // Pin Facebook client creds so isConfigured('facebook') is true
        // and the controller redirects to the provider authorize URL
        // instead of bouncing back with "not configured". (Google is
        // intentionally NOT in SocialOAuthService::PROVIDERS — the mobile
        // app uses the native Google SDK + /api/v1/auth/social path
        // covered above; the WebBrowser fallback is for the rest.)
        putenv('FACEBOOK_CLIENT_ID=test-fb-client');
        putenv('FACEBOOK_CLIENT_SECRET=test-fb-secret');

        // The mobile app calls /user/social-oauth/{provider}/login with the
        // 1inme://oauth-callback return URL. The endpoint must:
        //   - live outside the web auth middleware (status != 401/403)
        //   - accept the mobile scheme query without rejecting it
        //   - redirect to the provider authorize URL (302 away)
        $url = '/user/social-oauth/facebook/login'
            . '?source=mobile'
            . '&return=' . rawurlencode('1inme://oauth-callback');

        $resp = $this->get($url);

        $this->assertNotEquals(401, $resp->status(), 'mobile entry must not require web session');
        $this->assertNotEquals(403, $resp->status(), 'mobile entry must not be CSRF/abort()-blocked');
        $this->assertNotEquals(404, $resp->status(), 'mobile entry route must exist in every environment');
        $resp->assertStatus(302);
        $location = (string) $resp->headers->get('Location');
        $this->assertNotEmpty($location);
        // The redirect should point at Facebook's authorize endpoint
        // (configured for the provider in SocialOAuthService::PROVIDERS),
        // proving the mobile-source query did not block the flow.
        $this->assertStringContainsString('facebook.com', $location);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Generate an RSA-2048 keypair and return [$pemPrivateKey, $jwkPublic].
     */
    private function makeRsaKeyAndJwk(string $kid): array
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $pem);
        $details = openssl_pkey_get_details($res);

        $b64u = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        return [
            $pem,
            [
                'kty' => 'RSA',
                'kid' => $kid,
                'use' => 'sig',
                'alg' => 'RS256',
                'n'   => $b64u($details['rsa']['n']),
                'e'   => $b64u($details['rsa']['e']),
            ],
        ];
    }

    /** Sign a compact JWS using RS256. */
    private function signRs256Jwt(array $header, array $payload, string $pem): string
    {
        $b64u = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
        $h = $b64u(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = $b64u(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = '';
        openssl_sign($h . '.' . $p, $sig, $pem, OPENSSL_ALGO_SHA256);
        return $h . '.' . $p . '.' . $b64u($sig);
    }
}
