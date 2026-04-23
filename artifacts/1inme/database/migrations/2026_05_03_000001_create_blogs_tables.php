<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 32)->nullable();
            $table->string('cover_image')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('cover_image')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft|scheduled|published|archived
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->unsignedInteger('reading_time_min')->default(1);
            $table->boolean('is_featured_home')->default(false);
            $table->string('featured_slot', 40)->nullable(); // hero|carousel|null
            $table->boolean('allow_comments')->default(true);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->index(['status', 'published_at']);
            $table->index(['scheduled_at']);
            $table->index(['is_featured_home']);
        });

        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('blog_tags')->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('blog_comments')->cascadeOnDelete();
            // Author can be a dashboard user, a viewer follower, or a staff (for replies).
            $table->string('author_type', 20); // user|viewer|admin
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->string('author_avatar')->nullable();
            $table->text('body');
            $table->string('status', 20)->default('pending'); // pending|approved|spam|trash
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->index(['post_id', 'status', 'created_at']);
            $table->index(['status']);
        });

        // Seed the blogs.* permissions and grant the full set to existing
        // "Staff" roles so the new admin section is usable immediately.
        if (Schema::hasTable('permissions') && Schema::hasTable('role_permissions') && Schema::hasTable('roles')) {
            $perms = [
                ['name' => 'View Blogs',           'slug' => 'blogs.view',              'group' => 'blogs'],
                ['name' => 'Manage Blogs',         'slug' => 'blogs.manage',            'group' => 'blogs'],
                ['name' => 'Publish Blogs',        'slug' => 'blogs.publish',           'group' => 'blogs'],
                ['name' => 'Moderate Comments',    'slug' => 'blogs.comments.moderate', 'group' => 'blogs'],
                ['name' => 'Reply to Comments',    'slug' => 'blogs.comments.reply',    'group' => 'blogs'],
            ];

            $now = now();
            $newIds = [];
            foreach ($perms as $p) {
                $existing = DB::table('permissions')->where('slug', $p['slug'])->first();
                if ($existing) {
                    $newIds[] = $existing->id;
                    continue;
                }
                $newIds[] = DB::table('permissions')->insertGetId(array_merge($p, [
                    'created_at' => $now, 'updated_at' => $now,
                ]));
            }

            $staffRole = DB::table('roles')->where('slug', 'staff')->first();
            if ($staffRole) {
                foreach ($newIds as $pid) {
                    DB::table('role_permissions')->updateOrInsert(
                        ['role_id' => $staffRole->id, 'permission_id' => $pid],
                        []
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_categories');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('slug', [
                'blogs.view', 'blogs.manage', 'blogs.publish',
                'blogs.comments.moderate', 'blogs.comments.reply',
            ])->delete();
        }
    }
};
