<?php

namespace App\Modules\User\Support;

/**
 * How a background image meets the page it sits behind.
 *
 * Sana, 2026-10-04, having uploaded a decorative border frame: "i have
 * attached background image.... fit, strech, cover ... all are missing....
 * make sure things are working for normal link in bio and others also".
 *
 * ---- There was one behaviour, written twice ---------------------------
 *
 * `center/cover no-repeat` as a literal, in body-declarations.blade.php and
 * again in css.blade.php. Cover fills the viewport and throws away whatever
 * does not fit, which is the right default for a photograph and precisely
 * wrong for the thing he uploaded: a frame whose entire content is at the
 * edges, so cover crops off the only part that mattered.
 *
 * ---- Why five, and why these five -------------------------------------
 *
 * Each one is a thing somebody actually wants, not a CSS keyword dump:
 *
 *   cover    fill the screen, crop the overflow   (a photo)
 *   contain  show all of it, letterbox the rest   (a frame, a logo, a map)
 *   stretch  fill the screen, distort to do it    (a gradient, a texture)
 *   tile     repeat it at its own size            (a pattern, a motif)
 *   actual   once, at its own size, no repeat     (a watermark, a crest)
 *
 * `contain` is the one he was reaching for. It needs a colour behind it,
 * which the page already has in bg_fallback_color, so nothing new there.
 *
 * ---- The default is cover, and that is load-bearing -------------------
 *
 * Every page with a background image in the product today is rendering
 * `cover`, because that was the only option. An unset fit must keep meaning
 * cover or every one of those pages reflows the moment this ships.
 */
class BackgroundFit
{
    public const COVER   = 'cover';

    public const CONTAIN = 'contain';

    public const STRETCH = 'stretch';

    public const TILE    = 'tile';

    public const ACTUAL  = 'actual';

    /** What it was before this setting existed. */
    public const DEFAULT = self::COVER;

    /**
     * The choices, in the order a creator should meet them.
     *
     * Labels name the outcome rather than the CSS: somebody choosing a
     * background is thinking "show the whole thing", not "background-size:
     * contain".
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const CHOICES = [
        self::COVER => [
            'label' => 'Fill',
            'hint'  => 'Covers the screen. The edges get cropped. Best for photos.',
        ],
        self::CONTAIN => [
            'label' => 'Fit whole image',
            'hint'  => 'Shows all of it, with the background colour around the edges. Best for frames and borders.',
        ],
        self::STRETCH => [
            'label' => 'Stretch',
            'hint'  => 'Fills the screen by distorting the image. Fine for gradients and textures.',
        ],
        self::TILE => [
            'label' => 'Tile',
            'hint'  => 'Repeats at its own size. Best for small patterns.',
        ],
        self::ACTUAL => [
            'label' => 'Actual size',
            'hint'  => 'Once, at its own size, not repeated.',
        ],
    ];

    /**
     * Where the image sits when it does not fill the frame.
     *
     * Only meaningful for `contain` and `actual`; the other three either
     * fill the box or tile from the corner. The picker hides it for those,
     * but the value is kept either way so switching fit and back does not
     * lose it.
     *
     * @var array<string, string>
     */
    public const POSITIONS = [
        'top left'      => 'Top left',
        'top'           => 'Top',
        'top right'     => 'Top right',
        'left'          => 'Left',
        'center'        => 'Centre',
        'right'         => 'Right',
        'bottom left'   => 'Bottom left',
        'bottom'        => 'Bottom',
        'bottom right'  => 'Bottom right',
    ];

    public const DEFAULT_POSITION = 'center';

    /** A stored fit, or the default. Junk reads as the default. */
    public static function fit(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return array_key_exists($value, self::CHOICES) ? $value : self::DEFAULT;
    }

    /** A stored position, or the default. */
    public static function position(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return array_key_exists($value, self::POSITIONS) ? $value : self::DEFAULT_POSITION;
    }

    /**
     * The three declarations that paint an image at this fit.
     *
     * Returned as `background-size` / `background-position` /
     * `background-repeat` rather than folded into the `background`
     * shorthand, because the shorthand resets every longhand it omits --
     * and the pages that paint a background also set a colour, an
     * attachment and sometimes a preset on the same element.
     *
     * @return array{size: string, position: string, repeat: string}
     */
    public static function declarations(mixed $fit, mixed $position = null): array
    {
        $fit = self::fit($fit);
        $pos = self::position($position);

        return match ($fit) {
            self::CONTAIN => ['size' => 'contain', 'position' => $pos,      'repeat' => 'no-repeat'],
            self::STRETCH => ['size' => '100% 100%', 'position' => 'center', 'repeat' => 'no-repeat'],
            // Tiling from the centre leaves a half tile at every edge, which
            // is not what a pattern is for.
            self::TILE    => ['size' => 'auto',    'position' => 'top left', 'repeat' => 'repeat'],
            self::ACTUAL  => ['size' => 'auto',    'position' => $pos,       'repeat' => 'no-repeat'],
            default       => ['size' => 'cover',   'position' => 'center',   'repeat' => 'no-repeat'],
        };
    }

    /** Those three as a CSS declaration list, for inlining into a rule. */
    public static function css(mixed $fit, mixed $position = null): string
    {
        $d = self::declarations($fit, $position);

        return 'background-size: '.$d['size'].';'
            .' background-position: '.$d['position'].';'
            .' background-repeat: '.$d['repeat'].';';
    }

    /** Whether the position picker means anything at this fit. */
    public static function usesPosition(mixed $fit): bool
    {
        return in_array(self::fit($fit), [self::CONTAIN, self::ACTUAL], true);
    }
}
