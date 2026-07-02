<?php

namespace Tests\Feature;

use App\Modules\Common\Support\PlatformHosts;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression guard for the embed URL builders on {@see Link}.
 *
 * `Link::embedBaseUrl()` must always produce a publicly reachable host — a
 * verified custom domain when one is attached, otherwise the real platform
 * host resolved via {@see PlatformHosts::primary()}. It must NEVER fall back
 * to a loopback host (localhost / 127.0.0.1), because the embed snippets are
 * copy-pasted onto third-party sites where "localhost" is a dead link.
 *
 * These tests pin that contract so a future change to the host-resolution
 * logic can't silently reintroduce localhost URLs in embed snippets.
 */
class LinkEmbedUrlTest extends TestCase
{
    use RefreshDatabase;

    /** A recognised brand host that is never loopback. */
    private const BRAND_HOST = 'sayzio.app';

    private function makeUser(): User
    {
        $user = User::create([
            'name'     => 'Embed ' . Str::random(4),
            'email'    => 'embed-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function makeLink(User $user, ?int $domainId, string $alias): Link
    {
        return Link::create([
            'user_id'   => $user->id,
            'domain_id' => $domainId,
            'type'      => 'url',
            'alias'     => $alias,
            'long_url'  => 'https://dest.example.com/x',
            'is_active' => true,
            'settings'  => ['open_in_app' => false],
        ]);
    }

    /**
     * Configure the app so the platform host resolves to a real brand host.
     * Without this, a bare test environment could leave APP_URL pointing at
     * localhost — which is precisely the value the embed URL must avoid.
     */
    private function forcePlatformHost(string $host): void
    {
        config(['app.url' => 'https://' . $host]);
    }

    public function test_embed_base_url_uses_real_platform_host_without_custom_domain(): void
    {
        $this->forcePlatformHost(self::BRAND_HOST);

        $user = $this->makeUser();
        $link = $this->makeLink($user, null, 'embed-' . Str::random(6));

        $base = $link->embedBaseUrl();

        // The base URL is built on the resolved platform primary host...
        $this->assertSame('https://' . PlatformHosts::primary(), $base);
        // ...which for this configuration is the brand host, never loopback.
        $this->assertSame('https://' . self::BRAND_HOST, $base);
        $this->assertStringNotContainsString('localhost', $base);
        $this->assertStringNotContainsString('127.0.0.1', $base);
    }

    public function test_embed_base_url_uses_verified_custom_domain_when_attached(): void
    {
        $this->forcePlatformHost(self::BRAND_HOST);

        $user   = $this->makeUser();
        $custom = Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'go.example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $link = $this->makeLink($user, $custom->id, 'embed-' . Str::random(6));

        // The verified custom domain wins over the platform host.
        $this->assertSame('https://go.example.com', $link->embedBaseUrl());
        $this->assertStringNotContainsString(self::BRAND_HOST, $link->embedBaseUrl());
    }

    public function test_embed_script_snippet_never_contains_localhost(): void
    {
        $this->forcePlatformHost(self::BRAND_HOST);

        $user = $this->makeUser();
        $link = $this->makeLink($user, null, 'embed-' . Str::random(6));

        $snippet = $link->embedScriptSnippet();

        $this->assertStringNotContainsString('localhost', $snippet);
        $this->assertStringNotContainsString('127.0.0.1', $snippet);
        // Positively assert it is built on the real platform host.
        $this->assertStringContainsString('https://' . PlatformHosts::primary(), $snippet);
    }

    public function test_embed_iframe_snippet_never_contains_localhost(): void
    {
        $this->forcePlatformHost(self::BRAND_HOST);

        $user = $this->makeUser();
        $link = $this->makeLink($user, null, 'embed-' . Str::random(6));

        $snippet = $link->embedIframeSnippet();

        $this->assertStringNotContainsString('localhost', $snippet);
        $this->assertStringNotContainsString('127.0.0.1', $snippet);
        $this->assertStringContainsString('https://' . PlatformHosts::primary(), $snippet);
    }

    public function test_embed_snippets_use_custom_domain_when_attached(): void
    {
        $this->forcePlatformHost(self::BRAND_HOST);

        $user   = $this->makeUser();
        $custom = Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'links.example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        $link = $this->makeLink($user, $custom->id, 'embed-' . Str::random(6));

        $this->assertStringContainsString('https://links.example.com/embed/link/', $link->embedScriptSnippet());
        $this->assertStringContainsString('https://links.example.com/embed/link/', $link->embedIframeSnippet());
    }
}
