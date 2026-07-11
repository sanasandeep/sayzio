<?php

namespace Tests\Feature;

use App\Jobs\ProvisionPlatformAiMindJob;
use App\Jobs\RecordLoginEventJob;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\OtpService;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression coverage for Task #4596: creating a new account by email code
 * (OTP verify -> sign-up) must never stall or hang.
 *
 * A good OTP verify for a FIRST-TIME email has to return its response
 * promptly, which means every piece of heavy/networked work the sign-up
 * kicks off must be QUEUED (or otherwise deferred), never run inline on the
 * request:
 *
 *   - login-event recording (RecordLoginEventJob -> GeoIP lookup)
 *   - platform "Sayzio Default Mind" provisioning + its source-ingest jobs
 *     (ProvisionPlatformAiMindJob)
 *
 * and no e-mail (welcome / SMTP connect) may be sent synchronously.
 *
 * These assertions are deliberately structural rather than wall-clock based:
 * proving the work is pushed to the queue instead of executed is a stable,
 * non-flaky proxy for "the POST responds quickly even when SMTP is slow or
 * the AI backend is unconfigured".
 */
class OtpSignupNonBlockingTest extends TestCase
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

    public function test_first_time_email_otp_verify_defers_all_heavy_work(): void
    {
        Queue::fake();
        Mail::fake();

        // Force the "platform Mind not yet provisioned" branch so we exercise
        // the dispatch path (the sharded runner can leave a platform Mind
        // behind from a sibling test).
        AiMind::query()->whereNull('user_id')->where('is_default', true)->delete();

        $email = 'first-timer@example.com';
        $this->assertDatabaseMissing('users', ['email' => $email]);

        // Issue a real OTP the same way the send-otp step does, then drive the
        // web verify endpoint exactly as the browser would.
        $code = app(OtpService::class)->generate($email, 'email', 'login', 'web', '127.0.0.1');

        $response = $this->withSession([
            'otp_identifier' => $email,
            'otp_type'       => 'email',
        ])->post('/user/verify-otp', ['code' => $code]);

        // The verify POST succeeds and creates the account (redirect on the
        // web guard). A 500/hang would fail this outright.
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => $email]);

        // Login-event recording is queued, not run inline (its GeoIP lookup
        // must never sit on the sign-up request path).
        Queue::assertPushed(RecordLoginEventJob::class);

        // Platform Mind provisioning is queued, not run inline. Because the
        // queue is faked the job body never executes, which proves the AI row
        // creation + source-ingest dispatch is fully off the request path.
        Queue::assertPushed(ProvisionPlatformAiMindJob::class);
        $this->assertDatabaseMissing('ai_minds', ['user_id' => null, 'is_default' => true]);

        // No synchronous mail (no welcome e-mail / SMTP connect) on sign-up.
        Mail::assertNothingSent();
    }

    public function test_platform_mind_job_is_not_reenqueued_once_provisioned(): void
    {
        Queue::fake();

        // Simulate an install where the platform default Mind already exists.
        AiMind::create([
            'user_id'    => null,
            'name'       => 'Sayzio Default Mind',
            'is_default' => true,
        ]);

        $code = app(OtpService::class)->generate('second@example.com', 'email', 'login', 'web', '127.0.0.1');

        $this->withSession([
            'otp_identifier' => 'second@example.com',
            'otp_type'       => 'email',
        ])->post('/user/verify-otp', ['code' => $code])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'second@example.com']);

        // The existence guard means no redundant provisioning job is enqueued
        // on every sign-up once the platform Mind is already in place.
        Queue::assertNotPushed(ProvisionPlatformAiMindJob::class);
    }

    public function test_platform_mind_provisioning_never_runs_inline_under_sync_queue(): void
    {
        // The most important non-blocking guarantee: even when the app's
        // default queue driver is `sync` (which would normally execute a
        // ShouldQueue job inline, in-process, during dispatch), the sign-up
        // request must NOT run the heavy AI provisioning synchronously.
        //
        // We deliberately do NOT fake the queue here so that a regression which
        // reintroduces inline provisioning (e.g. calling ensurePlatformDefault
        // directly, or a plain dispatch() that respects the sync driver) would
        // actually create the platform Mind during the request and fail the
        // assertions below.
        config(['queue.default' => 'sync']);

        // Start from a clean slate so the dispatch branch is exercised.
        AiMind::query()->whereNull('user_id')->where('is_default', true)->delete();
        DB::table('jobs')->delete();

        $email = 'sync-first-timer@example.com';
        $code = app(OtpService::class)->generate($email, 'email', 'login', 'web', '127.0.0.1');

        $this->withSession([
            'otp_identifier' => $email,
            'otp_type'       => 'email',
        ])->post('/user/verify-otp', ['code' => $code])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => $email]);

        // Provisioning did NOT run inline: no platform Mind was created during
        // the request even though the default connection is `sync`.
        $this->assertDatabaseMissing('ai_minds', ['user_id' => null, 'is_default' => true]);

        // Instead the work was persisted to the database queue for a worker to
        // drain out-of-band, keeping the sign-up response non-blocking.
        $this->assertTrue(
            DB::table('jobs')->where('payload', 'like', '%ProvisionPlatformAiMindJob%')->exists(),
            'Expected ProvisionPlatformAiMindJob to be queued to the database connection, not run inline.'
        );
    }
}
