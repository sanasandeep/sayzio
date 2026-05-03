<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversation_sessions', function (Blueprint $table) {
            // Snapshot the flow version that was live when the session
            // started so mid-flight visitors keep talking to the version
            // they began with — even if the creator publishes edits.
            $table->unsignedInteger('flow_version')->nullable()->after('public_id');
            // Frozen copy of the flow graph (steps + choices + actions)
            // captured at session start. The answer endpoint resolves
            // steps from this snapshot so deletes/renames on the live
            // tables can never strand an in-flight visitor.
            $table->json('flow_snapshot')->nullable()->after('flow_version');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_sessions', function (Blueprint $table) {
            $table->dropColumn(['flow_version', 'flow_snapshot']);
        });
    }
};
