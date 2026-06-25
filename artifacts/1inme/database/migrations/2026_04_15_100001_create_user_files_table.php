<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_files')) {
            Schema::create('user_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('original_name');
                $table->string('filename');
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size_bytes');
                $table->string('type', 20)->default('image');
                $table->string('disk', 20)->default('public');
                $table->string('path', 500);
                $table->timestamps();

                $table->index(['user_id', 'type']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_files');
    }
};
