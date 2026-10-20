<?php

namespace App\Modules\User\Support;

/**
 * One plain sentence per block type, saying what the block is FOR.
 *
 * The palette tile stays a label and an icon -- these never render on the
 * tile itself. They feed two things:
 *
 *  1. the preview flyout, where a picture alone cannot help. Text,
 *     Formatted text and Markdown all render as a paragraph; Link Button
 *     and Call-to-Action both render as a button. What separates them is
 *     what they are for and how you type into them, which is words, not
 *     pixels.
 *  2. the palette search, which until now matched the label only -- so
 *     searching "bold" or "money" or "booking" found nothing, because
 *     there was no prose anywhere to match.
 *
 * House style for a blurb: one sentence, under 90 characters, addressed to
 * a creator rather than a developer, naming the thing it produces rather
 * than the feature that produces it. No jargon, no block names inside the
 * blurb, no trailing "block".
 */
final class BlockTypeBlurbs
{
    /** Maximum blurb length. Enforced by BlockTypeBlurbsTest. */
    public const MAX_LENGTH = 90;

    private const BLURBS = [
        'link'                       => 'A tappable button pointing anywhere you choose.',
        'link_big'                   => 'A bigger button with its own image, for the one link you most want clicked.',
        'featured_pin'               => 'A link pinned to the top of your page so it never scrolls away.',
        'external_item'              => 'A link that shows the other page title and picture, like a shared post.',
        'cta_button'                 => 'A single bold button for the one action you want people to take.',
        'heading'                    => 'A line of larger text that starts a new part of your page.',
        'heading_logo'               => 'A heading with your logo beside it.',
        'verified_heading'           => 'A heading with a verified tick beside it.',
        'paragraph'                  => 'Plain words, no formatting controls.',
        'paragraph_rich'             => 'Words you style as you type, like in a document.',
        'markdown'                   => 'Words you write with markdown shortcuts, if you already write that way.',
        'list'                       => 'Points down the page, each with a dot.',
        'list_numbered'              => 'Points down the page, numbered in order.',
        'list_pricing'               => 'A list where each line carries a price.',
        'alert'                      => 'A coloured strip for something people need to notice.',
        'badge'                      => 'A tiny label, like New or Sold out.',
        'divider'                    => 'A line that separates what is above from what is below.',
        'spacer'                     => 'Empty room between two things.',
        'link_tree_group'            => 'Several links kept together under one title.',
        'card'                       => 'A box that holds other sections together as one unit.',
        'grid'                       => 'Sections side by side in a set number of columns.',
        'grid_auto'                  => 'Sections side by side, fitting as many per row as will fit.',
        'card_slider'                => 'Cards people swipe through one at a time.',
        'scroll_cards'               => 'Cards in a row that scroll sideways.',
        'tabs'                       => 'Several panels behind tabs, one shown at a time.',
        'profile_card_v1'            => 'Your picture, name and bio in the classic layout.',
        'profile_card_v2'            => 'Your picture and bio over a wide cover photo.',
        'profile_card_v3'            => 'Your picture and bio with follower numbers beside them.',
        'profile_card_v4'            => 'Your picture and bio with badges underneath.',
        'image'                      => 'One picture.',
        'image_grid'                 => 'Several pictures laid out in a grid, all visible at once.',
        'image_slider'               => 'Several pictures, one at a time, with arrows.',
        'image_slider_v2'            => 'Several pictures people swipe through, like a phone gallery.',
        'header_video'               => 'A video across the top of your page, like a cover photo that moves.',
        'video'                      => 'A video from a link you paste.',
        'audio'                      => 'A single track people can play without leaving the page.',
        'audio_list'                 => 'Several tracks in a row, played one after another.',
        'pdf_document'               => 'A PDF people can read on the page or download.',
        'powerpoint'                 => 'A slide deck people can flip through on the page.',
        'excel'                      => 'A spreadsheet people can look through on the page.',
        'file'                       => 'A file people can download, with its size shown.',
        'file_list'                  => 'Several files to download, listed together.',
        'faq'                        => 'Questions with their answers already showing.',
        'faq_v2'                     => 'Questions people tap to open the answer.',
        'accordion'                  => 'Sections people tap to open, one topic at a time.',
        'poll'                       => 'A question with options, and the running results.',
        'quiz'                       => 'Questions with right answers and a score at the end.',
        'testimonials'               => 'Quotes from real customers, shown together.',
        'testimonial_carousel'       => 'Quotes from real customers, one at a time.',
        'review'                     => 'Star ratings and written reviews.',
        'reviews_wall'               => 'Lots of reviews tiled across the page.',
        'timeline'                   => 'Events down the page in the order they happened.',
        'timeline_staged'            => 'A journey down the page with each step marked done or still to come.',
        'social_proof'               => 'Small pop-ups showing what other people just did.',
        'ai_companion'               => 'A chat box that answers visitor questions for you.',
        'insider'                    => 'Posts for people who subscribed, hidden from everyone else.',
        'fan_leaderboard'            => 'Your most active supporters, ranked.',
        'roadmap'                    => 'What you are building, and what is coming next.',
        'product'                    => 'One thing for sale, with its price and a buy button.',
        'service'                    => 'One service you offer, with what it costs.',
        'catalog'                    => 'Several products together, each with its own price.',
        'market'                     => 'A full shop with a basket and checkout.',
        'price'                      => 'A price on its own, with no buy button.',
        'donation'                   => 'A way for people to give you money, amount up to them.',
        'coupon'                     => 'A discount code people can copy.',
        'one_time_offer'             => 'A deal that stops being available when the clock runs out.',
        'paypal'                     => 'A PayPal button.',
        'buy_me_coffee'              => 'Your Buy Me a Coffee page, on your page.',
        'patreon'                    => 'Your Patreon tiers, on your page.',
        'ko_fi'                      => 'Your Ko-fi page, on your page.',
        'tip_jar'                    => 'Small tips, with a few suggested amounts.',
        'menu'                       => 'A food or drinks menu with prices.',
        'menu_section'               => 'One part of a menu, like Starters or Drinks.',
        'affiliate_links'            => 'Products you earn a cut on, each with its tracking link.',
        'email_collector'            => 'An email box that just collects addresses for you.',
        'email_subscribe'            => 'An email box that signs people up to your newsletter.',
        'phone_collector'            => 'A phone number box, with the country code handled.',
        'contact_form'               => 'A form with the fields you choose, sent to your inbox.',
        'direct_message'             => 'A private message box only you can read.',
        'whatsapp_widget'            => 'A floating WhatsApp bubble that follows the page.',
        'whatsapp_item'              => 'A button that opens a WhatsApp chat with you.',
        'whatsapp_channel_subscribe' => 'A button to join your WhatsApp channel.',
        'whatsapp_number_subscribe'  => 'A box where people leave their number for your WhatsApp list.',
        'socials'                    => 'Round icons linking to your accounts.',
        'socials_multi'              => 'Your accounts in named groups, when you have a lot of them.',
        'socials_custom'             => 'Icons you upload yourself, for anywhere not on the list.',
        'instagram'                  => 'A link to your Instagram.',
        'instagram_media'            => 'One Instagram post you choose, shown in full.',
        'latest_instagram'           => 'Whatever you posted to Instagram most recently, kept current.',
        'tiktok_video'               => 'One TikTok, playable here.',
        'tiktok_profile'             => 'Your TikTok profile with your latest videos.',
        'twitter_profile'            => 'Your X profile with your latest posts.',
        'twitter_tweet'              => 'One post from X you choose.',
        'twitter_video'              => 'A video from X, playable here.',
        'pinterest_profile'          => 'Your Pinterest boards.',
        'snapchat'                   => 'Your Snapchat, with the scannable code.',
        'rss_feed'                   => 'Posts pulled in from any blog or feed, kept current.',
        'spotify'                    => 'A track, album or playlist people can play here.',
        'apple_music'                => 'Apple Music people can play here.',
        'soundcloud'                 => 'SoundCloud people can play here.',
        'tidal'                      => 'Tidal people can play here.',
        'mixcloud'                   => 'Mixcloud people can play here.',
        'anchor_fm'                  => 'Your podcast, playable here.',
        'youtube'                    => 'One YouTube video, playable here.',
        'youtube_feed'               => 'Several of your YouTube videos in a row.',
        'latest_youtube'             => 'Whatever you uploaded to YouTube most recently, kept current.',
        'vimeo'                      => 'A Vimeo video, playable here.',
        'twitch'                     => 'Your Twitch stream, live on your page when you are.',
        'kick'                       => 'Your Kick stream, live on your page when you are.',
        'rumble_video'               => 'A Rumble video, playable here.',
        'vk_video'                   => 'A VK video, playable here.',
        'countdown'                  => 'A clock counting down to a date you set.',
        'progress'                   => 'A bar showing how far along something is.',
        'chart_pie'                  => 'Numbers shown as slices of a circle.',
        'stats'                      => 'A few numbers worth showing off, side by side.',
        'qr_code'                    => 'A code people can scan with a phone camera.',
        'share'                      => 'Buttons for sharing your page onward.',
        'notification'               => 'A strip across the top for an announcement.',
        'nav_menu'                   => 'Links across the top for jumping around your page.',
        'ticker'                     => 'A line of text sliding sideways.',
        'event_list'                 => 'Your upcoming dates, with where and when.',
        'map'                        => 'A Google map centred where you say.',
        'yandex_maps'                => 'A Yandex map centred where you say.',
        'map_location'               => 'A map with your address and a button for directions.',
        'form'                       => 'A form you built in Sayzio, with its answers in your inbox.',
        'typeform'                   => 'A Typeform, filled in without leaving your page.',
        'calendly'                   => 'A button that opens your Calendly.',
        'calendly_embed'             => 'Your Calendly calendar on the page, bookable here.',
        'booking_slots'              => 'Times you are free, bookable without any outside tool.',
        'discord_server'             => 'Your Discord, with how many people are online.',
        'facebook_post'              => 'One Facebook post you choose.',
        'reddit_post'                => 'One Reddit post you choose.',
        'telegram_post'              => 'One Telegram post you choose.',
        'iframe_embed'               => 'Any other site inside a window on your page.',
        'custom_html'                => 'Your own code, for anything not covered above.',
        'vcard'                      => 'A button that saves your details straight to a phone contacts list.',
        'avatar'                     => 'Just your picture, on its own.',
        'verified_avatar'            => 'Your picture with a verified tick.',
        'resume'                     => 'Your work history laid out as a CV.',
    ];

    /**
     * The blurb for a palette type, or null when none is written yet.
     *
     * Keyed on the type as the palette shows it, not the canonical type:
     * `link_big` and `cta_button` both canonicalise to `link`, and the
     * whole point here is telling those three apart. Falls back to the
     * canonical type so a new alias inherits something sensible rather
     * than showing nothing.
     */
    public static function for(string $type): ?string
    {
        if (isset(self::BLURBS[$type])) {
            return self::BLURBS[$type];
        }

        $canonical = BlockTypeRegistry::canonical($type);

        return self::BLURBS[$canonical] ?? null;
    }

    /** Every blurb, keyed by palette type. */
    public static function all(): array
    {
        return self::BLURBS;
    }

    /**
     * Lowercased text the palette search matches against: the label plus
     * the blurb. Returned as one string because the Alpine filter does a
     * plain `includes()` on it.
     */
    public static function haystack(string $type, string $label): string
    {
        return trim(mb_strtolower($label . ' ' . (self::for($type) ?? '')));
    }
}
