<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable `source` column to creator_tips so tips started from
 * a Tip Jar biolink block ('tip_jar') can be distinguished from profile
 * / post / DM tips (null → 'tip') in the earnings breakdown and on
 * refund ledger rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('creator_tips') && !Schema::hasColumn('creator_tips', 'source')) {
            Schema::table('creator_tips', function (Blueprint $table) {
                $table->string('source', 32)->nullable()->after('gateway_charge_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('creator_tips') && Schema::hasColumn('creator_tips', 'source')) {
            Schema::table('creator_tips', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
