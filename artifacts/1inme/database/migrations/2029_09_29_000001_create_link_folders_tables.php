<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('link_folders')) {
            Schema::create('link_folders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 120);
                $table->string('color', 32)->default('blue');
                $table->timestamps();
                $table->index(['user_id', 'workspace_id']);
            });
        }

        if (! Schema::hasTable('link_folder_links')) {
            Schema::create('link_folder_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_folder_id')->constrained('link_folders')->cascadeOnDelete();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['link_folder_id', 'link_id']);
                $table->index('link_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('link_folder_links');
        Schema::dropIfExists('link_folders');
    }
};
