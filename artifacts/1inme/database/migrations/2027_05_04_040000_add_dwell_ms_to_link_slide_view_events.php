<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_slide_view_events', function (Blueprint $table) {
            // Time the viewer spent on the slide before moving on, in
            // milliseconds. Null on the original "entry" ping; populated
            // on a follow-up "exit" ping the player sends when the slide
            // is unmounted or scrolled past. Splitting entry/exit into
            // two rows keeps existing impression counts honest while
            // letting analytics compute average dwell time per slide
            // without altering the entry-event semantics.
            $table->unsignedInteger('dwell_ms')->nullable()->after('completed');
            $table->index(['deck_id', 'slide_index', 'dwell_ms']);
        });
    }

    public function down(): void
    {
        Schema::table('link_slide_view_events', function (Blueprint $table) {
            $table->dropIndex(['deck_id', 'slide_index', 'dwell_ms']);
            $table->dropColumn('dwell_ms');
        });
    }
};
