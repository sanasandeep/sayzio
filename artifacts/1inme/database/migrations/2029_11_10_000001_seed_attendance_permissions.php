<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['review' => 'Review internal attendance and requests', 'manage' => 'Manage internal attendance policy', 'export' => 'Export internal attendance'] as $action => $name) {
            if (! DB::table('permissions')->where('slug', 'attendance.'.$action)->exists()) {
                DB::table('permissions')->insert(['slug' => 'attendance.'.$action, 'name' => $name, 'group' => 'Staff attendance', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    { /* Keep operator-edited permission grants. */
    }
};
