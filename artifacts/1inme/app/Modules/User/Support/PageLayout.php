<?php

namespace App\Modules\User\Support;

/**
 * How wide a public page's content is, and how much air sits around it.
 *
 * Sana, 2026-10-04, with Page Padding Top set to 200 on a restaurant menu:
 * "page padding seems to be not working...".
 *
 * ---- It was not working on two of the three page types -----------------
 *
 * The Layout card saves into settings.biolink.layout and `biolink.blade.php`
 * reads it. The restaurant menu and the store menu are their own templates
 * and read none of it -- their container was
 *
 *     .page { max-width: 760px; margin: 0 auto; padding: 0 16px 120px; }
 *
 * written as literals. So every number on that card saved correctly, showed
 * correctly in the editor, and changed nothing on the page. Max width, top
 * padding, bottom padding, side padding: four controls, no effect. That is
 * the tenth time this month the same shape has turned up -- a control that
 * exists and does nothing -- and the fix is the same each time: one place
 * that knows the answer, and every page asks it.
 *
 * ---- Why the defaults live here and not in the templates ---------------
 *
 * They were already written out twice with `?? 32` and friends, in the
 * template and in the editor. A third copy in each menu page is how a page
 * ends up with a different default from the box that sets it. The bounds
 * match BiolinkBlockController::sanitizeLayout, which is what clamps them
 * on the way in.
 */
class PageLayout
{
    /**
     * The bounds each number is clamped to, on save and on read.
     *
     * `block_padding` is deliberately absent. Unset means "inherit from the
     * block's own theme" on the biolink page, not a number, and giving it a
     * default here would turn every block that has never set one into a
     * block with a fixed padding.
     *
     * @var array<string, array{0:int, 1:int}>  [min, max]
     */
    public const BOUNDS = [
        'max_width_phone'     => [280, 600],
        'max_width_tablet'    => [320, 900],
        'max_width_desktop'   => [400, 1200],
        'page_padding_top'    => [0, 200],
        'page_padding_bottom' => [0, 200],
        'page_padding_x'      => [0, 100],
        'block_gap'           => [0, 100],
    ];

    /** What a Link in Bio has always looked like with nothing set. */
    public const BIOLINK_DEFAULTS = [
        'max_width_phone'     => 448,
        'max_width_tablet'    => 540,
        'max_width_desktop'   => 680,
        'page_padding_top'    => 32,
        'page_padding_bottom' => 64,
        'page_padding_x'      => 16,
        'block_gap'           => 12,
    ];

    /**
     * What a menu page has always looked like with nothing set.
     *
     * These are the literals that were welded into the two menu templates:
     * one width at every size, no top padding, a deep bottom one to clear
     * the floating cart. They are the DEFAULTS rather than being replaced
     * by the Link in Bio set, because every menu in the product is
     * currently an unconfigured one -- adopting 448/540/680 would quietly
     * reflow all of them to make a settings card true.
     *
     * The same reasoning as MenuBlockSlot::DEFAULT: a page nobody has
     * configured must not move.
     */
    public const MENU_DEFAULTS = [
        // 600 rather than 760 because 600 is the most the phone box will
        // accept, so no owner could ever set more. Below 640px -- every
        // real phone -- the viewport is the limit anyway and the two are
        // indistinguishable.
        'max_width_phone'     => 600,
        'max_width_tablet'    => 760,
        'max_width_desktop'   => 760,
        'page_padding_top'    => 0,
        'page_padding_bottom' => 120,
        'page_padding_x'      => 16,
        'block_gap'           => 12,
    ];

    /**
     * The numbers a page should use, from the link's biolink settings.
     *
     * Everything is clamped on read as well as on save: these values came
     * out of a JSON blob that predates the bounds, and a max-width of 4
     * from some old row would render a page one character wide.
     *
     * @param  array<string, mixed>  $biolinkSettings  $link->settings['biolink']
     * @param  array<string, int>  $defaults  BIOLINK_DEFAULTS or MENU_DEFAULTS
     * @return array<string, int>
     */
    public static function resolve(array $biolinkSettings, array $defaults = self::BIOLINK_DEFAULTS): array
    {
        $layout = $biolinkSettings['layout'] ?? [];
        $layout = is_array($layout) ? $layout : [];

        $out = [];
        foreach (self::BOUNDS as $key => [$min, $max]) {
            $raw = $layout[$key] ?? null;
            $out[$key] = ($raw === null || $raw === '' || ! is_numeric($raw))
                ? ($defaults[$key] ?? self::BIOLINK_DEFAULTS[$key])
                : max($min, min($max, (int) $raw));
        }

        return $out;
    }

    /** The default set a link of this type has always rendered with. */
    public static function defaultsFor(?string $linkType): array
    {
        return in_array($linkType, ['restaurant_menu', 'store_menu'], true)
            ? self::MENU_DEFAULTS
            : self::BIOLINK_DEFAULTS;
    }

    /**
     * The container CSS for a page that is ONE column of content.
     *
     * The menu pages, in other words. `biolink.blade.php` has its own
     * twelve-column grid and its own reason for keeping horizontal padding
     * off the container (a block can go edge to edge), so it takes the
     * numbers from resolve() and lays them out itself.
     *
     * Returned as a declaration list rather than a full rule so the caller
     * owns its own selector.
     */
    public static function containerCss(array $biolinkSettings, array $defaults = self::MENU_DEFAULTS): string
    {
        $l = self::resolve($biolinkSettings, $defaults);

        return implode("\n", [
            'max-width: '.$l['max_width_phone'].'px;',
            'margin: 0 auto;',
            'padding: '.$l['page_padding_top'].'px '.$l['page_padding_x'].'px '.$l['page_padding_bottom'].'px;',
        ]);
    }

    /**
     * The two media queries that widen that container.
     *
     * Phone is the base because the overwhelming majority of menu traffic
     * is a phone pointed at a QR code on a table.
     */
    public static function widthQueriesCss(array $biolinkSettings, string $selector, array $defaults = self::MENU_DEFAULTS): string
    {
        $l = self::resolve($biolinkSettings, $defaults);

        return '@media (min-width: 640px) { '.$selector.' { max-width: '.$l['max_width_tablet'].'px; } }'
            ."\n".'@media (min-width: 1024px) { '.$selector.' { max-width: '.$l['max_width_desktop'].'px; } }';
    }
}
