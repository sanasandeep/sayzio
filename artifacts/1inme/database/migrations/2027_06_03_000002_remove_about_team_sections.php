<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $row = DB::table('site_pages')->where('slug', 'about')->first();
        if (!$row) {
            return;
        }

        $extra = json_decode((string) $row->extra, true);
        if (!is_array($extra)) {
            return;
        }

        $removed = ['co_founders', 'team'];

        // Drop the section payloads entirely.
        foreach ($removed as $key) {
            unset($extra[$key]);
        }

        // Drop their lower-section titles.
        if (isset($extra['section_titles']) && is_array($extra['section_titles'])) {
            unset(
                $extra['section_titles']['co_founders'],
                $extra['section_titles']['team_title'],
                $extra['section_titles']['team_subtitle'],
            );
        }

        // Drop them from the render order.
        if (isset($extra['section_order']) && is_array($extra['section_order'])) {
            $extra['section_order'] = array_values(array_filter(
                $extra['section_order'],
                fn ($slug) => !in_array($slug, $removed, true),
            ));
        }

        // Drop them from the visibility map.
        if (isset($extra['section_visibility']) && is_array($extra['section_visibility'])) {
            foreach ($removed as $key) {
                unset($extra['section_visibility'][$key]);
            }
        }

        DB::table('site_pages')
            ->where('id', $row->id)
            ->update([
                'extra'      => json_encode($extra),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No-op: removed sections are not restored.
    }
};
