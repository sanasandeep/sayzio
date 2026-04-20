<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('creator_posts', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('image');
            $table->timestamp('published_at')->nullable()->after('scheduled_at');
            $table->timestamp('pinned_at')->nullable()->after('published_at');
            $table->index(['user_id', 'pinned_at']);
            $table->index(['scheduled_at', 'published_at']);
        });

        // Backfill: existing posts are considered already published at created_at.
        \DB::statement('UPDATE creator_posts SET published_at = created_at WHERE published_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('creator_posts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'pinned_at']);
            $table->dropIndex(['scheduled_at', 'published_at']);
            $table->dropColumn(['scheduled_at', 'published_at', 'pinned_at']);
        });
    }
};
