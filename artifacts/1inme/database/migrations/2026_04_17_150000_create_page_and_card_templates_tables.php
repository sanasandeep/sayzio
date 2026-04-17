<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('category', 50)->default('general');
            $table->text('description')->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('plan_tier', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('snapshot');
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
            $table->index('category');
        });

        Schema::create('card_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('category', 50)->default('general');
            $table->text('description')->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('plan_tier', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('snapshot');
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_templates');
        Schema::dropIfExists('page_templates');
    }
};
