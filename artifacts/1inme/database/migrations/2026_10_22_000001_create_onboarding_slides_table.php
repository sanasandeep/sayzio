<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile-app onboarding/splash slider content. Admin-managed so
 * marketing can change copy + imagery without shipping a new build.
 *
 * `image_path` is a public-disk relative path (served via storage:link
 * at /storage/<image_path>); the API resource turns it into an
 * absolute URL for the mobile client.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('onboarding_slides')) {
            Schema::create('onboarding_slides', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('category', 80);
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('image_path')->nullable();
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['status', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_slides');
    }
};
