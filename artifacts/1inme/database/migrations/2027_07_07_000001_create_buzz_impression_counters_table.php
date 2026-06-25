<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-creator monthly Buzz (social-proof) impression meter.
 *
 * One row per (user, calendar month). Tracks how many Buzz notification
 * views (impressions) the creator's widgets served in the period. Used to
 * enforce the per-plan `max_buzz_impressions` allowance: once the count
 * reaches the allowance the public widget config stops serving
 * notifications until the next month, when a fresh row is opened.
 *
 * Deliberately separate from the cumulative `impressions` column on
 * `social_proofs` (which never resets) — gating must be period-scoped.
 * Mirrors the `api_usage_counters` metering pattern.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('buzz_impression_counters')) {
            Schema::create('buzz_impression_counters', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                // Calendar month bucket, e.g. "2026-06".
                $t->string('period', 7);
                $t->unsignedBigInteger('impressions_used')->default(0);
                $t->timestamps();

                $t->unique(['user_id', 'period']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('buzz_impression_counters');
    }
};
