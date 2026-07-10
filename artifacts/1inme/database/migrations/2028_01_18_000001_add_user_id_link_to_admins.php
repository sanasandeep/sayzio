<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Harden the user <-> admin privilege bridge (Admin Privilege Boundaries).
 *
 * The back-office Admin pool and the web User pool historically shared no
 * foreign key: a person was treated as "also an admin" purely because a row
 * in `admins` had the same email as their `users` row. That made the
 * privilege boundary depend on a mutable, user-controlled field — anyone who
 * registered or renamed a user to an admin's email inherited that admin's
 * authority without ever proving they controlled the mailbox.
 *
 * This migration introduces an explicit link column `admins.user_id` and
 * backfills it ONLY for collisions where the user has proven ownership of the
 * email address (`email_verified_at` is set) AND the match is unambiguous
 * (exactly one such user). Unverified or ambiguous historic collisions are
 * deliberately left unlinked, which neutralises any bridge an attacker
 * manufactured under the old email-only rule (they resolve to no admin).
 *
 * Additive + idempotent: guarded by hasColumn, safe to re-run.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        if (! Schema::hasColumn('admins', 'user_id')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('email');
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'email_verified_at')) {
            return;
        }

        DB::table('admins')
            ->whereNull('user_id')
            ->orderBy('id')
            ->chunkById(200, function ($admins) {
                foreach ($admins as $admin) {
                    $email = strtolower(trim((string) $admin->email));
                    if ($email === '') {
                        continue;
                    }

                    $userIds = DB::table('users')
                        ->whereRaw('lower(email) = ?', [$email])
                        ->whereNotNull('email_verified_at')
                        ->limit(2)
                        ->pluck('id');

                    // Only bind a proven, unambiguous owner. Anything else
                    // (unverified, or more than one match) stays unlinked.
                    if ($userIds->count() !== 1) {
                        continue;
                    }

                    DB::table('admins')
                        ->where('id', $admin->id)
                        ->whereNull('user_id')
                        ->update(['user_id' => $userIds->first()]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('admins', 'user_id')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
