<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bridge column for the followable Calendar link type (Task #2620).
 *
 * Associates a `calendar` link with the standalone Calendar collection it
 * surfaces. Nullable so every other link type leaves it untouched, and so
 * the link row survives if the calendar is later removed (nullOnDelete).
 * Additive + guarded for the shared RDS.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('links', 'calendar_id')) {
            Schema::table('links', function (Blueprint $table) {
                $table->foreignId('calendar_id')
                    ->nullable()
                    ->after('resume_id')
                    ->constrained('calendars')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('links', 'calendar_id')) {
            Schema::table('links', function (Blueprint $table) {
                // Native, idempotent FK drop (Blueprint try/catch is deferred
                // and ineffective here).
                try {
                    \Illuminate\Support\Facades\DB::statement('ALTER TABLE links DROP CONSTRAINT IF EXISTS links_calendar_id_foreign');
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('calendar_id');
            });
        }
    }
};
