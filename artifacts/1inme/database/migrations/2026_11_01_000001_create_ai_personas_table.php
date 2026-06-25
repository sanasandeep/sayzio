<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved persona library for the AI · Persona feature.
 *
 * Each row keeps the original brief (audience / goals / tone) and the
 * generated markdown profile so users can re-use a persona later
 * without spending credits to regenerate it.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ai_personas')) {
            Schema::create('ai_personas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('audience', 400);
                $table->string('goals', 600)->nullable();
                $table->string('tone', 200)->nullable();
                $table->text('content');
                $table->string('model', 64)->nullable();
                $table->timestamps();

                $table->index(['user_id', 'updated_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_personas');
    }
};
