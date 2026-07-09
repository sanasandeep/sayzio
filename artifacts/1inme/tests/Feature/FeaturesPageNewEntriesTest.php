<?php

namespace Tests\Feature;

use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Guards the newer Features-page entries so a future refactor of
 * SitePagesContent::featuresCategoriesDefault() (or the site_pages
 * seeder) cannot silently drop them:
 *  - "Audience Insights" inside the `analytics` category
 *  - the `cross-platform` category, specifically its
 *    "Extension notifications" and "Mobile share-sheet import" rows
 *
 * Covers three layers:
 *  1. the raw default catalogue (what the seeder writes on a re-seed)
 *  2. the public /features page rendered from a freshly seeded row
 *  3. the controller's append-missing-categories path, so instances
 *     with admin-edited content still surface the new category
 */
class FeaturesPageNewEntriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the features row exactly like the site_pages seeder does
     * (updateOrCreate with the default catalogue as `sections`).
     */
    private function seedFeaturesPage(?array $sections = null): SitePage
    {
        $page = SitePage::updateOrCreate(
            ['slug' => 'features'],
            [
                'title'            => 'Everything you can do with Sayzio',
                'meta_description' => 'Seeded features page.',
                'sections'         => $sections ?? SitePagesContent::featuresCategoriesDefault(),
            ]
        );
        // Drop the per-slug attribute cache so the public request
        // reads this row, not a stale cached copy.
        Cache::forget(SitePage::SLUG_CACHE_PREFIX . 'features');

        return $page;
    }

    public function test_default_catalogue_contains_new_entries(): void
    {
        $categories = SitePagesContent::featuresCategoriesDefault();
        $byId = collect($categories)->keyBy('id');

        $this->assertTrue($byId->has('analytics'), 'analytics category missing from featuresCategoriesDefault()');
        $analyticsNames = array_column($byId['analytics']['features'], 'name');
        $this->assertContains('Audience Insights', $analyticsNames);

        $this->assertTrue($byId->has('cross-platform'), 'cross-platform category missing from featuresCategoriesDefault()');
        $crossNames = array_column($byId['cross-platform']['features'], 'name');
        $this->assertContains('Extension notifications', $crossNames);
        $this->assertContains('Mobile share-sheet import', $crossNames);
    }

    public function test_features_page_renders_new_entries_after_reseed(): void
    {
        $this->seedFeaturesPage();

        $resp = $this->get('/features');

        $resp->assertOk();
        $resp->assertSee('Audience Insights');
        $resp->assertSee('Cross-platform tools');
        $resp->assertSee('Extension notifications');
        $resp->assertSee('Mobile share-sheet import');
    }

    public function test_features_page_appends_cross_platform_for_admin_edited_content(): void
    {
        // An instance whose stored sections predate the new category
        // (admin-edited content without `cross-platform`): the show()
        // path must append missing default categories by id.
        $stored = array_values(array_filter(
            SitePagesContent::featuresCategoriesDefault(),
            fn (array $cat) => ($cat['id'] ?? '') !== 'cross-platform'
        ));
        $this->seedFeaturesPage($stored);

        $resp = $this->get('/features');

        $resp->assertOk();
        $resp->assertSee('Cross-platform tools');
        $resp->assertSee('Extension notifications');
        $resp->assertSee('Mobile share-sheet import');
    }
}
