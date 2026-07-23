<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Google Custom Search (AI builder image search) usage counters.
 * One row per (day, user); user_id = 0 is the platform-wide daily total.
 * Powers the admin Integrations usage readout and the optional
 * per-user daily cap.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('google_cse_usage_counters')) {
            Schema::create('google_cse_usage_counters', function (Blueprint $table) {
                $table->id();
                $table->date('day');
                $table->unsignedBigInteger('user_id')->default(0); // 0 = platform total
                $table->unsignedInteger('queries')->default(0);
                $table->timestamps();

                $table->unique(['day', 'user_id']);
                $table->index('day');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_cse_usage_counters');
    }
};
