<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether (and when) a user deliberately chose their own password.
 *
 * Accounts created via OTP / social sign-up get a random, unused password
 * hash, so the hash alone can't tell "real password" apart from "filler".
 * password_set_at is stamped whenever the user chooses a password
 * (register-with-password, change, set-first, reset) and — as a self-healing
 * backfill for legacy accounts — on any successful login with the account's
 * own password. NULL means "never user-chosen".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'password_set_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('password_set_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'password_set_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('password_set_at');
            });
        }
    }
};
