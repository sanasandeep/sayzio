<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot tracking for the 24h-before-grace-ends reminder email, so
 * the hourly renew-due cron doesn't spam the user every hour once the
 * grace window is inside the next day.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('grace_ending_notified_at')->nullable()->after('grace_until');
        });
    }
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('grace_ending_notified_at');
        });
    }
};
