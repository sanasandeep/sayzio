<?php

namespace Tests\Feature;

use App\Modules\User\Support\BackgroundImageGallery;
use App\Modules\User\Support\PlatformAssetCatalog;
use Tests\TestCase;

/**
 * The background panel had two image pickers over the same S3 assets.
 *
 * Same shape of mistake as the six Style pickers, one level down, and they
 * disagreed about nearly everything:
 *
 *   "Stock"                        grid-images + hand-drawn, drawn as 152px
 *                                  SQUARES, and a pick was fetched as a Blob
 *                                  and copied into the user's vault.
 *   "Or choose from our gallery"   biolink-backgrounds, drawn as 9/14
 *                                  portraits behind an accordion, and a pick
 *                                  stored the S3 key so the server resolved
 *                                  the CDN URL -- no copy.
 *
 * Both were on screen at once, which is what "images here aren't uniform
 * sizes" was pointing at: 152x152 above, 75x116 below, measured live.
 *
 * One picker now. 9/14 wins because that is the shape of the page the image
 * fills -- a square crop previews something the user never gets. And the
 * by-reference path wins because it was already the better of the two:
 * platform assets never count against user storage.
 */
class OneImageGalleryOnTheBackgroundPanelTest extends TestCase
{
    private function card(): string
    {
        return file_get_contents(
            base_path('resources/views/user/links/partials/biolink-background-card.blade.php')
        );
    }

    /** Every folder the picker offers has to be one the catalog can list. */
    public function test_every_offered_folder_is_a_real_catalog_folder(): void
    {
        $this->assertNotEmpty(BackgroundImageGallery::FOLDERS);

        foreach (BackgroundImageGallery::FOLDERS as $slug => $label) {
            $this->assertTrue(PlatformAssetCatalog::isFolder($slug),
                "{$slug} is offered as a chip but the catalog cannot list it");
            $this->assertNotSame('', trim($label), "{$slug} needs a label a person can read");
        }
    }

    /**
     * The two pickers are one.
     *
     * An earlier version of this test also forbade a disclosure, on the
     * reasoning that the old accordion was where the second picker hid.
     * That was the wrong half to forbid. The bug was TWO pickers, not a
     * fold: with one picker, folding ours away is what lets the tab named
     * "use your own" lead with the user's own file -- and it means the 440
     * images are fetched only when someone asks for them, which the
     * always-open grid could not do.
     *
     * What must stay true is that opening it reveals the SAME single
     * picker, so the assertions below are about there being one.
     */
    public function test_the_background_panel_has_a_single_image_picker(): void
    {
        $card = $this->card();

        $this->assertStringNotContainsString('Or choose from our gallery', $card,
            'the accordion was the second picker');
        $this->assertSame(1, substr_count($card, 'x-for="a in galVisible()"'),
            'one grid of platform images, however it is revealed');
        $this->assertSame(1, substr_count($card, "name=\"background_image_asset\""),
            'two inputs for the same field is how the two pickers disagreed');
        $this->assertStringContainsString('galFolder', $card,
            'the merged picker filters by folder chip');

        // The background image field must opt out of the dropzone's own
        // Stock tab, or the merge just adds a third picker.
        $this->assertMatchesRegularExpression(
            "/'name'\s*=>\s*'background_image',.*?'allowStock'\s*=>\s*false/s",
            $card,
            'the background image field still carries the duplicate Stock tab'
        );
    }

    /** It draws in the same shape as every other background swatch. */
    public function test_the_gallery_uses_the_shared_swatch_shape(): void
    {
        $card = $this->card();

        $this->assertStringContainsString('class="bg-lib-swatch"', $card);
        $this->assertStringContainsString('bg-swatch-grid', $card);
        $this->assertStringContainsString('bg-lib-chip', $card,
            'the folder chips should look like the library chips, not a third style');
    }

    /** Stock stays where it still makes sense -- this is opt-out, not removal. */
    public function test_other_fields_keep_their_stock_tab(): void
    {
        $dropzone = file_get_contents(
            base_path('resources/views/user/partials/dropzone-input.blade.php')
        );

        $this->assertStringContainsString('$allowStock  = $allowStock  ?? true;', $dropzone,
            'Stock must default to on; only the background field opts out');
        $this->assertStringContainsString("\$browseType === 'image' && \$allowStock", $dropzone);
    }

    /**
     * A pick is an S3 key, and the save path must accept exactly the three
     * folders the picker offers -- no more.
     */
    public function test_a_pick_validates_only_within_the_offered_folders(): void
    {
        foreach (BackgroundImageGallery::slugs() as $slug) {
            $this->assertTrue(
                BackgroundImageGallery::accepts(PlatformAssetCatalog::FOLDERS[$slug].'/photo.jpg'),
                "{$slug} is offered in the picker, so its keys must validate"
            );
        }

        // A real catalog folder that is NOT offered here stays out.
        $this->assertFalse(BackgroundImageGallery::accepts('assets/people-avatars/someone.png'));
    }

    /** The key is untrusted input, so the guards that matter still hold. */
    public function test_a_crafted_key_cannot_point_somewhere_else(): void
    {
        foreach ([
            'assets/grid-images/../../.env',
            'assets/grid-images/nested/city.png',
            '../assets/grid-images/city.png',
            'assets/grid-imagesX/city.png',
            'assets/grid-images/',
            'assets/grid-images/script.php',
            null,
            '',
        ] as $key) {
            $this->assertFalse(BackgroundImageGallery::accepts($key),
                var_export($key, true).' must not validate as a gallery pick');
        }
    }
}
