<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\User\Services\PersonaCatalog;
use Database\Seeders\ExpandedPageTemplateLibrarySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-shot command to refresh persona "Recommended for you" templates.
 *
 * Background: ExpandedPageTemplateLibrarySeeder is idempotent and never
 * overwrites existing rows. Environments seeded before task #958 still
 * show the old beige starter pages. This command deletes a persona's
 * `persona-<slug>-*` rows ONLY if every one of them looks like a
 * seed-generated row that no admin has touched, then re-runs the seeder
 * so users see the new richer designs.
 *
 * Per-row classification (all four signals required):
 *  1. Slug is in the `persona-<slug>-<key>` namespace this seeder owns.
 *  2. `updated_at` has not drifted past `created_at` (small tolerance).
 *     Eloquent bumps `updated_at` on every save, so any admin edit
 *     leaves a fingerprint here.
 *  3. Name + description: if they match the CURRENT blueprint, the row
 *     is already up to date. If they differ AND the timestamp is clean,
 *     it's a stale OLD seed row that needs refreshing.
 *  4. Snapshot hash: same logic as #3, with the dynamic
 *     `countdown.target_date` field stripped before hashing (the seeder
 *     stamps `now()+30d` into it, so a literal compare would always
 *     drift even on a freshly-seeded row).
 *
 * Per-persona verdict:
 *  - `current` — every row matches the current blueprint AND has a
 *    clean timestamp. Nothing to do (true no-op on reruns).
 *  - `stale`   — every row has a clean timestamp but at least one row's
 *    content differs from the current blueprint. Delete and re-seed.
 *  - `edited`  — at least one row has a drifted timestamp OR an
 *    unknown slug (admin-added). Skip the persona entirely so curated
 *    content is preserved.
 *
 * Safe to re-run: refreshed personas land in the `current` bucket on
 * the next invocation, producing no further deletes or writes.
 */
class RefreshPersonaSeed extends Command
{
    protected $signature = 'templates:refresh-persona-seed
        {--persona= : Only consider this persona slug}
        {--dry-run : Print the plan without deleting or re-seeding}';

    protected $description = 'Delete seed-only persona templates and re-run ExpandedPageTemplateLibrarySeeder so users see the refreshed variety.';

    /** Tolerance (seconds) for treating updated_at == created_at. */
    private const EDIT_DRIFT_TOLERANCE = 2;

    public function handle(): int
    {
        $dry        = (bool) $this->option('dry-run');
        $only       = $this->option('persona');
        $seeder     = new ExpandedPageTemplateLibrarySeeder();
        $rows       = [];
        $idsToDel   = [];
        $needReseed = false;
        $refreshed  = 0;
        $unchanged  = 0;
        $skipped    = 0;

        foreach (PersonaCatalog::all() as $persona) {
            $slug = $persona['slug'];
            if ($only && $only !== $slug) {
                continue;
            }

            $existing = PageTemplate::query()
                ->where('slug', 'like', 'persona-' . $slug . '-%')
                ->get(['id', 'slug', 'name', 'description', 'snapshot', 'created_at', 'updated_at']);

            $blueprintsBySlug = [];
            foreach ($seeder->blueprintsFor($persona) as $bp) {
                $bpSlug = 'persona-' . $slug . '-' . Str::slug($bp['key']);
                $blueprintsBySlug[$bpSlug] = $bp;
            }

            if ($existing->isEmpty()) {
                $rows[]      = [$slug, 0, 'create'];
                $needReseed  = true;
                $refreshed++;
                continue;
            }

            $verdict = $this->classify($existing, $blueprintsBySlug);

            if ($verdict['state'] === 'current') {
                $rows[] = [$slug, $existing->count(), 'up to date'];
                $unchanged++;
            } elseif ($verdict['state'] === 'stale') {
                $rows[]     = [$slug, $existing->count(), 'refresh' . ($dry ? ' (dry-run)' : '')];
                $idsToDel   = array_merge($idsToDel, $existing->pluck('id')->all());
                $needReseed = true;
                $refreshed++;
            } else {
                $rows[] = [$slug, $existing->count(), 'skip — ' . $verdict['reason']];
                $skipped++;
            }
        }

        $this->table(['Persona', 'Existing rows', 'Result'], $rows);

        if ($dry) {
            $this->comment('Dry run — nothing deleted or re-seeded.');
            $this->info("Would refresh: {$refreshed}. Up to date: {$unchanged}. Would skip: {$skipped}.");
            return self::SUCCESS;
        }

        if (!empty($idsToDel)) {
            $deleted = PageTemplate::whereIn('id', $idsToDel)->delete();
            $this->info("Deleted {$deleted} stale seed template row(s).");
        }

        if ($needReseed) {
            // Re-run the seeder once. It's idempotent and only fills
            // personas that fall below the per-persona minimum, so
            // refreshed/empty personas get fresh rows while everyone
            // else (including admin-edited personas) is left alone.
            $seeder->setCommand($this);
            $seeder->run();
        } else {
            $this->info('Nothing to do — all personas already match the current seeder output.');
        }

        $this->info("Refreshed: {$refreshed}. Up to date: {$unchanged}. Skipped: {$skipped}.");
        return self::SUCCESS;
    }

    /**
     * Decide a persona's overall verdict from its existing rows.
     *
     * @param  \Illuminate\Support\Collection<int, PageTemplate>  $existing
     * @param  array<string, array{key:string,name:string,description:string,thumb:string,snapshot:array}>  $blueprints
     * @return array{state:'current'|'stale'|'edited',reason:string}
     */
    private function classify($existing, array $blueprints): array
    {
        $anyDrift = false;

        foreach ($existing as $row) {
            // Unknown slug — admin-added template living in the persona
            // namespace. Don't touch this persona at all.
            if (!isset($blueprints[$row->slug])) {
                return ['state' => 'edited', 'reason' => "unrecognized slug {$row->slug} (admin-added)"];
            }

            // Timestamp drift — admin saved through the panel.
            if ($row->updated_at && $row->created_at
                && $row->updated_at->getTimestamp() - $row->created_at->getTimestamp() > self::EDIT_DRIFT_TOLERANCE) {
                return ['state' => 'edited', 'reason' => "row {$row->slug} edited after creation"];
            }

            $bp = $blueprints[$row->slug];
            if ((string) $row->name !== (string) $bp['name']
                || (string) $row->description !== (string) $bp['description']
                || $this->snapshotHash((array) $row->snapshot) !== $this->snapshotHash($bp['snapshot'])) {
                $anyDrift = true;
            }
        }

        return ['state' => $anyDrift ? 'stale' : 'current', 'reason' => ''];
    }

    /**
     * Stable hash of a snapshot, ignoring fields the seeder fills with
     * dynamic values (currently the countdown block's `target_date`,
     * which is `now()+30d` at seed time).
     */
    private function snapshotHash(array $snapshot): string
    {
        return sha1(json_encode($this->canonicalize($snapshot)));
    }

    /**
     * Recursively scrub keys whose values are populated dynamically by
     * the seeder, so freshly-seeded rows hash equal to their blueprint.
     * Associative-array keys are also sorted so JSON drivers that
     * re-order object keys on round-trip (some pg/mysql JSON paths) can't
     * flip an unchanged row into a "stale" classification on rerun.
     *
     * @param  mixed  $value
     * @return mixed
     */
    private function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $k => $v) {
            if ($k === 'target_date') {
                $out[$k] = '__DYNAMIC__';
            } else {
                $out[$k] = $this->canonicalize($v);
            }
        }
        // Sort assoc arrays by key; leave list arrays in order (their
        // position is meaningful — e.g. block ordering on a page).
        if (!array_is_list($out)) {
            ksort($out);
        }
        return $out;
    }
}
