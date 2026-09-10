<?php

namespace Tests\Feature;

use App\Modules\Common\Support\SitePagesContent;
use Database\Seeders\LinkTypeExplainerSeeder;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the marketing link-type showcase against silent drift between its
 * several lockstep surfaces:
 *
 *  - the home "What you can create" cards (homeLinkTypesDefault),
 *  - the /features "Link types" category (featuresLinkTypesFromSections),
 *  - the /demos explainer pages (LinkTypeExplainerSeeder), whose aliases must
 *    equal `demo-type-` . Str::slug(name) for the /demos controller to match
 *    them to a showcase row, and
 *  - the /features meta_description "all N link types" count.
 *
 * Without these assertions, adding a type to one list but forgetting another
 * only shows up as a missing /demos card or a wrong count when someone eyeballs
 * the page. These are pure, DB-free assertions over the code defaults.
 */
class MarketingLinkTypeShowcaseSyncTest extends TestCase
{
    public function test_home_and_features_link_type_lists_have_same_names_in_same_order(): void
    {
        $homeNames = array_map(
            fn ($row) => (string) ($row['name'] ?? ''),
            SitePagesContent::homeLinkTypesDefault()
        );

        // Pass an empty sections payload so the helper falls back to the
        // built-in features default — the same source the home editor syncs from.
        $featuresNames = array_map(
            fn ($row) => (string) ($row['name'] ?? ''),
            SitePagesContent::featuresLinkTypesFromSections([])
        );

        $this->assertSame(
            $homeNames,
            $featuresNames,
            'The home "What you can create" cards and the /features "Link types" '
            .'category must list the same link types in the same order.'
        );
    }

    public function test_every_showcase_name_has_a_matching_explainer_demo_page(): void
    {
        $showcaseNames = array_map(
            fn ($row) => (string) ($row['name'] ?? ''),
            SitePagesContent::homeLinkTypesDefault()
        );

        $seededAliases = array_map(
            fn ($page) => (string) ($page['alias'] ?? ''),
            $this->explainerPages()
        );

        foreach ($showcaseNames as $name) {
            $expectedAlias = 'demo-type-' . Str::slug($name);
            $this->assertContains(
                $expectedAlias,
                $seededAliases,
                "Showcase link type \"{$name}\" has no LinkTypeExplainerSeeder page "
                ."with alias \"{$expectedAlias}\" — the /demos card for it would be "
                .'missing (or fall back to a description-less unmatched card). The '
                .'seeder alias must equal `demo-type-` . Str::slug(name).'
            );
        }
    }

    public function test_features_meta_description_count_matches_the_showcase_length(): void
    {
        $count = count(SitePagesContent::homeLinkTypesDefault());

        $meta = (string) (SitePagesContent::richDefaults()['features']['meta_description'] ?? '');

        $this->assertMatchesRegularExpression(
            '/all '.$count.' link types/i',
            $meta,
            "The /features meta_description must say \"all {$count} link types\" to "
            .'match the number of showcase link types.'
        );
    }

    /**
     * And the page has to say it too.
     *
     * The check above reads the code default. The page does not: when a
     * site_pages row exists -- which it does on every install past its first
     * day -- the row is what renders, and the default is only a fallback.
     *
     * So the two can disagree, and did. The default was corrected to 19 and
     * this test went green while the live page still said 18, because nothing
     * asked the page. It is the page Google reads.
     */
    public function test_the_rendered_features_page_says_the_real_count(): void
    {
        $count = count(SitePagesContent::homeLinkTypesDefault());

        // With a stored row, because that is the case that goes wrong.
        //
        // A fresh test database has no site_pages rows, so the page falls
        // back to the code default and this passes without touching the thing
        // it is about. Every real install has the row, and the row is what
        // renders. Seeded with a deliberately stale count so a page that
        // echoes the row unchanged fails here.
        \App\Modules\Common\Models\SitePage::updateOrCreate(
            ['slug' => 'features'],
            [
                'title' => 'Features',
                'meta_description' => 'Everything you get with Sayzio: all 3 link types (short links).',
            ]
        );

        $this->artisan('migrate', ['--path' => 'database/migrations/2028_09_10_000001_resync_features_link_type_count.php', '--force' => true]);

        $html = $this->get('/features')->assertOk()->getContent();

        preg_match_all('/all (\d+) link types/i', (string) $html, $found);

        $this->assertNotEmpty($found[1], '/features no longer states a link-type count at all');

        $wrong = array_values(array_unique(array_filter(
            $found[1],
            fn ($n) => (int) $n !== $count
        )));

        $this->assertSame([], $wrong, sprintf(
            "/features renders \"all %s link types\" but there are %d.\n\n"
            . "The number lives in two places: the code default in SitePagesContent, and\n"
            . "the site_pages row that actually renders. Correcting the default is not\n"
            . "enough -- the row needs a migration to carry the change across, the way\n"
            . "2028_09_10_000001 does.",
            implode('/', $wrong),
            $count
        ));
    }

    /**
     * The LinkTypeExplainerSeeder::pages() definition is private; read it via
     * reflection so the test asserts against the real seeded aliases.
     *
     * @return array<int, array<string, mixed>>
     */
    private function explainerPages(): array
    {
        $method = new ReflectionMethod(LinkTypeExplainerSeeder::class, 'pages');
        $method->setAccessible(true);

        return $method->invoke(new LinkTypeExplainerSeeder());
    }
}
