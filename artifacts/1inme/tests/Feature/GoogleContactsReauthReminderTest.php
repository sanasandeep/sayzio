<?php

namespace Tests\Feature;

use App\Console\Commands\SendGoogleContactsReauthReminders;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Models\EmailLog;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Services\Contacts\GoogleContactsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #5656 — if a Google Contacts connection stays disconnected for a week
 * after the initial reauth alert, a scheduled command sends exactly one
 * follow-up reminder (in-app + email), stamps reauth_reminder_sent_at so
 * reruns never re-send, and reconnecting clears the stamp so a future
 * expiry re-arms the reminder.
 */
class GoogleContactsReauthReminderTest extends TestCase
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

    public function test_reminder_sent_once_after_seven_days_disconnected(): void
    {
        $user    = $this->user();
        $account = $this->account($user, ['needs_reauth_at' => now()->subDays(8)]);

        $this->artisan('contacts:send-reauth-reminders')
            ->expectsOutputToContain('Sent 1 Google Contacts reconnect reminders.');

        $fresh = $account->fresh();
        $this->assertNotNull($fresh->reauth_reminder_sent_at);

        $notes = UserNotification::where('user_id', $user->id)
            ->where('type', SendGoogleContactsReauthReminders::TYPE)->get();
        $this->assertCount(1, $notes);
        $this->assertSame(route('user.contacts.index'), $notes->first()->data['url'] ?? null);

        $emails = EmailLog::where('user_id', $user->id)
            ->where('email_key', SendGoogleContactsReauthReminders::TYPE)->get();
        $this->assertCount(1, $emails);
        $this->assertSame($user->email, $emails->first()->recipient);
        $this->assertStringContainsString($account->account_email, $emails->first()->body);
        $this->assertStringContainsString(route('user.contacts.index'), $emails->first()->body);

        // Reruns must NOT re-send.
        $this->artisan('contacts:send-reauth-reminders')
            ->expectsOutputToContain('Sent 0 Google Contacts reconnect reminders.');

        $this->assertSame(1, UserNotification::where('user_id', $user->id)
            ->where('type', SendGoogleContactsReauthReminders::TYPE)->count());
        $this->assertSame(1, EmailLog::where('user_id', $user->id)
            ->where('email_key', SendGoogleContactsReauthReminders::TYPE)->count());
    }

    public function test_recently_disconnected_and_healthy_accounts_are_skipped(): void
    {
        $user = $this->user();
        $this->account($user, ['needs_reauth_at' => now()->subDays(3)]); // too recent
        $this->account($user);                                          // healthy

        $this->artisan('contacts:send-reauth-reminders')
            ->expectsOutputToContain('Sent 0 Google Contacts reconnect reminders.');

        $this->assertSame(0, UserNotification::where('user_id', $user->id)
            ->where('type', SendGoogleContactsReauthReminders::TYPE)->count());
        $this->assertSame(0, GoogleContactsAccount::where('user_id', $user->id)
            ->whereNotNull('reauth_reminder_sent_at')->count());
    }

    public function test_reconnect_clears_the_reminder_stamp_and_rearms_it(): void
    {
        $user    = $this->user();
        $account = $this->account($user, [
            'account_email'           => 'same@gmail.com',
            'needs_reauth_at'         => now()->subDays(10),
            'reauth_reminder_sent_at' => now()->subDays(3),
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

        $this->assertSame($account->id, $reconnected->id);
        $this->assertNull($reconnected->needs_reauth_at);
        $this->assertNull($reconnected->reauth_reminder_sent_at);

        // A future expiry that again lasts 7+ days reminds again.
        $reconnected->forceFill(['needs_reauth_at' => now()->subDays(8)])->save();

        $this->artisan('contacts:send-reauth-reminders')
            ->expectsOutputToContain('Sent 1 Google Contacts reconnect reminders.');
    }
}
