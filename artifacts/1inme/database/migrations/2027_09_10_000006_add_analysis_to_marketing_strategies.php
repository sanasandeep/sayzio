<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3281 — Deepen the Marketing Strategist.
 *
 * Adds the analysis surfaces the deepened strategist persists alongside
 * the plan: a snapshot of the Marketing Profile used, a grounded
 * performance diagnosis, a 0-100 scorecard, a three-band forecast, an
 * optional competitor comparison, a baseline metric snapshot (for outcome
 * tracking), the resolved goal metric, a measured outcome, and a public
 * share token for the shareable web report.
 *
 * Every column is added independently + guarded so a partially-applied
 * run is safe to re-run. Additive / idempotent (shared-RDS merge-safe).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketing_strategies')) {
            return;
        }

        $add = function (string $column, callable $definition): void {
            if (Schema::hasColumn('marketing_strategies', $column)) {
                return;
            }
            Schema::table('marketing_strategies', function (Blueprint $table) use ($definition) {
                $definition($table);
            });
        };

        $add('profile_snapshot',    fn (Blueprint $t) => $t->json('profile_snapshot')->nullable()->after('parameters'));
        $add('diagnosis',           fn (Blueprint $t) => $t->json('diagnosis')->nullable()->after('context_snapshot'));
        $add('scorecard',           fn (Blueprint $t) => $t->json('scorecard')->nullable()->after('diagnosis'));
        $add('forecast',            fn (Blueprint $t) => $t->json('forecast')->nullable()->after('scorecard'));
        $add('competitor_analysis', fn (Blueprint $t) => $t->json('competitor_analysis')->nullable()->after('forecast'));
        $add('baseline',            fn (Blueprint $t) => $t->json('baseline')->nullable()->after('competitor_analysis'));
        $add('outcome',             fn (Blueprint $t) => $t->json('outcome')->nullable()->after('baseline'));
        $add('goal_metric',         fn (Blueprint $t) => $t->string('goal_metric', 60)->nullable()->after('outcome'));
        $add('share_token',         fn (Blueprint $t) => $t->string('share_token', 64)->nullable()->after('goal_metric'));

        // Unique index on the share token so a public /ai-report/{token}
        // lookup resolves exactly one strategy. Guarded so re-runs are safe.
        if (Schema::hasColumn('marketing_strategies', 'share_token')
            && !$this->indexExists('marketing_strategies', 'marketing_strategies_share_token_uq')) {
            Schema::table('marketing_strategies', function (Blueprint $table) {
                $table->unique('share_token', 'marketing_strategies_share_token_uq');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketing_strategies')) {
            return;
        }
        if ($this->indexExists('marketing_strategies', 'marketing_strategies_share_token_uq')) {
            Schema::table('marketing_strategies', function (Blueprint $table) {
                $table->dropUnique('marketing_strategies_share_token_uq');
            });
        }
        foreach ([
            'profile_snapshot', 'diagnosis', 'scorecard', 'forecast',
            'competitor_analysis', 'baseline', 'outcome', 'goal_metric', 'share_token',
        ] as $column) {
            if (Schema::hasColumn('marketing_strategies', $column)) {
                Schema::table('marketing_strategies', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $rows = \Illuminate\Support\Facades\DB::select(
                'select 1 from pg_indexes where tablename = ? and indexname = ?',
                [$table, $index]
            );
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
