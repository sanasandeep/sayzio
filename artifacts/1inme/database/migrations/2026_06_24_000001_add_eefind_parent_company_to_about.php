<?php

use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill the already-deployed `about` site page row with the new
 * "About EEFind" parent-company section. 1INME is a brand/product of
 * EEFIND PVT LTD (EEFind Private Limited); the public /about page renders
 * the lower sections from this row's `extra` override (which wins over the
 * code defaults), so without this backfill the live page would not carry
 * the EEFind block, its place in the section order, or its visibility flag.
 *
 * Idempotent and non-destructive: we only add the `eefind` payload when it
 * is missing, only insert the `eefind` slug into `section_order` when it is
 * absent (positioned right after the founder block), and only default its
 * visibility to true when unset. Any admin-customised EEFind copy, order or
 * visibility is preserved. The founder block is never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $about = SitePage::where('slug', 'about')->first();
        if (!$about) {
            return;
        }

        $extra = is_array($about->extra) ? $about->extra : [];
        $changed = false;

        // 1) Seed the EEFind content block if it has never been set.
        if (!array_key_exists('eefind', $extra) || !is_array($extra['eefind'] ?? null) || empty($extra['eefind'])) {
            $extra['eefind'] = SitePagesContent::aboutEefindDefault();
            $changed = true;
        }

        // 2) Insert the `eefind` slug into the saved section order (right
        //    after `founder` when present, otherwise appended) so the live
        //    page renders it in a sensible place instead of after the CTA.
        $validSlugs = SitePagesContent::aboutLowerSectionSlugs();
        $order = array_values(array_filter(
            (array) ($extra['section_order'] ?? []),
            fn ($s) => is_string($s) && in_array($s, $validSlugs, true)
        ));
        if (!empty($order) && !in_array('eefind', $order, true)) {
            $founderPos = array_search('founder', $order, true);
            if ($founderPos !== false) {
                array_splice($order, $founderPos + 1, 0, 'eefind');
            } else {
                $order[] = 'eefind';
            }
            $extra['section_order'] = array_values($order);
            $changed = true;
        }

        // 3) Default the EEFind section to visible if no flag was saved.
        $visibility = (array) ($extra['section_visibility'] ?? []);
        if (!empty($visibility) && !array_key_exists('eefind', $visibility)) {
            $visibility['eefind'] = true;
            $extra['section_visibility'] = $visibility;
            $changed = true;
        }

        if ($changed) {
            $about->extra = $extra;
            $about->save();
        }
    }

    public function down(): void
    {
        // Backfill / seed migration down() policy: the EEFind block may have
        // been edited by an admin after this ran, so we cannot reliably
        // reconstruct the prior (section-less) state. We leave the content in
        // place on rollback rather than risk wiping admin-customised copy.
    }
};
