<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Content-only refresh of the long-form legal policy pages so the Privacy
 * Policy carries a dedicated "Google user data" section (Google sign-in
 * data received; Google Contacts sync via the People API — what is
 * accessed, two-way user-initiated sync, storage and deletion; the Google
 * API Services User Data Policy Limited Use statement with link; and how
 * to revoke access) and the Terms of Service third-party-integrations
 * clause briefly references Google (sign-in, Contacts and Calendar sync
 * governed by Google's own terms).
 *
 * Follows the established seed-version pattern: a page that still holds an
 * untouched earlier default (richDefaults, the legacy simple policy copy,
 * policyDefaultsV1, V2, V3 or V4, matched by exact section content) is
 * replaced wholesale and re-stamped to today; an admin-edited page only
 * receives missing sections appended (matched by stable id) so admin
 * edits are never clobbered.
 */
return new class extends Migration
{
    /**
     * Sections whose BODY changed in this generation and should be
     * refreshed per-section on otherwise admin-edited pages, but only when
     * that section's body still verbatim-matches a previous default.
     */
    private const SECTION_REFRESH = [
        'terms' => ['third-party-services'],
    ];

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

                // Per-section refresh: the Terms Google reference is a body
                // edit to an existing section, which append-missing cannot
                // deliver. When that one section's body still verbatim-matches
                // a previous default (i.e. the admin never touched THAT
                // section, even if other sections on the page were edited),
                // replace just that section's body with the new default.
                foreach (self::SECTION_REFRESH[$slug] ?? [] as $sectionId) {
                    $newDefault = null;
                    foreach (SitePagesContent::normalizeSections($data['sections']) as $s) {
                        if ($s['id'] === $sectionId) {
                            $newDefault = $s;
                            break;
                        }
                    }
                    if (!$newDefault) {
                        continue;
                    }
                    $prevBodies = [];
                    foreach ($prevList as $prev) {
                        foreach (SitePagesContent::normalizeSections($prev['sections'] ?? []) as $s) {
                            if ($s['id'] === $sectionId) {
                                $prevBodies[] = trim($s['body']);
                            }
                        }
                    }
                    foreach ($merged as $k => $s) {
                        if (($s['id'] ?? '') === $sectionId && in_array(trim((string) ($s['body'] ?? '')), $prevBodies, true)) {
                            $merged[$k]['body'] = $newDefault['body'];
                        }
                    }
                }
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
