<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ab_variants')) {
            Schema::create('ab_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
                $table->string('label', 120)->nullable();
                $table->text('url');
                $table->unsignedSmallInteger('weight')->default(50);
                $table->unsignedInteger('visitors')->default(0);
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->boolean('is_winner')->default(false);
                $table->timestamps();

                $table->index(['link_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_variants');
    }
};
