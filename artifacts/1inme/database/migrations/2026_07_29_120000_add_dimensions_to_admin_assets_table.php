<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('admin_assets', 'width')) {
            Schema::table('admin_assets', function (Blueprint $table) {
                $table->unsignedInteger('width')->nullable()->after('type');
            });
        }
        if (!Schema::hasColumn('admin_assets', 'height')) {
            Schema::table('admin_assets', function (Blueprint $table) {
                $table->unsignedInteger('height')->nullable()->after('width');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('admin_assets', 'height')) {
            Schema::table('admin_assets', function (Blueprint $table) {
                $table->dropColumn('height');
            });
        }
        if (Schema::hasColumn('admin_assets', 'width')) {
            Schema::table('admin_assets', function (Blueprint $table) {
                $table->dropColumn('width');
            });
        }
    }
};
