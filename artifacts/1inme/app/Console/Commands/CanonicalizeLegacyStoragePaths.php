<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * One-off (idempotent, resumable) backfill that rewrites legacy `/storage/<path>`
 * column values to their canonical URL, so exports, emails, and third-party
 * consumers get correct image URLs even outside the app's render path (where
 * runtime resolution + the /storage bridge route currently cover them).
 *
 * Pattern follows storage:migrate-to-s3: safe on the shared RDS (UPDATEs only,
 * chunked, no schema changes), re-runnable (only rows still starting with
 * `/storage/` match, and rewritten values never match again).
 *
 * By default rewrites to the canonical CDN/S3 URL built via
 * Storage::disk('public')->url() — this requires the `public` disk to be
 * S3-backed (otherwise the "canonical" URL would be a dev-host URL, which is
 * worse than the portable `/storage/...` form). Pass --relative to instead
 * strip the `/storage/` prefix and store the bare disk-relative path (works
 * on any driver, but only do this where every consumer resolves via the disk).
 */
class CanonicalizeLegacyStoragePaths extends Command
{
    protected $signature = 'storage:canonicalize-legacy-paths
        {--dry-run : Report what would change without writing anything}
        {--only= : Comma-separated subset of tables (users,creator_posts,blog_posts,blog_categories,links)}
        {--relative : Rewrite to a bare disk-relative path instead of the CDN URL}
        {--chunk=500 : Rows per chunk}';

    protected $description = 'Rewrite legacy /storage/<path> DB values to the canonical CDN URL (or bare relative path) so rows never depend on runtime fallbacks.';

    /** table => [id column, [columns holding legacy /storage/ values]] */
    private const TABLES = [
        'users'           => ['id', ['avatar', 'cover_image']],
        'creator_posts'   => ['id', ['image']],
        'blog_posts'      => ['id', ['cover_image', 'og_image']],
        'blog_categories' => ['id', ['cover_image']],
        'links'           => ['id', ['verified_logo']],
    ];

    private const PREFIX = '/storage/';

    public function handle(): int
    {
        $dryRun   = (bool) $this->option('dry-run');
        $relative = (bool) $this->option('relative');
        $chunk    = max(1, (int) $this->option('chunk'));
        $only     = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        if ($unknown = array_diff($only, array_keys(self::TABLES))) {
            $this->error('Unknown table(s) in --only: ' . implode(', ', $unknown)
                . '. Valid: ' . implode(', ', array_keys(self::TABLES)));
            return self::FAILURE;
        }

        if (!$relative) {
            if (config('filesystems.disks.public.driver') !== 's3') {
                $this->error('The `public` disk is not S3-backed, so there is no canonical CDN URL to write. '
                    . 'Either flip the disk to S3 first, or pass --relative to store bare disk-relative paths.');
                return self::FAILURE;
            }
            try {
                // Verify URL building works before touching any rows.
                Storage::disk('public')->url('healthcheck.txt');
            } catch (\Throwable $e) {
                $this->error('Could not build a public-disk URL (S3 misconfigured?): ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        $mode = $relative ? 'bare relative path' : 'canonical CDN URL';
        $this->info(($dryRun ? '[dry-run] ' : '') . "Rewriting legacy /storage/ values to {$mode}…");

        $grandUpdated = $grandFailed = 0;

        foreach (self::TABLES as $table => [$idColumn, $columns]) {
            if ($only && !in_array($table, $only, true)) {
                continue;
            }
            if (!Schema::hasTable($table)) {
                $this->line("  [{$table}] table missing — skipped.");
                continue;
            }
            $columns = array_values(array_filter(
                $columns,
                fn (string $c) => Schema::hasColumn($table, $c)
            ));
            if (!$columns) {
                $this->line("  [{$table}] no matching columns — skipped.");
                continue;
            }

            foreach ($columns as $column) {
                [$updated, $failed] = $this->rewriteColumn($table, $idColumn, $column, $relative, $dryRun, $chunk);
                $grandUpdated += $updated;
                $grandFailed  += $failed;
            }
        }

        $this->newLine();
        $verb = $dryRun ? 'would update' : 'updated';
        $this->info("Done. {$verb}={$grandUpdated} failed={$grandFailed}");

        return $grandFailed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{0:int,1:int} [updated, failed] */
    private function rewriteColumn(string $table, string $idColumn, string $column, bool $relative, bool $dryRun, int $chunk): array
    {
        $total = DB::table($table)->where($column, 'like', self::PREFIX . '%')->count();
        $this->line("  [{$table}.{$column}] {$total} legacy row(s)");
        if ($total === 0) {
            return [0, 0];
        }

        $updated = $failed = 0;

        DB::table($table)
            ->select([$idColumn, $column])
            ->where($column, 'like', self::PREFIX . '%')
            ->orderBy($idColumn)
            ->chunkById($chunk, function ($rows) use ($table, $idColumn, $column, $relative, $dryRun, &$updated, &$failed) {
                foreach ($rows as $row) {
                    $old  = (string) $row->{$column};
                    $path = ltrim(substr($old, strlen(self::PREFIX)), '/');
                    if ($path === '') {
                        continue; // degenerate "/storage/" value — leave untouched
                    }

                    try {
                        $new = $relative ? $path : Storage::disk('public')->url($path);
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->warn("    error: {$table}.{$column} #{$row->{$idColumn}} — {$e->getMessage()}");
                        continue;
                    }

                    if ($new === $old) {
                        continue;
                    }

                    if ($dryRun) {
                        $updated++;
                        if ($updated <= 5) {
                            $this->line("    would rewrite #{$row->{$idColumn}}: {$old} → {$new}");
                        }
                        continue;
                    }

                    // Re-check the value in the WHERE so a concurrent user
                    // update between read and write is never clobbered.
                    $updated += DB::table($table)
                        ->where($idColumn, $row->{$idColumn})
                        ->where($column, $old)
                        ->update([$column => $new]);
                }
            }, $idColumn);

        $this->line("    updated={$updated} failed={$failed}");

        return [$updated, $failed];
    }
}
