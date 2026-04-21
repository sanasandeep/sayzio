<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rich = SitePagesContent::richDefaults();

        foreach ($rich as $slug => $data) {
            $existing = DB::table('site_pages')->where('slug', $slug)->first();

            if (!$existing) {
                DB::table('site_pages')->insert([
                    'slug' => $slug,
                    'title' => $data['title'],
                    'meta_description' => $data['meta_description'] ?? null,
                    'sections' => json_encode($data['sections']),
                    'cta_label' => $data['cta_label'] ?? null,
                    'cta_url' => $data['cta_url'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                continue;
            }

            $current = json_decode($existing->sections ?? '[]', true) ?: [];
            if (SitePagesContent::isStillPlaceholder($slug, $current)) {
                DB::table('site_pages')->where('slug', $slug)->update([
                    'title' => $data['title'],
                    'meta_description' => $data['meta_description'] ?? $existing->meta_description,
                    'sections' => json_encode($data['sections']),
                    'updated_at' => $now,
                ]);
            }
        }

        // Make sure every footer-linked slug exists so no link can 404.
        foreach (SitePagesContent::footerSlugs() as $slug) {
            $exists = DB::table('site_pages')->where('slug', $slug)->exists();
            if (!$exists) {
                $fallback = SitePagesContent::fallbackForMissing($slug);
                DB::table('site_pages')->insert([
                    'slug' => $slug,
                    'title' => $fallback['title'],
                    'meta_description' => $fallback['meta_description'] ?? null,
                    'sections' => json_encode($fallback['sections']),
                    'cta_label' => $fallback['cta_label'] ?? null,
                    'cta_url' => $fallback['cta_url'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: leave the seeded content in place on rollback.
    }
};
