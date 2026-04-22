<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log of changes to the `ai.feature_models` admin setting.
 *
 * One row per (feature, change) so cost regressions can be traced back
 * to the exact admin and timestamp that flipped a feature onto a more
 * expensive model. Old/new model are stored verbatim — even if the
 * model later disappears from the models table, the history is intact.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_feature_model_changes', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 32);
            $table->string('old_model', 64)->nullable();
            $table->string('new_model', 64)->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_name', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['feature', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feature_model_changes');
    }
};
