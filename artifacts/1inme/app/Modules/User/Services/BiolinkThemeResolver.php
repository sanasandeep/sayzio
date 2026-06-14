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
     * Whitelist of biolink-settings keys a theme captures. Limiting
     * this keeps the snapshot focused on look-and-feel and avoids
     * scheduling a theme that accidentally toggles unrelated wiring
     * like analytics or share-button config.
     */
    public const THEMABLE_KEYS = [
        'background_type', 'background_color', 'background_gradient',
        'background_image', 'gradient_colors', 'gradient_angle',
        'gradient_type', 'gradient_preset_id',
        'slideshow_images', 'slideshow_interval', 'video_url', 'video_file',
        'bg_template_id', 'bg_attachment',
        'bg_fallback_color', 'bg_fallback_image',
        'bg_blur', 'bg_overlay_color', 'bg_overlay_opacity',
        'font_family', 'font_color',
        'button_style', 'button_color', 'button_text_color',
        'biolink_title', 'biolink_description',
        'block_theme',
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
