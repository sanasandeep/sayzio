<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #6477 — mirror Task Board due dates and Dialer note reminders onto the
 * user's personal "Tasks & Reminders" calendar. Each source row keeps a
 * back-reference to its mirrored calendar event (same bridge pattern as
 * `delivery_project_tasks.calendar_event_id`).
 *
 * Additive + guarded: shared RDS, every step checks current state first.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('task_cards') && !Schema::hasColumn('task_cards', 'calendar_event_id')) {
            Schema::table('task_cards', function (Blueprint $t) {
                $t->unsignedBigInteger('calendar_event_id')->nullable()->index()->after('due_date');
            });
        }

        if (Schema::hasTable('dialer_notes') && !Schema::hasColumn('dialer_notes', 'calendar_event_id')) {
            Schema::table('dialer_notes', function (Blueprint $t) {
                $t->unsignedBigInteger('calendar_event_id')->nullable()->index()->after('remind_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dialer_notes') && Schema::hasColumn('dialer_notes', 'calendar_event_id')) {
            Schema::table('dialer_notes', function (Blueprint $t) {
                $t->dropColumn('calendar_event_id');
            });
        }

        if (Schema::hasTable('task_cards') && Schema::hasColumn('task_cards', 'calendar_event_id')) {
            Schema::table('task_cards', function (Blueprint $t) {
                $t->dropColumn('calendar_event_id');
            });
        }
    }
};
