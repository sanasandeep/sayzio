<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only buffer of pending counter increments. The async click write path
 * never UPDATEs the hot `links.total_clicks` / `biolink_blocks.click_count`
 * rows directly (that serialises every concurrent visitor on a single row);
 * instead it APPENDs a delta row here (lock-free) and a single scheduled
 * `analytics:flush-counters` worker folds the accumulated deltas into the real
 * counters periodically.
 *
 * Additive, shared-DB-safe: a brand-new table guarded by hasTable.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('counter_deltas')) {
            return;
        }

        Schema::create('counter_deltas', function (Blueprint $table) {
            $table->id();
            // 'link' -> links.total_clicks/unique_clicks; 'block' -> biolink_blocks.click_count
            $table->string('entity_type', 16);
            $table->unsignedBigInteger('entity_id');
            $table->integer('total_delta')->default(0);
            $table->integer('unique_delta')->default(0);
            $table->timestamp('created_at')->nullable();

            // The flusher folds by (entity_type, entity_id); index it.
            $table->index(['entity_type', 'entity_id'], 'counter_deltas_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counter_deltas');
    }
};
