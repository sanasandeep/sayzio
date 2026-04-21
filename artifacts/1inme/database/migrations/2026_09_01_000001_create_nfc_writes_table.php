<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nfc_writes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
            $table->string('written_url', 2048);
            $table->string('tag_uid', 80)->nullable();
            $table->string('tag_type', 40)->nullable();
            $table->integer('tag_capacity_bytes')->nullable();
            $table->boolean('locked')->default(false);
            $table->string('device', 120)->nullable();
            $table->string('platform', 20)->nullable(); // ios | android
            $table->string('label', 120)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['link_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfc_writes');
    }
};
