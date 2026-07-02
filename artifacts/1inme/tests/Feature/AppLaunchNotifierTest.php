<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\AppLaunchSignup;
use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Support\AppLaunchNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pins the idempotent behaviour of the mobile-app launch notifier
 * ({@see AppLaunchNotifier} / `app-launch:notify`): every signup with a null
 * `notified_at` is emailed exactly once and stamped, and a second run sends
 * nothing. Mirrors the FeatureLaunchNotifier contract.
 *
 * Emails are asserted via the email_logs table Emailer writes on every send,
 * NOT Mail::fake() counters — see .agents/memory/mailfake-raw-noop.md.
 * Mail::fake() is kept only to stop real delivery during the test.
 */
class AppLaunchNotifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // Store URLs the launch email links to.
        AppSetting::put('marketing_play_store_url', 'https://play.google.com/store/apps/details?id=app.sayzio');
        AppSetting::put('marketing_app_store_url', 'https://apps.apple.com/app/id0000000000');
    }

    /** Count of "sent" launch emails logged for a recipient. */
    protected function sentCount(?string $email = null): int
    {
        $q = EmailLog::where('email_key', 'app.launched')->where('status', 'sent');
        if ($email !== null) {
            $q->where('recipient', $email);
        }

        return $q->count();
    }

    public function test_it_emails_every_unnotified_signup_and_stamps_them(): void
    {
        AppLaunchSignup::create(['email' => 'a@example.com']);
        AppLaunchSignup::create(['email' => 'b@example.com']);

        $processed = AppLaunchNotifier::notifyIfLaunched();

        $this->assertSame(2, $processed);
        $this->assertSame(1, $this->sentCount('a@example.com'));
        $this->assertSame(1, $this->sentCount('b@example.com'));
        $this->assertSame(0, AppLaunchSignup::whereNull('notified_at')->count());
    }

    public function test_a_second_run_sends_nothing(): void
    {
        AppLaunchSignup::create(['email' => 'once@example.com']);

        $first = AppLaunchNotifier::notifyIfLaunched();
        $this->assertSame(1, $first);
        $this->assertSame(1, $this->sentCount());

        // Already-notified rows are skipped on the next run — no new email_logs row.
        $second = AppLaunchNotifier::notifyIfLaunched();
        $this->assertSame(0, $second);
        $this->assertSame(1, $this->sentCount());
    }

    public function test_it_skips_rows_already_notified(): void
    {
        AppLaunchSignup::create(['email' => 'fresh@example.com']);
        AppLaunchSignup::create(['email' => 'done@example.com', 'notified_at' => now()]);

        $processed = AppLaunchNotifier::notifyIfLaunched();

        $this->assertSame(1, $processed);
        $this->assertSame(1, $this->sentCount('fresh@example.com'));
        $this->assertSame(0, $this->sentCount('done@example.com'));
    }

    public function test_it_skips_unsubscribed_rows(): void
    {
        AppLaunchSignup::create(['email' => 'stay@example.com']);
        AppLaunchSignup::create(['email' => 'gone@example.com', 'unsubscribed_at' => now()]);

        $processed = AppLaunchNotifier::notifyIfLaunched();

        // Only the still-subscribed row is emailed; the opted-out one is
        // never contacted and is left unstamped (nothing was sent to it).
        $this->assertSame(1, $processed);
        $this->assertSame(1, $this->sentCount('stay@example.com'));
        $this->assertSame(0, $this->sentCount('gone@example.com'));
        $this->assertNull(AppLaunchSignup::where('email', 'gone@example.com')->first()->notified_at);
    }

    public function test_the_command_runs_the_notifier(): void
    {
        AppLaunchSignup::create(['email' => 'cmd@example.com']);

        $this->artisan('app-launch:notify')
            ->assertExitCode(0);

        $this->assertSame(1, $this->sentCount('cmd@example.com'));
        $this->assertSame(0, AppLaunchSignup::whereNull('notified_at')->count());
    }

    public function test_the_command_refuses_when_no_store_url_is_configured(): void
    {
        // Both store URLs empty — the launch email would have no download buttons.
        AppSetting::put('marketing_play_store_url', '');
        AppSetting::put('marketing_app_store_url', '');

        AppLaunchSignup::create(['email' => 'burned@example.com']);

        $this->artisan('app-launch:notify')
            ->assertExitCode(1);

        // Nothing sent, nothing stamped — the list is preserved for a real launch.
        $this->assertSame(0, $this->sentCount('burned@example.com'));
        $this->assertSame(1, AppLaunchSignup::whereNull('notified_at')->count());
    }

    public function test_the_force_flag_sends_even_with_no_store_url(): void
    {
        AppSetting::put('marketing_play_store_url', '');
        AppSetting::put('marketing_app_store_url', '');

        AppLaunchSignup::create(['email' => 'forced@example.com']);

        $this->artisan('app-launch:notify', ['--force' => true])
            ->assertExitCode(0);

        $this->assertSame(1, $this->sentCount('forced@example.com'));
        $this->assertSame(0, AppLaunchSignup::whereNull('notified_at')->count());
    }
}
