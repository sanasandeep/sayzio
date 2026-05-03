<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fonts', function (Blueprint $table) {
            $table->id();
            // Owning user — when the user is deleted their fonts go too. We
            // also key uniqueness on (user_id, family) so the picker never
            // shows duplicate "My Fonts" entries.
            $table->unsignedBigInteger('user_id');
            $table->string('family', 80);
            $table->string('original_name', 200);
            // Storage disk + relative path. Mirrors UserFile so we can reuse
            // the user_files / public disk for serving.
            $table->string('disk', 20)->default('public');
            $table->string('path', 500);
            // 'woff2' | 'woff' | 'truetype' | 'opentype' — used in @font-face
            // src format() declarations on the public biolink page.
            $table->string('format', 20);
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'family']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fonts');
    }
};
