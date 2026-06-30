<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the last time the renew-due cron RE-ATTEMPTED a recurring charge
 * for a subscription that is already in its grace window (past_due/grace).
 *
 * Without this stamp the hourly cron would either never retry a transient
 * decline (so a card fixed mid-grace would still expire the account) or
 * hammer a declining card every hour. The stamp throttles retries to ~once
 * per day while still guaranteeing at least one attempt before grace ends.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('subscriptions', 'renewal_retry_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->timestamp('renewal_retry_at')->nullable()->after('grace_ending_notified_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscriptions', 'renewal_retry_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('renewal_retry_at');
            });
        }
    }
};
