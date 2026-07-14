<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Document the accepted max length of target_value directly on the
     * column. The column type is text (widened from varchar(120)), but
     * the application validates the composer input at max 5000 chars —
     * this comment keeps that contract discoverable at the DB level.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('notification_broadcasts', 'target_value')) {
            return;
        }

        DB::statement(
            "COMMENT ON COLUMN notification_broadcasts.target_value IS "
            . "'Broadcast target list (emails/IDs, comma or newline delimited). "
            . "Application-enforced max length: 5000 chars (see Admin NotificationController validation).'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasColumn('notification_broadcasts', 'target_value')) {
            return;
        }

        DB::statement('COMMENT ON COLUMN notification_broadcasts.target_value IS NULL');
    }
};
