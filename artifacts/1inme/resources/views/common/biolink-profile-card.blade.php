{{--
    Profile / identity card renderer (Task #1740).

    Ten ready-made identity designs for the profile_card_v1..v4 family,
    each differing in LAYOUT (avatar / cover / text / socials placement).
    The active layout comes from the curated `profile_identity` design's
    `_profile_layout` token (carried in `_style`); when no design has been
    applied we fall back to the block-type's historical layout so older
    blocks keep rendering.

    The card surface is skinned by $blockInline (bg / border / radius /
    shadow / text colour from the design); this partial owns ALL internal
    spacing and structure. The generic .block-styled wrapper is skipped for
    profile cards (see $skipWrap in biolink.blade.php) so we apply the
    surface style here and clip cover images with overflow-hidden.

    Vars (passed via @include): $block, $s, $blockStyle, $blockInline,
    $fontColor, $socialIcons.
--}}
@php
    $avatar   = $s['avatar'] ?? '';
    $name     = trim($s['name'] ?? '');
    $title    = trim($s['title'] ?? '');
    $bio      = trim($s['bio'] ?? '');
    $cover    = $s['cover'] ?? '';
    $verified = !empty($s['verified']);
    $location = trim($s['location'] ?? '');
    $website  = trim($s['website'] ?? '');
    $ctaLabel = trim($s['cta_label'] ?? '');
    $ctaUrl   = trim($s['cta_url'] ?? '');

    // Normalise the socials list — accept both {name} (profile-card form)
    // and {platform} (legacy socials shape) — dropping fully-empty rows.
    $psocials = [];
    foreach ((is_array($s['socials'] ?? null) ? $s['socials'] : []) as $soc) {
        if (!is_array($soc)) continue;
        $sn = $soc['name'] ?? $soc['platform'] ?? '';
        $su = $soc['url'] ?? '';
        if ($sn !== '' || $su !== '') $psocials[] = ['name' => $sn, 'url' => $su];
    }

    $stats  = is_array($s['stats'] ?? null) ? $s['stats'] : [];
    $badges = is_array($s['badges'] ?? null) ? $s['badges'] : [];

    // Active structural layout. Falls back to the historical per-type
    // layout when no `profile_identity` design has been applied.
    $layout = $s['_style']['_profile_layout'] ?? '';
    if ($layout === '') {
        $layout = match($block->type) {
            'profile_card_v2' => 'cover_hero',
            'profile_card_v3' => 'stats',
            'profile_card_v4' => 'badges',
            default           => 'classic_creator',
        };
    }

    // Per-layout accent (title / link / chip colour) — part of each
    // design's identity, so it's intrinsic to the layout rather than a
    // separately-stored token.
    $accent = match($layout) {
        'founder'                  => '#d4af37',
        'social_profile'           => '#3b82f6',
        'gradient'                 => '#ffffff',
        'glass'                    => '#c4b5fd',
        'minimal_dark', 'cover_hero' => '#a78bfa',
        default                    => '#7c3aed',
    };

    // Surface style. When the design sets no background we keep the page's
    // translucent glass-block look (matches the pre-#1740 default).
    $pBg       = $blockStyle['bg_color'] ?? '';
    $baseClass = ($pBg === '' || $pBg === null) ? 'glass-block' : '';
    $cardStyle = trim($blockInline);

    $initial = strtoupper(mb_substr($name !== '' ? $name : 'U', 0, 1));
@endphp

@php
    // Reusable avatar fallback colours.
    $avatarBg = 'rgba(124,58,237,0.20)';
@endphp

{{-- ───────────────────────────── CLASSIC CREATOR ───────────────────── --}}
@if($layout === 'classic_creator')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        @if($cover)<div class="h-28 bg-cover bg-center" style="background-image:url('{{ $cover }}')"></div>@endif
        <div class="px-5 pb-6 text-center {{ $cover ? '-mt-12' : 'pt-6' }}">
            <div class="flex justify-center">
                @if($avatar)<img src="{{ $avatar }}" class="w-24 h-24 rounded-full object-cover" style="border:4px solid #ffffff;box-shadow:0 6px 18px rgba(0,0,0,0.18)" alt="">
                @else<div class="w-24 h-24 rounded-full flex items-center justify-center text-2xl font-bold" style="border:4px solid #ffffff;background:{{ $avatarBg }}">{{ $initial }}</div>@endif
            </div>
            @if($name)<p class="mt-3 text-lg font-bold">{{ $name }}</p>@endif
            @if($title)<p class="text-sm font-medium" style="color:{{ $accent }}">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3" style="opacity:.72">{{ $bio }}</p>@endif
        </div>
    </div>

{{-- ───────────────────────────── MODERN GLASSMORPHISM ──────────────── --}}
@elseif($layout === 'glass')
    <div class="mb-4 overflow-hidden rounded-2xl relative {{ $baseClass }}" style="{{ $cardStyle }}">
        @if($cover)
            <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('{{ $cover }}');opacity:.30"></div>
            <div class="absolute inset-0" style="background:linear-gradient(160deg,rgba(124,58,237,0.40),rgba(236,72,153,0.28))"></div>
        @endif
        <div class="relative px-5 py-7 text-center text-white">
            <div class="flex justify-center">
                @if($avatar)<img src="{{ $avatar }}" class="w-20 h-20 rounded-full object-cover" style="border:3px solid rgba(255,255,255,0.55)" alt="">
                @else<div class="w-20 h-20 rounded-full flex items-center justify-center text-xl font-bold" style="border:3px solid rgba(255,255,255,0.55);background:rgba(255,255,255,0.12)">{{ $initial }}</div>@endif
            </div>
            @if($name)<p class="mt-3 text-lg font-bold">{{ $name }}</p>@endif
            @if($title)<p class="text-sm" style="color:{{ $accent }}">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3 text-white/80">{{ $bio }}</p>@endif
            @include('common.biolink-profile-socials', ['psocials' => $psocials, 'socialIcons' => $socialIcons, 'accent' => '#ffffff', 'chip' => 'glass'])
        </div>
    </div>

{{-- ───────────────────────────── COVER OVERLAY HERO ────────────────── --}}
@elseif($layout === 'cover_hero')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="relative" style="min-height:300px;@if($cover)background-image:url('{{ $cover }}');background-size:cover;background-position:center;@else background:#0b0b0f;@endif">
            <div class="absolute inset-0" style="background:linear-gradient(to top,rgba(0,0,0,0.88) 5%,rgba(0,0,0,0.15))"></div>
            <div class="absolute bottom-0 left-0 right-0 p-5 text-white">
                <div class="flex items-end gap-3">
                    @if($avatar)<img src="{{ $avatar }}" class="w-16 h-16 rounded-full object-cover shrink-0" style="border:3px solid rgba(255,255,255,0.85)" alt="">
                    @else<div class="w-16 h-16 rounded-full flex items-center justify-center text-lg font-bold shrink-0" style="border:3px solid rgba(255,255,255,0.85);background:{{ $avatarBg }}">{{ $initial }}</div>@endif
                    <div class="min-w-0">
                        @if($name)<p class="text-lg font-bold leading-tight">{{ $name }}</p>@endif
                        @if($title)<p class="text-sm" style="color:{{ $accent }}">{{ $title }}</p>@endif
                    </div>
                </div>
                @if($bio)<p class="text-sm mt-3 text-white/80">{{ $bio }}</p>@endif
            </div>
        </div>
    </div>

{{-- ───────────────────────────── SPLIT CARD ────────────────────────── --}}
@elseif($layout === 'split')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="flex items-center gap-5 p-5">
            <div class="shrink-0">
                @if($avatar)<img src="{{ $avatar }}" class="w-24 h-24 rounded-2xl object-cover" alt="">
                @else<div class="w-24 h-24 rounded-2xl flex items-center justify-center text-2xl font-bold" style="background:{{ $avatarBg }}">{{ $initial }}</div>@endif
            </div>
            <div class="min-w-0">
                @if($name)<p class="text-lg font-bold leading-tight">{{ $name }}</p>@endif
                @if($title)<p class="text-sm font-medium" style="color:{{ $accent }}">{{ $title }}</p>@endif
                @if($bio)<p class="text-sm mt-2" style="opacity:.72">{{ $bio }}</p>@endif
            </div>
        </div>
    </div>

{{-- ───────────────────────────── FLOATING AVATAR ───────────────────── --}}
@elseif($layout === 'floating')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        @if($cover)
            <div class="h-24 bg-cover bg-center" style="background-image:url('{{ $cover }}')"></div>
        @else
            <div class="h-24" style="background:linear-gradient(135deg,#7c3aed,#d946ef)"></div>
        @endif
        <div class="px-5 pb-6 -mt-12 text-center">
            <div class="flex justify-center">
                @if($avatar)<img src="{{ $avatar }}" class="w-24 h-24 rounded-full object-cover" style="border:5px solid #ffffff;box-shadow:0 10px 25px rgba(0,0,0,0.25)" alt="">
                @else<div class="w-24 h-24 rounded-full flex items-center justify-center text-2xl font-bold" style="border:5px solid #ffffff;background:{{ $avatarBg }};box-shadow:0 10px 25px rgba(0,0,0,0.25)">{{ $initial }}</div>@endif
            </div>
            @if($name)<p class="mt-3 text-lg font-bold">{{ $name }}</p>@endif
            @if($title)<p class="text-sm font-medium" style="color:{{ $accent }}">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3" style="opacity:.72">{{ $bio }}</p>@endif
        </div>
    </div>

{{-- ───────────────────────────── GRADIENT IDENTITY ─────────────────── --}}
@elseif($layout === 'gradient')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="px-5 py-7 text-center text-white">
            <div class="flex justify-center">
                @if($avatar)<img src="{{ $avatar }}" class="w-20 h-20 rounded-full object-cover" style="border:3px solid rgba(255,255,255,0.65)" alt="">
                @else<div class="w-20 h-20 rounded-full flex items-center justify-center text-xl font-bold" style="border:3px solid rgba(255,255,255,0.65);background:rgba(255,255,255,0.18)">{{ $initial }}</div>@endif
            </div>
            @if($name)<p class="mt-3 text-lg font-bold">{{ $name }}</p>@endif
            @if($title)<p class="text-sm text-white/85">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3 text-white/80">{{ $bio }}</p>@endif
            @include('common.biolink-profile-socials', ['psocials' => $psocials, 'socialIcons' => $socialIcons, 'accent' => '#ffffff', 'chip' => 'glass'])
        </div>
    </div>

{{-- ───────────────────────────── PREMIUM FOUNDER ───────────────────── --}}
@elseif($layout === 'founder')
    <div class="mb-4 overflow-hidden rounded-2xl relative {{ $baseClass }}" style="{{ $cardStyle }}">
        @if($cover)
            <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('{{ $cover }}');opacity:.35"></div>
            <div class="absolute inset-0" style="background:linear-gradient(160deg,rgba(0,0,0,0.75),rgba(0,0,0,0.92))"></div>
        @endif
        <div class="relative px-5 py-7 text-center text-white">
            <div class="flex justify-center">
                @if($avatar)<img src="{{ $avatar }}" class="w-20 h-20 rounded-full object-cover" style="border:3px solid #d4af37;box-shadow:0 0 22px rgba(212,175,55,0.35)" alt="">
                @else<div class="w-20 h-20 rounded-full flex items-center justify-center text-xl font-bold" style="border:3px solid #d4af37;background:rgba(212,175,55,0.12)">{{ $initial }}</div>@endif
            </div>
            @if($name)
                <p class="mt-3 text-lg font-bold" style="color:{{ $accent }}">
                    {{ $name }}@if($verified)<i class="fas fa-circle-check ml-1.5" style="color:{{ $accent }}"></i>@endif
                </p>
            @endif
            @if($title)<p class="text-sm text-white/70">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3 text-white/75">{{ $bio }}</p>@endif
            @if($ctaLabel)
                <a href="{{ $ctaUrl ?: '#' }}" @if($ctaUrl) target="_blank" rel="noopener" @endif
                   class="inline-flex items-center gap-2 mt-5 px-6 py-2.5 rounded-full text-sm font-semibold transition hover:scale-105"
                   style="border:1px solid #d4af37;color:#d4af37;background:rgba(212,175,55,0.06)">
                    <i class="fas fa-crown"></i>{{ $ctaLabel }}
                </a>
            @endif
        </div>
    </div>

{{-- ───────────────────────────── MINIMAL DARK ──────────────────────── --}}
@elseif($layout === 'minimal_dark')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="px-5 py-8 text-center text-white">
            <div class="flex justify-center">
                @if($avatar)<img src="{{ $avatar }}" class="w-20 h-20 rounded-full object-cover" style="border:1px solid rgba(255,255,255,0.25)" alt="">
                @else<div class="w-20 h-20 rounded-full flex items-center justify-center text-xl font-bold" style="border:1px solid rgba(255,255,255,0.25);background:rgba(255,255,255,0.06)">{{ $initial }}</div>@endif
            </div>
            @if($name)<p class="mt-4 text-xl font-bold tracking-tight">{{ $name }}</p>@endif
            @if($title)<p class="text-sm" style="color:{{ $accent }}">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3 text-white/65">{{ $bio }}</p>@endif
            @include('common.biolink-profile-socials', ['psocials' => $psocials, 'socialIcons' => $socialIcons, 'accent' => '#ffffff', 'chip' => 'glass'])
        </div>
    </div>

{{-- ───────────────────────────── MAGAZINE LAYOUT ───────────────────── --}}
@elseif($layout === 'magazine')
    <div class="mb-4 overflow-hidden rounded-xl {{ $baseClass }}" style="{{ $cardStyle }}">
        @if($cover)<div class="h-32 bg-cover bg-center" style="background-image:url('{{ $cover }}')"></div>@endif
        <div class="p-5">
            <div class="flex items-center gap-3">
                @if($avatar)<img src="{{ $avatar }}" class="w-14 h-14 rounded-full object-cover shrink-0" alt="">
                @else<div class="w-14 h-14 rounded-full flex items-center justify-center text-lg font-bold shrink-0" style="background:{{ $avatarBg }}">{{ $initial }}</div>@endif
                <div class="min-w-0">
                    @if($title)<p class="text-[11px] uppercase tracking-[0.18em] font-semibold" style="color:{{ $accent }}">{{ $title }}</p>@endif
                    @if($name)<p class="text-xl font-bold leading-tight">{{ $name }}</p>@endif
                </div>
            </div>
            @if($bio)<p class="text-sm mt-4 leading-relaxed" style="opacity:.78">{{ $bio }}</p>@endif
        </div>
    </div>

{{-- ───────────────────────────── SOCIAL PROFILE STYLE ──────────────── --}}
@elseif($layout === 'social_profile')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        @if($cover)
            <div class="h-24 bg-cover bg-center" style="background-image:url('{{ $cover }}')"></div>
        @else
            <div class="h-24" style="background:linear-gradient(135deg,#3b82f6,#06b6d4)"></div>
        @endif
        <div class="px-5 pb-6 -mt-11 text-center">
            <div class="flex justify-center">
                @if($avatar)<img src="{{ $avatar }}" class="w-22 h-22 rounded-full object-cover" style="width:5.5rem;height:5.5rem;border:4px solid #ffffff;box-shadow:0 4px 14px rgba(0,0,0,0.15)" alt="">
                @else<div class="rounded-full flex items-center justify-center text-2xl font-bold" style="width:5.5rem;height:5.5rem;border:4px solid #ffffff;background:{{ $avatarBg }}">{{ $initial }}</div>@endif
            </div>
            @if($name)
                <p class="mt-3 text-lg font-bold">
                    {{ $name }}@if($verified)<i class="fas fa-circle-check ml-1.5" style="color:{{ $accent }}"></i>@endif
                </p>
            @endif
            @if($title)<p class="text-sm font-medium" style="color:{{ $accent }}">{{ $title }}</p>@endif
            @if($location || $website)
                <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 mt-2 text-xs" style="opacity:.7">
                    @if($location)<span><i class="fas fa-location-dot mr-1"></i>{{ $location }}</span>@endif
                    @if($website)<a href="{{ $website }}" target="_blank" rel="noopener" class="hover:underline" style="color:{{ $accent }}"><i class="fas fa-link mr-1"></i>{{ preg_replace('#^https?://(www\.)?#', '', $website) }}</a>@endif
                </div>
            @endif
            @if($bio)<p class="text-sm mt-3" style="opacity:.72">{{ $bio }}</p>@endif
            @include('common.biolink-profile-socials', ['psocials' => $psocials, 'socialIcons' => $socialIcons, 'accent' => $accent, 'chip' => 'accent_outline'])
        </div>
    </div>

{{-- ───────────────────────────── LEGACY: STATS (v3 default) ────────── --}}
@elseif($layout === 'stats')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="p-5 text-center">
            <div class="flex justify-center">
                @if($avatar)<img src="{{ $avatar }}" class="w-16 h-16 rounded-full object-cover" style="border:2px solid rgba(255,255,255,0.12)" alt="">
                @else<div class="w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold" style="border:2px solid rgba(255,255,255,0.12);background:{{ $avatarBg }}">{{ $initial }}</div>@endif
            </div>
            @if($name)<p class="mt-3 font-semibold">{{ $name }}</p>@endif
            @if($title)<p class="text-xs" style="color:{{ $accent }}">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3" style="opacity:.6">{{ $bio }}</p>@endif
            @if(!empty($stats))
                <div class="flex justify-center gap-6 mt-4">
                    @foreach($stats as $stat)
                        <div class="text-center">
                            <p class="font-bold">{{ $stat['value'] ?? '0' }}</p>
                            <p class="text-[10px]" style="opacity:.45">{{ $stat['label'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

{{-- ───────────────────────────── LEGACY: BADGES (v4 default) & FALLBACK --}}
@else
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="p-5 text-center">
            <div class="flex justify-center">
                @if($avatar)<img src="{{ $avatar }}" class="w-16 h-16 rounded-full object-cover" style="border:2px solid rgba(255,255,255,0.12)" alt="">
                @else<div class="w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold" style="border:2px solid rgba(255,255,255,0.12);background:{{ $avatarBg }}">{{ $initial }}</div>@endif
            </div>
            @if($name)<p class="mt-3 font-semibold">{{ $name }}</p>@endif
            @if($title)<p class="text-xs" style="color:{{ $accent }}">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3" style="opacity:.6">{{ $bio }}</p>@endif
            @if(!empty($badges))
                <div class="flex flex-wrap justify-center gap-2 mt-4">
                    @foreach($badges as $badge)
                        @php $bLabel = is_array($badge) ? ($badge['label'] ?? '') : $badge; @endphp
                        @if($bLabel !== '')<span class="px-3 py-1 rounded-full text-xs" style="background:rgba(124,58,237,0.18);color:{{ $accent }}">{{ $bLabel }}</span>@endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif
