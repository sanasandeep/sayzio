<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('note_action_plans', function (Blueprint $t) {
            $t->boolean('shared')->default(false);
            $t->json('planner_settings')->nullable();
            $t->boolean('automation_enabled')->default(false);
            $t->timestamp('next_run_at')->nullable()->index();
        });
        Schema::create('planner_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained('note_action_plans')->cascadeOnDelete();
            $t->string('run_key')->unique();
            $t->string('status')->default('queued');
            $t->string('trigger')->default('manual');
            $t->string('feature')->default('planner_review');
            $t->unsignedInteger('reserved_coins')->default(0);
            $t->unsignedInteger('coins_spent')->default(0);
            $t->json('result')->nullable();
            $t->text('message')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });
        Schema::create('planner_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('plan_id')->nullable()->constrained('note_action_plans')->nullOnDelete();
            $t->string('resource_kind', 30)->nullable();
            $t->unsignedBigInteger('resource_id')->nullable();
            $t->text('body');
            $t->boolean('shared')->default(false);
            $t->boolean('pinned')->default(false);
            $t->timestamp('snoozed_until')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['workspace_id', 'resource_kind', 'resource_id']);
        });
        Schema::create('project_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 30);
            $t->unsignedBigInteger('resource_id');
            $t->timestamps();
            $t->unique(['workspace_id', 'kind', 'resource_id']);
        });
        Schema::create('planner_handoffs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained('note_action_plans')->cascadeOnDelete();
            $t->string('tool', 40);
            $t->text('brief');
            $t->text('result')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['planner_handoffs', 'project_items', 'planner_notes', 'planner_runs'] as $name) {
            Schema::dropIfExists($name);
        }Schema::table('note_action_plans', fn (Blueprint $t) => $t->dropColumn(['shared', 'planner_settings', 'automation_enabled', 'next_run_at']));
    }
};
