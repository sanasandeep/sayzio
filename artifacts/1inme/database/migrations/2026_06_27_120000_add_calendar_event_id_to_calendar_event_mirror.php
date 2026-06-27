<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the calendar_event_mirror table (originally keyed only to `ics`
 * Event-Invite Links) so it can also mirror a followable Calendar's
 * {@see \App\Modules\User\Models\CalendarEvent} rows to Google. Additive +
 * idempotent for the shared RDS: adds a nullable calendar_event_id and relaxes
 * link_id to nullable so a mirror row points at exactly one of the two.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('calendar_event_mirror')) {
            return;
        }

        if (!Schema::hasColumn('calendar_event_mirror', 'calendar_event_id')) {
            Schema::table('calendar_event_mirror', function (Blueprint $table) {
                $table->unsignedBigInteger('calendar_event_id')->nullable()->after('link_id');
                $table->index('calendar_event_id', 'cem_calendar_event_idx');
            });
        }

        // Relax the original NOT NULL on link_id so calendar-event mirrors can
        // omit it. DROP NOT NULL is non-destructive and reversible.
        try {
            DB::statement('ALTER TABLE calendar_event_mirror ALTER COLUMN link_id DROP NOT NULL');
        } catch (\Throwable $e) {
            // Already nullable, or a driver that doesn't support it — safe to ignore.
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('calendar_event_mirror')) {
            return;
        }
        if (Schema::hasColumn('calendar_event_mirror', 'calendar_event_id')) {
            Schema::table('calendar_event_mirror', function (Blueprint $table) {
                $table->dropIndex('cem_calendar_event_idx');
                $table->dropColumn('calendar_event_id');
            });
        }
    }
};
