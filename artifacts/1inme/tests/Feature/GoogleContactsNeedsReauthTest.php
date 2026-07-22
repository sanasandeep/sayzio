<?php

namespace Tests\Feature;

use App\Jobs\SyncGoogleContactsJob;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Contacts\GoogleContactsProvider;
use App\Modules\User\Services\Contacts\GoogleContactsSyncService;
use App\Modules\User\Services\Contacts\GoogleReauthRequiredException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * When Google revokes a Contacts refresh token it answers invalid_grant.
 * The app must persist that as a distinct needs_reauth state, stop retrying
 * every sync path, show a friendly "Reconnect" prompt on the contacts page,
 * and clear the state (reusing the same account row) after a reconnect.
 */
class GoogleContactsNeedsReauthTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $plan = Plan::create([
            'name'          => 'Free',
            'slug'          => 'free-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 1,
            'features'      => ['leads' => true, 'contacts_max' => 5000],
        ]);

        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@example.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'role'         => 'user',
            'handle'       => 'h' . Str::lower(Str::random(10)),
            'plan_id'      => $plan->id,
            'onboarded_at' => now(),
        ])->fresh();
    }

    private function account(User $user, array $attrs = []): GoogleContactsAccount
    {
        return GoogleContactsAccount::create(array_merge([
            'user_id'       => $user->id,
            'account_email' => 'g' . Str::random(6) . '@gmail.com',
            'pull_enabled'  => true,
            'push_enabled'  => false,
        ], $attrs));
    }

    public function test_invalid_grant_on_refresh_marks_the_account_needs_reauth(): void
    {
        $account = $this->account($this->user(), [
            'refresh_token'    => 'revoked-token',
            'access_token'     => 'stale',
            'token_expires_at' => now()->subHour(),
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(
                ['error' => 'invalid_grant', 'error_description' => 'Bad Request'],
                400
            ),
        ]);

        $stats = $this->app->make(GoogleContactsSyncService::class)->syncAccount($account);

        $fresh = $account->fresh();
        $this->assertNotNull($fresh->needs_reauth_at);
        $this->assertSame(GoogleContactsAccount::STATUS_NEEDS_REAUTH, $fresh->last_sync_status);
        $this->assertSame(1, $stats['errors']);
    }

    public function test_needs_reauth_account_skips_all_sync_paths_without_google_calls(): void
    {
        $account = $this->account($this->user(), ['needs_reauth_at' => now()]);

        Http::fake(); // any HTTP call would be recorded

        $result = $this->app->make(GoogleContactsSyncService::class)->syncNow($account);
        $this->assertSame('needs_reauth', $result['status']);

        // The on-open dispatch gate skips the account entirely.
        $this->assertFalse(SyncGoogleContactsJob::shouldQueue($account->user_id));

        // The scheduled backstop excludes it too.
        $this->artisan('contacts:sync')
            ->expectsOutputToContain('Syncing contacts for 0 account(s)');

        Http::assertNothingSent();
    }

    public function test_provider_refresh_throws_reauth_exception_when_already_flagged(): void
    {
        $account = $this->account($this->user(), ['needs_reauth_at' => now()]);

        $this->expectException(GoogleReauthRequiredException::class);
        $this->app->make(GoogleContactsProvider::class)->refreshIfNeeded($account);
    }

    public function test_web_sync_now_returns_reconnect_message_instead_of_calling_google(): void
    {
        $user = $this->user();
        $account = $this->account($user, ['needs_reauth_at' => now()]);

        Http::fake();

        $this->actingAs($user)
            ->from(route('user.contacts.index'))
            ->post(route('user.contacts.google.sync', $account))
            ->assertRedirect(route('user.contacts.index'))
            ->assertSessionHas('error', 'Your Google Contacts connection expired — please reconnect to resume syncing.');

        Http::assertNothingSent();
    }

    public function test_contacts_page_shows_reconnect_banner_instead_of_raw_error(): void
    {
        $user = $this->user();
        $this->account($user, [
            'needs_reauth_at'  => now(),
            'last_sync_status' => GoogleContactsAccount::STATUS_NEEDS_REAUTH,
            'last_sync_error'  => 'Google reported the connection as revoked or expired (invalid_grant).',
        ]);

        $resp = $this->actingAs($user)->get(route('user.contacts.index'));

        $resp->assertOk();
        $resp->assertSee('Your Google Contacts connection expired');
        $resp->assertSee('Reconnect');
        $resp->assertSee(route('user.contacts.google.connect'), false);
        $resp->assertDontSee('invalid_grant');
    }

    public function test_reconnect_reuses_the_same_account_row_and_clears_needs_reauth(): void
    {
        $user = $this->user();
        $account = $this->account($user, [
            'account_email'    => 'same@gmail.com',
            'needs_reauth_at'  => now(),
            'last_sync_status' => GoogleContactsAccount::STATUS_NEEDS_REAUTH,
            'last_sync_error'  => 'revoked',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token'  => 'fresh-access',
                'refresh_token' => 'fresh-refresh',
                'expires_in'    => 3600,
                'scope'         => 'contacts',
            ]),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'email' => 'same@gmail.com',
                'sub'   => 'ext-123',
            ]),
        ]);

        $reconnected = $this->app->make(GoogleContactsProvider::class)
            ->exchangeCode($user->id, 'auth-code', 'https://example.test/cb');

        $this->assertSame($account->id, $reconnected->id, 'reconnect must reuse the existing account row');
        $this->assertNull($reconnected->needs_reauth_at);
        $this->assertNull($reconnected->last_sync_error);
        $this->assertSame(1, GoogleContactsAccount::where('user_id', $user->id)->count());
        $this->assertFalse($reconnected->fresh()->needsReauth());
    }

    public function test_first_needs_reauth_transition_sends_one_email_and_in_app_notification(): void
    {
        $user    = $this->user();
        $account = $this->account($user);

        $account->markNeedsReauth('Google reported the connection as revoked or expired (invalid_grant).');

        $notes = \App\Modules\User\Models\UserNotification::where('user_id', $user->id)
            ->where('type', 'contacts.google_reauth')->get();
        $this->assertCount(1, $notes);
        $this->assertSame(route('user.contacts.index'), $notes->first()->data['url'] ?? null);

        $emails = \App\Modules\Common\Models\EmailLog::where('user_id', $user->id)
            ->where('email_key', 'contacts.google_reauth')->get();
        $this->assertCount(1, $emails);
        $this->assertSame($user->email, $emails->first()->recipient);
        $this->assertStringContainsString($account->account_email, $emails->first()->body);
        $this->assertStringContainsString(route('user.contacts.index'), $emails->first()->body);

        // Retries while still expired must NOT re-notify.
        $account->fresh()->markNeedsReauth('still revoked');
        $account->fresh()->markNeedsReauth('still revoked again');

        $this->assertSame(1, \App\Modules\User\Models\UserNotification::where('user_id', $user->id)
            ->where('type', 'contacts.google_reauth')->count());
        $this->assertSame(1, \App\Modules\Common\Models\EmailLog::where('user_id', $user->id)
            ->where('email_key', 'contacts.google_reauth')->count());
    }

    public function test_reconnect_rearms_the_expiry_notification(): void
    {
        $user    = $this->user();
        $account = $this->account($user);

        $account->markNeedsReauth('revoked');

        // Reconnect clears the state…
        $account->fresh()->forceFill(['needs_reauth_at' => null, 'last_sync_status' => null])->save();

        // …so a future expiry notifies again.
        $account->fresh()->markNeedsReauth('revoked again');

        $this->assertSame(2, \App\Modules\User\Models\UserNotification::where('user_id', $user->id)
            ->where('type', 'contacts.google_reauth')->count());
        $this->assertSame(2, \App\Modules\Common\Models\EmailLog::where('user_id', $user->id)
            ->where('email_key', 'contacts.google_reauth')->count());
    }

    public function test_api_status_and_sync_expose_the_needs_reauth_state(): void
    {
        $user = $this->user();
        $this->account($user, ['needs_reauth_at' => now()]);

        $token = $user->createToken('t')->plainTextToken;

        $status = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/contacts/google/status');
        $status->assertOk()
            ->assertJsonPath('data.account.needs_reauth', true);
        $this->assertNotNull($status->json('data.account.needs_reauth_at'));
        $this->assertNotNull($status->json('data.account.reconnect_message'));

        Http::fake();
        $sync = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/contacts/google/sync');
        $sync->assertStatus(409)
            ->assertJsonPath('error.code', 'google_needs_reauth');
        Http::assertNothingSent();
    }
}
