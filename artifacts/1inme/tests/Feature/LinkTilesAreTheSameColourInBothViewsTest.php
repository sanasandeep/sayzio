<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\LinkTileStyle;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\LinkTypeCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * My Links: a link's icon is the same colour whichever view you are in, and
 * that colour belongs to its type.
 *
 * Two faults, one screen. The list rows read a colour map written inline in
 * the blade that covered 7 of the 18 types, so Slides, Restaurant Menu,
 * Store Menu, AI Chatbot and eight others all fell through to the Short Link
 * colour and were indistinguishable. The grid tiles did not read the type at
 * all -- they took the FOLDER's colour, defaulting to blue, so every link
 * outside a folder was blue and the same link changed colour when you
 * switched views.
 *
 * Both now go through LinkTileStyle, and the colour comes from the type's
 * `tint` in the catalog.
 */
class LinkTilesAreTheSameColourInBothViewsTest extends TestCase
{
    use RefreshDatabase;

    private function makeLink(User $u, string $type): Link
    {
        $link = $u->links()->create([
            'user_id'   => $u->id,
            'type'      => $type,
            'alias'     => 'tc'.substr(Str::random(10), 0, 10),
            'long_url'  => 'https://example.com',
            'is_active' => true,
        ]);

        // My Links is scoped to the active workspace, so a link created
        // outside one is simply not on the page to be coloured.
        $ws = $u->ownedWorkspaces()->first();
        if ($ws && (int) $link->workspace_id !== (int) $ws->id) {
            $link->forceFill(['workspace_id' => $ws->id])->save();
        }

        return $link->fresh();
    }

    /** The index, with the workspace the link lives in selected. */
    private function myLinks(User $u): string
    {
        $ws = $u->ownedWorkspaces()->first();

        return $this->actingAs($u)
            ->withSession($ws ? [WorkspaceContext::SESSION_KEY => $ws->id] : [])
            ->get('/user/links')
            ->assertOk()
            ->getContent();
    }

    /**
     * The bug as the eye saw it: a Slides link and a Short Link were the same
     * colour, because Slides was not in the list's map.
     */
    public function test_every_type_has_a_colour_of_its_own(): void
    {
        $seen = [];

        foreach (LinkTypeCategories::types() as $value => $meta) {
            $this->assertArrayHasKey(
                'tint',
                $meta,
                "Link type '$value' has no tint, so its tile falls back to the "
                .'Short Link colour and reads as a different kind of link.'
            );

            $this->assertMatchesRegularExpression(
                '/^#[0-9a-f]{6}$/i',
                $meta['tint'],
                "Link type '$value' has a tint that is not a six-digit hex, and "
                .'the tile cannot take it apart to build its fill.'
            );

            $seen[$value] = strtolower($meta['tint']);
        }

        // Slides must not look like a Short Link -- the pair from the report.
        $this->assertNotSame(
            $seen['url'],
            $seen['slides'],
            'a Slides link is still painted the Short Link colour'
        );
        $this->assertNotSame($seen['url'], $seen['restaurant_menu']);
        $this->assertNotSame($seen['url'], $seen['store_menu']);
    }

    /**
     * The colour is a property of the link, so the two views cannot differ:
     * whatever the grid tile renders, the list row renders.
     */
    public function test_the_grid_and_the_list_render_the_same_colour(): void
    {
        $user = User::factory()->create()->fresh();

        $link = $this->makeLink($user, 'slides');
        $style = LinkTileStyle::for($link);

        $html = $this->myLinks($user);

        // Both views are in the same document -- one is x-show'd, not absent --
        // so the colour has to appear at least twice for the same link.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, $style['color']),
            'the list rows and the grid tiles are not painting this link the '
            .'same colour: one of them is reading something other than the type'
        );
    }

    /**
     * And the grid is no longer painting by folder, which is what made every
     * unfiled link blue.
     */
    public function test_a_link_in_no_folder_still_gets_its_type_colour(): void
    {
        $user = User::factory()->create()->fresh();

        $link = $this->makeLink($user, 'biolink');
        $this->assertNull($link->project_id, 'this link is meant to be outside any folder');

        $expected = LinkTileStyle::for($link)['color'];

        $this->assertNotSame('#3b82f6', $expected, 'the old folder-default blue is back');
        $this->assertStringContainsString(
            $expected,
            $this->myLinks($user)
        );
    }

    /**
     * A File Share link keeps showing what it holds, and now does so in the
     * grid too rather than only in the list.
     */
    public function test_a_file_link_is_painted_for_what_it_holds(): void
    {
        $plain = LinkTileStyle::for((object) ['type' => 'file', 'fileLink' => null]);
        $pdf   = LinkTileStyle::for((object) [
            'type'     => 'file',
            'fileLink' => (object) ['original_name' => 'invoice.pdf'],
        ]);

        $this->assertSame('fa-file-pdf', $pdf['icon']);
        $this->assertSame('PDF', $pdf['label']);
        $this->assertNotSame($plain['color'], $pdf['color']);
    }

    /** An unknown type does not render an uncoloured, iconless tile. */
    public function test_an_unknown_type_still_gets_a_tile(): void
    {
        $style = LinkTileStyle::for((object) ['type' => 'not_a_real_type']);

        $this->assertNotSame('', $style['icon']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $style['color']);
        $this->assertStringStartsWith('rgba(', $style['bg']);
    }
}
