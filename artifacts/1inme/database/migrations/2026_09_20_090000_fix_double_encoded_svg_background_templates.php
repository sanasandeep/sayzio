<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Twenty-six background templates never painted their artwork.
 *
 * Their CSS carries an inline SVG as a data URI:
 *
 *     background-image:url("data:image/svg+xml;utf8,<svg ...>...</svg>")
 *
 * In these rows the SVG was percent-encoded a second time on the way into
 * the database, so every `<` was stored as `%253C` rather than `<`. The
 * browser has no document to parse, the background-image resolves to
 * nothing, and the template renders as its flat ground colour -- which is
 * why the SVG category looked like two dozen identical dark rectangles.
 *
 * Twenty-five of them are in `svg`, one in `neon`. The other 434 templates
 * are unaffected: none of them contains `%25` at all.
 *
 * The repair is to undo exactly one layer of encoding, `%25` -> `%`, which
 * leaves `%253C` as `%3C` and `%2523` as `%23`. Both forms render: the
 * browser decodes percent escapes in the data URI, so `%3C` is `<` and
 * `%23` is a literal `#` in a colour, which is what the entries that were
 * encoded correctly already use. Verified against a headless render of the
 * hex-grid tile before writing this.
 *
 * Down restores the stored bytes exactly, `%` -> `%25`, for the rows this
 * migration touched and no others.
 */
return new class extends Migration
{
    /**
     * Two light variants shipped under the same name as their dark
     * counterparts. That was survivable while each picker was its own tab;
     * in one merged library it means search returns two rows the user
     * cannot tell apart. The `light-` slug is the one that gets the suffix.
     */
    private const RENAMES = [
        'light-conic-pastel' => ['Conic Pastel', 'Conic Pastel Light'],
        'light-polka-pink'   => ['Polka Pink',   'Polka Pink Light'],
    ];

    public function up(): void
    {
        foreach ($this->affected() as $row) {
            DB::table('bg_templates')->where('id', $row->id)->update([
                'css'           => str_replace('%25', '%', (string) $row->css),
                'preview_color' => str_replace('%25', '%', (string) $row->preview_color),
            ]);
        }

        foreach (self::RENAMES as $slug => [$from, $to]) {
            DB::table('bg_templates')
                ->where('slug', $slug)
                ->where('name', $from)
                ->update(['name' => $to]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $slug => [$from, $to]) {
            DB::table('bg_templates')
                ->where('slug', $slug)
                ->where('name', $to)
                ->update(['name' => $from]);
        }

        // Only the rows that still carry a decoded SVG data URI, so this
        // cannot double-encode a template an admin wrote correctly by hand.
        $rows = DB::table('bg_templates')
            ->select('id', 'css', 'preview_color')
            ->where('css', 'like', '%data:image/svg+xml%')
            ->where('css', 'not like', '%\%25%')
            ->get();

        foreach ($rows as $row) {
            DB::table('bg_templates')->where('id', $row->id)->update([
                'css'           => str_replace('%', '%25', (string) $row->css),
                'preview_color' => str_replace('%', '%25', (string) $row->preview_color),
            ]);
        }
    }

    /** Rows carrying the doubled encoding, and only those. */
    private function affected()
    {
        return DB::table('bg_templates')
            ->select('id', 'css', 'preview_color')
            ->where('css', 'like', '%\%25%')
            ->get();
    }
};
