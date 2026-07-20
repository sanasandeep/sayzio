<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a single `profile_showcase` JSONB column to `users` to store all
 * creator profile showcase configuration:
 *   - featured_link_ids  (array of up to 4 owned link IDs, ordered)
 *   - show_link_stats    (bool — whether to show click counts on featured link cards)
 *   - showcase_items     (array of {type, link_id} — opt-in curated items)
 *   - highlights         (object — per-metric visibility toggles for the strip)
 *   - cta                (object — primary + secondary contact/CTA buttons)
 *
 * All new section visibility keys (featured_links, showcase, highlights, cta)
 * are merged into the existing profile_section_visibility column at runtime
 * via User::PROFILE_DEFAULT_VISIBILITY — no separate migration needed for those.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'profile_showcase')) {
            Schema::table('users', function (Blueprint $table) {
                $table->jsonb('profile_showcase')->nullable()->after('profile_section_visibility');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'profile_showcase')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('profile_showcase');
            });
        }
    }
};
