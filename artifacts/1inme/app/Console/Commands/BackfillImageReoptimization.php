<?php

namespace App\Console\Commands;

use App\Modules\User\Models\Form;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SplashPage;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lazily shrink legacy raster UserFile rows that were uploaded before the
 * upload-time `compress_image` pipeline existed for biolink backgrounds /
 * fallbacks / slideshow slides / OG images, splash OG images, form cover /
 * card images, and link SEO images.
 *
 * Walks the persisted references in each owning model, extracts the
 * UserFile id from the stored `/f/{id}/{filename}` URL, and calls
 * `UserFile::reoptimizeImageInPlace()` with the same per-context max
 * dimensions used at upload time today. Writes only happen when the
 * recompressed bytes are actually smaller (helper handles the check),
 * so re-running is a no-op once the vault is clean.
 *
 * Logos, favicons, file share, OCR scans, and arbitrary visitor uploads
 * are intentionally NOT touched — those are stored as-is at upload time
 * because they're vector / pixel-perfect / forensic artwork.
 *
 * Owner rows are walked in id-ordered chunks to stay safe on big tables;
 * each UserFile id is processed at most once per run even when multiple
 * owners reference the same file.
 */
class BackfillImageReoptimization extends Command
{
    protected $signature = 'images:backfill-reoptimize
        {--chunk=200 : Owner rows scanned per batch}
        {--limit=0 : Cap on UserFile rows processed (0 = no cap)}
        {--dry-run : Report what would shrink without writing}
        {--only= : Comma-separated subset of contexts to run (biolink_bg,biolink_fallback,biolink_slide,biolink_og,splash_og,form_cover,form_card,link_seo)}';

    protected $description = 'Backfill server-side downscale + re-encode for legacy raster vault images referenced by biolinks, splash pages, forms, and link SEO/share images.';

    /** Per-context dimensions kept in sync with the upload-time settings. */
    private const CONTEXTS = [
        'biolink_bg'       => [1920, 1920, 85],
        'biolink_fallback' => [1920, 1920, 85],
        'biolink_slide'    => [1600, 1600, 85],
        'biolink_og'       => [1200, 1200, 85],
        'splash_og'        => [1200, 1200, 85],
        'form_cover'       => [1600, 1600, 85],
        'form_card'        => [1200, 1200, 85],
        'link_seo'         => [1200, 1200, 85],
    ];

    public function handle(): int
    {
        $chunk = max(25, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));
        $dry   = (bool) $this->option('dry-run');

        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $enabled = $only
            ? array_values(array_intersect(array_keys(self::CONTEXTS), $only))
            : array_keys(self::CONTEXTS);

        // Process tightest bounds first so a UserFile shared across multiple
        // contexts (e.g. background + OG image pointing at the same vault row)
        // gets shrunk to the strictest size — the per-run dedupe below means
        // whichever context runs first wins.
        usort($enabled, function (string $a, string $b) {
            [$aw, $ah] = self::CONTEXTS[$a];
            [$bw, $bh] = self::CONTEXTS[$b];
            return ($aw * $ah) <=> ($bw * $bh);
        });

        if (!$enabled) {
            $this->error('No valid contexts selected via --only.');
            return self::FAILURE;
        }

        $stats = [
            'scanned'    => 0,
            'shrunk'     => 0,
            'skipped'    => 0,
            'bytes_freed' => 0,
            // Per-user totals accumulated across every context this run, so a
            // single UPDATE per user lands at the end (atomic increments
            // survive concurrent runs / re-runs without double-counting,
            // because $seen dedupes the UserFile rows themselves).
            'per_user'   => [],
        ];
        $seen = [];

        $this->info(($dry ? '[dry-run] ' : '') . 'Backfilling: ' . implode(', ', $enabled));

        foreach ($enabled as $ctx) {
            if ($limit > 0 && $stats['scanned'] >= $limit) break;
            $this->line("· {$ctx}");
            $this->runContext($ctx, $chunk, $limit, $dry, $stats, $seen);
        }

        if (!$dry && !empty($stats['per_user'])) {
            $this->flushPerUserStats($stats['per_user']);
        }

        $this->newLine();
        $freedKb = round($stats['bytes_freed'] / 1024, 1);
        $this->info(sprintf(
            '%sScanned %d UserFile rows. Shrunk %d (freed %s KB). Skipped %d (already small / unsupported / missing).',
            $dry ? '[dry-run] ' : '',
            $stats['scanned'],
            $stats['shrunk'],
            number_format($freedKb, 1),
            $stats['skipped']
        ));

        return self::SUCCESS;
    }

    /**
     * Walk owner rows for a single context and reoptimize the referenced
     * UserFile. `$seen` is shared across contexts so a single UserFile is
     * processed once even when referenced from multiple places.
     */
    private function runContext(string $ctx, int $chunk, int $limit, bool $dry, array &$stats, array &$seen): void
    {
        [$maxW, $maxH, $quality] = self::CONTEXTS[$ctx];

        switch ($ctx) {
            case 'biolink_bg':
                $this->walkLinkSettings($chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    fn(array $bio) => isset($bio['background_image']) ? [$bio['background_image']] : []);
                break;

            case 'biolink_fallback':
                $this->walkLinkSettings($chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    fn(array $bio) => isset($bio['bg_fallback_image']) ? [$bio['bg_fallback_image']] : []);
                break;

            case 'biolink_slide':
                $this->walkLinkSettings($chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    fn(array $bio) => isset($bio['slideshow_images']) && is_array($bio['slideshow_images'])
                        ? array_values($bio['slideshow_images'])
                        : []);
                break;

            case 'biolink_og':
                $this->walkLinkSettings($chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    fn(array $bio) => isset($bio['og']['image_url']) ? [$bio['og']['image_url']] : []);
                break;

            case 'splash_og':
                $this->walkOwners(
                    SplashPage::query()->whereNotNull('og_image')->orderBy('id'),
                    $chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    fn(SplashPage $sp) => [$sp->og_image]
                );
                break;

            case 'form_cover':
                $this->walkOwners(
                    Form::query()->whereNotNull('design')->orderBy('id'),
                    $chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    function (Form $f) {
                        $design = $f->design ?? [];
                        return isset($design['cover']) ? [$design['cover']] : [];
                    }
                );
                break;

            case 'form_card':
                $this->walkOwners(
                    Form::query()->whereNotNull('design')->orderBy('id'),
                    $chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    function (Form $f) {
                        $design = $f->design ?? [];
                        return isset($design['card_image']) ? [$design['card_image']] : [];
                    }
                );
                break;

            case 'link_seo':
                $this->walkOwners(
                    Link::query()->whereNotNull('seo_image')->orderBy('id'),
                    $chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    fn(Link $l) => [$l->seo_image]
                );
                break;
        }
    }

    /**
     * Convenience walker for the four biolink-settings contexts: only Links
     * of type=biolink with a non-null `settings` blob need to be considered.
     */
    private function walkLinkSettings(int $chunk, int $limit, bool $dry, array &$stats, array &$seen, int $maxW, int $maxH, int $quality, \Closure $extract): void
    {
        $q = Link::query()
            ->where('type', 'biolink')
            ->whereNotNull('settings')
            ->orderBy('id');

        $this->walkOwners($q, $chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
            function (Link $link) use ($extract) {
                $bio = data_get($link->settings, 'biolink');
                if (!is_array($bio)) return [];
                return $extract($bio);
            }
        );
    }

    /**
     * Generic owner walker: chunk through `$query`, run `$extract` to get a
     * list of `/f/{id}/...` URLs per row, resolve to UserFile rows, and
     * reoptimize each one (deduped via `$seen`).
     */
    private function walkOwners($query, int $chunk, int $limit, bool $dry, array &$stats, array &$seen, int $maxW, int $maxH, int $quality, \Closure $extract): void
    {
        $query->chunkById($chunk, function ($rows) use (&$stats, &$seen, $limit, $dry, $maxW, $maxH, $quality, $extract) {
            foreach ($rows as $row) {
                if ($limit > 0 && $stats['scanned'] >= $limit) return false;

                $urls = $extract($row);
                foreach ($urls as $url) {
                    if ($limit > 0 && $stats['scanned'] >= $limit) return false;
                    $id = $this->extractUserFileId($url);
                    if ($id === null || isset($seen[$id])) continue;
                    $seen[$id] = true;

                    $stats['scanned']++;
                    $this->processUserFile($id, $maxW, $maxH, $quality, $dry, $stats);
                }
            }
            return true;
        });
    }

    /**
     * Stored vault URLs look like `/f/{id}/{filename}` (see
     * UserFile::getUrlAttribute). External / legacy public-disk URLs are
     * skipped — those aren't backed by a UserFile row to reoptimize.
     */
    private function extractUserFileId(?string $url): ?int
    {
        if (!is_string($url) || $url === '') return null;
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        if (!preg_match('#/f/(\d+)/#', $path, $m)) return null;
        return (int) $m[1];
    }

    private function processUserFile(int $id, int $maxW, int $maxH, int $quality, bool $dry, array &$stats): void
    {
        $file = UserFile::query()->withoutGlobalScope('workspace')->find($id);
        if (!$file || $file->type !== 'image') {
            $stats['skipped']++;
            return;
        }

        if ($dry) {
            // Dry-run can't tell ahead of time whether the recompress would
            // beat the current bytes, so just count it as "would attempt".
            return;
        }

        $beforeBytes = (int) $file->size_bytes;
        try {
            $changed = $file->reoptimizeImageInPlace($maxW, $maxH, $quality);
        } catch (\Throwable $e) {
            $stats['skipped']++;
            return;
        }

        if ($changed) {
            $stats['shrunk']++;
            $delta = $beforeBytes - (int) $file->size_bytes;
            if ($delta > 0) {
                $stats['bytes_freed'] += $delta;
                $uid = (int) $file->user_id;
                if ($uid > 0) {
                    if (!isset($stats['per_user'][$uid])) {
                        $stats['per_user'][$uid] = ['count' => 0, 'bytes' => 0];
                    }
                    $stats['per_user'][$uid]['count']++;
                    $stats['per_user'][$uid]['bytes'] += $delta;
                }
            }
        } else {
            $stats['skipped']++;
        }
    }

    /**
     * Persist accumulated per-user shrink totals onto the users row and
     * re-arm the one-time "we recovered X" banner by clearing any prior
     * dismissal. Skips users whose row no longer exists. Increments are
     * atomic so re-runs simply add to the totals.
     */
    private function flushPerUserStats(array $perUser): void
    {
        foreach ($perUser as $userId => $totals) {
            $count = (int) ($totals['count'] ?? 0);
            $bytes = (int) ($totals['bytes'] ?? 0);
            if ($count <= 0 && $bytes <= 0) continue;

            User::query()
                ->whereKey($userId)
                ->update([
                    'image_reoptimize_files_count' => DB::raw('image_reoptimize_files_count + ' . $count),
                    'image_reoptimize_bytes_freed' => DB::raw('image_reoptimize_bytes_freed + ' . $bytes),
                    // Clear any prior dismissal so the new savings show up.
                    'image_reoptimize_notice_dismissed_at' => null,
                ]);
        }
    }
}
