<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_approval_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('creator_post_id');
            $table->unsignedBigInteger('user_id'); // author of the comment
            // Optional action label this comment was attached to: 'submit',
            // 'approve', 'changes_requested', 'reject', or null for a plain
            // threaded reply.
            $table->string('action', 24)->nullable();
            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['creator_post_id', 'created_at'], 'pac_post_created_idx');
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_approval_comments');
    }
};
