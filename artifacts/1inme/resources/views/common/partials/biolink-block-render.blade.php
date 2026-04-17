@if($block->type === 'avatar')
    <div class="flex justify-center mb-4">
        @if(!empty($s['url']))
            <img src="{{ $s['url'] }}" alt="Avatar"
                 class="{{ ($s['rounded'] ?? true) ? 'rounded-full' : 'rounded-2xl' }} object-cover border-2 border-white/10"
                 style="width: {{ $s['size'] ?? 96 }}px; height: {{ $s['size'] ?? 96 }}px;">
        @else
            <div class="rounded-full bg-white/10 backdrop-blur flex items-center justify-center border-2 border-white/10"
                 style="width: {{ $s['size'] ?? 96 }}px; height: {{ $s['size'] ?? 96 }}px;">
                <span class="text-3xl font-bold">{{ strtoupper(substr($link->title ?: 'B', 0, 1)) }}</span>
            </div>
        @endif
    </div>

@elseif($block->type === 'heading')
    @php $hs = match($s['size'] ?? 'h2') { 'h1' => 'text-2xl md:text-3xl', 'h2' => 'text-xl md:text-2xl', 'h3' => 'text-lg md:text-xl', default => 'text-xl md:text-2xl' }; @endphp
    <div class="mb-3 text-{{ $s['align'] ?? 'center' }}"><h2 class="{{ $hs }} font-bold">{{ $s['text'] ?? '' }}</h2></div>

@elseif($block->type === 'heading_gradient')
    @php $hs = match($s['size'] ?? 'h2') { 'h1' => 'text-2xl md:text-3xl', 'h2' => 'text-xl md:text-2xl', 'h3' => 'text-lg md:text-xl', default => 'text-xl md:text-2xl' }; @endphp
    <div class="mb-3 text-{{ $s['align'] ?? 'center' }}">
        <h2 class="{{ $hs }} font-bold bg-clip-text text-transparent" style="background-image: linear-gradient(to right, {{ $s['from_color'] ?? '#7c3aed' }}, {{ $s['to_color'] ?? '#ec4899' }});">{{ $s['text'] ?? '' }}</h2>
    </div>

@elseif($block->type === 'heading_logo')
    <div class="mb-3 text-{{ $s['align'] ?? 'center' }} flex items-center justify-{{ $s['align'] ?? 'center' }} gap-3">
        @if(!empty($s['logo_url']))<img src="{{ $s['logo_url'] }}" alt="" class="h-8 w-8 object-contain">@endif
        @php $hs = match($s['size'] ?? 'h2') { 'h1' => 'text-2xl md:text-3xl', 'h2' => 'text-xl md:text-2xl', 'h3' => 'text-lg md:text-xl', default => 'text-xl md:text-2xl' }; @endphp
        <h2 class="{{ $hs }} font-bold">{{ $s['text'] ?? '' }}</h2>
    </div>

@elseif($block->type === 'heading_morph')
    @php $hs = match($s['size'] ?? 'h1') { 'h1' => 'text-3xl md:text-4xl', 'h2' => 'text-2xl md:text-3xl', default => 'text-3xl md:text-4xl' }; @endphp
    <div class="mb-3 text-{{ $s['align'] ?? 'center' }}"><h2 class="{{ $hs }} font-bold morph-text">{{ $s['text'] ?? '' }}</h2></div>

@elseif($block->type === 'paragraph')
    <div class="mb-4 text-{{ $s['align'] ?? 'center' }}"><p class="text-sm leading-relaxed" style="color: {{ $fontColor }}cc">{{ $s['text'] ?? '' }}</p></div>

@elseif($block->type === 'paragraph_rich')
    <div class="mb-4 prose prose-invert prose-sm max-w-none">{!! strip_tags($s['html'] ?? '', '<p><br><a><strong><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div><img><blockquote><hr>') !!}</div>

@elseif($block->type === 'link')
    <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
       class="bio-btn block w-full px-6 py-3.5 mb-3 text-center font-medium transition-all duration-300 flex items-center justify-center gap-3">
        @if(!empty($s['thumbnail']))<img src="{{ $s['thumbnail'] }}" class="w-6 h-6 rounded object-cover" alt="">
        @elseif(!empty($s['icon']))<i class="{{ $s['icon'] }}"></i>@endif
        <span>{{ $s['text'] ?? 'Link' }}</span>
    </a>

@elseif($block->type === 'link_big')
    <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
       class="block w-full mb-3 rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
       style="background: {{ $s['bg_color'] ?? ($btnColor ?? '#7c3aed') }};">
        <div class="px-6 py-5 flex items-center gap-4">
            @if(!empty($s['thumbnail']))<img src="{{ $s['thumbnail'] }}" class="w-12 h-12 rounded-xl object-cover" alt="">
            @elseif(!empty($s['icon']))<div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center"><i class="{{ $s['icon'] }} text-xl"></i></div>@endif
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-white truncate">{{ $s['text'] ?? 'Link' }}</p>
                @if(!empty($s['description']))<p class="text-xs text-white/60 mt-0.5 truncate">{{ $s['description'] }}</p>@endif
            </div>
            <i class="fas fa-arrow-right text-white/40"></i>
        </div>
    </a>

@elseif($block->type === 'divider')
    <div class="my-4 px-4"><hr style="border-style: {{ $s['style'] ?? 'solid' }}; border-color: {{ $s['color'] ?? 'rgba(255,255,255,0.1)' }}; border-width: 1px 0 0 0;"></div>

@elseif($block->type === 'spacer')
    <div style="height: {{ $s['height'] ?? 20 }}px"></div>

@elseif($block->type === 'image')
    @php
        $imgSt = $s['_image_style'] ?? [];
        $imgInline = \App\Modules\User\Models\BiolinkBlock::buildImageInlineStyle($imgSt);
        $imgLk = $s['_link'] ?? [];
        $imgLinkUrl = $imgLk['url'] ?? $s['link'] ?? '';
        $imgTrackUrl = $imgLinkUrl ? route('redirect.block', ['alias' => $link->alias, 'blockId' => $block->id]) : '';
        $imgTarget = $imgLk['target'] ?? '_blank';
        $imgRel = $imgLk['rel'] ?? 'noopener';
        $imgTitle = $imgLk['title'] ?? '';
    @endphp
    <div class="mb-4 overflow-hidden{{ empty($imgSt['mask_shape']) || ($imgSt['mask_shape'] ?? 'none') === 'none' ? ' rounded-xl' : '' }}">
        @if($imgTrackUrl)<a href="{{ $imgTrackUrl }}" target="{{ $imgTarget }}" rel="{{ $imgRel }}"{{ $imgTitle ? ' title="'.e($imgTitle).'"' : '' }}>@endif
        <img src="{{ $s['url'] ?? '' }}" alt="{{ $s['alt'] ?? '' }}" class="w-full{{ empty($imgInline) ? ' rounded-xl' : '' }}" style="{{ $imgInline }}">
        @if($imgTrackUrl)</a>@endif
    </div>

@elseif($block->type === 'cta_button')
    <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
       class="block w-full mb-3 text-center font-semibold transition-all duration-300 hover:-translate-y-0.5"
       style="background: {{ $s['color'] ?? ($btnColor ?? '#7c3aed') }}; color: {{ $s['text_color'] ?? ($btnTextColor ?? '#fff') }};
              padding: {{ ($s['size'] ?? 'lg') === 'sm' ? '10px 20px' : (($s['size'] ?? 'lg') === 'md' ? '14px 24px' : '18px 32px') }};
              border-radius: {{ $btnRadius ?? '12px' }}; box-shadow: 0 6px 20px {{ $s['color'] ?? ($btnColor ?? '#7c3aed') }}40;
              font-size: {{ ($s['size'] ?? 'lg') === 'sm' ? '14px' : (($s['size'] ?? 'lg') === 'md' ? '16px' : '18px') }};">
        {{ $s['text'] ?? 'Click Here' }}
    </a>

@elseif($block->type === 'badge')
    <div class="mb-3 flex justify-center">
        <span class="inline-block px-4 py-1.5 rounded-full text-xs font-semibold" style="background:{{ $s['color'] ?? '#7c3aed' }}; color:{{ $s['text_color'] ?? '#fff' }}">{{ $s['text'] ?? '' }}</span>
    </div>

@elseif(in_array($block->type, ['socials', 'socials_multi', 'socials_custom']))
    @php
        $sz = $s['size'] ?? 'md';
        $szClass = match($sz) { 'sm' => 'w-9 h-9', 'lg' => 'w-14 h-14', default => 'w-11 h-11' };
        $allPlatforms = $s['platforms'] ?? [];
        if ($block->type === 'socials_multi' && isset($s['groups'])) {
            $allPlatforms = [];
            foreach ($s['groups'] as $group) {
                $allPlatforms = array_merge($allPlatforms, $group['platforms'] ?? []);
            }
        }
    @endphp
    <div class="flex justify-center gap-3 mb-4 flex-wrap">
        @foreach($allPlatforms as $platform)
            @php $icon = ($socialIcons ?? [])[$platform['name'] ?? ''] ?? ['fas fa-link', '#7c3aed']; @endphp
            <a href="{{ $platform['url'] ?? '#' }}" target="_blank" rel="noopener"
               class="{{ $szClass }} {{ ($s['style'] ?? '') === 'square' ? 'rounded-lg' : 'rounded-full' }} glass-block flex items-center justify-center transition-all hover:scale-110 hover:-translate-y-1"
               style="color: {{ $icon[1] }}"><i class="{{ $icon[0] }} {{ $sz === 'lg' ? 'text-xl' : 'text-lg' }}"></i></a>
        @endforeach
    </div>

@elseif(in_array($block->type, ['faq', 'faq_v2']))
    <div class="mb-4 space-y-2" x-data="{ open: null }">
        @foreach(($s['items'] ?? []) as $i => $item)
        <div class="glass-block rounded-xl overflow-hidden {{ $block->type === 'faq_v2' ? 'border border-white/10' : '' }}">
            <button @click="open = open === {{ $i }} ? null : {{ $i }}" class="w-full px-4 py-3 flex items-center justify-between text-left">
                <span class="text-sm font-medium flex items-center gap-2">@if(!empty($item['icon']))<i class="{{ $item['icon'] }}"></i>@endif{{ $item['question'] ?? '' }}</span>
                <i class="fas fa-chevron-down text-xs transition-transform" :class="open === {{ $i }} ? 'rotate-180' : ''"></i>
            </button>
            <div x-show="open === {{ $i }}" x-cloak class="px-4 pb-3"><p class="text-sm" style="color:{{ $fontColor }}99">{{ $item['answer'] ?? '' }}</p></div>
        </div>
        @endforeach
    </div>

@elseif($block->type === 'product')
    <div class="mb-4 glass-block rounded-xl overflow-hidden">
        @if(!empty($s['image']))<img src="{{ $s['image'] }}" alt="{{ $s['name'] ?? '' }}" class="w-full h-48 object-cover">@endif
        <div class="p-4">
            <div class="flex items-start justify-between">
                <div><p class="font-semibold text-sm">{{ $s['name'] ?? '' }}</p></div>
                @if(!empty($s['price']))<span class="font-bold text-lg">{{ $s['price'] }}</span>@endif
            </div>
            @if(!empty($s['description']))<p class="text-xs mt-2" style="color:{{ $fontColor }}88">{{ $s['description'] }}</p>@endif
            @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn block w-full text-center mt-3 py-2.5 text-sm font-medium">Buy Now</a>@endif
        </div>
    </div>

@elseif($block->type === 'service')
    <div class="mb-4 glass-block rounded-xl p-4">
        <div class="flex items-start gap-3">
            <div class="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center flex-shrink-0"><i class="{{ $s['icon'] ?? 'fas fa-star' }} text-purple-400"></i></div>
            <div class="flex-1">
                <p class="font-semibold text-sm">{{ $s['name'] ?? '' }}</p>
                @if(!empty($s['price']))<p class="text-xs text-purple-400 mt-0.5">{{ $s['price'] }}</p>@endif
                @if(!empty($s['description']))<p class="text-xs mt-1" style="color:{{ $fontColor }}88">{{ $s['description'] }}</p>@endif
            </div>
        </div>
        @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn block w-full text-center mt-3 py-2 text-sm font-medium">Learn More</a>@endif
    </div>

@elseif($block->type === 'testimonials')
    <div class="mb-4 space-y-3">
        @foreach(($s['items'] ?? []) as $item)
        <div class="glass-block rounded-xl p-4">
            <div class="flex items-center gap-3 mb-2">
                @if(!empty($item['avatar']))<img src="{{ $item['avatar'] }}" class="w-10 h-10 rounded-full object-cover" alt="">
                @else<div class="w-10 h-10 rounded-full bg-purple-500/20 flex items-center justify-center"><span class="text-sm font-bold">{{ strtoupper(substr($item['name'] ?? 'A', 0, 1)) }}</span></div>@endif
                <div><p class="text-sm font-medium">{{ $item['name'] ?? '' }}</p>
                <div class="flex gap-0.5">@for($star = 1; $star <= 5; $star++)<i class="fas fa-star text-xs {{ $star <= ($item['rating'] ?? 5) ? 'text-yellow-400' : 'text-white/20' }}"></i>@endfor</div></div>
            </div>
            <p class="text-sm" style="color:{{ $fontColor }}cc">{{ $item['text'] ?? '' }}</p>
        </div>
        @endforeach
    </div>

@elseif($block->type === 'alert')
    @php $alertColors = ['info' => 'border-violet-400/30 bg-violet-500/10', 'success' => 'border-green-400/30 bg-green-500/10', 'warning' => 'border-yellow-400/30 bg-yellow-500/10', 'error' => 'border-red-400/30 bg-red-500/10']; @endphp
    <div class="mb-4 rounded-xl p-4 border {{ $alertColors[$s['type'] ?? 'info'] ?? $alertColors['info'] }}">
        @php $_alertIcon = $s['icon'] ?? 'fa-info-circle'; if(!preg_match('/^fa[sbrl] /', $_alertIcon)) $_alertIcon = 'fas ' . $_alertIcon; @endphp
        <p class="text-sm flex items-center gap-2"><i class="{{ $_alertIcon }}"></i>{{ $s['text'] ?? '' }}</p>
    </div>

@elseif(in_array($block->type, ['list', 'list_numbered']))
    <div class="mb-4 glass-block rounded-xl p-4">
        @if($block->type === 'list')
            @php $_listIcon = $s['icon'] ?? 'fa-check'; if(!preg_match('/^fa[sbrl] /', $_listIcon)) $_listIcon = 'fas ' . $_listIcon; @endphp
            <ul class="space-y-2">@foreach(($s['items'] ?? []) as $item)<li class="flex items-start gap-2 text-sm"><i class="{{ $_listIcon }} text-purple-400 mt-0.5 text-xs"></i><span style="color:{{ $fontColor }}cc">{{ $item }}</span></li>@endforeach</ul>
        @else
            <ol class="space-y-2 list-decimal list-inside">@foreach(($s['items'] ?? []) as $item)<li class="text-sm" style="color:{{ $fontColor }}cc">{{ $item }}</li>@endforeach</ol>
        @endif
    </div>

@elseif($block->type === 'youtube')
    @php
        $videoId = $s['video_id'] ?? '';
        if (str_contains($videoId, 'youtube.com') || str_contains($videoId, 'youtu.be')) {
            preg_match('/(?:v=|\/)([\w-]{11})/', $videoId, $m);
            $videoId = $m[1] ?? $videoId;
        }
    @endphp
    <div class="mb-4 rounded-xl overflow-hidden aspect-video">
        <iframe src="https://www.youtube.com/embed/{{ $videoId }}" class="w-full h-full rounded-xl"
                frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
    </div>

@elseif($block->type === 'video')
    <div class="mb-4 rounded-xl overflow-hidden glass-block">
        <video class="w-full rounded-xl" controls {{ ($s['autoplay'] ?? false) ? 'autoplay muted' : '' }}>
            <source src="{{ $s['url'] ?? '' }}" type="video/mp4">
        </video>
    </div>

@elseif($block->type === 'spotify')
    @php $spotifyEmbed = str_replace('open.spotify.com', 'open.spotify.com/embed', $s['url'] ?? ''); @endphp
    <div class="mb-4 rounded-xl overflow-hidden">
        <iframe src="{{ $spotifyEmbed }}" class="w-full rounded-xl" height="{{ ($s['type'] ?? 'track') === 'track' ? '152' : '352' }}"
                frameborder="0" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
    </div>

@elseif($block->type === 'email_collector')
    <div class="mb-4 glass-block rounded-xl p-5 text-center">
        <p class="text-sm font-semibold mb-3">{{ $s['title'] ?? 'Subscribe' }}</p>
        <form class="flex gap-2" onsubmit="event.preventDefault(); this.querySelector('button').textContent='Done!'; this.querySelector('button').disabled=true;">
            <input type="email" required placeholder="{{ $s['placeholder'] ?? 'Your email' }}" class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-white/20" style="color:{{ $fontColor }}">
            <button type="submit" class="bio-btn px-5 py-2.5 text-sm font-medium whitespace-nowrap">{{ $s['button_text'] ?? 'Subscribe' }}</button>
        </form>
    </div>

@elseif($block->type === 'verified_heading')
    @php $vhSize = ($s['font_size'] ?? '24') . 'px'; @endphp
    <div class="mb-3 text-{{ $s['alignment'] ?? 'center' }}">
        <h2 class="font-bold inline-flex items-center gap-2" style="font-size: {{ $vhSize }};">
            {{ $s['text'] ?? '' }}
            <svg class="inline-block shrink-0" width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="12" fill="#1d9bf0"/><path d="M9.5 12.5l2 2 4-4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </h2>
    </div>

@elseif($block->type === 'verified_avatar')
    @php $vaSize = ($s['size'] ?? '100') . 'px'; $vaShape = ($s['shape'] ?? 'circle') === 'circle' ? '50%' : '12px'; @endphp
    <div class="mb-4 flex justify-center">
        <div class="relative inline-block">
            @if(!empty($s['image_url']))
            <img src="{{ $s['image_url'] }}" alt="" class="object-cover" style="width: {{ $vaSize }}; height: {{ $vaSize }}; border-radius: {{ $vaShape }}; border: 3px solid rgba(255,255,255,0.2);">
            @else
            <div class="flex items-center justify-center" style="width: {{ $vaSize }}; height: {{ $vaSize }}; border-radius: {{ $vaShape }}; background: rgba(124,58,237,0.2); border: 3px solid rgba(255,255,255,0.2);"><i class="fas fa-user text-2xl" style="color: rgba(255,255,255,0.5);"></i></div>
            @endif
            <div class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full flex items-center justify-center" style="background: #1d9bf0; border: 2px solid var(--bg-color, #0a0612);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 12.5l2.5 2.5 5-5" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>
    </div>

@elseif($block->type === 'email_subscribe')
    <div class="mb-4 glass-block rounded-2xl p-6" x-data="{ submitted: false, loading: false, error: '' }">
        <div class="text-center mb-4">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background: linear-gradient(135deg, rgba(124,58,237,0.3), rgba(168,85,247,0.2));">
                <i class="fas fa-envelope text-purple-400 text-lg"></i>
            </div>
            <p class="text-base font-semibold mb-1">{{ $s['title'] ?? 'Subscribe' }}</p>
            @if(!empty($s['description']))<p class="text-xs opacity-50 leading-relaxed">{{ $s['description'] }}</p>@endif
        </div>
        <template x-if="!submitted">
            <form @submit.prevent="
                loading = true; error = '';
                fetch('/{{ $link->alias }}/subscribe', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                    body: JSON.stringify({
                        block_id: {{ $block->id }},
                        type: 'email',
                        email: $refs.emailInput.value,
                        name: $refs.nameInput ? $refs.nameInput.value : ''
                    })
                }).then(r => r.json()).then(d => {
                    loading = false;
                    if(d.success) submitted = true;
                    else error = d.message || 'Something went wrong';
                }).catch(() => { loading = false; error = 'Network error'; })
            " class="space-y-3">
                @if($s['name_field'] ?? false)
                <input x-ref="nameInput" type="text" placeholder="Your name" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-500/40 transition" style="color:{{ $fontColor }}">
                @endif
                <input x-ref="emailInput" type="email" required placeholder="{{ $s['placeholder'] ?? 'Enter your email' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-500/40 transition" style="color:{{ $fontColor }}">
                <button type="submit" :disabled="loading" class="bio-btn w-full px-5 py-3 text-sm font-semibold rounded-xl flex items-center justify-center gap-2 transition-all">
                    <template x-if="loading"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                    <span x-text="loading ? 'Subscribing...' : '{{ $s['button_text'] ?? 'Subscribe' }}'"></span>
                </button>
                <p x-show="error" x-text="error" class="text-xs text-red-400 text-center" x-cloak></p>
            </form>
        </template>
        <template x-if="submitted">
            <div class="text-center py-3">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full mb-3" style="background: rgba(34,197,94,0.15);">
                    <i class="fas fa-check text-green-400 text-xl"></i>
                </div>
                <p class="text-sm font-semibold text-green-400">{{ $s['success_message'] ?? 'Thanks for subscribing!' }}</p>
            </div>
        </template>
    </div>

@elseif($block->type === 'whatsapp_channel_subscribe')
    <div class="mb-4 glass-block rounded-2xl p-5">
        <div class="text-center mb-4">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background: rgba(37,211,102,0.15);">
                <i class="fab fa-whatsapp text-xl" style="color: #25D366;"></i>
            </div>
            <p class="text-base font-semibold mb-1">{{ $s['title'] ?? 'Follow our Channel' }}</p>
            @if(!empty($s['description']))<p class="text-xs opacity-50 leading-relaxed">{{ $s['description'] }}</p>@endif
        </div>
        <a href="{{ $s['channel_url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block w-full py-3.5 text-center font-semibold rounded-xl text-sm flex items-center justify-center gap-3 transition-all hover:-translate-y-0.5 hover:shadow-lg"
           style="background: #25D366; color: #fff;"
           onclick="fetch('/{{ $link->alias }}/subscribe', {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({block_id:{{ $block->id }},type:'whatsapp_channel',channel_url:'{{ $s['channel_url'] ?? '' }}'})})">
            <i class="fab fa-whatsapp text-lg"></i>
            <span>{{ $s['button_text'] ?? 'Follow Channel' }}</span>
        </a>
    </div>

@elseif($block->type === 'whatsapp_number_subscribe')
    <div class="mb-4 glass-block rounded-2xl p-5" x-data="{ submitted: false, loading: false, error: '', phone: '' }">
        <div class="text-center mb-4">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background: rgba(37,211,102,0.15);">
                <i class="fab fa-whatsapp text-xl" style="color: #25D366;"></i>
            </div>
            <p class="text-base font-semibold mb-1">{{ $s['title'] ?? 'Subscribe via WhatsApp' }}</p>
            @if(!empty($s['description']))<p class="text-xs opacity-50 leading-relaxed">{{ $s['description'] }}</p>@endif
        </div>
        <template x-if="!submitted">
            <div class="space-y-3">
                @if($s['collect_phone'] ?? true)
                <input x-model="phone" type="tel" placeholder="Your WhatsApp number" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm outline-none focus:border-green-500/40 transition" style="color:{{ $fontColor }}">
                @endif
                <button @click="
                    loading = true; error = '';
                    fetch('/{{ $link->alias }}/subscribe', {
                        method: 'POST',
                        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                        body: JSON.stringify({
                            block_id: {{ $block->id }},
                            type: 'whatsapp_number',
                            phone: phone
                        })
                    }).then(r => r.json()).then(d => {
                        loading = false;
                        if(d.success) {
                            submitted = true;
                            window.open('https://wa.me/{{ preg_replace('/[^0-9]/', '', $s['phone'] ?? '') }}?text={{ urlencode($s['default_message'] ?? 'Hi! I want to subscribe.') }}', '_blank');
                        } else error = d.message || 'Something went wrong';
                    }).catch(() => { loading = false; error = 'Network error'; })
                " :disabled="loading"
                   class="block w-full py-3.5 text-center font-semibold rounded-xl text-sm flex items-center justify-center gap-3 transition-all hover:-translate-y-0.5 cursor-pointer"
                   style="background: #25D366; color: #fff;">
                    <template x-if="loading"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                    <i class="fab fa-whatsapp text-lg" x-show="!loading"></i>
                    <span x-text="loading ? 'Subscribing...' : '{{ $s['button_text'] ?? 'Subscribe on WhatsApp' }}'"></span>
                </button>
                <p x-show="error" x-text="error" class="text-xs text-red-400 text-center" x-cloak></p>
            </div>
        </template>
        <template x-if="submitted">
            <div class="text-center py-3">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full mb-3" style="background: rgba(34,197,94,0.15);">
                    <i class="fas fa-check text-green-400 text-xl"></i>
                </div>
                <p class="text-sm font-semibold text-green-400">Subscribed! Check WhatsApp.</p>
            </div>
        </template>
    </div>

@elseif($block->type === 'countdown')
    <div class="mb-4 glass-block rounded-xl p-5 text-center" x-data="countdown('{{ $s['target_date'] ?? '' }}')" x-init="start()">
        <p class="text-sm font-semibold mb-3">{{ $s['title'] ?? 'Coming Soon' }}</p>
        <div class="flex justify-center gap-4">
            <div><span class="text-2xl font-bold" x-text="days">0</span><p class="text-[10px] uppercase tracking-wider mt-1" style="color:{{ $fontColor }}66">Days</p></div>
            <div><span class="text-2xl font-bold" x-text="hours">0</span><p class="text-[10px] uppercase tracking-wider mt-1" style="color:{{ $fontColor }}66">Hours</p></div>
            <div><span class="text-2xl font-bold" x-text="minutes">0</span><p class="text-[10px] uppercase tracking-wider mt-1" style="color:{{ $fontColor }}66">Min</p></div>
            <div><span class="text-2xl font-bold" x-text="seconds">0</span><p class="text-[10px] uppercase tracking-wider mt-1" style="color:{{ $fontColor }}66">Sec</p></div>
        </div>
    </div>

@elseif($block->type === 'progress')
    <div class="mb-4 glass-block rounded-xl p-4 space-y-3">
        @foreach(($s['items'] ?? []) as $item)
        <div>
            <div class="flex justify-between text-xs mb-1"><span>{{ $item['label'] ?? '' }}</span><span>{{ $item['value'] ?? 0 }}%</span></div>
            <div class="w-full h-2 rounded-full bg-white/10"><div class="h-full rounded-full transition-all" style="width: {{ $item['value'] ?? 0 }}%; background: {{ $item['color'] ?? '#7c3aed' }};"></div></div>
        </div>
        @endforeach
    </div>

@elseif($block->type === 'custom_html')
    <div class="mb-4">{!! strip_tags($s['html'] ?? '', '<p><br><a><strong><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div><img><iframe><table><tr><td><th><thead><tbody><hr><blockquote><pre><code><style>') !!}</div>

@elseif($block->type === 'whatsapp_widget')
    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $s['phone'] ?? '') }}?text={{ urlencode($s['message'] ?? '') }}" target="_blank" rel="noopener"
       class="block w-full mb-3 rounded-2xl py-4 text-center font-semibold transition-all duration-300 hover:-translate-y-1 flex items-center justify-center gap-3"
       style="background: #25D366; color: #fff; border-radius: {{ $btnRadius ?? '12px' }};">
        <i class="fab fa-whatsapp text-xl"></i><span>{{ $s['button_text'] ?? 'Chat on WhatsApp' }}</span>
    </a>

@elseif($block->type === 'price')
    <div class="mb-4 glass-block rounded-xl p-5 text-center">
        <p class="text-sm font-medium mb-1">{{ $s['title'] ?? '' }}</p>
        <div class="flex items-baseline justify-center gap-1"><span class="text-3xl font-bold">{{ $s['amount'] ?? '' }}</span><span class="text-sm text-white/40">{{ $s['period'] ?? '' }}</span></div>
        @if(!empty($s['features']))<ul class="mt-3 space-y-1.5 text-sm text-left">@foreach(($s['features'] ?? []) as $f)<li class="flex items-center gap-2"><i class="fas fa-check text-green-400 text-xs"></i><span style="color:{{ $fontColor }}cc">{{ $f }}</span></li>@endforeach</ul>@endif
        @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn block w-full mt-4 py-2.5 text-sm font-medium">Get Started</a>@endif
    </div>

@elseif($block->type === 'notification')
    @php $nColors = ['info' => 'bg-violet-500/20 border-violet-400/30', 'success' => 'bg-green-500/20 border-green-400/30', 'warning' => 'bg-yellow-500/20 border-yellow-400/30']; @endphp
    <div class="mb-4 rounded-xl p-3 border {{ $nColors[$s['type'] ?? 'info'] ?? $nColors['info'] }} flex items-center gap-3" x-data="{ show: true }" x-show="show">
        <i class="fas fa-bell text-sm"></i><p class="text-sm flex-1">{{ $s['text'] ?? '' }}</p>
        @if($s['dismissible'] ?? true)<button @click="show = false" class="text-white/40 hover:text-white"><i class="fas fa-times text-xs"></i></button>@endif
    </div>

@elseif($block->type === 'qr_code')
    <div class="mb-4 glass-block rounded-xl p-5 flex justify-center">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size={{ $s['size'] ?? 200 }}x{{ $s['size'] ?? 200 }}&data={{ urlencode($s['url'] ?? request()->url()) }}&bgcolor=0f0a1a&color=ffffff" alt="QR Code" class="rounded-lg">
    </div>

@elseif($block->type === 'iframe_embed')
    <div class="mb-4 rounded-xl overflow-hidden"><iframe src="{{ $s['url'] ?? '' }}" class="w-full rounded-xl" style="height:{{ $s['height'] ?? 400 }}px;" frameborder="0" loading="lazy"></iframe></div>

@elseif($block->type === 'form')
    @php
        $formId = $s['form_id'] ?? null;
        $formModel = $formId ? \App\Modules\User\Models\Form::find($formId) : null;
    @endphp
    @if($formModel && $formModel->is_active)
        <div class="mb-4 rounded-xl overflow-hidden glass-block" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">
            <iframe src="{{ $formModel->getPublicUrl() }}/iframe"
                    class="w-full block"
                    style="height: {{ $s['height'] ?? 600 }}px; border: 0; background: transparent;"
                    loading="lazy"
                    data-form-frame="{{ $formModel->id }}"
                    title="{{ $formModel->title }}"></iframe>
        </div>
        <script>
            (function () {
                if (window.__1inmeFormResizeBound) return;
                window.__1inmeFormResizeBound = true;
                window.addEventListener('message', function (e) {
                    if (!e.data || e.data.type !== '1inme-form-resize') return;
                    document.querySelectorAll('iframe[data-form-frame]').forEach(function (f) {
                        if (f.contentWindow === e.source) f.style.height = (e.data.height + 4) + 'px';
                    });
                });
            })();
        </script>
    @else
        <div class="mb-4 glass-block rounded-xl p-4 text-center text-xs text-white/40">
            <i class="fas fa-wpforms mb-1"></i>
            <p>{{ $formModel ? 'This form is currently disabled.' : 'Form not configured.' }}</p>
        </div>
    @endif

@elseif($block->type === 'file')
    <a href="{{ $s['url'] ?? '#' }}" target="_blank" class="mb-3 glass-block rounded-xl p-4 flex items-center gap-3 block hover:bg-white/[0.06] transition">
        <div class="w-11 h-11 rounded-xl bg-purple-500/20 flex items-center justify-center"><i class="{{ $s['icon'] ?? 'fas fa-file-download' }} text-purple-400"></i></div>
        <div class="flex-1 min-w-0"><p class="font-medium text-sm truncate">{{ $s['name'] ?? 'Download File' }}</p>@if(!empty($s['size']))<p class="text-xs text-white/40">{{ $s['size'] }}</p>@endif</div>
        <i class="fas fa-download text-white/30"></i>
    </a>

@elseif($block->type === 'markdown')
    <div class="mb-4 glass-block rounded-xl p-4 prose prose-invert prose-sm max-w-none">{!! nl2br(e($s['content'] ?? '')) !!}</div>

@elseif($block->type === 'rsvp')
    @php
        $eventLinkId = $s['event_link_id'] ?? null;
        $eventLink   = $eventLinkId
            ? \App\Modules\User\Models\Link::where('id', $eventLinkId)
                ->where('user_id', $block->link?->user_id ?? 0)
                ->where('type', 'ics')->with('icsData')->first()
            : null;
    @endphp
    <div class="mb-4 glass-block rounded-xl p-4 text-left" style="background:#fff; color:#111;">
        @if(!$eventLink)
            <div class="text-xs text-center text-white/60 py-2">RSVP block not configured.</div>
        @elseif(empty(($eventLink->settings ?? [])['rsvp_enabled']))
            <div class="text-xs text-center text-white/60 py-2">RSVP collection is disabled for this event.</div>
        @else
            <div class="mb-3">
                <div class="text-xs uppercase tracking-wider opacity-60">{{ $s['heading'] ?? 'RSVP to' }}</div>
                <div class="font-bold text-base">{{ $eventLink->title }}</div>
                @if($eventLink->icsData)
                    <div class="text-xs opacity-70">
                        <i class="far fa-clock me-1"></i>
                        {{ \Carbon\Carbon::parse($eventLink->icsData->starts_at)->format('M j, Y · g:i A') }}
                    </div>
                @endif
            </div>
            @include('common.partials.rsvp-form-fields', [
                'link' => $eventLink,
                'action' => url('/' . $eventLink->alias . '/rsvp'),
                'sourceTag' => 'biolink_block:' . $block->id,
            ])
        @endif
    </div>

@else
    <div class="mb-4 glass-block rounded-xl p-4 text-center">
        <i class="fas fa-cube text-lg mb-1 text-purple-400/50"></i>
        <p class="text-xs text-white/40">{{ \App\Modules\User\Models\BiolinkBlock::TYPES[$block->type]['label'] ?? ucfirst(str_replace('_', ' ', $block->type)) }}</p>
    </div>
@endif
