<?php

namespace App\Modules\User\Support;

/**
 * The icons a choice may wear, and how many times one may repeat.
 *
 * Sana, 2026-09-28: "it should represent with icons.. like multiple chilis
 * for spicy.. 1 to 3 / hot and cold / and any other".
 *
 * ---- Why there is no chilli --------------------------------------------
 *
 * There was meant to be one. It was drawn three times and rendered at the
 * size it actually appears -- 15px beside a choice name -- and all three
 * read as a banana. What makes a chilli legible is the curve plus the stem,
 * and at 15px the curve is just a fruit outline. A flame carries the same
 * "one, two, three" meaning, reads correctly at that size, and is what most
 * delivery apps use for heat anyway. So: FLAME, repeated.
 *
 * ---- Why a fixed set rather than "upload an icon" -----------------------
 *
 * Because the icon has to read at 15px on a phone, on both a light and a
 * dark sheet, next to two others of the same shape. An uploaded PNG fails
 * all three of those and there is no screen on which the owner could tell.
 * These are single-path outlines in currentColor, so they inherit the
 * sheet's text colour and cannot end up invisible on it.
 *
 * ---- Why repeat is a number and not three separate icons ---------------
 *
 * "Mild / Medium / Hot" is one icon at one, two and three. Three icons
 * would mean three drawings that have to stay in the same family, and an
 * owner who wants four levels would be stuck. A count is the thing that
 * actually varies.
 */
class MenuOptionIcon
{
    /** How many times one icon may be repeated on a single choice. */
    public const MAX_REPEAT = 3;

    /**
     * The catalogue. `path` is SVG path data drawn on a 24x24 box and
     * STROKED, not filled: an outline at 2px holds its shape at 15px where
     * a filled glyph turns into a blob.
     *
     * @return array<string, array{label: string, hint: string, path: string}>
     */
    public static function catalogue(): array
    {
        return [
            'flame' => [
                'label' => 'Flame',
                'hint'  => 'Heat. One, two or three for mild, medium and hot.',
                // Four flame outlines were rendered at 15px side by side
                // before this one was picked: it keeps its silhouette and
                // its inner tongue stays open at that size, where a plain
                // teardrop body reads as the Drop icon two rows down.
                'path'  => 'M12.5 2c-.3 2.2.6 3.6 1.8 5 1.3 1.5 2.7 3 2.7 5.5a5 5 0 0 1-10 0c0-1.1.3-2 .9-2.9.2 1.4 1 2.2 2 2.2 1.3 0 2.1-1 2.1-2.5 0-2.2-1.4-3.6.5-7.3z',
            ],
            'snowflake' => [
                'label' => 'Snowflake',
                'hint'  => 'Served chilled, iced, frozen.',
                'path'  => 'M12 2.5v19 M3.8 7.2l16.4 9.6 M20.2 7.2 3.8 16.8 M12 6 9.9 3.9 M12 6l2.1-2.1 M12 18l-2.1 2.1 M12 18l2.1 2.1',
            ],
            'drop' => [
                'label' => 'Drop',
                'hint'  => 'Sauce, gravy, dressing, oil.',
                'path'  => 'M12 3.2c0 0 5.8 6.3 5.8 10.3a5.8 5.8 0 0 1-11.6 0C6.2 9.5 12 3.2 12 3.2z',
            ],
            'sprig' => [
                'label' => 'Herb sprig',
                'hint'  => 'Coriander, mint, basil, greens.',
                'path'  => 'M12 21.5V8 M12 8c0-2.8 2.2-5 5-5 0 2.8-2.2 5-5 5z M12 14c-2.8 0-5-2.2-5-5 2.8 0 5 2.2 5 5z',
            ],
            'egg' => [
                'label' => 'Egg',
                'hint'  => 'Contains egg, or an egg on top.',
                'path'  => 'M12 3c3.3 0 6 4 6 8.2S15.3 18.5 12 18.5 6 15.4 6 11.2 8.7 3 12 3z',
            ],
            'wheat' => [
                'label' => 'Wheat',
                'hint'  => 'Grain, bread, contains gluten.',
                'path'  => 'M12 21.5V7 M12 12c-2.5 0-4-1.5-4-4 2.5 0 4 1.5 4 4z M12 12c2.5 0 4-1.5 4-4-2.5 0-4 1.5-4 4z M12 7c-2.5 0-4-1.5-4-4 2.5 0 4 1.5 4 4z M12 7c2.5 0 4-1.5 4-4-2.5 0-4 1.5-4 4z',
            ],
            'milk' => [
                'label' => 'Milk carton',
                'hint'  => 'Dairy, cream, cheese.',
                'path'  => 'M8.5 3h7v3l2 3v11.5h-11V9l2-3z M8.5 6h7',
            ],
            'star' => [
                'label' => 'Star',
                'hint'  => "The kitchen's pick, house special.",
                'path'  => 'M12 3.2l2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.7l6.1-.9L12 3.2z',
            ],
            'clock' => [
                'label' => 'Clock',
                'hint'  => 'Takes longer, slow-cooked, made to order.',
                'path'  => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z M12 7.3V12l3.6 2.1',
            ],
        ];
    }

    /** The keys, for validation. */
    public static function keys(): array
    {
        return array_keys(self::catalogue());
    }

    public static function has(?string $key): bool
    {
        return $key !== null && $key !== '' && array_key_exists($key, self::catalogue());
    }

    /**
     * A key we will actually draw, or null. Anything unrecognised becomes
     * null rather than being stored: a choice with an icon name nothing can
     * draw renders as a gap the owner cannot explain.
     */
    public static function sanitize(?string $key): ?string
    {
        $key = is_string($key) ? trim($key) : '';

        return self::has($key) ? $key : null;
    }

    /**
     * How many times to draw it. One when there is no icon, so the column
     * never holds a count for an icon that is not there.
     */
    public static function repeat(?string $key, mixed $count): int
    {
        if (! self::has(self::sanitize($key))) {
            return 1;
        }

        return max(1, min(self::MAX_REPEAT, (int) $count));
    }

    /**
     * Just the path data, keyed by name -- what the pages need. The labels
     * and hints are for the editor's picker and stay out of the public
     * payload.
     *
     * @return array<string, string>
     */
    public static function paths(): array
    {
        return array_map(fn ($icon) => $icon['path'], self::catalogue());
    }

    /**
     * The picker's list: key, label, hint and path together, in catalogue
     * order.
     *
     * @return array<int, array{key: string, label: string, hint: string, path: string}>
     */
    public static function forPicker(): array
    {
        $out = [];
        foreach (self::catalogue() as $key => $icon) {
            $out[] = ['key' => $key] + $icon;
        }

        return $out;
    }
}
