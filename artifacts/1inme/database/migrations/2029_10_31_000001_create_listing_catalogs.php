<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('listing_catalogs', function (Blueprint $table) {
            $table->id(); $table->foreignId('link_id')->unique()->constrained('links')->cascadeOnDelete();
            $table->json('settings')->nullable(); $table->timestamps();
        });
        Schema::create('listing_categories', function (Blueprint $table) {
            $table->id(); $table->foreignId('catalog_id')->constrained('listing_catalogs')->cascadeOnDelete();
            $table->string('name'); $table->unsignedInteger('sort_order')->default(0); $table->timestamps();
        });
        Schema::create('listing_entries', function (Blueprint $table) {
            $table->id(); $table->foreignId('catalog_id')->constrained('listing_catalogs')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('listing_categories')->nullOnDelete();
            $table->string('title'); $table->json('details')->nullable();
            $table->boolean('is_active')->default(false); $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0); $table->timestamps();
        });
        Schema::create('listing_inquiries', function (Blueprint $table) {
            $table->id(); $table->foreignId('catalog_id')->constrained('listing_catalogs')->cascadeOnDelete();
            $table->foreignId('entry_id')->nullable()->constrained('listing_entries')->nullOnDelete();
            $table->string('entry_title'); $table->string('name'); $table->string('email');
            $table->string('phone',40)->nullable(); $table->text('message')->nullable();
            $table->date('preferred_date')->nullable(); $table->string('batch')->nullable();
            $table->string('status',20)->default('new'); $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('listing_inquiries'); Schema::dropIfExists('listing_entries');
        Schema::dropIfExists('listing_categories'); Schema::dropIfExists('listing_catalogs');
    }
};
