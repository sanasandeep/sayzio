<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The passwordless WhatsApp sign-up path (`AuthController::createOtpSignupUser`
 * with type=mobile) creates accounts with `email = null` — but the original
 * users-table migration declared `email` NOT NULL, so every WhatsApp sign-up
 * insert died with a 23502 not-null violation. Relax the constraint so
 * mobile-only accounts can exist; the column's UNIQUE index is unaffected
 * (Postgres allows any number of NULLs under a unique constraint).
 *
 * Additive / non-destructive: DROP NOT NULL never touches data and is
 * trivially reversible. Guarded so re-running (or running against a DB where
 * the column is already nullable) is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'email')) {
            return;
        }

        $col = DB::selectOne(
            "select is_nullable from information_schema.columns where table_name = 'users' and column_name = 'email'"
        );
        if ($col && strtoupper((string) $col->is_nullable) === 'NO') {
            DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: re-adding NOT NULL would fail (or force
        // destructive edits) once mobile-only accounts with a null email
        // exist. Non-destructive by policy on the shared database.
    }
};
