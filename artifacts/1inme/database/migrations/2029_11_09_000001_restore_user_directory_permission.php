<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Older production installs did not run the fresh-install seeder.
        // Restore the catalog entry only; never grant it to existing roles.
        if (!DB::table('permissions')->where('slug', 'users.view')->exists()) {
            DB::table('permissions')->insert([
                'slug' => 'users.view', 'name' => 'View Users', 'group' => 'Users',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // An operator may have assigned this capability after deployment.
        // Keep the catalog entry and those assignments on rollback.
    }
};
