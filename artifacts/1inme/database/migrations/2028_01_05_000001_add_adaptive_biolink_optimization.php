<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3531 — Adaptive Biolink. Purely additive:
 *  - `mode` on biolink_experiments distinguishes the existing manual A/B
 *    flow ('ab', the default so every historical row keeps behaving
 *    exactly as before) from the new continuous 'adaptive' optimizer.
 *  - biolink_adaptive_arms holds per-segment bandit-arm stats. An "arm"
 *    is a candidate featured block (or NULL = the creator's default
 *    order) for one visitor segment within one adaptive experiment.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('biolink_experiments', 'mode')) {
            Schema::table('biolink_experiments', function (Blueprint $table) {
                $table->string('mode', 20)->default('ab')->after('link_id');
            });
        }

        if (!Schema::hasTable('biolink_adaptive_arms')) {
            Schema::create('biolink_adaptive_arms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('biolink_experiment_id')->constrained('biolink_experiments')->cascadeOnDelete();

                // Segment key produced by AdaptiveSegmentResolver, e.g.
                // "mobile|ios|northamerica|social|evening|returning".
                $table->string('segment', 191);

                // NULL = baseline arm (creator's manual block order, nothing
                // featured). Otherwise the id of the block moved to the top
                // of the page for this arm. Intentionally has no FK — the
                // referenced block may later be deleted by the creator, in
                // which case the arm is skipped at render time and pruned
                // by the housekeeping sweep.
                $table->unsignedBigInteger('featured_block_id')->nullable();

                $table->unsignedInteger('impressions')->default(0);
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('conversions')->default(0);

                $table->timestamps();

                $table->unique(
                    ['biolink_experiment_id', 'segment', 'featured_block_id'],
                    'biolink_adaptive_arms_unique'
                );
                $table->index(['biolink_experiment_id', 'segment']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('biolink_adaptive_arms');

        if (Schema::hasColumn('biolink_experiments', 'mode')) {
            Schema::table('biolink_experiments', function (Blueprint $table) {
                $table->dropColumn('mode');
            });
        }
    }
};
