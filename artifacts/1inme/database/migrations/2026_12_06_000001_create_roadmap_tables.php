<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roadmap_items')) {
            Schema::create('roadmap_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id');
                $t->unsignedBigInteger('link_id');
                $t->unsignedBigInteger('block_id');
                $t->string('status', 24)->default('pending');
                $t->string('title', 200);
                $t->text('description')->nullable();
                $t->unsignedInteger('votes_count')->default(0);
                $t->string('submitter_name', 120)->nullable();
                $t->string('submitter_email', 190)->nullable();
                $t->unsignedBigInteger('submitter_user_id')->nullable();
                $t->string('submitter_fingerprint', 64)->nullable();
                $t->string('submitter_ip', 45)->nullable();
                $t->boolean('is_blocked')->default(false);
                $t->unsignedBigInteger('merged_into_id')->nullable();
                $t->unsignedBigInteger('task_card_id')->nullable();
                $t->timestamp('shipped_at')->nullable();
                $t->timestamps();

                $t->index(['link_id', 'status']);
                $t->index(['block_id', 'status']);
                $t->index('workspace_id');
                $t->index('task_card_id');
            });
        }

        if (!Schema::hasTable('roadmap_votes')) {
            Schema::create('roadmap_votes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('item_id');
                $t->unsignedBigInteger('viewer_user_id')->nullable();
                $t->string('fingerprint', 64);
                $t->string('email', 190)->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamps();

                $t->unique(['item_id', 'fingerprint']);
                $t->index(['item_id', 'viewer_user_id']);
            });
        }

        if (!Schema::hasTable('roadmap_comments')) {
            Schema::create('roadmap_comments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('item_id');
                $t->unsignedBigInteger('viewer_user_id')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('author_name', 120);
                $t->text('body');
                $t->boolean('is_creator')->default(false);
                $t->boolean('is_hidden')->default(false);
                $t->string('fingerprint', 64)->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamps();

                $t->index(['item_id', 'is_hidden']);
            });
        }

        Schema::table('task_cards', function (Blueprint $t) {
            if (!Schema::hasColumn('task_cards', 'roadmap_item_id')) {
                $t->unsignedBigInteger('roadmap_item_id')->nullable()->after('archived_at');
                $t->index('roadmap_item_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_cards', function (Blueprint $t) {
            $t->dropIndex(['roadmap_item_id']);
            $t->dropColumn('roadmap_item_id');
        });
        Schema::dropIfExists('roadmap_comments');
        Schema::dropIfExists('roadmap_votes');
        Schema::dropIfExists('roadmap_items');
    }
};
