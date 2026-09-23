<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Link;

/**
 * The share button: one definition, every page that has a display.
 *
 * Sana, 2026-09-23: "for all link in bio or any other pages or link
 * types where display is there, i want share button with qr code to show
 * same page as well as share link options like to whatsapp, telegrams
 * and others..... share button should be visible or not, customizable
 * options should be there in settings on that link. default active".
 *
 * Most of it already existed -- and only on Link in Bio, off by default,
 * with its QR fetched from api.qrserver.com. Three things follow from
 * that, and each is a deliberate change here:
 *
 *  1. The page type stopped being the gate. Any link that renders a page
 *     someone can look at can carry this; SHAREABLE below is that list,
 *     and it is derived from the renderer rather than hand-maintained
 *     alongside it.
 *
 *  2. The QR is ours. Sayzio renders QR codes already, at /qr/render --
 *     so every scan of a share QR was being handed to a third party
 *     along with the URL of the page, in exchange for an image we can
 *     make ourselves. It also meant a scan could never be counted, which
 *     is the other half of what Sana asked for: the QR now carries
 *     ?src=share_qr, and each share link its own tag, so the page's
 *     analytics can tell a scan from a tap from a WhatsApp forward.
 *
 *  3. It defaults to on. An absent setting means on; an explicit `false`
 *     -- someone who went and turned it off -- still means off. Those
 *     are different states and conflating them would override a decision
 *     a creator already made.
 */
class ShareButton
{
    /**
     * Link types that render a page a visitor looks at.
     *
     * Excluded, deliberately: `url` (a redirect, there is no page),
     * `file` and `vcf` (downloads -- the browser never shows a page to
     * put a button on).
     */
    public const SHAREABLE = [
        ...Link::BIOLINK_FAMILY,
        Link::TYPE_REVIEWS,
        Link::TYPE_RESUME,
        Link::TYPE_PAID_PAGE,
        Link::TYPE_BRAND_KIT,
        Link::TYPE_CALENDAR,
        Link::TYPE_UPDATES,
        'ics',   // the public event page
        'text',  // the hosted text page
    ];

    /**
     * The networks the popup offers, in the order they appear.
     *
     * `tag` is what lands in the click row's source column, so the
     * owner's analytics can separate a WhatsApp forward from a scan.
     * `url` takes the share URL already encoded.
     *
     * @var array<int, array{key: string, label: string, icon: string, tag: string, url: string}>
     */
    public const NETWORKS = [
        ['key' => 'whatsapp', 'label' => 'WhatsApp', 'icon' => 'fab fa-whatsapp',  'tag' => 'share_whatsapp', 'url' => 'https://wa.me/?text=%s'],
        ['key' => 'telegram', 'label' => 'Telegram', 'icon' => 'fab fa-telegram',  'tag' => 'share_telegram', 'url' => 'https://t.me/share/url?url=%s'],
        ['key' => 'x',        'label' => 'X',        'icon' => 'fab fa-x-twitter', 'tag' => 'share_x',        'url' => 'https://twitter.com/intent/tweet?url=%s'],
        ['key' => 'facebook', 'label' => 'Facebook', 'icon' => 'fab fa-facebook-f', 'tag' => 'share_facebook', 'url' => 'https://www.facebook.com/sharer/sharer.php?u=%s'],
        ['key' => 'linkedin', 'label' => 'LinkedIn', 'icon' => 'fab fa-linkedin-in', 'tag' => 'share_linkedin', 'url' => 'https://www.linkedin.com/sharing/share-offsite/?url=%s'],
        ['key' => 'reddit',   'label' => 'Reddit',   'icon' => 'fab fa-reddit-alien', 'tag' => 'share_reddit',  'url' => 'https://reddit.com/submit?url=%s'],
        ['key' => 'email',    'label' => 'Email',    'icon' => 'fas fa-envelope',  'tag' => 'share_email',    'url' => 'mailto:?body=%s'],
        ['key' => 'sms',      'label' => 'Message',  'icon' => 'fas fa-comment-sms', 'tag' => 'share_sms',    'url' => 'sms:?&body=%s'],
    ];

    /** The source tag a QR scan is recorded under. */
    public const QR_TAG = 'share_qr';

    /** Every tag this feature can put in a click row's source column. */
    public static function sourceTags(): array
    {
        return array_merge(
            [self::QR_TAG],
            array_column(self::NETWORKS, 'tag')
        );
    }

    /**
     * How these tags read on the stats screen.
     *
     * The analytics page falls back to ucfirst(str_replace('_', ' ')) for
     * an unknown source, which would render this feature's rows as
     * "Share qr" and "Share whatsapp". They are the answer to "how are
     * people finding this page", so they are worth naming properly.
     *
     * @return array<string, string>
     */
    public static function sourceLabels(): array
    {
        $out = [self::QR_TAG => 'QR scan'];
        foreach (self::NETWORKS as $n) {
            $out[$n['tag']] = 'Shared via '.$n['label'];
        }

        return $out;
    }

    /** Can this link type carry a share button at all? */
    public static function supports(?string $linkType): bool
    {
        return $linkType !== null && in_array($linkType, self::SHAREABLE, true);
    }

    /**
     * Resolve the settings a page renders with.
     *
     * Note what `enabled` does with a missing key versus a false one: the
     * first is "never configured", which is now on; the second is "turned
     * off on purpose", which stays off. A blanket default would quietly
     * re-enable the button on every page whose owner had already said no.
     *
     * @param  array<string, mixed>  $bs  the link's settings['biolink'] array
     * @return array<string, mixed>
     */
    public static function resolve(array $bs): array
    {
        $s = is_array($bs['share_button'] ?? null) ? $bs['share_button'] : [];

        // Missing means "never configured" -- offer all of them. An
        // explicitly stored empty list means the creator unticked every
        // box, which is a real choice: QR and copy-link only. Treating
        // the two the same would make unticking them all do nothing.
        $networks = array_key_exists('networks', $s) && is_array($s['networks'])
            ? array_values(array_intersect(array_column(self::NETWORKS, 'key'), $s['networks']))
            : array_column(self::NETWORKS, 'key');

        return [
            'enabled'      => array_key_exists('enabled', $s) ? (bool) $s['enabled'] : true,
            'show_qr'      => array_key_exists('show_qr', $s) ? (bool) $s['show_qr'] : true,
            'style'        => in_array($s['style'] ?? '', ['fab', 'bar', 'icon'], true) ? $s['style'] : 'fab',
            'position'     => in_array($s['position'] ?? '', ['bottom-right', 'bottom-left', 'bottom-center', 'top-right', 'top-left'], true)
                                ? $s['position'] : 'bottom-right',
            'size'         => in_array($s['size'] ?? '', ['sm', 'md', 'lg'], true) ? $s['size'] : 'md',
            'label'        => self::text($s['label'] ?? '', 30) ?: 'Share',
            'color'        => self::hex($s['color'] ?? '', '#3d6bff'),
            'text_color'   => self::hex($s['text_color'] ?? '', '#ffffff'),
            'qr_size'      => max(100, min(400, (int) ($s['qr_size'] ?? 200))),
            'qr_fg_color'  => self::hex($s['qr_fg_color'] ?? '', '#000000'),
            'qr_bg_color'  => self::hex($s['qr_bg_color'] ?? '', '#ffffff'),
            'networks'     => $networks,
        ];
    }

    /**
     * Validation rules, keyed as the settings form names them.
     *
     * Shared for the same reason PageBackgroundInput::rules() is: a page
     * type that accepts less than the form sends is a control that looks
     * like it works and does not.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'share_button'              => 'nullable|array',
            'share_button.enabled'      => 'boolean',
            'share_button.show_qr'      => 'boolean',
            'share_button.style'        => 'nullable|string|in:fab,bar,icon',
            'share_button.position'     => 'nullable|string|in:bottom-right,bottom-left,bottom-center,top-right,top-left',
            'share_button.color'        => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'share_button.text_color'   => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'share_button.size'         => 'nullable|string|in:sm,md,lg',
            'share_button.qr_size'      => 'nullable|integer|min:100|max:400',
            'share_button.qr_fg_color'  => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'share_button.qr_bg_color'  => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'share_button.label'        => 'nullable|string|max:30',
            'share_button.networks'     => 'nullable|array|max:20',
            // `nullable` is load-bearing. The settings form posts a hidden
            // EMPTY entry ahead of the checkboxes, so that unticking all
            // of them still sends the key -- otherwise HTML omits the
            // field and "none selected" would arrive as "never
            // configured", which means all. Laravel's
            // ConvertEmptyStringsToNull turns that sentinel into null
            // before validation ever sees it, so the rule has to let a
            // null through; the save path filters it out.
            'share_button.networks.*'   => ['nullable', 'string', 'in:'.implode(',', array_column(self::NETWORKS, 'key'))],
        ];
    }

    /**
     * The same URL with a source tag on it, so the visit it produces is
     * attributable. Tags ride as `src`, which the public renderer already
     * reads for the Event Connect QR.
     */
    public static function tagged(string $url, string $tag): string
    {
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.'src='.urlencode($tag);
    }

    private static function hex(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1 ? $value : $fallback;
    }

    private static function text(mixed $value, int $max): string
    {
        return mb_substr(trim(is_string($value) ? $value : ''), 0, $max);
    }
}
