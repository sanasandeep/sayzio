<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-period dedup stamps for proactive API-usage warnings (task #1396).
 *
 * The MeterApiUsage middleware warns developers by email + in-app once
 * when they cross 80% of the plan's monthly included allowance, once
 * when they exhaust it (100%, now drawing on coin overage), and once
 * when overage can no longer be covered (wallet disabled / out of coins
 * → calls being rejected). Because each warning is tied to the (user,
 * period) counter row, these stamps reset naturally every month.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('api_usage_counters', function (Blueprint $t) {
            $t->timestamp('warned_80_at')->nullable()->after('prepaid_overage_remaining');
            $t->timestamp('warned_100_at')->nullable()->after('warned_80_at');
            $t->timestamp('overage_unavailable_notified_at')->nullable()->after('warned_100_at');
        });
    }

    public function down(): void
    {
        Schema::table('api_usage_counters', function (Blueprint $t) {
            $t->dropColumn(['warned_80_at', 'warned_100_at', 'overage_unavailable_notified_at']);
        });
    }
};
