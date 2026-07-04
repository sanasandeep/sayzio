<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact/lead follow-up reminders (Task #3524). Mirrors the
 * dialer_lookups.callback_at / callback_notified_at pattern: a due,
 * un-notified reminder is picked up once by the scheduled command below and
 * stamped so it is never re-sent.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'follow_up_at')) {
                $table->timestampTz('follow_up_at')->nullable()->after('last_synced_at');
            }
            if (!Schema::hasColumn('contacts', 'follow_up_note')) {
                $table->text('follow_up_note')->nullable()->after('follow_up_at');
            }
            if (!Schema::hasColumn('contacts', 'follow_up_notified_at')) {
                $table->timestampTz('follow_up_notified_at')->nullable()->after('follow_up_note');
            }
        });

        // Index for the scheduler's due-reminder scan.
        $indexExists = collect(Schema::getIndexes('contacts'))->contains('name', 'contacts_follow_up_at_index');
        if (!$indexExists) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->index('follow_up_at', 'contacts_follow_up_at_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'follow_up_at')) {
                $table->dropIndex('contacts_follow_up_at_index');
                $table->dropColumn(['follow_up_at', 'follow_up_note', 'follow_up_notified_at']);
            }
        });
    }
};
