<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit ledger for role attach/detach on user accounts. The
 * existing `workspace_audit_events` table is workspace-scoped and
 * therefore unsuitable for platform-level role grants (which are
 * not tied to any one workspace), so this is a small parallel
 * table that follows the same actor / target / payload shape.
 *
 * Actor can come from either the `web` guard (the user-facing
 * "User access" page) or the `admin` guard (the back-office
 * Admin model that powers the admin user-detail page), so the
 * actor id is split across two nullable foreign-key columns plus
 * a guard column for unambiguous lookup. Snapshotted name/email/
 * role-slug fields keep the audit row meaningful even after the
 * actor or role is later renamed or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_audits', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Either actor_user_id (web guard) or actor_admin_id
            // (admin guard) is set; both null means a system /
            // CLI action.
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->string('actor_guard', 16)->nullable();
            $table->string('actor_name', 191)->nullable();
            $table->string('actor_email', 191)->nullable();

            $table->unsignedBigInteger('target_user_id');

            // Role may be deleted later; snapshot slug/name so the
            // log stays human-readable.
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('role_slug', 191);
            $table->string('role_name', 191)->nullable();

            // 'attached' or 'detached'.
            $table->string('action', 16);

            // Which surface the change came from, e.g.
            // 'user_access' or 'admin'.
            $table->string('source', 32)->nullable();

            $table->string('ip', 64)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_user_id', 'created_at'], 'ura_target_created_idx');
            $table->index(['actor_user_id', 'created_at'], 'ura_actor_user_created_idx');
            $table->index(['actor_admin_id', 'created_at'], 'ura_actor_admin_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_audits');
    }
};
