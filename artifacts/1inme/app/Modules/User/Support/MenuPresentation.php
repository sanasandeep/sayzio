<?php

namespace App\Modules\User\Support;

/**
 * How a Restaurant Menu or Store Menu page looks.
 *
 * Sana, 2026-09-23: "for restaurant menu, store menu: i dont see much
 * options of backgrounds, fonts and other changes here... need detailed
 * changes just like in link in bio" and "multiple layout options like 2
 * columns, 1 columns... show atleast 5 options".
 *
 * ---- The font was not missing, it was ignored --------------------------
 *
 * These two page types are in Link::BIOLINK_FAMILY, so they reach the same
 * Appearance screen a Link in Bio uses -- background picker, font picker,
 * stickers, the lot -- and BiolinkBlockController::updatePageSettings saves
 * every one of those keys for them. The menu templates then read exactly
 * one of them (the background) and hardcoded the system font stack, so a
 * creator could pick Playfair Display, save it, see it saved, and get
 * -apple-system on the page forever.
 *
 * This class is the one place both templates ask what to render, so a key
 * that is offered in the editor is a key that reaches the page.
 *
 * ---- Layout ------------------------------------------------------------
 *
 * Both pages had one hardcoded column: photo left, text right, forever.
 * LAYOUTS below are five ways to draw the same items; the choice lives in
 * the menu row's `settings.layout` and the templates branch on the class
 * name alone, so the markup stays single-source and a sixth layout is a
 * CSS block rather than a fork of the item loop.
 */
class MenuPresentation
{
    /**
     * The five ways to lay a menu out.
     *
     * Ordered roughly by how much room each gives a photo, because that is
     * the real decision: a bakery with a picture of everything wants
     * `showcase`, a bar with forty whiskies wants `compact`.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const LAYOUTS = [
        'list' => [
            'label' => 'List',
            'hint'  => 'One column, small photo beside each item. The classic, and the safest on a phone.',
        ],
        'cards' => [
            'label' => 'Two columns',
            'hint'  => 'Items as cards, two across on a laptop and one on a phone. Good for a medium menu with photos.',
        ],
        'grid' => [
            'label' => 'Photo grid',
            'hint'  => 'Three across, photo on top. Best when every item has a good picture.',
        ],
        'compact' => [
            'label' => 'Compact',
            'hint'  => 'Text only, name and price on one line. Photos are hidden. For long lists -- drinks, sides, a wine list.',
        ],
        'showcase' => [
            'label' => 'Showcase',
            'hint'  => 'One item per row with a full-width photo above it. Few items, shown large.',
        ],
    ];

    public const DEFAULT_LAYOUT = 'list';

    /**
     * The colours a menu page can be given, and what each one paints.
     *
     * Sana, 2026-09-23: "while managing design for restaurent menu... i
     * cannot change colors of menu items and all".
     *
     * He was right. This template read exactly ONE colour out of the
     * creator's settings -- the accent -- and hardcoded everything else
     * (`color:#111`, with a prefers-color-scheme swap to `#f5f5f7`). So a
     * menu could be given a background, a font and a layout, and its item
     * names stayed whatever the browser's colour scheme decided.
     *
     * Each of these is optional. An unset colour inherits the page ink the
     * background picker already resolves, which is what the page did
     * before -- so a menu nobody has recoloured looks exactly as it does
     * today.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const COLOURS = [
        'heading_color' => [
            'label' => 'Category headings',
            'hint'  => 'The name above each group of items.',
        ],
        'item_color' => [
            'label' => 'Item names',
            'hint'  => 'Inherits the page text colour when unset.',
        ],
        'desc_color' => [
            'label' => 'Descriptions',
            'hint'  => 'The small print under an item. Defaults to a faded version of the item colour.',
        ],
        'price_color' => [
            'label' => 'Prices',
            'hint'  => 'Defaults to the accent colour.',
        ],
        'divider_color' => [
            'label' => 'Dividers',
            'hint'  => 'The hairline between items.',
        ],
    ];

    /**
     * How one item is separated from the next.
     *
     * Sana, 2026-09-23, listing what his printed card has and the product
     * does not: "menu dividers". PR A gave the divider a COLOUR; it still
     * had exactly one shape, a hairline, and no way to turn it off. A
     * pricier card uses a dotted rule or nothing at all, and both of those
     * are one CSS block rather than a template fork.
     *
     * `line` is the default because it is what every existing menu already
     * draws -- picking nothing must not change a live page.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const DIVIDERS = [
        'line' => [
            'label' => 'Hairline',
            'hint'  => 'A thin rule between items. What menus draw today.',
        ],
        'dotted' => [
            'label' => 'Dotted',
            'hint'  => 'A dotted rule, the way a printed card is usually set.',
        ],
        'none' => [
            'label' => 'None',
            'hint'  => 'Space alone separates the items. Cleanest with photos.',
        ],
    ];

    public const DEFAULT_DIVIDER = 'line';

    /**
     * How a section title is set.
     *
     * Sana, 2026-09-23: "design and style of cats and sub cats" and "design
     * looks of menu section". Both pages had exactly one: left-aligned,
     * bold, 18px, no rule, forever. A printed card almost never looks like
     * that -- it centres its headings between rules, or sets them as a
     * small letter-spaced label, or runs them across a filled band.
     *
     * The same choice paints sub-section headings one step quieter, so a
     * card reads as one design rather than two.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const HEADINGS = [
        'plain' => [
            'label' => 'Plain',
            'hint'  => 'Left, bold, no rule. What menus draw today.',
        ],
        'underline' => [
            'label' => 'Underlined',
            'hint'  => 'A rule under the title, across the column.',
        ],
        'centered' => [
            'label' => 'Centred',
            'hint'  => 'Centred with a rule either side, the way a printed card is set.',
        ],
        'label' => [
            'label' => 'Small caps',
            'hint'  => 'A small letter-spaced label over a rule. Quiet, for a long card.',
        ],
        'banner' => [
            'label' => 'Banner',
            'hint'  => 'The title on a filled band in the accent colour.',
        ],
    ];

    public const DEFAULT_HEADING = 'plain';

    /**
     * Where an item's price sits.
     *
     * Sana, 2026-09-23: "pricing on same column or new column".
     *
     * Leader dots existed already -- and were welded to the Compact layout,
     * so the one thing his printed card does on every line was unreachable
     * from the other four. Placement is its own axis now, which is what it
     * always was: a card with photos can still run its prices down the
     * right edge.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const PRICES = [
        'inline' => [
            'label' => 'Under the name',
            'hint'  => 'The price sits below the item, in the text column.',
        ],
        'right' => [
            'label' => 'Right column',
            'hint'  => 'Prices line up down the right edge, level with each name.',
        ],
        'dots' => [
            'label' => 'Right, with dots',
            'hint'  => 'Prices on the right with leader dots running to them.',
        ],
    ];

    /** A layout key that exists, falling back rather than rendering nothing. */
    public static function layout(?string $key): string
    {
        return isset(self::LAYOUTS[$key]) ? $key : self::DEFAULT_LAYOUT;
    }

    /** A divider key that exists, falling back to the hairline every menu has. */
    public static function divider(?string $key): string
    {
        return isset(self::DIVIDERS[$key]) ? $key : self::DEFAULT_DIVIDER;
    }

    /** A heading key that exists, falling back to what menus draw today. */
    public static function heading(?string $key): string
    {
        return isset(self::HEADINGS[$key]) ? $key : self::DEFAULT_HEADING;
    }

    /**
     * A price placement that exists.
     *
     * The default DEPENDS ON THE LAYOUT, and that is the whole care here.
     * Leader dots used to be part of what "Compact" meant -- welded into
     * that layout's CSS with no way to ask for them elsewhere and no way to
     * turn them off. Pulling them out into their own axis would silently
     * restyle every Compact menu on the platform unless an unset value
     * still means dots THERE and inline everywhere else, which is what this
     * does.
     */
    public static function price(?string $key, string $layout): string
    {
        if (isset(self::PRICES[$key])) {
            return $key;
        }

        return $layout === 'compact' ? 'dots' : 'inline';
    }

    /**
     * Resolve everything a menu template needs to paint itself.
     *
     * @param  array  $bs        the link's `settings['biolink']` array
     * @param  array  $menuSettings  the menu row's `settings` array
     * @param  string $accent    the menu row's accent colour
     * @return array{
     *     layout: string,
     *     font_family: string,
     *     font_css: string,
     *     font_href: ?string,
     *     heading_family: string,
     *     heading_css: string,
     *     accent: string
     * }
     */
    public static function resolve(array $bs, array $menuSettings, string $accent): array
    {
        $body    = self::cleanFamily($bs['font_family'] ?? '');
        // A Block Theme font, when the creator set one, is the nearer
        // choice for headings -- it is the "text on my page" control on the
        // same screen. Falls back to the page font so one picker is enough.
        $heading = self::cleanFamily(($bs['block_theme']['font_family'] ?? '')) ?: $body;

        // Colours the creator chose, each one optional. Anything unset is
        // returned as an empty string and the template falls back to what
        // it painted before -- so this cannot change an existing menu.
        $colours = [];
        foreach (array_keys(self::COLOURS) as $key) {
            $colours[$key] = self::hex($menuSettings[$key] ?? null);
        }

        $layout = self::layout($menuSettings['layout'] ?? null);

        return $colours + [
            'layout'         => $layout,
            'divider'        => self::divider($menuSettings['divider'] ?? null),
            'heading_style'  => self::heading($menuSettings['heading_style'] ?? null),
            'price_style'    => self::price($menuSettings['price_style'] ?? null, $layout),
            'font_family'    => $body,
            'font_css'       => self::cssStack($body),
            'font_href'      => self::googleHref(array_filter([$body, $heading])),
            'heading_family' => $heading,
            'heading_css'    => self::cssStack($heading),
            'accent'         => $accent !== '' ? $accent : '#3d6bff',
        ];
    }

    /**
     * A family name safe to drop into CSS and into a Google Fonts URL.
     *
     * Uploaded fonts are stored as "custom:Family" and served by the
     * biolink page's own @font-face block, which these templates do not
     * have -- so they resolve to the system stack rather than to a family
     * the browser will never find.
     */
    public static function cleanFamily(?string $raw): string
    {
        $raw = trim((string) $raw);

        if ($raw === '' || str_starts_with($raw, 'custom:')) {
            return '';
        }
        if (! FontCatalog::isKnown($raw)) {
            return '';
        }

        return $raw;
    }

    /** The CSS font stack, with the system fonts kept as the fallback. */
    public static function cssStack(string $family): string
    {
        $system = '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif';

        if ($family === '') {
            return $system;
        }

        return "'".str_replace("'", '', $family)."',".$system;
    }

    /**
     * One stylesheet link for every family the page uses, or null when it
     * only uses system fonts and needs no request at all.
     *
     * @param  array<int, string>  $families
     */
    public static function googleHref(array $families): ?string
    {
        $families = array_values(array_unique(array_filter($families)));

        return $families === [] ? null : FontCatalog::googleHrefCombined($families);
    }

    /**
     * A hex colour, or an empty string when nothing valid was chosen.
     *
     * Empty rather than a default on purpose: the template needs to tell
     * "the creator picked black" from "the creator picked nothing", because
     * the second case has to keep inheriting the page ink.
     */
    public static function hex(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1 ? $value : '';
    }
}
