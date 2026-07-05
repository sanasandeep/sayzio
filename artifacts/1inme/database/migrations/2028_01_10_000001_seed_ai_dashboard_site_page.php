<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the `ai-dashboard` marketing page into already-seeded installs.
 *
 * `SitePagesSeeder` generically upserts every `SitePagesContent::richDefaults()`
 * entry into `site_pages`, so fresh installs pick up the new `ai-dashboard`
 * row automatically. Existing (already-seeded) installs never re-run that
 * seeder, so — mirroring 2026_07_01_000001_seed_rich_footer_pages_and_social_links.php
 * and 2028_01_09_000001_sync_qr_code_forms_link_type_showcase.php — this
 * migration inserts the row directly, only when it does not already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('site_pages')->where('slug', 'ai-dashboard')->exists()) {
            return;
        }

        $data = SitePagesContent::richDefaults()['ai-dashboard'] ?? null;
        if (!$data) {
            return;
        }

        $now = now();

        DB::table('site_pages')->insert([
            'slug' => 'ai-dashboard',
            'title' => $data['title'],
            'meta_description' => $data['meta_description'] ?? null,
            'sections' => json_encode($data['sections'] ?? []),
            'cta_label' => $data['cta_label'] ?? null,
            'cta_url' => $data['cta_url'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Non-destructive: leave the seeded page in place on rollback.
    }
};
