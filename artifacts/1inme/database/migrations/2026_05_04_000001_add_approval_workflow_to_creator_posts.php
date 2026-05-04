<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_posts', function (Blueprint $table) {
            // Approval workflow state for posts created in team workspaces
            // that have "Require approval for posts" enabled. Null when the
            // workspace doesn't require approval (or for legacy rows).
            //   - pending_review     : editor submitted, awaiting decision
            //   - changes_requested  : reviewer asked for edits, back to draft
            //   - approved           : reviewer approved (then published or scheduled)
            //   - rejected           : reviewer rejected, back to draft
            // No `after()` positioning — this migration runs before the
            // later 2026_11_25 client-approval migration, so referencing
            // any of those columns here would fail on a fresh install.
            $table->string('approval_status', 20)->nullable();

            // When the editor most recently submitted for review.
            $table->timestamp('approval_requested_at')->nullable();

            // When the reviewer made a decision (approve / changes / reject).
            $table->timestamp('approval_decided_at')->nullable();

            // The reviewer who made that decision.
            $table->unsignedBigInteger('approval_decided_by_user_id')->nullable();

            // While a post is pending_review we hold its intended schedule
            // here so we can restore it on approval — the live
            // `scheduled_at` column is NULL'd out so the publish-due-posts
            // job doesn't pick the post up before review.
            $table->timestamp('intended_scheduled_at')->nullable();

            $table->index(['workspace_id', 'approval_status'], 'creator_posts_ws_approval_idx');
        });
    }

    public function down(): void
    {
        Schema::table('creator_posts', function (Blueprint $table) {
            try { $table->dropIndex('creator_posts_ws_approval_idx'); } catch (\Throwable $e) {}
            $table->dropColumn([
                'approval_status',
                'approval_requested_at',
                'approval_decided_at',
                'approval_decided_by_user_id',
                'intended_scheduled_at',
            ]);
        });
    }
};
