<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('verification_tick_types')) {
            Schema::create('verification_tick_types', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('color', 20)->default('#1d9bf0');
                $table->string('icon', 50)->default('fa-check-circle');
                $table->boolean('admin_assigned_only')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });

            DB::table('verification_tick_types')->insert([
                ['slug' => 'team',         'name' => 'Official',     'color' => '#1d9bf0', 'icon' => 'fa-check-circle', 'admin_assigned_only' => true,  'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['slug' => 'government',   'name' => 'Government',   'color' => '#8b9eb7', 'icon' => 'fa-landmark',     'admin_assigned_only' => false, 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
                ['slug' => 'ngo',          'name' => 'NGO / Charity','color' => '#00ba7c', 'icon' => 'fa-heart',        'admin_assigned_only' => false, 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
                ['slug' => 'company',      'name' => 'Company',      'color' => '#f4b400', 'icon' => 'fa-building',     'admin_assigned_only' => false, 'is_active' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
                ['slug' => 'creator',      'name' => 'Creator',      'color' => '#9c6afe', 'icon' => 'fa-star',         'admin_assigned_only' => false, 'is_active' => true, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_tick_types');
    }
};
