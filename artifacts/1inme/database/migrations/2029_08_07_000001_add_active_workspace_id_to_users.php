<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the user's active workspace so the stateless Sanctum API (mobile
 * app) resolves the SAME workspace the web session is using, instead of
 * silently falling back to "first accessible". Fixes web/app links parity
 * (Task: mobile Links tab showed a different subset than the web list).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'active_workspace_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('active_workspace_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'active_workspace_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('active_workspace_id');
            });
        }
    }
};
