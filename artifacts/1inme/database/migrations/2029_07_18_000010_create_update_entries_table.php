<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creator Updates / Changelog page — update_entries table.
 *
 * Each row is one entry (announcement / changelog item) on an Updates-type
 * link page. Entries belong to a link (the Updates page) and to a user
 * (the creator). Draft entries are only visible to the owner; published
 * entries appear on the public page newest-first.
 *
 * Follower notification deduplication: `notified_at` is stamped the first
 * time a published entry triggers a follower fan-out, preventing re-
 * notification if the entry is later edited or toggled draft→published again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('link_id')
                ->constrained('links')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title', 255);

            // Rich-text body stored as HTML (sanitized on save). Nullable so
            // a creator can save a draft with only a title.
            $table->text('body')->nullable();

            // Optional cover / thumbnail image URL (user-uploaded).
            $table->string('image', 2048)->nullable();

            // Optional tag like "New", "Fix", "Announcement", "Breaking", etc.
            $table->string('tag', 60)->nullable();

            // The display date (can differ from created_at so creators can
            // backdate or forward-date entries). Defaults to creation time.
            $table->date('published_date');

            // draft = hidden from public; published = visible on the page.
            $table->enum('status', ['draft', 'published'])->default('draft');

            // Stamped the first time this entry triggers a follower fan-out so
            // the notification fires exactly once per entry regardless of
            // subsequent edits or status toggles.
            $table->timestamp('notified_at')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['link_id', 'status', 'published_date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_entries');
    }
};
