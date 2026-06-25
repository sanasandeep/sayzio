<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #1211 — Discovery, Stats Home, Watermarking, Country Gating
 * and Moderation. Bundles all the schema for these areas in one file
 * so the relationships between them stay visible in a single review.
 *
 * - users:           per-creator preferences for mute words, watermark
 *                    overlays, country gating, DMCA contact, and the
 *                    "last weekly creator digest sent at" cursor.
 * - creator_posts:   per-post country gating overrides + a cached
 *                    7-day view counter for the trending sort.
 * - user_blocks:     viewer→creator block list (hides the blocked
 *                    creator from feeds, directory, and DMs).
 * - user_reports:    moderation queue for "report this creator /
 *                    comment / message" filed by signed-in viewers.
 * - dmca_takedowns:  public DMCA takedown intake.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'mute_words')) {
                $table->json('mute_words')->nullable();
            }
            if (!Schema::hasColumn('users', 'watermark_settings')) {
                // {enabled:bool, opacity:int 10..90, position:tl|tr|bl|br|center,
                //  text_template:'@{handle} • viewed by {viewer}'}
                $table->json('watermark_settings')->nullable();
            }
            if (!Schema::hasColumn('users', 'country_block_list')) {
                $table->json('country_block_list')->nullable();
            }
            if (!Schema::hasColumn('users', 'country_allow_list')) {
                $table->json('country_allow_list')->nullable();
            }
            if (!Schema::hasColumn('users', 'dmca_email')) {
                $table->string('dmca_email', 255)->nullable();
            }
            if (!Schema::hasColumn('users', 'creator_digest_last_sent_at')) {
                $table->timestamp('creator_digest_last_sent_at')->nullable();
            }
        });

        Schema::table('creator_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('creator_posts', 'country_block_list')) {
                $table->json('country_block_list')->nullable();
            }
            if (!Schema::hasColumn('creator_posts', 'country_allow_list')) {
                $table->json('country_allow_list')->nullable();
            }
            if (!Schema::hasColumn('creator_posts', 'view_count_7d')) {
                $table->unsignedInteger('view_count_7d')->default(0);
            }
        });

        if (!Schema::hasTable('user_blocks')) {
            Schema::create('user_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('blocker_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reason', 60)->nullable();
                $table->timestamps();
                $table->unique(['blocker_user_id', 'blocked_user_id'], 'user_blocks_pair_unique');
                $table->index(['blocked_user_id']);
            });
        }

        if (!Schema::hasTable('user_reports')) {
            Schema::create('user_reports', function (Blueprint $table) {
                $table->id();
                // Polymorphic target — one of:
                //   'user'    → target_id = users.id
                //   'comment' → target_id = creator_post_comments.id
                //   'message' → target_id = inbox_messages.id
                //   'post'    → target_id = creator_posts.id
                $table->string('target_type', 16);
                $table->unsignedBigInteger('target_id');

                $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reporter_ip', 45)->nullable();

                $table->string('reason', 32);
                $table->text('comment')->nullable();

                $table->string('status', 16)->default('pending');   // pending|dismissed|warned|removed|escalated|suspended
                $table->unsignedInteger('coalesced_count')->default(1);

                $table->text('admin_note')->nullable();
                $table->timestamp('actioned_at')->nullable();
                $table->foreignId('actioned_by_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->index(['status', 'target_type', 'created_at'], 'ur_status_type_idx');
                $table->index(['target_type', 'target_id'], 'ur_target_idx');
            });
        }

        if (!Schema::hasTable('dmca_takedowns')) {
            Schema::create('dmca_takedowns', function (Blueprint $table) {
                $table->id();
                // Public form — reporter is anonymous unless signed in.
                $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reporter_name', 200);
                $table->string('reporter_email', 200);
                $table->string('reporter_address', 500)->nullable();
                $table->string('rights_holder', 200)->nullable();

                $table->text('original_work_url');     // canonical URL of the original
                $table->text('infringing_url');        // 1inme URL alleged to infringe

                // Optional pointer to internal records when the form maps cleanly
                // — speeds up the admin "Hide post" / "Suspend creator" actions.
                $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('target_post_id')->nullable()->constrained('creator_posts')->nullOnDelete();

                $table->boolean('good_faith_acknowledged')->default(false);
                $table->boolean('penalty_of_perjury_acknowledged')->default(false);
                $table->string('signature', 200);

                $table->string('status', 16)->default('pending');   // pending|valid|invalid|removed|counter
                $table->text('admin_note')->nullable();
                $table->timestamp('actioned_at')->nullable();
                $table->foreignId('actioned_by_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->string('reporter_ip', 45)->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dmca_takedowns');
        Schema::dropIfExists('user_reports');
        Schema::dropIfExists('user_blocks');

        Schema::table('creator_posts', function (Blueprint $table) {
            foreach (['country_block_list', 'country_allow_list', 'view_count_7d'] as $c) {
                if (Schema::hasColumn('creator_posts', $c)) $table->dropColumn($c);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'mute_words', 'watermark_settings', 'country_block_list',
                'country_allow_list', 'dmca_email', 'creator_digest_last_sent_at',
            ] as $c) {
                if (Schema::hasColumn('users', $c)) $table->dropColumn($c);
            }
        });
    }
};
