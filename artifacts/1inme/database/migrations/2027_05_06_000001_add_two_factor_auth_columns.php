<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2FA enrollment columns on `users`.
 *
 * Workspace-level enforcement is stored in the existing `workspaces.settings`
 * JSON column under the keys:
 *   - `require_2fa` (bool)
 *   - `2fa_grace_until` (ISO timestamp; members must enroll by this time)
 *   - `2fa_enrollment_emails_sent_at` (ISO timestamp; for the heads-up email)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes']);
        });
    }
};
