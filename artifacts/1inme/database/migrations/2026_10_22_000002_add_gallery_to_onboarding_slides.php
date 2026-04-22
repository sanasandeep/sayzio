<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('onboarding_slides', function (Blueprint $table) {
            // Stores an ordered list of image paths (relative to the
            // `public` disk, e.g. "onboarding/creators_2.png"). When
            // present and non-empty the mobile splash renders these as
            // an auto-rotating image carousel within the slide.
            $table->jsonb('gallery_images')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_slides', function (Blueprint $table) {
            $table->dropColumn('gallery_images');
        });
    }
};
