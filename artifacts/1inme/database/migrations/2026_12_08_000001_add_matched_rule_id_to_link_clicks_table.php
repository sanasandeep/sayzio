<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('link_clicks')) return;
        if (Schema::hasColumn('link_clicks', 'matched_rule_id')) return;
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->string('matched_rule_id', 32)->nullable()->after('utm_params');
            $table->index(['link_id', 'matched_rule_id']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('link_clicks')) return;
        if (!Schema::hasColumn('link_clicks', 'matched_rule_id')) return;
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->dropIndex(['link_id', 'matched_rule_id']);
            $table->dropColumn('matched_rule_id');
        });
    }
};
