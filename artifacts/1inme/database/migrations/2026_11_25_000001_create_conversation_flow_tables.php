<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('conversation_flows')) {
            Schema::create('conversation_flows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('link_id');
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->string('name', 120)->default('Conversational Flow');
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_published')->default(false);
                $table->boolean('is_active')->default(true);
                $table->text('intro_message')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->index('link_id');
                $table->index(['link_id', 'is_published']);
                $table->index('workspace_id');
                $table->foreign('link_id')->references('id')->on('links')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('conversation_actions')) {
            Schema::create('conversation_actions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id');
                $table->string('kind', 40);
                $table->string('label', 160)->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index('flow_id');
                $table->foreign('flow_id')->references('id')->on('conversation_flows')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('conversation_steps')) {
            Schema::create('conversation_steps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id');
                $table->string('key', 60);
                $table->string('kind', 30)->default('question');
                $table->text('message_text');
                $table->string('answer_field', 60)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_entry')->default(false);
                $table->boolean('skip_if_known')->default(true);
                $table->string('next_step_key', 60)->nullable();
                $table->unsignedBigInteger('action_id')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->unique(['flow_id', 'key']);
                $table->index('flow_id');
                $table->foreign('flow_id')->references('id')->on('conversation_flows')->cascadeOnDelete();
                $table->foreign('action_id')->references('id')->on('conversation_actions')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('conversation_choices')) {
            Schema::create('conversation_choices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('step_id');
                $table->string('label', 120);
                $table->string('value', 120);
                $table->string('next_step_key', 60)->nullable();
                $table->unsignedBigInteger('action_id')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index('step_id');
                $table->foreign('step_id')->references('id')->on('conversation_steps')->cascadeOnDelete();
                $table->foreign('action_id')->references('id')->on('conversation_actions')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('conversation_sessions')) {
            Schema::create('conversation_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id');
                $table->unsignedBigInteger('link_id');
                $table->string('public_id', 40)->unique();
                $table->string('page_session_id', 40)->nullable();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->unsignedBigInteger('subscriber_id')->nullable();
                $table->string('current_step_key', 60)->nullable();
                $table->json('answers')->nullable();
                $table->json('path')->nullable();
                $table->boolean('completed')->default(false);
                $table->unsignedBigInteger('completed_action_id')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['flow_id', 'completed']);
                $table->index('link_id');
                $table->index('page_session_id');
                $table->foreign('flow_id')->references('id')->on('conversation_flows')->cascadeOnDelete();
                $table->foreign('link_id')->references('id')->on('links')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('conversation_step_events')) {
            Schema::create('conversation_step_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('flow_id');
                $table->string('step_key', 60);
                $table->string('event', 30);
                $table->string('choice_value', 120)->nullable();
                $table->timestamp('occurred_at')->useCurrent();

                $table->index(['flow_id', 'step_key', 'event']);
                $table->index('session_id');
                $table->foreign('session_id')->references('id')->on('conversation_sessions')->cascadeOnDelete();
                $table->foreign('flow_id')->references('id')->on('conversation_flows')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_step_events');
        Schema::dropIfExists('conversation_sessions');
        Schema::dropIfExists('conversation_choices');
        Schema::dropIfExists('conversation_steps');
        Schema::dropIfExists('conversation_actions');
        Schema::dropIfExists('conversation_flows');
    }
};
