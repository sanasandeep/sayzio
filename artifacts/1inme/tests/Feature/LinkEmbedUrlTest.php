<?php

namespace Tests\Feature;

use App\Modules\Common\Support\PlatformHosts;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        return User::factory()->create()->fresh();
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

    /**
     * Text links embed as a compact card, not a tall full-page iframe
     * (task #6712) — otherwise the editor preview and copyable snippet
     * leave a huge blank area under a few lines of text.
     */
    public function test_text_links_embed_as_compact_card(): void
    {
        $this->forcePlatformHost(self::BRAND_HOST);

        $user = $this->makeUser();
        $link = Link::create([
            'user_id'   => $user->id,
            'type'      => 'text',
            'alias'     => 'embed-txt-' . Str::random(6),
            'is_active' => true,
            'settings'  => ['text_content' => 'Hello world'],
        ]);

        $this->assertTrue($link->isEmbedCard());
        $this->assertSame('card', $link->embedKind());
        $this->assertSame('View text', $link->embedAction()['label']);

        // Compact card iframe snippet — never the 80vh/560px full-page one.
        // No subtitle (no seo_description) → the shorter card height.
        $snippet = $link->embedIframeSnippet();
        $this->assertStringContainsString('height:148px', $snippet);
        $this->assertStringNotContainsString('80vh', $snippet);
        $this->assertStringNotContainsString('min-height:560px', $snippet);

        // Adding a description makes the card render a subtitle row, so the
        // static snippet grows to fit (task #6713).
        $link->seo_description = 'A short description shown as the card subtitle';
        $this->assertSame('A short description shown as the card subtitle', $link->embedCardSubtitle());
        $this->assertStringContainsString('height:164px', $link->embedIframeSnippet());
    }

    public function test_text_link_card_endpoint_renders_title_and_action(): void
    {
        $this->forcePlatformHost(self::BRAND_HOST);

        $user = $this->makeUser();
        $link = Link::create([
            'user_id'   => $user->id,
            'type'      => 'text',
            'alias'     => 'embed-txt-' . Str::random(6),
            'title'     => 'My shared note',
            'is_active' => true,
            'settings'  => ['text_content' => 'Hello world'],
        ]);

        $this->get('/embed/link/' . $link->alias . '/card')
            ->assertOk()
            ->assertSee('My shared note')
            ->assertSee('View text');

        // The canonical iframe target for a card-style link is the card doc too.
        $this->get('/embed/link/' . $link->alias . '/iframe')
            ->assertOk()
            ->assertSee('View text');
    }

    /**
     * The static no-JS iframe snippet is sized at copy time and can't grow,
     * so the gated / unavailable fallback card layouts must fit WITHIN that
     * height (task #6714): the explanation lives on the single subtitle
     * line, and neither a footnote row nor the badge row renders.
     */
    public function test_gated_and_unavailable_cards_fit_static_snippet_height(): void
    {
        $this->forcePlatformHost(self::BRAND_HOST);

        $user = $this->makeUser();

        // Gated: link later switched to a non-public visibility.
        $gated = Link::create([
            'user_id'    => $user->id,
            'type'       => 'url',
            'alias'      => 'embed-gated-' . Str::random(6),
            'long_url'   => 'https://dest.example.com/x',
            'title'      => 'Now private',
            'is_active'  => true,
            'visibility' => 'followers',
        ]);

        $res = $this->get('/embed/link/' . $gated->alias . '/card')->assertOk();
        $res->assertSee('Private link — open to view if you have access.');
        $res->assertSee('View on site');
        // No extra footnote row and no badge row → fallback fits the copied height.
        $res->assertDontSee('class="footnote"', false);
        $res->assertDontSee('class="badge"', false);

        // Unavailable: link later deactivated.
        $off = Link::create([
            'user_id'   => $user->id,
            'type'      => 'url',
            'alias'     => 'embed-off-' . Str::random(6),
            'long_url'  => 'https://dest.example.com/x',
            'title'     => 'Now off',
            'is_active' => false,
        ]);

        $res = $this->get('/embed/link/' . $off->alias . '/card')->assertOk();
        $res->assertSee('This link is not available right now.');
        $res->assertDontSee('class="footnote"', false);
        $res->assertDontSee('class="badge"', false);

        // Happy path still renders the badge row.
        $ok = $this->makeLink($user, null, 'embed-ok-' . Str::random(6));
        $this->get('/embed/link/' . $ok->alias . '/card')
            ->assertOk()
            ->assertSee('class="badge"', false);
    }
}
