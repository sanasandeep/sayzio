<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3623 — proactively alert the event owner when a paid ticket tier
 * crosses "90%+ full" or "sold out". These two nullable timestamp columns
 * make the alert idempotent (once per threshold per tier): a stamp is set
 * the first time each threshold is crossed and is only cleared when the
 * owner raises the tier's capacity (so a re-fill re-alerts).
 *
 * Fully additive/guarded: safe to re-run against a partially-migrated DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('event_ticket_tiers')) return;

        Schema::table('event_ticket_tiers', function (Blueprint $table) {
            if (!Schema::hasColumn('event_ticket_tiers', 'capacity_alerted_near_at')) {
                $table->timestamp('capacity_alerted_near_at')->nullable()->after('sold_count');
            }
            if (!Schema::hasColumn('event_ticket_tiers', 'capacity_alerted_full_at')) {
                $table->timestamp('capacity_alerted_full_at')->nullable()->after('capacity_alerted_near_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('event_ticket_tiers')) return;

        Schema::table('event_ticket_tiers', function (Blueprint $table) {
            foreach (['capacity_alerted_near_at', 'capacity_alerted_full_at'] as $col) {
                if (Schema::hasColumn('event_ticket_tiers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
