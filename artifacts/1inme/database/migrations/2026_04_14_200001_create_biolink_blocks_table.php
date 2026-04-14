<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biolink_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
            $table->string('type', 50);
            $table->jsonb('settings')->default('{}');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();

            $table->index(['link_id', 'sort_order']);
            $table->index(['link_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biolink_blocks');
    }
};
