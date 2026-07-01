<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3302 — link a saved strategy to the named project profile it was
 * generated for. `profile_snapshot` already captures the intake values; this
 * adds the durable id so a plan can be traced back to (and re-run against)
 * its project.
 *
 * Additive / guarded / idempotent (shared-RDS merge-safe): nullable column
 * under a hasColumn guard, no FK.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketing_strategies')) {
            return;
        }

        Schema::table('marketing_strategies', function (Blueprint $table) {
            if (!Schema::hasColumn('marketing_strategies', 'profile_id')) {
                $table->unsignedBigInteger('profile_id')->nullable()->after('workspace_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketing_strategies')) {
            return;
        }

        Schema::table('marketing_strategies', function (Blueprint $table) {
            if (Schema::hasColumn('marketing_strategies', 'profile_id')) {
                $table->dropColumn('profile_id');
            }
        });
    }
};
