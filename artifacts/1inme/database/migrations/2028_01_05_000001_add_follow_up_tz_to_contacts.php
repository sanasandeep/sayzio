<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-reminder timezone override for contact follow-ups (Task #3526).
 * `follow_up_at` is still stored as an absolute UTC timestamptz; this column
 * only records which timezone the owner picked when setting the reminder, so
 * the wall-clock time is displayed/prefilled in that same zone instead of
 * always falling back to the account-level timezone. Nullable: existing
 * reminders (and any set without an override) fall back to the account tz.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'follow_up_tz')) {
                $table->string('follow_up_tz')->nullable()->after('follow_up_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'follow_up_tz')) {
                $table->dropColumn('follow_up_tz');
            }
        });
    }
};
