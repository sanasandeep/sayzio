<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Task #6619 — scope My Calendar to the active workspace. Existing user-owned
 * calendars (including the personal "Tasks & Reminders" and "Special Dates"
 * ones) predate workspace scoping, so backfill each one into its owner's
 * Personal workspace. Delivery-project calendars already carry a workspace_id
 * and are untouched. New calendars are stamped with the active workspace at
 * creation time from now on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('calendars') || !Schema::hasColumn('calendars', 'workspace_id')) {
            return;
        }

        \App\Modules\User\Models\Calendar::backfillMissingWorkspaces();
    }

    public function down(): void
    {
        // Data backfill — nothing to reverse (calendars simply keep their
        // workspace assignment; pre-backfill NULLs are not restorable).
    }
};
