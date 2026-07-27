<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Support\ScheduledJobHealthAlerts;
use App\Modules\Common\Support\PlatformHosts;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for PlatformHosts::outboundUrl() — the canonical outbound-link
 * normaliser that rewrites generated absolute URLs from a non-primary brand
 * domain (e.g. production APP_URL still on https://1in.me) to the primary
 * brand domain (sayzio.app). Dev/preview hosts, custom domains, and relative
 * URLs must pass through untouched, and the CLI/queue URL builders (scheduler
 * health alerts, billing links) must route through the helper.
 */
class OutboundBrandUrlTest extends TestCase
{
    use RefreshDatabase;

    // ── Helper unit behaviour ────────────────────────────────────

    public function test_non_primary_brand_host_is_rewritten_to_primary(): void
    {
        $this->assertSame(
            'https://sayzio.app/admin/cron-jobs',
            PlatformHosts::outboundUrl('https://1in.me/admin/cron-jobs'),
        );
    }

    public function test_query_and_fragment_are_preserved_and_scheme_forced_https(): void
    {
        $this->assertSame(
            'https://sayzio.app/user/billing?tab=invoices#latest',
            PlatformHosts::outboundUrl('http://1in.me/user/billing?tab=invoices#latest'),
        );
    }

    public function test_primary_brand_host_passes_through_unchanged(): void
    {
        $this->assertSame(
            'https://sayzio.app/admin',
            PlatformHosts::outboundUrl('https://sayzio.app/admin'),
        );
    }

    public function test_dev_and_custom_hosts_are_left_untouched(): void
    {
        foreach ([
            'https://my-app.replit.dev/admin/cron-jobs',
            'http://localhost:5000/admin/cron-jobs',
            'https://links.customer-domain.com/abc',
        ] as $url) {
            $this->assertSame($url, PlatformHosts::outboundUrl($url), "must not rewrite {$url}");
        }
    }

    public function test_relative_and_empty_urls_pass_through(): void
    {
        $this->assertSame('/admin/cron-jobs', PlatformHosts::outboundUrl('/admin/cron-jobs'));
        $this->assertSame('', PlatformHosts::outboundUrl(''));
    }

    // ── CLI/queue builders route through the helper ──────────────

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

        $user = User::create([
            'name'              => 'Ops Olivia',
            'email'             => 'olivia' . Str::random(6) . '@ops.test',
            'password'          => bcrypt('secret'),
            'status'            => 'active',
            'role'              => 'user',
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role->id);
        $user->flushPermissionCache();

        return $user;
    }

    public function test_scheduler_health_alert_links_to_primary_brand_when_base_url_is_legacy(): void
    {
        config(['app.url' => 'https://1in.me']);
        URL::forceRootUrl('https://1in.me');

        $ops = $this->makeOpsAdmin();
        ScheduledJobHealthAlerts::jobFinished('contacts:sync', false, 'boom', 1, 'schedule');

        $note = UserNotification::where('user_id', $ops->id)
            ->where('type', 'scheduled_job_failed')
            ->firstOrFail();

        $this->assertSame('https://sayzio.app/admin/cron-jobs', $note->data['url']);
    }

    public function test_scheduler_health_alert_keeps_dev_host_links(): void
    {
        config(['app.url' => 'https://my-app.replit.dev']);
        URL::forceRootUrl('https://my-app.replit.dev');

        $ops = $this->makeOpsAdmin();
        ScheduledJobHealthAlerts::jobFinished('reviews:sync', false, 'kaput', 1, 'schedule');

        $note = UserNotification::where('user_id', $ops->id)
            ->where('type', 'scheduled_job_failed')
            ->firstOrFail();

        $this->assertStringContainsString('my-app.replit.dev/admin/cron-jobs', $note->data['url']);
        $this->assertStringNotContainsString('sayzio.app', $note->data['url']);
    }

    public function test_billing_manage_url_is_rewritten_to_primary_brand(): void
    {
        config(['app.url' => 'https://1in.me']);
        URL::forceRootUrl('https://1in.me');

        $lifecycle = app(\App\Services\Billing\SubscriptionLifecycle::class);
        $method = new \ReflectionMethod($lifecycle, 'billingManageUrl');

        $this->assertSame('https://sayzio.app/user/billing', $method->invoke($lifecycle));
    }
}
