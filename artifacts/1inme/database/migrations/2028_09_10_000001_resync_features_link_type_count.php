<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Put the real link-type count back into the stored /features description.
 *
 * The page says "all N link types" and then names them. The code default was
 * corrected to 19 -- Text Page had been missing since it was added -- but the
 * page does not render the default: it renders the site_pages row, and that
 * row still said 18.
 *
 * There is already a migration meant to keep the two in step
 * (2028_01_09_000001), and it did nothing, for a reason worth writing down.
 * It only rewrites the row when the stored text still looks like the sentence
 * it expects:
 *
 *     /^Everything you get with Sayzio — all \d+ link types/
 *
 * with an em dash. The sentence now uses a colon, because the em dashes were
 * taken out of the marketing copy afterwards. So the guard stopped matching,
 * returned early every time it ran, and the field it was written to maintain
 * quietly stopped being maintained -- a safety check that had turned into an
 * off switch.
 *
 * This one matches on the part of the sentence that is not punctuation, and
 * only when the stored text is still recognisably the generated one. A
 * description an admin has rewritten is left alone: their words, their page.
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('site_pages')->where('slug', 'features')->first();

        if (! $row || ! is_string($row->meta_description) || $row->meta_description === '') {
            return;
        }

        // The stable shape: the opening clause, then "all <n> link types",
        // whatever sits between them.
        if (! preg_match('/^Everything you get with Sayzio\b.{0,4}\ball \d+ link types\b/u', $row->meta_description)) {
            return;
        }

        $fresh = SitePagesContent::richDefaults()['features']['meta_description'] ?? null;

        if (! is_string($fresh) || $fresh === '' || $fresh === $row->meta_description) {
            return;
        }

        DB::table('site_pages')->where('slug', 'features')->update([
            'meta_description' => $fresh,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Non-destructive: an accurate count is not something to roll back to
        // an inaccurate one.
    }
};
