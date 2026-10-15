<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->unsignedBigInteger('default_brand_kit_id')->nullable());
    }
    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('default_brand_kit_id'));
    }
};
