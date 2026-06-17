<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_stats', function (Blueprint $table) {
            $table->id();
            $table->string('label', 160);
            $table->string('value', 32);
            $table->string('suffix', 16)->nullable();
            $table->string('icon', 64)->default('fa-chart-line');
            $table->string('color', 16)->default('#7c3aed');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        DB::table('site_stats')->insert([
            ['icon' => 'fa-users',        'value' => '3.75 Lakh', 'suffix' => '+', 'label' => 'Users Worldwide',           'color' => '#1bd4d9', 'sort_order' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['icon' => 'fa-link',         'value' => '1,05,000',  'suffix' => '+', 'label' => 'Biolinks Created',          'color' => '#7c3aed', 'sort_order' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['icon' => 'fa-bolt',         'value' => '1,43 Lakh', 'suffix' => '+', 'label' => 'Analytics Events Tracked',  'color' => '#e94e8c', 'sort_order' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['icon' => 'fa-globe',        'value' => '67',        'suffix' => '+', 'label' => 'Countries Reached',         'color' => '#ff8a3c', 'sort_order' => 40, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['icon' => 'fa-qrcode',       'value' => '10,000',    'suffix' => '+', 'label' => 'QR codes generated',        'color' => '#ffc845', 'sort_order' => 50, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_stats');
    }
};
