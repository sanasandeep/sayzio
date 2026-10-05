<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['restaurant_menu_items', 'store_products'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'bulk_price')) {
                Schema::table($table, fn (Blueprint $t) => $t->decimal('bulk_price', 12, 2)->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['restaurant_menu_items', 'store_products'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'bulk_price')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('bulk_price'));
            }
        }
    }
};
