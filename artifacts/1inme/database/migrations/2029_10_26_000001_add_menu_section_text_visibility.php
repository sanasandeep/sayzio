<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['restaurant_menu_categories', 'store_categories'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (['hide_heading', 'hide_description'] as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->boolean($column)->default(false));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['restaurant_menu_categories', 'store_categories'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (['hide_heading', 'hide_description'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
                }
            }
        }
    }
};
