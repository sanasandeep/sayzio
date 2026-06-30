<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-serve account-badge requests (Task #2910). A user asks for an
 * existing {@see \App\Modules\Admin\Models\AccountBadge} or describes a
 * custom one; admins review the queue and, on approval, attach the badge
 * via the `account_badge_user` pivot.
 *
 * Additive + guarded (hasTable) so it's safe to re-run against the shared
 * production database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('badge_requests')) {
            return;
        }

        Schema::create('badge_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // The existing badge the user asked for (null for a free-text
            // custom request).
            $table->foreignId('account_badge_id')->nullable()
                ->constrained('account_badges')->nullOnDelete();
            // Free-text "the badge I want" for a custom request.
            $table->string('custom_name')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending')->index();
            // The badge actually attached on approval (may differ from the
            // requested one when an admin maps a custom request to a badge).
            $table->foreignId('assigned_badge_id')->nullable()
                ->constrained('account_badges')->nullOnDelete();
            // admin-guard reviewer id; no FK to keep this migration additive.
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_requests');
    }
};
