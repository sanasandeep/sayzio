<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class BiolinkBlock extends Model
{
    protected $fillable = [
        'link_id', 'type', 'settings', 'sort_order', 'is_active',
        'start_date', 'end_date',
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
        'basic' => 'Basic Content',
        'media' => 'Media',
        'social' => 'Social & Profiles',
        'music' => 'Music & Streaming',
        'video_platforms' => 'Video Platforms',
        'contact' => 'Contact & Lead Generation',
        'interactive' => 'Interactive & Engagement',
        'business' => 'Business & Monetization',
        'utility' => 'Utility & Functional',
        'layout' => 'Layout & Design',
        'integrations' => 'Integrations & Embeds',
        'files' => 'Files & External Content',
        'maps' => 'Maps & Location',
        'identity' => 'Digital Identity',
    ];

    public const TYPES = [
        'link' => ['label' => 'Link', 'icon' => 'fa-link', 'category' => 'basic'],
        'link_big' => ['label' => 'Link (Big Size)', 'icon' => 'fa-external-link-square-alt', 'category' => 'basic'],
        'heading' => ['label' => 'Heading', 'icon' => 'fa-heading', 'category' => 'basic'],
        'heading_gradient' => ['label' => 'Heading (Gradient)', 'icon' => 'fa-font', 'category' => 'basic'],
        'heading_logo' => ['label' => 'Heading (Logo)', 'icon' => 'fa-image', 'category' => 'basic'],
        'heading_morph' => ['label' => 'Heading (Morph)', 'icon' => 'fa-magic', 'category' => 'basic'],
        'paragraph' => ['label' => 'Paragraph', 'icon' => 'fa-paragraph', 'category' => 'basic'],
        'paragraph_rich' => ['label' => 'Paragraph (Rich Text)', 'icon' => 'fa-align-left', 'category' => 'basic'],
        'divider' => ['label' => 'Divider', 'icon' => 'fa-minus', 'category' => 'basic'],
        'list' => ['label' => 'List', 'icon' => 'fa-list-ul', 'category' => 'basic'],
        'list_numbered' => ['label' => 'List (Numbered)', 'icon' => 'fa-list-ol', 'category' => 'basic'],
        'list_pricing' => ['label' => 'List (Pricing)', 'icon' => 'fa-tags', 'category' => 'basic'],
        'alert' => ['label' => 'Alert', 'icon' => 'fa-exclamation-triangle', 'category' => 'basic'],
        'badge' => ['label' => 'Badge', 'icon' => 'fa-certificate', 'category' => 'basic'],

        'image' => ['label' => 'Image', 'icon' => 'fa-image', 'category' => 'media'],
        'image_grid' => ['label' => 'Image Grid', 'icon' => 'fa-th', 'category' => 'media'],
        'image_slider' => ['label' => 'Image Slider', 'icon' => 'fa-images', 'category' => 'media'],
        'image_slider_v2' => ['label' => 'Image Slider V2', 'icon' => 'fa-photo-video', 'category' => 'media'],
        'header_video' => ['label' => 'Header Video', 'icon' => 'fa-film', 'category' => 'media'],
        'video' => ['label' => 'Video', 'icon' => 'fa-video', 'category' => 'media'],
        'audio' => ['label' => 'Audio', 'icon' => 'fa-music', 'category' => 'media'],
        'pdf_document' => ['label' => 'PDF Document', 'icon' => 'fa-file-pdf', 'category' => 'media'],
        'powerpoint' => ['label' => 'PowerPoint', 'icon' => 'fa-file-powerpoint', 'category' => 'media'],
        'excel' => ['label' => 'Excel Spreadsheet', 'icon' => 'fa-file-excel', 'category' => 'media'],

        'socials' => ['label' => 'Socials', 'icon' => 'fa-share-alt', 'category' => 'social'],
        'socials_multi' => ['label' => 'Socials (Multi)', 'icon' => 'fa-users', 'category' => 'social'],
        'socials_custom' => ['label' => 'Socials (Custom)', 'icon' => 'fa-paint-brush', 'category' => 'social'],
        'instagram_media' => ['label' => 'Instagram Media', 'icon' => 'fa-instagram', 'category' => 'social'],
        'tiktok_video' => ['label' => 'TikTok Video', 'icon' => 'fa-music', 'category' => 'social'],
        'tiktok_profile' => ['label' => 'TikTok Profile', 'icon' => 'fa-user', 'category' => 'social'],
        'twitter_profile' => ['label' => 'Twitter Profile', 'icon' => 'fa-user-circle', 'category' => 'social'],
        'twitter_tweet' => ['label' => 'Twitter Tweet', 'icon' => 'fa-comment', 'category' => 'social'],
        'twitter_video' => ['label' => 'Twitter Video', 'icon' => 'fa-play-circle', 'category' => 'social'],
        'pinterest_profile' => ['label' => 'Pinterest Profile', 'icon' => 'fa-thumbtack', 'category' => 'social'],
        'snapchat' => ['label' => 'Snapchat', 'icon' => 'fa-ghost', 'category' => 'social'],
        'rss_feed' => ['label' => 'RSS Feed', 'icon' => 'fa-rss', 'category' => 'social'],

        'spotify' => ['label' => 'Spotify', 'icon' => 'fa-music', 'category' => 'music'],
        'apple_music' => ['label' => 'Apple Music', 'icon' => 'fa-apple-alt', 'category' => 'music'],
        'soundcloud' => ['label' => 'SoundCloud', 'icon' => 'fa-cloud', 'category' => 'music'],
        'tidal' => ['label' => 'Tidal', 'icon' => 'fa-water', 'category' => 'music'],
        'mixcloud' => ['label' => 'Mixcloud', 'icon' => 'fa-headphones', 'category' => 'music'],
        'anchor_fm' => ['label' => 'Anchor FM', 'icon' => 'fa-podcast', 'category' => 'music'],

        'youtube' => ['label' => 'YouTube', 'icon' => 'fa-play-circle', 'category' => 'video_platforms'],
        'youtube_feed' => ['label' => 'YouTube Feed', 'icon' => 'fa-th-list', 'category' => 'video_platforms'],
        'vimeo' => ['label' => 'Vimeo', 'icon' => 'fa-play', 'category' => 'video_platforms'],
        'twitch' => ['label' => 'Twitch', 'icon' => 'fa-gamepad', 'category' => 'video_platforms'],
        'kick' => ['label' => 'Kick', 'icon' => 'fa-bolt', 'category' => 'video_platforms'],
        'rumble_video' => ['label' => 'Rumble Video', 'icon' => 'fa-video', 'category' => 'video_platforms'],
        'vk_video' => ['label' => 'VK Video', 'icon' => 'fa-play-circle', 'category' => 'video_platforms'],

        'email_collector' => ['label' => 'Email Collector', 'icon' => 'fa-envelope', 'category' => 'contact'],
        'phone_collector' => ['label' => 'Phone Collector', 'icon' => 'fa-phone', 'category' => 'contact'],
        'contact_form' => ['label' => 'Contact Form', 'icon' => 'fa-paper-plane', 'category' => 'contact'],
        'whatsapp_widget' => ['label' => 'WhatsApp Widget', 'icon' => 'fa-comment-dots', 'category' => 'contact'],
        'whatsapp_item' => ['label' => 'WhatsApp Item', 'icon' => 'fa-comments', 'category' => 'contact'],

        'faq' => ['label' => 'FAQ', 'icon' => 'fa-question-circle', 'category' => 'interactive'],
        'faq_v2' => ['label' => 'FAQ V2', 'icon' => 'fa-question', 'category' => 'interactive'],
        'poll' => ['label' => 'Poll', 'icon' => 'fa-poll', 'category' => 'interactive'],
        'quiz' => ['label' => 'Quiz', 'icon' => 'fa-brain', 'category' => 'interactive'],
        'testimonials' => ['label' => 'Testimonials', 'icon' => 'fa-quote-right', 'category' => 'interactive'],
        'review' => ['label' => 'Review', 'icon' => 'fa-star', 'category' => 'interactive'],
        'timeline' => ['label' => 'Timeline', 'icon' => 'fa-stream', 'category' => 'interactive'],
        'timeline_staged' => ['label' => 'Timeline (Staged)', 'icon' => 'fa-project-diagram', 'category' => 'interactive'],

        'product' => ['label' => 'Product', 'icon' => 'fa-box', 'category' => 'business'],
        'service' => ['label' => 'Service', 'icon' => 'fa-concierge-bell', 'category' => 'business'],
        'catalog' => ['label' => 'Catalog', 'icon' => 'fa-book-open', 'category' => 'business'],
        'market' => ['label' => 'Market', 'icon' => 'fa-store', 'category' => 'business'],
        'price' => ['label' => 'Price', 'icon' => 'fa-dollar-sign', 'category' => 'business'],
        'donation' => ['label' => 'Donation', 'icon' => 'fa-hand-holding-heart', 'category' => 'business'],
        'coupon' => ['label' => 'Coupon', 'icon' => 'fa-ticket-alt', 'category' => 'business'],
        'one_time_offer' => ['label' => 'One Time Offer', 'icon' => 'fa-fire', 'category' => 'business'],
        'paypal' => ['label' => 'PayPal', 'icon' => 'fa-credit-card', 'category' => 'business'],

        'countdown' => ['label' => 'Countdown', 'icon' => 'fa-clock', 'category' => 'utility'],
        'progress' => ['label' => 'Progress', 'icon' => 'fa-tasks', 'category' => 'utility'],
        'chart_pie' => ['label' => 'Chart (Pie)', 'icon' => 'fa-chart-pie', 'category' => 'utility'],
        'qr_code' => ['label' => 'QR Code', 'icon' => 'fa-qrcode', 'category' => 'utility'],
        'share' => ['label' => 'Share', 'icon' => 'fa-share-square', 'category' => 'utility'],
        'cta_button' => ['label' => 'Call to Action', 'icon' => 'fa-hand-pointer', 'category' => 'utility'],
        'notification' => ['label' => 'Notification', 'icon' => 'fa-bell', 'category' => 'utility'],
        'nav_menu' => ['label' => 'Navigation Menu', 'icon' => 'fa-bars', 'category' => 'utility'],
        'ticker' => ['label' => 'Ticker', 'icon' => 'fa-scroll', 'category' => 'utility'],

        'card_slider' => ['label' => 'Card Slider', 'icon' => 'fa-clone', 'category' => 'layout'],
        'scroll_cards' => ['label' => 'Scroll Cards', 'icon' => 'fa-columns', 'category' => 'layout'],
        'profile_card_v1' => ['label' => 'Profile Card V1', 'icon' => 'fa-id-card', 'category' => 'layout'],
        'profile_card_v2' => ['label' => 'Profile Card V2', 'icon' => 'fa-id-card-alt', 'category' => 'layout'],
        'profile_card_v3' => ['label' => 'Profile Card V3', 'icon' => 'fa-address-card', 'category' => 'layout'],
        'profile_card_v4' => ['label' => 'Profile Card V4', 'icon' => 'fa-user-tag', 'category' => 'layout'],

        'custom_html' => ['label' => 'Custom HTML', 'icon' => 'fa-code', 'category' => 'integrations'],
        'iframe_embed' => ['label' => 'Iframe Embed', 'icon' => 'fa-window-maximize', 'category' => 'integrations'],
        'typeform' => ['label' => 'Typeform', 'icon' => 'fa-clipboard-list', 'category' => 'integrations'],
        'calendly' => ['label' => 'Calendly', 'icon' => 'fa-calendar-check', 'category' => 'integrations'],
        'discord_server' => ['label' => 'Discord Server', 'icon' => 'fa-hashtag', 'category' => 'integrations'],
        'facebook_post' => ['label' => 'Facebook Post', 'icon' => 'fa-thumbs-up', 'category' => 'integrations'],
        'reddit_post' => ['label' => 'Reddit Post', 'icon' => 'fa-comment-alt', 'category' => 'integrations'],
        'telegram_post' => ['label' => 'Telegram Post', 'icon' => 'fa-paper-plane', 'category' => 'integrations'],

        'file' => ['label' => 'File', 'icon' => 'fa-file-download', 'category' => 'files'],
        'external_item' => ['label' => 'External Item', 'icon' => 'fa-external-link-alt', 'category' => 'files'],
        'markdown' => ['label' => 'Markdown', 'icon' => 'fa-file-alt', 'category' => 'files'],

        'map' => ['label' => 'Google Maps', 'icon' => 'fa-map-marker-alt', 'category' => 'maps'],
        'yandex_maps' => ['label' => 'Yandex Maps', 'icon' => 'fa-map', 'category' => 'maps'],

        'spacer' => ['label' => 'Spacer', 'icon' => 'fa-arrows-alt-v', 'category' => 'layout'],

        'vcard' => ['label' => 'VCard', 'icon' => 'fa-address-book', 'category' => 'identity'],
        'avatar' => ['label' => 'Avatar', 'icon' => 'fa-user-circle', 'category' => 'identity'],
    ];

    public function link()
    {
        return $this->belongsTo(Link::class);
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

        return true;
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
