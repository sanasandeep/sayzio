<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\BgCatalogEntry;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Support\BackgroundLibrary;
use App\Modules\User\Support\BgPresetCatalog;
use App\Modules\User\Support\CatalogOverrides;
use App\Modules\User\Support\GradientCatalog;
use App\Modules\User\Support\MeshGradientCatalog;
use App\Modules\User\Support\PatternCatalog;
use App\Modules\User\Support\TilesBgCatalog;
use App\Modules\User\Support\TornStyleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The half of the background library that needed an engineer.
 *
 * /admin/bg-templates already managed 525 looks. The other 485 -- presets,
 * gradients, mesh, patterns, tiles, torn -- were PHP constants, so "add a
 * tiles palette" and "hide that gradient" were both deploys. That is the
 * gap this closes, and the constants stay exactly where they are: a row
 * adds a look, replaces one, or withdraws one from the picker.
 *
 * Three properties matter more than the CRUD, and each has a test here:
 *
 *  1. HIDING IS NOT DELETING. A page that saved a look two years ago keeps
 *     rendering it after an admin hides it. Anything else means retiring a
 *     look silently breaks pages.
 *
 *  2. DELETING THE ROW RESTORES THE DEFAULT. This is what makes editing a
 *     shipped look safe to offer at all -- the original is still in the
 *     code, one click away.
 *
 *  3. THE CACHE FOLLOWS THE WRITE. Every catalog read on the platform goes
 *     through a forever-cache. A save that does not bust it is a change
 *     nobody ever sees.
 *
 * TheShippedBackgroundCatalogsStillResolveTest covers the other half: that
 * with no rows at all, 485 looks render byte-for-byte as before.
 */
class BackgroundLooksAreEditableWithoutADeployTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CatalogOverrides::flush();
    }

    private function admin(): Admin
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'guard' => 'admin']);

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin'.uniqid().'@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    // ===== It is reachable at all =====

    /** Every catalog has a tab, and the tab shows its whole library. */
    public function test_every_catalog_is_browsable(): void
    {
        $admin = $this->admin();

        $expected = [
            'preset'     => 179,
            'gradient'   => 166,
            'mesh'       => 10,
            'pattern'    => 12,
            'tiles'      => 52,
            'torn'       => 60,
            'torn_style' => 6,
        ];

        foreach ($expected as $kind => $count) {
            $html = $this->actingAs($admin, 'admin')
                ->get(route('admin.bg-catalog.index', $kind))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('New ', $html);
            // The chip row states the size of each library.
            $this->assertStringContainsString((string) $count, $html,
                "the {$kind} tab does not show its {$count} looks");
        }
    }

    /** It is behind the same permission as the template manager. */
    public function test_a_signed_out_visitor_cannot_reach_it(): void
    {
        $this->get(route('admin.bg-catalog.index', 'mesh'))->assertRedirect();
        $this->post(route('admin.bg-catalog.toggle', ['mesh', 'mesh_aurora']))->assertRedirect();
    }

    // ===== Adding =====

    /** A brand new look appears in the picker with no deploy. */
    public function test_an_admin_can_add_a_look_that_did_not_exist(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(route('admin.bg-catalog.store', 'mesh'), [
            'entry_key' => 'mesh_twilight',
            'label'     => 'Twilight',
            'base'      => '#101024',
            'blobs'     => "#22d3ee 15 20 55\n#a78bfa 80 15 50",
            'is_active' => 1,
        ])->assertRedirect();

        CatalogOverrides::flush();

        $this->assertArrayHasKey('mesh_twilight', MeshGradientCatalog::all());
        $this->assertStringContainsString('#22d3ee', (string) MeshGradientCatalog::css('mesh_twilight'));
        $this->assertTrue(MeshGradientCatalog::isValidKey('mesh_twilight'),
            'a new look the renderer will not accept is not a new look');

        $inLibrary = array_filter(
            BackgroundLibrary::items(collect()),
            fn ($i) => $i['type'] === 'mesh' && $i['value'] === 'mesh_twilight'
        );
        $this->assertCount(1, $inLibrary, 'it must show up in the creator-facing library');
    }

    /** Each catalog's own shape is validated rather than stored as typed. */
    public function test_a_malformed_look_is_refused(): void
    {
        $admin = $this->admin();

        $cases = [
            // A gradient with one stop is a colour.
            ['gradient', ['entry_key' => 'g1', 'label' => 'G', 'category' => 'warm', 'type' => 'linear', 'angle' => 90, 'stops' => '#ff0000 0']],
            // An angle outside 0-360.
            ['gradient', ['entry_key' => 'g2', 'label' => 'G', 'category' => 'warm', 'type' => 'linear', 'angle' => 900, 'stops' => "#ff0000 0\n#0000ff 100"]],
            // A colour that is not a colour.
            ['mesh', ['entry_key' => 'm1', 'label' => 'M', 'base' => 'red', 'blobs' => '#ff0000 10 10 50']],
            // A torn colourway is two colours, not one.
            ['torn', ['entry_key' => 't1', 'label' => 'T', 'style' => 'diagonal', 'paper' => '#ffffff', 'backdrop' => '#111111']],
            // A tear shape with no sheets clips nothing.
            ['torn_style', ['entry_key' => 'ts1', 'label' => 'TS', 'sheets' => '']],
            // A tear shape whose "clip" is not one.
            ['torn_style', ['entry_key' => 'ts2', 'label' => 'TS', 'sheets' => 'url(evil)']],
            // A key with characters that would not survive a URL.
            ['pattern', ['entry_key' => 'Bad Key!', 'label' => 'P', 'css' => 'background: #000', 'colors' => '#000000']],
        ];

        foreach ($cases as [$kind, $payload]) {
            $this->actingAs($admin, 'admin')
                ->post(route('admin.bg-catalog.store', $kind), $payload)
                ->assertSessionHasErrors();
        }

        $this->assertSame(0, BgCatalogEntry::count(), 'none of those should have been stored');
    }

    /**
     * CSS typed here is written into a <style> block on every page that
     * picks the look, so it cannot be allowed to close one.
     */
    public function test_css_that_could_break_out_of_the_page_is_refused(): void
    {
        $admin = $this->admin();

        foreach ([
            'background: #000}</style><script>alert(1)</script>',
            'background: #000; } body { display:none',
            'background: url(<svg onload=alert(1)>)',
        ] as $css) {
            $this->actingAs($admin, 'admin')->post(route('admin.bg-catalog.store', 'pattern'), [
                'entry_key' => 'pattern_evil',
                'label'     => 'Evil',
                'css'       => $css,
                'colors'    => '#000000',
            ])->assertSessionHasErrors('css');
        }

        $this->assertSame(0, BgCatalogEntry::count());
    }

    /** Two looks cannot share a key, because the key is what pages save. */
    public function test_a_key_that_is_already_taken_is_refused(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(route('admin.bg-catalog.store', 'pattern'), [
            'entry_key' => 'pattern_dots_dark',
            'label'     => 'Mine',
            'css'       => 'background: #000',
            'colors'    => '#000000',
        ])->assertSessionHasErrors('entry_key');
    }

    // ===== Overriding, and undoing it =====

    /** Editing a shipped look changes what it renders. */
    public function test_editing_a_shipped_look_changes_what_pages_render(): void
    {
        $admin = $this->admin();
        $before = PatternCatalog::css('pattern_dots_dark');

        $this->actingAs($admin, 'admin')
            ->put(route('admin.bg-catalog.update', ['pattern', 'pattern_dots_dark']), [
                'label'     => 'Dots Darker',
                'css'       => 'background-color: #000000;background-image: radial-gradient(#fff 2px, transparent 2px)',
                'colors'    => '#000000, #ffffff',
                'is_active' => 1,
            ])->assertRedirect();

        CatalogOverrides::flush();

        $this->assertNotSame($before, PatternCatalog::css('pattern_dots_dark'));
        $this->assertSame('Dots Darker', PatternCatalog::all()['pattern_dots_dark']['label']);
    }

    /**
     * ...and deleting the row puts the shipped version back.
     *
     * This is the property that makes editing a compiled-in look safe to
     * offer: nothing here can lose a default.
     */
    public function test_deleting_the_override_restores_the_shipped_look(): void
    {
        $admin = $this->admin();
        $shipped = PatternCatalog::css('pattern_dots_dark');

        $this->actingAs($admin, 'admin')
            ->put(route('admin.bg-catalog.update', ['pattern', 'pattern_dots_dark']), [
                'label' => 'Mine', 'css' => 'background: #abcdef', 'colors' => '#abcdef', 'is_active' => 1,
            ]);
        CatalogOverrides::flush();
        $this->assertSame('background: #abcdef', PatternCatalog::css('pattern_dots_dark'));

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.bg-catalog.destroy', ['pattern', 'pattern_dots_dark']))
            ->assertRedirect();
        CatalogOverrides::flush();

        $this->assertSame($shipped, PatternCatalog::css('pattern_dots_dark'));
    }

    /** A gradient override keeps its place in the list rather than moving to the end. */
    public function test_an_overridden_gradient_stays_where_it_was(): void
    {
        $admin = $this->admin();
        $positionBefore = array_search('aurora', array_column(GradientCatalog::all(), 'id'), true);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.bg-catalog.update', ['gradient', 'aurora']), [
                'label' => 'Aurora II', 'category' => 'cool', 'type' => 'linear', 'angle' => 200,
                'stops' => "#00c9ff 0\n#92fe9d 100", 'is_active' => 1,
            ]);
        CatalogOverrides::flush();

        $ids = array_column(GradientCatalog::all(), 'id');
        $this->assertSame($positionBefore, array_search('aurora', $ids, true),
            'editing a gradient must not reshuffle the picker');
        $this->assertSame(166, count($ids), 'an override must replace, not append');
        $this->assertSame('Aurora II', GradientCatalog::findById('aurora')['name']);
    }

    // ===== Hiding, which is not deleting =====

    /**
     * The property this whole feature turns on: a hidden look leaves the
     * picker and keeps rendering for the pages that already chose it.
     */
    public function test_hiding_a_look_leaves_existing_pages_alone(): void
    {
        $admin = $this->admin();
        $shipped = PatternCatalog::css('pattern_dots_dark');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.bg-catalog.toggle', ['pattern', 'pattern_dots_dark']))
            ->assertRedirect();
        CatalogOverrides::flush();

        // Gone from the picker...
        $inLibrary = array_filter(
            BackgroundLibrary::items(collect()),
            fn ($i) => $i['type'] === 'pattern' && $i['value'] === 'pattern_dots_dark'
        );
        $this->assertSame([], $inLibrary, 'a hidden look must not be offered to anyone new');

        // ...and still rendering, unchanged, for everyone who has it.
        $this->assertSame($shipped, PatternCatalog::css('pattern_dots_dark'),
            'hiding a look must never change what a page using it renders');
        $this->assertTrue(PatternCatalog::isValidKey('pattern_dots_dark'),
            'and the renderer must still accept it');
    }

    /** Hiding is reversible, and the shipped payload survives the round trip. */
    public function test_showing_it_again_puts_it_back_unchanged(): void
    {
        $admin = $this->admin();
        $shipped = MeshGradientCatalog::css('mesh_aurora');

        foreach ([1, 2] as $_) {
            $this->actingAs($admin, 'admin')->post(route('admin.bg-catalog.toggle', ['mesh', 'mesh_aurora']));
            CatalogOverrides::flush();
        }

        $this->assertSame($shipped, MeshGradientCatalog::css('mesh_aurora'),
            'hiding and showing a shipped look must not quietly rewrite it');

        $back = array_filter(
            BackgroundLibrary::items(collect()),
            fn ($i) => $i['type'] === 'mesh' && $i['value'] === 'mesh_aurora'
        );
        $this->assertCount(1, $back);
    }

    /** Each catalog can be hidden from; none was wired up and forgotten. */
    public function test_hiding_works_for_every_catalog_in_the_picker(): void
    {
        $admin = $this->admin();

        $samples = [
            'preset'   => array_key_first(BgPresetCatalog::pickerPresets()),
            'gradient' => GradientCatalog::all()[0]['id'],
            'mesh'     => array_key_first(MeshGradientCatalog::shipped()),
            'pattern'  => array_key_first(PatternCatalog::shipped()),
            'tiles'    => array_key_first(TilesBgCatalog::shipped()),
            'torn'     => array_key_first(TornStyleCatalog::shippedPresets()),
        ];

        foreach ($samples as $kind => $key) {
            $this->actingAs($admin, 'admin')->post(route('admin.bg-catalog.toggle', [$kind, $key]));
        }
        CatalogOverrides::flush();

        $items = BackgroundLibrary::items(collect());
        foreach ($samples as $kind => $key) {
            $still = array_filter($items, fn ($i) => $i['type'] === $kind && $i['value'] === $key);
            $this->assertSame([], $still, "a hidden {$kind} is still in the library");
        }
    }

    // ===== The cache =====

    /**
     * Catalog reads are cached forever, so a write that does not bust the
     * cache is a change nobody ever sees. Here the cache is warmed first,
     * deliberately, and no flush() is called afterwards.
     */
    public function test_a_save_is_visible_without_anyone_clearing_a_cache(): void
    {
        $admin = $this->admin();

        // Warm it.
        $this->assertArrayNotHasKey('pattern_new', PatternCatalog::all());

        $this->actingAs($admin, 'admin')->post(route('admin.bg-catalog.store', 'pattern'), [
            'entry_key' => 'pattern_new',
            'label'     => 'New',
            'css'       => 'background: #abcdef',
            'colors'    => '#abcdef',
            'is_active' => 1,
        ])->assertRedirect();

        // Drop only this process's memo -- NOT the cache, which is the thing
        // under test. Calling flush() here would forget the cache itself and
        // make this pass whether or not the save ever busted it.
        $memo = new \ReflectionProperty(CatalogOverrides::class, 'memo');
        $memo->setAccessible(true);
        $memo->setValue(null, null);

        $this->assertArrayHasKey('pattern_new', PatternCatalog::all(),
            'the write did not reach the cache, so the platform would keep serving the old library');
    }

    // ===== Tiles and torn, the two with no DB path at all before this =====

    /** A new tiles palette renders its own gradients across the 24-tile grid. */
    public function test_a_new_tiles_palette_reaches_the_grid(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(route('admin.bg-catalog.store', 'tiles'), [
            'entry_key' => 'tiles_house',
            'label'     => 'House',
            'colors'    => '#ff0000, #00ff00',
            'tiles'     => "linear-gradient(135deg, #ff0000, #990000)\nlinear-gradient(135deg, #00ff00, #009900)",
            'is_active' => 1,
        ])->assertRedirect();
        CatalogOverrides::flush();

        $tiles = TilesBgCatalog::tiles('tiles_house', 'metro');
        $this->assertCount(TilesBgCatalog::TILE_COUNT, $tiles);
        $this->assertSame('linear-gradient(135deg, #ff0000, #990000)', $tiles[0]['css']);
        $this->assertSame('linear-gradient(135deg, #00ff00, #009900)', $tiles[1]['css'],
            'the grid must cycle through the palette it was given');
        $this->assertSame(['#ff0000', '#00ff00'], TilesBgCatalog::colors('tiles_house'));
    }

    /** A new tear shape is offered to torn looks and resolves to its sheets. */
    public function test_a_new_tear_shape_can_be_used_by_a_torn_look(): void
    {
        $admin = $this->admin();
        $clip  = 'polygon(0% 0%, 100% 0%, 100% 90%, 0% 80%)';

        $this->actingAs($admin, 'admin')->post(route('admin.bg-catalog.store', 'torn_style'), [
            'entry_key' => 'slant',
            'label'     => 'Slant',
            'sheets'    => $clip.' | 0.9',
            'is_active' => 1,
        ])->assertRedirect();
        CatalogOverrides::flush();

        $this->assertTrue(TornStyleCatalog::isValidStyle('slant'));
        $this->assertSame([['clip' => $clip, 'shade' => 0.9]], TornStyleCatalog::sheets('slant'));

        $this->actingAs($admin, 'admin')->post(route('admin.bg-catalog.store', 'torn'), [
            'entry_key' => 'house_slant',
            'label'     => 'House Slant',
            'style'     => 'slant',
            'paper'     => '#f0e6d2',
            'backdrop'  => '#c4a882, #7a6248',
            'is_active' => 1,
        ])->assertRedirect();
        CatalogOverrides::flush();

        $look = TornStyleCatalog::presets()['house_slant'] ?? null;
        $this->assertNotNull($look);
        $this->assertSame('slant', $look['style']);

        $inLibrary = array_values(array_filter(
            BackgroundLibrary::items(collect()),
            fn ($i) => $i['type'] === 'torn' && $i['value'] === 'house_slant'
        ));
        $this->assertCount(1, $inLibrary);
        $this->assertSame('#c4a882', $inLibrary[0]['torn']['backdrop']);
        $this->assertSame('#7a6248', $inLibrary[0]['torn']['backdrop2']);
    }

    /**
     * A torn look pointing at a tear shape that has since been deleted must
     * fall back rather than clip nothing -- which would render a blank page.
     */
    public function test_a_torn_look_whose_shape_was_deleted_falls_back(): void
    {
        BgCatalogEntry::create([
            'kind' => 'torn', 'entry_key' => 'orphan', 'label' => 'Orphan',
            'payload' => ['style' => 'gone', 'paper' => '#ffffff', 'backdrop' => ['#111111', '#222222']],
            'is_active' => true,
        ]);
        CatalogOverrides::flush();

        $this->assertSame(TornStyleCatalog::DEFAULT, TornStyleCatalog::presets()['orphan']['style']);
        $this->assertNotEmpty(TornStyleCatalog::sheets(TornStyleCatalog::presets()['orphan']['style']));
    }

    // ===== The whole chain =====

    /**
     * The point of all of this: a look an admin typed in lands on a real
     * published page.
     *
     * Everything above tests the catalogs. This tests that the catalogs are
     * what the PUBLIC RENDERER reads -- the admin's own gradients painted
     * onto a live biolink.
     *
     * One look per test on purpose: rendering two public pages inside a
     * single test leaves the test transaction in a state Postgres refuses
     * the next insert in, which reproduces with the shipped looks too and
     * has nothing to do with this change.
     */
    public function test_an_admin_made_mesh_paints_a_real_published_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(route('admin.bg-catalog.store', 'mesh'), [
            'entry_key' => 'mesh_house',
            'label'     => 'House Mesh',
            'base'      => '#101024',
            'blobs'     => "#22d3ee 15 20 55\n#a78bfa 80 15 50",
            'is_active' => 1,
        ])->assertRedirect();
        CatalogOverrides::flush();

        $html = $this->publishedPageUsing(['background_type' => 'mesh', 'mesh_preset' => 'mesh_house']);

        $this->assertStringContainsString('#22d3ee', $html,
            "a published page picking the admin's mesh does not render it");
        $this->assertStringContainsString('#101024', $html, 'including its base colour');
    }

    /** The same, for the catalog that had no database path at all before. */
    public function test_an_admin_made_tiles_palette_paints_a_real_published_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(route('admin.bg-catalog.store', 'tiles'), [
            'entry_key' => 'tiles_house',
            'label'     => 'House Tiles',
            'colors'    => '#ff0000, #00ff00',
            'tiles'     => 'linear-gradient(135deg, #ff0000, #990000)',
            'is_active' => 1,
        ])->assertRedirect();
        CatalogOverrides::flush();

        $html = $this->publishedPageUsing(['background_type' => 'tiles', 'tiles_palette' => 'tiles_house']);

        $this->assertStringContainsString('#990000', $html,
            "a published page picking the admin's tiles palette does not render it");
    }

    /** A live biolink page rendered with the given background settings. */
    private function publishedPageUsing(array $settings): string
    {
        $owner = \App\Modules\User\Models\User::create([
            'name'     => 'bg',
            'email'    => 'bg'.uniqid().'@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        /** @var \App\Modules\User\Models\Link $link */
        $link = $owner->links()->create([
            'user_id'   => $owner->id,
            'type'      => 'biolink',
            'alias'     => 'bg'.\Illuminate\Support\Str::random(10),
            'is_active' => true,
            'settings'  => ['biolink' => $settings],
        ]);

        return $this->get('/'.$link->alias)->assertOk()->getContent();
    }

    // ===== The editor screen =====

    /** A shipped look opens pre-filled from the code, not blank. */
    public function test_editing_a_shipped_look_opens_filled_in(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.bg-catalog.edit', ['mesh', 'mesh_aurora']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Aurora', $html);
        $this->assertStringContainsString('#0b1026', $html, 'the base colour must be filled in from the constant');
        $this->assertStringContainsString('#22d3ee 15 20 55', $html, 'and so must the blobs');
        $this->assertStringContainsString('ships with the code', $html,
            'an admin editing a shipped look should be told the original is safe');
    }

    /** A look that does not exist is a 404, not a blank create form. */
    public function test_an_unknown_look_is_not_found(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get(route('admin.bg-catalog.edit', ['mesh', 'nope']))->assertNotFound();
        $this->actingAs($admin, 'admin')->get(route('admin.bg-catalog.index', 'wallpaper'))->assertNotFound();
    }
}
