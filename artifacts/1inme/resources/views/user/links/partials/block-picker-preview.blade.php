{{--
    Block-picker preview thumbnail (Task #1202).

    Renders a small, true-to-life preview of what a block looks like
    when added with seeded defaults from BlockDefaults::seededSettings().
    Lives inside the gallery tile above the icon + label row.

    We intentionally do NOT use common/partials/biolink-block-render
    here — many partials require a live $link, social-account joins, or
    route helpers that aren't safe to invoke from the picker (it would
    hit the database for each of ~80 tiles every time the modal opens).
    Instead we render a curated mini-preview per visual archetype using
    the seeded content directly.

    Inputs:
      $type     — canonical block type slug (string)
      $typeInfo — TYPES entry: ['label', 'icon', 'category']
      $catColor — accent colour for the archetype (hex string)
--}}
@php
    use App\Modules\User\Support\BlockDefaults;
    $s = BlockDefaults::contentForType($type);
    $imgSquare = BlockDefaults::placeholderUrl('image_square');
    $avatar = BlockDefaults::placeholderUrl('avatar');
    // Group every type into a visual archetype so the preview switch
    // stays manageable. ~80 types collapse to ~20 archetypes.
    $archetype = match ($type) {
        // Buttons / links
        'link', 'link_big', 'cta_button', 'featured_pin', 'file', 'external_item' => 'button',
        // Headings
        'heading', 'heading_logo', 'verified_heading' => 'heading',
        // Paragraphs / rich text / markdown / alerts
        'paragraph', 'paragraph_rich', 'markdown', 'alert', 'notification' => 'paragraph',
        'badge' => 'badge',
        // Structure
        'divider' => 'divider',
        'spacer' => 'spacer',
        'card' => 'card',
        // Lists
        'list', 'list_numbered' => 'list',
        'list_pricing', 'price' => 'pricing',
        // Images
        'image', 'avatar', 'verified_avatar' => 'image',
        'image_grid', 'image_slider', 'image_slider_v2', 'scroll_cards', 'card_slider' => 'image_grid',
        // Video
        'video', 'header_video', 'youtube', 'vimeo', 'twitch', 'kick',
        'tiktok_video', 'twitter_video', 'vk_video', 'rumble_video', 'latest_youtube' => 'video',
        // Audio
        'audio', 'audio_list', 'spotify', 'apple_music', 'soundcloud',
        'tidal', 'mixcloud', 'anchor_fm' => 'audio',
        // Documents
        'pdf_document', 'powerpoint', 'excel', 'file_list' => 'document',
        // Socials row
        'socials', 'socials_multi', 'socials_custom' => 'socials',
        // Forms
        'email_collector', 'phone_collector', 'contact_form', 'email_subscribe',
        'whatsapp_widget', 'whatsapp_item', 'whatsapp_channel_subscribe',
        'whatsapp_number_subscribe', 'direct_message', 'donation', 'paypal',
        'buy_me_coffee', 'patreon', 'ko_fi' => 'form',
        // Embeds (generic placeholder)
        'instagram', 'instagram_media', 'twitter_tweet', 'twitter_profile',
        'tiktok_profile', 'snapchat', 'pinterest_profile', 'facebook_post',
        'reddit_post', 'telegram_post', 'discord_server', 'latest_instagram',
        'youtube_feed', 'rss_feed', 'calendly', 'calendly_embed', 'typeform',
        'custom_html', 'iframe_embed' => 'embed',
        // Maps
        'map', 'yandex_maps', 'map_location' => 'map',
        // Profile cards
        'profile_card_v1', 'profile_card_v2', 'profile_card_v3', 'profile_card_v4',
        'vcard', 'resume' => 'profile',
        // Products / commerce
        'product', 'service', 'catalog', 'market', 'coupon',
        'one_time_offer', 'affiliate_links' => 'product',
        // Stats / charts / data
        'stats', 'progress', 'chart_pie' => 'stats',
        'countdown' => 'countdown',
        'qr_code' => 'qr',
        'share' => 'share',
        'poll', 'quiz' => 'poll',
        // Testimonials
        'testimonials', 'testimonial_carousel', 'review' => 'testimonial',
        // Timelines
        'timeline', 'timeline_staged' => 'timeline',
        // Menus
        'menu', 'menu_section' => 'menu',
        // Booking / events
        'booking_slots', 'event_list' => 'event',
        // FAQ / accordion / tabs
        'faq', 'faq_v2', 'accordion' => 'faq',
        'tabs' => 'tabs',
        // Misc
        'ticker' => 'ticker',
        'nav_menu' => 'nav',
        'fan_leaderboard' => 'leaderboard',
        'insider' => 'insider',
        'social_proof' => 'badge_pill',
        'ai_companion' => 'chat',
        'form' => 'form_generic',
        'roadmap' => 'roadmap',
        default => 'generic',
    };
@endphp

<div class="block-preview-thumb" aria-hidden="true">
    @switch($archetype)
        @case('button')
            <div class="bpt-btn" style="background: {{ $catColor }};">
                <i class="fas fa-link"></i>
                <span>{{ \Illuminate\Support\Str::limit($s['text'] ?? $s['title'] ?? 'My Link', 18) }}</span>
            </div>
            @break

        @case('heading')
            <div class="bpt-heading">{{ \Illuminate\Support\Str::limit($s['text'] ?? 'Heading', 22) }}</div>
            <div class="bpt-underline" style="background: {{ $catColor }};"></div>
            @break

        @case('paragraph')
            <div class="bpt-line bpt-line-100"></div>
            <div class="bpt-line bpt-line-90"></div>
            <div class="bpt-line bpt-line-60"></div>
            @break

        @case('badge')
            <span class="bpt-pill" style="background: {{ $catColor }};">{{ \Illuminate\Support\Str::limit($s['text'] ?? 'Badge', 10) }}</span>
            @break

        @case('badge_pill')
            <div class="bpt-pill-row">
                <span class="bpt-pill-sm" style="background: {{ $catColor }}33; color: {{ $catColor }};">★ Featured</span>
                <span class="bpt-pill-sm" style="background: {{ $catColor }}33; color: {{ $catColor }};">↑ Trending</span>
            </div>
            @break

        @case('divider')
            <div class="bpt-divider"></div>
            @break

        @case('spacer')
            <div class="bpt-spacer">↕</div>
            @break

        @case('card')
            <div class="bpt-card">
                <div class="bpt-line bpt-line-60"></div>
                <div class="bpt-line bpt-line-90"></div>
            </div>
            @break

        @case('list')
            <div class="bpt-list-row"><span class="bpt-dot" style="background: {{ $catColor }};"></span><div class="bpt-line bpt-line-80"></div></div>
            <div class="bpt-list-row"><span class="bpt-dot" style="background: {{ $catColor }};"></span><div class="bpt-line bpt-line-60"></div></div>
            <div class="bpt-list-row"><span class="bpt-dot" style="background: {{ $catColor }};"></span><div class="bpt-line bpt-line-90"></div></div>
            @break

        @case('pricing')
            <div class="bpt-pricing">
                <div class="bpt-price-col"><div class="bpt-mini-num">$9</div><div class="bpt-line bpt-line-60"></div></div>
                <div class="bpt-price-col bpt-featured" style="border-color: {{ $catColor }}88;"><div class="bpt-mini-num">$29</div><div class="bpt-line bpt-line-60"></div></div>
                <div class="bpt-price-col"><div class="bpt-mini-num">$99</div><div class="bpt-line bpt-line-60"></div></div>
            </div>
            @break

        @case('image')
            <div class="bpt-image" style="background-image: url('{{ $imgSquare }}');"></div>
            @break

        @case('image_grid')
            <div class="bpt-grid">
                <div class="bpt-thumb" style="background-image: url('{{ $imgSquare }}');"></div>
                <div class="bpt-thumb" style="background-image: url('{{ $imgSquare }}');"></div>
                <div class="bpt-thumb" style="background-image: url('{{ $imgSquare }}');"></div>
            </div>
            @break

        @case('video')
            <div class="bpt-video">
                <div class="bpt-play"><i class="fas fa-play"></i></div>
            </div>
            @break

        @case('audio')
            <div class="bpt-audio">
                <div class="bpt-play-sm" style="background: {{ $catColor }};"><i class="fas fa-music"></i></div>
                <div class="bpt-wave">
                    <span></span><span></span><span></span><span></span><span></span>
                    <span></span><span></span><span></span><span></span>
                </div>
            </div>
            @break

        @case('document')
            <div class="bpt-doc">
                <i class="fas fa-file-alt" style="color: {{ $catColor }};"></i>
                <div class="bpt-doc-lines">
                    <div class="bpt-line bpt-line-80"></div>
                    <div class="bpt-line bpt-line-50"></div>
                </div>
            </div>
            @break

        @case('socials')
            <div class="bpt-socials">
                <span style="background: #E4405F;"><i class="fab fa-instagram"></i></span>
                <span style="background: #000;"><i class="fab fa-tiktok"></i></span>
                <span style="background: #FF0000;"><i class="fab fa-youtube"></i></span>
                <span style="background: #1DA1F2;"><i class="fab fa-twitter"></i></span>
            </div>
            @break

        @case('form')
            <div class="bpt-input"></div>
            <div class="bpt-btn bpt-btn-sm" style="background: {{ $catColor }};">
                <span>{{ \Illuminate\Support\Str::limit($s['button_text'] ?? 'Submit', 16) }}</span>
            </div>
            @break

        @case('embed')
            <div class="bpt-embed">
                <i class="fas fa-code" style="color: {{ $catColor }};"></i>
                <div class="bpt-embed-label">Embed</div>
            </div>
            @break

        @case('map')
            <div class="bpt-map">
                <i class="fas fa-map-marker-alt" style="color: {{ $catColor }};"></i>
            </div>
            @break

        @case('profile')
            <div class="bpt-profile">
                <div class="bpt-avatar" style="background-image: url('{{ $avatar }}');"></div>
                <div class="bpt-profile-meta">
                    <div class="bpt-line bpt-line-70"></div>
                    <div class="bpt-line bpt-line-50"></div>
                </div>
            </div>
            @break

        @case('product')
            <div class="bpt-product">
                <div class="bpt-thumb" style="background-image: url('{{ $imgSquare }}');"></div>
                <div class="bpt-product-meta">
                    <div class="bpt-line bpt-line-80"></div>
                    <div class="bpt-mini-num" style="color: {{ $catColor }};">$29</div>
                </div>
            </div>
            @break

        @case('stats')
            <div class="bpt-stats">
                <div class="bpt-stat"><div class="bpt-mini-num" style="color: {{ $catColor }};">10k</div></div>
                <div class="bpt-stat"><div class="bpt-mini-num" style="color: {{ $catColor }};">4.9</div></div>
                <div class="bpt-stat"><div class="bpt-mini-num" style="color: {{ $catColor }};">120</div></div>
            </div>
            @break

        @case('countdown')
            <div class="bpt-countdown">
                <span style="background: {{ $catColor }}33; color: {{ $catColor }};">12</span>
                <span style="background: {{ $catColor }}33; color: {{ $catColor }};">04</span>
                <span style="background: {{ $catColor }}33; color: {{ $catColor }};">37</span>
            </div>
            @break

        @case('qr')
            <div class="bpt-qr">
                <i class="fas fa-qrcode"></i>
            </div>
            @break

        @case('share')
            <div class="bpt-socials">
                <span style="background: {{ $catColor }};"><i class="fas fa-share"></i></span>
                <span style="background: var(--bpt-icon-circle-bg);"><i class="fab fa-facebook"></i></span>
                <span style="background: var(--bpt-icon-circle-bg);"><i class="fab fa-twitter"></i></span>
            </div>
            @break

        @case('poll')
            <div class="bpt-poll-row"><div class="bpt-poll-bar" style="width: 60%; background: {{ $catColor }};"></div></div>
            <div class="bpt-poll-row"><div class="bpt-poll-bar" style="width: 35%; background: {{ $catColor }}88;"></div></div>
            <div class="bpt-poll-row"><div class="bpt-poll-bar" style="width: 20%; background: {{ $catColor }}55;"></div></div>
            @break

        @case('testimonial')
            <div class="bpt-quote">
                <i class="fas fa-quote-left" style="color: {{ $catColor }};"></i>
                <div class="bpt-quote-lines">
                    <div class="bpt-line bpt-line-90"></div>
                    <div class="bpt-line bpt-line-60"></div>
                </div>
            </div>
            @break

        @case('timeline')
            <div class="bpt-timeline">
                <div class="bpt-tl-row"><span class="bpt-tl-dot" style="background: {{ $catColor }};"></span><div class="bpt-line bpt-line-70"></div></div>
                <div class="bpt-tl-row"><span class="bpt-tl-dot" style="background: {{ $catColor }};"></span><div class="bpt-line bpt-line-60"></div></div>
                <div class="bpt-tl-row"><span class="bpt-tl-dot" style="background: {{ $catColor }};"></span><div class="bpt-line bpt-line-50"></div></div>
            </div>
            @break

        @case('menu')
            <div class="bpt-menu-row"><div class="bpt-line bpt-line-60"></div><span class="bpt-mini-num" style="color: {{ $catColor }};">$14</span></div>
            <div class="bpt-menu-row"><div class="bpt-line bpt-line-50"></div><span class="bpt-mini-num" style="color: {{ $catColor }};">$11</span></div>
            <div class="bpt-menu-row"><div class="bpt-line bpt-line-70"></div><span class="bpt-mini-num" style="color: {{ $catColor }};">$16</span></div>
            @break

        @case('event')
            <div class="bpt-event">
                <div class="bpt-event-date" style="background: {{ $catColor }};">
                    <span>12</span><small>JAN</small>
                </div>
                <div class="bpt-event-meta">
                    <div class="bpt-line bpt-line-90"></div>
                    <div class="bpt-line bpt-line-50"></div>
                </div>
            </div>
            @break

        @case('faq')
            <div class="bpt-faq-row"><i class="fas fa-plus" style="color: {{ $catColor }};"></i><div class="bpt-line bpt-line-80"></div></div>
            <div class="bpt-faq-row"><i class="fas fa-plus" style="color: {{ $catColor }};"></i><div class="bpt-line bpt-line-60"></div></div>
            @break

        @case('tabs')
            <div class="bpt-tabs">
                <span class="bpt-tab-active" style="background: {{ $catColor }};">About</span>
                <span class="bpt-tab">More</span>
                <span class="bpt-tab">FAQ</span>
            </div>
            @break

        @case('ticker')
            <div class="bpt-ticker" style="border-color: {{ $catColor }}44;">
                <span style="color: {{ $catColor }};">●</span>
                <div class="bpt-line bpt-line-80"></div>
            </div>
            @break

        @case('nav')
            <div class="bpt-nav">
                <span>Home</span><span>About</span><span>Contact</span>
            </div>
            @break

        @case('leaderboard')
            <div class="bpt-lb-row"><span class="bpt-rank" style="background: {{ $catColor }};">1</span><div class="bpt-avatar-sm" style="background-image: url('{{ $avatar }}');"></div><div class="bpt-line bpt-line-50"></div></div>
            <div class="bpt-lb-row"><span class="bpt-rank">2</span><div class="bpt-avatar-sm" style="background-image: url('{{ $avatar }}');"></div><div class="bpt-line bpt-line-60"></div></div>
            @break

        @case('insider')
            <div class="bpt-lock"><i class="fas fa-lock" style="color: {{ $catColor }};"></i></div>
            <div class="bpt-line bpt-line-80"></div>
            <div class="bpt-line bpt-line-60"></div>
            @break

        @case('chat')
            <div class="bpt-chat-row"><div class="bpt-bubble bpt-bubble-them"></div></div>
            <div class="bpt-chat-row" style="justify-content: flex-end;"><div class="bpt-bubble bpt-bubble-me" style="background: {{ $catColor }};"></div></div>
            @break

        @case('form_generic')
            <div class="bpt-input"></div>
            <div class="bpt-input bpt-input-sm"></div>
            <div class="bpt-btn bpt-btn-sm" style="background: {{ $catColor }};"><span>Submit</span></div>
            @break

        @case('roadmap')
            <div class="bpt-roadmap">
                <span style="background: {{ $catColor }}33; color: {{ $catColor }};">Ideas</span>
                <span style="background: {{ $catColor }}55; color: white;">Planned</span>
                <span style="background: {{ $catColor }}; color: white;">Shipped</span>
            </div>
            @break

        @default
            <div class="bpt-generic" style="background: {{ $catColor }}15;">
                <i class="fas {{ $typeInfo['icon'] }}" style="color: {{ $catColor }};"></i>
            </div>
    @endswitch
</div>
