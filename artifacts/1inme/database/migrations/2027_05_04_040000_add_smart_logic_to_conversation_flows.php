<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Per-choice settings — primarily branching `conditions` (when
        // selected, optional rules can override `next_step_key`). Stored
        // as JSON so creator-defined logic is forward-compatible without
        // further migrations.
        Schema::table('conversation_choices', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('action_id');
        });

        // Index for the new step-event kinds we'll log
        // (`validation_failed`, `ai_classified`) so analytics aggregation
        // stays cheap as event volume grows.
        Schema::table('conversation_step_events', function (Blueprint $table) {
            // `event` already has an index via composite; add a dedicated
            // step-key+event index for histogram queries.
            $table->index(['flow_id', 'event'], 'cse_flow_event_idx');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_choices', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
        Schema::table('conversation_step_events', function (Blueprint $table) {
            $table->dropIndex('cse_flow_event_idx');
        });
    }
};
