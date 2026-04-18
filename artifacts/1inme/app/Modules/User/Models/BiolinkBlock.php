<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class BiolinkBlock extends Model
{
    protected $fillable = [
        'link_id', 'type', 'settings', 'sort_order', 'is_active',
        'start_date', 'end_date', 'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    public const CATEGORIES = [
        'basic'           => 'Essentials',
        'layout'          => 'Layout & Profile',
        'media'           => 'Media',
        'interactive'     => 'Engagement',
        'business'        => 'Commerce',
        'contact'         => 'Contact & Lead Capture',
        'social'          => 'Social Profiles',
        'music'           => 'Music & Audio',
        'video_platforms' => 'Video Platforms',
        'utility'         => 'Widgets',
        'maps'            => 'Maps',
        'integrations'    => 'Embeds & Integrations',
        'identity'        => 'Identity',
    ];

    public const TYPES = [
        // ── Essentials ────────────────────────────────────────────────
        'link'             => ['label' => 'Link Button',         'icon' => 'fa-link',                       'category' => 'basic'],
        'link_big'         => ['label' => 'Featured Link',       'icon' => 'fa-external-link-square-alt',   'category' => 'basic'],
        'heading'          => ['label' => 'Heading',             'icon' => 'fa-heading',                    'category' => 'basic'],
        'heading_gradient' => ['label' => 'Gradient Heading',    'icon' => 'fa-font',                       'category' => 'basic'],
        'heading_logo'     => ['label' => 'Logo Heading',        'icon' => 'fa-image',                      'category' => 'basic'],
        'heading_morph'    => ['label' => 'Animated Heading',    'icon' => 'fa-magic',                      'category' => 'basic'],
        'paragraph'        => ['label' => 'Text',                'icon' => 'fa-paragraph',                  'category' => 'basic'],
        'paragraph_rich'   => ['label' => 'Rich Text',           'icon' => 'fa-align-left',                 'category' => 'basic'],
        'markdown'         => ['label' => 'Markdown',            'icon' => 'fa-file-alt',                   'category' => 'basic'],
        'list'             => ['label' => 'Bulleted List',       'icon' => 'fa-list-ul',                    'category' => 'basic'],
        'list_numbered'    => ['label' => 'Numbered List',       'icon' => 'fa-list-ol',                    'category' => 'basic'],
        'list_pricing'     => ['label' => 'Pricing List',        'icon' => 'fa-tags',                       'category' => 'basic'],
        'alert'            => ['label' => 'Alert Banner',        'icon' => 'fa-exclamation-triangle',       'category' => 'basic'],
        'badge'            => ['label' => 'Badge',               'icon' => 'fa-certificate',                'category' => 'basic'],
        'divider'          => ['label' => 'Divider',             'icon' => 'fa-minus',                      'category' => 'basic'],
        'spacer'           => ['label' => 'Spacer',              'icon' => 'fa-arrows-alt-v',               'category' => 'basic'],

        // ── Layout & Profile ──────────────────────────────────────────
        'card'             => ['label' => 'Card Container',      'icon' => 'fa-layer-group',                'category' => 'layout'],
        'card_slider'      => ['label' => 'Card Carousel',       'icon' => 'fa-clone',                      'category' => 'layout'],
        'scroll_cards'     => ['label' => 'Scrolling Cards',     'icon' => 'fa-columns',                    'category' => 'layout'],
        'profile_card_v1'  => ['label' => 'Profile · Classic',   'icon' => 'fa-id-card',                    'category' => 'layout'],
        'profile_card_v2'  => ['label' => 'Profile · Cover',     'icon' => 'fa-id-card-alt',                'category' => 'layout'],
        'profile_card_v3'  => ['label' => 'Profile · Stats',     'icon' => 'fa-address-card',               'category' => 'layout'],
        'profile_card_v4'  => ['label' => 'Profile · Badges',    'icon' => 'fa-user-tag',                   'category' => 'layout'],

        // ── Media ─────────────────────────────────────────────────────
        'image'            => ['label' => 'Image',               'icon' => 'fa-image',                      'category' => 'media'],
        'image_grid'       => ['label' => 'Image Grid',          'icon' => 'fa-th',                         'category' => 'media'],
        'image_slider'     => ['label' => 'Image Slider',        'icon' => 'fa-images',                     'category' => 'media'],
        'image_slider_v2'  => ['label' => 'Photo Carousel',      'icon' => 'fa-photo-video',                'category' => 'media'],
        'header_video'     => ['label' => 'Header Video',        'icon' => 'fa-film',                       'category' => 'media'],
        'video'            => ['label' => 'Video',               'icon' => 'fa-video',                      'category' => 'media'],
        'audio'            => ['label' => 'Audio Player',        'icon' => 'fa-music',                      'category' => 'media'],
        'pdf_document'     => ['label' => 'PDF Document',        'icon' => 'fa-file-pdf',                   'category' => 'media'],
        'powerpoint'       => ['label' => 'Slides (PowerPoint)', 'icon' => 'fa-file-powerpoint',            'category' => 'media'],
        'excel'            => ['label' => 'Spreadsheet (Excel)', 'icon' => 'fa-file-excel',                 'category' => 'media'],
        'file'             => ['label' => 'File Download',       'icon' => 'fa-file-download',              'category' => 'media'],

        // ── Engagement ────────────────────────────────────────────────
        'faq'              => ['label' => 'FAQ',                 'icon' => 'fa-question-circle',            'category' => 'interactive'],
        'faq_v2'           => ['label' => 'FAQ (Accordion)',     'icon' => 'fa-question',                   'category' => 'interactive'],
        'poll'             => ['label' => 'Poll',                'icon' => 'fa-poll',                       'category' => 'interactive'],
        'quiz'             => ['label' => 'Quiz',                'icon' => 'fa-brain',                      'category' => 'interactive'],
        'testimonials'     => ['label' => 'Testimonials',        'icon' => 'fa-quote-right',                'category' => 'interactive'],
        'review'           => ['label' => 'Reviews',             'icon' => 'fa-star',                       'category' => 'interactive'],
        'timeline'         => ['label' => 'Timeline',            'icon' => 'fa-stream',                     'category' => 'interactive'],
        'timeline_staged'  => ['label' => 'Staged Timeline',     'icon' => 'fa-project-diagram',            'category' => 'interactive'],
        'social_proof'     => ['label' => 'Buzz Notification',   'icon' => 'fa-bell',                       'category' => 'interactive'],

        // ── Commerce ──────────────────────────────────────────────────
        'product'          => ['label' => 'Product',             'icon' => 'fa-box',                        'category' => 'business'],
        'service'          => ['label' => 'Service',             'icon' => 'fa-concierge-bell',             'category' => 'business'],
        'catalog'          => ['label' => 'Catalog',             'icon' => 'fa-book-open',                  'category' => 'business'],
        'market'           => ['label' => 'Storefront',          'icon' => 'fa-store',                      'category' => 'business'],
        'price'            => ['label' => 'Price Tag',           'icon' => 'fa-dollar-sign',                'category' => 'business'],
        'donation'         => ['label' => 'Donation',            'icon' => 'fa-hand-holding-heart',         'category' => 'business'],
        'coupon'           => ['label' => 'Coupon',              'icon' => 'fa-ticket-alt',                 'category' => 'business'],
        'one_time_offer'   => ['label' => 'Limited Offer',       'icon' => 'fa-fire',                       'category' => 'business'],
        'paypal'           => ['label' => 'PayPal',              'icon' => 'fa-credit-card',                'category' => 'business'],

        // ── Contact & Lead Capture ────────────────────────────────────
        'email_collector'           => ['label' => 'Email Collector',       'icon' => 'fa-envelope',           'category' => 'contact'],
        'email_subscribe'           => ['label' => 'Newsletter Signup',     'icon' => 'fa-envelope-open-text', 'category' => 'contact'],
        'phone_collector'           => ['label' => 'Phone Collector',       'icon' => 'fa-phone',              'category' => 'contact'],
        'contact_form'              => ['label' => 'Contact Form',          'icon' => 'fa-paper-plane',        'category' => 'contact'],
        'whatsapp_widget'           => ['label' => 'WhatsApp Chat',         'icon' => 'fa-comment-dots',       'category' => 'contact'],
        'whatsapp_item'             => ['label' => 'WhatsApp Button',       'icon' => 'fa-comments',           'category' => 'contact'],
        'whatsapp_channel_subscribe'=> ['label' => 'WhatsApp Channel',      'icon' => 'fa-bullhorn',           'category' => 'contact'],
        'whatsapp_number_subscribe' => ['label' => 'WhatsApp Number',       'icon' => 'fa-phone-square',       'category' => 'contact'],

        // ── Social Profiles ───────────────────────────────────────────
        'socials'          => ['label' => 'Social Icons',        'icon' => 'fa-share-alt',                  'category' => 'social'],
        'socials_multi'    => ['label' => 'Social Hub',          'icon' => 'fa-users',                      'category' => 'social'],
        'socials_custom'   => ['label' => 'Custom Social Icons', 'icon' => 'fa-paint-brush',                'category' => 'social'],
        'instagram_media'  => ['label' => 'Instagram Post',      'icon' => 'fa-instagram',                  'category' => 'social'],
        'tiktok_video'     => ['label' => 'TikTok Video',        'icon' => 'fa-music',                      'category' => 'social'],
        'tiktok_profile'   => ['label' => 'TikTok Profile',      'icon' => 'fa-user',                       'category' => 'social'],
        'twitter_profile'  => ['label' => 'X / Twitter Profile', 'icon' => 'fa-user-circle',                'category' => 'social'],
        'twitter_tweet'    => ['label' => 'X / Twitter Post',    'icon' => 'fa-comment',                    'category' => 'social'],
        'twitter_video'    => ['label' => 'X / Twitter Video',   'icon' => 'fa-play-circle',                'category' => 'social'],
        'pinterest_profile'=> ['label' => 'Pinterest Profile',   'icon' => 'fa-thumbtack',                  'category' => 'social'],
        'snapchat'         => ['label' => 'Snapchat',            'icon' => 'fa-ghost',                      'category' => 'social'],
        'rss_feed'         => ['label' => 'RSS Feed',            'icon' => 'fa-rss',                        'category' => 'social'],

        // ── Music & Audio ─────────────────────────────────────────────
        'spotify'          => ['label' => 'Spotify',             'icon' => 'fa-music',                      'category' => 'music'],
        'apple_music'      => ['label' => 'Apple Music',         'icon' => 'fa-apple-alt',                  'category' => 'music'],
        'soundcloud'       => ['label' => 'SoundCloud',          'icon' => 'fa-cloud',                      'category' => 'music'],
        'tidal'            => ['label' => 'Tidal',               'icon' => 'fa-water',                      'category' => 'music'],
        'mixcloud'         => ['label' => 'Mixcloud',            'icon' => 'fa-headphones',                 'category' => 'music'],
        'anchor_fm'        => ['label' => 'Anchor / Podcast',    'icon' => 'fa-podcast',                    'category' => 'music'],

        // ── Video Platforms ───────────────────────────────────────────
        'youtube'          => ['label' => 'YouTube',             'icon' => 'fa-play-circle',                'category' => 'video_platforms'],
        'youtube_feed'     => ['label' => 'YouTube Feed',        'icon' => 'fa-th-list',                    'category' => 'video_platforms'],
        'vimeo'            => ['label' => 'Vimeo',               'icon' => 'fa-play',                       'category' => 'video_platforms'],
        'twitch'           => ['label' => 'Twitch',              'icon' => 'fa-gamepad',                    'category' => 'video_platforms'],
        'kick'             => ['label' => 'Kick',                'icon' => 'fa-bolt',                       'category' => 'video_platforms'],
        'rumble_video'     => ['label' => 'Rumble',              'icon' => 'fa-video',                      'category' => 'video_platforms'],
        'vk_video'         => ['label' => 'VK Video',            'icon' => 'fa-play-circle',                'category' => 'video_platforms'],

        // ── Widgets ───────────────────────────────────────────────────
        'cta_button'       => ['label' => 'Call-to-Action',      'icon' => 'fa-hand-pointer',               'category' => 'utility'],
        'countdown'        => ['label' => 'Countdown Timer',     'icon' => 'fa-clock',                      'category' => 'utility'],
        'progress'         => ['label' => 'Progress Bar',        'icon' => 'fa-tasks',                      'category' => 'utility'],
        'chart_pie'        => ['label' => 'Pie Chart',           'icon' => 'fa-chart-pie',                  'category' => 'utility'],
        'qr_code'          => ['label' => 'QR Code',             'icon' => 'fa-qrcode',                     'category' => 'utility'],
        'share'            => ['label' => 'Share Buttons',       'icon' => 'fa-share-square',               'category' => 'utility'],
        'notification'     => ['label' => 'Notification Bar',    'icon' => 'fa-bell',                       'category' => 'utility'],
        'nav_menu'         => ['label' => 'Navigation Menu',     'icon' => 'fa-bars',                       'category' => 'utility'],
        'ticker'           => ['label' => 'News Ticker',         'icon' => 'fa-scroll',                     'category' => 'utility'],

        // ── Maps ──────────────────────────────────────────────────────
        'map'              => ['label' => 'Google Map',          'icon' => 'fa-map-marker-alt',             'category' => 'maps'],
        'yandex_maps'      => ['label' => 'Yandex Map',          'icon' => 'fa-map',                        'category' => 'maps'],

        // ── Embeds & Integrations ─────────────────────────────────────
        'form'             => ['label' => '1INME Form',          'icon' => 'fa-wpforms',                    'category' => 'integrations'],
        'typeform'         => ['label' => 'Typeform',            'icon' => 'fa-clipboard-list',             'category' => 'integrations'],
        'calendly'         => ['label' => 'Calendly',            'icon' => 'fa-calendar-check',             'category' => 'integrations'],
        'discord_server'   => ['label' => 'Discord Server',      'icon' => 'fa-hashtag',                    'category' => 'integrations'],
        'facebook_post'    => ['label' => 'Facebook Post',       'icon' => 'fa-thumbs-up',                  'category' => 'integrations'],
        'reddit_post'      => ['label' => 'Reddit Post',         'icon' => 'fa-comment-alt',                'category' => 'integrations'],
        'telegram_post'    => ['label' => 'Telegram Post',       'icon' => 'fa-paper-plane',                'category' => 'integrations'],
        'iframe_embed'     => ['label' => 'Embed (iframe)',      'icon' => 'fa-window-maximize',            'category' => 'integrations'],
        'custom_html'      => ['label' => 'Custom HTML',         'icon' => 'fa-code',                       'category' => 'integrations'],
        'external_item'    => ['label' => 'External Link Card',  'icon' => 'fa-external-link-alt',          'category' => 'integrations'],

        // ── Identity ──────────────────────────────────────────────────
        'vcard'            => ['label' => 'Contact (vCard)',     'icon' => 'fa-address-book',               'category' => 'identity'],
        'avatar'           => ['label' => 'Avatar',              'icon' => 'fa-user-circle',                'category' => 'identity'],

        // ── System / Verified (hidden from gallery) ───────────────────
        'verified_heading' => ['label' => 'Verified Heading',    'icon' => 'fa-check-circle',               'category' => 'verified', 'system' => true],
        'verified_avatar'  => ['label' => 'Verified Avatar',     'icon' => 'fa-user-check',                 'category' => 'verified', 'system' => true],
    ];

    public const STYLE_DEFAULTS = [
        'font_family' => '',
        'font_size' => '',
        'font_weight' => '',
        'font_style' => 'normal',
        'text_color' => '',
        'bg_color' => '',
        'bg_image' => '',
        'bg_opacity' => 100,
        'border_color' => '',
        'border_radius' => '',
        'border_width' => '',
        'border_style' => 'none',
        'shadow_type' => 'none',
        'shadow_color' => '#00000040',
        'shadow_x' => 0,
        'shadow_y' => 4,
        'shadow_blur' => 12,
        'shadow_spread' => 0,
        'display_mode' => 'card',
        'effect' => 'none',
        'glass_blur' => 20,
        'glass_opacity' => 15,
        'padding' => '',
        'padding_top' => '',
        'padding_bottom' => '',
        'padding_left' => '',
        'padding_right' => '',
        'margin_top' => '',
        'margin_bottom' => '',
        'margin_left' => '',
        'margin_right' => '',
        'grid_span' => 12,
        '_template' => '',
    ];

    public const BLOCK_TEMPLATES = [
        'minimal' => [
            'label' => 'Minimal',
            'icon' => 'fa-feather',
            'preview_bg' => '#ffffff',
            'preview_text' => '#111',
            'style' => [
                'bg_color' => 'transparent', 'border_style' => 'none', 'shadow_type' => 'none',
                'display_mode' => 'content', 'effect' => 'none', 'border_radius' => '0',
            ],
        ],
        'clean_card' => [
            'label' => 'Clean Card',
            'icon' => 'fa-square',
            'preview_bg' => '#ffffff',
            'preview_text' => '#333',
            'style' => [
                'bg_color' => '#ffffff0d', 'border_style' => 'solid', 'border_width' => '1',
                'border_color' => '#ffffff15', 'border_radius' => '12', 'shadow_type' => 'soft',
                'shadow_color' => '#00000020', 'shadow_y' => '4', 'shadow_blur' => '16',
                'display_mode' => 'card', 'effect' => 'none', 'padding' => '16',
            ],
        ],
        'glassmorphism' => [
            'label' => 'Glassmorphism',
            'icon' => 'fa-gem',
            'preview_bg' => 'rgba(255,255,255,0.05)',
            'preview_text' => '#fff',
            'style' => [
                'bg_color' => '#ffffff0a', 'border_style' => 'solid', 'border_width' => '1',
                'border_color' => '#ffffff12', 'border_radius' => '16', 'shadow_type' => 'glow',
                'shadow_color' => '#8b5cf620', 'display_mode' => 'card', 'effect' => 'glass',
                'glass_blur' => '20', 'glass_opacity' => '10', 'padding' => '20',
            ],
        ],
        'neon_glow' => [
            'label' => 'Neon Glow',
            'icon' => 'fa-bolt',
            'preview_bg' => '#0a0a0a',
            'preview_text' => '#8b5cf6',
            'style' => [
                'bg_color' => '#0a0a0a', 'border_style' => 'solid', 'border_width' => '1',
                'border_color' => '#8b5cf6', 'border_radius' => '12', 'shadow_type' => 'neon',
                'shadow_color' => '#8b5cf660', 'shadow_blur' => '20', 'display_mode' => 'card',
                'effect' => 'none', 'text_color' => '#8b5cf6', 'padding' => '16',
            ],
        ],
        'gradient_border' => [
            'label' => 'Gradient Border',
            'icon' => 'fa-palette',
            'preview_bg' => '#111',
            'preview_text' => '#fff',
            'style' => [
                'bg_color' => '#111111', 'border_style' => 'solid', 'border_width' => '2',
                'border_color' => '#8b5cf6', 'border_radius' => '16', 'shadow_type' => 'soft',
                'shadow_color' => '#8b5cf620', 'display_mode' => 'card', 'effect' => 'gradient_border',
                'padding' => '18',
            ],
        ],
        'bold_solid' => [
            'label' => 'Bold Solid',
            'icon' => 'fa-stop',
            'preview_bg' => '#8b5cf6',
            'preview_text' => '#fff',
            'style' => [
                'bg_color' => '#8b5cf6', 'border_style' => 'none', 'border_radius' => '16',
                'shadow_type' => 'hard', 'shadow_color' => '#00000040', 'shadow_y' => '6',
                'shadow_blur' => '24', 'display_mode' => 'card', 'effect' => 'none',
                'text_color' => '#ffffff', 'padding' => '20',
            ],
        ],
        'frosted' => [
            'label' => 'Frosted',
            'icon' => 'fa-snowflake',
            'preview_bg' => 'rgba(255,255,255,0.08)',
            'preview_text' => '#eee',
            'style' => [
                'bg_color' => '#ffffff14', 'border_style' => 'solid', 'border_width' => '1',
                'border_color' => '#ffffff20', 'border_radius' => '20', 'shadow_type' => 'soft',
                'display_mode' => 'card', 'effect' => 'glass', 'glass_blur' => '30',
                'glass_opacity' => '8', 'padding' => '24',
            ],
        ],
        'outlined' => [
            'label' => 'Outlined',
            'icon' => 'fa-border-all',
            'preview_bg' => 'transparent',
            'preview_text' => '#fff',
            'style' => [
                'bg_color' => 'transparent', 'border_style' => 'solid', 'border_width' => '2',
                'border_color' => '#ffffff30', 'border_radius' => '12', 'shadow_type' => 'none',
                'display_mode' => 'card', 'effect' => 'none', 'padding' => '16',
            ],
        ],
        'neumorphic' => [
            'label' => 'Neumorphic',
            'icon' => 'fa-circle',
            'preview_bg' => '#1a1a2e',
            'preview_text' => '#ccc',
            'style' => [
                'bg_color' => '#1a1a2e', 'border_style' => 'none', 'border_radius' => '20',
                'shadow_type' => 'neumorphic', 'display_mode' => 'card', 'effect' => 'none',
                'padding' => '20',
            ],
        ],
        'pill' => [
            'label' => 'Pill',
            'icon' => 'fa-capsules',
            'preview_bg' => '#ffffff10',
            'preview_text' => '#fff',
            'style' => [
                'bg_color' => '#ffffff10', 'border_style' => 'solid', 'border_width' => '1',
                'border_color' => '#ffffff15', 'border_radius' => '999', 'shadow_type' => 'none',
                'display_mode' => 'card', 'effect' => 'none', 'padding' => '12',
            ],
        ],
    ];

    public static function getBlockStyle(array $blockSettings, array $globalTheme = []): array
    {
        $style = self::STYLE_DEFAULTS;
        if (!empty($globalTheme) && ($globalTheme['apply_to_all'] ?? false)) {
            $style = array_merge($style, array_filter($globalTheme, fn($v) => $v !== '' && $v !== null));
        }
        $blockStyle = $blockSettings['_style'] ?? [];
        if (!empty($blockStyle)) {
            $style = array_merge($style, array_filter($blockStyle, fn($v) => $v !== '' && $v !== null));
        }
        unset($style['apply_to_all']);
        return $style;
    }

    public static function buildInlineStyle(array $style): string
    {
        $css = [];
        if (!empty($style['font_family'])) $css[] = "font-family:'{$style['font_family']}',sans-serif";
        if (!empty($style['font_size'])) $css[] = "font-size:{$style['font_size']}px";
        if (!empty($style['font_weight'])) $css[] = "font-weight:{$style['font_weight']}";
        if (($style['font_style'] ?? 'normal') !== 'normal') $css[] = "font-style:{$style['font_style']}";
        if (!empty($style['text_color'])) $css[] = "color:{$style['text_color']}";

        $hasIndividualPadding = !empty($style['padding_top']) || !empty($style['padding_bottom']) || !empty($style['padding_left']) || !empty($style['padding_right']);
        if ($hasIndividualPadding) {
            $pt = !empty($style['padding_top']) ? $style['padding_top'] . 'px' : (!empty($style['padding']) ? $style['padding'] . 'px' : '');
            $pb = !empty($style['padding_bottom']) ? $style['padding_bottom'] . 'px' : (!empty($style['padding']) ? $style['padding'] . 'px' : '');
            $pl = !empty($style['padding_left']) ? $style['padding_left'] . 'px' : (!empty($style['padding']) ? $style['padding'] . 'px' : '');
            $pr = !empty($style['padding_right']) ? $style['padding_right'] . 'px' : (!empty($style['padding']) ? $style['padding'] . 'px' : '');
            if ($pt) $css[] = "padding-top:{$pt}";
            if ($pb) $css[] = "padding-bottom:{$pb}";
            if ($pl) $css[] = "padding-left:{$pl}";
            if ($pr) $css[] = "padding-right:{$pr}";
        } elseif (!empty($style['padding'])) {
            $css[] = "padding:{$style['padding']}px";
        }

        $hasMargin = !empty($style['margin_top']) || !empty($style['margin_bottom']) || !empty($style['margin_left']) || !empty($style['margin_right']);
        if ($hasMargin) {
            if (!empty($style['margin_top'])) $css[] = "margin-top:{$style['margin_top']}px";
            if (!empty($style['margin_bottom'])) $css[] = "margin-bottom:{$style['margin_bottom']}px";
            if (!empty($style['margin_left'])) $css[] = "margin-left:{$style['margin_left']}px";
            if (!empty($style['margin_right'])) $css[] = "margin-right:{$style['margin_right']}px";
        }

        if (($style['display_mode'] ?? 'card') === 'card') {
            $bgOpacity = ($style['bg_opacity'] ?? 100) / 100;
            if (!empty($style['bg_color']) && $style['bg_color'] !== 'transparent') {
                if ($bgOpacity < 1 && preg_match('/^#([0-9a-fA-F]{6})$/', $style['bg_color'], $m)) {
                    $r = hexdec(substr($m[1], 0, 2));
                    $g = hexdec(substr($m[1], 2, 2));
                    $b = hexdec(substr($m[1], 4, 2));
                    $css[] = "background-color:rgba({$r},{$g},{$b},{$bgOpacity})";
                } else {
                    $css[] = "background-color:{$style['bg_color']}";
                }
            } elseif (($style['bg_color'] ?? '') === 'transparent') {
                $css[] = "background:transparent";
            }
            if (!empty($style['border_radius'])) $css[] = "border-radius:{$style['border_radius']}px";
            if (($style['border_style'] ?? 'none') !== 'none' && !empty($style['border_width'])) {
                $css[] = "border:{$style['border_width']}px {$style['border_style']} {$style['border_color']}";
            }
            if (($style['effect'] ?? 'none') === 'glass') {
                $blur = $style['glass_blur'] ?? 20;
                $glassOp = ($style['glass_opacity'] ?? 15) / 100;
                $css[] = "backdrop-filter:blur({$blur}px) saturate(1.2)";
                $css[] = "-webkit-backdrop-filter:blur({$blur}px) saturate(1.2)";
                if (empty($style['bg_color']) || $style['bg_color'] === 'transparent') {
                    $css[] = "background-color:rgba(255,255,255,{$glassOp})";
                }
            } elseif (($style['effect'] ?? 'none') === 'gradient_border') {
                $css[] = "border-image:linear-gradient(135deg, #8b5cf6, #ec4899, #8b5cf6) 1";
                $css[] = "border-style:solid";
                if (empty($style['border_width'])) $css[] = "border-width:2px";
            }
            $shadow = self::buildShadow($style);
            if ($shadow) $css[] = "box-shadow:{$shadow}";
        }

        if (!empty($style['bg_image']) && preg_match('/^https?:\/\//', $style['bg_image'])) {
            $css[] = "background-image:url('" . str_replace("'", '', $style['bg_image']) . "')";
            $css[] = "background-size:cover";
            $css[] = "background-position:center";
        }

        return implode(';', $css);
    }

    public static function buildShadow(array $style): string
    {
        $type = $style['shadow_type'] ?? 'none';
        if ($type === 'none') return '';
        $color = $style['shadow_color'] ?? '#00000040';
        $x = $style['shadow_x'] ?? 0;
        $y = $style['shadow_y'] ?? 4;
        $blur = $style['shadow_blur'] ?? 12;
        $spread = $style['shadow_spread'] ?? 0;
        return match($type) {
            'soft' => "{$x}px {$y}px {$blur}px {$spread}px {$color}",
            'hard' => "{$x}px {$y}px 0px {$spread}px {$color}",
            'neon' => "0 0 {$blur}px {$color}, 0 0 " . ($blur * 2) . "px {$color}",
            'glow' => "0 0 {$blur}px {$color}",
            'neumorphic' => "8px 8px 16px #00000040, -8px -8px 16px #ffffff08",
            'inset' => "inset {$x}px {$y}px {$blur}px {$color}",
            default => '',
        };
    }

    public const IMAGE_STYLE_DEFAULTS = [
        'mask_shape' => 'none',
        'border_radius' => '',
        'object_fit' => 'cover',
        'border_style' => 'none',
        'border_width' => '',
        'border_color' => '',
        'shadow_type' => 'none',
        'shadow_color' => '#00000040',
        'shadow_x' => 0,
        'shadow_y' => 4,
        'shadow_blur' => 12,
        'shadow_spread' => 0,
    ];

    public const MASK_CLIP_PATHS = [
        'circle' => 'circle(50% at 50% 50%)',
        'diamond' => 'polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%)',
        'hexagon' => 'polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%)',
        'octagon' => 'polygon(29.3% 0%, 70.7% 0%, 100% 29.3%, 100% 70.7%, 70.7% 100%, 29.3% 100%, 0% 70.7%, 0% 29.3%)',
        'star' => 'polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%)',
        'blob' => 'polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%)',
        'arch' => 'polygon(0% 100%, 0% 30%, 5% 15%, 15% 5%, 30% 0%, 70% 0%, 85% 5%, 95% 15%, 100% 30%, 100% 100%)',
    ];

    public static function buildImageInlineStyle(array $imgStyle): string
    {
        $css = [];

        $shape = $imgStyle['mask_shape'] ?? 'none';
        if ($shape === 'circle') {
            $css[] = 'border-radius:50%';
            $css[] = 'clip-path:circle(50% at 50% 50%)';
        } elseif ($shape === 'rounded') {
            $css[] = 'border-radius:20px';
        } elseif ($shape === 'square') {
            $css[] = 'border-radius:0';
        } elseif (isset(self::MASK_CLIP_PATHS[$shape])) {
            $css[] = 'clip-path:' . self::MASK_CLIP_PATHS[$shape];
        }

        if ($shape === 'none' && !empty($imgStyle['border_radius'])) {
            $css[] = "border-radius:{$imgStyle['border_radius']}px";
        }

        if (!empty($imgStyle['object_fit']) && $imgStyle['object_fit'] !== 'cover') {
            $css[] = "object-fit:{$imgStyle['object_fit']}";
        }

        if (($imgStyle['border_style'] ?? 'none') !== 'none' && !empty($imgStyle['border_width'])) {
            $css[] = "border:{$imgStyle['border_width']}px {$imgStyle['border_style']} {$imgStyle['border_color']}";
        }

        $shadowType = $imgStyle['shadow_type'] ?? 'none';
        if ($shadowType !== 'none') {
            $color = $imgStyle['shadow_color'] ?? '#00000040';
            $x = $imgStyle['shadow_x'] ?? 0;
            $y = $imgStyle['shadow_y'] ?? 4;
            $blur = $imgStyle['shadow_blur'] ?? 12;
            $spread = $imgStyle['shadow_spread'] ?? 0;

            if ($shadowType === 'drop') {
                $css[] = "filter:drop-shadow({$x}px {$y}px {$blur}px {$color})";
            } else {
                $shadow = match($shadowType) {
                    'soft' => "{$x}px {$y}px {$blur}px {$spread}px {$color}",
                    'hard' => "{$x}px {$y}px 0px {$spread}px {$color}",
                    'neon' => "0 0 {$blur}px {$color}, 0 0 " . ($blur * 2) . "px {$color}",
                    'glow' => "0 0 {$blur}px {$color}",
                    default => '',
                };
                if ($shadow) $css[] = "box-shadow:{$shadow}";
            }
        }

        return implode(';', $css);
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function activeChildren()
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function isVisible(): bool
    {
        if (!$this->is_active) return false;
        if ($this->start_date && $this->start_date->isFuture()) return false;
        if ($this->end_date && $this->end_date->isPast()) return false;

        $v = $this->settings['_visibility'] ?? [];
        if (empty($v)) return true;

        $request = request();

        if (!empty($v['continents'])) {
            $continent = self::countryToContinent(self::detectCountry($request));
            if ($continent && !in_array($continent, $v['continents'])) return false;
        }

        if (!empty($v['countries'])) {
            $country = self::detectCountry($request);
            if ($country && !in_array($country, $v['countries'])) return false;
        }

        if (!empty($v['cities'])) {
            $city = $request->header('X-City', $request->header('CF-IPCity', ''));
            if ($city && !in_array(strtolower($city), array_map('strtolower', $v['cities']))) return false;
        }

        if (!empty($v['devices'])) {
            $device = self::detectDevice($request);
            if ($device && !in_array($device, $v['devices'])) return false;
        }

        if (!empty($v['os'])) {
            $os = self::detectOS($request);
            if ($os && !in_array($os, $v['os'])) return false;
        }

        if (!empty($v['browsers'])) {
            $browser = self::detectBrowser($request);
            if ($browser && !in_array($browser, $v['browsers'])) return false;
        }

        if (!empty($v['languages'])) {
            $lang = self::detectLanguage($request);
            if ($lang && !in_array($lang, $v['languages'])) return false;
        }

        if (!empty($v['time_slots']) && is_array($v['time_slots'])) {
            if (!self::matchesTimeSlot($v['time_slots'])) return false;
        }

        return true;
    }

    /**
     * Returns true if "now" (in the app timezone) falls inside at least one
     * configured slot. Each slot = [days => [mon..sun], start => HH:MM, end => HH:MM].
     * Across-midnight ranges (start > end) are treated as wrapping into the next day.
     */
    public static function matchesTimeSlot(array $slots): bool
    {
        try {
            $tz = config('app.timezone') ?: 'UTC';
            $now = new \DateTimeImmutable('now', new \DateTimeZone($tz));
        } catch (\Throwable $e) {
            return true; // fail-open on bad tz
        }

        $dayMap = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];
        $today = $dayMap[(int)$now->format('N')] ?? '';
        $yesterday = $dayMap[(int)$now->modify('-1 day')->format('N')] ?? '';
        $minutesNow = ((int)$now->format('G')) * 60 + (int)$now->format('i');

        foreach ($slots as $slot) {
            if (!is_array($slot)) continue;
            $days = (array)($slot['days'] ?? []);
            if (empty($days)) continue;
            $start = $slot['start'] ?? '';
            $end   = $slot['end']   ?? '';
            if (!preg_match('/^(\d{2}):(\d{2})$/', $start, $sM) || !preg_match('/^(\d{2}):(\d{2})$/', $end, $eM)) continue;
            $startMin = ((int)$sM[1]) * 60 + (int)$sM[2];
            $endMin   = ((int)$eM[1]) * 60 + (int)$eM[2];

            if ($startMin <= $endMin) {
                // Same-day window
                if (in_array($today, $days, true) && $minutesNow >= $startMin && $minutesNow < $endMin) {
                    return true;
                }
            } else {
                // Wraps midnight: [start..24:00) on chosen day OR [00:00..end) on the next day
                if (in_array($today, $days, true) && $minutesNow >= $startMin) return true;
                if (in_array($yesterday, $days, true) && $minutesNow < $endMin) return true;
            }
        }
        return false;
    }

    public static function detectCountry($request): string
    {
        return strtoupper($request->header('CF-IPCountry', $request->header('X-Country', '')));
    }

    public static function detectDevice($request): string
    {
        $ua = strtolower($request->userAgent() ?? '');
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) return 'tablet';
        if (preg_match('/mobile|android|iphone|ipod|opera mini|iemobile/i', $ua)) return 'mobile';
        return 'desktop';
    }

    public static function detectOS($request): string
    {
        $ua = $request->userAgent() ?? '';
        if (preg_match('/windows/i', $ua)) return 'Windows';
        if (preg_match('/macintosh|mac os/i', $ua)) return 'OS X';
        if (preg_match('/android/i', $ua)) return 'Android';
        if (preg_match('/iphone|ipad|ipod/i', $ua)) return 'iOS';
        if (preg_match('/linux/i', $ua)) return 'Linux';
        if (preg_match('/cros/i', $ua)) return 'Chrome OS';
        return '';
    }

    public static function detectBrowser($request): string
    {
        $ua = $request->userAgent() ?? '';
        if (preg_match('/edg\//i', $ua)) return 'Edge';
        if (preg_match('/opr\/|opera/i', $ua)) return 'Opera';
        if (preg_match('/brave/i', $ua)) return 'Brave';
        if (preg_match('/vivaldi/i', $ua)) return 'Vivaldi';
        if (preg_match('/chrome/i', $ua)) return 'Chrome';
        if (preg_match('/firefox/i', $ua)) return 'Firefox';
        if (preg_match('/safari/i', $ua) && !preg_match('/chrome/i', $ua)) return 'Safari';
        if (preg_match('/msie|trident/i', $ua)) return 'Internet Explorer';
        return '';
    }

    public static function detectLanguage($request): string
    {
        $accept = $request->header('Accept-Language', '');
        if (preg_match('/^([a-z]{2,3})/i', $accept, $m)) return strtolower($m[1]);
        return '';
    }

    public static function countryToContinent(string $countryCode): string
    {
        $map = [
            'AF' => 'Asia', 'AX' => 'Europe', 'AL' => 'Europe', 'DZ' => 'Africa', 'AS' => 'Oceania',
            'AD' => 'Europe', 'AO' => 'Africa', 'AG' => 'North America', 'AR' => 'South America',
            'AM' => 'Asia', 'AU' => 'Oceania', 'AT' => 'Europe', 'AZ' => 'Asia', 'BS' => 'North America',
            'BH' => 'Asia', 'BD' => 'Asia', 'BB' => 'North America', 'BY' => 'Europe', 'BE' => 'Europe',
            'BZ' => 'North America', 'BJ' => 'Africa', 'BT' => 'Asia', 'BO' => 'South America',
            'BA' => 'Europe', 'BW' => 'Africa', 'BR' => 'South America', 'BN' => 'Asia',
            'BG' => 'Europe', 'BF' => 'Africa', 'BI' => 'Africa', 'KH' => 'Asia', 'CM' => 'Africa',
            'CA' => 'North America', 'CV' => 'Africa', 'CF' => 'Africa', 'TD' => 'Africa',
            'CL' => 'South America', 'CN' => 'Asia', 'CO' => 'South America', 'KM' => 'Africa',
            'CG' => 'Africa', 'CD' => 'Africa', 'CR' => 'North America', 'CI' => 'Africa',
            'HR' => 'Europe', 'CU' => 'North America', 'CY' => 'Europe', 'CZ' => 'Europe',
            'DK' => 'Europe', 'DJ' => 'Africa', 'DM' => 'North America', 'DO' => 'North America',
            'EC' => 'South America', 'EG' => 'Africa', 'SV' => 'North America', 'GQ' => 'Africa',
            'ER' => 'Africa', 'EE' => 'Europe', 'ET' => 'Africa', 'FJ' => 'Oceania',
            'FI' => 'Europe', 'FR' => 'Europe', 'GA' => 'Africa', 'GM' => 'Africa',
            'GE' => 'Asia', 'DE' => 'Europe', 'GH' => 'Africa', 'GR' => 'Europe',
            'GD' => 'North America', 'GT' => 'North America', 'GN' => 'Africa', 'GW' => 'Africa',
            'GY' => 'South America', 'HT' => 'North America', 'HN' => 'North America',
            'HU' => 'Europe', 'IS' => 'Europe', 'IN' => 'Asia', 'ID' => 'Asia', 'IR' => 'Asia',
            'IQ' => 'Asia', 'IE' => 'Europe', 'IL' => 'Asia', 'IT' => 'Europe', 'JM' => 'North America',
            'JP' => 'Asia', 'JO' => 'Asia', 'KZ' => 'Asia', 'KE' => 'Africa', 'KI' => 'Oceania',
            'KP' => 'Asia', 'KR' => 'Asia', 'KW' => 'Asia', 'KG' => 'Asia', 'LA' => 'Asia',
            'LV' => 'Europe', 'LB' => 'Asia', 'LS' => 'Africa', 'LR' => 'Africa', 'LY' => 'Africa',
            'LI' => 'Europe', 'LT' => 'Europe', 'LU' => 'Europe', 'MK' => 'Europe', 'MG' => 'Africa',
            'MW' => 'Africa', 'MY' => 'Asia', 'MV' => 'Asia', 'ML' => 'Africa', 'MT' => 'Europe',
            'MH' => 'Oceania', 'MR' => 'Africa', 'MU' => 'Africa', 'MX' => 'North America',
            'FM' => 'Oceania', 'MD' => 'Europe', 'MC' => 'Europe', 'MN' => 'Asia', 'ME' => 'Europe',
            'MA' => 'Africa', 'MZ' => 'Africa', 'MM' => 'Asia', 'NA' => 'Africa', 'NR' => 'Oceania',
            'NP' => 'Asia', 'NL' => 'Europe', 'NZ' => 'Oceania', 'NI' => 'North America',
            'NE' => 'Africa', 'NG' => 'Africa', 'NO' => 'Europe', 'OM' => 'Asia', 'PK' => 'Asia',
            'PW' => 'Oceania', 'PA' => 'North America', 'PG' => 'Oceania', 'PY' => 'South America',
            'PE' => 'South America', 'PH' => 'Asia', 'PL' => 'Europe', 'PT' => 'Europe',
            'QA' => 'Asia', 'RO' => 'Europe', 'RU' => 'Europe', 'RW' => 'Africa', 'KN' => 'North America',
            'LC' => 'North America', 'VC' => 'North America', 'WS' => 'Oceania', 'SM' => 'Europe',
            'ST' => 'Africa', 'SA' => 'Asia', 'SN' => 'Africa', 'RS' => 'Europe', 'SC' => 'Africa',
            'SL' => 'Africa', 'SG' => 'Asia', 'SK' => 'Europe', 'SI' => 'Europe', 'SB' => 'Oceania',
            'SO' => 'Africa', 'ZA' => 'Africa', 'ES' => 'Europe', 'LK' => 'Asia', 'SD' => 'Africa',
            'SR' => 'South America', 'SZ' => 'Africa', 'SE' => 'Europe', 'CH' => 'Europe',
            'SY' => 'Asia', 'TW' => 'Asia', 'TJ' => 'Asia', 'TZ' => 'Africa', 'TH' => 'Asia',
            'TL' => 'Asia', 'TG' => 'Africa', 'TO' => 'Oceania', 'TT' => 'North America',
            'TN' => 'Africa', 'TR' => 'Asia', 'TM' => 'Asia', 'TV' => 'Oceania', 'UG' => 'Africa',
            'UA' => 'Europe', 'AE' => 'Asia', 'GB' => 'Europe', 'US' => 'North America',
            'UY' => 'South America', 'UZ' => 'Asia', 'VU' => 'Oceania', 'VE' => 'South America',
            'VN' => 'Asia', 'YE' => 'Asia', 'ZM' => 'Africa', 'ZW' => 'Africa',
        ];
        return $map[$countryCode] ?? '';
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}
