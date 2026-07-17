<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Content-only refresh of the long-form legal policy pages so the Privacy
 * Policy's "How long we keep your data" section discloses the event
 * contact-swap retention windows (accepted swaps kept 2 years after
 * acceptance; never-accepted requests deleted ~30 days after the event
 * ends, or ~90 days after the request when the event has no end date) —
 * matching events:prune-contact-exchanges as scheduled in
 * routes/schedules/analytics-cleanup.php.
 *
 * Follows the established seed-version pattern: a page that still holds an
 * untouched earlier default (richDefaults, policyDefaultsV1, V2 or V3,
 * matched by exact section content) is replaced wholesale and re-stamped
 * to today; an admin-edited page only receives missing sections appended
 * (matched by stable id) so admin edits are never clobbered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('site_pages')) {
            return;
        }

        $now = now();
        $today = $now->toDateString();
        $policySlugs = SitePagesContent::policySlugs();
        $defaults = SitePagesContent::policyDefaults();
        $previousDefaults = SitePagesContent::policyPreviousDefaults();

        foreach ($policySlugs as $slug) {
            if (!isset($defaults[$slug])) {
                continue;
            }
            $data = $defaults[$slug];
            $existing = DB::table('site_pages')->where('slug', $slug)->first();

            if (!$existing) {
                DB::table('site_pages')->insert([
                    'slug'             => $slug,
                    'title'            => $data['title'],
                    'meta_description' => $data['meta_description'] ?? null,
                    'intro'            => $data['intro'] ?? null,
                    'last_updated_at'  => $data['last_updated_at'] ?? $today,
                    'show_toc'         => true,
                    'sections'         => json_encode(SitePagesContent::normalizeSections($data['sections'])),
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
                continue;
            }

            $current = json_decode($existing->sections ?? '[]', true) ?: [];
            $prevList = $previousDefaults[$slug] ?? [];
            $prevSectionSets = array_map(static fn ($p) => $p['sections'] ?? [], $prevList);
            $isUntouched = SitePagesContent::sectionsMatchAnyPrevious($current, $prevSectionSets);

            if ($isUntouched) {
                $merged = SitePagesContent::normalizeSections($data['sections']);
            } else {
                $merged = SitePagesContent::mergeMissingSections($current, $data['sections']);
            }

            $newTitle = $existing->title;
            $newMeta  = $existing->meta_description;
            $newIntro = $existing->intro;
            foreach ($prevList as $prev) {
                if (trim((string) $existing->title) === trim((string) ($prev['title'] ?? ''))) {
                    $newTitle = $data['title'];
                }
                if (trim((string) $existing->meta_description) === trim((string) ($prev['meta_description'] ?? ''))) {
                    $newMeta = $data['meta_description'] ?? $existing->meta_description;
                }
                if (trim((string) $existing->intro) === trim((string) ($prev['intro'] ?? ''))) {
                    $newIntro = $data['intro'] ?? $existing->intro;
                }
            }

            // Stamp "last updated" forward only when the body was refreshed
            // wholesale; preserve the admin's date otherwise.
            $newLastUpdated = $existing->last_updated_at ?? ($data['last_updated_at'] ?? $today);
            if ($isUntouched) {
                $newLastUpdated = $data['last_updated_at'] ?? $today;
            }

            DB::table('site_pages')->where('slug', $slug)->update([
                'title'            => $newTitle,
                'meta_description' => $newMeta,
                'intro'            => $newIntro ?: ($data['intro'] ?? null),
                'last_updated_at'  => $newLastUpdated,
                'show_toc'         => true,
                'sections'         => json_encode($merged),
                'updated_at'       => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Content-only refresh; nothing to roll back.
    }
};
