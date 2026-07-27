<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\Release;
use App\Modules\Common\Support\ZioBrowserRelease;
use App\Services\Integrations\SystemUpdateService;
use Illuminate\Support\Facades\Log;

/**
 * Assembles the per-surface version picture for the admin "Versions &
 * Releases" hub.
 *
 * Data sources:
 *   - web       → SystemUpdateService commit data (local vs remote SHA)
 *   - zio_browser → committed snapshot (declared) vs cached GitHub release
 *                   feed (latest); new feed versions are auto-inserted into
 *                   the releases table (source = github)
 *   - marketing / mobile / dialer / extension / api_server → versions baked
 *     into version-snapshot.json at merge/CI time (generate:version-snapshot)
 *   - docs      → newest docs/*.md timestamp from the same snapshot
 *   - latest for snapshot-driven surfaces = newest releases-table entry
 *
 * Every lookup degrades gracefully to status "unknown" — this page must
 * never 500 because a snapshot is missing or a feed has never been fetched.
 */
class VersionRegistry
{
    public const GUARD_STATUS_KEY = 'sync_guard_status';

    /** Guard keys → labels shown in the Sync Status panel. */
    public const GUARDS = [
        'dialer_sync'      => 'Dialer standalone sync',
        'docs_parity'      => 'Mobile ⇄ docs parity',
        'doc_constants'    => 'Doc constants drift',
        'api_server_paths' => 'API server paths',
    ];

    /**
     * @return array<string,mixed>|null the committed version snapshot, or null
     */
    public static function snapshot(): ?array
    {
        $path = base_path('version-snapshot.json');
        if (!is_file($path)) {
            return null;
        }
        try {
            $data = json_decode((string) file_get_contents($path), true);

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * One row per surface:
     * [key, label, current, latest, status(up_to_date|update_available|unknown),
     *  last_release_at, detail, releases(Collection<Release>)]
     *
     * @return array<int,array<string,mixed>>
     */
    public static function surfaces(): array
    {
        self::syncZioBrowserReleases();

        $snapshot = self::snapshot();
        $declared = is_array($snapshot['surfaces'] ?? null) ? $snapshot['surfaces'] : [];

        $releasesBySurface = Release::query()
            ->orderByRaw('released_at DESC NULLS LAST')
            ->orderByDesc('id')
            ->get()
            ->groupBy('surface');

        $rows = [];
        foreach (Release::SURFACES as $key => $label) {
            $releases = $releasesBySurface->get($key, collect());
            $latestRelease = $releases->first();

            $row = [
                'key'             => $key,
                'label'           => $label,
                'current'         => null,
                'latest'          => null,
                'status'          => 'unknown',
                'last_release_at' => $latestRelease?->released_at?->toDateString(),
                'detail'          => null,
                'releases'        => $releases,
            ];

            if ($key === 'web') {
                $rows[] = self::webRow($row);
                continue;
            }

            if ($key === 'zio_browser') {
                $rows[] = self::zioBrowserRow($row, $declared['zio_browser'] ?? null);
                continue;
            }

            if ($key === 'docs') {
                $docsAt = $snapshot['docs_updated_at'] ?? null;
                if (is_string($docsAt) && $docsAt !== '') {
                    $row['current'] = 'Updated ' . substr($docsAt, 0, 10);
                    $row['status']  = 'up_to_date';
                    $row['detail']  = 'Docs ship with every deploy; timestamp reflects the newest docs file at snapshot time.';
                } else {
                    $row['detail'] = 'No version snapshot found — run generate:version-snapshot.';
                }
                $rows[] = $row;
                continue;
            }

            // Snapshot-declared surfaces: mobile, dialer, extension, api_server, marketing
            $current = $declared[$key] ?? null;
            $latest  = $latestRelease?->version;
            $row['current'] = is_string($current) && $current !== '' ? $current : null;
            $row['latest']  = $latest;

            if ($row['current'] === null) {
                $row['detail'] = 'Declared version not found in the committed snapshot.';
            } elseif ($latest === null) {
                // No changelog entries yet — treat the declared version as current.
                $row['status'] = 'up_to_date';
                $row['detail'] = 'No changelog entries recorded yet.';
            } else {
                $row['status'] = version_compare($row['current'], $latest, '>=')
                    ? 'up_to_date'
                    : 'update_available';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Web surface: deployed commit vs GitHub main via SystemUpdateService.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function webRow(array $row): array
    {
        try {
            if (!SystemUpdateService::isConfigured()) {
                $row['detail'] = SystemUpdateService::isReplit()
                    ? 'Managed by Replit — deploys via Publish; commit check needs GitHub credentials.'
                    : 'GitHub credentials not configured — commit check unavailable.';

                return $row;
            }

            $status = SystemUpdateService::cachedStatus();
            $local  = $status['local_sha']  ?? null;
            $remote = $status['remote_sha'] ?? null;

            $row['current'] = $local ? substr($local, 0, 7) : null;
            $row['latest']  = $remote ? substr($remote, 0, 7) : null;

            if (!empty($status['error'])) {
                $row['detail'] = 'Update check error: ' . $status['error'];
            } elseif ($local && $remote) {
                $row['status'] = !empty($status['available']) ? 'update_available' : 'up_to_date';
                if (!empty($status['commits_behind'])) {
                    $row['detail'] = $status['commits_behind'] . ' commit(s) behind GitHub main.';
                }
            }
        } catch (\Throwable $e) {
            $row['detail'] = 'Update check failed: ' . $e->getMessage();
        }

        return $row;
    }

    /**
     * Zio Browser: declared (built) version vs latest published GitHub release
     * from the always-cached feed. Never hits the network here.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function zioBrowserRow(array $row, ?string $declared): array
    {
        $row['current'] = is_string($declared) && $declared !== '' ? $declared : null;

        try {
            $feed = ZioBrowserRelease::current();
            $latest = is_string($feed['version'] ?? null) ? $feed['version'] : null;
            $row['latest'] = $latest;

            if ($row['current'] === null) {
                $row['detail'] = 'Declared version not found in the committed snapshot.';
            } elseif ($latest !== null) {
                $row['status'] = version_compare($row['current'], $latest, '>=')
                    ? 'up_to_date'
                    : 'update_available';
            }
        } catch (\Throwable $e) {
            $row['detail'] = 'Release feed unavailable: ' . $e->getMessage();
        }

        return $row;
    }

    /**
     * Auto-insert the cached GitHub release for Zio Browser into the releases
     * table so its changelog prefills without manual entry. Best-effort and
     * idempotent (unique surface+version); a DB hiccup never breaks the page.
     */
    public static function syncZioBrowserReleases(): void
    {
        try {
            $feed = ZioBrowserRelease::current();
            $version = is_string($feed['version'] ?? null) ? $feed['version'] : null;
            if ($version === null || $version === '') {
                return;
            }

            $publishedAt = null;
            if (is_string($feed['published_at'] ?? null) && $feed['published_at'] !== '') {
                try {
                    $publishedAt = \Illuminate\Support\Carbon::parse($feed['published_at'])->toDateString();
                } catch (\Throwable $e) {
                    // leave null
                }
            }

            Release::firstOrCreate(
                ['surface' => 'zio_browser', 'version' => $version],
                [
                    'released_at' => $publishedAt,
                    'notes'       => 'Published on GitHub (tag ' . ZioBrowserRelease::TAG_PREFIX . $version . ').',
                    'source'      => 'github',
                ]
            );
        } catch (\Throwable $e) {
            Log::debug('VersionRegistry: zio-browser release sync skipped', ['reason' => $e->getMessage()]);
        }
    }
}
