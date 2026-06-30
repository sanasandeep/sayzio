<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan upgrade/downgrade billing rework.
 *
 *  1. `subscription_credit_reviews` — after a full-price upgrade we no
 *     longer auto-credit the leftover days/add-on time from the old plan.
 *     Instead each upgrade flags a pending review row an admin can approve
 *     (extend the new plan's expiry by N days) or dismiss.
 *
 *  2. `subscriptions.scheduled_downgrade_plan_id` — a scheduled change to
 *     a chosen lower PAID plan, applied at cycle end by the renewal job
 *     (instead of cancelling to Free). Cancellable before it applies.
 *
 * Guarded natively so it is safe to re-run against the shared RDS.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('subscription_credit_reviews')) {
            Schema::create('subscription_credit_reviews', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
                $t->foreignId('old_subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
                $t->foreignId('old_plan_id')->nullable()->constrained('plans')->nullOnDelete();
                $t->foreignId('new_plan_id')->nullable()->constrained('plans')->nullOnDelete();
                $t->unsignedInteger('leftover_days')->default(0);
                $t->unsignedInteger('leftover_addon_days')->default(0);
                $t->json('addons_snapshot')->nullable();
                $t->string('currency', 3)->nullable();
                // pending | approved | dismissed
                $t->string('status', 20)->default('pending');
                $t->unsignedInteger('granted_days')->nullable();
                $t->foreignId('actioned_by')->nullable()->constrained('admins')->nullOnDelete();
                $t->timestamp('actioned_at')->nullable();
                $t->text('note')->nullable();
                $t->timestamps();
                $t->index(['status', 'created_at']);
            });
        }

        if (Schema::hasTable('subscriptions') && !Schema::hasColumn('subscriptions', 'scheduled_downgrade_plan_id')) {
            Schema::table('subscriptions', function (Blueprint $t) {
                $t->foreignId('scheduled_downgrade_plan_id')
                    ->nullable()
                    ->after('replaced_by_id')
                    ->constrained('plans')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'scheduled_downgrade_plan_id')) {
            try {
                DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_scheduled_downgrade_plan_id_foreign');
            } catch (\Throwable $e) {
                // best-effort: constraint name may differ across drivers
            }
            Schema::table('subscriptions', function (Blueprint $t) {
                $t->dropColumn('scheduled_downgrade_plan_id');
            });
        }

        Schema::dropIfExists('subscription_credit_reviews');
    }
};
