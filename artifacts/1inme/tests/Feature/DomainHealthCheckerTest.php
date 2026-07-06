<?php

namespace Tests\Feature;

use App\Mail\DomainHealthAlertMail;
use App\Modules\Common\Services\DomainHealthChecker;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Coverage for the custom-domain takeover-protection state machine.
 * The DNS lookup itself is exercised through a tiny test double so the
 * tests don't depend on real DNS resolvers being available in CI.
 */
class DomainHealthCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://platform.test']);
        config(['domains.drift_grace_hours' => 168]);
        Mail::fake();
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
        ]);
    }

    private function makeDomain(User $user, array $attrs = []): Domain
    {
        return Domain::create(array_merge([
            'user_id'      => $user->id,
            'domain'       => 'links.example.com',
            'cname_target' => 'platform.test',
            'is_active'    => true,
            'is_verified'  => true,
            'verified_at'  => now()->subDays(30),
            'type'         => 'redirect',
        ], $attrs));
    }

    private function checker(string $resolveTo = 'platform.test'): DomainHealthChecker
    {
        return new class (app(NotificationService::class), $resolveTo) extends DomainHealthChecker {
            public function __construct(NotificationService $n, private string $stub)
            {
                parent::__construct($n);
            }
            protected function resolve(string $host, string $expected): array
            {
                if ($this->stub === '__none__') return [false, null];
                return [strtolower($this->stub) === strtolower($expected), strtolower($this->stub)];
            }
        };
    }

    public function test_healthy_dns_records_status_and_does_not_notify(): void
    {
        $user   = $this->makeUser();
        $domain = $this->makeDomain($user);

        $status = $this->checker('platform.test')->checkDomain($domain->fresh());

        $this->assertSame(Domain::DNS_STATUS_HEALTHY, $status);
        $domain->refresh();
        $this->assertTrue($domain->is_verified);
        $this->assertNull($domain->dns_drift_started_at);
        $this->assertNotNull($domain->dns_last_checked_at);
        Mail::assertNothingSent();
        $this->assertSame(0, UserNotification::where('user_id', $user->id)->count());
    }

    public function test_drift_opens_window_and_notifies_once(): void
    {
        $user   = $this->makeUser();
        $domain = $this->makeDomain($user);

        $checker = $this->checker('someoneelse.cdn.com');
        $checker->checkDomain($domain->fresh());

        $domain->refresh();
        $this->assertSame(Domain::DNS_STATUS_DRIFTING, $domain->dns_status);
        $this->assertTrue($domain->is_verified, 'domain must stay verified during the drift window so the unique-domain claim lock holds');
        $this->assertNotNull($domain->dns_drift_started_at);
        Mail::assertSent(DomainHealthAlertMail::class, 1);
        $this->assertSame(1, UserNotification::where('user_id', $user->id)->where('type', 'custom_domain_drift')->count());

        // Re-running within the cooldown window must not double-notify.
        $checker->checkDomain($domain->fresh());
        Mail::assertSent(DomainHealthAlertMail::class, 1);
        $this->assertSame(1, UserNotification::where('user_id', $user->id)->where('type', 'custom_domain_drift')->count());
    }

    public function test_drift_recovers_when_dns_comes_back(): void
    {
        $user   = $this->makeUser();
        $domain = $this->makeDomain($user, [
            'dns_status'           => Domain::DNS_STATUS_DRIFTING,
            'dns_drift_started_at' => now()->subHours(3),
            'dns_drift_notified_at'=> now()->subHours(3),
        ]);

        $this->checker('platform.test')->checkDomain($domain->fresh());

        $domain->refresh();
        $this->assertSame(Domain::DNS_STATUS_HEALTHY, $domain->dns_status);
        $this->assertNull($domain->dns_drift_started_at);
        $this->assertNull($domain->dns_drift_notified_at);
        $this->assertTrue($domain->is_verified);
    }

    public function test_grace_elapses_auto_unverifies_and_keeps_claim_lock(): void
    {
        $user   = $this->makeUser();
        $domain = $this->makeDomain($user, [
            'dns_status'            => Domain::DNS_STATUS_DRIFTING,
            'dns_drift_started_at'  => now()->subHours(200),
            'dns_drift_notified_at' => now()->subHours(50),
        ]);

        $status = $this->checker('someoneelse.cdn.com')->checkDomain($domain->fresh());

        $this->assertSame(Domain::DNS_STATUS_UNVERIFIED, $status);
        $domain->refresh();
        $this->assertFalse($domain->is_verified);
        $this->assertNotNull($domain->dns_unverified_warning_sent_at);
        Mail::assertSent(DomainHealthAlertMail::class, 1);
        $this->assertSame(1, UserNotification::where('user_id', $user->id)->where('type', 'custom_domain_unverified')->count());

        // The row is preserved so a competing account cannot create a
        // duplicate row for the same hostname (claim lock).
        $this->assertNotNull(Domain::where('domain', $domain->domain)->first());
    }

    public function test_due_domains_skips_global_unverified_and_recently_checked(): void
    {
        $user = $this->makeUser();
        $a = $this->makeDomain($user, ['domain' => 'a.example.com', 'dns_last_checked_at' => null]);
        $b = $this->makeDomain($user, ['domain' => 'b.example.com', 'dns_last_checked_at' => now()->subMinutes(5)]);
        $c = $this->makeDomain($user, ['domain' => 'c.example.com', 'is_verified' => false]);
        $d = Domain::create([
            'user_id'     => null,
            'domain'      => 'd.example.com',
            'cname_target'=> 'platform.test',
            'is_active'   => true,
            'is_verified' => true,
            'type'        => 'redirect',
        ]);

        $ids = $this->checker()->dueDomains()->pluck('id')->all();

        $this->assertContains($a->id, $ids);
        $this->assertNotContains($b->id, $ids, 'recently-checked domain must be skipped');
        $this->assertNotContains($c->id, $ids, 'unverified domain must be skipped');
        $this->assertNotContains($d->id, $ids, 'admin-global domain must be skipped');
    }
}
