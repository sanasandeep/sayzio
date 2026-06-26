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
                ],
            ],
            [
                'label' => 'Business & monetization',
                'desc'  => 'Grow your reputation and earn from your audience.',
                'types' => [
                    ['value' => 'paid_page', 'icon' => 'fa-crown', 'badge' => 'bg-rose-500/15 text-rose-300',     'label' => 'Bizs Profile',  'desc' => 'A themeable home that automatically shows all your posts, tiers & tips — no linking needed.'],
                    ['value' => 'reviews',   'icon' => 'fa-star',  'badge' => 'bg-yellow-500/15 text-yellow-300', 'label' => 'Reviews Page',  'desc' => 'Collect and showcase reviews from your audience.'],
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
     * Quick "What are you trying to do?" goal prompts shown above the manual
     * picker. Each maps a plain-language goal to the link type that best fits,
     * letting people who don't know the type names jump straight to the right
     * card. Every `type` here must exist in categories().
     *
     * @return array<int, array{type:string, icon:string, label:string}>
     */
    public static function intents(): array
    {
        return [
            ['type' => 'url',             'icon' => 'fa-link',         'label' => 'Shorten a link'],
            ['type' => 'file',            'icon' => 'fa-file',         'label' => 'Share a file'],
            ['type' => 'paid_page',       'icon' => 'fa-crown',        'label' => 'Take payments'],
            ['type' => 'reviews',         'icon' => 'fa-star',         'label' => 'Collect reviews'],
            ['type' => 'restaurant_menu', 'icon' => 'fa-utensils',     'label' => 'Show a menu'],
            ['type' => 'vcf',             'icon' => 'fa-address-card', 'label' => 'Share my contact'],
            ['type' => 'biolink',         'icon' => 'fa-id-card',      'label' => 'Build a profile page'],
            ['type' => 'resume',          'icon' => 'fa-file-lines',   'label' => 'Share my resume'],
            ['type' => 'ics',             'icon' => 'fa-calendar',     'label' => 'Invite to an event'],
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
