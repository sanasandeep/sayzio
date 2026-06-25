<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit ledger for back-office user-management actions:
 * plan assignments, coin grants/deductions, account creation, and
 * suspend/reactivate. Distinct from `user_role_audits` (which tracks
 * only role attach/detach) so the broader "who did what to which
 * account" history has its own filterable surface.
 *
 * The operator is always an `admin`-guard Admin (every action below
 * is initiated from the admin panel), snapshotted by name/email so
 * the row stays meaningful after the operator is renamed/removed.
 * `details` is a JSON payload describing the change (e.g. old/new
 * plan, coin delta + reason, suspension reason + reactivation date).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_action_audits')) {
            return;
        }

        if (!Schema::hasTable('admin_action_audits')) {
            Schema::create('admin_action_audits', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Operator (admin guard). Nullable + name/email snapshot so a
                // CLI/system action or a later-deleted admin still reads cleanly.
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('admin_name', 191)->nullable();
                $table->string('admin_email', 191)->nullable();

                // Affected user. Nullable for actions not tied to a single user.
                $table->unsignedBigInteger('target_user_id')->nullable();
                $table->string('target_name', 191)->nullable();
                $table->string('target_email', 191)->nullable();

                // e.g. plan.assigned, coins.granted, coins.deducted,
                // account.created, account.suspended, account.reactivated.
                $table->string('action', 48);

                // Free-form structured payload describing the change.
                $table->json('details')->nullable();

                $table->string('ip', 64)->nullable();

                $table->timestamp('created_at')->useCurrent();

                $table->index(['target_user_id', 'created_at'], 'aaa_target_created_idx');
                $table->index(['admin_id', 'created_at'], 'aaa_admin_created_idx');
                $table->index(['action', 'created_at'], 'aaa_action_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_audits');
    }
};
