<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'api_usage_warning_threshold')) {
                $table->unsignedTinyInteger('api_usage_warning_threshold')
                    ->default(80)
                    ->after('backlink_digest_preferred_hour');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'api_usage_warning_threshold')) {
                $table->dropColumn('api_usage_warning_threshold');
            }
        });
    }
};
