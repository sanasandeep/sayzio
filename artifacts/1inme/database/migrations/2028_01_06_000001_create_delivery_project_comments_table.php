<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3566 — two-way comments on a Delivery Project.
 *
 * Clients (via the client portal) and anonymous buyers (via the public
 * share_token page) can post a comment/question; workspace team members can
 * reply. The thread is visible on the team dashboard, the portal view and the
 * public share page. author_role distinguishes a client/buyer note from a
 * team reply; author_user_id is set only for team replies (portal/public
 * authors are anonymous and identified by their captured name/email).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_project_comments')) {
            return;
        }

        Schema::create('delivery_project_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('delivery_projects')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();

            // 'client' = posted by the buyer/client (portal or public share);
            // 'team'   = a reply from a workspace member.
            $table->string('author_role', 20)->default('client');
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();

            $table->text('body');
            $table->timestamps();

            $table->index(['project_id', 'id']);
            $table->index(['workspace_id', 'author_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_project_comments');
    }
};
