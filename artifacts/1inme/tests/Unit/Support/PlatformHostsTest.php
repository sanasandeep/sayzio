<?php

namespace Tests\Unit\Support;

use App\Modules\Common\Support\PlatformHosts;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the helper that decides whether an inbound request host
 * should be treated as the platform or as a custom domain. Regressions
 * here silently break either the preview pane or every custom-domain
 * link, so every host class is exercised explicitly.
 */
class PlatformHostsTest extends TestCase
{
    use RefreshDatabase;

    private string $previousAppUrl;
    private ?string $previousDevDomain;
    private ?string $previousDeployedDomains;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousAppUrl = (string) config('app.url');
        $this->previousDevDomain = getenv('REPLIT_DEV_DOMAIN') !== false ? getenv('REPLIT_DEV_DOMAIN') : null;
        $this->previousDeployedDomains = getenv('REPLIT_DOMAINS') !== false ? getenv('REPLIT_DOMAINS') : null;
        // Pin the configured platform hosts so assertions don't depend on
        // whatever the developer's .env / process env happens to set.
        config(['app.url' => 'https://platform.test']);
        $this->setReplitEnv('REPLIT_DEV_DOMAIN', 'dev-replit.example.dev');
        $this->setReplitEnv('REPLIT_DOMAINS', 'app-one.example.com,app-two.example.com');
    }

    protected function tearDown(): void
    {
        config(['app.url' => $this->previousAppUrl]);
        $this->setReplitEnv('REPLIT_DEV_DOMAIN', $this->previousDevDomain);
        $this->setReplitEnv('REPLIT_DOMAINS', $this->previousDeployedDomains);
        parent::tearDown();
    }

    /**
     * Sets / unsets an env var across every adapter the Laravel `env()`
     * helper might consult ($_ENV, $_SERVER, putenv, the cached Env
     * repository). Setting only one of those is not enough — different
     * Laravel/Dotenv versions read from different adapters, so PlatformHosts
     * could otherwise see stale values from the surrounding test process.
     */
    private function setReplitEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
            \Illuminate\Support\Env::getRepository()->clear($key);
            return;
        }
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
        \Illuminate\Support\Env::getRepository()->set($key, $value);
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    public function test_normalize_handles_null_empty_case_and_port(): void
    {
        $this->assertNull(PlatformHosts::normalize(null));
        $this->assertNull(PlatformHosts::normalize(''));
        $this->assertNull(PlatformHosts::normalize('   '));
        $this->assertSame('example.com', PlatformHosts::normalize('Example.COM'));
        $this->assertSame('example.com', PlatformHosts::normalize('Example.com:8443'));
        $this->assertSame('example.com', PlatformHosts::normalize('  example.com  '));
    }

    public function test_configured_includes_app_url_replit_dev_and_deployed_domains(): void
    {
        $configured = PlatformHosts::configured();

        $this->assertContains('platform.test', $configured);
        $this->assertContains('dev-replit.example.dev', $configured);
        $this->assertContains('app-one.example.com', $configured);
        $this->assertContains('app-two.example.com', $configured);
        // No duplicates even if values overlap.
        $this->assertSame(array_values(array_unique($configured)), $configured);
    }

    public function test_configured_handles_missing_replit_envs_gracefully(): void
    {
        $this->setReplitEnv('REPLIT_DEV_DOMAIN', null);
        $this->setReplitEnv('REPLIT_DOMAINS', null);

        $configured = PlatformHosts::configured();

        $this->assertSame(['platform.test'], $configured);
    }

    public function test_is_platform_host_for_app_url_dev_and_deployed_hosts(): void
    {
        $this->assertTrue(PlatformHosts::isPlatformHost('platform.test'));
        $this->assertTrue(PlatformHosts::isPlatformHost('dev-replit.example.dev'));
        $this->assertTrue(PlatformHosts::isPlatformHost('app-one.example.com'));
        $this->assertTrue(PlatformHosts::isPlatformHost('app-two.example.com'));
    }

    public function test_is_platform_host_is_case_insensitive_and_ignores_port(): void
    {
        $this->assertTrue(PlatformHosts::isPlatformHost('Platform.Test'));
        $this->assertTrue(PlatformHosts::isPlatformHost('PLATFORM.TEST:9000'));
        $this->assertTrue(PlatformHosts::isPlatformHost('App-One.Example.Com:443'));
    }

    public function test_is_platform_host_for_null_or_empty_host(): void
    {
        $this->assertTrue(PlatformHosts::isPlatformHost(null));
        $this->assertTrue(PlatformHosts::isPlatformHost(''));
    }

    public function test_is_platform_host_false_for_verified_active_custom_domain(): void
    {
        $user = $this->makeUser();
        Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'go.example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
            'verified_at' => now(),
        ]);

        $this->assertFalse(PlatformHosts::isPlatformHost('go.example.com'));
        $this->assertFalse(PlatformHosts::isPlatformHost('GO.EXAMPLE.COM:443'));
    }

    public function test_is_platform_host_true_for_unverified_or_inactive_custom_domain_rows(): void
    {
        $user = $this->makeUser();
        Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'pending.example.com',
            'type'        => 'custom',
            'is_verified' => false,
            'is_active'   => true,
        ]);
        Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'disabled.example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => false,
        ]);

        $this->assertTrue(PlatformHosts::isPlatformHost('pending.example.com'));
        $this->assertTrue(PlatformHosts::isPlatformHost('disabled.example.com'));
    }

    public function test_is_platform_host_true_for_completely_unknown_host(): void
    {
        // Anything not configured AND not a verified+active custom domain
        // counts as platform — caller decides whether to surface the
        // "Domain not connected" notice based on isPendingCustomDomain().
        $this->assertTrue(PlatformHosts::isPlatformHost('random-stranger.example.org'));
    }

    public function test_is_pending_custom_domain_true_for_unverified_or_inactive(): void
    {
        $user = $this->makeUser();
        Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'pending.example.com',
            'type'        => 'custom',
            'is_verified' => false,
            'is_active'   => true,
        ]);
        Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'disabled.example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => false,
        ]);

        $this->assertTrue(PlatformHosts::isPendingCustomDomain('pending.example.com'));
        $this->assertTrue(PlatformHosts::isPendingCustomDomain('PENDING.EXAMPLE.COM:443'));
        $this->assertTrue(PlatformHosts::isPendingCustomDomain('disabled.example.com'));
    }

    public function test_is_pending_custom_domain_false_for_verified_active_or_unknown(): void
    {
        $user = $this->makeUser();
        Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'go.example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
            'verified_at' => now(),
        ]);

        $this->assertFalse(PlatformHosts::isPendingCustomDomain('go.example.com'));
        $this->assertFalse(PlatformHosts::isPendingCustomDomain('not-registered.example.com'));
        $this->assertFalse(PlatformHosts::isPendingCustomDomain(null));
        $this->assertFalse(PlatformHosts::isPendingCustomDomain(''));
    }
}
