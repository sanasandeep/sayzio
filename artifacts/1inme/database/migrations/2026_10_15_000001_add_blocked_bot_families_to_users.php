<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Per-user list of bot family display names (e.g. "GPTBot (OpenAI)",
            // "AhrefsBot") that the creator has chosen to drop entirely from
            // tracking. Hits matching one of these families are not recorded
            // in link_clicks at all — they don't even show up in the "bot
            // hits filtered" badge afterwards. Stored as a JSON array of
            // strings; empty array == respect only the global BotDetector
            // exclusions.
            $table->json('blocked_bot_families')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('blocked_bot_families');
        });
    }
};
