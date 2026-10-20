<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\CompetitorTeardown;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;

/**
 * The closed set of Sayzio capabilities a teardown may recommend.
 *
 * A competitor teardown lists what a page is missing. Saying "no social
 * proof" is only half an answer; the other half is what to do about it
 * here. The obvious way to get that second half is to let the model write
 * it, and that is exactly what must not happen -- a model asked to name
 * product features will invent them, confidently, in front of 375K users.
 *
 * So the model does not write recommendations. It picks keys from this
 * list and nothing else; {@see self::sanitize()} drops anything it does
 * not recognise before the analysis is ever stored. Every label, blurb and
 * destination below is written here, by a person, and every route and
 * block type was checked against the router and BlockTypeRegistry.
 *
 * Two entries are conditional, because advice that does not apply is worse
 * than no advice:
 *
 *  - `promos` needs the creator to already sell subscription tiers. A promo
 *    code is a discount on a tier; suggesting one to somebody with nothing
 *    to discount is noise.
 *  - every block needs a built page to put the block on. The teardown knows
 *    one when `built_link_id` is set, so blocks link into that page's editor
 *    and otherwise say what they are waiting for.
 */
final class SayzioFixes
{
    public const KIND_PAGE  = 'page';
    public const KIND_BLOCK = 'block';

    /**
     * @return array<string, array{label:string, blurb:string, kind:string, icon:string, route?:string, block?:string, needs_tiers?:bool}>
     */
    public static function all(): array
    {
        return [
            // ---- Pages and tools, each a real route ----
            'review_page' => [
                'label' => 'Add a Review page',
                'blurb' => 'Collect real reviews and show them on a page of their own.',
                'kind'  => self::KIND_PAGE,
                'icon'  => 'fa-star',
                'route' => 'user.links.reviews.create',
            ],
            'lead_form' => [
                'label' => 'Build a form',
                'blurb' => 'Capture emails, enquiries or bookings instead of losing the visit.',
                'kind'  => self::KIND_PAGE,
                'icon'  => 'fa-rectangle-list',
                'route' => 'user.forms.create',
            ],
            'subscribers' => [
                'label' => 'Grow a subscriber list',
                'blurb' => 'Turn one-off visitors into people you can reach again.',
                'kind'  => self::KIND_PAGE,
                'icon'  => 'fa-envelope-open-text',
                'route' => 'user.monetization.subscribers',
            ],
            'file_lead_magnet' => [
                'label' => 'Share a file link',
                'blurb' => 'Give something worth having away, and get a contact for it.',
                'kind'  => self::KIND_PAGE,
                'icon'  => 'fa-file-arrow-down',
                'route' => 'user.links.file.create',
            ],
            'qr_code' => [
                'label' => 'Make a QR code',
                'blurb' => 'Carry the page onto packaging, print, a table card or a stand.',
                'kind'  => self::KIND_PAGE,
                'icon'  => 'fa-qrcode',
                'route' => 'user.qr-codes.create',
            ],
            'booking' => [
                'label' => 'Open a booking calendar',
                'blurb' => 'Let people book a time rather than read and leave.',
                'kind'  => self::KIND_PAGE,
                'icon'  => 'fa-calendar-check',
                'route' => 'user.calendars.create',
            ],
            'promos' => [
                'label'       => 'Issue a promo code',
                'blurb'       => 'A code with an expiry gives a reason to act now rather than later.',
                'kind'        => self::KIND_PAGE,
                'icon'        => 'fa-tag',
                'route'       => 'user.monetization.promos',
                'needs_tiers' => true,
            ],

            // ---- Blocks, each a real type in BlockTypeRegistry ----
            'testimonial_block' => [
                'label' => 'Testimonial carousel',
                'blurb' => 'Put what customers said on the page itself.',
                'kind'  => self::KIND_BLOCK,
                'icon'  => 'fa-quote-left',
                'block' => 'testimonial_carousel',
            ],
            'primary_cta_block' => [
                'label' => 'One primary button',
                'blurb' => 'A single call-to-action that says what happens next.',
                'kind'  => self::KIND_BLOCK,
                'icon'  => 'fa-bullseye',
                'block' => 'cta_button',
            ],
            'featured_block' => [
                'label' => 'Featured pin',
                'blurb' => 'Lift the one thing that matters above everything else.',
                'kind'  => self::KIND_BLOCK,
                'icon'  => 'fa-thumbtack',
                'block' => 'featured_pin',
            ],
            'faq_block' => [
                'label' => 'FAQ',
                'blurb' => 'Answer the objection before it costs you the click.',
                'kind'  => self::KIND_BLOCK,
                'icon'  => 'fa-circle-question',
                'block' => 'faq',
            ],
            'stats_block' => [
                'label' => 'Stats',
                'blurb' => 'Numbers that make the claim credible at a glance.',
                'kind'  => self::KIND_BLOCK,
                'icon'  => 'fa-chart-simple',
                'block' => 'stats',
            ],
            'pricing_block' => [
                'label' => 'Pricing list',
                'blurb' => 'Say what each thing costs so nobody has to guess.',
                'kind'  => self::KIND_BLOCK,
                'icon'  => 'fa-list-ol',
                'block' => 'list_pricing',
            ],
            'visuals_block' => [
                'label' => 'Image slider',
                'blurb' => 'Give the page something to look at, not just links to read.',
                'kind'  => self::KIND_BLOCK,
                'icon'  => 'fa-images',
                'block' => 'image_slider',
            ],
        ];
    }

    /** @return list<string> The only keys the model is allowed to return. */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * The ALLOWED FIXES block for the teardown's system prompt.
     *
     * Built from the same array the renderer reads, so a key can never be
     * offered to the model without a destination waiting for it.
     */
    public static function promptCatalogue(): string
    {
        $lines = [];
        foreach (self::all() as $key => $fix) {
            $lines[] = '- ' . $key . ': ' . $fix['blurb'];
        }

        return implode("\n", $lines);
    }

    /**
     * Keep only keys this list actually defines.
     *
     * This is the guard. Whatever the model returns -- a misspelling, a
     * feature from another product, something it invented wholesale -- is
     * discarded here, before `analysis` is written.
     *
     * @param  mixed $raw
     * @return list<string>
     */
    public static function sanitize($raw, int $max = 6): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $allowed = self::all();
        $out     = [];

        foreach ($raw as $key) {
            if (!is_string($key)) {
                continue;
            }
            $key = trim($key);
            if (!isset($allowed[$key]) || in_array($key, $out, true)) {
                continue;
            }
            $out[] = $key;
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * Turn stored keys into things a creator can actually click.
     *
     * Conditions are applied here rather than at save time on purpose: a
     * creator who adds a tier next week should start seeing the promo
     * suggestion on a teardown they ran today.
     *
     * @param  mixed $keys
     * @return list<array{key:string, label:string, blurb:string, kind:string, icon:string, url:?string, pending:?string}>
     */
    public static function resolve($keys, ?User $owner = null, ?CompetitorTeardown $teardown = null): array
    {
        $all      = self::all();
        $builtId  = $teardown?->built_link_id;
        $hasTiers = null;

        $out = [];
        foreach (self::sanitize($keys) as $key) {
            $fix = $all[$key];

            if (!empty($fix['needs_tiers'])) {
                // Resolved once, and only if a promo key actually turned up.
                $hasTiers ??= $owner
                    && SubscriptionTier::where('user_id', $owner->id)->where('is_active', true)->exists();

                if (!$hasTiers) {
                    continue;
                }
            }

            $url     = null;
            $pending = null;

            if ($fix['kind'] === self::KIND_PAGE) {
                $url = self::safeRoute($fix['route'] ?? null);
                if ($url === null) {
                    continue;
                }
            } else {
                if ($builtId) {
                    $url = self::safeRoute('user.links.blocks.editor', [$builtId]);
                }
                if ($url === null) {
                    $pending = 'Build a better version first, then add it there.';
                }
            }

            $out[] = [
                'key'     => $key,
                'label'   => $fix['label'],
                'blurb'   => $fix['blurb'],
                'kind'    => $fix['kind'],
                'icon'    => $fix['icon'],
                'url'     => $url,
                'pending' => $pending,
            ];
        }

        return $out;
    }

    /**
     * A route URL, or null if that route is not registered.
     *
     * Routes move. A missing one should drop the suggestion quietly, not
     * throw a RouteNotFoundException in the middle of a results page.
     */
    private static function safeRoute(?string $name, array $params = []): ?string
    {
        if (!$name) {
            return null;
        }

        try {
            return route($name, $params);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
