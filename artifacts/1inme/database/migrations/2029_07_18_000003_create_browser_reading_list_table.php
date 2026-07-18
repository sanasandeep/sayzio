<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('browser_reading_list')) {
            Schema::create('browser_reading_list', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('local_id', 64)->index();
                $table->text('url');
                $table->text('normalized_url');
                $table->string('title');
                $table->string('favicon_url')->nullable();
                $table->boolean('is_read')->default(false);
                $table->boolean('deleted')->default(false);
                $table->timestamp('item_updated_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'local_id']);
                $table->index(['user_id', 'deleted']);
                $table->index(['user_id', 'is_read']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('browser_reading_list');
    }
};
