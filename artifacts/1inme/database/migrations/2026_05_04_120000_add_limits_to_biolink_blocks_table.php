<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #1094 — Per-block scarcity (countdowns + remaining-count badges).
 *
 * `max_clicks` is the global click cap (nullable = no cap). `click_count`
 * is the running tally bumped atomically by LinkTrackingService so we
 * don't have to query LinkClick on every render. Time-based expiry
 * piggybacks on the existing `end_date` column — no second column needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biolink_blocks', function (Blueprint $table) {
            $table->unsignedInteger('max_clicks')->nullable()->after('end_date');
            $table->unsignedInteger('click_count')->default(0)->after('max_clicks');
        });
    }

    public function down(): void
    {
        Schema::table('biolink_blocks', function (Blueprint $table) {
            $table->dropColumn(['max_clicks', 'click_count']);
        });
    }
};
