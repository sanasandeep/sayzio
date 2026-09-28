<?php

namespace App\Modules\User\Support;

use Illuminate\Support\Collection;

/**
 * Where a block sits relative to the menu on a menu page.
 *
 * Sana, 2026-09-28: "how to add blocks on top of menu or in betweel
 * somwhere like end of each sec section?"
 *
 * He could not. The renderer had two slots, 'above' and 'below', and
 * NOTHING anywhere wrote settings._style._menu_slot -- no editor control, no
 * API field, no default. One filter read it and every block fell through to
 * 'below'. So the "above the menu" position existed in the code and no
 * creator could ever reach it, and "after a section" was not expressible at
 * all.
 *
 * That is the fifth time this month the same shape has turned up: a
 * capability built, a screen that never offers it. So the slot is a value
 * object now rather than a string compared in a Blade filter, and the thing
 * that decides what a slot MEANS is one method both the renderer and the
 * editor call.
 *
 * ---- The slot vocabulary ----------------------------------------------
 *
 *   'above'          before the menu
 *   'below'          after the menu   <- the default, so nothing moves
 *   'section:<id>'   after that section, before the next one
 *
 * 'below' is the default on purpose: every block that exists today has no
 * slot, and every one of them is currently rendered after the menu. A
 * different default would silently rearrange every menu page in the
 * product.
 */
class MenuBlockSlot
{
    public const ABOVE = 'above';

    public const BELOW = 'below';

    public const DEFAULT = self::BELOW;

    /** The prefix that makes a slot mean "after this section". */
    public const SECTION_PREFIX = 'section:';

    /** The slot string for "after section N". */
    public static function forSection(int $categoryId): string
    {
        return self::SECTION_PREFIX.$categoryId;
    }

    /**
     * The category id a slot points at, or null when it is not a section
     * slot. Anything malformed reads as null rather than throwing: this
     * value comes out of a settings JSON blob that predates the field.
     */
    public static function sectionId(?string $slot): ?int
    {
        if (! is_string($slot) || ! str_starts_with($slot, self::SECTION_PREFIX)) {
            return null;
        }

        $id = substr($slot, strlen(self::SECTION_PREFIX));

        return ctype_digit($id) ? (int) $id : null;
    }

    /**
     * What this block's slot actually resolves to, given the sections the
     * menu has right now.
     *
     * The load-bearing case is a block pinned after a section that has since
     * been DELETED. Filtering on the raw value would drop that block off the
     * page with nothing to say so -- the creator's content silently gone
     * because they reorganised their menu. It falls back to the default
     * instead, which is where every block sat before this existed.
     *
     * @param  array<int, int>|Collection  $liveCategoryIds
     */
    public static function resolve(?string $slot, $liveCategoryIds): string
    {
        if ($slot === self::ABOVE) {
            return self::ABOVE;
        }

        $sectionId = self::sectionId($slot);
        if ($sectionId === null) {
            // 'below', null, or junk.
            return self::DEFAULT;
        }

        $ids = $liveCategoryIds instanceof Collection
            ? $liveCategoryIds->all()
            : $liveCategoryIds;

        $ids = array_map('intval', (array) $ids);

        return in_array($sectionId, $ids, true)
            ? self::forSection($sectionId)
            : self::DEFAULT;
    }

    /** A block's stored slot, before resolution. */
    public static function of(array $settings): string
    {
        $slot = $settings['_style']['_menu_slot'] ?? null;

        return is_string($slot) && $slot !== '' ? $slot : self::DEFAULT;
    }

    /**
     * Is this a slot a creator may choose? Used to validate what the editor
     * sends, so a block cannot be pinned after a section on another menu --
     * or after one that does not exist.
     *
     * @param  array<int, int>|Collection  $liveCategoryIds
     */
    public static function isValid(?string $slot, $liveCategoryIds): bool
    {
        if ($slot === self::ABOVE || $slot === self::BELOW) {
            return true;
        }

        $sectionId = self::sectionId($slot);
        if ($sectionId === null) {
            return false;
        }

        $ids = $liveCategoryIds instanceof Collection
            ? $liveCategoryIds->all()
            : $liveCategoryIds;

        return in_array($sectionId, array_map('intval', (array) $ids), true);
    }

    /**
     * The positions to offer, in the order they appear down the page.
     *
     * The labels name the section rather than describing the mechanism,
     * because that is what the creator is looking at: "After Starters", not
     * "section:41".
     *
     * @param  Collection  $categories  top-level sections, in page order
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(Collection $categories): array
    {
        $out = [[
            'value' => self::ABOVE,
            'label' => 'Top of page',
        ]];

        foreach ($categories as $category) {
            $out[] = [
                'value' => self::forSection((int) $category->id),
                'label' => 'After '.$category->name,
            ];
        }

        $out[] = [
            'value' => self::BELOW,
            'label' => 'Bottom of page',
        ];

        return $out;
    }
}
