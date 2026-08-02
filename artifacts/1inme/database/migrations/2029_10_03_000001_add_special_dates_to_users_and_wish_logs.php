<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #6551 — Special Dates on the creator profile.
 *
 * 1. users.special_dates: jsonb array of typed entries
 *    [{ id, kind, label, date (Y-m-d), public, notify, sync, calendar_event_id }]
 * 2. special_date_wish_logs: idempotency guard so the daily wish fan-out
 *    sends at most once per entry per occurrence-year even across re-runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'special_dates')) {
            Schema::table('users', function (Blueprint $table) {
                $table->jsonb('special_dates')->nullable();
            });
        }

        if (!Schema::hasTable('special_date_wish_logs')) {
            Schema::create('special_date_wish_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');           // the creator
                $table->string('entry_id', 64);                  // special-date entry id
                $table->unsignedSmallInteger('occurrence_year'); // e.g. 2026
                $table->timestamp('created_at')->nullable();

                $table->unique(['user_id', 'entry_id', 'occurrence_year'], 'sd_wish_once_per_occurrence');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('special_date_wish_logs');
        if (Schema::hasColumn('users', 'special_dates')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('special_dates');
            });
        }
    }
};
