<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('android_apk_releases')) {
            return;
        }

        Schema::create('android_apk_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version_name', 64);
            $table->string('build_number', 64)->nullable();
            $table->bigInteger('file_size_bytes')->unsigned()->default(0);
            $table->string('disk', 64)->default('public');
            $table->string('path', 500);
            $table->string('eas_build_id', 128)->nullable();
            $table->string('source_url', 2000)->nullable();
            $table->unsignedBigInteger('published_by_admin_id')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_live')->default(false);
            $table->timestamps();

            $table->index('is_live');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('android_apk_releases');
    }
};
