<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workspace_activity_events')) {
            Schema::create('workspace_activity_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('action', 64);            // e.g. link.update, post.publish, billing.cancel
                $table->string('object_type', 64)->nullable(); // e.g. link, post, member, billing
                $table->unsignedBigInteger('object_id')->nullable();
                $table->string('object_label', 255)->nullable();
                $table->string('object_url', 1024)->nullable();
                $table->json('payload')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['workspace_id', 'created_at']);
                $table->index(['workspace_id', 'actor_user_id']);
                $table->index(['workspace_id', 'action']);
                $table->index(['workspace_id', 'object_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_activity_events');
    }
};
