<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-granted asset transfer capability + auditable transfer log.
 *
 * users.transfer_capability_granted_at / _by record the explicit grant
 * (users whose email matches an Admin record are implicitly granted and
 * need no row-level state). asset_transfers is the immutable audit log
 * of every completed link/workspace transfer, admin-viewable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'transfer_capability_granted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('transfer_capability_granted_at')->nullable();
                // Snapshot of the granting operator (name/email) so the audit
                // stays readable if the admin account is renamed or removed.
                $table->string('transfer_capability_granted_by')->nullable();
            });
        }

        if (!Schema::hasTable('asset_transfers')) {
            Schema::create('asset_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('kind', 20); // 'link' | 'workspace'
                $table->unsignedBigInteger('asset_id');
                $table->string('asset_label')->nullable(); // snapshot: link title/alias or workspace name
                $table->unsignedBigInteger('from_user_id')->index();
                $table->unsignedBigInteger('to_user_id')->index();
                $table->string('from_email')->nullable();
                $table->string('to_email')->nullable();
                $table->string('channel', 10)->default('web'); // 'web' | 'api'
                $table->json('details')->nullable(); // per-table reassignment counts etc.
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transfers');
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'transfer_capability_granted_at')) {
                $table->dropColumn(['transfer_capability_granted_at', 'transfer_capability_granted_by']);
            }
        });
    }
};
