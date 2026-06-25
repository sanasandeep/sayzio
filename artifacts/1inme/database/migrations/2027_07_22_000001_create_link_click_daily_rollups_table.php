<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated daily rollups of link_clicks so analytics dashboards over long
 * windows don't scan tens of millions of raw rows per request. `analytics:rollup-daily`
 * finalises whole past days into these tables; read surfaces serve rollups for
 * finalised days and union the (small) raw current day on top — so the numbers
 * exactly match a full raw aggregate while bounding the scanned row count.
 *
 * Two tables:
 *  - link_click_daily            : per (link, day) totals (human + bot + unique).
 *  - link_click_daily_dimensions : per (link, day, dimension, value) breakdown
 *                                  for channel/device/country/source/referrer.
 *
 * Additive, shared-DB-safe: brand-new tables guarded by hasTable.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('link_click_daily')) {
            Schema::create('link_click_daily', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('link_id');
                $table->date('click_date');
                // Human-only clicks (is_bot = false, is_throttled = false) — mirrors
                // the LinkClick global scope so dashboard totals don't drift.
                $table->unsignedBigInteger('total_clicks')->default(0);
                $table->unsignedBigInteger('unique_visitors')->default(0);
                // Bot + throttled rows, surfaced as the "blocked attempts" badge.
                $table->unsignedBigInteger('bot_clicks')->default(0);
                $table->timestamps();

                $table->unique(['link_id', 'click_date'], 'link_click_daily_unique');
                $table->index(['link_id', 'click_date'], 'link_click_daily_link_date_idx');
                $table->index('click_date', 'link_click_daily_date_idx');
            });
        }

        if (!Schema::hasTable('link_click_daily_dimensions')) {
            Schema::create('link_click_daily_dimensions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('link_id');
                $table->date('click_date');
                // channel | device | country | source | referrer
                $table->string('dimension', 20);
                $table->string('dim_value', 191)->nullable();
                $table->unsignedBigInteger('clicks')->default(0);
                $table->timestamps();

                $table->unique(
                    ['link_id', 'click_date', 'dimension', 'dim_value'],
                    'link_click_daily_dim_unique'
                );
                $table->index(['link_id', 'dimension', 'click_date'], 'link_click_daily_dim_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('link_click_daily_dimensions');
        Schema::dropIfExists('link_click_daily');
    }
};
