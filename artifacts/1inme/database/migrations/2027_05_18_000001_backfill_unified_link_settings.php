<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Task #1467 — consolidate duplicated link settings onto a single source of
 * truth and migrate existing data so nothing is lost.
 *
 * For every link that still carries the legacy duplicated copies inside the
 * `settings.biolink` JSON, promote them onto the canonical `Link` columns
 * (when the column is still empty) and then strip the JSON duplicates so the
 * two can never diverge again:
 *
 *   settings.biolink.meta.seo_title        -> links.seo_title
 *   settings.biolink.meta.seo_description  -> links.seo_description
 *   settings.biolink.og.image_url          -> links.seo_image
 *   settings.biolink.favicon_url           -> links.favicon
 *
 * Idempotent: once a row is migrated the JSON keys are gone, so re-running
 * finds nothing to promote.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('links')
            ->whereNotNull('settings')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $settings = json_decode($row->settings, true);
                    if (!is_array($settings) || empty($settings['biolink']) || !is_array($settings['biolink'])) {
                        continue;
                    }

                    $bl = $settings['biolink'];
                    $update = [];
                    $dirty = false;

                    $metaTitle = $bl['meta']['seo_title'] ?? null;
                    if (is_string($metaTitle) && trim($metaTitle) !== '' && ($row->seo_title === null || $row->seo_title === '')) {
                        $update['seo_title'] = trim($metaTitle);
                    }
                    if (isset($settings['biolink']['meta']['seo_title'])) {
                        unset($settings['biolink']['meta']['seo_title']);
                        $dirty = true;
                    }

                    $metaDesc = $bl['meta']['seo_description'] ?? null;
                    if (is_string($metaDesc) && trim($metaDesc) !== '' && ($row->seo_description === null || $row->seo_description === '')) {
                        $update['seo_description'] = trim($metaDesc);
                    }
                    if (isset($settings['biolink']['meta']['seo_description'])) {
                        unset($settings['biolink']['meta']['seo_description']);
                        $dirty = true;
                    }

                    $ogImage = $bl['og']['image_url'] ?? null;
                    if (is_string($ogImage) && trim($ogImage) !== '' && ($row->seo_image === null || $row->seo_image === '')) {
                        $update['seo_image'] = trim($ogImage);
                    }
                    if (isset($settings['biolink']['og']['image_url'])) {
                        unset($settings['biolink']['og']['image_url']);
                        $dirty = true;
                    }

                    $favUrl = $bl['favicon_url'] ?? null;
                    if (is_string($favUrl) && trim($favUrl) !== '' && ($row->favicon === null || $row->favicon === '')) {
                        $update['favicon'] = trim($favUrl);
                    }
                    if (array_key_exists('favicon_url', $settings['biolink'])) {
                        unset($settings['biolink']['favicon_url']);
                        $dirty = true;
                    }

                    if (!$dirty && empty($update)) {
                        continue;
                    }

                    $update['settings'] = json_encode($settings);
                    DB::table('links')->where('id', $row->id)->update($update);
                }
            });
    }

    public function down(): void
    {
        // No-op: backfill is a non-destructive promotion to the canonical
        // columns; reverting would only re-introduce the duplicated storage.
    }
};
