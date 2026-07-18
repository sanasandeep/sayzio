<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('browser_devices')) {
            Schema::create('browser_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('device_uuid', 64)->unique();
                $table->string('label');
                $table->string('platform', 16)->default('mac'); // mac | windows | linux
                $table->string('app_version', 32)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('browser_bookmarks')) {
            Schema::create('browser_bookmarks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('local_id', 64)->index();
                $table->text('url');
                $table->text('normalized_url');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('favicon_url')->nullable();
                $table->string('folder')->nullable();
                $table->boolean('deleted')->default(false);
                $table->timestamp('item_updated_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'local_id']);
                $table->index(['user_id', 'deleted']);
            });
        }

        if (! Schema::hasTable('browser_collections')) {
            Schema::create('browser_collections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('local_id', 64)->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('color', 16)->nullable();
                $table->string('icon', 32)->nullable();
                $table->boolean('deleted')->default(false);
                $table->timestamp('item_updated_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'local_id']);
                $table->index(['user_id', 'deleted']);
            });
        }

        if (! Schema::hasTable('browser_saved_links')) {
            Schema::create('browser_saved_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('local_id', 64)->index();
                $table->string('collection_local_id', 64)->nullable();
                $table->text('url');
                $table->text('normalized_url');
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('ai_summary')->nullable();
                $table->json('ai_tags')->nullable();
                $table->text('ai_context')->nullable();
                $table->text('notes')->nullable();
                $table->string('favicon_url')->nullable();
                $table->boolean('deleted')->default(false);
                $table->timestamp('item_updated_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'local_id']);
                $table->index(['user_id', 'deleted']);
                $table->index(['user_id', 'collection_local_id']);
            });
        }

        if (! Schema::hasTable('browser_history_sync')) {
            Schema::create('browser_history_sync', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('local_id', 64)->index();
                $table->text('url');
                $table->text('normalized_url');
                $table->string('title')->nullable();
                $table->string('favicon_url')->nullable();
                $table->unsignedInteger('visit_count')->default(1);
                $table->timestamp('last_visited_at')->nullable();
                $table->boolean('deleted')->default(false);
                $table->timestamp('item_updated_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'local_id']);
                $table->index(['user_id', 'last_visited_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('browser_history_sync');
        Schema::dropIfExists('browser_saved_links');
        Schema::dropIfExists('browser_collections');
        Schema::dropIfExists('browser_bookmarks');
        Schema::dropIfExists('browser_devices');
    }
};
