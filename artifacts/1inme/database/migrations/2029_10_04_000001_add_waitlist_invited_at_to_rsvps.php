<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Waitlist paid-tier invite idempotency (Task #6547).
 *
 * PAID tiers can't be auto-promoted, so a freed seat emails the next
 * waitlisted guest a "spot opened" purchase invite. Without a marker,
 * concurrent/repeated capacity triggers would re-email the same guest.
 * This timestamp records the last time a paid invite was sent so the
 * promotion service can skip anyone invited within the last 24h — the
 * selection + stamping happen inside the same locked transaction so
 * concurrent triggers can never double-send.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('rsvps') && !Schema::hasColumn('rsvps', 'waitlist_invited_at')) {
            Schema::table('rsvps', function (Blueprint $table) {
                $table->timestamp('waitlist_invited_at')->nullable()->after('manage_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rsvps') && Schema::hasColumn('rsvps', 'waitlist_invited_at')) {
            Schema::table('rsvps', function (Blueprint $table) {
                $table->dropColumn('waitlist_invited_at');
            });
        }
    }
};
