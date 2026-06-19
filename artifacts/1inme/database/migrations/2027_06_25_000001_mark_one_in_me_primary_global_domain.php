<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `1in.me` is the platform's primary global domain. On databases that
        // ran the original global-domain seed migration before `1in.me` was
        // promoted, the row exists but `is_primary` is false. Promote it here
        // — but only when no global domain has already been flagged primary,
        // so an admin's manual choice is never silently overridden. Safe to
        // re-run.
        if (!Schema::hasColumn('domains', 'is_primary')) {
            return;
        }

        $alreadyPrimary = DB::table('domains')
            ->whereNull('user_id')
            ->where('is_primary', true)
            ->exists();

        if ($alreadyPrimary) {
            return;
        }

        DB::table('domains')
            ->whereNull('user_id')
            ->where('domain', '1in.me')
            ->update(['is_primary' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (!Schema::hasColumn('domains', 'is_primary')) {
            return;
        }

        DB::table('domains')
            ->whereNull('user_id')
            ->where('domain', '1in.me')
            ->update(['is_primary' => false, 'updated_at' => now()]);
    }
};
