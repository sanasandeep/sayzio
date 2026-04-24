<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Task #762: makes the entire /contact page editable from the admin —
 * mirroring what task #751 did for /about. This migration backfills the
 * new keys we just added to SitePagesContent::contactExtraDefault()
 * (hero, details_heading, feature_cards, office_image, form) onto the
 * existing `contact` row in `site_pages`, seeded with today's hard-coded
 * literals so currently-deployed sites do not visually change after the
 * deploy and admins see the existing copy in the new editor fields.
 *
 * Existing values for shared keys (address/email/phone/hours/social/map
 * and any user-customised values that happen to overlap) are preserved
 * — the new keys only fill gaps via array_replace_recursive.
 */
return new class extends Migration {
    public function up(): void
    {
        $row = DB::table('site_pages')->where('slug', 'contact')->first();
        if (!$row) {
            // No contact row yet (fresh install before initial seeder runs);
            // the seeder/install path will produce the new defaults itself.
            return;
        }

        $existing = [];
        if (!empty($row->extra)) {
            $decoded = is_string($row->extra) ? json_decode($row->extra, true) : $row->extra;
            if (is_array($decoded)) $existing = $decoded;
        }

        $defaults = SitePagesContent::contactExtraDefault();

        // Only top-level keys we introduced in this task should be merged in;
        // we do *not* clobber any user-edited values for the pre-existing
        // address/email/phone/hours/social/map keys.
        $newKeys = ['hero', 'details_heading', 'feature_cards', 'office_image', 'form'];
        foreach ($newKeys as $key) {
            if (!array_key_exists($key, $existing)) {
                $existing[$key] = $defaults[$key];
                continue;
            }
            // For nested associative groups (hero, office_image, form), deep-merge
            // so any sub-keys the admin had not seen before still get a sensible
            // starting value. feature_cards is a list — leave it untouched if
            // the admin already saved something.
            if ($key === 'feature_cards' || $key === 'details_heading') {
                continue;
            }
            if (is_array($existing[$key]) && is_array($defaults[$key])) {
                $existing[$key] = array_replace_recursive($defaults[$key], $existing[$key]);
            }
        }

        DB::table('site_pages')
            ->where('id', $row->id)
            ->update([
                'extra'      => json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // One-shot, additive backfill — rolling it back is intentionally a no-op
        // because removing keys would silently drop admin-edited copy.
    }
};
