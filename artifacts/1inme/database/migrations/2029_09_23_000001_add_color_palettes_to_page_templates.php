<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-defined color palettes for design-locked page templates.
 *
 * Each row stores a JSON array of palettes:
 *   [{ key, name, colors: { background_type?, background_color?, gradient_colors?,
 *      gradient_angle?, font_color?, button_color?, button_text_color?,
 *      bg_overlay_color?, bg_overlay_opacity? } }, ...]
 *
 * Design-locked pages let creators pick one of these palettes instead of
 * free-form styling; the first palette is the template default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('page_templates', 'color_palettes')) {
            Schema::table('page_templates', function (Blueprint $table) {
                $table->json('color_palettes')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('page_templates', 'color_palettes')) {
            Schema::table('page_templates', function (Blueprint $table) {
                $table->dropColumn('color_palettes');
            });
        }
    }
};
