<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs an orphaned/partially-applied workspace migration.
 *
 * The original workspace migration (2026_05_02_000001_create_workspaces_tables)
 * is recorded as "Ran" but its `form_submissions ADD workspace_id` ALTER never
 * committed on some environments, so the table is missing the column while the
 * FormSubmission model's BelongsToWorkspace global scope filters on it — causing
 * SQLSTATE[42703] 500s. Re-running migrate won't fix it because the row is logged.
 *
 * This forward migration adds the missing column (guarded so it's a no-op where
 * already present) and backfills workspace_id from the parent form, mirroring the
 * original column definition and backfill pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('form_submissions')) return;

        if (!Schema::hasColumn('form_submissions', 'workspace_id')) {
            Schema::table('form_submissions', function (Blueprint $table) {
                $table->unsignedBigInteger('workspace_id')->nullable()->after('id');
                $table->index('workspace_id');
            });
        }

        // Backfill workspace_id from the parent form (form_submissions has no
        // direct user_id), mirroring 2026_05_02_000002_backfill_default_workspaces.
        DB::statement('UPDATE form_submissions
                       SET workspace_id = (SELECT workspace_id FROM forms WHERE forms.id = form_submissions.form_id)
                       WHERE workspace_id IS NULL');
    }

    public function down(): void
    {
        // No-op: the column is owned by the original workspace migration.
    }
};
