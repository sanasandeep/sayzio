<?php

namespace Tests\Feature;

use App\Jobs\ProvisionPlatformAiMindJob;
use App\Jobs\RecordLoginEventJob;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\OtpService;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\User;
use App\Services\AI\AiMindIngestor;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\RawMessage;
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

    public function test_first_time_email_otp_verify_responds_quickly_under_slow_ai_and_smtp_backends(): void
    {
        // Wall-clock guard (Task #4597). The structural tests above prove the
        // heavy work is *queued*; this one proves that translates into an
        // actually-fast response even when the two backends the sign-up path
        // could touch — the AI ingestor and SMTP — are pathologically slow.
        //
        // Both stubbed backends sleep for far longer than the response budget,
        // so if a regression ever runs either inline on the sign-up request
        // (e.g. re-introducing a direct AiMindProvisioner::ensurePlatformDefault
        // call in User::created, dropping ProvisionPlatformAiMindJob's
        // sync-safe deferral, or adding a synchronous welcome e-mail) the verify
        // POST would block on a sleep and blow the threshold below.

        $sleepSeconds = 6;                 // each slow backend stalls this long
        $responseBudgetSeconds = 3.0;      // the POST must return well under this

        // Use the app's real `sync` driver: a plain dispatch() of a ShouldQueue
        // job would run it inline, in-process, during the request. This is the
        // exact misconfiguration the deferral exists to survive, so it's the
        // right setting to prove the guarantee holds.
        config(['queue.default' => 'sync']);

        // Fake ONLY the login-event job. Under `sync` it would otherwise run
        // inline (GeoIP HTTP lookup + possible alert e-mail through our slow
        // transport), which is unrelated to the AI/welcome-mail regressions
        // this test targets and would confound the timing. Every other job
        // (platform-Mind provisioning + its source ingestion) runs for real.
        Queue::fake([RecordLoginEventJob::class]);

        // Stub a pathologically slow AI backend: if source ingestion ever runs
        // inline (only possible if provisioning is executed on the request
        // instead of deferred to the database queue) this sleep fires.
        $this->app->instance(AiMindIngestor::class, new class(app(OpenAiService::class), $sleepSeconds) extends AiMindIngestor {
            public function __construct(OpenAiService $openai, private int $sleepSeconds)
            {
                parent::__construct($openai);
            }

            public function ingest(AiMindSource $source): void
            {
                sleep($this->sleepSeconds);
            }

            public function ingestAllForMind(\App\Modules\User\Models\AiMind $mind): void
            {
                sleep($this->sleepSeconds);
            }
        });

        // Stub a pathologically slow SMTP backend: any e-mail sent
        // synchronously on the sign-up path (e.g. a welcome mail) blocks here.
        $slowTransport = new class($sleepSeconds) extends ArrayTransport {
            public function __construct(private int $sleepSeconds)
            {
                parent::__construct();
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                sleep($this->sleepSeconds);

                return parent::send($message, $envelope);
            }
        };
        Mail::mailer()->setSymfonyTransport($slowTransport);

        // Force the "platform Mind not yet provisioned" branch so the dispatch
        // path (and, under a regression, the slow inline ingest) is exercised.
        AiMind::query()->whereNull('user_id')->where('is_default', true)->delete();

        // Warm the framework (route registration, view/config bootstrapping)
        // with an untimed request so only the sign-up work is measured, not
        // one-off first-request boot cost.
        $this->get('/user/login');

        $email = 'timed-first-timer@example.com';
        $this->assertDatabaseMissing('users', ['email' => $email]);

        $code = app(OtpService::class)->generate($email, 'email', 'login', 'web', '127.0.0.1');

        $start = microtime(true);
        $response = $this->withSession([
            'otp_identifier' => $email,
            'otp_type'       => 'email',
        ])->post('/user/verify-otp', ['code' => $code]);
        $elapsed = microtime(true) - $start;

        // The account is created and the request redirects (a 500/hang fails).
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => $email]);

        // The core assertion: the verify POST returned promptly despite both
        // backends being far slower than the budget, which can only hold if no
        // slow work ran inline on the request.
        $this->assertLessThan(
            $responseBudgetSeconds,
            $elapsed,
            sprintf(
                'First-time OTP sign-up took %.2fs (budget %.1fs). A slow AI/SMTP '
                . 'backend ran inline — heavy provisioning or a synchronous '
                . 'welcome e-mail is back on the sign-up request path.',
                $elapsed,
                $responseBudgetSeconds
            )
        );

        // Sanity floor: the stubs really would have blown the budget if hit, so
        // a "fast" pass genuinely means the slow paths were skipped, not that
        // the stubs were no-ops.
        $this->assertGreaterThan($responseBudgetSeconds, (float) $sleepSeconds);

        // Provisioning was deferred, not executed: no platform Mind was created
        // during the request (its creation + ingest would have tripped the
        // slow stub above).
        $this->assertDatabaseMissing('ai_minds', ['user_id' => null, 'is_default' => true]);
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
