<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Take the em dashes out of the marketing copy that lives in the database.
 *
 * The homepage copy was moved out of Blade and into admin-editable tables a
 * while back, which is the right call -- but it means a copy rule cannot be
 * enforced by editing views alone. Two of those tables hold sentences that
 * appear on the homepage:
 *
 *   zio_lines     the four lines Zio speaks in the hero bubble
 *   testimonials  the quotes in the social-proof row
 *
 * Their seed arrays have been corrected in the create migrations, so a fresh
 * install is already right. Existing installs are not: those rows were
 * written years of migrations ago and are the ones actually on screen. This
 * migration rewrites them in place.
 *
 * Deliberately narrow:
 *
 *   - Only the columns that render as prose. An accent colour or a sort
 *     order has no punctuation to fix, and a blanket UPDATE over every text
 *     column is how you corrupt something you did not think about.
 *   - Only rows that actually contain the character, so the vast majority
 *     of installs touch nothing and `updated_at` stays meaningful.
 *   - Admin edits are respected: an em dash typed by a human is still an em
 *     dash we do not want on the page, so it is rewritten too -- but the
 *     surrounding wording, whatever it now says, is left alone.
 *
 * The substitution matches the one applied to the views: a dash with spaces
 * around it becomes a comma, a dash without them becomes a hyphen. That is
 * not always the most elegant sentence, but it is always grammatical, and
 * the copy is editable in admin for anyone who wants to do better.
 *
 * Irreversible by design. `down()` cannot know which commas used to be
 * dashes, and guessing would corrupt copy that was always a comma.
 */
return new class extends Migration
{
    /** table => the columns on it that render as prose. */
    private const COPY = [
        'zio_lines'    => ['text'],
        'testimonials' => ['quote', 'author_role'],
    ];

    public function up(): void
    {
        foreach (self::COPY as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->where($column, 'like', '%' . self::DASH . '%')
                    ->orderBy('id')
                    ->chunkById(200, function ($rows) use ($table, $column) {
                        foreach ($rows as $row) {
                            DB::table($table)
                                ->where('id', $row->id)
                                ->update([$column => self::rewrite($row->{$column})]);
                        }
                    });
            }
        }
    }

    public function down(): void
    {
        // Not reversible: see the class comment.
    }

    private const DASH = "\u{2014}";

    /** A spaced dash is a clause break (comma); a bare one joins words (hyphen). */
    private static function rewrite(?string $value): ?string
    {
        if ($value === null || ! str_contains($value, self::DASH)) {
            return $value;
        }

        $value = preg_replace('/\s+' . self::DASH . '\s+/u', ', ', $value);

        return str_replace(self::DASH, '-', $value);
    }
};
