<?php

namespace Tests\Feature;

use App\Mail\SuspiciousLoginMail;
use App\Modules\Common\Services\GeoIpService;
use App\Modules\Common\Services\LoginAlertService;
use App\Modules\User\Models\LoginEvent;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class SuspiciousLoginAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'name' => 'Sec User',
        ]);
    }

    private function fakeGeo(?string $country): void
    {
        $mock = Mockery::mock(GeoIpService::class);
        $mock->shouldReceive('detectCountry')->andReturn($country);
        $this->app->instance(GeoIpService::class, $mock);
    }

    private function makeRequest(string $ua, string $ip = '203.0.113.10'): Request
    {
        return Request::create('/', 'POST', server: [
            'REMOTE_ADDR'     => $ip,
            'HTTP_USER_AGENT' => $ua,
        ]);
    }

    public function test_first_login_is_recorded_but_does_not_email(): void
    {
        Mail::fake();
        $this->fakeGeo('US');

        $user = $this->makeUser();
        $service = app(LoginAlertService::class);

        $event = $service->record($user, $this->makeRequest('Mozilla/5.0 (Macintosh) Chrome/120'), 'web_otp_email');

        $this->assertNotNull($event);
        $this->assertFalse($event->is_new);
        $this->assertFalse($event->alert_sent);
        Mail::assertNothingSent();
    }

    public function test_new_country_triggers_alert_email(): void
    {
        Mail::fake();
        $service = app(LoginAlertService::class);
        $user = $this->makeUser();

        // Baseline login from US/Chrome/macOS
        $this->fakeGeo('US');
        $service->record($user, $this->makeRequest('Mozilla/5.0 (Macintosh) Chrome/120'), 'web_otp_email');

        // Same browser/OS, brand new country.
        $this->fakeGeo('RU');
        $event = $service->record(
            $user,
            $this->makeRequest('Mozilla/5.0 (Macintosh) Chrome/120', '198.51.100.7'),
            'web_otp_email'
        );

        $this->assertTrue($event->is_new);
        $this->assertContains('country', $event->new_reasons);
        $this->assertTrue($event->fresh()->alert_sent);
        Mail::assertSent(SuspiciousLoginMail::class, fn ($m) => $m->hasTo($user->email));
    }

    public function test_revoke_kills_tokens_and_clears_password(): void
    {
        Mail::fake();
        $this->fakeGeo('US');
        $user = $this->makeUser();

        $tokenA = $user->createToken('a');
        $tokenB = $user->createToken('b');
        $oldHash = $user->password;

        $event = LoginEvent::create([
            'user_id'                  => $user->id,
            'channel'                  => 'mobile_otp',
            'ip'                       => '203.0.113.10',
            'personal_access_token_id' => $tokenA->accessToken->id,
            'is_new'                   => true,
            'new_reasons'              => ['country'],
            'alert_sent'               => true,
            'revoke_token'             => bin2hex(random_bytes(24)),
        ]);

        app(LoginAlertService::class)->revokeFromEmail($event);

        $this->assertEquals(0, $user->tokens()->count(), 'all tokens revoked');
        $this->assertNotEquals($oldHash, $user->fresh()->password, 'password rotated');
        $this->assertNotNull($event->fresh()->revoked_at);
    }

    public function test_signed_revoke_endpoint_invalidates_session(): void
    {
        Mail::fake();
        $this->fakeGeo('US');
        $user = $this->makeUser();
        $token = $user->createToken('mobile');

        $event = LoginEvent::create([
            'user_id'                  => $user->id,
            'channel'                  => 'mobile_otp',
            'ip'                       => '203.0.113.10',
            'personal_access_token_id' => $token->accessToken->id,
            'is_new'                   => true,
            'new_reasons'              => ['country'],
            'alert_sent'               => true,
            'revoke_token'             => bin2hex(random_bytes(24)),
        ]);

        $signed = \Illuminate\Support\Facades\URL::signedRoute(
            'user.security.logins.revoke',
            ['token' => $event->revoke_token],
            now()->addDays(30),
        );

        $relative = parse_url($signed, PHP_URL_PATH) . '?' . parse_url($signed, PHP_URL_QUERY);
        $this->get($relative)->assertOk();

        $this->assertEquals(0, $user->tokens()->count());
        $this->assertNotNull($event->fresh()->revoked_at);
    }

    public function test_api_revoke_endpoint_requires_ownership(): void
    {
        Mail::fake();
        $owner   = $this->makeUser();
        $stranger = $this->makeUser();

        $event = LoginEvent::create([
            'user_id'      => $owner->id,
            'channel'      => 'mobile_otp',
            'ip'           => '203.0.113.10',
            'is_new'       => true,
            'new_reasons'  => ['country'],
            'alert_sent'   => true,
            'revoke_token' => bin2hex(random_bytes(24)),
        ]);

        // Stranger cannot revoke owner's session.
        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/security/logins/{$event->id}/revoke")
            ->assertStatus(404);

        // Owner can.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/security/logins/{$event->id}/revoke")
            ->assertOk()
            ->assertJsonPath('data.revoked', true);
    }
}
