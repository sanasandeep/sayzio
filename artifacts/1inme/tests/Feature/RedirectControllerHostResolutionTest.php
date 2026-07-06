<?php

namespace Tests\Feature;

use App\Modules\User\Models\Domain;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage for `RedirectController::handle` host classification.
 * Pairs with PlatformHostsTest: this exercises the controller's branching
 * by issuing real HTTP requests under different Host headers, plus proves
 * that signed preview URLs survive cross-host validation (which is what
 * lets the editor iframe load the preview on whatever platform host the
 * browser happens to be on).
 */
class RedirectControllerHostResolutionTest extends TestCase
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
        config(['app.url' => 'https://platform.test']);
        URL::forceRootUrl('https://platform.test');
        // env() inside PlatformHosts reads through Laravel's Env repository,
        // which (since putenv was disabled) only sees what the repository
        // itself was set to. Mirror the change in $_ENV / $_SERVER too so
        // the helper sees the same values whichever adapter is active.
        $this->setReplitEnv('REPLIT_DEV_DOMAIN', 'dev-replit.example.dev');
        $this->setReplitEnv('REPLIT_DOMAINS', 'app-one.example.com');
    }

    protected function tearDown(): void
    {
        config(['app.url' => $this->previousAppUrl]);
        URL::forceRootUrl(null);
        $this->previousDevDomain === null
            ? $this->setReplitEnv('REPLIT_DEV_DOMAIN', null)
            : $this->setReplitEnv('REPLIT_DEV_DOMAIN', $this->previousDevDomain);
        $this->previousDeployedDomains === null
            ? $this->setReplitEnv('REPLIT_DOMAINS', null)
            : $this->setReplitEnv('REPLIT_DOMAINS', $this->previousDeployedDomains);
        parent::tearDown();
    }

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

    private function makeUrlLink(User $user, ?int $domainId, string $alias, string $url = 'https://destination.example.com/landing'): Link
    {
        return Link::create([
            'user_id'   => $user->id,
            'domain_id' => $domainId,
            'type'      => 'url',
            'alias'     => $alias,
            'long_url'  => $url,
            'is_active' => true,
            // Disable the app-opener interstitial so the URL link always
            // performs a redirect for plain GETs in tests.
            'settings'  => ['open_in_app' => false],
        ]);
    }

    /**
     * Issue a GET request as if the browser is using $host. Force the URL
     * generator's root to the same host so that Laravel's testing harness
     * builds a fully-qualified URL on that host (otherwise APP_URL wins
     * and `$request->getHost()` ends up as `localhost`).
     */
    private function getOnHost(string $host, string $path)
    {
        URL::forceRootUrl('http://' . $host);
        try {
            return $this->call('GET', $path);
        } finally {
            URL::forceRootUrl('https://platform.test');
        }
    }

    public function test_platform_host_resolves_link_with_null_domain_id(): void
    {
        $user  = $this->makeUser();
        $alias = Link::generateAlias();
        $this->makeUrlLink($user, null, $alias);

        $response = $this->getOnHost('platform.test', '/' . $alias);

        $response->assertRedirect('https://destination.example.com/landing');
    }

    public function test_verified_custom_domain_scopes_alias_lookup_to_its_domain_id(): void
    {
        $user = $this->makeUser();

        $domain = Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'go.example.com',
            'type'        => 'custom',
            'is_verified' => true,
            'is_active'   => true,
            'verified_at' => now(),
        ]);

        // The links table has a global unique constraint on `alias`, so
        // the platform link and the custom-domain link have to use
        // distinct aliases. The point of this test is the *scoping*: each
        // alias must only resolve on the host its link is bound to.
        $platformAlias = 'plat-' . Str::random(6);
        $customAlias   = 'cust-' . Str::random(6);
        $this->makeUrlLink($user, null, $platformAlias, 'https://platform-target.example.com/');
        $this->makeUrlLink($user, $domain->id, $customAlias, 'https://custom-target.example.com/');

        // Platform alias on platform host → resolves.
        $this->getOnHost('platform.test', '/' . $platformAlias)
            ->assertRedirect('https://platform-target.example.com/');

        // Custom-domain alias on its custom host → resolves.
        $this->getOnHost('go.example.com', '/' . $customAlias)
            ->assertRedirect('https://custom-target.example.com/');

        // Custom-domain alias on the platform host → must NOT leak across.
        // The verified custom-domain row is silently invisible from the
        // platform side, so this falls through to "short link not found".
        $this->getOnHost('platform.test', '/' . $customAlias)
            ->assertViewIs('common.short-link-not-found');

        // Platform alias on the verified custom domain → likewise scoped
        // out: the lookup is constrained to domain_id = <go.example.com>,
        // so the platform-only link is not found there either.
        $this->getOnHost('go.example.com', '/' . $platformAlias)
            ->assertViewIs('common.short-link-not-found');
    }

    public function test_unknown_pending_custom_domain_renders_domain_not_connected_view(): void
    {
        $user = $this->makeUser();

        // Domain row exists but isn't verified yet → "Domain not connected".
        Domain::create([
            'user_id'     => $user->id,
            'domain'      => 'pending.example.com',
            'type'        => 'custom',
            'is_verified' => false,
            'is_active'   => true,
        ]);

        $response = $this->getOnHost('pending.example.com', '/anything');

        $response->assertStatus(404);
        $response->assertViewIs('common.domain-not-connected');
        $response->assertViewHas('host', 'pending.example.com');
    }

    public function test_unknown_host_with_no_domain_row_falls_through_to_short_link_not_found(): void
    {
        // Truly unknown host (not configured + no row) is treated as
        // platform; an alias miss there must NOT show the custom-domain
        // notice, it should show "short link not found".
        $response = $this->getOnHost('random-stranger.example.org', '/missing-' . Str::random(5));

        $response->assertStatus(404);
        $response->assertViewIs('common.short-link-not-found');
    }

    /**
     * The editor iframe generates a signed preview URL on whatever host
     * APP_URL is pinned to and then loads it in a parent page that may be
     * on a different platform host (e.g. a Replit dev domain). The
     * signature must validate either way — that's what
     * `URL::temporarySignedRoute(..., absolute: false)` buys us, and this
     * test pins it down so it can't silently regress.
     */
    public function test_signed_preview_url_validates_when_hit_on_a_different_platform_host(): void
    {
        URL::forceRootUrl('https://platform.test');

        $signedRelative = URL::temporarySignedRoute(
            'redirect.handle',
            now()->addHour(),
            ['alias' => 'somealias', '_preview' => 1],
            false
        );
        $this->assertStringStartsWith('/somealias?', $signedRelative);
        $this->assertStringContainsString('signature=', $signedRelative);

        $build = function (string $host, string $url): Request {
            $req = Request::create($url, 'GET', [], [], [], [
                'HTTP_HOST'   => $host,
                'HTTPS'       => 'on',
                'SERVER_PORT' => '443',
            ]);
            $route = Route::getRoutes()->match($req);
            $req->setRouteResolver(fn () => $route);
            return $req;
        };

        // Same host as APP_URL → valid.
        $this->assertTrue(
            $build('platform.test', $signedRelative)
                ->hasValidSignatureWhileIgnoring(['_draft', '_t'], false),
            'Signed preview URL should validate on the host it was generated on.'
        );

        // Different platform host → still valid because the URL was
        // signed as relative (host is intentionally not part of the HMAC).
        $this->assertTrue(
            $build('dev-replit.example.dev', $signedRelative)
                ->hasValidSignatureWhileIgnoring(['_draft', '_t'], false),
            'Signed preview URL should validate on a different platform host.'
        );
        $this->assertTrue(
            $build('app-one.example.com', $signedRelative)
                ->hasValidSignatureWhileIgnoring(['_draft', '_t'], false),
            'Signed preview URL should validate on a deployed Replit host.'
        );

        // Editor appends `_draft` and `_t` client-side; signature must
        // tolerate those even though they weren't part of the signed URL.
        $withDraft = $signedRelative . '&_draft=1&_t=' . time();
        $this->assertTrue(
            $build('dev-replit.example.dev', $withDraft)
                ->hasValidSignatureWhileIgnoring(['_draft', '_t'], false),
            'Signed preview URL should still validate after appending _draft/_t.'
        );

        // Tampering with the alias must invalidate the signature even on
        // the host where it was generated.
        $tampered = str_replace('/somealias?', '/somealias-tampered?', $signedRelative);
        $this->assertFalse(
            $build('platform.test', $tampered)
                ->hasValidSignatureWhileIgnoring(['_draft', '_t'], false),
            'Tampered URL must not validate.'
        );
    }
}
