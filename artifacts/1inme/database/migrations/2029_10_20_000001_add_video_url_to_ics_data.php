<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One video per event.
 *
 * The embed renderers already exist -- common/blocks/youtube.blade.php and
 * friends have been serving biolink pages all along. The event page simply
 * had no field to point at them, so this adds the field and the page does
 * the rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ics_data', function (Blueprint $table) {
            $table->string('video_url', 2048)->nullable()->after('cover_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('ics_data', function (Blueprint $table) {
            if (Schema::hasColumn('ics_data', 'video_url')) {
                $table->dropColumn('video_url');
            }
        });
    }
};
