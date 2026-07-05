<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Task #3654: event categories move from the hardcoded `EventCategories`
 * list to an admin-managed table so admins can add/edit/disable them
 * without a code change. Seeds every entry from the previous curated
 * list (same slug/label/icon/colors) so nothing already stored on events
 * (`settings['event_category']`) stops resolving.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('event_categories')) {
            Schema::create('event_categories', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 60)->unique();
                $table->string('name', 100);
                $table->string('icon', 60)->default('fa-calendar-star');
                $table->string('color_from', 20)->default('#3d6bff');
                $table->string('color_to', 20)->default('#2342c7');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }

        if ((int) DB::table('event_categories')->count() === 0) {
            $order = 0;
            foreach (\App\Modules\User\Support\EventCategories::DEFAULTS as $slug => $meta) {
                DB::table('event_categories')->insert([
                    'slug'       => $slug,
                    'name'       => $meta['label'],
                    'icon'       => $meta['icon'],
                    'color_from' => $meta['color'][0],
                    'color_to'   => $meta['color'][1],
                    'sort_order' => $order++,
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_categories');
    }
};
