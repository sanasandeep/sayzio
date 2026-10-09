<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('contact_directories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->unique()->constrained('links')->cascadeOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
        Schema::create('directory_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('directory_id')->constrained('contact_directories')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('directory_categories')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('directory_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('directory_id')->constrained('contact_directories')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('directory_categories')->nullOnDelete();
            $table->string('name');
            $table->json('details')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('directory_contacts');
        Schema::dropIfExists('directory_categories');
        Schema::dropIfExists('contact_directories');
    }
};
