{{-- Biolink block renderer (Task #1090).
     Each block type is rendered by a partial in resources/views/common/blocks/.
     The map below is the full dispatch table; legacy types (link_big, faq_v2,
     socials_multi, etc.) keep their own entries so saved blocks render
     identically with no data migration. New canonical types added in #1090
     live alongside. The trailing @default branch handles unknown types and
     preserves the legacy poll/comments hook for content-mode blocks. --}}
@php
    $__blockPartials = [
        "avatar"                           => 'common.blocks.avatar',
        "heading"                          => 'common.blocks.heading',
        "heading_logo"                     => 'common.blocks.heading-logo',
        "paragraph"                        => 'common.blocks.paragraph',
        "paragraph_rich"                   => 'common.blocks.paragraph-rich',
        "link"                             => 'common.blocks.link',
        "link_big"                         => 'common.blocks.link-big',
        "divider"                          => 'common.blocks.divider',
        "spacer"                           => 'common.blocks.spacer',
        "image"                            => 'common.blocks.image',
        "cta_button"                       => 'common.blocks.cta-button',
        "badge"                            => 'common.blocks.badge',
        "socials"                          => 'common.blocks.socials',
        "socials_multi"                    => 'common.blocks.socials',
        "socials_custom"                   => 'common.blocks.socials',
        "faq"                              => 'common.blocks.faq',
        "faq_v2"                           => 'common.blocks.faq',
        "product"                          => 'common.blocks.product',
        "service"                          => 'common.blocks.service',
        "testimonials"                     => 'common.blocks.testimonials',
        "alert"                            => 'common.blocks.alert',
        "list"                             => 'common.blocks.list',
        "list_numbered"                    => 'common.blocks.list',
        "list_pricing"                     => 'common.blocks.list-pricing',
        "youtube"                          => 'common.blocks.youtube',
        "video"                            => 'common.blocks.video',
        "spotify"                          => 'common.blocks.spotify',
        "email_collector"                  => 'common.blocks.email-collector',
        "verified_heading"                 => 'common.blocks.verified-heading',
        "verified_avatar"                  => 'common.blocks.verified-avatar',
        "email_subscribe"                  => 'common.blocks.email-subscribe',
        "whatsapp_channel_subscribe"       => 'common.blocks.whatsapp-channel-subscribe',
        "whatsapp_number_subscribe"        => 'common.blocks.whatsapp-number-subscribe',
        "countdown"                        => 'common.blocks.countdown',
        "progress"                         => 'common.blocks.progress',
        "custom_html"                      => 'common.blocks.custom-html',
        "whatsapp_widget"                  => 'common.blocks.whatsapp-widget',
        "price"                            => 'common.blocks.price',
        "notification"                     => 'common.blocks.notification',
        "ai_companion"                     => 'common.blocks.ai-companion',
        "social_proof"                     => 'common.blocks.social-proof',
        "qr_code"                          => 'common.blocks.qr-code',
        "iframe_embed"                     => 'common.blocks.iframe-embed',
        "form"                             => 'common.blocks.form',
        "file"                             => 'common.blocks.file',
        "markdown"                         => 'common.blocks.markdown',
        "rsvp"                             => 'common.blocks.rsvp',
        "buy_me_coffee"                    => 'common.blocks.buy-me-coffee',
        "patreon"                          => 'common.blocks.buy-me-coffee',
        "ko_fi"                            => 'common.blocks.buy-me-coffee',
        "latest_youtube"                   => 'common.blocks.latest-youtube',
        "latest_instagram"                 => 'common.blocks.latest-instagram',
        "featured_pin"                     => 'common.blocks.featured-pin',
        "calendly_embed"                   => 'common.blocks.calendly-embed',
        "map_location"                     => 'common.blocks.map-location',
        "insider"                          => 'common.blocks.insider',
        "fan_leaderboard"                  => 'common.blocks.fan-leaderboard',
        "roadmap"                          => 'common.blocks.roadmap',
        "file_list"                        => 'common.blocks.file-list',
        "audio_list"                       => 'common.blocks.audio-list',
        "link_tree_group"                  => 'common.blocks.link-tree-group',
        "tabs"                             => 'common.blocks.tabs',
        "accordion"                        => 'common.blocks.accordion',
        "event_list"                       => 'common.blocks.event-list',
        "menu"                             => 'common.blocks.menu',
        "testimonial_carousel"             => 'common.blocks.testimonial-carousel',
        "stats"                            => 'common.blocks.stats',
        "affiliate_links"                  => 'common.blocks.affiliate-links',
        "booking_slots"                    => 'common.blocks.booking-slots',
        "instagram"                        => 'common.blocks.latest-instagram',
        "menu_section"                     => 'common.blocks.menu-section',
    ];
    $__partial = $__blockPartials[$block->type] ?? null;
@endphp

@if($__partial)
    @include($__partial, ['link' => $link, 'block' => $block, 's' => $s, 'fontColor' => $fontColor])
@else
    @if(!empty($block->settings['enable_polls']) || !empty($block->settings['enable_comments']))
        @if(!empty($block->settings['enable_polls']))
            @include('partials.community.polls-block', ['link' => $link, 'block' => $block])
        @endif
        @if(!empty($block->settings['enable_comments']))
            @include('partials.community.comments-block', ['link' => $link, 'block' => $block])
        @endif
    @endif
    <div class="mb-4 glass-block rounded-xl p-4 text-center">
        <i class="fas fa-cube text-lg mb-1 text-purple-400/50"></i>
        <p class="text-xs text-white/40">{{ \App\Modules\User\Models\BiolinkBlock::TYPES[$block->type]['label'] ?? ucfirst(str_replace('_', ' ', $block->type)) }}</p>
    </div>
@endif


@once
    @push('scripts')
        <script src="{{ asset('js/community-public.js') }}" defer></script>
    @endpush
@endonce
