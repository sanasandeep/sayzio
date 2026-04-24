<?php

use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill the new About `extra` groups (hero / values / story_images /
 * section_titles / cta) into the already-deployed `about` site page row
 * so that switching to the new admin editor does not blank out anything
 * on the public /about page. Existing groups (founder, co_founders,
 * team, milestones, blog_block) are preserved as-is — we only add keys
 * the row doesn't have yet.
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
        $defaults = SitePagesContent::aboutExtraDefault();

        $newGroups = ['hero', 'values', 'story_images', 'section_titles', 'cta'];
        $changed = false;
        foreach ($newGroups as $key) {
            if (!array_key_exists($key, $extra)) {
                $extra[$key] = $defaults[$key];
                $changed = true;
            }
        }

        if ($changed) {
            $about->extra = $extra;
            $about->save();
        }
    }

    public function down(): void
    {
        // Per CONTRIBUTING.md "Backfill / seed migration down() policy":
        // only strip the keys we added in up() if their value is still
        // structurally equal to the seeded default. Any drift means an
        // admin edited the group via the new editor and we must preserve
        // their copy.
        $about = SitePage::where('slug', 'about')->first();
        if (!$about || !is_array($about->extra)) {
            return;
        }

        $extra = $about->extra;
        $defaults = SitePagesContent::aboutExtraDefault();
        $changed = false;
        foreach (['hero', 'values', 'story_images', 'section_titles', 'cta'] as $key) {
            if (!array_key_exists($key, $extra) || !array_key_exists($key, $defaults)) {
                continue;
            }
            if ($extra[$key] == $defaults[$key]) {
                unset($extra[$key]);
                $changed = true;
            }
        }
        if ($changed) {
            $about->extra = $extra;
            $about->save();
        }
    }
};
