<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Realign the seeded "Brand / Press Kit" explainer page alias with the slug
 * the marketing showcase derives.
 *
 * The /demos controller looks each card up by `demo-type-` . Str::slug(name),
 * and "Brand / Press Kit" slugs to `brand-press-kit`. The LinkTypeExplainerSeeder
 * previously seeded the page under `demo-type-brand-kit`, so the demos card
 * never matched the showcase row (it surfaced via the unmatched-demo fallback
 * with no description). The seeder now uses `demo-type-brand-press-kit`; this
 * renames any already-seeded row so installs do not end up with a duplicate
 * (or description-less) card after the seeder runs again.
 *
 * Guarded + additive: only renames when the old alias exists and the new one
 * does not, so it never collides with a freshly seeded correct row and never
 * touches an unrelated link.
 */
return new class extends Migration
{
    public function up(): void
    {
        $old = 'demo-type-brand-kit';
        $new = 'demo-type-brand-press-kit';

        $oldRow = DB::table('links')->where('alias', $old)->first();
        if (!$oldRow) {
            return;
        }

        $newExists = DB::table('links')->where('alias', $new)->exists();
        if ($newExists) {
            return;
        }

        DB::table('links')->where('id', $oldRow->id)->update([
            'alias' => $new,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Non-destructive: leave the realigned alias in place on rollback.
    }
};
