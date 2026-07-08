<?php

namespace Tests\Feature;

use App\Jobs\RecordLoginEventJob;
use App\Jobs\RecordAdminLastLoginJob;
use App\Mail\SuspiciousLoginMail;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Services\GeoIpService;
use App\Modules\Common\Services\LoginAlertService;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\User\Models\LoginEvent;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * Verifies that login surfaces dispatch RecordLoginEventJob (instead of
 * calling LoginAlertService inline) and that the job itself reproduces the
 * full suspicious-login pipeline.
 */
class LoginEventJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function fakeGeo(?string $country): void
    {
        $mock = Mockery::mock(GeoIpService::class);
        $mock->shouldReceive('detectCountry')->andReturn($country);
        $this->app->instance(GeoIpService::class, $mock);
    }

    // -----------------------------------------------------------------------
    // Job dispatch: web password login
    // -----------------------------------------------------------------------

    public function test_password_login_dispatches_job_not_inline(): void
    {
        Queue::fake();
        // Password login is off by default; enable it for this test.
        AppSetting::put(AuthMethods::SETTING_EMAIL_PASSWORD_ENABLED, true);

        // The User model has a 'hashed' cast on the password column, so pass
        // the plain-text value and let the cast hash it automatically.
        $user = User::factory()->create([
            'password' => 'secret123',
            'status'   => 'active',
        ]);

        $this->post(route('user.login.submit'), [
            'email'    => $user->email,
            'password' => 'secret123',
        ])->assertRedirect();

        Queue::assertPushed(RecordLoginEventJob::class, function ($job) use ($user) {
            return $job->userId === $user->id
                && str_contains($job->channel, 'web_password')
                && $job->updateLastLoginAt === true;
        });

        // last_login_at must NOT be set yet (it's deferred to the job).
        $this->assertNull($user->fresh()->last_login_at);
    }

    // -----------------------------------------------------------------------
    // Job channel coverage: api_password
    // -----------------------------------------------------------------------

    /**
     * Verifies that RecordLoginEventJob handles the 'api_password' channel
     * identically to 'web_password' — records a login event and sets
     * last_login_at. The job is dispatched by Api\AuthController on a
     * successful password login (same dispatch call as the web controller).
     */
    public function test_api_password_channel_job_records_event(): void
    {
        $user      = User::factory()->create();
        $loggedAt  = now()->subSeconds(2);

        $job = new RecordLoginEventJob(
            $user->id,
            'api_password',
            '1.2.3.4',
            'TestMobileApp/1.0',
            [],
            true,
            $loggedAt,
        );

        // handle() takes a LoginAlertService via Laravel's IoC; use app()->call().
        app()->call([$job, 'handle']);

        $this->assertDatabaseHas('login_events', [
            'user_id' => $user->id,
            'channel' => 'api_password',
            'ip'      => '1.2.3.4',
        ]);

        // Verify last_login_at was set and is close to the dispatched timestamp.
        // Using diffInSeconds to avoid microsecond precision mismatches from DB.
        $fresh = $user->fresh();
        $this->assertNotNull($fresh->last_login_at);
        $this->assertLessThanOrEqual(2, abs($fresh->last_login_at->diffInSeconds($loggedAt)));
    }

    // -----------------------------------------------------------------------
    // Job dispatch: admin demo login
    // -----------------------------------------------------------------------

    public function test_admin_demo_login_dispatches_admin_job(): void
    {
        Queue::fake();

        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        $this->post(route('admin.demo.login'))->assertRedirect();

        Queue::assertPushed(RecordAdminLastLoginJob::class);
    }

    // -----------------------------------------------------------------------
    // Job execution: full pipeline still records event + sends alert
    // -----------------------------------------------------------------------

    public function test_job_records_login_event_with_last_login_at(): void
    {
        Mail::fake();
        $this->fakeGeo('US');

        $user = User::factory()->create();
        $loggedInAt = now()->subSeconds(2);

        $job = new RecordLoginEventJob(
            userId: $user->id,
            channel: 'web_password',
            ip: '203.0.113.1',
            userAgent: 'Mozilla/5.0 (Macintosh) Chrome/120',
            opts: [],
            updateLastLoginAt: true,
            loggedInAt: $loggedInAt,
        );
        $job->handle(app(LoginAlertService::class));

        $this->assertEquals(
            $loggedInAt->toDateTimeString(),
            $user->fresh()->last_login_at->toDateTimeString(),
            'last_login_at should be the captured dispatch-time timestamp'
        );

        $this->assertDatabaseHas('login_events', [
            'user_id' => $user->id,
            'channel' => 'web_password',
        ]);
    }

    public function test_job_sends_suspicious_login_mail_on_new_country(): void
    {
        Mail::fake();
        $service = app(LoginAlertService::class);
        $user = User::factory()->create();

        // Seed a baseline login from US.
        $this->fakeGeo('US');
        $service->recordRaw($user, '203.0.113.1', 'Mozilla/5.0 (Macintosh) Chrome/120', 'web_password');

        // Now run the job for a login from a brand-new country.
        $this->fakeGeo('RU');
        $job = new RecordLoginEventJob(
            userId: $user->id,
            channel: 'web_password',
            ip: '198.51.100.7',
            userAgent: 'Mozilla/5.0 (Macintosh) Chrome/120',
            opts: [],
            updateLastLoginAt: true,
            loggedInAt: now(),
        );
        $job->handle($service);

        Mail::assertSent(SuspiciousLoginMail::class, fn ($m) => $m->hasTo($user->email));

        $this->assertDatabaseHas('login_events', [
            'user_id'    => $user->id,
            'is_new'     => true,
            'alert_sent' => true,
        ]);
    }

    public function test_job_skips_last_login_update_when_flag_false(): void
    {
        Mail::fake();
        $this->fakeGeo('US');

        $user = User::factory()->create(['last_login_at' => null]);

        $job = new RecordLoginEventJob(
            userId: $user->id,
            channel: 'api_register',
            ip: '203.0.113.1',
            userAgent: 'Sayzio/1.0',
            opts: [],
            updateLastLoginAt: false,
            loggedInAt: null,
        );
        $job->handle(app(LoginAlertService::class));

        $this->assertNull($user->fresh()->last_login_at, 'last_login_at must remain null for registration-only jobs');
    }

    public function test_job_is_silent_when_user_deleted(): void
    {
        // Should not throw even if the user was deleted between dispatch and execution.
        $job = new RecordLoginEventJob(
            userId: PHP_INT_MAX,
            channel: 'web_password',
            ip: '203.0.113.1',
            userAgent: 'Mozilla/5.0',
            opts: [],
            updateLastLoginAt: true,
            loggedInAt: now(),
        );
        $job->handle(app(LoginAlertService::class));
        $this->assertTrue(true);
    }

    // -----------------------------------------------------------------------
    // Timestamp correctness: created_at = dispatch time, not execution time
    // -----------------------------------------------------------------------

    /**
     * Simulates a delayed queue worker: the job carries a loggedInAt captured
     * 5 minutes ago and only executes "now". The login_events row's
     * created_at (what the Recent Logins page and API render) must be the
     * dispatch-captured timestamp, not the job-execution time.
     */
    public function test_event_created_at_matches_dispatch_time_not_execution_time(): void
    {
        Mail::fake();
        $this->fakeGeo('US');

        $user = User::factory()->create();
        $loggedInAt = now()->subMinutes(5)->startOfSecond();

        $job = new RecordLoginEventJob(
            userId: $user->id,
            channel: 'web_password',
            ip: '203.0.113.1',
            userAgent: 'Mozilla/5.0 (Macintosh) Chrome/120',
            opts: [],
            updateLastLoginAt: true,
            loggedInAt: $loggedInAt,
        );
        $job->handle(app(LoginAlertService::class));

        $event = LoginEvent::where('user_id', $user->id)->firstOrFail();
        $this->assertEquals(
            $loggedInAt->toDateTimeString(),
            $event->created_at->toDateTimeString(),
            'login_events.created_at must be the dispatch-captured loggedInAt, not job-execution time'
        );
        // Sanity: execution time is minutes later, so equality above is meaningful.
        $this->assertGreaterThanOrEqual(290, abs(now()->diffInSeconds($event->created_at)));
    }

    /**
     * When no loggedInAt is provided (legacy dispatches), created_at falls
     * back to the execution moment instead of erroring out.
     */
    public function test_event_created_at_defaults_to_now_without_logged_in_at(): void
    {
        Mail::fake();
        $this->fakeGeo('US');

        $user = User::factory()->create();

        $job = new RecordLoginEventJob(
            userId: $user->id,
            channel: 'api_register',
            ip: '203.0.113.1',
            userAgent: 'Sayzio/1.0',
            opts: [],
            updateLastLoginAt: false,
            loggedInAt: null,
        );
        $job->handle(app(LoginAlertService::class));

        $event = LoginEvent::where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($event->created_at);
        $this->assertLessThanOrEqual(5, abs(now()->diffInSeconds($event->created_at)));
    }

    // -----------------------------------------------------------------------
    // Recent Logins surfaces render the job-set timestamp
    // -----------------------------------------------------------------------

    public function test_recent_logins_page_renders_dispatch_time(): void
    {
        Mail::fake();
        $this->fakeGeo('US');

        $user = User::factory()->create();
        $loggedInAt = now()->subMinutes(30)->startOfSecond();

        $job = new RecordLoginEventJob(
            userId: $user->id,
            channel: 'web_password',
            ip: '203.0.113.1',
            userAgent: 'Mozilla/5.0 (Macintosh) Chrome/120',
            opts: [],
            updateLastLoginAt: true,
            loggedInAt: $loggedInAt,
        );
        $job->handle(app(LoginAlertService::class));

        $response = $this->actingAs($user, 'web')->get(route('user.security.logins'));
        $response->assertOk();
        // The blade renders created_at as "M j, Y g:i A".
        $response->assertSee($loggedInAt->format('M j, Y g:i A'));
        // The job-execution time must NOT be what's shown.
        $this->assertNotEquals(
            $loggedInAt->format('M j, Y g:i A'),
            now()->format('M j, Y g:i A')
        );
    }

    public function test_api_recent_logins_returns_dispatch_time(): void
    {
        Mail::fake();
        $this->fakeGeo('US');

        $user = User::factory()->create();
        $loggedInAt = now()->subMinutes(30)->startOfSecond();

        $job = new RecordLoginEventJob(
            userId: $user->id,
            channel: 'api_password',
            ip: '203.0.113.1',
            userAgent: 'TestMobileApp/1.0',
            opts: [],
            updateLastLoginAt: true,
            loggedInAt: $loggedInAt,
        );
        $job->handle(app(LoginAlertService::class));

        // Real bearer token (Sanctum::actingAs breaks TouchSessionToken).
        $plain = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($plain)->getJson('/api/v1/security/logins');
        $response->assertOk();

        $rows = collect($response->json('data.events'))->where('channel', 'api_password');
        $this->assertNotEmpty($rows, 'API should list the recorded login event');
        $this->assertEquals(
            $loggedInAt->toIso8601String(),
            \Illuminate\Support\Carbon::parse($rows->first()['created_at'])->toIso8601String(),
            'API created_at must be the dispatch-captured loggedInAt'
        );
    }

    // -----------------------------------------------------------------------
    // RecordAdminLastLoginJob: updates admin last_login_at
    // -----------------------------------------------------------------------

    public function test_admin_last_login_job_updates_timestamp(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        $admin = Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin-test-job@example.com',
            'password' => Hash::make('password'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);

        $this->assertNull($admin->last_login_at);

        $at = now()->subSeconds(5);
        $job = new RecordAdminLastLoginJob($admin->id, $at);
        $job->handle();

        $this->assertEquals(
            $at->toDateTimeString(),
            $admin->fresh()->last_login_at->toDateTimeString()
        );
    }
}
