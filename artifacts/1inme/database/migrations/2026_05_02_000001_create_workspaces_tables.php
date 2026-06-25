<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workspaces')) {
            Schema::create('workspaces', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->string('name');
                $table->string('slug')->nullable();
                $table->timestamps();

                $table->index('owner_user_id');
            });
        }

        if (!Schema::hasTable('workspace_members')) {
            Schema::create('workspace_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role')->default('viewer'); // admin/editor/replier/analyst/viewer/custom
                $table->json('permissions')->nullable();
                $table->timestamps();

                $table->unique(['workspace_id', 'user_id']);
                $table->index('user_id');
            });
        }

        if (!Schema::hasTable('workspace_invites')) {
            Schema::create('workspace_invites', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('inviter_user_id');
                $table->string('email');
                $table->string('role')->default('viewer');
                $table->json('permissions')->nullable();
                $table->string('token', 80)->unique();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'email']);
            });
        }

        // Add workspace_id (and a created_by_user_id where attribution makes
        // sense) to every user-scoped resource table. We keep `user_id` on
        // each row pointing at the workspace owner so existing scoping
        // queries (`$user->links()`, `where('user_id', $userId)`) keep
        // working unchanged. workspace_id becomes the new authoritative
        // scoping key for cross-workspace isolation.
        $tables = [
            'links', 'projects', 'creator_posts', 'forms', 'subscribers',
            'pixels', 'qr_codes', 'splash_pages', 'user_files', 'contacts',
            'referrals', 'referral_rewards', 'social_proofs',
            'calendar_accounts', 'integration_configs', 'inbox_replies',
            'inbox_forward_destinations', 'link_performance_snapshots',
            'follows', 'form_submissions', 'domains',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) continue;
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'workspace_id')) {
                    $table->unsignedBigInteger('workspace_id')->nullable()->after('id');
                    $table->index('workspace_id');
                }
                // created_by_user_id only on tables where the actor differs
                // from the workspace owner — skip on rows representing
                // *visitors* (subscribers, follows, form_submissions).
                $skipAttribution = ['subscribers', 'follows', 'form_submissions',
                                    'referrals', 'referral_rewards', 'inbox_forward_deliveries',
                                    'link_performance_snapshots', 'domains'];
                if (!in_array($tableName, $skipAttribution, true)
                    && !Schema::hasColumn($tableName, 'created_by_user_id')) {
                    $table->unsignedBigInteger('created_by_user_id')->nullable()->after('workspace_id');
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'links', 'projects', 'creator_posts', 'forms', 'subscribers',
            'pixels', 'qr_codes', 'splash_pages', 'user_files', 'contacts',
            'referrals', 'referral_rewards', 'social_proofs',
            'calendar_accounts', 'integration_configs', 'inbox_replies',
            'inbox_forward_destinations', 'link_performance_snapshots',
            'follows', 'form_submissions', 'domains',
        ];
        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) continue;
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'workspace_id')) {
                    try { $table->dropIndex([$tableName . '_workspace_id_index']); } catch (\Throwable $e) {}
                    $table->dropColumn('workspace_id');
                }
                if (Schema::hasColumn($tableName, 'created_by_user_id')) {
                    $table->dropColumn('created_by_user_id');
                }
            });
        }
        Schema::dropIfExists('workspace_invites');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
