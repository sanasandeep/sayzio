<?php

namespace Tests\Feature;

use App\Modules\Api\Middleware\TouchSessionToken;
use App\Modules\Common\Services\GeoIpService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Focused coverage for the TouchSessionToken middleware, which stamps each
 * Sanctum personal-access-token's last IP / user-agent / country after an
 * authenticated /api/v1 request so the "Devices & sessions" page can show
 * "last seen from <country>" accurately.
 *
 * We authenticate with a REAL Sanctum bearer token (never Sanctum::actingAs,
 * which injects a Mockery mock the middleware can't forceFill()->save() on)
 * so the genuine auth + token-touch path runs end-to-end.
 */
class TouchSessionTokenTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '203.0.113.10';
    private const UA = 'OneInMeMobileApp/2.3 (probe)';

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    public function test_authed_request_stamps_last_ip_ua_and_country_on_the_token(): void
    {
        $user = $this->makeUser();

        // A real token, freshly minted (no last_* metadata yet).
        $new      = $user->createToken('probe');
        $tokenId  = $new->accessToken->id;
        $plain    = $new->plainTextToken;

        // The middleware resolves the country via GeoIpService::detectCountry.
        // Stub it so the test never touches the network and the country is
        // deterministic.
        $geo = Mockery::mock(GeoIpService::class);
        $geo->shouldReceive('detectCountry')->once()->with(self::IP)->andReturn('DE');
        $this->app->instance(GeoIpService::class, $geo);

        $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->withToken($plain)
            ->getJson('/api/v1/auth/me', ['User-Agent' => self::UA])
            ->assertOk();

        $token = PersonalAccessToken::find($tokenId);
        $this->assertSame(self::IP, $token->last_ip);
        $this->assertSame(self::UA, $token->last_user_agent);
        $this->assertSame('DE', $token->last_country);
    }

    public function test_no_write_happens_when_ip_and_ua_are_unchanged(): void
    {
        $user = $this->makeUser();

        $new     = $user->createToken('probe');
        $tokenId = $new->accessToken->id;
        $plain   = $new->plainTextToken;

        // Pre-stamp the token with exactly the IP/UA the next request carries,
        // plus a sentinel country that GeoIpService would never produce. If the
        // middleware re-enters the IP branch it would call detectCountry and
        // overwrite the sentinel — so its survival proves no write occurred.
        PersonalAccessToken::find($tokenId)->forceFill([
            'last_ip'         => self::IP,
            'last_user_agent' => self::UA,
            'last_country'    => 'ZZ',
        ])->save();

        // detectCountry must NOT be called when the IP is unchanged.
        $geo = Mockery::mock(GeoIpService::class);
        $geo->shouldReceive('detectCountry')->never();
        $this->app->instance(GeoIpService::class, $geo);

        $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->withToken($plain)
            ->getJson('/api/v1/auth/me', ['User-Agent' => self::UA])
            ->assertOk();

        $token = PersonalAccessToken::find($tokenId);
        $this->assertSame(self::IP, $token->last_ip);
        $this->assertSame(self::UA, $token->last_user_agent);
        // Sentinel preserved => the IP/UA branches were skipped (no write).
        $this->assertSame('ZZ', $token->last_country);
    }

    public function test_guest_request_is_a_noop(): void
    {
        // No authenticated user => currentAccessToken() is null => nothing to
        // stamp. The middleware must pass the response through untouched and
        // never resolve GeoIpService.
        $geo = Mockery::mock(GeoIpService::class);
        $geo->shouldReceive('detectCountry')->never();
        $this->app->instance(GeoIpService::class, $geo);

        $request  = Request::create('/api/v1/auth/me', 'GET', server: ['REMOTE_ADDR' => self::IP]);
        $expected = new Response('passthrough', 200);

        $middleware = new TouchSessionToken();
        $response   = $middleware->handle($request, fn () => $expected);

        $this->assertSame($expected, $response);
        $this->assertSame('passthrough', $response->getContent());
    }

    public function test_web_session_guard_is_a_noop(): void
    {
        // A web-session user has no current access token (currentAccessToken()
        // returns null), so the middleware must skip the stamp entirely rather
        // than try to forceFill()->save() on a non-token.
        $user = $this->makeUser();

        $geo = Mockery::mock(GeoIpService::class);
        $geo->shouldReceive('detectCountry')->never();
        $this->app->instance(GeoIpService::class, $geo);

        $request = Request::create('/api/v1/auth/me', 'GET', server: ['REMOTE_ADDR' => self::IP]);
        $request->setUserResolver(fn () => $user);
        $this->assertNull($user->currentAccessToken());

        $expected   = new Response('passthrough', 200);
        $middleware = new TouchSessionToken();
        $response   = $middleware->handle($request, fn () => $expected);

        $this->assertSame($expected, $response);
    }
}
