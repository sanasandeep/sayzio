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
        return true;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}
