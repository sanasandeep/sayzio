<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the features SitePage row's sections with the structured
 * categories shape used by the new /features page. Existing installs
 * had either no row, an empty sections array, or the old plain
 * heading/body sections from the previous rich-defaults migration —
 * none of which match what the new blade renders. Admins who already
 * authored their own structured categories (rows with a `features`
 * sub-array) are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $structured = SitePagesContent::featuresCategoriesDefault();
        $payload = json_encode($structured);

        $row = DB::table('site_pages')->where('slug', 'features')->first();

        if (!$row) {
            DB::table('site_pages')->insert([
                'slug' => 'features',
                'title' => 'Features',
                'meta_description' => 'A complete tour of every capability inside 1INME — biolinks, short links, QR codes, analytics, inboxes, teams, billing, and more.',
                'sections' => $payload,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return;
        }

        $current = json_decode($row->sections ?? '[]', true) ?: [];
        if ($this->isAlreadyStructured($current)) {
            return;
        }

        DB::table('site_pages')->where('slug', 'features')->update([
            'sections' => $payload,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Non-destructive: leave the seeded content in place on rollback.
    }

    private function isAlreadyStructured(array $sections): bool
    {
        if (empty($sections)) {
            return false;
        }
        foreach ($sections as $row) {
            if (!is_array($row)) {
                return false;
            }
            if (!array_key_exists('features', $row) || !is_array($row['features'])) {
                return false;
            }
        }
        return true;
    }
};
