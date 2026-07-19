<?php

namespace Tests\Feature;

use App\Modules\Common\Support\ZioBrowserRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the public /download page (SayZio Browser installers).
 *
 * Stale-while-revalidate: the page ONLY reads the cached release (never
 * calls GitHub inline). Freshness comes from the scheduled
 * `zio-browser:refresh-release` command; a cache-miss page view triggers an
 * after-response refresh so the next visitor sees live links. These tests
 * fake the GitHub HTTP call so the page can never silently blank its
 * download buttons after a GitHub API change or outage.
 */
class ZioBrowserDownloadPageTest extends TestCase
{
    use RefreshDatabase;

    private const RELEASES_URL = 'https://api.github.com/repos/sanasandeep/sayzio/releases*';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(ZioBrowserRelease::CACHE_KEY);
        Cache::forget(ZioBrowserRelease::REFRESH_LOCK_KEY);
    }

    /** @param array<int,array<string,mixed>> $assets */
    private function githubRelease(string $tag, array $assets, bool $draft = false, bool $prerelease = false): array
    {
        return [
            'tag_name' => $tag,
            'draft' => $draft,
            'prerelease' => $prerelease,
            'published_at' => '2026-07-01T00:00:00Z',
            'assets' => $assets,
        ];
    }

    private function asset(string $name): array
    {
        return [
            'name' => $name,
            'browser_download_url' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v9.9.9/' . $name,
        ];
    }

    private function fakeFullRelease(string $version = '9.9.9'): void
    {
        Http::fake([
            self::RELEASES_URL => Http::response([
                $this->githubRelease('zio-browser-v' . $version, [
                    $this->asset("SayZio.Browser-{$version}-arm64.dmg"),
                    $this->asset("SayZio.Browser-{$version}.dmg"),
                    $this->asset("SayZio.Browser.Setup.{$version}.exe"),
                    $this->asset("SayZio.Browser-{$version}-arm64-mac.zip"),
                    $this->asset("SayZio.Browser-{$version}-mac.zip"),
                ]),
            ]),
        ]);
    }

    public function test_page_renders_cached_release_without_any_github_call(): void
    {
        // Seed the cache the way the scheduled job does.
        $this->fakeFullRelease();
        $this->assertTrue(ZioBrowserRelease::refresh());

        // A fresh fake with no allowed responses: any HTTP call would throw.
        Http::fake(function (): void {
            $this->fail('The /download page must not perform any live HTTP call.');
        });

        $response = $this->get('/download');

        $response->assertOk();
        $response->assertSee('v9.9.9');
        $base = 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v9.9.9/';
        $response->assertSee($base . 'SayZio.Browser-9.9.9-arm64.dmg');
        $response->assertSee($base . 'SayZio.Browser-9.9.9.dmg');
        $response->assertSee($base . 'SayZio.Browser.Setup.9.9.9.exe');
        // Portable mac zips are exposed as alternate links.
        $response->assertSee($base . 'SayZio.Browser-9.9.9-arm64-mac.zip');
        $response->assertSee($base . 'SayZio.Browser-9.9.9-mac.zip');
    }

    public function test_cache_miss_renders_fallback_instantly_and_self_heals_after_response(): void
    {
        $this->fakeFullRelease();

        // No cache yet: the visitor gets the pinned fallback immediately …
        $response = $this->get('/download');
        $response->assertOk();
        $response->assertSee('v0.1.0');
        $fallback = 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.1.0/';
        $response->assertSee($fallback . 'SayZio.Browser-0.1.0-arm64.dmg');
        $response->assertSee($fallback . 'SayZio.Browser-0.1.0.dmg');
        $response->assertSee($fallback . 'SayZio.Browser.Setup.0.1.0.exe');

        // … and the after-response refresh has populated the cache, so the
        // NEXT visitor sees the live release.
        $this->assertTrue(Cache::has(ZioBrowserRelease::CACHE_KEY));
        $next = $this->get('/download');
        $next->assertOk();
        $next->assertSee('v9.9.9');
    }

    public function test_api_failure_renders_pinned_fallback_and_caches_nothing(): void
    {
        Http::fake([
            self::RELEASES_URL => Http::response('upstream broke', 502),
        ]);

        $response = $this->get('/download');

        $response->assertOk();
        $response->assertSee('v0.1.0');

        // A failed refresh must never poison the cache.
        $this->assertFalse(Cache::has(ZioBrowserRelease::CACHE_KEY));
    }

    public function test_connection_exception_renders_pinned_fallback_urls(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        });

        $response = $this->get('/download');

        $response->assertOk();
        $response->assertSee('v0.1.0');
        $response->assertSee('zio-browser-v0.1.0/SayZio.Browser.Setup.0.1.0.exe');
    }

    public function test_cache_miss_refresh_is_throttled_by_lock(): void
    {
        // Lock held (a refresh recently ran/failed): the page must not
        // trigger another fetch, only serve the fallback.
        Cache::add(ZioBrowserRelease::REFRESH_LOCK_KEY, 1, ZioBrowserRelease::REFRESH_LOCK_TTL);
        Http::fake(function (): void {
            $this->fail('Throttled cache-miss view must not call GitHub.');
        });

        $response = $this->get('/download');
        $response->assertOk();
        $response->assertSee('v0.1.0');
        $this->assertFalse(Cache::has(ZioBrowserRelease::CACHE_KEY));
    }

    public function test_cache_miss_serves_persisted_last_good_release_over_pinned_fallback(): void
    {
        // A previously successful fetch persisted the release durably; with
        // a cold cache the page must serve it instead of the stale pinned
        // v0.1.0 bootstrap fallback.
        \App\Modules\Admin\Models\AppSetting::put(
            ZioBrowserRelease::LAST_RELEASE_SETTING,
            array_merge(ZioBrowserRelease::FALLBACK, ['version' => '7.7.7'])
        );
        Cache::add(ZioBrowserRelease::REFRESH_LOCK_KEY, 1, ZioBrowserRelease::REFRESH_LOCK_TTL);
        Http::fake(function (): void {
            $this->fail('Cache-miss view with lock held must not call GitHub.');
        });

        $response = $this->get('/download');
        $response->assertOk();
        $response->assertSee('v7.7.7');
    }

    public function test_successful_refresh_persists_last_good_release(): void
    {
        $this->fakeFullRelease('6.0.0');
        $this->assertTrue(ZioBrowserRelease::refresh());

        $stored = \App\Modules\Admin\Models\AppSetting::get(ZioBrowserRelease::LAST_RELEASE_SETTING);
        $this->assertIsArray($stored);
        $this->assertSame('6.0.0', $stored['version']);
    }

    public function test_refresh_command_populates_cache_and_reports_success(): void
    {
        $this->fakeFullRelease('5.0.0');

        $this->assertSame(0, Artisan::call('zio-browser:refresh-release'));
        $this->assertSame('5.0.0', ZioBrowserRelease::current()['version']);
    }

    public function test_refresh_command_failure_keeps_previous_cached_release(): void
    {
        // Seed the cache directly (Http::fake stubs stack, so re-faking the
        // same URL with a failure would not override an earlier success).
        Cache::forever(ZioBrowserRelease::CACHE_KEY, array_merge(ZioBrowserRelease::FALLBACK, ['version' => '5.0.0']));

        Http::fake([
            self::RELEASES_URL => Http::response('upstream broke', 502),
        ]);

        $this->assertSame(1, Artisan::call('zio-browser:refresh-release'));
        // Stale-while-revalidate: the old release keeps serving.
        $this->assertSame('5.0.0', ZioBrowserRelease::current()['version']);
    }

    public function test_asset_name_platform_mapping(): void
    {
        // Shuffled asset order + noise files: mapping must key off name shape,
        // not position. arm64 dmg/zip → Apple Silicon slots, plain → Intel.
        Http::fake([
            self::RELEASES_URL => Http::response([
                $this->githubRelease('zio-browser-v2.0.0', [
                    $this->asset('latest-mac.yml'),
                    $this->asset('SayZio.Browser.Setup.2.0.0.exe'),
                    $this->asset('SayZio.Browser-2.0.0-mac.zip'),
                    $this->asset('SayZio.Browser-2.0.0-arm64.dmg'),
                    $this->asset('SayZio.Browser-2.0.0.dmg.blockmap'),
                    $this->asset('SayZio.Browser-2.0.0.dmg'),
                    $this->asset('SayZio.Browser-2.0.0-arm64-mac.zip'),
                    $this->asset('source.zip'), // zip without "mac" — ignored
                ]),
            ]),
        ]);

        $this->assertTrue(ZioBrowserRelease::refresh());
        $release = ZioBrowserRelease::current();

        $base = 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v9.9.9/';
        $this->assertSame('2.0.0', $release['version']);
        $this->assertSame($base . 'SayZio.Browser-2.0.0-arm64.dmg', $release['mac_arm64_dmg']);
        $this->assertSame($base . 'SayZio.Browser-2.0.0.dmg', $release['mac_x64_dmg']);
        $this->assertSame($base . 'SayZio.Browser.Setup.2.0.0.exe', $release['windows_exe']);
        $this->assertSame($base . 'SayZio.Browser-2.0.0-arm64-mac.zip', $release['mac_arm64_zip']);
        $this->assertSame($base . 'SayZio.Browser-2.0.0-mac.zip', $release['mac_x64_zip']);
    }

    public function test_skips_drafts_prereleases_and_foreign_tags(): void
    {
        Http::fake([
            self::RELEASES_URL => Http::response([
                $this->githubRelease('zio-browser-v3.0.0', [
                    $this->asset('SayZio.Browser-3.0.0-arm64.dmg'),
                    $this->asset('SayZio.Browser-3.0.0.dmg'),
                    $this->asset('SayZio.Browser.Setup.3.0.0.exe'),
                ], draft: true),
                $this->githubRelease('zio-browser-v2.9.0', [
                    $this->asset('SayZio.Browser-2.9.0-arm64.dmg'),
                    $this->asset('SayZio.Browser-2.9.0.dmg'),
                    $this->asset('SayZio.Browser.Setup.2.9.0.exe'),
                ], prerelease: true),
                $this->githubRelease('mobile-v1.0.0', [
                    $this->asset('app.apk'),
                ]),
                $this->githubRelease('zio-browser-v2.8.0', [
                    $this->asset('SayZio.Browser-2.8.0-arm64.dmg'),
                    $this->asset('SayZio.Browser-2.8.0.dmg'),
                    $this->asset('SayZio.Browser.Setup.2.8.0.exe'),
                ]),
            ]),
        ]);

        $this->assertTrue(ZioBrowserRelease::refresh());
        $this->assertSame('2.8.0', ZioBrowserRelease::current()['version']);
    }

    public function test_release_missing_headline_installers_is_not_trusted(): void
    {
        // A matching release without all three headline installers must not
        // be cached — the page keeps its fallback instead of rendering gaps.
        Http::fake([
            self::RELEASES_URL => Http::response([
                $this->githubRelease('zio-browser-v4.0.0', [
                    $this->asset('SayZio.Browser-4.0.0-arm64.dmg'),
                    // no x64 dmg, no exe
                ]),
            ]),
        ]);

        $this->assertFalse(ZioBrowserRelease::refresh());
        $this->assertFalse(Cache::has(ZioBrowserRelease::CACHE_KEY));
        $this->assertSame('0.1.0', ZioBrowserRelease::current()['version']);
    }

    public function test_missing_installer_failure_stores_specific_release_tag_in_health_state(): void
    {
        // When the newest matching release exists but is missing headline
        // installers, the health state must name the specific release tag so
        // ops know which release to re-upload assets for — not just a generic
        // "fetch failed" message.
        Http::fake([
            self::RELEASES_URL => Http::response([
                $this->githubRelease('zio-browser-v4.1.0', [
                    $this->asset('SayZio.Browser-4.1.0-arm64.dmg'),
                    // mac_x64_dmg and windows_exe intentionally absent
                ]),
            ]),
        ]);

        $this->assertFalse(ZioBrowserRelease::refresh());

        $error = ZioBrowserRelease::lastRefreshError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('zio-browser-v4.1.0', $error,
            'Error must name the specific release tag so ops know which release is broken');
        $this->assertStringContainsString('skipped', $error);

        $state = \App\Modules\Admin\Models\AppSetting::get(ZioBrowserRelease::HEALTH_KEY, []);
        $this->assertIsArray($state);
        $this->assertStringContainsString('zio-browser-v4.1.0', $state['last_error'] ?? '',
            'Health state last_error must also name the specific release tag');
    }

    public function test_http_error_failure_stores_status_code_in_health_state(): void
    {
        Http::fake([
            self::RELEASES_URL => Http::response('rate limited', 429),
        ]);

        $this->assertFalse(ZioBrowserRelease::refresh());

        $error = ZioBrowserRelease::lastRefreshError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('429', $error,
            'Error must include the HTTP status code for quick triage');
        $this->assertStringContainsString('rate limit', strtolower($error),
            'Rate-limit errors (429) must hint at setting GITHUB_TOKEN');
    }

    public function test_no_matching_release_tag_stores_diagnostic_error(): void
    {
        // All releases have non-zio-browser tags: refresh must fail with a
        // specific message naming the expected tag prefix.
        Http::fake([
            self::RELEASES_URL => Http::response([
                $this->githubRelease('mobile-v1.0.0', []),
                $this->githubRelease('sayzio-v2.0.0', []),
            ]),
        ]);

        $this->assertFalse(ZioBrowserRelease::refresh());

        $error = ZioBrowserRelease::lastRefreshError();
        $this->assertNotNull($error);
        $this->assertStringContainsString(ZioBrowserRelease::TAG_PREFIX, $error,
            'Error must name the expected tag prefix so ops know what the API found vs. expected');
    }

    public function test_github_token_is_sent_in_authorization_header_when_configured(): void
    {
        // When config('services.github.token') is set, the request must carry
        // an Authorization header to benefit from the higher rate limit.
        config(['services.github.token' => 'ghp_test_token_abc123']);

        $captured = null;
        Http::fake([
            self::RELEASES_URL => function (\Illuminate\Http\Client\Request $request) use (&$captured) {
                $captured = $request->header('Authorization');
                return Http::response([]);
            },
        ]);

        ZioBrowserRelease::refresh();

        $this->assertNotEmpty($captured, 'Authorization header must be sent when a GitHub token is configured');
        $this->assertStringContainsString('ghp_test_token_abc123', implode('', (array) $captured));

        config(['services.github.token' => null]);
    }

    public function test_github_token_is_not_sent_when_unconfigured(): void
    {
        config(['services.github.token' => null]);

        $hasAuth = false;
        Http::fake([
            self::RELEASES_URL => function (\Illuminate\Http\Client\Request $request) use (&$hasAuth) {
                $hasAuth = $request->hasHeader('Authorization');
                return Http::response([]);
            },
        ]);

        ZioBrowserRelease::refresh();

        $this->assertFalse($hasAuth, 'Authorization header must NOT be sent when no GitHub token is configured');
    }

    public function test_refresh_command_outputs_specific_error_on_failure(): void
    {
        // The command must output the specific failure reason (not just a
        // generic "fetch failed") so the run-row captures diagnosable text.
        Http::fake([
            self::RELEASES_URL => Http::response('rate limited', 429),
        ]);

        Artisan::call('zio-browser:refresh-release');
        $output = Artisan::output();

        $this->assertStringContainsString('429', $output,
            'Command output must include the HTTP status for rate-limit errors');
    }
}
