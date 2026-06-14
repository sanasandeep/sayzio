<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user monthly API-call meter (task #1393).
 *
 * One row per (user, calendar month). Tracks how many metered API-key
 * calls were served, how many of those exceeded the plan's included
 * monthly allowance (overage), how many coins were spent buying overage
 * blocks, and how many prepaid overage calls remain (coins are debited
 * in blocks of `api_overage_calls_per_coin`, so a partial block is
 * carried forward here instead of being lost).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_usage_counters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Calendar month bucket, e.g. "2026-06".
            $t->string('period', 7);
            $t->unsignedBigInteger('calls_used')->default(0);
            $t->unsignedBigInteger('overage_calls')->default(0);
            $t->unsignedBigInteger('coins_spent')->default(0);
            $t->unsignedBigInteger('prepaid_overage_remaining')->default(0);
            $t->timestamps();

            $t->unique(['user_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_counters');
    }
};
