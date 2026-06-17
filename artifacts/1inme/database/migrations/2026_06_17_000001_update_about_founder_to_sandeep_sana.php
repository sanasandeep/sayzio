<?php

use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;

/**
 * Refresh the featured founder on the already-deployed `about` site page
 * row to the real founder, Sandeep Sana (Founder & CEO of 1INME). The
 * public /about page renders from this row's `extra.founder` override,
 * which wins over the code defaults, so the live page would keep showing
 * the old placeholder ("Aarav Reddy") without this backfill.
 *
 * Guarded to only overwrite the row while it still holds the seeded
 * placeholder founder, so any admin-customised founder copy is preserved.
 */
return new class extends Migration
{
    private const OLD_NAME = 'Aarav Reddy';

    public function up(): void
    {
        $about = SitePage::where('slug', 'about')->first();
        if (!$about) {
            return;
        }

        $extra = is_array($about->extra) ? $about->extra : [];
        $currentName = trim((string) ($extra['founder']['name'] ?? ''));

        // Preserve any admin-customised founder; only replace the placeholder
        // (or a missing/empty founder) with the real founder defaults.
        if ($currentName !== '' && $currentName !== self::OLD_NAME) {
            return;
        }

        $defaults = SitePagesContent::aboutExtraDefault();
        $extra['founder'] = $defaults['founder'];

        $about->extra = $extra;
        $about->save();
    }

    public function down(): void
    {
        // Per CONTRIBUTING.md "Backfill / seed migration down() policy":
        // only revert if the founder still structurally equals the value we
        // wrote in up(). Any drift means an admin edited it and we preserve
        // their copy. We cannot reconstruct the prior placeholder reliably,
        // so we leave the real founder in place on rollback.
    }
};
