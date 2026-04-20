<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Existing-user country default per Task #191:
 * Where the previous data gives a strong hint about the user's country
 * (mobile starting with `+91` → India), backfill `users.country`. Leave
 * it null for everyone else so they get the global USD default until
 * they pick a country in profile settings.
 *
 * Forward-only: the down() is a no-op so we never wipe a curator/user's
 * country choice during a rollback.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('users')
            ->whereNull('country')
            ->where(function ($q) {
                $q->where('mobile', 'like', '+91%')
                  ->orWhere('mobile', 'like', '0091%')
                  ->orWhere('mobile', 'like', '91%');
            })
            ->update(['country' => 'IN']);
    }

    public function down(): void
    {
        // Intentionally no-op. We don't wipe country choices on rollback.
    }
};
