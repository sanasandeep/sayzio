<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Public /download page for the SayZio Browser desktop app.
 *
 * Installer links are resolved from the LATEST published GitHub release
 * whose tag matches the browser tag prefix, so the page never goes stale
 * when a new version ships. The GitHub API response is cached (the repo is
 * public — unauthenticated calls are rate-limited to 60/hr/IP) and any
 * fetch failure falls back to the last-known pinned release URLs so the
 * buttons always work.
 */
class ZioBrowserDownloadController extends Controller
{
    private const REPO = 'sanasandeep/sayzio';
    private const TAG_PREFIX = 'zio-browser-v';
    private const CACHE_KEY = 'zio_browser_release_v1';
    private const CACHE_TTL = 21600; // 6h — releases are infrequent.

    /**
     * app_settings key persisting the last SUCCESSFULLY fetched release, so
     * the fallback self-updates as releases ship. Survives cache clears and
     * restarts; superseded only by a fresher successful fetch.
     */
    private const LAST_RELEASE_SETTING = 'zio_browser_last_release';

    /**
     * Last-resort bootstrap fallback (v0.1.0) used only when the GitHub API
     * is unreachable, nothing is cached, AND no release has ever been
     * persisted to app_settings. The persisted last-good release (see
     * self::LAST_RELEASE_SETTING) supersedes it once any fetch succeeds.
     */
    private const FALLBACK = [
        'version' => '0.1.0',
        'mac_arm64_dmg' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.1.0/SayZio.Browser-0.1.0-arm64.dmg',
        'mac_x64_dmg' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.1.0/SayZio.Browser-0.1.0.dmg',
        'windows_exe' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.1.0/SayZio.Browser.Setup.0.1.0.exe',
        'mac_arm64_zip' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.1.0/SayZio.Browser-0.1.0-arm64-mac.zip',
        'mac_x64_zip' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.1.0/SayZio.Browser-0.1.0-mac.zip',
        'published_at' => null,
    ];

    public function show()
    {
        try {
            $release = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                $fetched = self::fetchLatestRelease();
                if ($fetched === null) {
                    // Don't cache a failure for 6h — throw so remember()
                    // skips the write and we retry on the next request.
                    throw new \RuntimeException('zio-browser release fetch failed');
                }

                return $fetched;
            });
        } catch (\Throwable $e) {
            $release = self::lastGoodRelease() ?? self::FALLBACK;
        }

        return view('public.download', ['release' => $release, 'seoKey' => 'download']);
    }

    /**
     * @return array<string,mixed>|null plain array (file-cache safe), null on failure
     */
    private static function fetchLatestRelease(): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get('https://api.github.com/repos/' . self::REPO . '/releases', ['per_page' => 15]);
        } catch (\Throwable $e) {
            return null;
        }

        if (!$response->ok() || !is_array($response->json())) {
            return null;
        }

        foreach ($response->json() as $rel) {
            if (!is_array($rel) || ($rel['draft'] ?? true) || ($rel['prerelease'] ?? false)) {
                continue;
            }
            $tag = (string) ($rel['tag_name'] ?? '');
            if (!str_starts_with($tag, self::TAG_PREFIX)) {
                continue;
            }

            $out = [
                'version' => substr($tag, strlen(self::TAG_PREFIX)),
                'mac_arm64_dmg' => null,
                'mac_x64_dmg' => null,
                'windows_exe' => null,
                'mac_arm64_zip' => null,
                'mac_x64_zip' => null,
                'published_at' => $rel['published_at'] ?? null,
            ];

            foreach ((array) ($rel['assets'] ?? []) as $asset) {
                if (!is_array($asset)) {
                    continue;
                }
                $name = strtolower((string) ($asset['name'] ?? ''));
                $url = (string) ($asset['browser_download_url'] ?? '');
                if ($url === '') {
                    continue;
                }
                if (str_ends_with($name, '.dmg')) {
                    $key = str_contains($name, 'arm64') ? 'mac_arm64_dmg' : 'mac_x64_dmg';
                    $out[$key] = $url;
                } elseif (str_ends_with($name, '.exe')) {
                    $out['windows_exe'] = $url;
                } elseif (str_ends_with($name, '.zip') && str_contains($name, 'mac')) {
                    $key = str_contains($name, 'arm64') ? 'mac_arm64_zip' : 'mac_x64_zip';
                    $out[$key] = $url;
                }
            }

            // Require the three headline installers before trusting the release.
            if ($out['mac_arm64_dmg'] && $out['mac_x64_dmg'] && $out['windows_exe']) {
                self::persistLastGoodRelease($out);

                return $out;
            }
        }

        return null;
    }

    /**
     * Durably persist the last successfully fetched release so the outage
     * fallback self-updates as releases ship. Best-effort: a DB hiccup must
     * never break the live fetch path that just succeeded.
     *
     * @param array<string,mixed> $release
     */
    private static function persistLastGoodRelease(array $release): void
    {
        try {
            $current = AppSetting::get(self::LAST_RELEASE_SETTING);
            if (is_array($current) && ($current['version'] ?? null) === ($release['version'] ?? null)) {
                return; // Unchanged — skip the write.
            }
            AppSetting::put(self::LAST_RELEASE_SETTING, $release);
        } catch (\Throwable $e) {
            // Best-effort only.
        }
    }

    /**
     * Read the persisted last-good release, validating shape so a corrupt or
     * partial value can never render a broken download page.
     *
     * @return array<string,mixed>|null
     */
    private static function lastGoodRelease(): ?array
    {
        try {
            $stored = AppSetting::get(self::LAST_RELEASE_SETTING);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($stored)) {
            return null;
        }

        foreach (['mac_arm64_dmg', 'mac_x64_dmg', 'windows_exe'] as $key) {
            if (!is_string($stored[$key] ?? null) || $stored[$key] === '') {
                return null;
            }
        }
        if (!is_string($stored['version'] ?? null) || $stored['version'] === '') {
            return null;
        }

        // Keep the view contract stable even if optional keys are absent.
        return $stored + [
            'mac_arm64_zip' => null,
            'mac_x64_zip' => null,
            'published_at' => null,
        ];
    }
}
