<?php

namespace Tests\Unit\Services;

use App\Modules\User\Models\SocialAccountConnection;
use App\Modules\User\Services\SocialFollowers\SocialOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifies that each provider-specific token-refresh strategy hits the right
 * endpoint with the right request shape and persists the new token. We pin
 * env vars at runtime so the service treats every provider as configured.
 */
class SocialOAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure all six paid providers so isConfigured() returns true.
        putenv('FACEBOOK_CLIENT_ID=fb-id');     putenv('FACEBOOK_CLIENT_SECRET=fb-secret');
        putenv('TWITTER_CLIENT_ID=x-id');       putenv('TWITTER_CLIENT_SECRET=x-secret');
        putenv('LINKEDIN_CLIENT_ID=li-id');     putenv('LINKEDIN_CLIENT_SECRET=li-secret');
        putenv('PINTEREST_CLIENT_ID=pin-id');   putenv('PINTEREST_CLIENT_SECRET=pin-secret');
        putenv('TIKTOK_CLIENT_KEY=tk-key');     putenv('TIKTOK_CLIENT_SECRET=tk-secret');
    }

    private function svc(): SocialOAuthService
    {
        return new SocialOAuthService();
    }

    private function makeConnection(string $platform, array $overrides = []): SocialAccountConnection
    {
        $userId = DB::table('users')->insertGetId([
            'name'       => 'Test',
            'email'      => 'oauth-test-' . uniqid() . '@example.com',
            'password'   => bcrypt('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return SocialAccountConnection::create(array_merge([
            'user_id'        => $userId,
            'platform'       => $platform,
            'handle'         => 'tester',
            'access_token'   => 'old-access',
            'refresh_token'  => 'old-refresh',
            'token_expires_at' => now()->addHour(),
        ], $overrides));
    }

    public function test_meta_uses_fb_exchange_token_grant_without_refresh_token(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'access_token' => 'new-meta-token',
                'expires_in'   => 5_184_000, // 60 days
            ], 200),
        ]);

        // Meta connections often have no refresh_token at all — make sure that's fine.
        $c = $this->makeConnection('facebook', ['refresh_token' => null]);

        $this->assertTrue($this->svc()->canRefreshToken($c));
        $this->assertTrue($this->svc()->refreshAccessToken($c));

        Http::assertSent(function ($req) {
            parse_str(parse_url($req->url(), PHP_URL_QUERY) ?? '', $q);
            return str_contains($req->url(), 'graph.facebook.com')
                && ($q['grant_type'] ?? null) === 'fb_exchange_token'
                && ($q['fb_exchange_token'] ?? null) === 'old-access'
                && ($q['client_id'] ?? null) === 'fb-id'
                && ($q['client_secret'] ?? null) === 'fb-secret';
        });

        $this->assertSame('new-meta-token', $c->fresh()->access_token);
        $this->assertNotNull($c->fresh()->token_expires_at);
    }

    public function test_tiktok_refresh_uses_client_key_field(): void
    {
        Http::fake([
            'open.tiktokapis.com/*' => Http::response([
                'access_token'  => 'new-tk-access',
                'refresh_token' => 'rotated-tk-refresh',
                'expires_in'    => 86_400,
            ], 200),
        ]);

        $c = $this->makeConnection('tiktok');
        $this->assertTrue($this->svc()->refreshAccessToken($c));

        Http::assertSent(function ($req) {
            return str_contains($req->url(), 'open.tiktokapis.com/v2/oauth/token')
                && ($req->data()['client_key'] ?? null) === 'tk-key'
                && ($req->data()['grant_type'] ?? null) === 'refresh_token'
                && ($req->data()['refresh_token'] ?? null) === 'old-refresh';
        });

        $fresh = $c->fresh();
        $this->assertSame('new-tk-access', $fresh->access_token);
        $this->assertSame('rotated-tk-refresh', $fresh->refresh_token);
    }

    public function test_twitter_refresh_uses_basic_auth_and_refresh_token(): void
    {
        Http::fake([
            'api.twitter.com/*' => Http::response([
                'access_token' => 'new-x-access',
                'expires_in'   => 7200,
            ], 200),
        ]);

        $c = $this->makeConnection('twitter');
        $this->assertTrue($this->svc()->refreshAccessToken($c));

        Http::assertSent(function ($req) {
            return str_contains($req->url(), 'api.twitter.com/2/oauth2/token')
                && $req->hasHeader('Authorization', 'Basic ' . base64_encode('x-id:x-secret'))
                && ($req->data()['grant_type'] ?? null) === 'refresh_token'
                && ($req->data()['refresh_token'] ?? null) === 'old-refresh';
        });

        $this->assertSame('new-x-access', $c->fresh()->access_token);
    }

    public function test_linkedin_uses_generic_oauth2_refresh_grant(): void
    {
        Http::fake([
            'linkedin.com/*' => Http::response([
                'access_token' => 'new-li',
                'expires_in'   => 1800,
            ], 200),
        ]);

        $c = $this->makeConnection('linkedin');
        $this->assertTrue($this->svc()->refreshAccessToken($c));

        Http::assertSent(function ($req) {
            return str_contains($req->url(), 'linkedin.com/oauth/v2/accessToken')
                && ($req->data()['grant_type'] ?? null) === 'refresh_token'
                && ($req->data()['client_id'] ?? null) === 'li-id'
                && ($req->data()['client_secret'] ?? null) === 'li-secret';
        });
    }

    public function test_failed_refresh_throws_so_caller_can_mark_broken(): void
    {
        Http::fake([
            'pinterest.com/*' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $c = $this->makeConnection('pinterest');

        $this->expectException(\RuntimeException::class);
        $this->svc()->refreshAccessToken($c);
    }

    public function test_unconfigured_provider_short_circuits_to_false(): void
    {
        putenv('LINKEDIN_CLIENT_ID');
        putenv('LINKEDIN_CLIENT_SECRET');

        $c = $this->makeConnection('linkedin');
        $this->assertFalse($this->svc()->canRefreshToken($c));
        $this->assertFalse($this->svc()->refreshAccessToken($c));
        Http::assertNothingSent();
    }
}
