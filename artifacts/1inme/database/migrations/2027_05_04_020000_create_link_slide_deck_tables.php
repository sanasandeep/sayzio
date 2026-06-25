<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('link_slide_decks')) {
            Schema::create('link_slide_decks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('link_id');
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_published')->default(false);
                $table->json('settings')->nullable();           // theme, autoAdvance, loop, default transition
                $table->json('published_snapshot')->nullable(); // frozen copy public viewers read
                $table->timestamps();

                $table->unique('link_id');
                $table->index('workspace_id');
                $table->foreign('link_id')->references('id')->on('links')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('link_slides')) {
            Schema::create('link_slides', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('deck_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('title', 160)->nullable();
                $table->json('block_ids')->nullable();   // ordered ids referencing biolink_blocks.id
                $table->json('background')->nullable();  // {type, color|gradient|image_url}
                $table->json('animation')->nullable();   // {enter, exit, duration_ms}
                $table->string('transition', 30)->default('slide');
                $table->json('settings')->nullable();    // misc per-slide knobs (text alignment, ...)
                $table->timestamps();

                $table->index(['deck_id', 'sort_order']);
                $table->foreign('deck_id')->references('id')->on('link_slide_decks')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('link_slide_view_events')) {
            Schema::create('link_slide_view_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('deck_id');
                $table->unsignedBigInteger('link_id');
                $table->unsignedInteger('slide_index');
                $table->string('page_session_id', 60)->nullable();
                $table->string('source', 20)->default('web'); // web | mobile_app
                $table->timestamp('occurred_at')->useCurrent();

                $table->index(['deck_id', 'slide_index']);
                $table->index(['link_id', 'occurred_at']);
                $table->foreign('deck_id')->references('id')->on('link_slide_decks')->cascadeOnDelete();
                $table->foreign('link_id')->references('id')->on('links')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('link_slide_view_events');
        Schema::dropIfExists('link_slides');
        Schema::dropIfExists('link_slide_decks');
    }
};
