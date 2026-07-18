<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The composer's "user" audience now accepts a multi-email/ID list
     * validated up to 5000 chars, but target_value was string(120).
     * Widen it to text so long recipient lists persist without
     * truncation errors.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('notification_broadcasts', 'target_value')) {
            return;
        }

        DB::statement('ALTER TABLE notification_broadcasts ALTER COLUMN target_value TYPE text');
    }

    public function down(): void
    {
        // Intentionally left additive-only; narrowing back to
        // varchar(120) could truncate stored targets.
    }
};
