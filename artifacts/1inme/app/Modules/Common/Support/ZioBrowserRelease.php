<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves SayZio Browser installer links from the GitHub Releases API.
 *
 * Stale-while-revalidate: the last-known release is cached FOREVER and the
 * /download page only ever reads that cache (or the pinned fallback) — it
 * never performs a live GitHub call, so visitors never wait on the 8s API
 * timeout. Freshness comes from the scheduled `zio-browser:refresh-release`
 * job (routes/schedules/syncing-integrations.php); a refresh failure logs a
 * warning and simply keeps serving the previous cached release.
 */
class ZioBrowserRelease
{
    public const REPO = 'sanasandeep/sayzio';
    public const TAG_PREFIX = 'zio-browser-v';
    public const CACHE_KEY = 'zio_browser_release_v1';

    /**
     * Throttle for the opportunistic after-response refresh triggered by a
     * cache-miss page view (fresh deploys / flushed cache), so a burst of
     * visitors can't stampede the GitHub API before the scheduler ticks.
     */
    public const REFRESH_LOCK_KEY = 'zio_browser_release_refresh_lock_v1';
    public const REFRESH_LOCK_TTL = 600; // 10 min

    /**
     * app_settings key persisting the last SUCCESSFULLY fetched release, so
     * the fallback self-updates as releases ship. Survives cache clears and
     * restarts; superseded only by a fresher successful fetch.
     */
    public const LAST_RELEASE_SETTING = 'zio_browser_last_release';

    /**
     * app_settings key tracking refresh health so the scheduled
     * `zio-browser:check-freshness` watchdog can alert admins when refreshes
     * have been failing continuously beyond its staleness threshold:
     *   - last_success_at — ISO-8601 of the last successful refresh
     *   - failing_since   — ISO-8601 of the first failure after the last
     *                       success; cleared on the next success
     *   - last_failure_at — ISO-8601 of the most recent failed refresh
     *   - last_error      — short reason string from the most recent failure
     * The watchdog also stores its alert-episode state under this key
     * (see CheckZioBrowserReleaseFreshness).
     */
    public const HEALTH_KEY = 'zio_browser_refresh_health';

    /**
     * Last-resort bootstrap fallback (v0.1.0) used only when nothing is
     * cached AND no release has ever been persisted to app_settings. The
     * persisted last-good release (see self::LAST_RELEASE_SETTING)
     * supersedes it once any fetch succeeds.
     */
    public const FALLBACK = [
        'version' => '0.1.0',
        'mac_arm64_dmg' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.1.0/SayZio.Browser-0.1.0-arm64.dmg',
        'mac_x64_dmg' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.1.0/SayZio.Browser-0.1.0.dmg',
        'windows_exe' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.1.0/SayZio.Browser.Setup.0.1.0.exe',
        'mac_arm64_zip' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.1.0/SayZio.Browser-0.1.0-arm64-mac.zip',
        'mac_x64_zip' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.1.0/SayZio.Browser-0.1.0-mac.zip',
        'published_at' => null,
    ];

    /**
     * Instant, cache-only read for the public page. Never hits the network.
     *
     * @return array<string,mixed>
     */
    public static function current(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        return self::lastGoodRelease() ?? self::FALLBACK;
    }

    /**
     * Fetch the latest release and cache it forever (superseded by the next
     * successful refresh). On failure the previous cached value is kept.
     */
    public static function refresh(): bool
    {
        $fetched = self::fetchLatestRelease();
        if ($fetched === null) {
            Log::warning('zio-browser release refresh failed; keeping last cached release', [
                'has_cached' => Cache::has(self::CACHE_KEY),
            ]);
            self::recordRefreshFailure();

            return false;
        }

        Cache::forever(self::CACHE_KEY, $fetched);
        self::recordRefreshSuccess();

        return true;
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
     * Stamp a successful refresh into the health state and end any open
     * failure streak. Best-effort: health bookkeeping must never break the
     * refresh path itself.
     */
    private static function recordRefreshSuccess(): void
    {
        try {
            $state = AppSetting::get(self::HEALTH_KEY, []);
            $state = is_array($state) ? $state : [];
            $state['last_success_at'] = now()->toIso8601String();
            unset($state['failing_since'], $state['last_error']);
            AppSetting::put(self::HEALTH_KEY, $state);
        } catch (\Throwable $e) {
            // Best-effort only.
        }
    }

    /**
     * Stamp a failed refresh into the health state, opening a failure streak
     * (failing_since) if one is not already running. Best-effort.
     */
    private static function recordRefreshFailure(): void
    {
        try {
            $state = AppSetting::get(self::HEALTH_KEY, []);
            $state = is_array($state) ? $state : [];
            $state['last_failure_at'] = now()->toIso8601String();
            $state['last_error'] = 'GitHub release fetch failed or returned no usable zio-browser release';
            if (empty($state['failing_since'])) {
                $state['failing_since'] = now()->toIso8601String();
            }
            AppSetting::put(self::HEALTH_KEY, $state);
        } catch (\Throwable $e) {
            // Best-effort only.
        }
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
