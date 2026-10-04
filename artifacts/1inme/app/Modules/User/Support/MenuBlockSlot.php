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
    /**
     * Before the page's hero -- its title, description and order badge.
     *
     * Sana, 2026-09-28, having set a block to "Top of page": "its not going
     * top of table.. still i see more heading and sub heading".
     *
     * He was right and the label was wrong. There was one slot above the
     * menu and it rendered AFTER the hero, so a block that said it was at
     * the top of the page had the restaurant's name and description above
     * it. Those are two different places and a creator may reasonably want
     * either, so they are two slots and each one says which it is.
     */
    public const TOP = 'top';

    /** After the hero, before the first section. */
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
        if ($slot === self::TOP || $slot === self::ABOVE) {
            return $slot;
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
     * Does this LOOK like a slot, without asking which menu it is for?
     *
     * For the style sanitizer, which is shared with the admin Block Designs
     * manager and so never has a link in hand. It is a shape test and
     * nothing more: `section:999` passes here and is refused later by
     * isValid(), which knows the menu's own sections.
     *
     * It exists because the sanitizer used to write the list of slots out
     * again, by hand, and then fell a slot behind -- which is how "Above
     * the title" came to be a position the picker offered and the save
     * silently threw away.
     */
    public static function isKnownShape(?string $slot): bool
    {
        return $slot === self::TOP
            || $slot === self::ABOVE
            || $slot === self::BELOW
            || self::sectionId($slot) !== null;
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
        if ($slot === self::TOP || $slot === self::ABOVE || $slot === self::BELOW) {
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
     * ---- Why they were rewritten -------------------------------------
     *
     * They read "Very top, above the title", "Above the menu", "After
     * Starters", "Bottom of page" -- four labels measuring from four
     * different things, two of which ("very top", "bottom of page") are
     * about the page and two of which are about the menu. Sana, 2026-10-04:
     * "the dropdown and words look unpolished".
     *
     * Every label now says where the block sits relative to one of the two
     * landmarks a creator can actually see on the page -- the title and the
     * menu -- and the list reads straight down the page from the first to
     * the last. A section's own name is quoted, so a section called "Bottom
     * of page" cannot be mistaken for the position of the same name.
     *
     * @param  Collection  $categories  top-level sections, in page order
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(Collection $categories): array
    {
        $out = [
            [
                'value' => self::TOP,
                'label' => 'Above the title',
            ],
            [
                'value' => self::ABOVE,
                'label' => 'Below the title, before the menu',
            ],
        ];

        foreach ($categories as $category) {
            $out[] = [
                'value' => self::forSection((int) $category->id),
                // Curly quotes, because a section is named by its owner and
                // "After Chef's Specials & More" has to stay readable as a
                // name rather than running into the sentence around it.
                'label' => 'After “'.$category->name.'”',
            ];
        }

        $out[] = [
            'value' => self::BELOW,
            'label' => 'Below the menu',
        ];

        return $out;
    }
}
