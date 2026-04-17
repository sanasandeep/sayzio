<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill any links that still carry a populated `settings.splash` JSON
 * after the initial splash-pages migration. The first migration's earlier
 * version skipped rows where `enabled=false`, so disabled-but-populated
 * splash content was left in `settings.splash` and never promoted to a
 * standalone splash_pages row. This migration recovers that data.
 *
 * Idempotent: once a link is migrated its `settings.splash` is stripped,
 * so re-running finds nothing to do.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('links')
            ->whereNotNull('settings')
            ->whereNull('splash_page_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $settings = json_decode($row->settings, true) ?: [];
                    $sp = $settings['splash'] ?? null;
                    if (!is_array($sp) || empty(array_filter($sp, fn($v) => $v !== null && $v !== ''))) {
                        continue;
                    }

                    $wasEnabled = !empty($sp['enabled']);
                    $title = $sp['title'] ?? null;
                    $name = $title ?: ('Splash for link #' . $row->id);

                    $id = DB::table('splash_pages')->insertGetId([
                        'user_id'       => $row->user_id,
                        'project_id'    => $row->project_id ?? null,
                        'name'          => mb_substr($name, 0, 120),
                        'title'         => $title ? mb_substr($title, 0, 160) : null,
                        'description'   => $sp['description']   ?? null,
                        'cta_label'     => isset($sp['cta_label']) ? mb_substr($sp['cta_label'], 0, 60) : null,
                        'cta_url'       => isset($sp['cta_url'])   ? mb_substr($sp['cta_url'], 0, 2000) : null,
                        'auto_redirect' => !empty($sp['auto_redirect']),
                        'countdown'     => isset($sp['countdown']) ? max(0, min(120, (int) $sp['countdown'])) : 5,
                        'logo'          => $sp['logo']          ?? null,
                        'favicon'       => $sp['favicon']       ?? null,
                        'og_image'      => $sp['og_image']      ?? null,
                        'custom_css'    => $sp['custom_css']    ?? null,
                        'custom_js'     => $sp['custom_js']     ?? null,
                        'usage_count'   => $wasEnabled ? 1 : 0,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);

                    unset($settings['splash']);
                    DB::table('links')->where('id', $row->id)->update([
                        'splash_page_id' => $id,
                        'splash_enabled' => $wasEnabled,
                        'settings'       => json_encode($settings),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // No-op: backfill is best-effort recovery; reverting would re-introduce data loss.
    }
};
