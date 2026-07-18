<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores pairs of contacts the user has explicitly marked "not a duplicate"
 * so the duplicate-detection engine never re-flags the same pair.
 *
 * Pairs are stored in canonical order (smaller id first) so a single
 * unique index prevents both orientations from being inserted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_dismissed_pairs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            // Canonical order: contact_id_a < contact_id_b always.
            $table->unsignedBigInteger('contact_id_a');
            $table->unsignedBigInteger('contact_id_b');
            $table->timestamp('dismissed_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            // No FK on contact_id_* — if a contact is deleted the dismissal
            // record becomes inert and will never match again; a cleanup job
            // can prune orphan rows lazily.
            $table->unique(['user_id', 'contact_id_a', 'contact_id_b'], 'contact_dismissed_pairs_unique');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_dismissed_pairs');
    }
};
