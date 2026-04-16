<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('page_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('link_id')->constrained()->cascadeOnDelete();
            $t->string('session_id', 40)->unique();
            $t->string('ip_address', 45)->nullable();
            $t->string('country_code', 2)->nullable();
            $t->string('city', 100)->nullable();
            $t->string('browser', 30)->nullable();
            $t->string('os', 30)->nullable();
            $t->string('device_type', 20)->nullable();
            $t->string('referrer', 1024)->nullable();
            $t->string('language', 8)->nullable();
            $t->timestamp('started_at');
            $t->timestamp('last_seen_at');
            $t->integer('duration_seconds')->default(0);
            $t->boolean('ended')->default(false);
            $t->timestamps();
            $t->index(['link_id', 'started_at']);
            $t->index(['link_id', 'ended']);
        });

        Schema::create('block_views', function (Blueprint $t) {
            $t->id();
            $t->foreignId('link_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('block_id');
            $t->string('block_type', 60)->nullable();
            $t->string('session_id', 40);
            $t->integer('view_duration_ms')->default(0);
            $t->integer('impression_count')->default(1);
            $t->timestamp('first_viewed_at');
            $t->timestamp('last_viewed_at');
            $t->timestamps();
            $t->index(['link_id', 'block_id']);
            $t->index(['session_id']);
            $t->unique(['session_id', 'block_id'], 'block_views_session_block_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_views');
        Schema::dropIfExists('page_sessions');
    }
};
