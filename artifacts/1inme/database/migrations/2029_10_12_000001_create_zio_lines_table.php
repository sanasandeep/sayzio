<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The lines Zio speaks in the homepage hero bubble.
 *
 * They were hardcoded in home/partials/hero.blade.php, so changing a word of
 * the first thing a visitor reads meant a deploy. Same shape as site_stats:
 * a short string, an on/off flag and an order.
 *
 * `text` is capped at 120 rather than the usual 160 because the bubble is a
 * fixed ellipse -- text has to be laid INSIDE a curve, not measured around
 * it -- and past roughly two lines it stops fitting the shape. The admin
 * form warns well before the column does.
 */
return new class extends Migration
{
    /** The four lines that were hardcoded in the blade, so nothing changes on deploy. */
    private const SEED = [
        "Hi, I'm Zio 👋",
        'I build your link page, QR codes and short links.',
        'Then I answer your visitors, and pick up your calls.',
        'Free forever. Want to try me?',
    ];

    public function up(): void
    {
        Schema::create('zio_lines', function (Blueprint $table) {
            $table->id();
            $table->string('text', 120);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        DB::table('zio_lines')->insert(array_map(fn ($text, $i) => [
            'text'       => $text,
            'is_active'  => true,
            'sort_order' => $i * 10,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::SEED, array_keys(self::SEED)));
    }

    public function down(): void
    {
        Schema::dropIfExists('zio_lines');
    }
};
