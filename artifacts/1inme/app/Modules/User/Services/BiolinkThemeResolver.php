<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\BiolinkThemeSchedule;
use App\Modules\User\Models\Link;

/**
 * Resolves the biolink "skin" (colors, hero, header copy, background)
 * that should be visible to viewers right now.
 *
 * The cron in `routes/console.php` flips schedules through
 * `pending → active → completed` and is the source of truth for the
 * persisted theme. {@see applyActiveTheme()} is a read-time safety
 * net so a viewer hitting the page in the gap between a schedule's
 * start time and the next cron tick still sees the scheduled look.
 */
class BiolinkThemeResolver
{
    /**
     * Returns the schedule that should be visible right now for this
     * biolink — preferring an already-active row, falling back to a
     * pending row whose start has just elapsed (so visitors don't
     * see the old look in the up-to-1-minute gap before the cron
     * activates it).
     */
    public function currentScheduleFor(Link $link): ?BiolinkThemeSchedule
    {
        $now = now();

        return BiolinkThemeSchedule::query()
            ->where('link_id', $link->id)
            ->whereIn('status', [BiolinkThemeSchedule::STATUS_ACTIVE, BiolinkThemeSchedule::STATUS_PENDING])
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            // Most-recently-started wins if multiple windows overlap.
            ->orderByDesc('starts_at')
            ->with('theme')
            ->first();
    }

    /**
     * Mutate `$link->settings['biolink']` in-memory so the public
     * renderer sees the active scheduled theme. No-op when nothing
     * is scheduled. Does NOT persist — persistence happens in the
     * activation cron when the schedule first flips to `active`.
     */
    public function applyActiveTheme(Link $link): void
    {
        if (!$link->isBiolinkFamily()) return;

        $sched = $this->currentScheduleFor($link);
        if (!$sched || !$sched->theme) return;

        $themeSettings = (array) ($sched->theme->settings ?? []);
        if (empty($themeSettings)) return;

        $settings = $link->settings ?? [];
        $current  = (array) ($settings['biolink'] ?? []);

        // Theme overlay wins on the themable keys, but anything the
        // theme doesn't touch (analytics, share button, menu_bar, etc.)
        // is preserved from the live page settings.
        $settings['biolink'] = array_replace($current, $themeSettings);
        $link->settings = $settings;
    }

    /**
     * Look-and-feel keys a theme captures, OTHER than the background.
     *
     * Limiting the snapshot keeps it focused and avoids scheduling a theme
     * that accidentally toggles unrelated wiring like analytics or
     * share-button config.
     *
     * @var list<string>
     */
    private const THEMABLE_NON_BACKGROUND_KEYS = [
        'font_family', 'font_color',
        'button_style', 'button_color', 'button_text_color',
        'biolink_title', 'biolink_description',
        'block_theme',
    ];

    /**
     * Every biolink-settings key a theme captures.
     *
     * The background half is taken from the renderer's own field list
     * rather than restated here. It used to be restated, was written
     * before presets, mesh, pattern, tiles and torn paper existed, and
     * never caught up: a theme captured from a page with a Tiles
     * background stored `background_type: tiles` and none of the eleven
     * fields that say WHICH tiles. Activating or reverting that schedule
     * therefore restored a background nobody had chosen.
     *
     * Deriving it means the next background field is themable the day it
     * is added, and ScheduledThemesCaptureTheWholeBackgroundTest fails if
     * anyone reintroduces a hand-maintained copy.
     *
     * @var list<string>
     */
    public const THEMABLE_KEYS = [
        ...\App\Modules\User\Support\PageBackground::FIELDS,
        ...self::THEMABLE_NON_BACKGROUND_KEYS,
    ];

    /**
     * Capture the themable fields out of a link's biolink settings.
     * Non-themable keys (menu_bar, share_button, meta, etc.) are
     * dropped intentionally.
     *
     * @return array<string, mixed>
     */
    public function snapshotFromLink(Link $link): array
    {
        $bs = (array) (($link->settings ?? [])['biolink'] ?? []);
        $out = [];
        foreach (self::THEMABLE_KEYS as $k) {
            if (array_key_exists($k, $bs)) $out[$k] = $bs[$k];
        }
        return $out;
    }
}
