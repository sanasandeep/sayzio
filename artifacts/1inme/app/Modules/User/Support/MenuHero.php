<?php

namespace App\Modules\User\Support;

/**
 * The top of a menu page: the name, the description, the ordering badge.
 *
 * Sana, 2026-10-04: "Priyumm tiffins and order at table both..... can be
 * optional hidden.. also alignment and color and style changes....".
 *
 * ---- Why anyone would hide their own restaurant's name ----------------
 *
 * Because they have already put it in a block. The menu page renders
 * creator blocks above the title, and the first thing a restaurant puts
 * there is its logo -- which has the name in it. So the page says "Priyumm
 * Tiffins" twice, once as artwork and once as an h1 underneath, and there
 * was no way to drop the second. That is the exact page in his screenshot.
 *
 * The badge is the same story from the other end: "Order at table" is
 * useful on a QR code taped to a table and meaningless on a menu someone
 * opened from Instagram.
 *
 * ---- Hidden, not deleted ----------------------------------------------
 *
 * The title still goes in <title>, in the OG tags and in the heading the
 * screen reader announces -- hiding it here is a visual choice about one
 * block of the page, not a decision to publish an untitled document. A
 * page whose visible name is in an image still needs a name in its markup.
 */
class MenuHero
{
    /** Alignment of the whole hero block. */
    public const ALIGNMENTS = [
        'left'   => 'Left',
        'center' => 'Centre',
        'right'  => 'Right',
    ];

    public const DEFAULT_ALIGN = 'left';

    /**
     * How big the name is.
     *
     * Sizes rather than a pixel box: a creator picking how their
     * restaurant's name looks is choosing a feel, not typing a number, and
     * a free number is how a title ends up at 9px on a phone.
     *
     * @var array<string, array{label: string, size: string, weight: string}>
     */
    public const SIZES = [
        'small'  => ['label' => 'Small',  'size' => '20px', 'weight' => '700'],
        'medium' => ['label' => 'Medium', 'size' => '26px', 'weight' => '800'],
        'large'  => ['label' => 'Large',  'size' => '34px', 'weight' => '800'],
        'huge'   => ['label' => 'Huge',   'size' => '44px', 'weight' => '900'],
    ];

    /** 26px/800 is what the h1 has always been. */
    public const DEFAULT_SIZE = 'medium';

    /**
     * Every setting, with the value a page has rendered with until now.
     *
     * The defaults are the old hard-coded markup: both parts shown, left
     * aligned, medium, inheriting the page ink. A menu nobody has touched
     * must come out of this looking exactly as it did.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'hero_title_hidden' => false,
        'hero_badge_hidden' => false,
        'hero_align'        => self::DEFAULT_ALIGN,
        'hero_size'         => self::DEFAULT_SIZE,
        'hero_title_color'  => '',
        'hero_badge_color'  => '',
    ];

    /**
     * The hero's settings, resolved from the menu's settings blob.
     *
     * @param  array<string, mixed>  $menuSettings
     * @return array<string, mixed>
     */
    public static function resolve(array $menuSettings): array
    {
        return [
            'hero_title_hidden' => (bool) ($menuSettings['hero_title_hidden'] ?? false),
            'hero_badge_hidden' => (bool) ($menuSettings['hero_badge_hidden'] ?? false),
            'hero_align'        => self::align($menuSettings['hero_align'] ?? null),
            'hero_size'         => self::size($menuSettings['hero_size'] ?? null),
            // Empty means inherit, the same way the item colours work.
            'hero_title_color'  => MenuPresentation::hex($menuSettings['hero_title_color'] ?? null),
            'hero_badge_color'  => MenuPresentation::hex($menuSettings['hero_badge_color'] ?? null),
        ];
    }

    public static function align(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return array_key_exists($value, self::ALIGNMENTS) ? $value : self::DEFAULT_ALIGN;
    }

    public static function size(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return array_key_exists($value, self::SIZES) ? $value : self::DEFAULT_SIZE;
    }

    /**
     * Is there anything left to draw?
     *
     * With the title hidden, the badge hidden and no description, the hero
     * is an empty div with 46px of padding -- a gap at the top of the page
     * that nobody asked for. The template skips it entirely instead.
     */
    public static function isEmpty(array $resolved, bool $hasDescription, bool $isOrder): bool
    {
        $titleGone = $resolved['hero_title_hidden'];
        $badgeGone = $resolved['hero_badge_hidden'] || ! $isOrder;

        return $titleGone && $badgeGone && ! $hasDescription;
    }

    /**
     * The CSS the hero needs, as declarations for `.hero` and `.hero h1`.
     *
     * Returned as a rendered block rather than values, so the two menu
     * templates cannot assemble it differently.
     */
    public static function css(array $resolved): string
    {
        $size = self::SIZES[$resolved['hero_size']] ?? self::SIZES[self::DEFAULT_SIZE];

        $out = '.hero { text-align: '.$resolved['hero_align'].'; }'
            ."\n".'.hero h1 { font-size: '.$size['size'].'; font-weight: '.$size['weight'].'; }';

        if ($resolved['hero_title_color'] !== '') {
            $out .= "\n".'.hero h1 { color: '.$resolved['hero_title_color'].'; }';
        }
        if ($resolved['hero_badge_color'] !== '') {
            // The badge's own colour overrides the accent it inherits.
            $out .= "\n".'.hero .badge { background: '.$resolved['hero_badge_color'].'; }';
        }

        return $out;
    }

    /** The settings keys, for the editor payload and the save rules. */
    public static function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /**
     * Validation rules for the two menu settings endpoints.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'hero_title_hidden' => 'sometimes|boolean',
            'hero_badge_hidden' => 'sometimes|boolean',
            'hero_align'        => 'nullable|string|in:'.implode(',', array_keys(self::ALIGNMENTS)),
            'hero_size'         => 'nullable|string|in:'.implode(',', array_keys(self::SIZES)),
            'hero_title_color'  => 'nullable|string|max:16',
            'hero_badge_color'  => 'nullable|string|max:16',
        ];
    }
}
