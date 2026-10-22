<?php

namespace App\Modules\User\Support;

/**
 * Font Awesome name -> Lucide symbol id, for the navigation sprite.
 *
 * WHY THIS IS A CLASS AND NOT A FIND-AND-REPLACE
 *
 * Most of the nav's glyphs are literals in Blade and were rewritten
 * once, by script, with anything unmapped treated as an error rather
 * than a blank square. But three sources are not literals: the seven
 * rail sections come from NavRailSections, the settings tabs come
 * from SettingsTabs, and a workspace's symbol comes from a database
 * column. The first two can be mapped at render time -- that is what
 * this is for. The third cannot, because nothing constrains what gets
 * stored, so those stay on Font Awesome and say so.
 *
 * A name with no entry here resolves to the help glyph rather than to
 * nothing: a missing <use> target renders an empty box with no hint
 * that anything is wrong, and a question mark at least reads as "this
 * icon is missing" to whoever sees it.
 */
class NavIcons
{
    /** What a name resolves to when the map has no entry for it. */
    public const FALLBACK = 'circle-help';

    /** @var array<string, string> */
    public const MAP = [
        'fa-address-book' => 'book-user',
        'fa-bars' => 'menu',
        'fa-bell' => 'bell',
        'fa-bolt' => 'zap',
        'fa-boxes-stacked' => 'boxes',
        'fa-brain' => 'brain',
        'fa-building' => 'building-2',
        'fa-bullseye' => 'target',
        'fa-calculator' => 'calculator',
        'fa-calendar-check' => 'calendar-check',
        'fa-calendar-days' => 'calendar-days',
        'fa-calendar-week' => 'calendar-range',
        'fa-certificate' => 'award',
        'fa-chart-line' => 'chart-line',
        'fa-chevron-down' => 'chevron-down',
        'fa-chevron-right' => 'chevron-right',
        'fa-chevron-up' => 'chevron-up',
        'fa-circle-plus' => 'circle-plus',
        'fa-circle-question' => 'circle-help',
        'fa-clipboard-list' => 'clipboard-list',
        'fa-comment-dots' => 'message-circle',
        'fa-diagram-project' => 'workflow',
        'fa-ellipsis' => 'ellipsis',
        'fa-envelope-open-text' => 'mail-open',
        'fa-file-invoice-dollar' => 'receipt-text',
        'fa-file-lines' => 'file-text',
        'fa-folder' => 'folder',
        'fa-folder-open' => 'folder-open',
        'fa-gem' => 'gem',
        'fa-gift' => 'gift',
        'fa-house' => 'house',
        'fa-id-badge' => 'contact',
        'fa-inbox' => 'inbox',
        'fa-link' => 'link',
        'fa-list-check' => 'list-checks',
        'fa-network-wired' => 'network',
        'fa-palette' => 'palette',
        'fa-pen-to-square' => 'square-pen',
        'fa-percent' => 'percent',
        'fa-phone' => 'phone',
        'fa-plus' => 'plus',
        'fa-plus-circle' => 'circle-plus',
        'fa-qrcode' => 'qr-code',
        'fa-receipt' => 'receipt',
        'fa-repeat' => 'repeat',
        'fa-right-from-bracket' => 'log-out',
        'fa-robot' => 'bot',
        'fa-rocket' => 'rocket',
        'fa-rotate' => 'rotate-cw',
        'fa-sack-dollar' => 'wallet',
        'fa-search' => 'search',
        'fa-server' => 'server',
        'fa-sign-out-alt' => 'log-out',
        'fa-sliders' => 'sliders-horizontal',
        'fa-stream' => 'list',
        'fa-table-columns' => 'panel-left',
        'fa-user-astronaut' => 'circle-user',
        'fa-user-group' => 'users-round',
        'fa-user-pen' => 'user-pen',
        'fa-user-plus' => 'user-plus',
        'fa-user-secret' => 'venetian-mask',
        'fa-user-shield' => 'shield-user',
        'fa-users' => 'users',
        'fa-vault' => 'vault',
        'fa-wand-magic-sparkles' => 'wand-sparkles',
    ];

    /**
     * The sprite id for a Font Awesome class name, with or without the
     * `fa-` prefix and with or without a leading `fas `.
     */
    public static function lucide(?string $fa): string
    {
        $key = trim((string) $fa);
        $key = preg_replace('/^fa[srlbd]?\s+/', '', $key) ?? $key;
        $key = trim(explode(' ', $key)[0]);

        return self::MAP[$key] ?? self::FALLBACK;
    }

    /** Every symbol id the map can produce, for the sprite to cover. */
    public static function symbols(): array
    {
        $ids = array_values(self::MAP);
        $ids[] = self::FALLBACK;
        $ids = array_unique($ids);
        sort($ids);

        return $ids;
    }
}
