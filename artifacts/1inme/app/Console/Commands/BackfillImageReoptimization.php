<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SplashPage;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        {--only= : Comma-separated subset of contexts to run (biolink_bg,biolink_fallback,biolink_slide,biolink_og,splash_og,form_cover,form_card,link_seo)}
        {--no-alert : Suppress the operator alert for this run (use for the expected one-off post-deploy backfill)}';

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

    /**
     * AppSetting key holding the operator-alert config / runtime state for
     * this command. See {@see alertDefaults()} for the shape.
     */
    public const ALERT_SETTING_KEY = 'images.reoptimize_alert';

    /**
     * Defaults for the alert config block. The state-tracking keys
     * (`last_sent_at`, `suppress_next`) are stored alongside the
     * operator-tunable knobs so a single AppSetting row owns everything.
     */
    public static function alertDefaults(): array
    {
        return [
            // Master switch — when false, the command never alerts even
            // if a nightly run shrinks above the threshold.
            'enabled'        => true,
            // Fire when a single run shrinks more than this many files.
            'threshold'      => 25,
            // Don't re-alert until this many hours have elapsed since the
            // last alert. The nightly tick is 24h, so 48h means at most
            // one alert per two consecutive bad runs by default.
            'cooldown_hours' => 48,
            // Optional explicit recipients; comma/space/semicolon separated.
            // When empty, every super_admin with a verified email is mailed.
            'emails'         => '',
            // Set by an admin via the AppSetting store right before they
            // opt a brand-new context into the upload-time pipeline (or
            // bump the per-context max dimensions). The next nightly run
            // is then expected to legitimately shrink lots of files; this
            // flag suppresses that one-off alert and is auto-cleared
            // after the run that consumed it.
            'suppress_next'  => false,
            // ISO-8601 timestamp of the most recent alert dispatch.
            // Used to enforce `cooldown_hours`.
            'last_sent_at'   => null,
        ];
    }

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
            // Per-context shrink counts, used so the operator alert can
            // pinpoint which upload surface regressed (e.g. "27 form_cover
            // shrinks" → check the form-cover upload path).
            'per_context_shrunk' => [],
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

        if (!$dry) {
            $this->maybeDispatchAlert($stats);
        }

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
                $this->walkLinkSettings($ctx, $chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    fn(array $bio) => isset($bio['background_image']) ? [$bio['background_image']] : []);
                break;

            case 'biolink_fallback':
                $this->walkLinkSettings($ctx, $chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    fn(array $bio) => isset($bio['bg_fallback_image']) ? [$bio['bg_fallback_image']] : []);
                break;

            case 'biolink_slide':
                $this->walkLinkSettings($ctx, $chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    fn(array $bio) => isset($bio['slideshow_images']) && is_array($bio['slideshow_images'])
                        ? array_values($bio['slideshow_images'])
                        : []);
                break;

            case 'biolink_og':
                $this->walkLinkSettings($ctx, $chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    fn(array $bio) => isset($bio['og']['image_url']) ? [$bio['og']['image_url']] : []);
                break;

            case 'splash_og':
                $this->walkOwners(
                    $ctx,
                    SplashPage::query()->whereNotNull('og_image')->orderBy('id'),
                    $chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
                    fn(SplashPage $sp) => [$sp->og_image]
                );
                break;

            case 'form_cover':
                $this->walkOwners(
                    $ctx,
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
                    $ctx,
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
                    $ctx,
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
    private function walkLinkSettings(string $ctx, int $chunk, int $limit, bool $dry, array &$stats, array &$seen, int $maxW, int $maxH, int $quality, \Closure $extract): void
    {
        $q = Link::query()
            ->where('type', 'biolink')
            ->whereNotNull('settings')
            ->orderBy('id');

        $this->walkOwners($ctx, $q, $chunk, $limit, $dry, $stats, $seen, $maxW, $maxH, $quality,
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
     * reoptimize each one (deduped via `$seen`). The `$ctx` label is
     * threaded through so per-context shrink counts can be attributed
     * back to the correct upload surface in the operator alert.
     */
    private function walkOwners(string $ctx, $query, int $chunk, int $limit, bool $dry, array &$stats, array &$seen, int $maxW, int $maxH, int $quality, \Closure $extract): void
    {
        $query->chunkById($chunk, function ($rows) use ($ctx, &$stats, &$seen, $limit, $dry, $maxW, $maxH, $quality, $extract) {
            foreach ($rows as $row) {
                if ($limit > 0 && $stats['scanned'] >= $limit) return false;

                $urls = $extract($row);
                foreach ($urls as $url) {
                    if ($limit > 0 && $stats['scanned'] >= $limit) return false;
                    $id = $this->extractUserFileId($url);
                    if ($id === null || isset($seen[$id])) continue;
                    $seen[$id] = true;

                    $stats['scanned']++;
                    $this->processUserFile($ctx, $id, $maxW, $maxH, $quality, $dry, $stats);
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

    private function processUserFile(string $ctx, int $id, int $maxW, int $maxH, int $quality, bool $dry, array &$stats): void
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
            $stats['per_context_shrunk'][$ctx] = ($stats['per_context_shrunk'][$ctx] ?? 0) + 1;
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

    /**
     * Operator alert: once the legacy vault is clean every nightly run
     * should be a no-op. So if a run shrinks more than the configured
     * threshold of files, that's a signal the upload-time `compress_image`
     * pipeline regressed (or a new image surface was added without
     * opting in). Notify super_admins (in-app + email) and include the
     * per-context breakdown so the offending upload path is obvious.
     *
     * Suppressed by, in order:
     *   - the `--no-alert` CLI flag (used for the expected one-off
     *     post-deploy backfill that's run by hand)
     *   - the `suppress_next` AppSetting flag, which an admin sets right
     *     before opting a new context in / bumping max dimensions; it's
     *     auto-cleared by the next run that exceeds the threshold so a
     *     genuine regression after that still alerts
     *   - the `cooldown_hours` window since the previous alert
     *   - the `enabled` master switch
     */
    private function maybeDispatchAlert(array $stats): void
    {
        $cfg = array_replace(self::alertDefaults(), (array) AppSetting::get(self::ALERT_SETTING_KEY, []));

        $shrunk     = (int) ($stats['shrunk'] ?? 0);
        $perContext = (array) ($stats['per_context_shrunk'] ?? []);
        $threshold  = max(1, (int) ($cfg['threshold'] ?? 25));

        if ($shrunk <= $threshold) {
            return;
        }

        if (!($cfg['enabled'] ?? true)) {
            $this->info("Shrunk {$shrunk} > threshold {$threshold}, but alerts are disabled — skipping.");
            return;
        }

        if ($this->option('no-alert')) {
            // Manual one-off run (e.g. opted a new context in). Also
            // consume the suppress_next flag so a subsequent nightly run
            // doesn't double-suppress.
            if (!empty($cfg['suppress_next'])) {
                AppSetting::put(self::ALERT_SETTING_KEY, array_replace($cfg, ['suppress_next' => false]));
            }
            $this->info("Shrunk {$shrunk} > threshold {$threshold}, alert suppressed via --no-alert.");
            return;
        }

        if (!empty($cfg['suppress_next'])) {
            // Expected post-deploy / opt-in backfill — eat the alert
            // exactly once and re-arm for the next run.
            AppSetting::put(self::ALERT_SETTING_KEY, array_replace($cfg, ['suppress_next' => false]));
            $this->info("Shrunk {$shrunk} > threshold {$threshold}, alert suppressed via suppress_next flag (one-off).");
            return;
        }

        $cooldownHours = max(1, (int) ($cfg['cooldown_hours'] ?? 48));
        $lastSent = $cfg['last_sent_at'] ?? null;
        if ($lastSent) {
            try {
                $lastSentAt = Carbon::parse($lastSent);
                if ($lastSentAt->greaterThan(now()->subHours($cooldownHours))) {
                    $this->info("Shrunk {$shrunk} > threshold {$threshold}, but inside cooldown (last sent {$lastSentAt->diffForHumans()}) — skipping.");
                    return;
                }
            } catch (\Throwable $e) {
                // Malformed timestamp — fall through and re-alert; the
                // write below will heal the value.
            }
        }

        // Sort the per-context breakdown by count desc so the worst
        // offender leads the email body.
        arsort($perContext);
        $contextLines = [];
        foreach ($perContext as $ctx => $n) {
            $contextLines[] = "  · {$ctx}: {$n}";
        }
        $contextSummary = $contextLines
            ? implode("\n", $contextLines)
            : '  · (no per-context attribution)';

        $contextList = !empty($perContext) ? implode(', ', array_keys($perContext)) : '(unknown)';

        $subject = "Image reoptimize: {$shrunk} files shrunk last night (threshold {$threshold})";
        $body    = "The nightly images:backfill-reoptimize job shrunk {$shrunk} files in its last run, exceeding the configured threshold of {$threshold}.\n\n"
                 . "Once the legacy vault is clean, runs should be no-ops, so a sustained shrink count usually means the upload-time compress_image pipeline regressed for one of these surfaces, or a new image surface was added without opting in.\n\n"
                 . "Per-context breakdown:\n" . $contextSummary . "\n\n"
                 . "Suspect upload paths: {$contextList}.";
        $url     = route('admin.dashboard');

        $admins = User::query()->where('role', 'super_admin')->get();

        $inAppDelivered = 0;
        foreach ($admins as $u) {
            try {
                UserNotification::create([
                    'user_id' => $u->id,
                    'type'    => 'image_reoptimize_alert',
                    'data'    => [
                        'subject'      => $subject,
                        'body'         => $body,
                        'message'      => $body, // legacy field rendered by the user_notifications view
                        'url'          => $url,  // canonical key consumed by the in-app notification list
                        'target_url'   => $url,  // legacy alias for older renderers
                        'shrunk'       => $shrunk,
                        'threshold'    => $threshold,
                        'per_context'  => $perContext,
                    ],
                    'created_at' => now(),
                ]);
                $inAppDelivered++;
            } catch (\Throwable $e) {
                Log::warning("image-reoptimize alert in-app failed for user {$u->id}: " . $e->getMessage());
            }
        }

        // Email fan-out: explicit list if the admin configured one,
        // otherwise every super_admin with a verified email. Re-validate
        // each address defensively in case the setting was hand-edited.
        $explicit = [];
        foreach (preg_split('/[\s,;]+/', (string) ($cfg['emails'] ?? '')) ?: [] as $p) {
            $p = strtolower(trim((string) $p));
            if ($p !== '' && filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $explicit[$p] = true;
            }
        }
        $explicit = array_keys($explicit);
        if (!empty($explicit)) {
            $emails = $explicit;
        } else {
            $emails = $admins
                ->filter(fn ($u) => $u->email && $u->email_verified_at)
                ->pluck('email')
                ->unique()
                ->values()
                ->all();
        }

        $emailsSent = 0;
        foreach ($emails as $email) {
            try {
                Mail::raw($body . "\n\n" . $url, function ($m) use ($email, $subject) {
                    $m->to($email)->subject($subject);
                });
                $emailsSent++;
            } catch (\Throwable $e) {
                Log::warning("image-reoptimize alert email to {$email} failed: " . $e->getMessage());
            }
        }

        AppSetting::put(self::ALERT_SETTING_KEY, array_replace($cfg, [
            'last_sent_at' => now()->toIso8601String(),
        ]));

        $this->info("Alert dispatched — in-app: {$inAppDelivered}, email: {$emailsSent}.");
    }
}
