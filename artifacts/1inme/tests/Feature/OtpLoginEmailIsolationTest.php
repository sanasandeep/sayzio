<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Models\EmailLog;
use App\Modules\User\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Integrations\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the email-isolation change: the boot-time SMTP
 * override in AppServiceProvider is now production-only. Every non-production
 * environment (dev, CI, e2e, the PHPUnit suite) must be forced onto the
 * non-delivering "log" mailer so it can NEVER open a real SMTP socket —
 * sending OTP/verification mail to @example.com fixtures through the live relay
 * once triggered an upstream abuse block that knocked out real production OTP
 * delivery.
 *
 * This guards two things that must keep holding together:
 *
 *   (a) In non-production the effective mailer resolves to "log", not "smtp"
 *       — even when admin SMTP app_settings ARE configured (i.e. the gate does
 *       not open a socket just because credentials exist).
 *
 *   (b) OTP login still works end-to-end: a code can be issued, read back from
 *       the database / email log (never a real inbox), and used to complete
 *       login — proving black-holing the transport didn't break the flow.
 */
class OtpLoginEmailIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);
    }

    /** Seed a full set of admin SMTP credentials, as the Admin → Email/SMTP UI would. */
    private function configureAdminSmtp(): void
    {
        MailSettings::setMailer('smtp');
        MailSettings::setHost('smtp.example-relay.test');
        MailSettings::setPort(587);
        MailSettings::setEncryption('tls');
        MailSettings::setUsername('postmaster@example-relay.test');
        MailSettings::setPassword('super-secret-pw');
        MailSettings::setFromAddress('noreply@example-relay.test');
        MailSettings::setFromName('Sayzio');
    }

    public function test_non_production_boot_forces_the_log_mailer(): void
    {
        // The suite runs under APP_ENV=testing (non-production) with
        // MAIL_MAILER=array in phpunit.xml. If the production-only gate were
        // ever dropped, config('mail.default') would fall back to that env
        // value ('array'); the fact it's 'log' proves the boot override ran.
        $this->assertFalse($this->app->environment('production'));
        $this->assertSame('log', config('mail.default'));
    }

    public function test_configure_mail_transport_forces_log_even_with_admin_smtp_configured(): void
    {
        // Admin has genuinely configured real SMTP credentials.
        $this->configureAdminSmtp();
        $this->assertTrue(MailSettings::hasAnyAdminValue());
        $this->assertSame('smtp', MailSettings::mailer());

        // Sanity floor: those credentials really are live-applicable — in
        // production the same settings flip the default mailer to 'smtp' (a
        // real socket). So a "log" result below is the environment gate doing
        // its job, not the admin settings being inert.
        MailSettings::applyRuntimeConfig();
        $this->assertSame('smtp', config('mail.default'));

        // ...but the non-production gate overrides that back to the
        // non-delivering log driver, ignoring the admin SMTP credentials.
        (new AppServiceProvider($this->app))->configureMailTransport();
        $this->assertSame('log', config('mail.default'));
    }

    public function test_email_otp_login_round_trips_over_the_log_mailer(): void
    {
        // Even with real SMTP credentials on file, the non-production gate must
        // keep the transport black-holed for this whole flow.
        $this->configureAdminSmtp();
        Queue::fake();

        $email = 'otp-isolation-' . Str::random(8) . '@example.com';
        $user = User::create([
            'name'     => 'OTP Isolation',
            'email'    => $email,
            'password' => Hash::make('unused-password'),
            'status'   => 'active',
        ]);

        // The effective mailer is the non-delivering log driver — no real SMTP
        // socket is opened by the OTP send below.
        $this->assertSame('log', config('mail.default'));

        // Trigger a real OTP send through the web endpoint. Under the log
        // mailer this black-holes the message instead of dialing the relay.
        $this->post('/user/send-otp', [
            'identifier' => $email,
            'type'       => 'email',
        ])->assertRedirect();

        // The code is still recoverable without a real inbox: it's persisted
        // to the otps table (how the app itself verifies it).
        $otp = DB::table('otps')
            ->where('identifier', $email)
            ->where('type', 'email')
            ->where('purpose', 'login')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($otp, 'An OTP row should be issued for the email send.');
        $this->assertFalse((bool) $otp->used);
        $code = (string) $otp->code;
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // ...and it's equally readable from the email log the Emailer pipeline
        // writes regardless of transport delivery — proving "readable from the
        // log" holds when SMTP is black-holed.
        $log = EmailLog::where('email_key', 'auth.otp_code')
            ->where('recipient', $email)
            ->latest('id')
            ->first();
        $this->assertNotNull($log, 'An email_logs row should record the OTP send.');
        $this->assertStringContainsString($code, (string) $log->body);

        // The code completes login end-to-end: the verify POST authenticates
        // the existing account (the send step already stashed the identifier in
        // the session).
        $this->post('/user/verify-otp', ['code' => $code])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue((bool) DB::table('otps')->where('id', $otp->id)->value('used'));

        // The transport was never taken off the log driver for the whole flow.
        $this->assertSame('log', config('mail.default'));
    }
}
