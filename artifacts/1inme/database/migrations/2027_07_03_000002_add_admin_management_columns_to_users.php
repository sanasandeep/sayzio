<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns powering the admin/staff user-management suite:
 *
 *  - Temporary hold/suspend: a suspended account is blocked from
 *    logging in / using the app. `suspended_at` is the source of
 *    truth (orthogonal to the existing active/inactive/banned
 *    `status` column and to the separate 18+ `adult_flag_*`
 *    suspension). `reactivate_at` is an optional auto-lift date.
 *
 *  - Comp / time-limited plans: when an operator grants a plan
 *    "free for N days", `comp_plan_expires_at` marks the window so
 *    the scheduled revert job can drop the account back to the
 *    default plan when it elapses (the existing `plan_expires_at`
 *    is left for the subscription-billing path).
 *
 * Additive + guarded (hasColumn checks) so it is safe to re-run
 * against the shared RDS where a partially-applied migration may
 * already have some columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('users', 'suspension_reason')) {
                $table->text('suspension_reason')->nullable()->after('suspended_at');
            }
            if (! Schema::hasColumn('users', 'suspended_by')) {
                $table->unsignedBigInteger('suspended_by')->nullable()->after('suspension_reason');
            }
            if (! Schema::hasColumn('users', 'reactivate_at')) {
                $table->timestamp('reactivate_at')->nullable()->after('suspended_by');
            }
            if (! Schema::hasColumn('users', 'comp_plan_expires_at')) {
                $table->timestamp('comp_plan_expires_at')->nullable()->after('plan_expires_at');
            }
            if (! Schema::hasColumn('users', 'comp_plan_granted_by')) {
                $table->unsignedBigInteger('comp_plan_granted_by')->nullable()->after('comp_plan_expires_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'suspended_at', 'suspension_reason', 'suspended_by', 'reactivate_at',
                'comp_plan_expires_at', 'comp_plan_granted_by',
            ] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
