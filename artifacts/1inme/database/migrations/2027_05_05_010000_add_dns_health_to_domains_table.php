<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('dns_status', 32)->nullable()->after('verified_at');
            $table->timestamp('dns_last_checked_at')->nullable()->after('dns_status');
            $table->string('dns_last_target')->nullable()->after('dns_last_checked_at');
            $table->timestamp('dns_drift_started_at')->nullable()->after('dns_last_target');
            $table->timestamp('dns_drift_notified_at')->nullable()->after('dns_drift_started_at');
            $table->timestamp('dns_unverified_warning_sent_at')->nullable()->after('dns_drift_notified_at');

            $table->index(['is_verified', 'dns_last_checked_at'], 'domains_verified_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex('domains_verified_checked_idx');
            $table->dropColumn([
                'dns_status',
                'dns_last_checked_at',
                'dns_last_target',
                'dns_drift_started_at',
                'dns_drift_notified_at',
                'dns_unverified_warning_sent_at',
            ]);
        });
    }
};
