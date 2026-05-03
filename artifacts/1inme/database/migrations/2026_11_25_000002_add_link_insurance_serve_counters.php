<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-destination serve counters used by the Link Insurance
     * dashboard's "original vs effective destination" analytics.
     *
     * `links.insurance_primary_serve_count` counts clicks that landed
     * on the original long_url. `links.insurance_failover_serve_count`
     * counts clicks routed through ANY backup. The per-backup
     * `link_backups.serve_count` lets the UI show which backup absorbed
     * the failover traffic.
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->unsignedBigInteger('insurance_primary_serve_count')->default(0)->after('insurance_fallback_message');
            $table->unsignedBigInteger('insurance_failover_serve_count')->default(0)->after('insurance_primary_serve_count');
        });
        Schema::table('link_backups', function (Blueprint $table) {
            $table->unsignedBigInteger('serve_count')->default(0)->after('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('link_backups', function (Blueprint $table) {
            $table->dropColumn('serve_count');
        });
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn(['insurance_primary_serve_count', 'insurance_failover_serve_count']);
        });
    }
};
