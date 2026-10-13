<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_action_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->string('purpose', 40);
            $table->text('notes');
            $table->string('source_name')->nullable();
            $table->json('result');
            $table->json('actions')->nullable();
            $table->unsignedInteger('coins_spent')->default(0);
            $table->string('model')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_action_plans');
    }
};
