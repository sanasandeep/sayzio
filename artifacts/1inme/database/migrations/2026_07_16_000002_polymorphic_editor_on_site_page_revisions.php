<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Editors can be either admin accounts or end users, so we drop the
        // strict FK to `users` and add an editor_type discriminator.
        //
        // Idempotent on purpose: on drifted/partial schemas the constraint may
        // already be gone (or the column already added). A Blueprint-level
        // try/catch around dropForeign() does NOT work because Laravel defers
        // the SQL until the closure returns, so the exception escapes. We use
        // native "DROP CONSTRAINT IF EXISTS" + a column guard instead.
        DB::statement('ALTER TABLE site_page_revisions DROP CONSTRAINT IF EXISTS site_page_revisions_editor_id_foreign');

        if (! Schema::hasColumn('site_page_revisions', 'editor_type')) {
            Schema::table('site_page_revisions', function (Blueprint $table) {
                $table->string('editor_type', 30)->nullable()->after('editor_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('site_page_revisions', function (Blueprint $table) {
            if (Schema::hasColumn('site_page_revisions', 'editor_type')) {
                $table->dropColumn('editor_type');
            }
        });

        // Restore the strict editor_id -> users FK, but only when it can be
        // added cleanly (column present, constraint not already there) so the
        // rollback stays idempotent on drifted/partial schemas.
        if (Schema::hasColumn('site_page_revisions', 'editor_id')) {
            $hasFk = DB::selectOne(
                "select 1 from pg_constraint where conname = 'site_page_revisions_editor_id_foreign'"
            );
            if (! $hasFk) {
                Schema::table('site_page_revisions', function (Blueprint $table) {
                    $table->foreign('editor_id')->references('id')->on('users')->nullOnDelete();
                });
            }
        }
    }
};
