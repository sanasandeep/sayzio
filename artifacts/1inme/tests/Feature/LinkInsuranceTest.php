<?php

namespace Tests\Feature;

use App\Modules\Common\Services\LinkHealthChecker;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkBackup;
use App\Modules\User\Models\LinkHealthCheck;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Exercises the Link Insurance failover/restore decision engine end to
 * end without hitting the network — Http::fake() lets us script the
 * exact response sequence the checker should react to.
 */
class LinkInsuranceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(): User
    {
        return User::create([
            'name'     => 'Test '.Str::random(4),
            'email'    => 'u'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    protected function makeInsuredLink(array $overrides = []): Link
    {
        $user = $this->makeUser();
        $link = new Link(array_merge([
            'user_id'                       => $user->id,
            'type'                          => 'url',
            'alias'                         => 'tst'.\Illuminate\Support\Str::random(5),
            'long_url'                      => 'https://primary.example.com/page',
            'is_active'                     => true,
            'redirect_type'                 => 301,
            'insurance_enabled'             => true,
            'insurance_cadence_minutes'     => 30,
            'insurance_failure_threshold'   => 2,
            'insurance_recovery_threshold'  => 2,
            'insurance_auto_restore'        => true,
            'insurance_state'               => 'primary',
        ], $overrides));
        $link->save();
        return $link;
    }

    public function test_failover_promotes_first_healthy_backup_after_threshold_failures(): void
    {
        $link = $this->makeInsuredLink();
        LinkBackup::create(['link_id' => $link->id, 'position' => 1, 'url' => 'https://backup-1.example.com']);
        LinkBackup::create(['link_id' => $link->id, 'position' => 2, 'url' => 'https://backup-2.example.com']);

        Http::fake([
            'primary.example.com/*' => Http::response('', 503),
            '*'                      => Http::response('', 200),
        ]);

        $checker = app(LinkHealthChecker::class);

        // First failure — under threshold, no failover yet.
        $checker->checkLink($link);
        $link->refresh();
        $this->assertSame('primary', $link->insurance_state);
        $this->assertSame(1, $link->insurance_consecutive_failures);

        // Second failure — threshold met, promotes backup #1.
        $checker->checkLink($link);
        $link->refresh();
        $this->assertSame('failover', $link->insurance_state);
        $this->assertSame('https://backup-1.example.com', $link->insurance_active_url);
        $this->assertNotNull($link->insurance_last_failover_at);

        // The link's destination URL now reflects the promoted backup.
        $this->assertStringStartsWith('https://backup-1.example.com', $link->getDestinationUrl());
    }

    public function test_state_goes_to_down_when_all_backups_are_unhealthy(): void
    {
        $link = $this->makeInsuredLink();
        LinkBackup::create([
            'link_id' => $link->id, 'position' => 1, 'url' => 'https://backup-1.example.com',
            'last_status' => 'down', 'last_http_code' => 502, 'last_checked_at' => now(),
        ]);

        Http::fake(['*' => Http::response('', 502)]);

        $checker = app(LinkHealthChecker::class);
        $checker->checkLink($link); // failure 1
        $checker->checkLink($link); // failure 2 → tries to promote, no healthy backup
        $link->refresh();

        $this->assertSame('down', $link->insurance_state);
        $this->assertNull($link->insurance_active_url);
    }

    public function test_recheck_primary_restores_after_recovery_threshold(): void
    {
        $link = $this->makeInsuredLink([
            'insurance_state'      => 'failover',
            'insurance_active_url' => 'https://backup-1.example.com',
        ]);
        LinkBackup::create(['link_id' => $link->id, 'position' => 1, 'url' => 'https://backup-1.example.com']);

        // Backup probe stays healthy; primary returns healthy on the
        // separate primary-recheck path.
        Http::fake(['*' => Http::response('', 200)]);

        $checker = app(LinkHealthChecker::class);

        // recheckPrimaryFromFailover increments the success counter.
        $checker->recheckPrimaryFromFailover($link);
        $link->refresh();
        $this->assertSame('failover', $link->insurance_state, 'Should not restore on first success');
        $this->assertSame(1, $link->insurance_consecutive_successes);

        $checker->recheckPrimaryFromFailover($link);
        $link->refresh();
        $this->assertSame('primary', $link->insurance_state);
        $this->assertNull($link->insurance_active_url);
    }

    public function test_health_check_row_is_recorded_for_every_probe(): void
    {
        $link = $this->makeInsuredLink();
        Http::fake(['*' => Http::response('', 200)]);

        app(LinkHealthChecker::class)->checkLink($link);

        $this->assertDatabaseHas('link_health_checks', [
            'link_id'    => $link->id,
            'status'     => 'healthy',
            'http_code'  => 200,
            'target_url' => 'https://primary.example.com/page',
        ]);
    }

    public function test_settings_endpoint_accepts_fewer_than_three_backups(): void
    {
        $link = $this->makeInsuredLink(['insurance_enabled' => false]);
        $user = $link->user()->first();

        // Submit with one filled slot and two empty slots — the form
        // always renders 3 inputs but the user may only need 1 backup.
        $this->actingAs($user)
            ->post(route('user.links.insurance.update', $link->id), [
                'insurance_enabled'             => '1',
                'insurance_cadence_minutes'     => 30,
                'insurance_failure_threshold'   => 2,
                'insurance_recovery_threshold'  => 2,
                'backups' => [
                    ['url' => 'https://only.example.com', 'label' => null],
                    ['url' => '', 'label' => ''],
                    ['url' => null, 'label' => null],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $link->backups()->count());
    }

    public function test_recovery_resets_when_primary_recheck_fails_between_successes(): void
    {
        $link = $this->makeInsuredLink([
            'insurance_state'                 => 'failover',
            'insurance_active_url'            => 'https://backup-1.example.com',
            'insurance_recovery_threshold'    => 2,
            'insurance_consecutive_failures'  => 0,
            'insurance_consecutive_successes' => 0,
        ]);
        LinkBackup::create(['link_id' => $link->id, 'position' => 1, 'url' => 'https://backup-1.example.com', 'last_status' => 'healthy']);

        // Stub the probe via a partial mock so we can deterministically
        // sequence healthy/down/healthy without Http::fake quirks
        // around HEAD verbs and per-call re-registration.
        $checker = $this->getMockBuilder(LinkHealthChecker::class)
            ->onlyMethods(['probe'])
            ->setConstructorArgs([app(\App\Modules\Common\Services\NotificationService::class)])
            ->getMock();
        $checker->method('probe')->willReturnOnConsecutiveCalls(
            ['status' => 'healthy', 'http_code' => 200, 'latency_ms' => 1, 'error_class' => null, 'error_detail' => null],
            ['status' => 'down',    'http_code' => 500, 'latency_ms' => 1, 'error_class' => 'http_5xx', 'error_detail' => 'HTTP 500'],
            ['status' => 'healthy', 'http_code' => 200, 'latency_ms' => 1, 'error_class' => null, 'error_detail' => null],
        );

        $checker->recheckPrimaryFromFailover($link->fresh());
        $this->assertSame(1, $link->fresh()->insurance_consecutive_successes);
        $this->assertSame('failover', $link->fresh()->insurance_state, 'after first success');

        $checker->recheckPrimaryFromFailover($link->fresh());
        $this->assertSame(0, $link->fresh()->insurance_consecutive_successes,
            'A failed primary recheck must reset the recovery counter.');
        $this->assertSame('failover', $link->fresh()->insurance_state);

        $checker->recheckPrimaryFromFailover($link->fresh());
        $this->assertSame('failover', $link->fresh()->insurance_state,
            'One success after a reset is below the threshold; must stay in failover.');
        $this->assertSame(1, $link->fresh()->insurance_consecutive_successes);
    }

    public function test_settings_endpoint_persists_backups_and_thresholds(): void
    {
        $link = $this->makeInsuredLink(['insurance_enabled' => false]);
        $user = $link->user()->first();

        $this->actingAs($user)
            ->post(route('user.links.insurance.update', $link->id), [
                'insurance_enabled'             => '1',
                'insurance_cadence_minutes'     => 30,
                'insurance_failure_threshold'   => 3,
                'insurance_recovery_threshold'  => 2,
                'insurance_auto_restore'        => '1',
                'backups' => [
                    ['url' => 'https://b1.example.com', 'label' => 'Mirror'],
                    ['url' => 'https://b2.example.com', 'label' => null],
                ],
            ])
            ->assertRedirect();

        $link->refresh();
        $this->assertTrue($link->insurance_enabled);
        $this->assertSame(3, $link->insurance_failure_threshold);
        $this->assertSame(2, $link->backups()->count());
        $this->assertSame('https://b1.example.com', $link->backups()->where('position', 1)->value('url'));
    }
}
