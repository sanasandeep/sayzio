<?php

namespace App\Modules\Common\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The intrinsic pixel size of one of our own public images.
 *
 * An <img> with no width and height has no aspect ratio until the file lands,
 * so the browser reserves nothing and everything below it jumps down when it
 * does. Measured across the marketing site, 107 images had no reserved box --
 * every photograph on /about, /features, /services, /newsroom and a dozen
 * more, most of them 1280x896 above the fold.
 *
 * The numbers are read from the file rather than written into the markup for
 * the reason the brand logo taught us: these URLs are configurable. A hero a
 * client swaps for one of a different shape would keep reserving the old
 * box, which is worse than reserving none -- the page would settle into the
 * wrong layout and then jump out of it. A URL we cannot measure gets no
 * attributes at all.
 *
 * getimagesize() opens the file, so results are cached against the path's
 * modification time: a re-uploaded image invalidates itself and a warm page
 * pays nothing.
 */
class AssetSize
{
    /**
     * `[width, height]`, or null when the URL is not a public file we can read.
     *
     * @return array{0:int,1:int}|null
     */
    public static function of(string $url): ?array
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        // Only our own public assets. Somebody else's CDN is not a file we can
        // measure without a network round trip per render.
        $host = parse_url($url, PHP_URL_HOST);

        if ($host !== null && $host !== self::requestHost()) {
            return null;
        }

        $file = public_path(ltrim($path, '/'));

        if (! is_file($file)) {
            return null;
        }

        $key = 'asset_size:' . md5($file) . ':' . filemtime($file);

        return Cache::rememberForever($key, function () use ($file) {
            $size = @getimagesize($file);

            return ($size && $size[0] > 0 && $size[1] > 0) ? [(int) $size[0], (int) $size[1]] : null;
        });
    }

    /**
     * The same thing as ready-to-echo attributes: ` width="1280" height="896"`,
     * or an empty string.
     *
     * This is what the `@imgSize` Blade directive echoes, and the leading space
     * is deliberate so it can sit directly against the preceding attribute.
     */
    public static function attributes(string $url): string
    {
        $size = self::of($url);

        return $size ? ' width="' . $size[0] . '" height="' . $size[1] . '"' : '';
    }

    private static function requestHost(): ?string
    {
        try {
            return request()->getHost();
        } catch (\Throwable) {
            return null;
        }
    }
}
