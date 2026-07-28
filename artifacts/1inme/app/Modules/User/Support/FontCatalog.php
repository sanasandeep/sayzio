<?php

namespace App\Modules\User\Support;

/**
 * Searchable Google Fonts catalog (100+).
 *
 * Each entry: ['family' => 'Inter', 'category' => 'sans', 'weights' => [300,400,500,600,700]].
 *
 * `weights` controls which weight axes we request from Google Fonts when
 * loading the family on the public biolink page. Categories (sans / serif /
 * display / mono / handwriting) drive the picker filter chips. The family
 * string MUST match the Google Fonts API exactly (case + spaces) — it is
 * URL-encoded into the css2 request and embedded in the picker's CSS preview.
 */
class FontCatalog
{
    public const CATEGORIES = [
        'sans' => 'Sans Serif',
        'serif' => 'Serif',
        'display' => 'Display',
        'handwriting' => 'Handwriting',
        'mono' => 'Monospace',
    ];

    /** Default weight set requested when an entry omits its own. */
    private const DEFAULT_WEIGHTS = [300, 400, 500, 600, 700];

    /**
     * @return array<int, array{family:string, category:string, weights:array<int,int>}>
     */
    public static function all(): array
    {
        $sans = [
            'Inter', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Source Sans 3',
            'Poppins', 'Raleway', 'Nunito', 'Nunito Sans', 'Work Sans', 'Mulish',
            'Rubik', 'Karla', 'Manrope', 'DM Sans', 'Outfit', 'Plus Jakarta Sans',
            'Be Vietnam Pro', 'Public Sans', 'IBM Plex Sans', 'Hind', 'Heebo',
            'Barlow', 'Cabin', 'PT Sans', 'Oxygen', 'Quicksand', 'Fira Sans',
            'Titillium Web', 'Asap', 'Archivo', 'Anek Latin', 'Albert Sans',
            'Sora', 'Urbanist', 'Onest', 'Geist', 'Geologica', 'Inter Tight',
            'Space Grotesk', 'Hanken Grotesk', 'Schibsted Grotesk', 'Big Shoulders Text',
            'Red Hat Display', 'Red Hat Text', 'Atkinson Hyperlegible', 'Mukta',
            'Saira', 'Saira Condensed', 'Exo 2', 'Exo', 'Bai Jamjuree',
            'Prompt', 'Maven Pro', 'Catamaran', 'Jost', 'Lexend',
        ];

        $serif = [
            'Playfair Display', 'Merriweather', 'PT Serif', 'Lora', 'Cormorant Garamond',
            'EB Garamond', 'Crimson Text', 'Crimson Pro', 'Libre Baskerville',
            'Source Serif 4', 'Bitter', 'Spectral', 'Domine', 'Cardo',
            'Roboto Serif', 'Noto Serif', 'IBM Plex Serif', 'Fraunces',
            'DM Serif Display', 'DM Serif Text', 'Vollkorn', 'Newsreader',
            'Literata', 'Petrona', 'Old Standard TT',
        ];

        $display = [
            'Bebas Neue', 'Oswald', 'Anton', 'Abril Fatface', 'Righteous',
            'Pacifico', 'Lobster', 'Comfortaa', 'Bungee', 'Paytone One',
            'Permanent Marker', 'Russo One', 'Black Ops One', 'Special Elite',
            'Press Start 2P', 'Monoton', 'Bowlby One', 'Audiowide', 'Orbitron',
            'Rampart One', 'Unica One', 'Alfa Slab One', 'Staatliches',
            'Archivo Black', 'Cinzel', 'Yeseva One',
        ];

        $hand = [
            'Caveat', 'Dancing Script', 'Sacramento', 'Great Vibes', 'Satisfy',
            'Kaushan Script', 'Indie Flower', 'Shadows Into Light', 'Amatic SC',
            'Patrick Hand', 'Architects Daughter', 'Homemade Apple', 'Gloria Hallelujah',
            'Reenie Beanie',
        ];

        $mono = [
            'JetBrains Mono', 'Fira Code', 'Source Code Pro', 'IBM Plex Mono',
            'Roboto Mono', 'Space Mono', 'Inconsolata', 'Cousine', 'PT Mono',
            'Ubuntu Mono', 'Anonymous Pro', 'Overpass Mono',
        ];

        $entries = [];
        foreach ($sans as $f) {
            $entries[] = ['family' => $f, 'category' => 'sans', 'weights' => self::DEFAULT_WEIGHTS];
        }
        foreach ($serif as $f) {
            $entries[] = ['family' => $f, 'category' => 'serif', 'weights' => self::DEFAULT_WEIGHTS];
        }
        foreach ($display as $f) {
            // Display fonts often only have one weight — request 400 broadly to
            // match Google Fonts' "single-weight" families. The API silently
            // ignores unsupported weights so 400 is the safe lowest-common.
            $entries[] = ['family' => $f, 'category' => 'display', 'weights' => [400, 700]];
        }
        foreach ($hand as $f) {
            $entries[] = ['family' => $f, 'category' => 'handwriting', 'weights' => [400, 700]];
        }
        foreach ($mono as $f) {
            $entries[] = ['family' => $f, 'category' => 'mono', 'weights' => [400, 500, 700]];
        }
        return $entries;
    }

    /** Family-name => entry lookup. */
    public static function byFamily(): array
    {
        $out = [];
        foreach (self::all() as $e) {
            $out[$e['family']] = $e;
        }
        return $out;
    }

    /** All families as a flat list of family names. */
    public static function families(): array
    {
        return array_map(fn ($e) => $e['family'], self::all());
    }

    public static function isKnown(string $family): bool
    {
        return isset(self::byFamily()[$family]);
    }

    /**
     * Build a Google Fonts <link> href for one family. Returns null if the
     * family is not in the catalog (custom uploads / unknown values bypass
     * Google Fonts entirely).
     */
    public static function googleHref(string $family): ?string
    {
        $entry = self::byFamily()[$family] ?? null;
        if (!$entry) return null;
        $weights = implode(';', $entry['weights']);
        return 'https://fonts.googleapis.com/css2?family=' . str_replace('%20', '+', rawurlencode($family))
            . ':wght@' . $weights . '&display=swap';
    }

    /**
     * Build a single combined Google Fonts <link> href for multiple
     * families. The css2 endpoint accepts repeated `family=` query
     * parameters, so requesting several families only costs one
     * render-blocking request/connection instead of one per family.
     * Unknown (legacy) families fall back to a broad weight request just
     * like {@see googleHref}. Returns null if $families is empty.
     *
     * @param array<int, string> $families
     */
    public static function googleHrefCombined(array $families): ?string
    {
        $families = array_values(array_unique(array_filter($families)));
        if (empty($families)) return null;

        $byFamily = self::byFamily();
        $parts = [];
        foreach ($families as $family) {
            $entry = $byFamily[$family] ?? null;
            $weights = $entry ? implode(';', $entry['weights']) : '300;400;500;600;700';
            $parts[] = 'family=' . str_replace('%20', '+', rawurlencode($family)) . ':wght@' . $weights;
        }
        return 'https://fonts.googleapis.com/css2?' . implode('&', $parts) . '&display=swap';
    }
}
