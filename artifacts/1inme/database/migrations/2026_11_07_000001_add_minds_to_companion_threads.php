<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-thread Mind grounding for Companion. Mirrors the Persona / Coach
 * forms: an array of own-Mind ids the user wants to ground replies in,
 * plus a separate opt-in toggle for the platform default Mind. Set at
 * thread creation time and reused on every turn so the chat stays
 * grounded for the life of the conversation.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('companion_threads', function (Blueprint $table) {
            $table->json('mind_ids')->nullable()->after('title');
            $table->boolean('include_platform')->default(false)->after('mind_ids');
        });
    }

    public function down(): void
    {
        Schema::table('companion_threads', function (Blueprint $table) {
            $table->dropColumn(['mind_ids', 'include_platform']);
        });
    }
};
