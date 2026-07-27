<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('page_templates', 'design_locked')) {
            Schema::table('page_templates', function (Blueprint $table) {
                $table->boolean('design_locked')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('page_templates', 'design_locked')) {
            Schema::table('page_templates', function (Blueprint $table) {
                $table->dropColumn('design_locked');
            });
        }
    }
};
