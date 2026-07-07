<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Services\SslCertificateIssuer;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for automatic HTTPS issuance for verified custom/global domains.
 * The certbot/nginx work is behind the Process facade, so these tests fake
 * the helper invocation and assert the state machine: due-domain selection,
 * success/failure bookkeeping, retry backoff, threshold-gated admin alerts
 * (with cooldown dedup) and the recovery notice.
 */
class DomainSslIssuanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'domains.ssl.auto_issue'           => true,
            'domains.ssl.command'              => '/usr/local/sbin/sayzio-issue-cert',
            'domains.ssl.certbot_email'        => null,
            'domains.ssl.timeout'              => 30,
            'domains.ssl.retry_hours'          => 1,
            'domains.ssl.alert_after_attempts' => 3,
            'domains.ssl.alert_cooldown_hours' => 24,
        ]);
        Mail::fake();
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeOpsAdmin(): User
    {
        $role = Role::create([
            'name'  => 'Ops ' . Str::random(4),
            'slug'  => 'ops-' . Str::lower(Str::random(6)),
            'guard' => 'web',
        ]);
        $perm = Permission::firstOrCreate(
            ['slug' => 'user.ops_alerts.receive'],
            ['name' => 'Receive operational alerts', 'group' => 'user-app'],
        );
        $role->permissions()->attach($perm->id);

        $user = $this->makeUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->roles()->attach($role->id);
        $user->flushPermissionCache();

        return $user;
    }

    private function makeDomain(?User $user, array $attrs = []): Domain
    {
        return Domain::create(array_merge([
            'user_id'      => $user?->id,
            'domain'       => 'links-' . Str::lower(Str::random(6)) . '.example.com',
            'cname_target' => 'platform.test',
            'is_active'    => true,
            'is_verified'  => true,
            'verified_at'  => now(),
            'type'         => 'redirect',
        ], $attrs));
    }

    private function issuer(): SslCertificateIssuer
    {
        return app(SslCertificateIssuer::class);
    }

    public function test_command_is_a_noop_when_auto_issue_disabled(): void
    {
        config(['domains.ssl.auto_issue' => false]);
        Process::fake();
        $this->makeDomain($this->makeUser());

        $this->artisan('domains:issue-certificates')->assertSuccessful();

        Process::assertNothingRan();
    }

    public function test_successful_issuance_marks_domain_issued(): void
    {
        Process::fake(['*' => Process::result(output: 'OK: certificate + vhost installed')]);
        $domain = $this->makeDomain($this->makeUser());

        $result = $this->issuer()->issue($domain);

        $this->assertSame(SslCertificateIssuer::RESULT_ISSUED, $result);
        $domain->refresh();
        $this->assertSame(SslCertificateIssuer::STATUS_ISSUED, $domain->ssl_status);
        $this->assertNotNull($domain->ssl_issued_at);
        $this->assertNull($domain->ssl_last_error);
        Process::assertRan(fn ($process) => str_contains(
            is_array($process->command) ? implode(' ', $process->command) : $process->command,
            $domain->domain
        ));
    }

    public function test_global_admin_domain_is_picked_up_too(): void
    {
        Process::fake(['*' => Process::result(output: 'OK')]);
        $global = $this->makeDomain(null, ['is_global' => true]);

        $due = $this->issuer()->dueDomains();

        $this->assertTrue($due->contains('id', $global->id));
        $this->artisan('domains:issue-certificates')->assertSuccessful();
        $this->assertSame(SslCertificateIssuer::STATUS_ISSUED, $global->fresh()->ssl_status);
    }

    public function test_failure_records_error_without_alerting_below_threshold(): void
    {
        Process::fake(['*' => Process::result(output: '', errorOutput: 'certbot: DNS problem', exitCode: 1)]);
        $this->makeOpsAdmin();
        $domain = $this->makeDomain($this->makeUser());

        $result = $this->issuer()->issue($domain);

        $this->assertSame(SslCertificateIssuer::RESULT_FAILED, $result);
        $domain->refresh();
        $this->assertSame(SslCertificateIssuer::STATUS_FAILED, $domain->ssl_status);
        $this->assertSame(1, (int) $domain->ssl_attempts);
        $this->assertStringContainsString('DNS problem', (string) $domain->ssl_last_error);
        $this->assertNull($domain->ssl_alerted_at);
        $this->assertSame(0, UserNotification::where('type', 'domain_ssl_failed')->count());
    }

    public function test_third_failure_alerts_ops_admins_once_per_cooldown(): void
    {
        Process::fake(['*' => Process::result(output: '', errorOutput: 'certbot: timeout', exitCode: 1)]);
        $admin  = $this->makeOpsAdmin();
        $domain = $this->makeDomain($this->makeUser());

        foreach (range(1, 3) as $i) {
            $this->issuer()->issue($domain->fresh());
        }

        $domain->refresh();
        $this->assertSame(3, (int) $domain->ssl_attempts);
        $this->assertNotNull($domain->ssl_alerted_at);
        $this->assertSame(1, UserNotification::where('user_id', $admin->id)->where('type', 'domain_ssl_failed')->count());

        // A fourth failure inside the cooldown window must not re-alert.
        $this->issuer()->issue($domain->fresh());
        $this->assertSame(1, UserNotification::where('user_id', $admin->id)->where('type', 'domain_ssl_failed')->count());
    }

    public function test_recovery_notice_sent_when_previously_alerted_domain_succeeds(): void
    {
        $admin  = $this->makeOpsAdmin();
        $domain = $this->makeDomain($this->makeUser());
        $domain->forceFill([
            'ssl_status'     => SslCertificateIssuer::STATUS_FAILED,
            'ssl_attempts'   => 3,
            'ssl_alerted_at' => now()->subHours(2),
        ])->save();

        Process::fake(['*' => Process::result(output: 'OK')]);
        $result = $this->issuer()->issue($domain->fresh());

        $this->assertSame(SslCertificateIssuer::RESULT_ISSUED, $result);
        $this->assertSame(1, UserNotification::where('user_id', $admin->id)->where('type', 'domain_ssl_issued')->count());
    }

    public function test_due_domains_respects_verification_state_and_backoff(): void
    {
        $user = $this->makeUser();

        $fresh      = $this->makeDomain($user); // never attempted
        $unverified = $this->makeDomain($user, ['is_verified' => false, 'verified_at' => null]);
        $inactive   = $this->makeDomain($user, ['is_active' => false]);
        $issued     = $this->makeDomain($user);
        $issued->forceFill(['ssl_status' => SslCertificateIssuer::STATUS_ISSUED, 'ssl_issued_at' => now()])->save();
        $recent     = $this->makeDomain($user);
        $recent->forceFill(['ssl_status' => SslCertificateIssuer::STATUS_FAILED, 'ssl_last_attempted_at' => now()->subMinutes(10)])->save();
        $stale      = $this->makeDomain($user);
        $stale->forceFill(['ssl_status' => SslCertificateIssuer::STATUS_FAILED, 'ssl_last_attempted_at' => now()->subHours(2)])->save();

        $ids = $this->issuer()->dueDomains()->pluck('id');

        $this->assertTrue($ids->contains($fresh->id));
        $this->assertTrue($ids->contains($stale->id));
        $this->assertFalse($ids->contains($unverified->id));
        $this->assertFalse($ids->contains($inactive->id));
        $this->assertFalse($ids->contains($issued->id));
        $this->assertFalse($ids->contains($recent->id));
    }

    public function test_mark_pending_resets_ssl_state(): void
    {
        $domain = $this->makeDomain($this->makeUser());
        $domain->forceFill([
            'ssl_status'            => SslCertificateIssuer::STATUS_FAILED,
            'ssl_attempts'          => 5,
            'ssl_last_attempted_at' => now(),
            'ssl_last_error'        => 'old error',
            'ssl_alerted_at'        => now(),
        ])->save();

        SslCertificateIssuer::markPending($domain->fresh());

        $domain->refresh();
        $this->assertSame(SslCertificateIssuer::STATUS_PENDING, $domain->ssl_status);
        $this->assertSame(0, (int) $domain->ssl_attempts);
        $this->assertNull($domain->ssl_last_attempted_at);
        $this->assertNull($domain->ssl_last_error);
        $this->assertNull($domain->ssl_alerted_at);
    }

    public function test_unsafe_domain_name_is_refused_before_reaching_the_helper(): void
    {
        Process::fake();
        $domain = $this->makeDomain($this->makeUser());
        // Bypass validation to simulate a hostile value already in the DB.
        Domain::withoutGlobalScope('workspace')->whereKey($domain->id)
            ->update(['domain' => 'evil.com; rm -rf /']);

        $result = $this->issuer()->issue($domain->fresh());

        $this->assertSame(SslCertificateIssuer::RESULT_FAILED, $result);
        Process::assertNothingRan();
        $this->assertStringContainsString('safety pattern', (string) $domain->fresh()->ssl_last_error);
    }
}
