<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Insider feed posts scoped per biolink. The Insider block on a link
        // reads from this table and is gated by community_members.
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('biolink_blocks')->nullOnDelete();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->string('media_type', 16)->nullable(); // image|video|null
            $table->string('media_url', 1024)->nullable();
            $table->string('access', 16)->default('members'); // public|members|paid|followers
            $table->string('status', 16)->default('draft'); // draft|scheduled|published|archived
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('reactions_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['link_id', 'status', 'published_at']);
            $table->index(['user_id', 'status']);
        });

        // Member roster for the Insider block on a given link. A member is
        // a viewer (subscriber-style) who unlocked the gated feed either
        // through free signup or a paid subscription tier.
        Schema::create('community_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // creator
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('biolink_blocks')->nullOnDelete();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->foreignId('subscriber_id')->nullable()->constrained('subscribers')->nullOnDelete();
            $table->unsignedBigInteger('viewer_user_id')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('display_name')->nullable();
            $table->string('tier', 32)->default('free'); // free|paid
            $table->string('status', 16)->default('active'); // active|cancelled|banned
            $table->string('stripe_subscription_id')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->json('preferences')->nullable(); // e.g. notify_email, notify_inapp
            $table->timestamps();

            $table->unique(['link_id', 'email'], 'community_members_link_email_unique');
            $table->index(['link_id', 'status']);
        });

        // Comments attached to any biolink block.
        Schema::create('block_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('biolink_blocks')->cascadeOnDelete();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->foreignId('parent_id')->nullable()->constrained('block_comments')->nullOnDelete();
            // Optional: when set, the comment belongs to a specific Insider
            // feed post (one of the posts inside the gated feed) so each
            // post has its own comment thread.
            $table->foreignId('post_id')->nullable()->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // creator if replying as themselves
            $table->unsignedBigInteger('viewer_user_id')->nullable()->index();
            $table->foreignId('member_id')->nullable()->constrained('community_members')->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->text('body');
            $table->string('status', 16)->default('visible'); // visible|hidden|spam|deleted
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false); // lock thread = no replies
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['block_id', 'status', 'created_at']);
            $table->index(['link_id', 'status']);
        });

        // Emoji reactions on a block (optionally on a comment via parent_comment_id).
        Schema::create('block_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('biolink_blocks')->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('block_comments')->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('community_posts')->cascadeOnDelete();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->unsignedBigInteger('viewer_user_id')->nullable()->index();
            $table->string('voter_fingerprint', 64)->nullable();
            $table->string('emoji', 16); // 👍 ❤️ 😂 🔥 🎉 …
            $table->timestamps();

            $table->unique(
                ['block_id', 'comment_id', 'post_id', 'voter_fingerprint', 'emoji'],
                'block_reactions_unique'
            );
            $table->index(['block_id', 'emoji']);
        });

        // Standalone polls table per task — distinct from the legacy
        // poll_votes used by the simple `poll` block — used by the Insider
        // feed and any block that opts in to the new polls UI.
        Schema::create('block_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('biolink_blocks')->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('community_posts')->cascadeOnDelete();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->string('question', 500);
            $table->json('options'); // [{label, votes}]
            $table->string('visibility', 16)->default('public'); // public|members|followers
            $table->boolean('multi_select')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();

            $table->index(['block_id', 'is_closed']);
        });

        Schema::create('block_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('block_polls')->cascadeOnDelete();
            $table->unsignedSmallInteger('option_index');
            $table->unsignedBigInteger('viewer_user_id')->nullable()->index();
            $table->string('voter_fingerprint', 64)->nullable();
            $table->timestamps();

            $table->unique(['poll_id', 'voter_fingerprint', 'option_index'], 'block_poll_votes_unique');
        });

        // Points ledger for the fan leaderboard. Append-only; the leaderboard
        // is rebuilt by aggregating this ledger.
        Schema::create('fan_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // creator
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->unsignedBigInteger('viewer_user_id')->nullable();
            $table->string('voter_fingerprint', 64)->nullable();
            $table->string('display_name')->nullable();
            $table->string('action', 32); // share|click|comment|reaction|referral|signup|post
            $table->integer('points');
            $table->morphs('subject'); // subject_id, subject_type — block, comment, etc.
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['link_id', 'viewer_user_id']);
            $table->index(['link_id', 'voter_fingerprint']);
            $table->index(['link_id', 'action', 'created_at']);
        });

        // Per-biolink leaderboard configuration (rules, visible perks).
        Schema::create('fan_leaderboard_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete()->unique();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('show_anonymous_option')->default(true);
            $table->json('point_rules')->nullable(); // { share:5, click:1, comment:3, reaction:1, referral:25 }
            $table->json('perks')->nullable(); // [{ rank:1, label:'VIP DM access' }, ...]
            $table->unsignedSmallInteger('top_n')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fan_leaderboard_settings');
        Schema::dropIfExists('fan_points');
        Schema::dropIfExists('block_poll_votes');
        Schema::dropIfExists('block_polls');
        Schema::dropIfExists('block_reactions');
        Schema::dropIfExists('block_comments');
        Schema::dropIfExists('community_members');
        Schema::dropIfExists('community_posts');
    }
};
