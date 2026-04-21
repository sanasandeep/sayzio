<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_boards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            // 'team'   = visible to every workspace member with tasks.view
            // 'personal' = private to owner_user_id (only that member sees it)
            $table->string('scope', 16)->default('team');
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('name');
            $table->string('slug', 80)->nullable();
            $table->string('color', 16)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'scope']);
            $table->index(['workspace_id', 'owner_user_id']);
        });

        Schema::create('task_columns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('board_id');
            $table->string('name');
            $table->string('color', 16)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('wip_limit')->nullable();
            // 'done' columns auto-mark cards as completed when dropped in.
            $table->boolean('is_done')->default(false);
            $table->timestamps();

            $table->index(['board_id', 'position']);
            $table->index('workspace_id');
        });

        Schema::create('task_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('board_id');
            $table->unsignedBigInteger('column_id');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->date('due_date')->nullable();
            // low | normal | high | urgent
            $table->string('priority', 16)->default('normal');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['column_id', 'position']);
            $table->index(['board_id', 'archived_at']);
            $table->index('workspace_id');
            $table->index('due_date');
        });

        Schema::create('task_card_assignees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('card_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['card_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('board_id');
            $table->string('name', 60);
            $table->string('color', 16)->default('#8b5cf6');
            $table->timestamps();

            $table->index(['board_id', 'name']);
            $table->index('workspace_id');
        });

        Schema::create('task_card_labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('card_id');
            $table->unsignedBigInteger('label_id');
            $table->timestamps();

            $table->unique(['card_id', 'label_id']);
        });

        Schema::create('task_subtasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('card_id');
            $table->string('title', 240);
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['card_id', 'position']);
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('card_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->timestamps();

            $table->index('card_id');
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('card_id');
            $table->unsignedBigInteger('user_id')->nullable();
            // created | moved | assigned | unassigned | completed | reopened |
            // due_set | priority | renamed | label_added | label_removed
            $table->string('type', 32);
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_subtasks');
        Schema::dropIfExists('task_card_labels');
        Schema::dropIfExists('task_labels');
        Schema::dropIfExists('task_card_assignees');
        Schema::dropIfExists('task_cards');
        Schema::dropIfExists('task_columns');
        Schema::dropIfExists('task_boards');
    }
};
