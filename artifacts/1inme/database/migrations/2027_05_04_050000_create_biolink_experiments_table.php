<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('biolink_experiments')) {
            Schema::create('biolink_experiments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();

                // Frozen-at-start snapshot of the variant A block tree.
                // Each entry: {id,type,settings,sort_order,is_active,parent_id,
                // children:[…recursive…]}. We keep IDs from the moment the
                // experiment started so per-block click rows still have a stable
                // identifier even after the live blocks (= variant B) drift.
                $table->json('variant_a_snapshot');
                // Variant B starts as a clone of A. The creator edits the live
                // blocks_table; on every save we mirror the new state into this
                // column so the public renderer doesn't need to query both
                // sources at request-time.
                $table->json('variant_b_snapshot');

                // running | stopped | completed (winner promoted)
                $table->string('status', 20)->default('running');
                // a | b | null (null until decided)
                $table->string('winner', 1)->nullable();
                // manual | sample_size | end_date
                $table->string('stop_condition', 20)->default('manual');
                $table->unsignedInteger('stop_sample_size')->nullable();
                $table->timestamp('stop_end_date')->nullable();

                // Per-variant counters. Updated synchronously on each
                // exposure / click / conversion so the results panel
                // never has to scan link_clicks.
                $table->unsignedInteger('variant_a_visits')->default(0);
                $table->unsignedInteger('variant_a_clicks')->default(0);
                $table->unsignedInteger('variant_a_conversions')->default(0);
                $table->unsignedInteger('variant_b_visits')->default(0);
                $table->unsignedInteger('variant_b_clicks')->default(0);
                $table->unsignedInteger('variant_b_conversions')->default(0);

                $table->timestamp('started_at')->nullable();
                $table->timestamp('stopped_at')->nullable();
                $table->timestamp('promoted_at')->nullable();
                $table->timestamps();

                $table->index(['link_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('biolink_experiments');
    }
};
