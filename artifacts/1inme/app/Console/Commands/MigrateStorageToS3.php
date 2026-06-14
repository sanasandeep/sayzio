<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off (idempotent, resumable) copy of existing local user-content files
 * into S3, preserving the exact relative paths so the `disk` value stamped on
 * each DB record keeps resolving once the disks are flipped to S3.
 *
 * Local source roots copied into the bucket (same relative paths):
 *   storage/app/public       (public content — CloudFront-served)
 *   storage/app/user-files   (private content — signed temporary URLs)
 *   storage/app/admin-assets (private content — signed temporary URLs)
 *
 * The command builds its own LOCAL source disk and a single S3 target disk
 * straight from config, so it works the same whether USER_CONTENT_DISK is
 * already `s3` or still `local`. No per-object ACLs are set: the `1in.me`
 * bucket has ACLs disabled, and access is governed by the bucket policy +
 * CloudFront (public) and pre-signed URLs (private). It never deletes the
 * local copies — flip the toggle and verify first, then prune locally.
 *
 * Re-running is safe: files already present in S3 (same size) are skipped, so
 * an interrupted run (e.g. on a high-latency cross-region bucket) just resumes.
 */
class MigrateStorageToS3 extends Command
{
    protected $signature = 'storage:migrate-to-s3
        {--only= : Comma-separated subset of groups to copy (public,user_files,admin_assets)}
        {--force : Re-upload even when an object of the same size already exists}
        {--limit=0 : Cap on files copied per group (0 = no cap), useful for a smoke test}';

    protected $description = 'Copy existing local user-content files (public, user_files, admin_assets) into the S3 bucket, preserving relative paths.';

    /** group => local source root (copied to S3 under the same relative paths). */
    private const GROUPS = [
        'public'       => 'app/public',
        'user_files'   => 'app/user-files',
        'admin_assets' => 'app/admin-assets',
    ];

    public function handle(): int
    {
        if (!env('AWS_BUCKET')) {
            $this->error('AWS_BUCKET is not configured — set the AWS_* env vars before migrating.');
            return self::FAILURE;
        }

        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');

        $target = $this->s3Disk();
        $grandCopied = $grandSkipped = $grandFailed = 0;

        foreach (self::GROUPS as $group => $root) {
            if ($only && !in_array($group, $only, true)) {
                continue;
            }

            $sourcePath = storage_path($root);
            if (!is_dir($sourcePath)) {
                $this->line("  [{$group}] no local directory ({$sourcePath}) — nothing to copy.");
                continue;
            }

            $source = Storage::build([
                'driver' => 'local',
                'root' => $sourcePath,
                'throw' => true,
            ]);

            $files = $source->allFiles();
            $total = count($files);
            $this->info("[{$group}] {$total} local file(s) → s3");

            $copied = $skipped = $failed = 0;
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            foreach ($files as $relative) {
                if ($limit > 0 && $copied >= $limit) {
                    break;
                }

                try {
                    if (!$force && $target->exists($relative)
                        && $target->size($relative) === $source->size($relative)) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    $stream = $source->readStream($relative);
                    $ok = $target->writeStream($relative, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    if ($ok) {
                        $copied++;
                    } else {
                        $failed++;
                        $this->newLine();
                        $this->warn("  failed: {$group}/{$relative}");
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("  error: {$group}/{$relative} — {$e->getMessage()}");
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->line("  [{$group}] copied={$copied} skipped={$skipped} failed={$failed}");

            $grandCopied += $copied;
            $grandSkipped += $skipped;
            $grandFailed += $failed;
        }

        $this->newLine();
        $this->info("Done. copied={$grandCopied} skipped={$grandSkipped} failed={$grandFailed}");

        return $grandFailed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Build the S3 target disk from config. No `visibility` is set because the
     * bucket has ACLs disabled — see the class docblock.
     */
    private function s3Disk()
    {
        return Storage::build([
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
        ]);
    }
}
