<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_members', function (Blueprint $table) {
            if (!Schema::hasColumn('workspace_members', 'last_active_at')) {
                $table->timestamp('last_active_at')->nullable()->after('permissions');
            }
            if (!Schema::hasColumn('workspace_members', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('last_active_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workspace_members', function (Blueprint $table) {
            if (Schema::hasColumn('workspace_members', 'suspended_at')) {
                $table->dropColumn('suspended_at');
            }
            if (Schema::hasColumn('workspace_members', 'last_active_at')) {
                $table->dropColumn('last_active_at');
            }
        });
    }
};
