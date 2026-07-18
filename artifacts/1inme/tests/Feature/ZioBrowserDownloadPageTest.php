<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the public /download page (SayZio Browser installers).
 *
 * The controller resolves installer URLs live from the GitHub Releases API
 * (cached 6h) and falls back to pinned v0.1.0 URLs on any failure. These
 * tests fake the GitHub HTTP call so the page can never silently blank its
 * download buttons after a GitHub API change or outage.
 */
class ZioBrowserDownloadPageTest extends TestCase
{
    use RefreshDatabase;

    private const RELEASES_URL = 'https://api.github.com/repos/sanasandeep/sayzio/releases*';
    private const CACHE_KEY = 'zio_browser_release_v1';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(self::CACHE_KEY);
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

    public function test_renders_installer_links_from_live_github_release(): void
    {
        Http::fake([
            self::RELEASES_URL => Http::response([
                $this->githubRelease('zio-browser-v9.9.9', [
                    $this->asset('SayZio.Browser-9.9.9-arm64.dmg'),
                    $this->asset('SayZio.Browser-9.9.9.dmg'),
                    $this->asset('SayZio.Browser.Setup.9.9.9.exe'),
                    $this->asset('SayZio.Browser-9.9.9-arm64-mac.zip'),
                    $this->asset('SayZio.Browser-9.9.9-mac.zip'),
                ]),
            ]),
        ]);

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

    public function test_api_failure_renders_pinned_fallback_urls(): void
    {
        Http::fake([
            self::RELEASES_URL => Http::response('upstream broke', 502),
        ]);

        $response = $this->get('/download');

        $response->assertOk();
        $response->assertSee('v0.1.0');
        $fallback = 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.1.0/';
        $response->assertSee($fallback . 'SayZio.Browser-0.1.0-arm64.dmg');
        $response->assertSee($fallback . 'SayZio.Browser-0.1.0.dmg');
        $response->assertSee($fallback . 'SayZio.Browser.Setup.0.1.0.exe');

        // A failed fetch must NOT be cached for 6h — the next request retries.
        $this->assertFalse(Cache::has(self::CACHE_KEY));
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

        $response = $this->get('/download');
        $response->assertOk();

        $release = $response->viewData('release');
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

        $response = $this->get('/download');
        $response->assertOk();
        $this->assertSame('2.8.0', $response->viewData('release')['version']);
    }

    public function test_release_missing_headline_installers_falls_back(): void
    {
        // A matching release without all three headline installers must not
        // be trusted — the page falls back instead of rendering gaps.
        Http::fake([
            self::RELEASES_URL => Http::response([
                $this->githubRelease('zio-browser-v4.0.0', [
                    $this->asset('SayZio.Browser-4.0.0-arm64.dmg'),
                    // no x64 dmg, no exe
                ]),
            ]),
        ]);

        $response = $this->get('/download');
        $response->assertOk();
        $this->assertSame('0.1.0', $response->viewData('release')['version']);
    }
}
