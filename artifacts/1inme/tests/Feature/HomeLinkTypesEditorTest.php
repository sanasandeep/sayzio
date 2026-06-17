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
        //  - an invalid accent colour -> defaulted to #7c3aed
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
                        'color' => 'red', // invalid -> #7c3aed
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
        $this->assertSame('#7c3aed', $stored[1]['color']);

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
