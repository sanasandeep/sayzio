<?php

namespace App\Modules\User\Support;

use App\Modules\Admin\Models\BgTemplate;

/**
 * Everything needed to paint a page background, resolved from settings.
 *
 * This used to live inline in common/biolink.blade.php -- about 130 lines
 * of resolution tangled up with fonts, buttons and stickers, with the CSS
 * and the layer markup scattered across three more places in the same
 * 2,400-line file. One consequence: the biolink was the only page in the
 * app that could render a background at all.
 *
 * That is why the conversational page shows a picker of 941 ready-made
 * looks and honours two of them -- it could not reuse any of this, so it
 * hand-rolled `image` and `slideshow` and read a `theme.background` key no
 * controller has ever written. And it is why no other page type could be
 * offered the picker: there was nothing to render it with.
 *
 * NOTHING ABOUT THE OUTPUT CHANGES HERE. The order of the branches, the
 * defaults, the fallbacks and the quirks are all carried across as they
 * were; ThePageBackgroundRendersIdenticallyTest records the rendered CSS
 * of all nineteen branches and fails on any drift.
 *
 * @see \App\Modules\User\Services\BiolinkThemeResolver::THEMABLE_KEYS  the
 *      fields a scheduled theme captures -- kept in sync with FIELDS below.
 */
class PageBackground
{
    /**
     * Every settings key this renderer reads.
     *
     * Kept as one list so the theme snapshot and the design-lock list can
     * be checked against it rather than drifting apart, which is how the
     * theme snapshot silently stopped capturing tiles, torn, mesh, pattern
     * and preset backgrounds.
     *
     * @var list<string>
     */
    public const FIELDS = [
        'background_type', 'background_color', 'background_gradient', 'background_image',
        'gradient_colors', 'gradient_angle', 'gradient_type', 'gradient_preset_id',
        'slideshow_images', 'slideshow_interval', 'video_url', 'video_file',
        'bg_template_id', 'bg_attachment', 'bg_fallback_color', 'bg_fallback_image',
        'bg_blur', 'bg_overlay_color', 'bg_overlay_opacity',
        'bg_preset_key', 'bg_preset_opacity',
        'mesh_preset', 'pattern_preset',
        'tiles_palette', 'tiles_layout', 'tiles_animate',
        'torn_style', 'torn_paper_color', 'torn_backdrop_color', 'torn_backdrop_color2', 'torn_image',
    ];

    /** Types whose paint can go on the dedicated .bg-page-fixed layer. */
    private const LAYERABLE = ['color', 'gradient', 'preset', 'image', 'mesh', 'pattern'];

    public const DEFAULT_COLOR    = '#0a0612';
    public const DEFAULT_GRADIENT = 'linear-gradient(135deg, #0a0612 0%, #1a0533 50%, #0a0612 100%)';
    public const DEFAULT_PAPER    = '#cfe0e6';

    /**
     * Has the owner actually chosen a background?
     *
     * resolve() defaults an absent type to 'gradient', which is right for
     * the biolink -- that IS its default look. It is wrong for every page
     * type that had its own hardcoded colours long before it had a picker:
     * a Reviews page with no saved background must keep the radial it has
     * always had, not acquire the biolink's purple gradient because a
     * default fired.
     *
     * So those renderers ask this first. No choice means their own CSS
     * stands untouched; a choice means the shared background takes over.
     */
    public static function chosen(array $bs): bool
    {
        return isset($bs['background_type']) && $bs['background_type'] !== '';
    }

    /**
     * Resolve a biolink settings array into everything the views need.
     *
     * @param  array  $bs  the `settings['biolink']` array
     * @return array<string, mixed>
     */
    public static function resolve(array $bs): array
    {
        $type           = $bs['background_type'] ?? 'gradient';
        $color          = $bs['background_color'] ?? self::DEFAULT_COLOR;
        $gradient       = $bs['background_gradient'] ?? self::DEFAULT_GRADIENT;
        $image          = $bs['background_image'] ?? '';
        $attachment     = $bs['bg_attachment'] ?? 'fixed';
        $fallbackColor  = $bs['bg_fallback_color'] ?? self::DEFAULT_COLOR;
        $fallbackImage  = $bs['bg_fallback_image'] ?? '';

        // Preset CSS background: resolved server-side from the catalog by key.
        $presetCss = null;

        // Torn-paper composite: a backdrop layer (photo or preset gradient)
        // behind a solid paper sheet whose right edge is a jagged torn
        // diagonal. Active either as its own background_type ('torn' with a
        // user backdrop photo + paper color) or via a torn-group preset.
        $tornActive        = false;
        $tornPaper         = self::DEFAULT_PAPER;
        $tornBackdropCss   = null;   // full CSS declaration(s) for the backdrop layer
        $tornBackdropImage = '';     // user-uploaded backdrop photo URL

        if ($type === 'preset' && !empty($bs['bg_preset_key'])) {
            $presetKey = (string) $bs['bg_preset_key'];
            if (BgPresetCatalog::isTorn($presetKey)) {
                $tornActive      = true;
                $tornPaper       = BgPresetCatalog::tornPaper($presetKey) ?? $tornPaper;
                $tornBackdropCss = BgPresetCatalog::tornBackdrop($presetKey);
            } else {
                $presetCss = BgPresetCatalog::css($presetKey);
            }
        } elseif ($type === 'torn') {
            $tornActive        = true;
            $tornPaper         = is_string($bs['torn_paper_color'] ?? null) && $bs['torn_paper_color'] !== ''
                ? $bs['torn_paper_color']
                : $tornPaper;
            $tornBackdropImage = is_string($bs['torn_image'] ?? null) ? $bs['torn_image'] : '';

            // Backdrop colors (Task #6204): validated hex pair -> gradient,
            // only when no backdrop photo was uploaded (photo wins).
            $c1 = is_string($bs['torn_backdrop_color'] ?? null) ? $bs['torn_backdrop_color'] : '';
            $c2 = is_string($bs['torn_backdrop_color2'] ?? null) ? $bs['torn_backdrop_color2'] : '';
            if ($tornBackdropImage === '' && $c1 !== '') {
                $tornBackdropCss = 'background: linear-gradient(150deg, '.$c1.' 0%, '.($c2 !== '' ? $c2 : $c1).' 100%)';
            }
        } elseif ($type === 'mesh' && !empty($bs['mesh_preset'])) {
            // Mesh / Pattern (Task #6204) reuse the preset render path:
            // CSS is resolved server-side from the catalogs by key.
            $presetCss = MeshGradientCatalog::css((string) $bs['mesh_preset']);
        } elseif ($type === 'pattern' && !empty($bs['pattern_preset'])) {
            $presetCss = PatternCatalog::css((string) $bs['pattern_preset']);
        }

        // Tiles background (Task #6204): a dedicated grid layer of catalog
        // gradients. Resolved fully server-side; the optional pulse
        // animation is gated behind prefers-reduced-motion in the CSS.
        $tiles        = [];
        $tilesAnimate = false;
        if ($type === 'tiles' && !empty($bs['tiles_palette'])) {
            $tiles = TilesBgCatalog::tiles(
                (string) $bs['tiles_palette'],
                (string) ($bs['tiles_layout'] ?? 'uniform')
            );
            $tilesAnimate = !empty($bs['tiles_animate']) && $bs['tiles_animate'] !== '0';
        }

        // Fixed/Scroll background position. "Fixed" backgrounds render on a
        // dedicated position:fixed full-viewport layer instead of relying on
        // `background-attachment: fixed`, which mobile Safari does not support.
        $fixed = ($attachment !== 'scroll');

        // Preset background transparency (Task #5970): 0-100, 100 = opaque.
        // A translucent preset can't be painted on the body itself (opacity
        // would fade the whole page), so it always renders on the dedicated
        // background layer -- position:fixed for "Fixed", absolute for "Scroll".
        $presetOpacity     = max(0, min(100, (int) ($bs['bg_preset_opacity'] ?? 100)));
        $presetTranslucent = ($type === 'preset' && $presetCss && $presetOpacity < 100);

        // Torn composites always render on their own dedicated layers
        // (backdrop + clipped paper), so they never use the generic
        // .bg-page-fixed layer nor an inline body background.
        $hasLayer = !$tornActive
            && ($fixed || $presetTranslucent)
            && in_array($type, self::LAYERABLE, true);

        $templateId = $bs['bg_template_id'] ?? null;

        return [
            'bs'                => $bs,
            'type'              => $type,
            'color'             => $color,
            'gradient'          => $gradient,
            'image'             => $image,
            'attachment'        => $attachment,
            'fixed'             => $fixed,
            'fallbackColor'     => $fallbackColor,
            'fallbackImage'     => $fallbackImage,
            'presetCss'         => $presetCss,
            'presetOpacity'     => $presetOpacity,
            'presetTranslucent' => $presetTranslucent,
            'hasLayer'          => $hasLayer,
            'tornActive'        => $tornActive,
            'tornPaper'         => $tornPaper,
            'tornBackdropCss'   => $tornBackdropCss,
            'tornBackdropImage' => $tornBackdropImage,
            'tornSheets'        => $tornActive ? TornStyleCatalog::sheets($bs['torn_style'] ?? null) : [],
            'tiles'             => $tiles,
            'tilesActive'       => $tiles !== [],
            'tilesAnimate'      => $tilesAnimate,
            'blur'              => (int) ($bs['bg_blur'] ?? 0),
            'overlayColor'      => $bs['bg_overlay_color'] ?? '#000000',
            'overlayOpacity'    => (int) ($bs['bg_overlay_opacity'] ?? 0),
            'slideshowImages'   => $bs['slideshow_images'] ?? [],
            'slideshowInterval' => (int) ($bs['slideshow_interval'] ?? 5),
            'videoUrl'          => $bs['video_url'] ?? '',
            'videoFile'         => $bs['video_file'] ?? '',
            'template'          => $templateId ? BgTemplate::find($templateId) : null,
        ];
    }

    /**
     * Readable font color for a page that has never been themed.
     *
     * When no background_type has been saved the page falls back to the dark
     * default gradient. A stale or mis-matched font_color (e.g. dark #212529
     * left over from a cleared theme) then produces unreadable text. This
     * returns the WCAG-safe override, or the color unchanged when it is fine.
     *
     * Intentionally-themed pages are never touched -- the caller only
     * consults this when `background_type` is absent from saved settings.
     */
    public static function readableFontColor(string $fontColor, string $fallbackColor): string
    {
        $lum = static function (string $c): ?float {
            $h = ltrim($c, '#');
            if (strlen($h) !== 6 || !ctype_xdigit($h)) {
                return null;
            }
            $r = hexdec(substr($h, 0, 2)) / 255;
            $g = hexdec(substr($h, 2, 2)) / 255;
            $b = hexdec(substr($h, 4, 2)) / 255;
            $lin = fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
        };

        // bgLum default 0.0016 is #0a0612 -- the effective background-color
        // that always renders, with gradient/image layers sitting on top.
        $bgLum   = $lum($fallbackColor) ?? 0.0016;
        $fontLum = $lum($fontColor);

        if ($fontLum === null) {
            return $bgLum < 0.18 ? '#ffffff' : '#212529';
        }

        $ratio = (max($bgLum, $fontLum) + 0.05) / (min($bgLum, $fontLum) + 0.05);

        return $ratio < 3.0
            ? ($bgLum < 0.18 ? '#ffffff' : '#212529')
            : $fontColor;
    }
}
