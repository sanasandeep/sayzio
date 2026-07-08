<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Database\Seeders\SitePagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature coverage for the home-page "What you can create" link-types
 * showcase. The showcase is driven by the home SitePage row under
 * `extra.link_types` (admin-editable) with a shared defaults fallback.
 * There is no other automated coverage, so this guards the three moving
 * parts: the public fallback, the admin save/normalize path, and the
 * seeder's seed-only-when-missing behaviour.
 */
class HomeLinkTypesEditorTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function makeHomePage(array $extra = []): SitePage
    {
        $page = SitePage::firstOrNew(['slug' => 'home']);
        $page->fill([
            'title'            => 'Your link, your page, your audience. All in one.',
            'meta_description' => 'Seeded home page.',
            'sections'         => [],
            'extra'            => $extra,
        ])->save();
        return $page;
    }

    public function test_public_home_renders_default_cards_when_link_types_unset(): void
    {
        // Home page exists but has no extra.link_types — the public page
        // must fall back to the shared SitePagesContent defaults.
        $this->makeHomePage([]);

        $resp = $this->get('/');
        $resp->assertOk();

        $defaults = SitePagesContent::homeLinkTypesDefault();
        // Assert a couple of unique default descriptions render so we know
        // it's the defaults (not just incidental matching text).
        $resp->assertSee($defaults[0]['desc'], false); // Short Link
        $resp->assertSee(
            'A chat-style page that greets visitors and guides them through your links one message at a time.',
            false
        );
        // Every default card name renders.
        foreach ($defaults as $lt) {
            $resp->assertSee($lt['name'], false);
        }
    }

    public function test_public_home_renders_default_cards_when_link_types_empty(): void
    {
        // An explicitly-empty list (e.g. an admin cleared every row) must
        // also fall back to the defaults rather than render an empty grid.
        $this->makeHomePage(['link_types' => []]);

        $resp = $this->get('/');
        $resp->assertOk();
        $resp->assertSee(SitePagesContent::homeLinkTypesDefault()[0]['desc'], false);
    }

    public function test_admin_save_persists_normalized_link_types_and_public_reflects_edits(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makeHomePage(['link_types' => SitePagesContent::homeLinkTypesDefault()]);

        // Submit two real cards (in a deliberate order), plus edge cases:
        //  - a fully-blank row (no name, no desc) -> dropped
        //  - "new" as the string '1' -> coerced to bool true
        //  - "new" as '0' -> coerced to bool false
        //  - an invalid accent colour -> defaulted to #3d6bff
        //  - a blank icon -> defaulted to fa-link
        $payload = [
            'title'            => 'Your link, your page, your audience. All in one.',
            'meta_description' => 'Updated home page.',
            'sections'         => [],
            'extra' => [
                'link_types' => [
                    [
                        'name'  => 'Zebra Link',
                        'desc'  => 'Custom zebra description that is unique.',
                        'icon'  => 'fa-paw',
                        'color' => '#123abc',
                        'new'   => '1',
                    ],
                    [
                        'name'  => 'Apple Link',
                        'desc'  => 'Custom apple description that is unique.',
                        'icon'  => '', // blank -> fa-link
                        'color' => 'red', // invalid -> #3d6bff
                        'new'   => '0',
                    ],
                    // Fully-blank row -> dropped.
                    ['name' => '', 'desc' => '', 'icon' => '', 'color' => '', 'new' => '1'],
                ],
            ],
        ];

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/home', $payload);
        $resp->assertRedirect(route('admin.site-pages.edit', 'home'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertIsArray($page->extra);
        $stored = $page->extra['link_types'] ?? null;
        $this->assertIsArray($stored);

        // Blank row dropped: only the two real cards survive.
        $this->assertCount(2, $stored);

        // Order preserved exactly as submitted.
        $this->assertSame(['Zebra Link', 'Apple Link'], array_column($stored, 'name'));

        // "new" coerced to real booleans.
        $this->assertTrue($stored[0]['new']);
        $this->assertFalse($stored[1]['new']);

        // Valid colour kept; invalid colour defaulted.
        $this->assertSame('#123abc', $stored[0]['color']);
        $this->assertSame('#3d6bff', $stored[1]['color']);

        // Blank icon defaulted.
        $this->assertSame('fa-paw', $stored[0]['icon']);
        $this->assertSame('fa-link', $stored[1]['icon']);

        // The persisted shape equals what the normaliser produces for the
        // same input — a true round-trip.
        $this->assertEquals(
            SitePagesContent::normalizeHomeLinkTypes($payload['extra']['link_types']),
            $stored
        );

        // Public home reflects the edits, in order, and no longer shows the
        // default first-card description.
        $publicResp = $this->get('/');
        $publicResp->assertOk();
        $publicResp->assertSee('Zebra Link', false);
        $publicResp->assertSee('Apple Link', false);
        $publicResp->assertSee('Custom zebra description that is unique.', false);
        $publicResp->assertDontSee(SitePagesContent::homeLinkTypesDefault()[0]['desc'], false);

        // Order: the first submitted card renders before the second.
        $html = $publicResp->getContent();
        $this->assertLessThan(
            strpos($html, 'Apple Link'),
            strpos($html, 'Zebra Link'),
            'Submitted card order should be preserved on the public page.'
        );
    }

    public function test_featured_split_falls_back_to_first_six_for_legacy_data(): void
    {
        // Legacy data: no row carries a `featured` key at all — the split
        // must be positional (first 6 big, rest compact), so pre-existing
        // admin overrides render exactly as before.
        $items = [];
        for ($i = 1; $i <= 9; $i++) {
            $items[] = ['name' => "Type {$i}", 'icon' => 'fa-link', 'color' => '#3d6bff', 'new' => false, 'desc' => "Desc {$i}"];
        }

        $split = SitePagesContent::splitHomeLinkTypesFeatured($items);
        $this->assertSame(
            ['Type 1', 'Type 2', 'Type 3', 'Type 4', 'Type 5', 'Type 6'],
            array_column($split['featured'], 'name')
        );
        $this->assertSame(
            ['Type 7', 'Type 8', 'Type 9'],
            array_column($split['more'], 'name')
        );
        // Original keys are preserved (public stagger seeds from them).
        $this->assertSame([6, 7, 8], array_keys($split['more']));
    }

    public function test_featured_split_honours_explicit_flags_with_cap(): void
    {
        // Explicit flags: featured rows go to the headline tier regardless
        // of position; flags beyond the cap overflow into the compact tier.
        $items = [];
        for ($i = 1; $i <= 9; $i++) {
            $items[] = [
                'name'     => "Type {$i}",
                'icon'     => 'fa-link',
                'color'    => '#3d6bff',
                'new'      => false,
                'desc'     => "Desc {$i}",
                // Feature types 2..9 (8 flagged, cap is 6).
                'featured' => $i >= 2,
            ];
        }

        $split = SitePagesContent::splitHomeLinkTypesFeatured($items);
        $this->assertSame(
            ['Type 2', 'Type 3', 'Type 4', 'Type 5', 'Type 6', 'Type 7'],
            array_column($split['featured'], 'name'),
            'Flags win over position, capped at ' . SitePagesContent::HOME_LINK_TYPES_FEATURED_CAP . ' in list order.'
        );
        $this->assertSame(
            ['Type 1', 'Type 8', 'Type 9'],
            array_column($split['more'], 'name')
        );
    }

    public function test_admin_save_persists_featured_flags_and_public_renders_flagged_split(): void
    {
        $admin = $this->makeAdmin();
        $this->makeHomePage([]);

        // Seven cards; only the LAST is featured — under the old positional
        // rule it would be a compact tile, with the flag it must be the big
        // (and only) headline card.
        $linkTypes = [];
        for ($i = 1; $i <= 7; $i++) {
            $linkTypes[] = [
                'name'     => "Flagged Type {$i}",
                'desc'     => "Flagged description {$i}.",
                'icon'     => 'fa-link',
                'color'    => '#123abc',
                'new'      => '0',
                'featured' => $i === 7 ? '1' : '0',
            ];
        }

        $resp = $this->actingAs($admin, 'admin')->put('/admin/site-pages/home', [
            'title'            => 'Home',
            'meta_description' => 'Home page.',
            'sections'         => [],
            'extra'            => ['link_types' => $linkTypes],
        ]);
        $resp->assertSessionHasNoErrors();

        $stored = SitePage::firstWhere('slug', 'home')->extra['link_types'];
        $this->assertCount(7, $stored);
        $this->assertFalse($stored[0]['featured']);
        $this->assertTrue($stored[6]['featured']);

        $publicResp = $this->get('/');
        $publicResp->assertOk();
        $html = $publicResp->getContent();

        // The interactive spotlight replaces the old two-tier card grid.
        // All types appear in the chip rail and info panes; the stage + rail
        // markup is present. No more showcase-card-lg / showcase-card-sm tiers.
        $this->assertStringContainsString(
            'lt-rail',
            $html,
            'The interactive chip rail must render.'
        );
        $this->assertStringContainsString(
            'lt-stage',
            $html,
            'The spotlight stage must render.'
        );

        // Every type name from the stored data must appear in the rendered HTML
        // (chips, info panes, and mock visuals all embed the type name).
        foreach ($stored as $lt) {
            $this->assertStringContainsString(
                $lt['name'],
                $html,
                "Type \"{$lt['name']}\" must appear in the spotlight markup."
            );
        }

        // The explicitly-flagged type (Type 7) is present — the spotlight shows
        // all types regardless of the featured flag, which is preserved in data.
        $this->assertStringContainsString(
            'Flagged Type 7',
            $html,
            'The explicitly-flagged type must appear in the spotlight.'
        );
    }

    public function test_saved_showcase_split_matches_public_home_featured_and_strip_exactly(): void
    {
        // End-to-end drift guard: save the editor with an overflow flag set
        // (more flagged rows than the cap allows) and assert the public home
        // renders EXACTLY the tiers splitHomeLinkTypesFeatured() computes —
        // same names, same order, in the right tier. If the PHP split ever
        // changes (cap, flag parsing, ordering) this pins the public page to
        // the persisted data end-to-end.
        $admin = $this->makeAdmin();
        $this->makeHomePage([]);

        // 9 rows; flag rows 2..9 (8 flagged, cap is 6) so the split must
        // overflow rows 8 and 9 into the compact strip alongside row 1.
        $linkTypes = [];
        for ($i = 1; $i <= 9; $i++) {
            $linkTypes[] = [
                'name'     => "Split Type {$i}",
                'desc'     => "Split description {$i}.",
                'icon'     => 'fa-link',
                'color'    => '#123abc',
                'new'      => '0',
                'featured' => $i >= 2 ? '1' : '0',
            ];
        }

        $resp = $this->actingAs($admin, 'admin')->put('/admin/site-pages/home', [
            'title'            => 'Home',
            'meta_description' => 'Home page.',
            'sections'         => [],
            'extra'            => ['link_types' => $linkTypes],
        ]);
        $resp->assertSessionHasNoErrors();

        // The split source of truth is the PERSISTED rows, exactly as the
        // public blade computes it.
        $stored = SitePage::firstWhere('slug', 'home')->extra['link_types'];
        $split  = SitePagesContent::splitHomeLinkTypesFeatured($stored);
        $expectedFeatured = array_column($split['featured'], 'name');
        $expectedMore     = array_column($split['more'], 'name');
        // Sanity: the fixture really exercises the overflow path.
        $this->assertSame(
            ['Split Type 2', 'Split Type 3', 'Split Type 4', 'Split Type 5', 'Split Type 6', 'Split Type 7'],
            $expectedFeatured
        );
        $this->assertSame(['Split Type 1', 'Split Type 8', 'Split Type 9'], $expectedMore);

        $publicResp = $this->get('/');
        $publicResp->assertOk();
        $html = $publicResp->getContent();

        // Tier card counts match the split.
        $this->assertSame(count($expectedFeatured), substr_count($html, 'showcase-card-lg'));
        $this->assertSame(count($expectedMore), substr_count($html, 'showcase-card-sm'));

        // The "And plenty more" divider separates the tiers: every featured
        // name must render before it, every strip name after it.
        $dividerPos = strpos($html, 'And plenty more');
        $this->assertNotFalse($dividerPos, 'Compact strip divider must render when the split has overflow.');

        $namesInOrder = static function (string $haystack, array $names): array {
            $positions = [];
            foreach ($names as $name) {
                $pos = strpos($haystack, $name);
                $positions[$name] = $pos;
            }
            return $positions;
        };

        $featuredPositions = $namesInOrder($html, $expectedFeatured);
        foreach ($featuredPositions as $name => $pos) {
            $this->assertNotFalse($pos, "Featured card '{$name}' must render.");
            $this->assertLessThan($dividerPos, $pos, "Featured card '{$name}' must render in the big tier (before the strip divider).");
        }
        // Featured order preserved exactly as the split emits it.
        $sorted = array_values($featuredPositions);
        for ($i = 1; $i < count($sorted); $i++) {
            $this->assertGreaterThan($sorted[$i - 1], $sorted[$i], 'Featured cards must render in split order.');
        }

        $morePositions = $namesInOrder($html, $expectedMore);
        foreach ($morePositions as $name => $pos) {
            $this->assertNotFalse($pos, "Strip tile '{$name}' must render.");
            $this->assertGreaterThan($dividerPos, $pos, "Strip tile '{$name}' must render in the compact strip (after the divider).");
        }
        $sortedMore = array_values($morePositions);
        for ($i = 1; $i < count($sortedMore); $i++) {
            $this->assertGreaterThan($sortedMore[$i - 1], $sortedMore[$i], 'Strip tiles must render in split order.');
        }
    }

    public function test_editor_alpine_mirror_uses_php_featured_cap(): void
    {
        // The editor's live preview mirrors the split client-side in Alpine.
        // The cap it uses must be injected from the PHP constant so the two
        // can never drift on the cap value.
        $admin = $this->makeAdmin();
        $this->makeHomePage(['link_types' => SitePagesContent::homeLinkTypesDefault()]);

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.site-pages.edit', 'home'));
        $resp->assertOk();
        $resp->assertSee(
            'featuredCap: ' . SitePagesContent::HOME_LINK_TYPES_FEATURED_CAP,
            false
        );
    }

    public function test_seeder_seeds_default_link_types_only_when_missing(): void
    {
        // Fresh seed: no home page yet — the seeder creates it and seeds the
        // default link-types showcase.
        $this->assertNull(SitePage::firstWhere('slug', 'home'));

        (new SitePagesSeeder())->run();

        $home = SitePage::firstWhere('slug', 'home');
        $this->assertNotNull($home);
        $this->assertEquals(
            SitePagesContent::homeLinkTypesDefault(),
            $home->extra['link_types'] ?? null
        );
    }

    public function test_seeder_does_not_clobber_admin_edited_link_types(): void
    {
        // Admin has customised the showcase to a single card. A re-seed must
        // never overwrite it.
        $custom = [
            ['name' => 'Only Card', 'icon' => 'fa-star', 'color' => '#abcdef', 'new' => true, 'desc' => 'Just one.'],
        ];
        $this->makeHomePage(['link_types' => $custom]);

        (new SitePagesSeeder())->run();

        $home = SitePage::firstWhere('slug', 'home');
        $this->assertNotNull($home);
        $this->assertEquals($custom, $home->extra['link_types'] ?? null);
    }
}
