<?php

use App\Modules\Common\Support\SitePagesContent;
use Database\Seeders\SitePagesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: some production site_pages rows (notably `buzz`) were
 * auto-created with the generic "Coming soon — this page is being
 * prepared" fallback because seeders never run on production. Replace any
 * row that still holds that untouched fallback (or the original short
 * placeholder copy) with the canonical seeded copy, leaving admin-edited
 * rows untouched. Additive + idempotent — safe for the shared database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('site_pages')) {
            return;
        }

        $now = now();

        // Canonical copy per slug: seeder-owned marketing/error pages,
        // AI product pages, and the centralised rich defaults.
        $canonical = [];
        foreach (SitePagesSeeder::extraPages() as $p) {
            $canonical[$p['slug']] = $p;
        }
        foreach (SitePagesContent::aiProductsDefault() as $slug => $data) {
            $canonical[$slug] = $data + ['slug' => $slug];
        }
        $policySlugs = SitePagesContent::policySlugs();
        foreach (SitePagesContent::richDefaults() as $slug => $data) {
            if (isset($canonical[$slug])) continue;
            // Policy pages have their own merge/refresh flow; only heal
            // them here if they still hold the generic fallback (handled
            // by the placeholder check below like every other slug).
            $canonical[$slug] = $data + ['slug' => $slug];
            if ($slug === 'features') {
                $canonical[$slug]['sections'] = SitePagesContent::featuresCategoriesDefault();
            }
        }

        foreach ($canonical as $slug => $data) {
            $existing = DB::table('site_pages')->where('slug', $slug)->first();
            if (!$existing) {
                continue; // creation is owned by the earlier seed migration/seeder
            }

            $current = json_decode($existing->sections ?? '[]', true) ?: [];
            if (!SitePagesContent::isStillPlaceholder($slug, $current)) {
                continue; // admin-edited (or already rich) — leave untouched
            }

            DB::table('site_pages')->where('slug', $slug)->update([
                'title' => $data['title'],
                'meta_description' => $data['meta_description'] ?? $existing->meta_description,
                'sections' => json_encode($data['sections']),
                'cta_label' => $data['cta_label'] ?? $existing->cta_label,
                'cta_url' => $data['cta_url'] ?? $existing->cta_url,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive: keep the healed content on rollback.
    }
};
