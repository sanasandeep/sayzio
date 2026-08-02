<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Task #6477 — one-time backfill: mirror existing task-card due dates and
 * future dialer-note reminders onto each owner's personal "Tasks & Reminders"
 * calendar. Chunked; idempotent (only rows with a NULL calendar_event_id are
 * touched, and the sync helper skips users who opted out).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('calendars')
            || !Schema::hasColumn('task_cards', 'calendar_event_id')
            || !Schema::hasColumn('dialer_notes', 'calendar_event_id')) {
            return;
        }

        \App\Modules\User\Support\PersonalCalendarSync::backfillTaskCards();
        \App\Modules\User\Support\PersonalCalendarSync::backfillDialerNotes();
    }

    public function down(): void
    {
        // Data-only backfill; the mirrored events are cleaned up by the schema
        // migration's rollback (calendar_event_id column drop) plus normal
        // event deletion — nothing structural to undo here.
    }
};
