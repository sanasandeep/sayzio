<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-generated Brand Kit visual assets (Task #5612).
 *
 * One row per (kit, type): regenerating replaces the previous image and
 * bumps `version`. The rendered PNG lives in the user's file vault
 * (`user_files`, S3-backed) via `user_file_id`; deleting the asset deletes
 * that file too.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('brand_kit_assets')) {
            return;
        }

        Schema::create('brand_kit_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_kit_id')->constrained('brand_kits')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);               // logo, favicon, letterhead, ...
            $table->string('status', 20)->default('ready'); // ready | failed
            $table->unsignedBigInteger('user_file_id')->nullable();
            $table->text('prompt')->nullable();       // optional user tweak instructions
            $table->json('params')->nullable();       // size, model, etc.
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('credits_spent')->default(0);
            $table->timestamps();

            $table->unique(['brand_kit_id', 'type']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_kit_assets');
    }
};
