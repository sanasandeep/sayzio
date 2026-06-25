<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger for CSV downloads of the role-change audit
 * trail. Sits next to `user_role_audits` and follows the same
 * actor-shape (split user/admin id columns + guard) so the same
 * "who did this" lookups work without a custom join.
 *
 * Captures one row per call to the role-audit CSV export
 * endpoints. Lets super-admins answer the meta-compliance
 * question of "who pulled this audit and when" without grepping
 * webserver logs — important whenever an export is shared
 * outside the platform.
 *
 * Scope distinguishes a full-pool export from a single-user
 * export (in which case `target_user_id` is set), and
 * `row_count` snapshots how many audit rows were in the
 * download — useful for spotting unusually large pulls.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_role_audit_exports')) {
            Schema::create('user_role_audit_exports', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Mirrors `user_role_audits`: either actor_user_id (web
                // guard) or actor_admin_id (admin guard) is set; both
                // null means a system / CLI download (rare but
                // possible if a job ever streams the CSV).
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->unsignedBigInteger('actor_admin_id')->nullable();
                $table->string('actor_guard', 16)->nullable();
                $table->string('actor_name', 191)->nullable();
                $table->string('actor_email', 191)->nullable();

                // 'full_pool' = every audit row across the user pool.
                // 'single_user' = scoped to one target account
                //                  (`target_user_id` populated).
                $table->string('scope', 32);
                $table->unsignedBigInteger('target_user_id')->nullable();

                // Snapshot of how many audit rows were in the
                // download. Stored separately from the stream so a
                // truncated download still leaves a record of what
                // was offered.
                $table->unsignedInteger('row_count')->default(0);

                $table->string('ip', 64)->nullable();

                $table->timestamp('created_at')->useCurrent();

                $table->index('created_at', 'urae_created_idx');
                $table->index(['actor_user_id', 'created_at'], 'urae_actor_user_created_idx');
                $table->index(['actor_admin_id', 'created_at'], 'urae_actor_admin_created_idx');
                $table->index(['target_user_id', 'created_at'], 'urae_target_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_audit_exports');
    }
};
