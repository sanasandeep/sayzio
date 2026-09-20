<?php

namespace App\Modules\User\Support;

/**
 * The curated S3 folders a page-background image may be chosen from.
 *
 * There used to be two image pickers on the background panel, over the same
 * asset system, disagreeing about almost everything:
 *
 *   "Stock"        grid-images + hand-drawn, drawn as 152px squares, and a
 *                  pick was fetched as a Blob and COPIED into the user's
 *                  vault, counting against their storage.
 *   "Or choose
 *    from our
 *    gallery"      biolink-backgrounds, drawn as 9/14 portraits behind an
 *                  accordion, and a pick stored the S3 KEY so the server
 *                  resolved the public CDN URL -- no copy, no storage.
 *
 * One list now, over all three folders, in the 9/14 shape the rest of the
 * panel uses -- a background fills a portrait page, so a square crop
 * previews something the user never gets -- and every pick takes the
 * by-reference path, which was already the better of the two.
 *
 * These are the same folders the admin gallery manager writes to
 * (Admin\Controllers\PlatformGalleryController, /admin/platform-gallery),
 * so adding an image there is all it takes for it to appear here.
 */
class BackgroundImageGallery
{
    /**
     * Folder slug => the chip label the user reads. Keys must exist in
     * PlatformAssetCatalog::FOLDERS; the order is the chip order.
     *
     * @var array<string, string>
     */
    public const FOLDERS = [
        'biolink-backgrounds' => 'Backgrounds',
        'grid-images'         => 'Photos',
        'hand-drawn'          => 'Hand-drawn',
    ];

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::FOLDERS);
    }

    /**
     * Whether an S3 key names an asset in one of these folders.
     *
     * The save path validates by prefix and filename rather than by an S3
     * round-trip, so a request cannot point `background_image` at an
     * arbitrary object.
     */
    public static function accepts(?string $key): bool
    {
        return $key !== null
            && $key !== ''
            && PlatformAssetCatalog::folderForKey($key, self::slugs()) !== null;
    }
}
