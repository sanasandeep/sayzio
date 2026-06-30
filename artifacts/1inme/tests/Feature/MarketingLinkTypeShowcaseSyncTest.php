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
