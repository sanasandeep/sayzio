<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\BiolinkBlock;

/**
 * Per-type "first-paint" defaults: structural style tokens, placeholder
 * media URLs, and a friendly text/seed payload for new blocks.
 *
 * Style overrides intentionally only set structural tokens (radius,
 * padding, shadow_preset, display_mode, glass_preset, font sizing,
 * border style/width). Colour fields (bg_color, border_color,
 * text_color) are left to {@see BiolinkBlock::STYLE_DEFAULTS} so the
 * active biolink theme resolves them at render time.
 */
class BlockDefaults
{
    /**
     * Per-block-type structural style overrides layered on top of
     * {@see BiolinkBlock::STYLE_DEFAULTS}. Variant catalog payloads
     * still fully replace `_style` at apply-variant time.
     *
     * @return array<string,mixed>
     */
    public static function styleForType(string $type): array
    {
        $canonical = BlockTypeRegistry::canonical($type);

        return match ($canonical) {
            'heading', 'heading_logo' => [
                'font_size' => '28', 'font_weight' => '700',
                'display_mode' => 'content', 'padding' => '8',
            ],
            'paragraph', 'paragraph_rich', 'markdown' => [
                'font_size' => '16', 'display_mode' => 'content', 'padding' => '6',
            ],

            'link', 'link_big', 'cta_button', 'featured_pin' => [
                'border_radius' => '14', 'padding' => '14',
                'shadow_preset' => 'soft', 'display_mode' => 'card',
                'border_style' => 'solid', 'border_width' => '1',
            ],

            'divider', 'spacer' => [
                'display_mode' => 'content', 'padding' => '0',
            ],

            'card', 'card_slider', 'scroll_cards' => [
                'border_radius' => '20', 'padding' => '20',
                'glass_preset' => 'light', 'shadow_preset' => 'soft',
            ],

            // Plain grid containers carry no chrome of their own — the public
            // render reads only the columns/gap/padding settings, so the
            // per-block _style wrapper stays minimal (no glass / shadow).
            'grid', 'grid_auto' => [
                'display_mode' => 'content', 'padding' => '0',
                'border_radius' => '0',
            ],

            'profile_card_v1', 'profile_card_v2', 'profile_card_v3', 'profile_card_v4' => [
                // Padding is 0 because the profile-card renderer owns all
                // internal spacing per layout (Task #1740). The card chrome
                // (radius / shadow / border) still applies to the surface.
                'border_radius' => '20', 'padding' => '0',
                'shadow_preset' => 'soft',
                'border_style' => 'solid', 'border_width' => '1',
            ],

            'image', 'image_grid', 'image_slider', 'image_slider_v2',
            'header_video', 'video', 'audio', 'pdf_document', 'powerpoint',
            'excel', 'file' => [
                'border_radius' => '16', 'shadow_preset' => 'soft',
                'display_mode' => 'card', 'padding' => '8',
            ],

            'faq', 'faq_v2', 'poll', 'quiz', 'testimonials', 'review', 'reviews_wall',
            'timeline', 'timeline_staged', 'social_proof', 'ai_companion',
            'insider', 'fan_leaderboard', 'roadmap', 'product', 'service',
            'catalog', 'market', 'price', 'donation', 'coupon',
            'one_time_offer', 'paypal', 'buy_me_coffee', 'patreon', 'ko_fi',
            'email_collector', 'email_subscribe', 'phone_collector',
            'contact_form', 'whatsapp_widget', 'whatsapp_item',
            'whatsapp_channel_subscribe', 'whatsapp_number_subscribe',
            'list_pricing', 'menu', 'menu_section', 'event_list',
            'testimonial_carousel', 'stats', 'affiliate_links',
            'booking_slots', 'file_list', 'audio_list', 'link_tree_group',
            'tabs', 'accordion' => [
                'border_radius' => '16', 'padding' => '16',
                'shadow_preset' => 'soft', 'display_mode' => 'card',
                'border_style' => 'solid', 'border_width' => '1',
            ],

            'alert', 'badge', 'notification' => [
                'border_radius' => '999', 'padding' => '10',
                'display_mode' => 'card', 'shadow_preset' => 'soft',
            ],

            default => [
                'border_radius' => '14', 'padding' => '12',
                'display_mode' => 'card', 'shadow_preset' => 'soft',
            ],
        };
    }

    /**
     * Absolute URL to a placeholder image asset. Resolved through
     * Laravel's asset() helper so APP_URL is applied (the URL
     * sanitizer only accepts http(s) absolutes).
     */
    public static function placeholderUrl(string $kind): string
    {
        $map = [
            'image'        => 'image.svg',
            'image_square' => 'image-square.svg',
            'cover'        => 'cover.svg',
            'avatar'       => 'avatar.svg',
            'logo'         => 'logo.svg',
            'document'     => 'document.svg',
        ];
        $file = $map[$kind] ?? 'image.svg';
        return asset('block-placeholders/' . $file);
    }

    /**
     * Project-hosted sample media URLs for non-image media blocks.
     * Files live under public/block-placeholders/.
     */
    public static function sampleMediaUrl(string $kind): string
    {
        $file = match ($kind) {
            'video' => 'sample.mp4',
            'audio' => 'sample.mp3',
            'pdf'   => 'sample.pdf',
            'pptx'  => 'sample.pptx',
            'xlsx'  => 'sample.xlsx',
            default => '',
        };
        return $file === '' ? '' : asset('block-placeholders/' . $file);
    }

    /**
     * First-paint *content* defaults for a freshly-added block. Used by
     * BiolinkBlockController::store() and the block picker preview tile
     * (Task #1202) to render a true-to-life thumbnail. Blocks returned
     * with `_placeholder => true` show a "replace this" banner in the
     * editor; the flag is cleared by update() once the creator edits a
     * seeded field. Style overrides live in {@see styleForType()} and
     * are layered on top of {@see BiolinkBlock::STYLE_DEFAULTS} by the
     * caller; this method only seeds the per-type *content* keys.
     *
     * @return array<string,mixed>
     */
    public static function contentForType(string $type): array
    {
        $imgUrl       = self::placeholderUrl('image');
        $imgSquareUrl = self::placeholderUrl('image_square');
        $coverUrl     = self::placeholderUrl('cover');
        $avatarUrl    = self::placeholderUrl('avatar');
        // First-paint socials for the profile-card identity designs (#1740).
        $profileSocials = [
            ['name' => 'linkedin',  'url' => 'https://linkedin.com/in/yourhandle'],
            ['name' => 'twitter',   'url' => 'https://twitter.com/yourhandle'],
            ['name' => 'instagram', 'url' => 'https://instagram.com/yourhandle'],
            ['name' => 'github',    'url' => 'https://github.com/yourhandle'],
        ];
        $logoUrl      = self::placeholderUrl('logo');
        $docUrl       = self::placeholderUrl('document');
        $videoUrl     = self::sampleMediaUrl('video');
        $audioUrl     = self::sampleMediaUrl('audio');
        $pdfUrl       = self::sampleMediaUrl('pdf');
        $pptUrl       = self::sampleMediaUrl('pptx');
        $xlsxUrl      = self::sampleMediaUrl('xlsx');

        return match ($type) {
            'link' => ['url' => 'https://example.com', 'text' => 'My Link', 'icon' => '', 'thumbnail' => '', '_placeholder' => true],
            'link_big' => ['url' => 'https://example.com', 'text' => 'My Featured Link', 'description' => 'A short blurb about where this goes.', 'icon' => '', 'thumbnail' => $imgSquareUrl, 'bg_color' => '#7c3aed', '_placeholder' => true],
            'heading' => ['text' => 'Hello, I\'m new here', 'size' => 'h2', 'align' => 'center', 'style' => 'plain', '_placeholder' => true],
            'heading_logo' => ['text' => 'Your Brand', 'logo_url' => $logoUrl, 'size' => 'h2', 'align' => 'center', '_placeholder' => true],
            'paragraph' => ['text' => 'Tell visitors a little about yourself or what this block is for.', 'align' => 'center', '_placeholder' => true],
            'paragraph_rich' => ['html' => '<p>Replace this with your own rich text. <strong>Bold</strong>, <em>italic</em>, and links all work.</p>', '_placeholder' => true],
            'divider' => ['style' => 'solid', 'color' => 'rgba(255,255,255,0.1)'],
            'list' => ['style' => 'clean', 'icon' => 'fa-check', 'items' => [
                ['text' => 'First item — replace with your own', 'icon' => ''],
                ['text' => 'Second item — drag to reorder', 'icon' => ''],
                ['text' => 'Third item — add as many as you need', 'icon' => ''],
            ], '_placeholder' => true],
            'list_numbered' => ['style' => 'clean', 'items' => [
                ['text' => 'First step — replace with your own'],
                ['text' => 'Second step — keep going'],
                ['text' => 'Third step — finish strong'],
            ], '_placeholder' => true],
            'list_pricing' => ['style' => 'classic', 'items' => [
                ['name' => 'Starter',   'description' => 'Perfect for trying things out', 'price' => '$9',  'period' => '/mo', 'included' => true,  'featured' => false],
                ['name' => 'Pro',       'description' => 'Everything you need to grow',   'price' => '$29', 'period' => '/mo', 'included' => true,  'featured' => true],
                ['name' => 'Enterprise','description' => 'Custom limits + priority support','price' => '$99','period' => '/mo', 'included' => false, 'featured' => false],
            ], '_placeholder' => true],
            'alert' => ['text' => 'Heads up! Replace this with your own announcement.', 'type' => 'info', 'icon' => 'fa-info-circle', '_placeholder' => true],
            'badge' => ['text' => 'New', 'color' => '#7c3aed', 'text_color' => '#ffffff', '_placeholder' => true],

            'image' => ['url' => $imgUrl, 'alt' => 'Placeholder image', 'link' => '', '_placeholder' => true],
            'image_grid' => ['images' => [
                ['url' => $imgUrl, 'alt' => 'Placeholder 1'],
                ['url' => $imgSquareUrl, 'alt' => 'Placeholder 2'],
                ['url' => $imgUrl, 'alt' => 'Placeholder 3'],
            ], 'columns' => 3, 'gap' => 4, '_placeholder' => true],
            'image_slider' => ['images' => [
                ['url' => $imgUrl, 'alt' => 'Placeholder 1'],
                ['url' => $imgUrl, 'alt' => 'Placeholder 2'],
            ], 'autoplay' => true, 'interval' => 3000, '_placeholder' => true],
            'image_slider_v2' => ['images' => [
                ['url' => $imgUrl, 'alt' => 'Placeholder 1'],
                ['url' => $imgUrl, 'alt' => 'Placeholder 2'],
            ], 'autoplay' => true, 'effect' => 'fade', '_placeholder' => true],
            'header_video' => ['url' => $videoUrl, 'autoplay' => true, 'muted' => true, 'loop' => true, '_placeholder' => true],
            'video' => ['url' => $videoUrl, 'autoplay' => false, '_placeholder' => true],
            'audio' => ['url' => $audioUrl, 'title' => 'Placeholder audio track', '_placeholder' => true],
            'pdf_document' => ['url' => $pdfUrl, 'title' => 'Placeholder document', '_placeholder' => true],
            'powerpoint' => ['url' => $pptUrl, 'title' => 'Placeholder presentation', '_placeholder' => true],
            'excel' => ['url' => $xlsxUrl, 'title' => 'Placeholder spreadsheet', '_placeholder' => true],

            'socials' => ['platforms' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/yourhandle'],
                ['platform' => 'tiktok', 'url' => 'https://tiktok.com/@yourhandle'],
                ['platform' => 'youtube', 'url' => 'https://youtube.com/@yourhandle'],
            ], '_placeholder' => true],
            'socials_multi' => ['groups' => [['label' => 'Personal', 'platforms' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/yourhandle'],
                ['platform' => 'twitter', 'url' => 'https://twitter.com/yourhandle'],
            ]]], '_placeholder' => true],
            'socials_custom' => ['platforms' => [
                ['icon' => 'fa-brands fa-instagram', 'url' => 'https://instagram.com/yourhandle', 'label' => 'Instagram'],
                ['icon' => 'fa-brands fa-tiktok', 'url' => 'https://tiktok.com/@yourhandle', 'label' => 'TikTok'],
            ], 'style' => 'rounded', 'size' => 'md', '_placeholder' => true],
            'instagram_media' => ['url' => 'https://www.instagram.com/p/CkQ7-gDgF8B/', '_placeholder' => true],
            'tiktok_video' => ['url' => 'https://www.tiktok.com/@scout2015/video/6718335390845095173', '_placeholder' => true],
            'tiktok_profile' => ['username' => 'scout2015', '_placeholder' => true],
            'twitter_profile' => ['username' => 'twitter', '_placeholder' => true],
            'twitter_tweet' => ['url' => 'https://twitter.com/Twitter/status/1445078208190291973', '_placeholder' => true],
            'twitter_video' => ['url' => 'https://twitter.com/Twitter/status/1445078208190291973', '_placeholder' => true],
            'pinterest_profile' => ['username' => 'pinterest', '_placeholder' => true],
            'snapchat' => ['username' => 'team.snapchat', '_placeholder' => true],
            'rss_feed' => ['url' => 'https://hnrss.org/frontpage', 'count' => 5, '_placeholder' => true],

            'spotify' => ['url' => 'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT', 'type' => 'track', '_placeholder' => true],
            'apple_music' => ['url' => 'https://music.apple.com/us/album/abbey-road-remastered/1441164426', 'type' => 'album', '_placeholder' => true],
            'soundcloud' => ['url' => 'https://soundcloud.com/forss/flickermood', '_placeholder' => true],
            'tidal' => ['url' => 'https://tidal.com/browse/track/77640617', '_placeholder' => true],
            'mixcloud' => ['url' => 'https://www.mixcloud.com/discover/popular/', '_placeholder' => true],
            'anchor_fm' => ['url' => 'https://anchor.fm/yourshow', '_placeholder' => true],

            'youtube' => ['video_id' => 'dQw4w9WgXcQ', 'autoplay' => false, '_placeholder' => true],
            'youtube_feed' => ['channel_id' => 'UC_x5XG1OV2P6uZZ5FSM9Ttw', 'count' => 3, '_placeholder' => true],
            'vimeo' => ['video_id' => '76979871', '_placeholder' => true],
            'twitch' => ['channel' => 'twitch', '_placeholder' => true],
            'kick' => ['channel' => 'trainwreckstv', '_placeholder' => true],
            'rumble_video' => ['url' => 'https://rumble.com/v3hxrlk-introducing-rumble-cloud.html', '_placeholder' => true],
            'vk_video' => ['url' => 'https://vk.com/video-9695053_456239639', '_placeholder' => true],

            'email_collector' => ['title' => 'Stay in the loop', 'placeholder' => 'you@example.com', 'button_text' => 'Subscribe', '_placeholder' => true],
            'phone_collector' => ['title' => 'Call me back', 'placeholder' => '+1 555 123 4567', 'button_text' => 'Request a callback', '_placeholder' => true],
            'contact_form' => ['title' => 'Get in touch', 'fields' => ['name', 'email', 'message'], 'button_text' => 'Send message', '_placeholder' => true],
            'whatsapp_widget' => ['phone' => '+15551234567', 'message' => 'Hi! I saw your link in bio and wanted to chat.', 'button_text' => 'Chat on WhatsApp', '_placeholder' => true],
            'whatsapp_item' => ['phone' => '+15551234567', 'name' => 'Sales team', 'message' => 'Hi! I have a quick question.', 'avatar' => $avatarUrl, '_placeholder' => true],
            'email_subscribe' => ['title' => 'Join our Newsletter', 'description' => 'Get the latest updates delivered to your inbox.', 'placeholder' => 'you@example.com', 'button_text' => 'Subscribe', 'success_message' => 'Thanks for subscribing!', 'name_field' => true, '_placeholder' => true],
            'whatsapp_channel_subscribe' => ['title' => 'Follow our WhatsApp Channel', 'description' => 'Stay updated with our latest content.', 'channel_url' => 'https://whatsapp.com/channel/0029Va4f3oqGE56fFuoPJa1A', 'button_text' => 'Follow Channel', 'icon_style' => 'branded', '_placeholder' => true],
            'whatsapp_number_subscribe' => ['title' => 'Subscribe via WhatsApp', 'description' => 'Get updates directly on WhatsApp.', 'phone' => '+15551234567', 'default_message' => 'Hi! I want to subscribe to updates.', 'button_text' => 'Subscribe on WhatsApp', 'collect_phone' => true, '_placeholder' => true],

            'verified_heading' => ['text' => '', 'verified' => true, 'locked_text' => true, 'font_size' => '24', 'alignment' => 'center'],
            'verified_avatar' => ['image_url' => '', 'verified' => true, 'locked_image' => true, 'size' => '100', 'shape' => 'circle'],

            'faq' => ['items' => [
                ['question' => 'How do I get started?', 'answer' => 'Replace this with your most common question and answer.'],
                ['question' => 'Do you offer support?', 'answer' => 'Yes — replace this with how customers can reach you.'],
            ], '_placeholder' => true],
            'faq_v2' => ['items' => [
                ['question' => 'How do I get started?', 'answer' => 'Replace with your real answer.', 'icon' => 'fa-circle-question'],
                ['question' => 'Do you offer support?', 'answer' => 'Replace with your real answer.', 'icon' => 'fa-life-ring'],
            ], 'style' => 'bordered', '_placeholder' => true],
            'poll' => ['question' => 'What should I post next?', 'options' => ['Behind the scenes', 'Tutorials', 'Q&A sessions'], '_placeholder' => true],
            'quiz' => ['title' => 'Quick Quiz', 'questions' => [
                ['question' => 'Which option do you prefer?', 'options' => ['Option A', 'Option B'], 'correct' => 0],
            ], '_placeholder' => true],
            'testimonials' => ['items' => [
                ['name' => 'Alex Carter', 'text' => 'A glowing testimonial goes here. Replace with a real one.', 'avatar' => $avatarUrl, 'rating' => 5],
                ['name' => 'Sam Lopez', 'text' => 'Another testimonial — swap in real customer feedback.', 'avatar' => $avatarUrl, 'rating' => 5],
            ], '_placeholder' => true],
            'review' => ['name' => 'Alex Carter', 'text' => 'Loved working with them — would recommend! (Replace with a real review.)', 'rating' => 5, 'avatar' => $avatarUrl, '_placeholder' => true],
            'reviews_wall' => ['heading' => 'What people are saying', 'source' => 'both', 'layout' => 'grid', 'sort' => 'recent', 'limit' => 6, 'show_summary' => true, 'allow_submissions' => true, 'providers' => [], '_placeholder' => true],
            'timeline' => ['items' => [
                ['title' => 'Got started', 'description' => 'The day it all began.', 'date' => '2024-01'],
                ['title' => 'Hit a milestone', 'description' => 'Replace with your own moment.', 'date' => '2024-06'],
                ['title' => 'Today', 'description' => 'Replace with what you\'re up to now.', 'date' => '2025'],
            ], '_placeholder' => true],
            'timeline_staged' => ['items' => [
                ['title' => 'Stage 1 — Discovery', 'description' => 'Replace with your first stage.', 'status' => 'completed'],
                ['title' => 'Stage 2 — In progress', 'description' => 'What you\'re working on now.', 'status' => 'in_progress'],
                ['title' => 'Stage 3 — Coming up', 'description' => 'What\'s next on the roadmap.', 'status' => 'planned'],
            ], '_placeholder' => true],

            'product' => ['name' => 'Sample Product', 'description' => 'A short description of what makes this product great.', 'price' => '$29', 'image' => $imgSquareUrl, 'url' => 'https://example.com', 'badge' => 'New', '_placeholder' => true],
            'service' => ['name' => 'Sample Service', 'description' => 'What you offer and who it\'s for.', 'price' => 'From $99', 'icon' => 'fa-star', 'url' => 'https://example.com', '_placeholder' => true],
            'catalog' => ['items' => [
                ['name' => 'Sample Item 1', 'price' => '$19', 'image' => $imgSquareUrl, 'url' => 'https://example.com'],
                ['name' => 'Sample Item 2', 'price' => '$29', 'image' => $imgSquareUrl, 'url' => 'https://example.com'],
            ], '_placeholder' => true],
            'market' => ['items' => [
                ['name' => 'Sample Product', 'price' => '$29', 'image' => $imgSquareUrl, 'url' => 'https://example.com'],
                ['name' => 'Another Product', 'price' => '$49', 'image' => $imgSquareUrl, 'url' => 'https://example.com'],
            ], '_placeholder' => true],
            'price' => ['amount' => '$99', 'period' => '/month', 'title' => 'Pro Plan', 'features' => ['Everything in Starter', 'Priority support', 'Custom integrations'], 'url' => 'https://example.com', '_placeholder' => true],
            'donation' => ['title' => 'Support my work', 'description' => 'Every contribution helps me keep creating.', 'amounts' => [5, 10, 25, 50], 'currency' => 'USD', 'url' => 'https://example.com', '_placeholder' => true],
            'coupon' => ['code' => 'SAVE20', 'description' => 'Get 20% off your first order.', 'expires' => '', '_placeholder' => true],
            'one_time_offer' => ['title' => 'Limited-time offer', 'description' => 'A short pitch about why this offer is special.', 'price' => '$49', 'original_price' => '$99', 'url' => 'https://example.com', 'countdown' => '', '_placeholder' => true],
            'paypal' => ['email' => 'you@example.com', 'amount' => '10', 'currency' => 'USD', 'button_text' => 'Pay with PayPal', '_placeholder' => true],

            'countdown' => ['target_date' => date('Y-m-d', strtotime('+30 days')), 'title' => 'Coming soon — replace this', '_placeholder' => true],
            'progress' => ['items' => [
                ['label' => 'Goal one', 'value' => 75, 'color' => '#7c3aed'],
                ['label' => 'Goal two', 'value' => 40, 'color' => '#22d3ee'],
            ], '_placeholder' => true],
            'chart_pie' => ['items' => [
                ['label' => 'Segment A', 'value' => 50, 'color' => '#7c3aed'],
                ['label' => 'Segment B', 'value' => 30, 'color' => '#ec4899'],
                ['label' => 'Segment C', 'value' => 20, 'color' => '#22d3ee'],
            ], '_placeholder' => true],
            'qr_code' => ['url' => 'https://example.com', 'size' => 200, '_placeholder' => true],
            'share' => ['text' => 'Share this page', 'platforms' => ['twitter', 'facebook', 'linkedin', 'whatsapp'], '_placeholder' => true],
            'cta_button' => ['text' => 'Get started', 'url' => 'https://example.com', 'color' => '#7c3aed', 'text_color' => '#ffffff', 'size' => 'lg', '_placeholder' => true],
            'notification' => ['text' => 'Replace this with your latest update or announcement.', 'type' => 'info', 'dismissible' => true, '_placeholder' => true],
            'social_proof' => ['social_proof_id' => null],
            'ai_companion' => ['companion_id' => null],
            'form' => ['form_id' => null, 'height' => 600],
            'nav_menu' => ['items' => [
                ['text' => 'Home', 'url' => '#'],
                ['text' => 'About', 'url' => '#about'],
                ['text' => 'Contact', 'url' => '#contact'],
            ], '_placeholder' => true],
            'ticker' => ['items' => ['Breaking news', 'Replace with your own announcements'], 'speed' => 'normal', '_placeholder' => true],

            'spacer' => ['height' => 20],
            'card' => [
                'title' => 'Card title',
                '_placeholder' => true,
                'columns' => 2,
                'gap' => 12,
                'padding' => 16,
                'border_radius' => 16,
                'bg_type' => 'glass',
                'bg_color' => 'rgba(255,255,255,0.06)',
                'bg_gradient' => '',
                'bg_image' => '',
                'glass_blur' => 12,
                'glass_opacity' => 6,
                'border_color' => 'rgba(255,255,255,0.08)',
                'border_width' => 1,
                'shadow' => 'none',
                'shadow_color' => '#00000040',
            ],

            'grid' => [
                'title' => 'Grid section',
                '_placeholder' => true,
                'columns' => 2,
                'gap' => 12,
                'padding' => 0,
            ],
            'grid_auto' => [
                'title' => 'Auto-fit grid',
                '_placeholder' => true,
                'min_width' => 140,
                'gap' => 12,
                'padding' => 0,
            ],

            'card_slider' => ['cards' => [
                ['title' => 'Card one', 'description' => 'A short description of what this card is about.', 'image' => $imgSquareUrl, 'url' => 'https://example.com'],
                ['title' => 'Card two', 'description' => 'Replace these placeholder cards with your own content.', 'image' => $imgSquareUrl, 'url' => 'https://example.com'],
                ['title' => 'Card three', 'description' => 'Each card can link somewhere different.', 'image' => $imgSquareUrl, 'url' => 'https://example.com'],
            ], '_placeholder' => true],
            'scroll_cards' => ['cards' => [
                ['title' => 'Card one', 'description' => 'A short description of what this card is about.', 'image' => $imgSquareUrl],
                ['title' => 'Card two', 'description' => 'Replace these with your own content.', 'image' => $imgSquareUrl],
                ['title' => 'Card three', 'description' => 'Up to a dozen cards work nicely here.', 'image' => $imgSquareUrl],
            ], '_placeholder' => true],
            // Profile cards (Task #1740). Every slot now carries the full
            // field set the ten `profile_identity` designs can surface —
            // cover, verified flag, location/website, a CTA pair and a
            // socials list — so any design works on any slot at first paint.
            // Layouts only render the fields they use, so the extras stay
            // invisible until a design that needs them is applied.
            'profile_card_v1' => ['name' => 'Your Name', 'title' => 'What you do', 'avatar' => $avatarUrl, 'cover' => $coverUrl, 'bio' => 'A short, friendly bio about yourself.', 'verified' => true, 'location' => 'Your City, Country', 'website' => 'https://1inme.com', 'cta_label' => 'Get in touch', 'cta_url' => 'https://1inme.com', 'socials' => $profileSocials, '_placeholder' => true],
            'profile_card_v2' => ['name' => 'Your Name', 'title' => 'What you do', 'avatar' => $avatarUrl, 'cover' => $coverUrl, 'bio' => 'A short, friendly bio about yourself.', 'verified' => true, 'location' => 'Your City, Country', 'website' => 'https://1inme.com', 'cta_label' => 'Get in touch', 'cta_url' => 'https://1inme.com', 'socials' => $profileSocials, '_placeholder' => true],
            'profile_card_v3' => ['name' => 'Your Name', 'title' => 'What you do', 'avatar' => $avatarUrl, 'cover' => $coverUrl, 'bio' => 'A short, friendly bio about yourself.', 'verified' => true, 'location' => 'Your City, Country', 'website' => 'https://1inme.com', 'cta_label' => 'Get in touch', 'cta_url' => 'https://1inme.com', 'socials' => $profileSocials, 'stats' => [['label' => 'Followers', 'value' => '1.2K'], ['label' => 'Following', 'value' => '320'], ['label' => 'Posts', 'value' => '48']], '_placeholder' => true],
            'profile_card_v4' => ['name' => 'Your Name', 'title' => 'What you do', 'avatar' => $avatarUrl, 'cover' => $coverUrl, 'bio' => 'A short, friendly bio about yourself.', 'verified' => true, 'location' => 'Your City, Country', 'website' => 'https://1inme.com', 'cta_label' => 'Get in touch', 'cta_url' => 'https://1inme.com', 'socials' => $profileSocials, 'badges' => [], '_placeholder' => true],

            'custom_html' => ['html' => '<!-- Paste your custom HTML here -->', '_placeholder' => true],
            'iframe_embed' => ['url' => 'https://example.com', 'height' => 400, '_placeholder' => true],
            'typeform' => ['url' => 'https://form.typeform.com/to/abcd1234', '_placeholder' => true],
            'calendly' => ['url' => 'https://calendly.com/yourname/30min', '_placeholder' => true],
            'discord_server' => ['server_id' => '267624335836053506', '_placeholder' => true],
            'facebook_post' => ['url' => 'https://www.facebook.com/20531316728/posts/10154009990506729/', '_placeholder' => true],
            'reddit_post' => ['url' => 'https://www.reddit.com/r/announcements/comments/8bb85p/', '_placeholder' => true],
            'telegram_post' => ['url' => 'https://t.me/telegram/197', '_placeholder' => true],

            'file' => ['url' => $pdfUrl, 'name' => 'Download placeholder', 'size' => '12 KB', 'icon' => 'fa-file-download', '_placeholder' => true],
            'external_item' => ['url' => 'https://example.com', 'title' => 'External item title', 'description' => 'A short description that will appear under the title.', 'image' => $imgUrl, '_placeholder' => true],
            'markdown' => ['content' => "# Hello\n\nReplace this with your **markdown** content. Headings, _italics_, lists, and [links](https://example.com) all work.", '_placeholder' => true],

            'map' => ['address' => '1600 Amphitheatre Parkway, Mountain View, CA', 'zoom' => 14, '_placeholder' => true],
            'yandex_maps' => ['address' => 'Red Square, Moscow, Russia', 'zoom' => 14, '_placeholder' => true],
            'map_location' => ['address' => '1600 Amphitheatre Parkway, Mountain View, CA', 'lat' => '37.4220', 'lng' => '-122.0841', 'label' => 'Drop a friendly label here', 'zoom' => 15, 'show_directions' => true, '_placeholder' => true],

            'buy_me_coffee' => ['username' => 'yourname', 'text' => 'Buy me a coffee', 'description' => 'Your tips keep me caffeinated and creating.', 'amounts' => [1, 3, 5], '_placeholder' => true],
            'patreon' => ['username' => 'yourname', 'text' => 'Become a patron', 'description' => 'Get exclusive perks and support what I make.', 'tier_name' => 'Supporter', '_placeholder' => true],
            'ko_fi' => ['username' => 'yourname', 'text' => 'Support me on Ko-fi', 'description' => 'A small tip goes a long way.', 'amounts' => [3, 5, 10], '_placeholder' => true],
            'latest_youtube' => ['channel' => 'GoogleDevelopers', 'video_id' => '', 'title' => 'Latest from your channel', 'thumbnail' => $imgUrl, 'cached_at' => null, '_placeholder' => true],
            'latest_instagram' => ['handle' => 'instagram', 'post_url' => '', 'thumbnail' => $imgSquareUrl, 'caption' => 'Latest from your feed', 'cached_at' => null, '_placeholder' => true],
            'featured_pin' => ['text' => 'Featured', 'description' => 'Highlight your top link or announcement.', 'url' => 'https://example.com', 'icon' => 'fa-thumbtack', 'thumbnail' => $imgSquareUrl, 'accent_color' => '#f59e0b', '_placeholder' => true],
            'calendly_embed' => ['url' => 'https://calendly.com/yourname/30min', 'height' => 700, 'hide_event_details' => false, 'hide_cookie_banner' => true, '_placeholder' => true],

            'vcard' => ['name' => 'Your Name', 'email' => 'you@example.com', 'phone' => '+1 555 123 4567', 'company' => 'Your Company', 'title' => 'Your Role', 'website' => 'https://example.com', '_placeholder' => true],
            'avatar' => ['url' => $avatarUrl, 'size' => 96, 'rounded' => true, '_placeholder' => true],

            'roadmap' => [
                'title'                => 'Public Roadmap',
                'subtitle'             => 'Suggest ideas, vote on what comes next.',
                'allow_submissions'    => true,
                'require_email'        => true,
                'require_login'        => false,
                'auto_approve'         => false,
                'kanban_board_id'      => null,
                'show_columns'         => ['ideas', 'planned', 'in_progress', 'shipped'],
                'blocked_emails'       => [],
                'blocked_fingerprints' => [],
                '_placeholder'         => true,
            ],

            // ── Newer interactive / contact / identity types ───────────
            // Insider feed: gated content stream. Seed with sample posts
            // so the empty state isn't confusing on first paint.
            'insider' => ['title' => 'Insider Updates', 'description' => 'Members-only news, drops, and behind-the-scenes posts.', 'access' => 'public', 'cta_text' => 'Become an insider', 'posts' => [
                ['title' => 'Welcome to the inner circle', 'body' => 'Replace this with your first insider-only update.', 'date' => date('Y-m-d')],
                ['title' => 'What I\'m working on', 'body' => 'A short note about what\'s coming up.', 'date' => date('Y-m-d', strtotime('-3 days'))],
            ], '_placeholder' => true],
            // Top-fans leaderboard: seed with 3 sample fans so the layout
            // demonstrates rankings on first render.
            'fan_leaderboard' => ['title' => 'Top Fans', 'description' => 'My most engaged supporters this month.', 'period' => 'monthly', 'show_avatars' => true, 'fans' => [
                ['name' => 'Alex Carter', 'avatar' => $avatarUrl, 'score' => 1240, 'badge' => 'Champion'],
                ['name' => 'Sam Lopez', 'avatar' => $avatarUrl, 'score' => 980, 'badge' => 'MVP'],
                ['name' => 'Riley Chen', 'avatar' => $avatarUrl, 'score' => 720, 'badge' => 'Rising star'],
            ], '_placeholder' => true],
            // Direct message: seed with phone+email channel options and
            // friendly prompt so the form is usable immediately.
            'direct_message' => ['title' => 'Send me a message', 'description' => 'I read every note — usually reply within a day.', 'placeholder' => 'Say hi, ask a question, or pitch a collab…', 'button_text' => 'Send message', 'channel' => 'email', 'destination_email' => 'you@example.com', 'destination_phone' => '+15551234567', 'collect_name' => true, 'collect_email' => true, '_placeholder' => true],
            // Resume / CV: seed with a believable mini-resume covering
            // role, experience, education, skills, and links.
            'resume' => [
                'name' => 'Your Name',
                'headline' => 'What you do, in one line',
                'summary' => 'A short paragraph that sells you in 30 seconds. Replace with your own bio.',
                'avatar' => $avatarUrl,
                'email' => 'you@example.com',
                'phone' => '+1 555 123 4567',
                'website' => 'https://example.com',
                'location' => 'City, Country',
                'experience' => [
                    ['title' => 'Lead Designer', 'company' => 'Bright Studio', 'start' => '2022', 'end' => 'Present', 'description' => 'Replace with what you built and the impact it had.'],
                    ['title' => 'Designer', 'company' => 'Northwind Co', 'start' => '2019', 'end' => '2022', 'description' => 'A line about your previous role.'],
                ],
                'education' => [
                    ['school' => 'State University', 'degree' => 'BFA, Design', 'start' => '2015', 'end' => '2019'],
                ],
                'skills' => ['Design systems', 'Prototyping', 'Product strategy', 'Workshops'],
                'links' => [
                    ['label' => 'Portfolio', 'url' => 'https://example.com'],
                    ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/yourhandle'],
                ],
                '_placeholder' => true,
            ],

            'menu_section' => ['name' => 'Mains', 'layout' => 'plain', 'accent_color' => '#7c3aed', 'items' => [
                ['name' => 'Margherita pizza', 'price' => '$14', 'description' => 'San Marzano tomato, fior di latte, basil.'],
                ['name' => 'Cacio e pepe', 'price' => '$16', 'description' => 'Fresh tonnarelli, pecorino romano, black pepper.'],
            ], '_placeholder' => true],
            'instagram' => ['mode' => 'post', 'handle' => 'instagram', 'post_url' => 'https://www.instagram.com/p/CkQ7-gDgF8B/', 'thumbnail' => $imgSquareUrl, 'caption' => 'Latest from your feed', '_placeholder' => true],


            'file_list' => ['title' => 'Files', 'layout' => 'compact', 'accent_color' => '#7c3aed', 'items' => [
                ['name' => 'Placeholder document.pdf', 'url' => $pdfUrl, 'ext' => 'pdf', 'size' => 13312, 'description' => 'Replace with your own file.'],
            ], '_placeholder' => true],
            'audio_list' => ['title' => 'Playlist', 'layout' => 'compact', 'accent_color' => '#7c3aed', 'tracks' => [
                ['title' => 'Placeholder track', 'artist' => 'SoundHelix', 'url' => $audioUrl, 'cover' => $imgSquareUrl, 'duration' => '6:00'],
            ], '_placeholder' => true],
            'link_tree_group' => ['title' => 'My Links', 'layout' => 'list', 'accent_color' => '#7c3aed', 'items' => [
                ['text' => 'My website', 'url' => 'https://example.com', 'icon' => 'fa-globe', 'description' => 'Where it all lives.'],
                ['text' => 'Latest project', 'url' => 'https://example.com', 'icon' => 'fa-rocket', 'description' => 'What I\'m working on now.'],
                ['text' => 'Contact me', 'url' => 'mailto:you@example.com', 'icon' => 'fa-envelope', 'description' => 'For collabs and questions.'],
            ], '_placeholder' => true],
            'tabs' => ['layout' => 'tabs', 'accent_color' => '#7c3aed', 'tabs' => [
                ['label' => 'About', 'text' => 'A short intro about you or your project.'],
                ['label' => 'Services', 'text' => 'Replace with what you offer.'],
                ['label' => 'Contact', 'text' => 'How to get in touch.'],
            ], '_placeholder' => true],
            'accordion' => ['layout' => 'plain', 'accent_color' => '#7c3aed', 'items' => [
                ['title' => 'How does it work?', 'body' => 'Replace with your own answer.'],
                ['title' => 'Where can I learn more?', 'body' => 'Replace with a real answer or a link to your docs.'],
            ], '_placeholder' => true],
            'event_list' => ['title' => 'Upcoming Events', 'layout' => 'compact', 'accent_color' => '#7c3aed', 'events' => [
                ['title' => 'Live Q&A on YouTube', 'date' => date('Y-m-d', strtotime('+7 days')), 'location' => 'Online', 'url' => 'https://example.com', 'description' => 'Replace with your real event.'],
                ['title' => 'Pop-up workshop', 'date' => date('Y-m-d', strtotime('+21 days')), 'location' => 'Brooklyn, NY', 'url' => 'https://example.com', 'description' => 'A short blurb about what attendees will learn.'],
            ], '_placeholder' => true],
            'menu' => ['title' => 'Today\'s Menu', 'layout' => 'classic', 'accent_color' => '#7c3aed', 'sections' => [
                ['name' => 'Starters', 'items' => [
                    ['name' => 'House focaccia', 'price' => '$6', 'description' => 'With rosemary and flaky salt.', 'thumbnail' => $imgSquareUrl],
                    ['name' => 'Caesar salad', 'price' => '$11', 'description' => 'Romaine, anchovy dressing, parmesan.', 'thumbnail' => $imgSquareUrl],
                ]],
                ['name' => 'Mains', 'items' => [
                    ['name' => 'Margherita pizza', 'price' => '$14', 'description' => 'San Marzano tomato, fior di latte, basil.', 'thumbnail' => $imgSquareUrl],
                ]],
            ], '_placeholder' => true],
            'testimonial_carousel' => ['layout' => 'carousel', 'accent_color' => '#7c3aed', 'items' => [
                ['quote' => 'Genuinely the best service I\'ve used this year.', 'name' => 'Alex Carter', 'title' => 'Founder, Bright Studio', 'avatar' => $avatarUrl],
                ['quote' => 'The whole team was a delight to work with.', 'name' => 'Sam Lopez', 'title' => 'Head of Marketing, Northwind', 'avatar' => $avatarUrl],
            ], '_placeholder' => true],
            'stats' => ['title' => 'By the numbers', 'layout' => 'row', 'accent_color' => '#7c3aed', 'items' => [
                ['value' => '10k', 'label' => 'Followers', 'caption' => 'across socials'],
                ['value' => '4.9', 'label' => 'Rating', 'caption' => 'from 230 reviews'],
                ['value' => '120', 'label' => 'Projects', 'caption' => 'shipped to date'],
            ], '_placeholder' => true],
            'affiliate_links' => ['title' => 'My Picks', 'layout' => 'compact', 'accent_color' => '#7c3aed', 'disclaimer' => 'Some links may earn a commission.', 'items' => [
                ['name' => 'Sample affiliate product', 'url' => 'https://example.com', 'price' => '$29', 'merchant' => 'Example Store', 'thumbnail' => $imgSquareUrl],
                ['name' => 'Another favourite', 'url' => 'https://example.com', 'price' => '$59', 'merchant' => 'Example Store', 'thumbnail' => $imgSquareUrl],
            ], '_placeholder' => true],
            'booking_slots' => ['title' => 'Book a slot', 'layout' => 'list', 'cta_text' => 'Book', 'accent_color' => '#7c3aed', 'slots' => [
                ['start' => date('Y-m-d', strtotime('+1 day')) . ' 10:00', 'duration' => '30 min', 'url' => 'https://example.com', 'taken' => false],
                ['start' => date('Y-m-d', strtotime('+1 day')) . ' 14:00', 'duration' => '30 min', 'url' => 'https://example.com', 'taken' => false],
                ['start' => date('Y-m-d', strtotime('+2 day')) . ' 09:30', 'duration' => '60 min', 'url' => 'https://example.com', 'taken' => false],
            ], '_placeholder' => true],

            default => [],
        };
    }

    /**
     * Full seeded settings for a freshly-added block: content from
     * {@see contentForType()} merged with `_style` derived from
     * {@see styleForType()} layered on {@see BiolinkBlock::STYLE_DEFAULTS}.
     * Used by the picker preview tile so its render matches what the
     * controller will actually save when the user clicks the tile.
     *
     * @return array<string,mixed>
     */
    public static function seededSettings(string $type): array
    {
        $settings = self::contentForType($type);
        if (!isset($settings['_style']) || !is_array($settings['_style']) || $settings['_style'] === []) {
            $settings['_style'] = array_merge(
                BiolinkBlock::STYLE_DEFAULTS,
                self::styleForType($type)
            );
        }
        return $settings;
    }
}
