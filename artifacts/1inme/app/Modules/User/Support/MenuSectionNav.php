<?php

namespace App\Modules\User\Support;

/**
 * Getting down a long menu without scrolling past everything.
 *
 * Sana, 2026-10-05: "Section jumping - suggest multi different layout type
 * options: horizontal scroll tabs with all, select drop down, verticle tab
 * with icon display or number (default)" and "section headings should have
 * numbers default, option with selecting icons also".
 *
 * ---- Why a menu needs this at all --------------------------------------
 *
 * A printed card is read by eye: you see "Desserts" in your peripheral
 * vision and your thumb goes there. A phone shows one section at a time, so
 * a forty-dish menu is a scroll with no map. The customer who wants a cold
 * drink scrolls past everything twice.
 *
 * ---- Four shapes, because four rooms ------------------------------------
 *
 * None of these is better. A bar with six sections wants TABS; a cafe with
 * three wants NOTHING and would look over-built with a nav; a long card on
 * a tablet mounted at a counter wants the VERTICAL rail, which stays put
 * while the list moves. The DROPDOWN is the one that never runs out of room
 * -- twenty sections is a scroll in any horizontal bar and one tap in a
 * select.
 *
 * So this is a choice, with a default that suits the common case rather
 * than a design somebody has to work around.
 */
class MenuSectionNav
{
    /**
     * @var array<string, array{label: string, hint: string}>
     */
    public const NAVS = [
        'none' => [
            'label' => 'None',
            'hint'  => 'No jump bar. Right for a short card — three sections do not need a map.',
        ],
        'tabs' => [
            'label' => 'Scrolling tabs',
            'hint'  => 'A row of section names across the top that scrolls sideways, with “All” first. The safest on a phone.',
        ],
        'dropdown' => [
            'label' => 'Dropdown',
            'hint'  => 'One select listing every section. The only one that never runs out of room — right past about ten sections.',
        ],
        'vertical' => [
            'label' => 'Side rail',
            'hint'  => 'A column down the side that stays put while the menu scrolls. For a tablet at a counter.',
        ],
    ];

    /**
     * The default is tabs, not none.
     *
     * A menu long enough to have sections is long enough to want jumping,
     * and the bar costs one row. "None" is there for the creator who knows
     * their card is short, rather than being the state everybody has to
     * discover a setting to escape.
     */
    public const DEFAULT_NAV = 'tabs';

    /** Below this many sections a jump bar is noise, so it is not drawn. */
    public const MIN_SECTIONS = 2;

    /**
     * How a section announces itself in the nav and in its heading.
     *
     * "numbers default, option with selecting icons also" -- so the number
     * is what every menu gets for free (a section's position IS its
     * number), and an icon is the upgrade for a creator who picks one.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const MARKERS = [
        'number' => [
            'label' => 'Numbers',
            'hint'  => 'Sections are numbered 1, 2, 3 in the order you arranged them.',
        ],
        'icon' => [
            'label' => 'Icons',
            'hint'  => 'The icon you picked for each section. Sections without one fall back to their number.',
        ],
        'none' => [
            'label' => 'Nothing',
            'hint'  => 'Section names on their own.',
        ],
    ];

    public const DEFAULT_MARKER = 'number';

    /**
     * Icons a section can be given.
     *
     * Deliberately a short, food-shaped list rather than a whole icon set.
     * A picker with nine hundred icons is a picker nobody finishes, and the
     * job here is "which of my six sections is the drinks one" -- which
     * nine icons answers and nine hundred does not.
     *
     * Font Awesome names, because that is what this product already loads;
     * a tenth icon is a line here, not a new dependency.
     *
     * @var array<string, string>  key => label
     */
    public const ICONS = [
        'utensils'    => 'Food',
        'bowl-food'   => 'Bowl',
        'burger'      => 'Burger',
        'pizza-slice' => 'Pizza',
        'drumstick-bite' => 'Meat',
        'fish'        => 'Seafood',
        'leaf'        => 'Vegetarian',
        'bread-slice' => 'Bakery',
        'ice-cream'   => 'Dessert',
        'mug-hot'     => 'Hot drinks',
        'martini-glass-citrus' => 'Cold drinks',
        'cake-candles' => 'Cakes',
        'cheese'      => 'Sides',
        'pepper-hot'  => 'Spicy',
        'star'        => 'Specials',
        'tag'         => 'Offers',
    ];

    public static function nav(?string $key): string
    {
        return array_key_exists((string) $key, self::NAVS) ? (string) $key : self::DEFAULT_NAV;
    }

    public static function marker(?string $key): string
    {
        return array_key_exists((string) $key, self::MARKERS) ? (string) $key : self::DEFAULT_MARKER;
    }

    /** A key we will actually draw, or null. */
    public static function icon(?string $key): ?string
    {
        $key = is_string($key) ? trim($key) : '';

        return array_key_exists($key, self::ICONS) ? $key : null;
    }

    /**
     * Validation rules for both settings, generated.
     *
     * Hand-listing these is the mistake this codebase has made over and
     * over: a catalogue grows a fifth option, the editor offers it, and the
     * save silently drops it because a rule somewhere still lists four.
     *
     * @return array<string, mixed>
     */
    public const COLOURS = [
        'section_nav_text_color' => ['label' => 'Section tab text', 'default' => '#262626'],
        'section_nav_background_color' => ['label' => 'Section tab background', 'default' => '#ffffff'],
        'section_nav_border_color' => ['label' => 'Section tab border', 'default' => '#d4d4d4'],
    ];

    public static function colours(array $settings): array
    {
        $colours = [];
        foreach (self::COLOURS as $key => $meta) {
            $value = $settings[$key] ?? null;
            $colours[$key] = is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1
                ? $value : $meta['default'];
        }

        return $colours;
    }

    public static function rules(): array
    {
        $rules = [
            'section_nav'    => ['nullable', 'string', 'in:'.implode(',', array_keys(self::NAVS))],
            'section_marker' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::MARKERS))],
        ];
        foreach (array_keys(self::COLOURS) as $key) {
            $rules[$key] = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        return $rules;
    }

    /**
     * The jump targets for a built tree, in the order they are drawn.
     *
     * Takes the SAME tree the page renders from, so the bar cannot offer a
     * section the page does not show -- a jump link to a hidden section is
     * a link to nowhere, and the customer who taps it concludes the menu is
     * broken rather than that the section was hidden.
     *
     * @param  array  $tree  from MenuTree::build()
     * @return array<int, array{id: int, name: string, anchor: string, number: int, icon: ?string}>
     */
    public static function targets(array $tree): array
    {
        $out = [];
        $n = 0;

        foreach ($tree as $section) {
            $cat = $section['category'];
            $n++;

            $out[] = [
                'id'     => (int) $cat->id,
                'name'   => (string) $cat->name,
                'anchor' => self::anchor($cat->id),
                'number' => $n,
                'icon'   => self::icon($cat->icon ?? null),
            ];
        }

        return $out;
    }

    /**
     * A section's anchor id.
     *
     * Built from the row id rather than a slug of the name: two sections
     * called "Specials" would collide, and renaming a section would break
     * every QR code and shared link pointing at it.
     */
    public static function anchor(int|string $categoryId): string
    {
        return 'sec-'.$categoryId;
    }

    /** Is a jump bar worth drawing for this many sections? */
    public static function worthDrawing(string $nav, int $sections): bool
    {
        return $nav !== 'none' && $sections >= self::MIN_SECTIONS;
    }
}
