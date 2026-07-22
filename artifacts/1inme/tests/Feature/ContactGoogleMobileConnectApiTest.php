<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\GoogleContactsProvider;
use App\Modules\User\Services\Contacts\GoogleContactsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Mobile Google Contacts reconnect flow: the authed POST
 * /api/v1/contacts/google/connect mints an authorize URL with an encrypted
 * stateless token, and the PUBLIC GET /api/v1/contacts/google/oauth/callback
 * decrypts it, exchanges the code and bounces to the app deep link. Uses a
 * REAL Sanctum bearer token (see .agents/memory/sanctum-api-tests.md).
 */
class ContactGoogleMobileConnectApiTest extends TestCase
{
    use RefreshDatabase;

    private array $headers = [];

    private function user(array $features = ['contacts_google_sync' => true]): User
    {
        $plan = Plan::create([
            'name'          => 'Pro',
            'slug'          => 'pro-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 1,
            'features'      => $features,
        ]);

        $user = User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@example.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'role'         => 'user',
            'handle'       => 'h' . Str::lower(Str::random(10)),
            'plan_id'      => $plan->id,
            'onboarded_at' => now(),
        ])->fresh();

        $token = $user->createToken('test', ['*'])->plainTextToken;
        $this->headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ];

        return $user;
    }

    private function mockProvider(): Mockery\MockInterface
    {
        $mock = Mockery::mock(GoogleContactsProvider::class);
        $this->app->instance(GoogleContactsProvider::class, $mock);
        return $mock;
    }

    public function test_connect_returns_authorize_url(): void
    {
        $this->user();

        $provider = $this->mockProvider();
        $provider->shouldReceive('isConfigured')->andReturn(true);
        $provider->shouldReceive('authorizationUrl')
            ->once()
            ->withArgs(function (string $state, string $redirect) {
                // State must round-trip through Crypt and carry the user id.
                $data = json_decode(Crypt::decryptString($state), true);
                return is_array($data)
                    && isset($data['u'], $data['exp'])
                    && $data['r'] === 'sayzio://google-contacts-oauth'
                    && str_contains($redirect, '/api/v1/contacts/google/oauth/callback');
            })
            ->andReturn('https://accounts.google.com/o/oauth2/v2/auth?fake=1');

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/contacts/google/connect')
            ->assertOk()
            ->assertJsonPath('data.authorize_url', 'https://accounts.google.com/o/oauth2/v2/auth?fake=1');
    }

    public function test_connect_is_plan_gated(): void
    {
        $this->user(['contacts_google_sync' => false]);

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/contacts/google/connect')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'plan_upgrade_required');
    }

    public function test_connect_fails_when_provider_not_configured(): void
    {
        $this->user();

        $provider = $this->mockProvider();
        $provider->shouldReceive('isConfigured')->andReturn(false);

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/contacts/google/connect')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'provider_not_configured');
    }

    public function test_callback_exchanges_code_and_bounces_connected(): void
    {
        $user = $this->user();

        $account = GoogleContactsAccount::create([
            'user_id'       => $user->id,
            'account_email' => 'g' . Str::random(6) . '@gmail.com',
            'pull_enabled'  => true,
            'push_enabled'  => true,
        ]);

        $provider = $this->mockProvider();
        $provider->shouldReceive('exchangeCode')
            ->once()
            ->withArgs(fn ($uid, $code, $redirect) => $uid === $user->id && $code === 'the-code')
            ->andReturn($account);

        $sync = Mockery::mock(GoogleContactsSyncService::class);
        $sync->shouldReceive('syncAccount')->once()->andReturn(['created' => 0]);
        $this->app->instance(GoogleContactsSyncService::class, $sync);

        $state = Crypt::encryptString(json_encode([
            'u'   => $user->id,
            'r'   => 'sayzio://google-contacts-oauth',
            'n'   => Str::random(16),
            'exp' => now()->addMinutes(10)->timestamp,
        ]));

        $this->get('/api/v1/contacts/google/oauth/callback?' . http_build_query([
                'state' => $state,
                'code'  => 'the-code',
            ]))
            ->assertRedirect('sayzio://google-contacts-oauth?status=connected');
    }

    public function test_callback_rejects_garbage_state(): void
    {
        $this->get('/api/v1/contacts/google/oauth/callback?state=nonsense&code=x')
            ->assertRedirect('sayzio://google-contacts-oauth?error=invalid_state');
    }

    public function test_callback_rejects_expired_state(): void
    {
        $user = $this->user();
        $state = Crypt::encryptString(json_encode([
            'u'   => $user->id,
            'r'   => 'sayzio://google-contacts-oauth',
            'n'   => Str::random(16),
            'exp' => now()->subMinute()->timestamp,
        ]));

        $this->get('/api/v1/contacts/google/oauth/callback?state=' . urlencode($state) . '&code=x')
            ->assertRedirect('sayzio://google-contacts-oauth?error=expired');
    }

    public function test_callback_bounces_cancelled_when_no_code(): void
    {
        $user = $this->user();
        $state = Crypt::encryptString(json_encode([
            'u'   => $user->id,
            'r'   => 'sayzio://google-contacts-oauth',
            'n'   => Str::random(16),
            'exp' => now()->addMinutes(10)->timestamp,
        ]));

        $this->get('/api/v1/contacts/google/oauth/callback?state=' . urlencode($state) . '&error=access_denied')
            ->assertRedirect('sayzio://google-contacts-oauth?error=cancelled');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
