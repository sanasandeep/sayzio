<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let a protected entry target a user by id, not only by email
 * (Task: protected accounts for email-less users). Accounts that signed
 * up without an email (users.email null — e.g. WhatsApp/mobile-only
 * signups) could never be protected because the list was keyed
 * exclusively by lowercased email.
 *
 * Adds a nullable `user_id` alternate key and relaxes `email` to
 * nullable so an id-only entry can exist. Every entry must still carry
 * at least one key (email or user_id) — enforced at the application
 * layer in ProtectedAccount / the admin controllers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('protected_accounts')) {
            return;
        }

        if (! Schema::hasColumn('protected_accounts', 'user_id')) {
            Schema::table('protected_accounts', function ($table) {
                $table->unsignedBigInteger('user_id')->nullable()->unique();
            });
        }

        // Relax the NOT NULL on email so id-only entries can be stored.
        DB::statement('ALTER TABLE protected_accounts ALTER COLUMN email DROP NOT NULL');
    }

    public function down(): void
    {
        if (Schema::hasTable('protected_accounts') && Schema::hasColumn('protected_accounts', 'user_id')) {
            Schema::table('protected_accounts', function ($table) {
                $table->dropColumn('user_id');
            });
        }
    }
};
