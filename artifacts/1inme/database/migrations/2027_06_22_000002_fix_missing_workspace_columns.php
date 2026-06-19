<?php

use App\Modules\Common\Support\WorkspaceColumnHealth;
use Illuminate\Database\Migrations\Migration;

/**
 * Generalises 2027_06_22_000001_fix_missing_form_submissions_workspace_id to
 * EVERY table the original workspace migration should have touched.
 *
 * The original workspace migration (2026_05_02_000001_create_workspaces_tables)
 * is recorded as "Ran" but an interrupted run can leave any of its ~20
 * `ALTER TABLE ... ADD workspace_id` / `created_by_user_id` statements
 * un-committed, while the relevant model's BelongsToWorkspace global scope still
 * filters on the column — causing SQLSTATE[42703] 500s. Re-running migrate won't
 * fix it because the row is logged.
 *
 * This forward migration adds any missing column (guarded so it's a no-op where
 * already present) and backfills it, reusing {@see WorkspaceColumnHealth::repair()}
 * — the same idempotent add+backfill the scheduled health check uses on demand.
 */
return new class extends Migration
{
    public function up(): void
    {
        WorkspaceColumnHealth::repair();
    }

    public function down(): void
    {
        // No-op: the columns are owned by the original workspace migration.
    }
};
