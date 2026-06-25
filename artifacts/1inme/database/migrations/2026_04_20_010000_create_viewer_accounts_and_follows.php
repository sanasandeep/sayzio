<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'bio')) $table->text('bio')->nullable();
            if (!Schema::hasColumn('users', 'handle')) $table->string('handle', 60)->nullable()->unique();
            if (!Schema::hasColumn('users', 'discoverable')) $table->boolean('discoverable')->default(true);
            if (!Schema::hasColumn('users', 'notify_new_follower')) $table->boolean('notify_new_follower')->default(true);
            if (!Schema::hasColumn('users', 'notify_follower_updates')) $table->boolean('notify_follower_updates')->default(true);
            if (!Schema::hasColumn('users', 'followers_count')) $table->unsignedInteger('followers_count')->default(0);
            if (!Schema::hasColumn('users', 'avatar')) $table->string('avatar')->nullable();
            if (!Schema::hasColumn('users', 'allow_followers')) $table->boolean('allow_followers')->default(true);
        });

        if (!Schema::hasTable('follows')) {
            Schema::create('follows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['follower_id', 'creator_id']);
                $table->index(['creator_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('creator_posts')) {
            Schema::create('creator_posts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('title')->nullable();
                $table->text('body');
                $table->string('image')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('user_notifications')) {
            Schema::create('user_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 60);
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['user_id', 'read_at', 'created_at']);
            });
        }

        if (!Schema::hasTable('feed_events')) {
            Schema::create('feed_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 60); // post|publish|new_block|profile_update
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('subject_type', 60)->nullable();
                $table->json('data')->nullable();
                $table->timestamp('occurred_at')->useCurrent();
                $table->index(['user_id', 'occurred_at']);
                $table->index('occurred_at');
            });
        }

        Schema::table('link_clicks', function (Blueprint $table) {
            if (!Schema::hasColumn('link_clicks', 'viewer_user_id')) {
                $table->foreignId('viewer_user_id')->nullable()->after('alias')->constrained('users')->nullOnDelete();
                $table->index(['link_id', 'viewer_user_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('link_clicks', function (Blueprint $table) {
            if (Schema::hasColumn('link_clicks', 'viewer_user_id')) {
                $table->dropForeign(['viewer_user_id']);
                $table->dropColumn('viewer_user_id');
            }
        });
        Schema::dropIfExists('feed_events');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('creator_posts');
        Schema::dropIfExists('follows');
        Schema::table('users', function (Blueprint $table) {
            foreach (['bio','handle','discoverable','notify_new_follower','notify_follower_updates','followers_count'] as $c) {
                if (Schema::hasColumn('users', $c)) $table->dropColumn($c);
            }
        });
    }
};
