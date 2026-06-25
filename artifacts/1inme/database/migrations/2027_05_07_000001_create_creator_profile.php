<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the data needed for the new fixed-layout Creator Profile that
 * lives at /@handle (separate from the biolink at /{alias}).
 *
 * - Adds profile metadata (cover, tagline, location, niche tags,
 *   socials, section visibility) to users.
 * - Extends creator_posts with multi-media post types (gallery,
 *   video, audio, link card).
 * - Adds dedicated comment + reaction tables for creator posts so
 *   the new surface stays decoupled from biolink-block community
 *   tables.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'cover_image')) {
                $table->string('cover_image', 1024)->nullable();
            }
            if (!Schema::hasColumn('users', 'tagline')) {
                $table->string('tagline', 200)->nullable();
            }
            if (!Schema::hasColumn('users', 'location')) {
                $table->string('location', 120)->nullable();
            }
            if (!Schema::hasColumn('users', 'niche_tags')) {
                $table->json('niche_tags')->nullable();
            }
            if (!Schema::hasColumn('users', 'socials')) {
                $table->json('socials')->nullable();
            }
            if (!Schema::hasColumn('users', 'profile_published')) {
                $table->boolean('profile_published')->default(false);
            }
            if (!Schema::hasColumn('users', 'profile_section_visibility')) {
                // {hero:true, stats:true, about:true, posts:true,
                //  socials:true, biolink:true, contact:true}
                $table->json('profile_section_visibility')->nullable();
            }
            if (!Schema::hasColumn('users', 'posts_count')) {
                $table->unsignedInteger('posts_count')->default(0);
            }
        });

        Schema::table('creator_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('creator_posts', 'post_type')) {
                $table->string('post_type', 16)->default('text')->after('user_id');
            }
            if (!Schema::hasColumn('creator_posts', 'media')) {
                $table->json('media')->nullable()->after('image');
            }
            if (!Schema::hasColumn('creator_posts', 'reactions_count')) {
                $table->unsignedInteger('reactions_count')->default(0);
            }
            if (!Schema::hasColumn('creator_posts', 'comments_count')) {
                $table->unsignedInteger('comments_count')->default(0);
            }
        });

        if (!Schema::hasTable('creator_post_comments')) {
            Schema::create('creator_post_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->constrained('creator_posts')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('creator_post_comments')->cascadeOnDelete();
                // Author is always a registered viewer (ViewerSession) — anonymous
                // comments are not allowed on the new surface.
                $table->foreignId('viewer_user_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->string('status', 16)->default('visible'); // visible|hidden|deleted
                $table->timestamps();
                $table->index(['post_id', 'parent_id', 'created_at'], 'cpc_post_parent_idx');
                $table->index(['viewer_user_id']);
            });
        }

        if (!Schema::hasTable('creator_post_reactions')) {
            Schema::create('creator_post_reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->constrained('creator_posts')->cascadeOnDelete();
                $table->foreignId('viewer_user_id')->constrained('users')->cascadeOnDelete();
                // Branded reaction key — see CreatorPostReaction::REACTIONS.
                $table->string('reaction', 24);
                $table->timestamp('created_at')->useCurrent();

                // One viewer can pick at most one reaction per post (toggling
                // swaps the row server-side).
                $table->unique(['post_id', 'viewer_user_id'], 'cpr_post_viewer_unique');
                $table->index(['post_id', 'reaction']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_post_reactions');
        Schema::dropIfExists('creator_post_comments');

        Schema::table('creator_posts', function (Blueprint $table) {
            foreach (['post_type', 'media', 'reactions_count', 'comments_count'] as $c) {
                if (Schema::hasColumn('creator_posts', $c)) $table->dropColumn($c);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'cover_image', 'tagline', 'location', 'niche_tags', 'socials',
                'profile_published', 'profile_section_visibility', 'posts_count',
            ] as $c) {
                if (Schema::hasColumn('users', $c)) $table->dropColumn($c);
            }
        });
    }
};
