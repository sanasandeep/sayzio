<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records who granted each account badge (Task #3045 — creators giving badges
 * they hold to other accounts by handle). A null `assigned_by` means the badge
 * came from staff/admin or the self-request → admin-approval flow; a non-null
 * value points at the creator who passed it on, making creator-granted badges
 * auditable and distinguishable.
 *
 * Additive + idempotent (shared-RDS rules): guarded by hasTable/hasColumn so a
 * partial re-run is safe; never drops pivot rows. FK uses nullOnDelete so
 * removing the granting account leaves the recipient's badge intact (just
 * un-attributed).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_badge_user') && !Schema::hasColumn('account_badge_user', 'assigned_by')) {
            Schema::table('account_badge_user', function (Blueprint $table) {
                $table->unsignedBigInteger('assigned_by')->nullable()->after('user_id');
                $table->foreign('assigned_by', 'abu_assigned_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('account_badge_user') && Schema::hasColumn('account_badge_user', 'assigned_by')) {
            Schema::table('account_badge_user', function (Blueprint $table) {
                try {
                    $table->dropForeign('abu_assigned_by_fk');
                } catch (\Throwable $e) {
                    // FK may not exist on a partially-applied state — ignore.
                }
                $table->dropColumn('assigned_by');
            });
        }
    }
};
