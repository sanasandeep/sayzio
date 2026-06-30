<?php

namespace App\Modules\User\Support;

/**
 * Single source of truth for the grouped link-type catalog shown on the
 * "Create Link" type picker.
 *
 * Each category: ['label' => string, 'desc' => string, 'types' => [...]].
 * Each type: [
 *   'value' => string,  // links.type value submitted by the form
 *   'icon'  => string,  // Font Awesome icon class (e.g. 'fa-link')
 *   'badge' => string,  // Tailwind badge classes (bg + text colour)
 *   'label' => string,  // human-readable name
 *   'desc'  => string,  // one-line description
 * ].
 *
 * Adding or renaming a type/category is a one-line data change here; every
 * surface that lists link-type groups should consume `categories()` rather
 * than redefining the structure inline.
 */
class LinkTypeCategories
{
    /**
     * @return array<int, array{label:string, desc:string, types:array<int, array{value:string, icon:string, badge:string, label:string, desc:string}>}>
     */
    public static function categories(): array
    {
        return [
            [
                'label' => 'Everyday links',
                'desc'  => 'Quick, single-purpose links you can share anywhere in seconds.',
                'types' => [
                    ['value' => 'url',  'icon' => 'fa-link',         'badge' => 'bg-violet-500/15 text-violet-300',  'label' => 'Short Link',   'desc' => 'Shorten any URL with a custom alias and click tracking.'],
                    ['value' => 'file', 'icon' => 'fa-file',         'badge' => 'bg-emerald-500/15 text-emerald-300','label' => 'File Share',   'desc' => 'Share a downloadable file behind a short link.'],
                    ['value' => 'ics',  'icon' => 'fa-calendar',     'badge' => 'bg-amber-500/15 text-amber-300',    'label' => 'Event',        'desc' => 'A calendar event visitors can add in a single tap.'],
                    ['value' => 'vcf',  'icon' => 'fa-address-card', 'badge' => 'bg-cyan-500/15 text-cyan-300',      'label' => 'Contact Card', 'desc' => 'A digital business card visitors can save instantly.'],
                ],
            ],
            [
                'label' => 'Pages & mini-sites',
                'desc'  => 'Full, customizable pages that live at a single link — no website needed.',
                'types' => [
                    ['value' => 'biolink',         'icon' => 'fa-id-card',    'badge' => 'bg-pink-500/15 text-pink-300',       'label' => 'Link in Bio',        'desc' => 'A mini-site of your links, blocks and media on one page.'],
                    ['value' => 'slides',          'icon' => 'fa-clone',      'badge' => 'bg-fuchsia-500/15 text-fuchsia-300', 'label' => 'Slides',             'desc' => 'Present a swipeable deck of slides from a single link.'],
                    ['value' => 'restaurant_menu', 'icon' => 'fa-utensils',   'badge' => 'bg-orange-500/15 text-orange-300',   'label' => 'Restaurant Menu',    'desc' => 'A digital menu with sections, items and prices.'],
                    ['value' => 'resume',          'icon' => 'fa-file-lines', 'badge' => 'bg-indigo-500/15 text-indigo-300',   'label' => 'Resume / Portfolio', 'desc' => 'A shareable resume / portfolio page with PDF download.'],
                    ['value' => 'calendar',        'icon' => 'fa-calendar-days', 'badge' => 'bg-lime-500/15 text-lime-300',    'label' => 'Calendar',           'desc' => 'A followable calendar of events visitors can subscribe to.'],
                ],
            ],
            [
                'label' => 'Business & monetization',
                'desc'  => 'Grow your reputation and earn from your audience.',
                'types' => [
                    ['value' => 'paid_page', 'icon' => 'fa-crown', 'badge' => 'bg-rose-500/15 text-rose-300',     'label' => 'Bizs Profile',  'desc' => 'A themeable home that automatically shows all your posts, tiers & tips — no linking needed.'],
                    ['value' => 'reviews',   'icon' => 'fa-star',  'badge' => 'bg-yellow-500/15 text-yellow-300', 'label' => 'Reviews Page',  'desc' => 'Collect and showcase reviews from your audience.'],
                    ['value' => 'brand_kit', 'icon' => 'fa-palette', 'badge' => 'bg-purple-500/15 text-purple-300', 'label' => 'Brand / Press Kit', 'desc' => 'A shareable press kit with logo downloads, colours, fonts and brand voice.'],
                ],
            ],
            [
                'label' => 'AI-powered',
                'desc'  => 'Let AI answer and guide your visitors for you.',
                'types' => [
                    ['value' => 'ai_chat',        'icon' => 'fa-robot',    'badge' => 'bg-teal-500/15 text-teal-300', 'label' => 'AI Chatbot',     'desc' => 'An AI assistant that answers your visitors for you.'],
                    ['value' => 'conversational', 'icon' => 'fa-comments', 'badge' => 'bg-sky-500/15 text-sky-300',   'label' => 'Conversational', 'desc' => 'A guided, chat-style page that responds as visitors tap.'],
                ],
            ],
        ];
    }

    /**
     * Goals that the guided biolink wizard can build, mapped to
     * the persona step(s) the wizard should pre-seed. Each entry is a
     * `['group' => ?string, 'persona' => ?string]` pair:
     *   - `group`   — the persona group to seed for the wizard's first
     *                 ("category") step. null means "no specific group" — drop
     *                 the user into the generic wizard at step 0.
     *   - `persona` — an OPTIONAL persona slug to *also* seed, used only when a
     *                 goal maps unambiguously to a single persona. When present
     *                 (and it belongs to `group`) the wizard skips its persona
     *                 question too and lands on the starting-design step. null
     *                 leaves the persona as the user's choice (ambiguous goals).
     * Goals absent from this map have no wizard path and keep the manual
     * link-type selection flow.
     *
     * The wizard always produces a biolink-family page, so only goals whose
     * persona-themed page genuinely serves the goal belong here. Pure utility
     * links the wizard can't build (short link, file share, event/.ics,
     * contact card) stay on the manual flow.
     *
     *   - biolink         → null group   generic Link in Bio (no pre-seed)
     *   - restaurant_menu → Food / chef  the Food group's single chef persona
     *                                    builds menus, so it lands on chef
     *   - paid_page       → Creators / creator  fan-monetization (posts/tiers/
     *                                    tips) is the archetypal "creator" page
     *                                    ("content, fans, all your links"); the
     *                                    other Creators personas monetize too,
     *                                    but `creator` is the clear best fit and
     *                                    the group default, so it lands on it
     *   - reviews         → Business / business  the Business group is just
     *                                    business + agency, and collecting /
     *                                    showcasing customer reviews is the
     *                                    textbook Business/Brand use case (and
     *                                    the group default) far more than an
     *                                    agency, so it lands on business
     *   - resume          → Services / freelancer  a resume/portfolio is the
     *                                    freelancer's "portfolio + hire-me" page,
     *                                    so it lands straight on freelancer
     *
     * Every `group` here must be a valid PersonaCatalog group, and every
     * `persona` must belong to its `group`.
     *
     * @return array<string, array{group:string|null, persona:string|null}>
     */
    public static function wizardGroups(): array
    {
        return [
            'biolink'         => ['group' => null,       'persona' => null],
            'restaurant_menu' => ['group' => 'Food',     'persona' => 'chef'],
            'paid_page'       => ['group' => 'Creators', 'persona' => 'creator'],
            'reviews'         => ['group' => 'Business', 'persona' => 'business'],
            'resume'          => ['group' => 'Services', 'persona' => 'freelancer'],
            'brand_kit'       => ['group' => 'Creators', 'persona' => 'creator'],
        ];
    }

    /**
     * Flat, value-keyed view of every link type in the catalog. Lets surfaces
     * that work one type at a time (e.g. a per-link type badge) look up a
     * type's label/icon/badge/desc without re-flattening the groups.
     *
     * @return array<string, array{value:string, icon:string, badge:string, label:string, desc:string}>
     */
    public static function types(): array
    {
        $out = [];
        foreach (self::categories() as $cat) {
            foreach ($cat['types'] as $type) {
                $out[$type['value']] = $type;
            }
        }

        return $out;
    }

    /**
     * Value => human-readable label map derived from the catalog. This is the
     * single source the rest of the app should use for friendly type names.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        static $labels = null;

        return $labels ??= array_map(static fn (array $type): string => $type['label'], self::types());
    }
}
