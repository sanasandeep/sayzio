{{-- Unified biolink block renderer (Task #2042).

     SINGLE source of truth for rendering a block on the public biolink page.
     Used for BOTH top-level blocks (included from common/biolink.blade.php)
     and blocks nested inside Card / Grid containers (recursive @include in
     the container branch below). Previously top-level blocks went through a
     large inline @if/@elseif chain in biolink.blade.php while card children
     went through this dispatch table, so every new block type had to be
     wired into BOTH or it rendered inconsistently. Now there is ONE place:

       - Types with a dedicated partial in resources/views/common/blocks/
         are registered in $__blockPartials and rendered via that partial.
       - Types without a dedicated partial yet are handled by the inline
         fallback chain (the @if(false) anchor lets every real branch stay
         an @elseif).
       - The trailing @else keeps the legacy poll/comments hook for
         content-mode blocks and shows a placeholder for unknown types. --}}
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
    $btnInline = $btnInline ?? '';
@endphp

@if($__partial)
    @include($__partial, ['link' => $link, 'block' => $block, 's' => $s, 'fontColor' => $fontColor, 'btnInline' => $btnInline])
@else
    @if(false){{-- anchor: keeps every real branch below as an @elseif --}}
            @elseif($block->type === 'resume')
                @php
                    $__rOwner = $link->user ?? null;
                    $__rResume = $__rOwner ? $__rOwner->resume : null;
                    $__rUrl = $__rOwner ? url('/' . $__rOwner->publicHandle() . '/resume') : null;
                    $__rDisplay = $s['display'] ?? 'card'; // card | inline
                    $__rTitle = $s['title'] ?? 'My résumé';
                    $__rCta   = $s['cta_label'] ?? 'View full résumé';
                    $__rDesc  = $s['description'] ?? null;
                    if ($__rResume && empty($__rDesc)) {
                        $__rh = $__rResume->getMergedSections()['header'] ?? [];
                        $__rDesc = trim($__rh['headline'] ?? '');
                    }
                @endphp
                @if (!$__rResume || !$__rResume->is_public || !$__rUrl)
                    {{-- Owner hasn't published yet — render nothing on the public page. --}}
                @elseif ($__rDisplay === 'inline')
                    <div class="rounded-2xl overflow-hidden mb-3" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                        <div class="px-3 py-2 flex items-center justify-between text-xs" style="color: {{ $fontColor }}cc;">
                            <span class="inline-flex items-center gap-2 font-semibold"><i class="fas fa-file-lines"></i> {{ $__rTitle }}</span>
                            <a href="{{ $__rUrl }}" class="font-bold underline-offset-2 hover:underline" style="color: {{ $fontColor }};">Open <i class="fas fa-external-link-alt text-[10px]"></i></a>
                        </div>
                        <div style="background:#fff; color:#111;">
                            @include('common.partials.resume-render', ['resume' => $__rResume, 'compact' => true])
                        </div>
                    </div>
                @else
                    <a href="{{ $__rUrl }}" class="block mb-3 rounded-2xl p-4 transition-all hover:scale-[1.01]"
                       style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: {{ $fontColor }};">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                                 style="background: rgba(124,58,237,0.18); color:#c4b5fd;">
                                <i class="fas fa-file-lines"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold truncate">{{ $__rTitle }}</div>
                                @if (!empty($__rDesc))
                                    <div class="text-xs opacity-75 truncate">{{ $__rDesc }}</div>
                                @endif
                            </div>
                            <span class="text-xs font-semibold inline-flex items-center gap-1 shrink-0">
                                {{ $__rCta }} <i class="fas fa-arrow-right text-[10px]"></i>
                            </span>
                        </div>
                    </a>
                @endif

            @elseif($block->type === 'image_grid')
                @php
                    $imgSt = $s['_image_style'] ?? [];
                    $imgInline = \App\Modules\User\Models\BiolinkBlock::buildImageInlineStyle($imgSt);
                    $imgLk = $s['_link'] ?? [];
                    $gridLinkUrl = $imgLk['url'] ?? '';
                    $gridTrackUrl = $gridLinkUrl ? route('redirect.block', ['alias' => $link->alias, 'blockId' => $block->id]) : '';
                    $gridTarget = $imgLk['target'] ?? '_blank';
                    $gridRel = $imgLk['rel'] ?? 'noopener';
                @endphp
                <div class="mb-4 grid grid-cols-{{ $s['columns'] ?? 3 }} gap-{{ $s['gap'] ?? 2 }}">
                    @if($gridTrackUrl)<a href="{{ $gridTrackUrl }}" target="{{ $gridTarget }}" rel="{{ $gridRel }}" class="contents">@endif
                    @foreach(($s['images'] ?? []) as $img)
                        <img src="{{ is_array($img) ? ($img['url'] ?? '') : $img }}" alt="" class="w-full aspect-square object-cover{{ empty($imgInline) ? ' rounded-lg' : '' }}" style="{{ $imgInline }}">
                    @endforeach
                    @if($gridTrackUrl)</a>@endif
                </div>

            @elseif(in_array($block->type, ['image_slider', 'image_slider_v2']))
                @php
                    $imgSt = $s['_image_style'] ?? [];
                    $imgInline = \App\Modules\User\Models\BiolinkBlock::buildImageInlineStyle($imgSt);
                    $sliderLk = $s['_link'] ?? [];
                    $sliderLinkUrl = $sliderLk['url'] ?? '';
                    $sliderTrackUrl = $sliderLinkUrl ? route('redirect.block', ['alias' => $link->alias, 'blockId' => $block->id]) : '';
                    $sliderTarget = $sliderLk['target'] ?? '_blank';
                    $sliderRel = $sliderLk['rel'] ?? 'noopener';
                @endphp
                <div class="mb-4 rounded-xl overflow-hidden relative" x-data="{ current: 0, images: {{ json_encode($s['images'] ?? []) }} }" x-init="setInterval(() => { if(images.length > 1) current = (current + 1) % images.length }, {{ $s['interval'] ?? 3000 }})">
                    @if($sliderTrackUrl)<a href="{{ $sliderTrackUrl }}" target="{{ $sliderTarget }}" rel="{{ $sliderRel }}">@endif
                    <template x-for="(img, i) in images" :key="i">
                        <img :src="typeof img === 'string' ? img : img.url" x-show="current === i" class="w-full{{ empty($imgInline) ? ' rounded-xl' : '' }} {{ ($s['effect'] ?? '') === 'fade' ? 'transition-opacity duration-500' : '' }}" style="{{ $imgInline }}" alt="">
                    </template>
                    @if($sliderTrackUrl)</a>@endif
                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
                        <template x-for="(_, i) in images" :key="'d'+i">
                            <button @click="current = i" class="w-2 h-2 rounded-full transition-all" :class="current === i ? 'bg-white w-4' : 'bg-white/40'"></button>
                        </template>
                    </div>
                </div>

            @elseif($block->type === 'header_video')
                <div class="mb-4 rounded-xl overflow-hidden">
                    <video class="w-full rounded-xl" {{ ($s['autoplay'] ?? true) ? 'autoplay' : '' }} {{ ($s['muted'] ?? true) ? 'muted' : '' }} {{ ($s['loop'] ?? true) ? 'loop' : '' }} playsinline>
                        <source src="{{ $s['url'] ?? '' }}" type="video/mp4">
                    </video>
                </div>

            @elseif($block->type === 'audio')
                <div class="mb-3 glass-block rounded-xl p-4">
                    @if(!empty($s['title']))<p class="text-sm font-medium mb-2">{{ $s['title'] }}</p>@endif
                    <audio controls class="w-full" style="filter: invert(1) hue-rotate(180deg);"><source src="{{ $s['url'] ?? '' }}"></audio>
                </div>

            @elseif($block->type === 'pdf_document')
                <div class="mb-4 glass-block rounded-xl p-4">
                    <div class="flex items-center gap-3 mb-3"><i class="fas fa-file-pdf text-red-400 text-xl"></i><span class="font-medium text-sm">{{ $s['title'] ?? 'PDF Document' }}</span></div>
                    @if(!empty($s['url']))<iframe src="{{ $s['url'] }}" class="w-full h-64 rounded-lg border border-white/10"></iframe>@endif
                </div>

            @elseif(in_array($block->type, ['powerpoint', 'excel']))
                <div class="mb-4 glass-block rounded-xl p-4">
                    <div class="flex items-center gap-3"><i class="fas {{ $block->type === 'powerpoint' ? 'fa-file-powerpoint text-orange-400' : 'fa-file-excel text-green-400' }} text-xl"></i>
                        <div class="flex-1"><span class="font-medium text-sm">{{ $s['title'] ?? ($block->type === 'powerpoint' ? 'Presentation' : 'Spreadsheet') }}</span></div>
                        @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn px-4 py-2 text-xs font-medium">View</a>@endif
                    </div>
                </div>

            {{-- SOCIAL — Follow buttons (Task #48) --}}
            @elseif($block->type === 'instagram_media')
                <div class="mb-4 rounded-xl overflow-hidden glass-block p-1">
                    <iframe src="{{ str_replace('/p/', '/p/', $s['url'] ?? '') }}embed" class="w-full" style="min-height:400px;border:none;" loading="lazy"></iframe>
                </div>

            @elseif($block->type === 'tiktok_video')
                <div class="mb-4 glass-block rounded-xl p-4 text-center">
                    <i class="fab fa-tiktok text-2xl mb-2"></i>
                    <a href="{{ $s['url'] ?? '#' }}" target="_blank" class="bio-btn inline-block px-5 py-2 text-sm font-medium mt-2">Watch on TikTok</a>
                </div>

            @elseif($block->type === 'tiktok_profile')
                <div class="mb-4 glass-block rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center"><i class="fab fa-tiktok text-xl"></i></div>
                    <div class="flex-1"><p class="font-medium text-sm">{{ '@' . ($s['username'] ?? '') }}</p><p class="text-xs text-white/40">TikTok</p></div>
                    <a href="https://tiktok.com/@{{ $s['username'] ?? '' }}" target="_blank" class="bio-btn px-4 py-2 text-xs font-medium">Follow</a>
                </div>

            @elseif($block->type === 'twitter_profile')
                <div class="mb-4 glass-block rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center"><i class="fab fa-x-twitter text-xl"></i></div>
                    <div class="flex-1"><p class="font-medium text-sm">{{ '@' . ($s['username'] ?? '') }}</p><p class="text-xs text-white/40">X (Twitter)</p></div>
                    <a href="https://x.com/{{ $s['username'] ?? '' }}" target="_blank" class="bio-btn px-4 py-2 text-xs font-medium">Follow</a>
                </div>

            @elseif(in_array($block->type, ['twitter_tweet', 'twitter_video']))
                <div class="mb-4 glass-block rounded-xl p-4 text-center">
                    <i class="fab fa-x-twitter text-2xl mb-2"></i>
                    <a href="{{ $s['url'] ?? '#' }}" target="_blank" class="bio-btn inline-block px-5 py-2 text-sm font-medium mt-2">View on X</a>
                </div>

            @elseif($block->type === 'pinterest_profile')
                <div class="mb-4 glass-block rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center"><i class="fab fa-pinterest text-xl" style="color:#BD081C"></i></div>
                    <div class="flex-1"><p class="font-medium text-sm">{{ $s['username'] ?? '' }}</p><p class="text-xs text-white/40">Pinterest</p></div>
                    <a href="https://pinterest.com/{{ $s['username'] ?? '' }}" target="_blank" class="bio-btn px-4 py-2 text-xs font-medium">Follow</a>
                </div>

            @elseif($block->type === 'snapchat')
                <div class="mb-4 glass-block rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background:#FFFC00"><i class="fab fa-snapchat text-xl text-black"></i></div>
                    <div class="flex-1"><p class="font-medium text-sm">{{ $s['username'] ?? '' }}</p><p class="text-xs text-white/40">Snapchat</p></div>
                    <a href="https://snapchat.com/add/{{ $s['username'] ?? '' }}" target="_blank" class="bio-btn px-4 py-2 text-xs font-medium">Add</a>
                </div>

            @elseif($block->type === 'rss_feed')
                <div class="mb-4 glass-block rounded-xl p-4">
                    <div class="flex items-center gap-2 mb-3"><i class="fas fa-rss text-orange-400"></i><span class="text-sm font-medium">RSS Feed</span></div>
                    <p class="text-xs text-white/40">Feed: {{ $s['url'] ?? '' }}</p>
                </div>

            {{-- MUSIC --}}
            @elseif($block->type === 'apple_music')
                @php $amEmbed = str_replace('music.apple.com', 'embed.music.apple.com', $s['url'] ?? ''); @endphp
                <div class="mb-4 rounded-xl overflow-hidden">
                    <iframe src="{{ $amEmbed }}" class="w-full rounded-xl" height="{{ ($s['type'] ?? 'album') === 'song' ? '175' : '450' }}" frameborder="0" allow="autoplay; encrypted-media" loading="lazy"></iframe>
                </div>

            @elseif($block->type === 'soundcloud')
                <div class="mb-4 rounded-xl overflow-hidden">
                    <iframe width="100%" height="166" scrolling="no" frameborder="no" src="https://w.soundcloud.com/player/?url={{ urlencode($s['url'] ?? '') }}&color=%237c3aed&auto_play=false&hide_related=true&show_comments=false&show_user=true&show_reposts=false&show_teaser=false" class="rounded-xl" loading="lazy"></iframe>
                </div>

            @elseif(in_array($block->type, ['tidal', 'mixcloud', 'anchor_fm']))
                <div class="mb-4 glass-block rounded-xl p-4 text-center">
                    <i class="fas {{ $block->type === 'tidal' ? 'fa-water' : ($block->type === 'mixcloud' ? 'fa-headphones' : 'fa-podcast') }} text-2xl mb-2"></i>
                    <p class="text-sm font-medium mb-2">{{ \App\Modules\User\Models\BiolinkBlock::TYPES[$block->type]['label'] }}</p>
                    @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn inline-block px-5 py-2 text-sm font-medium">Listen</a>@endif
                </div>

            {{-- VIDEO PLATFORMS --}}
            @elseif($block->type === 'youtube_feed')
                <div class="mb-4 glass-block rounded-xl p-4">
                    <div class="flex items-center gap-2 mb-3"><i class="fab fa-youtube text-red-500 text-lg"></i><span class="text-sm font-medium">YouTube Channel</span></div>
                    <p class="text-xs text-white/40">Channel: {{ $s['channel_id'] ?? '' }}</p>
                </div>

            @elseif($block->type === 'vimeo')
                <div class="mb-4 rounded-xl overflow-hidden aspect-video">
                    <iframe src="https://player.vimeo.com/video/{{ $s['video_id'] ?? '' }}" class="w-full h-full rounded-xl" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                </div>

            @elseif($block->type === 'twitch')
                <div class="mb-4 rounded-xl overflow-hidden aspect-video">
                    <iframe src="https://player.twitch.tv/?channel={{ $s['channel'] ?? '' }}&parent={{ request()->getHost() }}" class="w-full h-full rounded-xl" frameborder="0" allowfullscreen loading="lazy"></iframe>
                </div>

            @elseif(in_array($block->type, ['kick', 'rumble_video', 'vk_video']))
                <div class="mb-4 glass-block rounded-xl p-4 text-center">
                    <i class="fas {{ $block->type === 'kick' ? 'fa-bolt' : 'fa-play-circle' }} text-2xl mb-2"></i>
                    <p class="text-sm font-medium mb-2">{{ \App\Modules\User\Models\BiolinkBlock::TYPES[$block->type]['label'] }}</p>
                    @php $watchUrl = $block->type === 'kick' ? 'https://kick.com/' . ($s['channel'] ?? '') : ($s['url'] ?? '#'); @endphp
                    <a href="{{ $watchUrl }}" target="_blank" class="bio-btn inline-block px-5 py-2 text-sm font-medium">Watch</a>
                </div>

            {{-- CONTACT --}}
            @elseif($block->type === 'phone_collector')
                <div class="mb-4 glass-block rounded-xl p-5 text-center">
                    <p class="text-sm font-semibold mb-3">{{ $s['title'] ?? 'Call Us' }}</p>
                    <form class="flex gap-2" onsubmit="event.preventDefault(); this.querySelector('button').textContent='Done!'; this.querySelector('button').disabled=true;">
                        <input type="tel" required placeholder="{{ $s['placeholder'] ?? 'Your phone' }}" class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-white/20" style="color:{{ $fontColor }}">
                        <button type="submit" class="bio-btn px-5 py-2.5 text-sm font-medium whitespace-nowrap">{{ $s['button_text'] ?? 'Submit' }}</button>
                    </form>
                </div>

            @elseif($block->type === 'contact_form')
                <div class="mb-4 glass-block rounded-xl p-5" x-data="{ submitted: false, loading: false, error: '' }">
                    <p class="text-sm font-semibold mb-3 text-center">{{ $s['title'] ?? 'Contact Us' }}</p>
                    <template x-if="!submitted">
                        <form @submit.prevent="
                            loading = true; error = '';
                            fetch('/{{ $link->alias }}/subscribe', {
                                method: 'POST',
                                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                                body: JSON.stringify({
                                    block_id: {{ $block->id }},
                                    type: 'contact_form',
                                    name: $refs.cfName{{ $block->id }}.value,
                                    email: $refs.cfEmail{{ $block->id }}.value,
                                    message: $refs.cfMessage{{ $block->id }}.value,
                                    _hp: $refs.cfHp{{ $block->id }}.value
                                })
                            }).then(r => r.json()).then(d => {
                                loading = false;
                                if(d.success) submitted = true;
                                else error = d.message || 'Something went wrong';
                            }).catch(() => { loading = false; error = 'Network error'; })
                        " class="space-y-3">
                            <input x-ref="cfHp{{ $block->id }}" type="text" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none;">
                            <input x-ref="cfName{{ $block->id }}" type="text" placeholder="Name" required class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none" style="color:{{ $fontColor }}">
                            <input x-ref="cfEmail{{ $block->id }}" type="email" placeholder="Email" required class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none" style="color:{{ $fontColor }}">
                            <textarea x-ref="cfMessage{{ $block->id }}" placeholder="Message" rows="3" required maxlength="5000" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none" style="color:{{ $fontColor }}"></textarea>
                            <button type="submit" :disabled="loading" class="bio-btn w-full py-2.5 text-sm font-medium flex items-center justify-center gap-2">
                                <template x-if="loading"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                                <span x-text="loading ? 'Sending...' : '{{ $s['button_text'] ?? 'Send' }}'"></span>
                            </button>
                            <p x-show="error" x-text="error" class="text-xs text-red-400 text-center" x-cloak></p>
                        </form>
                    </template>
                    <template x-if="submitted">
                        <div class="text-center py-3">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background: rgba(34,197,94,0.15);">
                                <i class="fas fa-check text-green-400 text-xl"></i>
                            </div>
                            <p class="text-sm font-semibold text-green-400">{{ $s['success_message'] ?? 'Message sent — thanks!' }}</p>
                        </div>
                    </template>
                </div>

            @elseif($block->type === 'direct_message')
                @php
                    $dmTitle  = $s['title']  ?? 'Send a direct message';
                    $dmDesc   = $s['description'] ?? 'Reach out — replies arrive in your inbox.';
                    $dmPh     = $s['placeholder'] ?? 'Write your message…';
                    $dmBtn    = $s['button_text'] ?? 'Send message';
                    $dmLimit  = (int) (\App\Modules\Common\Models\ViewerDmConversation::VIEWER_INITIAL_LIMIT);
                    $dmLinkId = (int) ($link->id ?? 0);
                    $loggedIn = \App\Modules\Common\Services\ViewerSession::check();
                @endphp
                @include('common.partials.dm-chat-widget', [
                    'dmTitle'   => $dmTitle,
                    'dmDesc'    => $dmDesc,
                    'dmPh'      => $dmPh,
                    'dmBtn'     => $dmBtn,
                    'dmLimit'   => $dmLimit,
                    'dmLinkId'  => $dmLinkId,
                    'loggedIn'  => $loggedIn,
                    'fontColor' => $fontColor,
                    'variant'   => 'block',
                ])

            @elseif($block->type === 'whatsapp_item')
                <div class="mb-4 glass-block rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background:#25D366"><i class="fab fa-whatsapp text-xl text-white"></i></div>
                    <div class="flex-1"><p class="font-medium text-sm">{{ $s['name'] ?? 'WhatsApp' }}</p><p class="text-xs text-white/40">{{ $s['phone'] ?? '' }}</p></div>
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $s['phone'] ?? '') }}?text={{ urlencode($s['message'] ?? '') }}" target="_blank" class="bio-btn px-4 py-2 text-xs font-medium">Chat</a>
                </div>

            {{-- INTERACTIVE --}}
            @elseif($block->type === 'poll')
                {{-- Live poll: persists each vote to /api/v1/biolinks/{alias}/blocks/{id}/poll-vote
                     and then swaps the button list out for tally bars fetched from the matching
                     /poll-results endpoint, so viewers can immediately see how their pick compares.
                     If the results fetch fails, the option list stays visible with the picked
                     option highlighted (and a small "Thanks for voting" hint) instead of an error wall. --}}
                @php
                    $pollRevealAt = null;
                    $pollRevealLabel = null;
                    if (!empty($s['reveal_results_at'])) {
                        try {
                            $pollRevealAt = \Carbon\Carbon::parse($s['reveal_results_at']);
                            $pollRevealLabel = $pollRevealAt->toIso8601String();
                        } catch (\Throwable $e) {
                            $pollRevealAt = null;
                        }
                    }
                @endphp
                <div class="mb-4 glass-block rounded-xl p-5"
                     x-data="biolinkPoll({
                        alias: @js($link->alias),
                        blockId: {{ (int) $block->id }},
                        options: @js(array_values((array) ($s['options'] ?? []))),
                        revealAt: @js($pollRevealLabel),
                     })"
                     x-init="init()">
                    <p class="text-sm font-semibold mb-3">{{ $s['question'] ?? '' }}</p>
                    <template x-if="resultsLocked">
                        <p class="text-xs mb-2" style="color:{{ $fontColor }}99">
                            <i class="fas fa-lock mr-1"></i>Results visible after <span x-text="revealAtDisplay"></span>
                        </p>
                    </template>
                    <template x-if="!results">
                        <div class="space-y-2">
                            @foreach(($s['options'] ?? []) as $i => $opt)
                            <button type="button"
                                    @click="vote({{ $i }}, @js($opt))"
                                    :disabled="submitting !== null"
                                    class="w-full text-left px-4 py-2.5 rounded-xl text-sm transition-all disabled:opacity-60"
                                    :class="voted === {{ $i }} ? 'bg-purple-500/30 border border-purple-400/40' : 'bg-white/5 border border-white/10 hover:bg-white/10'">
                                <span class="flex items-center gap-2">
                                    <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                                          :class="voted === {{ $i }} ? 'border-purple-400' : 'border-white/30'">
                                        <span x-show="voted === {{ $i }}" class="w-2 h-2 rounded-full bg-purple-400"></span>
                                    </span>
                                    <span class="flex-1">{{ $opt }}</span>
                                    <template x-if="submitting === {{ $i }}">
                                        <i class="fas fa-spinner fa-spin text-xs text-white/60"></i>
                                    </template>
                                </span>
                            </button>
                            @endforeach
                        </div>
                    </template>
                    <template x-if="results">
                        <div>
                            <p class="text-xs mb-2" style="color:{{ $fontColor }}88">
                                <span x-text="results.total_votes"></span>
                                <span x-text="results.total_votes === 1 ? 'vote' : 'votes'"></span>
                            </p>
                            <div class="space-y-2">
                                <template x-for="opt in results.options" :key="opt.index">
                                    <div class="relative w-full px-4 py-2.5 rounded-xl text-sm overflow-hidden border"
                                         :class="opt.index === voted ? 'border-purple-400/50' : 'border-white/10'">
                                        <div class="absolute inset-y-0 left-0 transition-all"
                                             :style="`width:${Math.max(0, Math.min(100, opt.percent))}%; background-color:${opt.index === voted ? 'rgba(124,58,237,0.35)' : 'rgba(124,58,237,0.15)'}`"></div>
                                        <div class="relative flex items-center gap-2">
                                            <span class="flex-1 truncate" x-text="opt.label"></span>
                                            <template x-if="opt.index === voted">
                                                <i class="fas fa-check text-xs text-purple-300"></i>
                                            </template>
                                            <span class="text-xs font-semibold tabular-nums" x-text="opt.percent + '%'"></span>
                                            <span class="text-[10px] tabular-nums" style="color:{{ $fontColor }}66" x-text="opt.count"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="!results && voted !== null && !error">
                        <p class="text-xs mt-2 text-green-300">Thanks for voting!</p>
                    </template>
                    <template x-if="error">
                        <p class="text-xs mt-2 text-red-300" x-text="error"></p>
                    </template>
                </div>

            @elseif($block->type === 'review')
                <div class="mb-4 glass-block rounded-xl p-4">
                    <div class="flex items-center gap-3 mb-2">
                        @if(!empty($s['avatar']))<img src="{{ $s['avatar'] }}" class="w-10 h-10 rounded-full object-cover" alt="">@endif
                        <div><p class="text-sm font-medium">{{ $s['name'] ?? '' }}</p>
                        <div class="flex gap-0.5">@for($star = 1; $star <= 5; $star++)<i class="fas fa-star text-xs {{ $star <= ($s['rating'] ?? 5) ? 'text-yellow-400' : 'text-white/20' }}"></i>@endfor</div></div>
                    </div>
                    <p class="text-sm" style="color:{{ $fontColor }}cc">{{ $s['text'] ?? '' }}</p>
                </div>

            @elseif($block->type === 'reviews_wall')
                @php
                    $rwSource = in_array($s['source'] ?? 'both', ['native', 'external', 'both'], true) ? ($s['source'] ?? 'both') : 'both';
                    $rwSort   = in_array($s['sort'] ?? 'recent', ['recent', 'rating'], true) ? ($s['sort'] ?? 'recent') : 'recent';
                    $rwLimit  = min(50, max(1, (int) ($s['limit'] ?? 6)));
                    $rwProviders = is_array($s['providers'] ?? null) ? $s['providers'] : [];
                    $rwItems  = \App\Modules\User\Support\ReviewFeed::build((int) $link->user_id, (int) $link->id, $rwSource, $rwSort, $rwLimit, $rwProviders);
                    $rwSummary = ($s['show_summary'] ?? true)
                        ? app(\App\Modules\User\Support\ReviewSummaryService::class)->summary((int) $link->user_id, (int) $link->id, $rwSource)
                        : null;
                    $rwLayout = ($s['layout'] ?? 'grid') === 'list' ? 'list' : 'grid';
                @endphp
                <div class="mb-4" data-reviews-wall="{{ $block->id }}" data-reviews-alias="{{ $link->alias }}">
                    @if(!empty($s['heading']))<h3 class="text-base font-semibold mb-3">{{ $s['heading'] }}</h3>@endif
                    @if($rwSummary)
                    <div class="glass-block rounded-xl p-4 mb-3 flex items-center gap-4">
                        <div class="text-3xl font-bold">{{ number_format($rwSummary['average'] ?? 0, 1) }}</div>
                        <div>
                            <div class="flex gap-0.5">@for($star = 1; $star <= 5; $star++)<i class="fas fa-star text-sm {{ $star <= round($rwSummary['average'] ?? 0) ? 'text-yellow-400' : 'text-white/20' }}"></i>@endfor</div>
                            <p class="text-xs mt-0.5" style="color:{{ $fontColor }}88">{{ $rwSummary['total'] ?? 0 }} reviews</p>
                        </div>
                    </div>
                    @endif
                    <div class="{{ $rwLayout === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 gap-3' : 'space-y-3' }}">
                        @forelse($rwItems as $rev)
                        <div class="glass-block rounded-xl p-4">
                            <div class="flex items-center gap-2.5 mb-1.5">
                                @if(!empty($rev['author_avatar']))<img src="{{ $rev['author_avatar'] }}" class="w-9 h-9 rounded-full object-cover" alt="">
                                @else<div class="w-9 h-9 rounded-full bg-purple-500/20 flex items-center justify-center"><span class="text-xs font-bold">{{ strtoupper(substr($rev['author_name'] ?: 'A', 0, 1)) }}</span></div>@endif
                                <div class="min-w-0">
                                    <p class="text-sm font-medium truncate">{{ $rev['author_name'] ?: 'Anonymous' }}@if(!empty($rev['verified']))<span class="inline-flex items-center gap-0.5 align-middle ml-1 text-[9px] font-semibold text-emerald-300 bg-emerald-400/10 border border-emerald-400/30 rounded-full px-1.5 py-px" title="Verified customer"><i class="fas fa-circle-check text-[8px]"></i>Verified</span>@endif @if(!empty($rev['is_pinned']))<i class="fas fa-thumbtack text-purple-400 text-[10px] ml-1"></i>@endif</p>
                                    @if(!empty($rev['rating']))<div class="flex gap-0.5">@for($star = 1; $star <= 5; $star++)<i class="fas fa-star text-[10px] {{ $star <= $rev['rating'] ? 'text-yellow-400' : 'text-white/20' }}"></i>@endfor</div>@endif
                                </div>
                                @if(!empty($rev['source']) && $rev['source'] !== 'native')<span class="ml-auto text-[10px] px-1.5 py-0.5 rounded bg-white/10 text-white/50">{{ $rev['source_label'] }}</span>@endif
                            </div>
                            @if(!empty($rev['body']))<p class="text-sm" style="color:{{ $fontColor }}cc">{{ $rev['body'] }}</p>@endif
                            @if(!empty($rev['media']))
                            <div class="flex gap-1.5 mt-2 flex-wrap">
                                @foreach($rev['media'] as $m)
                                    @if(($m['type'] ?? '') === 'image')<img src="{{ $m['url'] }}" class="w-14 h-14 object-cover rounded-lg" alt="">
                                    @elseif(($m['type'] ?? '') === 'video')<video src="{{ $m['url'] }}" class="w-14 h-14 object-cover rounded-lg" controls></video>
                                    @elseif(($m['type'] ?? '') === 'audio')<audio src="{{ $m['url'] }}" controls class="h-8"></audio>@endif
                                @endforeach
                            </div>
                            @endif
                            @if(!empty($rev['answers']))
                            <div class="mt-2 text-xs space-y-0.5" style="color:{{ $fontColor }}99">
                                @foreach($rev['answers'] as $a)<div><span class="font-medium" style="color:{{ $fontColor }}cc">{{ $a['prompt'] }}:</span> {{ $a['answer'] }}</div>@endforeach
                            </div>
                            @endif
                            @if(!empty($rev['reply']))
                            <div class="mt-2 pl-2.5 border-l-2 border-purple-500/40 text-xs" style="color:{{ $fontColor }}aa"><span class="text-purple-400 font-medium">Reply:</span> {{ $rev['reply'] }}</div>
                            @endif
                        </div>
                        @empty
                        <p class="text-sm text-center py-4" style="color:{{ $fontColor }}66">No reviews yet — be the first!</p>
                        @endforelse
                    </div>
                    @if($s['allow_submissions'] ?? true)
                    <button type="button" class="bio-btn block w-full text-center mt-3 py-2.5 text-sm font-medium" onclick="document.getElementById('rw-modal-{{ $block->id }}').classList.toggle('hidden')">
                        <i class="fas fa-star mr-1.5"></i>Write a review
                    </button>
                    <div id="rw-modal-{{ $block->id }}" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70" onclick="if(event.target===this)this.classList.add('hidden')">
                        <form method="POST" action="{{ url('/' . $link->alias . '/reviews') }}" enctype="multipart/form-data" class="glass-block rounded-2xl p-5 w-full max-w-md max-h-[90vh] overflow-y-auto">
                            @csrf
                            <div class="flex items-center justify-between mb-3"><h4 class="font-semibold">Write a review</h4><button type="button" onclick="document.getElementById('rw-modal-{{ $block->id }}').classList.add('hidden')" class="text-white/40 hover:text-white">&times;</button></div>
                            <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                            <div class="flex gap-1 mb-3" data-rw-stars>
                                @for($star = 1; $star <= 5; $star++)<button type="button" data-star="{{ $star }}" class="text-2xl text-white/20 hover:text-yellow-400"><i class="fas fa-star"></i></button>@endfor
                                <input type="hidden" name="rating" value="">
                            </div>
                            <input type="text" name="author_name" placeholder="Your name" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2.5 text-sm mb-2">
                            @php $rwRequireVerification = $s['require_verification'] ?? false; @endphp
                            @if(($s['collect_email'] ?? true) || $rwRequireVerification)<input type="email" name="author_email" placeholder="{{ $rwRequireVerification ? 'Your email (required to verify, kept private)' : 'Your email (kept private)' }}" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2.5 text-sm mb-2" {{ $rwRequireVerification ? 'required' : '' }}>@endif
                            <textarea name="body" rows="3" placeholder="Share your experience…" class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2.5 text-sm mb-2"></textarea>
                            @if($s['collect_media'] ?? true)<input type="file" name="media[]" multiple accept="image/*,audio/*,video/*" class="w-full text-xs text-white/60 mb-2">@endif
                            <button type="submit" class="bio-btn block w-full text-center py-2.5 text-sm font-medium">Submit review</button>
                        </form>
                    </div>
                    @endif
                </div>

            @elseif(in_array($block->type, ['timeline', 'timeline_staged']))
                <div class="mb-4 glass-block rounded-xl p-5">
                    <div class="relative pl-6 border-l-2 border-purple-500/30 space-y-4">
                        @foreach(($s['items'] ?? []) as $item)
                        @php $dotColor = ($block->type === 'timeline_staged') ? match($item['status'] ?? 'upcoming') { 'completed' => 'bg-green-400', 'active' => 'bg-purple-400 animate-pulse', default => 'bg-white/30' } : 'bg-purple-400'; @endphp
                        <div class="relative">
                            <div class="absolute -left-[25px] w-3 h-3 rounded-full {{ $dotColor }}"></div>
                            <p class="text-sm font-medium">{{ $item['title'] ?? '' }}</p>
                            @if(!empty($item['description']))<p class="text-xs mt-0.5" style="color:{{ $fontColor }}88">{{ $item['description'] }}</p>@endif
                            @if(!empty($item['date']))<p class="text-xs mt-0.5 text-purple-400/60">{{ $item['date'] }}</p>@endif
                        </div>
                        @endforeach
                    </div>
                </div>

            @elseif($block->type === 'quiz')
                <div class="mb-4 glass-block rounded-xl p-5 text-center">
                    <i class="fas fa-brain text-2xl mb-2 text-purple-400"></i>
                    <p class="text-sm font-semibold">{{ $s['title'] ?? 'Quiz' }}</p>
                    <p class="text-xs text-white/40 mt-1">Interactive quiz</p>
                </div>

            {{-- BUSINESS --}}
            @elseif(in_array($block->type, ['catalog', 'market']))
                <div class="mb-4 space-y-2">
                    @foreach(($s['items'] ?? []) as $item)
                    <div class="glass-block rounded-xl p-3 flex items-center gap-3">
                        @if(!empty($item['image']))<img src="{{ $item['image'] }}" class="w-14 h-14 rounded-lg object-cover" alt="">@endif
                        <div class="flex-1 min-w-0"><p class="font-medium text-sm truncate">{{ $item['name'] ?? '' }}</p>@if(!empty($item['price']))<p class="text-xs text-purple-400">{{ $item['price'] }}</p>@endif</div>
                        @if(!empty($item['url']))<a href="{{ $item['url'] }}" target="_blank" class="bio-btn px-3 py-1.5 text-xs font-medium">View</a>@endif
                    </div>
                    @endforeach
                </div>

            @elseif($block->type === 'donation')
                <div class="mb-4 glass-block rounded-xl p-5 text-center">
                    <i class="fas fa-hand-holding-heart text-2xl mb-2 text-pink-400"></i>
                    <p class="font-semibold text-sm">{{ $s['title'] ?? 'Support Us' }}</p>
                    @if(!empty($s['description']))<p class="text-xs mt-1" style="color:{{ $fontColor }}88">{{ $s['description'] }}</p>@endif
                    <div class="flex justify-center gap-2 mt-3 flex-wrap">
                        @foreach(($s['amounts'] ?? [5,10,25]) as $amt)
                        <a href="{{ ($s['url'] ?? '#') }}" target="_blank" class="bio-btn px-4 py-2 text-sm font-medium">${{ $amt }}</a>
                        @endforeach
                    </div>
                </div>

            @elseif($block->type === 'coupon')
                <div class="mb-4 glass-block rounded-xl p-5 text-center" x-data="{ copied: false }">
                    <i class="fas fa-ticket-alt text-2xl mb-2 text-yellow-400"></i>
                    <p class="text-xs mb-2" style="color:{{ $fontColor }}88">{{ $s['description'] ?? '' }}</p>
                    <div class="flex items-center justify-center gap-2">
                        <code class="px-4 py-2 rounded-lg bg-white/10 border border-dashed border-white/20 font-mono text-lg font-bold tracking-wider">{{ $s['code'] ?? '' }}</code>
                        <button @click="navigator.clipboard.writeText('{{ $s['code'] ?? '' }}'); copied = true; setTimeout(() => copied = false, 2000)" class="bio-btn px-3 py-2 text-sm"><i class="fas" :class="copied ? 'fa-check' : 'fa-copy'"></i></button>
                    </div>
                    @if(!empty($s['expires']))<p class="text-xs text-white/30 mt-2">Expires: {{ $s['expires'] }}</p>@endif
                </div>

            @elseif($block->type === 'one_time_offer')
                <div class="mb-4 glass-block rounded-xl p-5 text-center border border-yellow-500/20">
                    <p class="text-xs font-bold uppercase tracking-wider text-yellow-400 mb-1">Limited Offer</p>
                    <p class="font-semibold">{{ $s['title'] ?? '' }}</p>
                    @if(!empty($s['description']))<p class="text-xs mt-1" style="color:{{ $fontColor }}88">{{ $s['description'] }}</p>@endif
                    <div class="flex items-baseline justify-center gap-2 mt-2">
                        @if(!empty($s['original_price']))<span class="text-sm line-through text-white/30">{{ $s['original_price'] }}</span>@endif
                        <span class="text-2xl font-bold text-yellow-400">{{ $s['price'] ?? '' }}</span>
                    </div>
                    @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn block w-full mt-3 py-2.5 text-sm font-medium" style="background: linear-gradient(135deg, #f59e0b, #ef4444);">Grab Now</a>@endif
                </div>

            @elseif($block->type === 'paypal')
                <div class="mb-4">
                    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
                        <input type="hidden" name="cmd" value="_xclick"><input type="hidden" name="business" value="{{ $s['email'] ?? '' }}">
                        <input type="hidden" name="amount" value="{{ $s['amount'] ?? '' }}"><input type="hidden" name="currency_code" value="{{ $s['currency'] ?? 'USD' }}">
                        <button type="submit" class="bio-btn w-full py-3.5 text-sm font-semibold flex items-center justify-center gap-2">
                            <i class="fab fa-paypal"></i>{{ $s['button_text'] ?? 'Pay with PayPal' }}
                        </button>
                    </form>
                </div>

            @elseif($block->type === 'chart_pie')
                @php $total = array_sum(array_column($s['items'] ?? [], 'value')); $offset = 0; @endphp
                <div class="mb-4 glass-block rounded-xl p-5 flex items-center gap-4">
                    <svg viewBox="0 0 36 36" class="w-24 h-24 flex-shrink-0">
                        @foreach(($s['items'] ?? []) as $item)
                        @php $pct = $total > 0 ? ($item['value'] / $total * 100) : 0; @endphp
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="{{ $item['color'] ?? '#7c3aed' }}" stroke-width="3.8" stroke-dasharray="{{ $pct }} {{ 100 - $pct }}" stroke-dashoffset="-{{ $offset }}" transform="rotate(-90 18 18)"></circle>
                        @php $offset += $pct; @endphp
                        @endforeach
                    </svg>
                    <div class="space-y-1 text-xs">@foreach(($s['items'] ?? []) as $item)<div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full" style="background:{{ $item['color'] ?? '#7c3aed' }}"></span>{{ $item['label'] ?? '' }}</div>@endforeach</div>
                </div>

            @elseif($block->type === 'share')
                @php $shareUrl = urlencode(request()->url()); $shareText = urlencode($s['text'] ?? ''); @endphp
                <div class="mb-4 glass-block rounded-xl p-4">
                    <p class="text-sm font-medium text-center mb-3">{{ $s['text'] ?? 'Share this page' }}</p>
                    <div class="flex justify-center gap-3">
                        <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareText }}" target="_blank" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition"><i class="fab fa-x-twitter"></i></a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" target="_blank" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition"><i class="fab fa-facebook-f" style="color:#1877F2"></i></a>
                        <a href="https://www.linkedin.com/shareArticle?url={{ $shareUrl }}" target="_blank" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition"><i class="fab fa-linkedin-in" style="color:#0A66C2"></i></a>
                        <a href="https://wa.me/?text={{ $shareText }}%20{{ $shareUrl }}" target="_blank" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition"><i class="fab fa-whatsapp" style="color:#25D366"></i></a>
                    </div>
                </div>

            @elseif($block->type === 'nav_menu')
                <div class="mb-4 flex flex-wrap justify-center gap-2">
                    @foreach(($s['items'] ?? []) as $item)
                    <a href="{{ $item['url'] ?? '#' }}" class="px-4 py-2 text-xs font-medium glass-block rounded-full hover:bg-white/10 transition">{{ $item['text'] ?? '' }}</a>
                    @endforeach
                </div>

            @elseif($block->type === 'ticker')
                <div class="mb-4 glass-block rounded-xl overflow-hidden py-2">
                    <div class="ticker-scroll whitespace-nowrap text-sm">
                        @foreach(($s['items'] ?? []) as $item)<span class="mx-6">{{ $item }}</span>@endforeach
                        @foreach(($s['items'] ?? []) as $item)<span class="mx-6">{{ $item }}</span>@endforeach
                    </div>
                </div>

            {{-- LAYOUT --}}
            @elseif(in_array($block->type, ['card_slider', 'scroll_cards']))
                <div class="mb-4 flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory" style="scrollbar-width: thin;">
                    @foreach(($s['cards'] ?? $s['items'] ?? []) as $card)
                    <div class="glass-block rounded-xl flex-shrink-0 w-64 snap-center overflow-hidden">
                        @if(!empty($card['image']))<img src="{{ $card['image'] }}" class="w-full h-32 object-cover" alt="">@endif
                        <div class="p-3"><p class="font-medium text-sm">{{ $card['title'] ?? $card['name'] ?? '' }}</p>@if(!empty($card['description']))<p class="text-xs mt-1" style="color:{{ $fontColor }}88">{{ $card['description'] }}</p>@endif
                        @if(!empty($card['url']))<a href="{{ $card['url'] }}" target="_blank" class="text-xs text-purple-400 mt-2 inline-block">View &rarr;</a>@endif</div>
                    </div>
                    @endforeach
                </div>

            @elseif(str_starts_with($block->type, 'profile_card'))
                @include('common.biolink-profile-card', [
                    'block'       => $block,
                    's'           => $s,
                    'blockStyle'  => $blockStyle,
                    'blockInline' => $blockInline,
                    'fontColor'   => $fontColor,
                    'socialIcons' => $socialIcons,
                ])

            {{-- INTEGRATIONS --}}
            @elseif($block->type === 'typeform')
                <div class="mb-4 rounded-xl overflow-hidden"><iframe src="{{ $s['url'] ?? '' }}" class="w-full rounded-xl" style="height:500px;" frameborder="0" loading="lazy"></iframe></div>

            @elseif($block->type === 'calendly')
                <div class="mb-4 rounded-xl overflow-hidden"><iframe src="{{ $s['url'] ?? '' }}" class="w-full rounded-xl" style="height:630px;" frameborder="0" loading="lazy"></iframe></div>

            @elseif($block->type === 'discord_server')
                <div class="mb-4 rounded-xl overflow-hidden"><iframe src="https://discord.com/widget?id={{ $s['server_id'] ?? '' }}&theme=dark" class="w-full rounded-xl" height="350" frameborder="0" loading="lazy"></iframe></div>

            @elseif(in_array($block->type, ['facebook_post', 'reddit_post', 'telegram_post']))
                <div class="mb-4 glass-block rounded-xl p-4 text-center">
                    @php $platform = match($block->type) { 'facebook_post' => ['fab fa-facebook', 'Facebook', '#1877F2'], 'reddit_post' => ['fab fa-reddit', 'Reddit', '#FF4500'], 'telegram_post' => ['fab fa-telegram', 'Telegram', '#26A5E4'] }; @endphp
                    <i class="{{ $platform[0] }} text-2xl mb-2" style="color:{{ $platform[2] }}"></i>
                    <a href="{{ $s['url'] ?? '#' }}" target="_blank" class="bio-btn inline-block px-5 py-2 text-sm font-medium mt-2">View on {{ $platform[1] }}</a>
                </div>

            {{-- FILES --}}
            @elseif($block->type === 'external_item')
                <a href="{{ $s['url'] ?? '#' }}" target="_blank" class="mb-3 glass-block rounded-xl overflow-hidden block hover:bg-white/[0.06] transition">
                    @if(!empty($s['image']))<img src="{{ $s['image'] }}" class="w-full h-40 object-cover" alt="">@endif
                    <div class="p-4"><p class="font-medium text-sm">{{ $s['title'] ?? '' }}</p>@if(!empty($s['description']))<p class="text-xs mt-1" style="color:{{ $fontColor }}88">{{ $s['description'] }}</p>@endif</div>
                </a>

            @elseif($block->type === 'map')
                @php $mapQ = urlencode($s['address'] ?? ''); @endphp
                <div class="mb-4 rounded-xl overflow-hidden aspect-video glass-block">
                    <iframe src="https://maps.google.com/maps?q={{ $mapQ }}&z={{ $s['zoom'] ?? 14 }}&output=embed" class="w-full h-full rounded-xl" frameborder="0" style="border:0;filter:invert(90%) hue-rotate(180deg);" loading="lazy"></iframe>
                </div>

            @elseif($block->type === 'yandex_maps')
                @php $yQ = urlencode($s['address'] ?? ''); @endphp
                <div class="mb-4 rounded-xl overflow-hidden aspect-video glass-block">
                    <iframe src="https://yandex.com/map-widget/v1/?text={{ $yQ }}&z={{ $s['zoom'] ?? 14 }}" class="w-full h-full rounded-xl" frameborder="0" loading="lazy"></iframe>
                </div>

            {{-- CARD / GRID / AUTO-FIT GRID CONTAINERS --}}
            @elseif(\App\Modules\User\Models\BiolinkBlock::isContainerType($block->type))
                @php
                    $cardChildren = $block->activeChildren()->get()->filter(fn($b) => $b->isVisible());
                    $gap = intval($s['gap'] ?? 12);
                    $isCard = $block->type === 'card';
                    $isAutoGrid = $block->type === 'grid_auto';

                    if ($isCard) {
                        // Styled card container — keeps its background / overlay /
                        // border / shadow chrome and a fixed column count.
                        $cols = intval($s['columns'] ?? 2) ?: 2;
                        $pad = intval($s['padding'] ?? 16);
                        $br = intval($s['border_radius'] ?? 16);
                        $bgType = $s['bg_type'] ?? 'glass';
                        $bw = intval($s['border_width'] ?? 1);
                        $bc = $s['border_color'] ?? 'rgba(255,255,255,0.08)';
                        $shadow = match($s['shadow'] ?? 'none') {
                            'sm' => '0 1px 3px ' . ($s['shadow_color'] ?? '#00000040'),
                            'md' => '0 4px 12px ' . ($s['shadow_color'] ?? '#00000040'),
                            'lg' => '0 10px 30px ' . ($s['shadow_color'] ?? '#00000040'),
                            'xl' => '0 20px 50px ' . ($s['shadow_color'] ?? '#00000040'),
                            default => 'none',
                        };
                        $bgStyle = match($bgType) {
                            'glass' => 'background:rgba(255,255,255,' . (intval($s['glass_opacity'] ?? 6) / 100) . ');backdrop-filter:blur(' . intval($s['glass_blur'] ?? 12) . 'px);-webkit-backdrop-filter:blur(' . intval($s['glass_blur'] ?? 12) . 'px);',
                            'color' => 'background:' . ($s['bg_color'] ?? 'rgba(255,255,255,0.06)') . ';',
                            'gradient' => 'background:' . ($s['bg_gradient'] ?? 'linear-gradient(135deg,#7c3aed,#ec4899)') . ';',
                            'image' => 'background:url(' . ($s['bg_image'] ?? '') . ') center/cover no-repeat;',
                            'transparent' => 'background:transparent;',
                            default => 'background:rgba(255,255,255,0.06);',
                        };
                        $containerStyle = $bgStyle . ' padding:' . $pad . 'px; border-radius:' . $br . 'px; border:' . $bw . 'px solid ' . $bc . '; box-shadow:' . $shadow . ';';
                        $gridTemplate = 'repeat(' . $cols . ', 1fr)';
                    } elseif ($isAutoGrid) {
                        // Auto-fit responsive grid — plain (no chrome). Columns are
                        // derived from a minimum item width; children wrap as space
                        // allows so each child occupies one auto-sized cell.
                        $pad = intval($s['padding'] ?? 0);
                        $minW = intval($s['min_width'] ?? 140) ?: 140;
                        $cols = 0; // auto-fit: no fixed column count for child span maths
                        $containerStyle = 'padding:' . $pad . 'px;';
                        $gridTemplate = 'repeat(auto-fit, minmax(' . $minW . 'px, 1fr))';
                    } else {
                        // Plain column grid — no background / overlay, just columns,
                        // gap and padding.
                        $cols = intval($s['columns'] ?? 2) ?: 2;
                        $pad = intval($s['padding'] ?? 0);
                        $containerStyle = 'padding:' . $pad . 'px;';
                        $gridTemplate = 'repeat(' . $cols . ', 1fr)';
                    }
                @endphp
                <div class="mb-4 {{ $isCard ? 'card-container-render' : 'grid-container-render' }}" style="{{ $containerStyle }}">
                    @if(!empty($s['title']))
                    <div class="mb-3 text-sm font-semibold" style="color: {{ $fontColor ?? '#fff' }}cc;">{{ $s['title'] }}</div>
                    @endif
                    <div style="display:grid; grid-template-columns:{{ $gridTemplate }}; gap:{{ $gap }}px;">
                        @foreach($cardChildren as $childBlock)
                            @php
                                $cs = $childBlock->settings ?? [];
                                $childStyle = \App\Modules\User\Models\BiolinkBlock::getBlockStyle($cs, $globalTheme);
                                $childInline = \App\Modules\User\Models\BiolinkBlock::buildInlineStyle($childStyle);
                                $childHasStyle = !empty($cs['_style']) || (!empty($globalTheme) && ($globalTheme['apply_to_all'] ?? false));
                                $childIsBtnLike = in_array($childBlock->type, ['link', 'link_big', 'cta_button', 'button']);
                                $childSkipWrap = in_array($childBlock->type, ['avatar', 'divider', 'spacer', 'social_icons']) || $childIsBtnLike;
                                $childBtnInline = ($childIsBtnLike && $childHasStyle) ? $childInline : '';
                                // Fixed-column containers honour the child's grid_span;
                                // auto-fit grids give every child a single cell.
                                $childSpanRaw = intval($childStyle['grid_span'] ?? 12) ?: 12;
                                $childSpan = $cols > 0 ? min(max(1, (int)round($childSpanRaw / 12 * $cols)), $cols) : 1;
                            @endphp
                            <div style="grid-column: span {{ $childSpan }};">
                            @if($childHasStyle && !$childSkipWrap)<div class="block-styled" style="{{ $childInline }}">@endif
                                @include('common.partials.biolink-block-render', ['block' => $childBlock, 's' => $cs, 'fontColor' => $fontColor ?? '#fff', 'btnInline' => $childBtnInline])
                            @if($childHasStyle && !$childSkipWrap)</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>

            {{-- IDENTITY --}}
            @elseif($block->type === 'vcard')
                <div class="mb-4 glass-block rounded-xl p-5 text-center">
                    <div class="w-16 h-16 rounded-full bg-purple-500/20 flex items-center justify-center mx-auto mb-3"><i class="fas fa-address-book text-2xl text-purple-400"></i></div>
                    <p class="font-semibold">{{ $s['name'] ?? '' }}</p>
                    @if(!empty($s['title']))<p class="text-xs text-purple-400">{{ $s['title'] }}</p>@endif
                    @if(!empty($s['company']))<p class="text-xs text-white/40">{{ $s['company'] }}</p>@endif
                    <div class="flex justify-center gap-4 mt-3 text-sm">
                        @if(!empty($s['phone']))<a href="tel:{{ $s['phone'] }}" class="text-purple-400"><i class="fas fa-phone"></i></a>@endif
                        @if(!empty($s['email']))<a href="mailto:{{ $s['email'] }}" class="text-purple-400"><i class="fas fa-envelope"></i></a>@endif
                        @if(!empty($s['website']))<a href="{{ $s['website'] }}" target="_blank" class="text-purple-400"><i class="fas fa-globe"></i></a>@endif
                    </div>
                    <button onclick="downloadVCard()" class="bio-btn mt-3 px-5 py-2 text-sm font-medium">Save Contact</button>
                    <script>
                    function downloadVCard(){
                        var vcard = "BEGIN:VCARD\nVERSION:3.0\nN:{{ $s['name'] ?? '' }}\nFN:{{ $s['name'] ?? '' }}\nORG:{{ $s['company'] ?? '' }}\nTITLE:{{ $s['title'] ?? '' }}\nTEL:{{ $s['phone'] ?? '' }}\nEMAIL:{{ $s['email'] ?? '' }}\nURL:{{ $s['website'] ?? '' }}\nEND:VCARD";
                        var blob = new Blob([vcard], {type:'text/vcard'});
                        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = '{{ $s['name'] ?? 'contact' }}.vcf'; a.click();
                    }
                    </script>
                </div>

            {{-- SUBSCRIPTION BLOCKS --}}

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
@endif


@once
    @push('scripts')
        <script src="{{ asset('js/community-public.js') }}" defer></script>
    @endpush
@endonce
