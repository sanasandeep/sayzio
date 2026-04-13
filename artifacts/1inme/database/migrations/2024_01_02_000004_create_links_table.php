<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('url');
            $table->string('alias')->unique();
            $table->string('title')->nullable();
            $table->text('long_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();

            $table->string('password')->nullable();
            $table->boolean('is_password_protected')->default(false);

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_image')->nullable();

            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();

            $table->jsonb('settings')->nullable();

            $table->unsignedBigInteger('total_clicks')->default(0);
            $table->unsignedBigInteger('unique_clicks')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'project_id']);
            $table->index(['alias']);
            $table->index(['is_active', 'expires_at']);
        });

        Schema::create('link_pixels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pixel_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['link_id', 'pixel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_pixels');
        Schema::dropIfExists('links');
    }
};
