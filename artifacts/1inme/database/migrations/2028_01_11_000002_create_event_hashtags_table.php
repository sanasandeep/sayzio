<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Task #3654: admin-managed predefined hashtags for the /events directory.
 * These are listed first in the directory's hashtag row, ahead of the
 * auto-computed trending tags, so admins can steer discovery instead of
 * relying purely on whatever happens to be popular right now. Seeded with
 * a small set of generically useful defaults so the list isn't empty.
 */
return new class extends Migration
{
    private const DEFAULT_TAGS = [
        'live', 'music', 'networking', 'free', 'family-friendly', 'workshop',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('event_hashtags')) {
            Schema::create('event_hashtags', function (Blueprint $table) {
                $table->id();
                $table->string('tag', 60)->unique();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if ((int) DB::table('event_hashtags')->count() === 0) {
            $order = 0;
            foreach (self::DEFAULT_TAGS as $tag) {
                DB::table('event_hashtags')->insert([
                    'tag'        => $tag,
                    'sort_order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_hashtags');
    }
};
