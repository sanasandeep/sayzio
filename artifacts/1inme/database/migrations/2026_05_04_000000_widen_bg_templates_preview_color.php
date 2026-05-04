<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bg_templates', function (Blueprint $table) {
            $table->text('preview_color')->change();
        });
    }

    public function down(): void
    {
        Schema::table('bg_templates', function (Blueprint $table) {
            $table->string('preview_color', 200)->default('#1a1a2e')->change();
        });
    }
};
