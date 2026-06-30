<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-period dedup marker for the fan→creator "your subscription renews
 * soon" reminder (Task #3011). Stamped when a reminder is sent; the daily
 * command only sends again once this stamp is null or predates the current
 * billing period start, so each billing period gets at most one reminder
 * and the next period naturally re-arms.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('creator_subscriptions', 'renewal_reminder_sent_at')) {
            Schema::table('creator_subscriptions', function (Blueprint $table) {
                $table->timestamp('renewal_reminder_sent_at')->nullable()->after('last_payment_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('creator_subscriptions', 'renewal_reminder_sent_at')) {
            Schema::table('creator_subscriptions', function (Blueprint $table) {
                $table->dropColumn('renewal_reminder_sent_at');
            });
        }
    }
};
