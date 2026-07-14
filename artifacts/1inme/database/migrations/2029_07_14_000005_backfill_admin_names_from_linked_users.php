<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time backfill: update each admin record whose linked user (`admins.user_id`)
 * has a different name. This corrects stale display names — such as an admin
 * whose user profile was renamed after the admin account was created — so the
 * admin sidebar immediately shows the current name without waiting for the
 * next profile save.
 *
 * Only admin rows with an explicit `user_id` link are touched. Admins that
 * have no linked user (staff-only accounts) keep their own name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('admins', 'user_id')) {
            return;
        }

        // Pull all linked (admin, user) pairs where the names differ.
        $pairs = DB::table('admins')
            ->join('users', 'admins.user_id', '=', 'users.id')
            ->whereNotNull('admins.user_id')
            ->whereRaw('admins.name IS DISTINCT FROM users.name')
            ->select('admins.id as admin_id', 'users.name as user_name')
            ->get();

        foreach ($pairs as $pair) {
            DB::table('admins')
                ->where('id', $pair->admin_id)
                ->update(['name' => $pair->user_name]);
        }
    }

    public function down(): void
    {
        // Name syncing is non-destructive; there is no meaningful rollback.
    }
};
