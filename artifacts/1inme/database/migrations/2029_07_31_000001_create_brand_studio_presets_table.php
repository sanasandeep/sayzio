<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved kit combinations for AI Brand Studio (Task #5577) — a user-named
 * reusable composition (kinds/counts/purposes) that appears alongside the
 * built-in presets in the kit composer on web and mobile.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('brand_studio_presets')) {
            return;
        }

        Schema::create('brand_studio_presets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name', 60);
            $table->json('composition'); // list of {kind, count, purpose}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_studio_presets');
    }
};
