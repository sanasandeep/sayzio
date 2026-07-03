<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'is_readonly_demo')) {
            Schema::table('users', function (Blueprint $table) {
                // Task #3498 — marks a showcase/demo account as read-only:
                // it renders every editor/settings surface normally, but a
                // global write-guard middleware short-circuits any
                // state-changing request from an account with this flag
                // set before any persistence happens. Keyed on this flag
                // (not a hardcoded email) so the behavior stays scoped to
                // accounts explicitly marked, not baked into one address.
                $table->boolean('is_readonly_demo')->default(false)->after('is_demo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_readonly_demo')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_readonly_demo');
            });
        }
    }
};
