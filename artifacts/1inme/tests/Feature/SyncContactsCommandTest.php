<?php

namespace Tests\Feature;

use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\GoogleContactsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * The scheduled `contacts:sync` backstop routes every account through the
 * throttled syncNow(), so it must cheaply skip accounts an on-demand trigger
 * or the sync-on-open job just handled — otherwise the backstop double-hits
 * the People API. An explicit `--account` run is an operator forcing a sync
 * and must bypass the cooldown. Provider mocked (no live People API here).
 */
class SyncContactsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function account(array $attrs = []): GoogleContactsAccount
    {
        $user = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
            'handle'   => 'h' . Str::lower(Str::random(10)),
        ]);

        return GoogleContactsAccount::create(array_merge([
            'user_id'       => $user->id,
            'account_email' => 'g' . Str::random(6) . '@gmail.com',
            'pull_enabled'  => true,
            'push_enabled'  => false,
        ], $attrs));
    }

    private function mockProvider(): Mockery\MockInterface
    {
        $mock = Mockery::mock(GoogleContactsProvider::class);
        $this->app->instance(GoogleContactsProvider::class, $mock);
        return $mock;
    }

    public function test_unattended_backstop_skips_a_recently_synced_account(): void
    {
        $provider = $this->mockProvider();
        // Never touch the People API for an account inside its cooldown.
        $provider->shouldReceive('listPeople')->never();
        $provider->shouldReceive('createPerson')->never();

        $account = $this->account();
        // Stamp it as just-synced so syncNow() short-circuits as "throttled".
        Cache::put("google-contacts:sync-at:{$account->id}", time(), now()->addMinute());

        $this->artisan('contacts:sync')->assertExitCode(0);

        // Left untouched: no status write, no last_synced_at.
        $this->assertNull($account->fresh()->last_synced_at);
    }

    public function test_explicit_account_flag_forces_a_sync_past_the_cooldown(): void
    {
        $provider = $this->mockProvider();
        // Forced run must actually pull, even inside the cooldown window.
        $provider->shouldReceive('listPeople')->once()->andReturn([]);

        $account = $this->account();
        Cache::put("google-contacts:sync-at:{$account->id}", time(), now()->addMinute());

        $this->artisan('contacts:sync', ['--account' => $account->id])->assertExitCode(0);

        $this->assertSame('ok', $account->fresh()->last_sync_status);
        $this->assertNotNull($account->fresh()->last_synced_at);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
