<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3281 — Multi-step funnel suggestions.
 *
 * A suggestion can now be an ordered set of steps applied in one click
 * (e.g. create landing link → attach pixel → schedule posts). `steps`
 * holds the ordered plan; `step_results` records the per-step outcome
 * (applied / skipped / error + ref) so the UI can show which parts of the
 * funnel landed. The existing single-action types are unaffected.
 *
 * Additive / guarded / idempotent (shared-RDS merge-safe).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketing_strategy_suggestions')) {
            return;
        }
        if (!Schema::hasColumn('marketing_strategy_suggestions', 'steps')) {
            Schema::table('marketing_strategy_suggestions', function (Blueprint $table) {
                $table->json('steps')->nullable()->after('payload');
            });
        }
        if (!Schema::hasColumn('marketing_strategy_suggestions', 'step_results')) {
            Schema::table('marketing_strategy_suggestions', function (Blueprint $table) {
                $table->json('step_results')->nullable()->after('steps');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketing_strategy_suggestions')) {
            return;
        }
        foreach (['steps', 'step_results'] as $column) {
            if (Schema::hasColumn('marketing_strategy_suggestions', $column)) {
                Schema::table('marketing_strategy_suggestions', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
