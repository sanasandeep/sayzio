<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit ledger for every successful sign-in that used the
 * admin-configurable master override password (instead of the account's
 * own password). One row per master-password login across all three
 * surfaces — web user login, the mobile/REST API, and the admin panel.
 *
 * The accessed account is snapshotted by name/email so the row stays
 * meaningful after the account is renamed/removed, and the originating
 * IP + user-agent are captured so an admin can review who reached which
 * account and from where. The master password value itself is never
 * stored here (or anywhere in plaintext).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_password_logins')) {
            return;
        }

        if (!Schema::hasTable('master_password_logins')) {
            Schema::create('master_password_logins', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Which login surface the override was used on: web | api | admin.
                $table->string('guard', 16);

                // The account that was accessed. id is nullable + name/email
                // snapshotted so the entry reads cleanly even after deletion.
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('target_name', 191)->nullable();
                $table->string('target_email', 191)->nullable();

                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 512)->nullable();

                $table->timestamp('created_at')->useCurrent();

                $table->index(['guard', 'created_at'], 'mpl_guard_created_idx');
                $table->index(['target_email', 'created_at'], 'mpl_target_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('master_password_logins');
    }
};
