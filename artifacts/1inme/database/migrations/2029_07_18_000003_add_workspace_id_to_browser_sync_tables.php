<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add workspace_id to the browser sync tables so Zio Browser profiles
 * (which map 1:1 to Sayzio workspaces) can store isolated sync records
 * per workspace on the server.
 *
 * This is additive-only and backward-compatible:
 *  - workspace_id is nullable; existing rows default to NULL (= personal profile).
 *  - All unique constraints are widened to include workspace_id so the same
 *    local_id can exist independently in each profile's sync bucket.
 *  - Existing rows with workspace_id = NULL are treated by the controller as
 *    belonging to the default/personal profile.
 */
return new class extends Migration
{
    private const TABLES = [
        'browser_bookmarks',
        'browser_collections',
        'browser_saved_links',
        'browser_history_sync',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'workspace_id')) {
                    // nullable so existing rows stay valid
                    $t->unsignedBigInteger('workspace_id')->nullable()->after('user_id');
                }
            });

            // Widen the existing (user_id, local_id) unique index to
            // (user_id, workspace_id, local_id) so one local_id can
            // appear in multiple workspace profiles.
            Schema::table($table, function (Blueprint $t) use ($table) {
                // Drop the old unique constraint first (may not exist in all envs)
                try {
                    $t->dropUnique(['user_id', 'local_id']);
                } catch (\Throwable) {
                    // constraint may already be named differently or absent — ignore
                }
            });

            Schema::table($table, function (Blueprint $t) {
                $t->unique(['user_id', 'workspace_id', 'local_id']);
                $t->index('workspace_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                // Re-add the original (user_id, local_id) unique constraint
                try {
                    $t->dropUnique(['user_id', 'workspace_id', 'local_id']);
                    $t->dropIndex(['workspace_id']);
                } catch (\Throwable) {
                    // best-effort
                }

                if (Schema::hasColumn($table, 'workspace_id')) {
                    $t->dropColumn('workspace_id');
                }

                $t->unique(['user_id', 'local_id']);
            });
        }
    }
};
