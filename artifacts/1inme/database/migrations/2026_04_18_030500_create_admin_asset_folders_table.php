<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_asset_folders')) {
            Schema::create('admin_asset_folders', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_asset_folders');
    }
};
