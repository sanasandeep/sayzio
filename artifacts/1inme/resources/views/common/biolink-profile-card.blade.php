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
        'glass'                    => '#bccfff',
        'minimal_dark', 'cover_hero' => '#90acff',
        'business_card', 'id_badge' => '#2563eb',
        'ticket_stub'              => '#b45309',
        'paper_collage'            => '#5f6f52',
        'terminal'                 => '#4ade80',
        'polaroid', 'sidebar_accent' => '#3d6bff',
        default                    => '#3d6bff',
    };

    // Decorative avatar frame (Task #5910). Key + optional tint live in
    // _style; unknown keys make svg() return null so nothing renders.
    // Default tint is the layout accent. The wrapper isolates its own
    // stacking context and the frame sits at z-index:-1, so the avatar
    // paints on top without touching any per-layout img markup.
    $pcFrameKey   = $s['_style']['_avatar_frame'] ?? '';
    $pcFrameColor = (string) ($s['_style']['_avatar_frame_color'] ?? '');
    $pcFrameSvg   = \App\Modules\User\Support\AvatarFrameCatalog::svg(
        is_string($pcFrameKey) ? $pcFrameKey : '',
        $pcFrameColor !== '' ? $pcFrameColor : $accent
    );
    $pcFrameOpen  = $pcFrameSvg
        ? '<span class="relative inline-flex shrink-0" style="isolation:isolate" data-avatar-frame="' . e($pcFrameKey) . '">'
            . '<span class="absolute pointer-events-none" aria-hidden="true" style="inset:-18%;z-index:-1">' . $pcFrameSvg . '</span>'
        : '';
    $pcFrameClose = $pcFrameSvg ? '</span>' : '';

    // Surface style. When the design sets no background we keep the page's
    // translucent glass-block look (matches the pre-#1740 default).
    $pBg       = $blockStyle['bg_color'] ?? '';
    $baseClass = ($pBg === '' || $pBg === null) ? 'glass-block' : '';
    $cardStyle = trim($blockInline);

    $initial = strtoupper(mb_substr($name !== '' ? $name : 'U', 0, 1));
@endphp

@php
    // Reusable avatar fallback colours.
    $avatarBg = 'rgba(61,107,255,0.20)';
@endphp

{{-- ───────────────────────────── SPLIT HERO ────────────────────────── --}}
{{-- Task #5876: photo-first column for split desktop layouts — a large
     circular avatar with the social-icon row beneath it, nothing else.
     Name/title/links live in sibling blocks in the page's other column.
     Transparent surface: the page background (usually a blurred photo)
     shows through. --}}
@if($layout === 'split_hero')
    <div class="mb-4 {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="px-2 py-4 flex flex-col items-center text-center">
            {!! $pcFrameOpen !!}@if($avatar)
                <img src="{{ $avatar }}" class="rounded-full object-cover w-48 h-48 md:w-64 md:h-64" style="border:3px solid rgba(255,255,255,0.35);box-shadow:0 10px 34px rgba(0,0,0,0.30)" alt="{{ $name }}">
            @else
                <div class="rounded-full flex items-center justify-center text-5xl font-bold w-48 h-48 md:w-64 md:h-64" style="border:3px solid rgba(255,255,255,0.35);background:{{ $avatarBg }}">{{ $initial }}</div>
            @endif{!! $pcFrameClose !!}
            @include('common.biolink-profile-socials', ['psocials' => $psocials, 'socialIcons' => $socialIcons, 'accent' => '#ffffff', 'chip' => 'plain'])
        </div>
    </div>

{{-- ───────────────────────────── CLASSIC CREATOR ───────────────────── --}}
@elseif($layout === 'classic_creator')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        @if($cover)<div class="h-28 bg-cover bg-center" style="background-image:url('{{ $cover }}')"></div>@endif
        <div class="px-5 pb-6 text-center {{ $cover ? '-mt-12' : 'pt-6' }}">
            <div class="flex justify-center">
                {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-24 h-24 rounded-full object-cover" style="border:4px solid #ffffff;box-shadow:0 6px 18px rgba(0,0,0,0.18)" alt="">
                @else<div class="w-24 h-24 rounded-full flex items-center justify-center text-2xl font-bold" style="border:4px solid #ffffff;background:{{ $avatarBg }}">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
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
            <div class="absolute inset-0" style="background:linear-gradient(160deg,rgba(61,107,255,0.40),rgba(236,72,153,0.28))"></div>
        @endif
        <div class="relative px-5 py-7 text-center text-white">
            <div class="flex justify-center">
                {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-20 h-20 rounded-full object-cover" style="border:3px solid rgba(255,255,255,0.55)" alt="">
                @else<div class="w-20 h-20 rounded-full flex items-center justify-center text-xl font-bold" style="border:3px solid rgba(255,255,255,0.55);background:rgba(255,255,255,0.12)">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
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
                    {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-16 h-16 rounded-full object-cover shrink-0" style="border:3px solid rgba(255,255,255,0.85)" alt="">
                    @else<div class="w-16 h-16 rounded-full flex items-center justify-center text-lg font-bold shrink-0" style="border:3px solid rgba(255,255,255,0.85);background:{{ $avatarBg }}">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
                    <div class="min-w-0">
                        @if($name)<p class="text-lg font-bold leading-tight">{{ $name }}</p>@endif
                        @if($title)<p class="text-sm" style="color:{{ $accent }}">{{ $title }}</p>@endif
                    </div>
                </div>
                @if($bio)<p class="text-sm mt-3 text-white/80">{{ $bio }}</p>@endif
            </div>
        </div>
    </div>

{{-- ───────────────────────────── PORTRAIT POSTER ───────────────────── --}}
{{-- Task #5906: full-bleed portrait cover filling the card, a large
     ringed circular avatar centered mid-photo, and the name + thin
     divider + letter-spaced uppercase title overlaid near the bottom
     over a dark gradient. Gradient background when no cover; initial
     avatar when no avatar. --}}
@elseif($layout === 'portrait_poster')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="relative flex flex-col items-center" style="min-height:420px;@if($cover)background-image:url('{{ $cover }}');background-size:cover;background-position:center;@else background:linear-gradient(160deg,#64748b,#334155 60%,#1e293b);@endif">
            <div class="absolute inset-0" style="background:linear-gradient(to top,rgba(0,0,0,0.82) 0%,rgba(0,0,0,0.35) 34%,rgba(0,0,0,0.05) 60%)"></div>
            <div class="relative flex justify-center w-full" style="margin-top:88px">
                {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-32 h-32 rounded-full object-cover" style="border:4px solid rgba(255,255,255,0.85);box-shadow:0 8px 28px rgba(0,0,0,0.35)" alt="">
                @else<div class="w-32 h-32 rounded-full flex items-center justify-center text-3xl font-bold text-white" style="border:4px solid rgba(255,255,255,0.85);background:{{ $avatarBg }};box-shadow:0 8px 28px rgba(0,0,0,0.35)">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
            </div>
            <div class="relative w-full mt-auto px-6 pb-8 pt-10 text-center text-white">
                @if($name)<p class="text-2xl font-bold leading-tight" style="letter-spacing:.04em">{{ $name }}</p>@endif
                @if($name && $title)<div class="mx-auto mt-3" style="width:200px;max-width:70%;height:1px;background:rgba(255,255,255,0.75)"></div>@endif
                @if($title)<p class="mt-3 text-xs font-semibold uppercase" style="letter-spacing:.35em">{{ $title }}</p>@endif
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
            <div class="h-24" style="background:linear-gradient(135deg,#3d6bff,#d946ef)"></div>
        @endif
        <div class="px-5 pb-6 -mt-12 text-center">
            <div class="flex justify-center">
                {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-24 h-24 rounded-full object-cover" style="border:5px solid #ffffff;box-shadow:0 10px 25px rgba(0,0,0,0.25)" alt="">
                @else<div class="w-24 h-24 rounded-full flex items-center justify-center text-2xl font-bold" style="border:5px solid #ffffff;background:{{ $avatarBg }};box-shadow:0 10px 25px rgba(0,0,0,0.25)">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
            </div>
            @if($name)<p class="mt-3 text-lg font-bold">{{ $name }}</p>@endif
            @if($title)<p class="text-sm font-medium" style="color:{{ $accent }}">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3" style="opacity:.72">{{ $bio }}</p>@endif
        </div>
    </div>

{{-- ───────────────────────────── ARCH BAND ─────────────────────────── --}}
{{-- Task #5922: wide cover photo whose bottom edge carries a semi-
     circular arch band; the circular avatar sits inside the arch and
     straddles the cover's bottom edge. The band and the avatar ring
     share ONE color + width — the block's border_color / border_width
     (so recoloring the border restyles both in lockstep). --}}
@elseif($layout === 'arch_band')
    @php
        $abColor = ($blockStyle['border_color'] ?? '') !== '' ? $blockStyle['border_color'] : '#b98a5e';
        $abWidth = max(2, min(10, (int) (($blockStyle['border_width'] ?? '') !== '' ? $blockStyle['border_width'] : 6)));
        // Arch outer diameter = avatar + band thickness on each side. The
        // band thickness scales with the shared border width.
        $abAv   = 160;                       // avatar diameter px
        $abBand = 14 + $abWidth * 4;         // band thickness px
        $abOut  = $abAv + 2 * $abBand;       // arch outer diameter px
    @endphp
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="relative">
            <div class="h-44 bg-cover bg-center" style="@if($cover)background-image:url('{{ $cover }}');@else background:linear-gradient(135deg,#e7dccf,#cdb9a0);@endif"></div>
            {{-- Thin rule along the cover's bottom edge, same band color --}}
            <div class="absolute left-0 right-0" style="bottom:0;height:3px;background:{{ $abColor }}" aria-hidden="true"></div>
            {{-- Filled semi-circular arch band, bottom-aligned with the cover --}}
            <div class="absolute left-1/2 -translate-x-1/2" aria-hidden="true"
                 style="bottom:0;width:{{ $abOut }}px;height:{{ (int) ($abOut / 2) + 12 }}px;background:{{ $abColor }};border-radius:{{ $abOut }}px {{ $abOut }}px 0 0"></div>
        </div>
        <div class="relative px-5 pb-6 text-center" style="padding-top:{{ (int) ($abAv / 2) + 16 }}px">
            <div class="absolute left-1/2 -translate-x-1/2" style="top:-{{ (int) ($abAv / 2) }}px">
                {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="rounded-full object-cover" style="width:{{ $abAv }}px;height:{{ $abAv }}px;border:{{ $abWidth }}px solid {{ $abColor }};background:#ffffff" alt="">
                @else<div class="rounded-full flex items-center justify-center text-4xl font-bold" style="width:{{ $abAv }}px;height:{{ $abAv }}px;border:{{ $abWidth }}px solid {{ $abColor }};background:{{ $avatarBg }}">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
            </div>
            @if($name)<p class="text-xl font-bold leading-tight">{{ $name }}@if($verified)<i class="fas fa-circle-check ml-1.5" style="color:{{ $abColor }}"></i>@endif</p>@endif
            @if($title)<p class="mt-1 text-xs font-semibold uppercase" style="letter-spacing:.25em;color:{{ $abColor }}">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3" style="opacity:.72">{{ $bio }}</p>@endif
            @include('common.biolink-profile-socials', ['psocials' => $psocials, 'socialIcons' => $socialIcons, 'accent' => $abColor, 'chip' => 'accent_outline'])
        </div>
    </div>

{{-- ───────────────────────────── OVERLAP HERO ──────────────────────── --}}
{{-- Tall cover with the white card pulled UP over it; the avatar
     straddles the card's top edge (half over the cover, half on the
     card). The block surface itself stays transparent — the white card
     is internal so the page background shows around it. --}}
@elseif($layout === 'overlap_hero')
    <div class="mb-4" style="{{ $cardStyle }}">
        <div class="relative">
            <div class="h-44 rounded-2xl bg-cover bg-center" style="@if($cover)background-image:url('{{ $cover }}');@else background:linear-gradient(135deg,#3d6bff,#6ea8ff);@endif"></div>
            <div class="relative mx-4 -mt-14 rounded-3xl px-5 pb-6 text-center" style="background:#ffffff;box-shadow:0 14px 34px rgba(15,23,42,0.16);padding-top:3.75rem">
                <div class="absolute left-1/2 -translate-x-1/2" style="top:-3rem">
                    {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-24 h-24 rounded-full object-cover" style="border:4px solid #ffffff;box-shadow:0 8px 22px rgba(0,0,0,0.22)" alt="">
                    @else<div class="w-24 h-24 rounded-full flex items-center justify-center text-2xl font-bold" style="border:4px solid #ffffff;background:{{ $avatarBg }};color:#0f172a">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
                </div>
                @if($name)<p class="text-lg font-bold" style="color:#0f172a">{{ $name }}</p>@endif
                @if($title)<p class="text-sm font-medium" style="color:{{ $accent }}">{{ $title }}</p>@endif
                @if($bio)<p class="text-sm mt-3" style="color:#475569">{{ $bio }}</p>@endif
                @include('common.biolink-profile-socials', ['psocials' => $psocials, 'socialIcons' => $socialIcons, 'accent' => $accent, 'chip' => 'accent_outline'])
            </div>
        </div>
    </div>

{{-- ───────────────────────────── SPLIT HERO PANEL ──────────────────── --}}
{{-- Task #5885: tall solid-colour hero panel — script-style name up top,
     a letter-spaced tagline, then the photo filling the rest of the
     panel. Designed to sit beside a grid of flat link tiles on desktop
     (the wrap stretches it to the tile rows' height via grid_row_span_md),
     and to stack above them on phones. No bottom margin: the page grid's
     gap owns the spacing so the panel lines up flush with the tiles. --}}
@elseif($layout === 'split_hero_panel')
    @once('split-hero-pacifico')
        {{-- The script name uses Pacifico; the page-level font collector only
             sees block-theme / page fonts, so this layout loads its own face
             (body-level stylesheet links are valid HTML; @once dedupes). --}}
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap">
    @endonce
    <div class="overflow-hidden flex flex-col h-full {{ $baseClass }}" style="min-height:340px;{{ $cardStyle }}">
        <div class="px-6 pt-8 pb-4 text-center">
            @if($name)<p class="text-4xl leading-tight" style="font-family:'Pacifico','Brush Script MT',cursive;font-weight:400">{{ $name }}</p>@endif
            @if($title)<p class="mt-3 text-[11px] font-bold uppercase" style="letter-spacing:0.35em;opacity:.85;font-family:ui-sans-serif,system-ui,sans-serif">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3" style="opacity:.75;font-family:ui-sans-serif,system-ui,sans-serif">{{ $bio }}</p>@endif
        </div>
        <div class="flex-1 min-h-0 px-6 pb-6 flex">
            @if($avatar)
                <img src="{{ $avatar }}" class="w-full h-full object-cover object-top" style="min-height:220px" alt="">
            @else
                <div class="w-full h-full flex items-center justify-center text-5xl font-bold" style="min-height:220px;background:rgba(0,0,0,0.08)">{{ $initial }}</div>
            @endif
        </div>
    </div>

{{-- ───────────────────────────── GRADIENT IDENTITY ─────────────────── --}}
@elseif($layout === 'gradient')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="px-5 py-7 text-center text-white">
            <div class="flex justify-center">
                {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-20 h-20 rounded-full object-cover" style="border:3px solid rgba(255,255,255,0.65)" alt="">
                @else<div class="w-20 h-20 rounded-full flex items-center justify-center text-xl font-bold" style="border:3px solid rgba(255,255,255,0.65);background:rgba(255,255,255,0.18)">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
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
                {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-20 h-20 rounded-full object-cover" style="border:3px solid #d4af37;box-shadow:0 0 22px rgba(212,175,55,0.35)" alt="">
                @else<div class="w-20 h-20 rounded-full flex items-center justify-center text-xl font-bold" style="border:3px solid #d4af37;background:rgba(212,175,55,0.12)">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
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
                {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-20 h-20 rounded-full object-cover" style="border:1px solid rgba(255,255,255,0.25)" alt="">
                @else<div class="w-20 h-20 rounded-full flex items-center justify-center text-xl font-bold" style="border:1px solid rgba(255,255,255,0.25);background:rgba(255,255,255,0.06)">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
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
                {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-14 h-14 rounded-full object-cover shrink-0" alt="">
                @else<div class="w-14 h-14 rounded-full flex items-center justify-center text-lg font-bold shrink-0" style="background:{{ $avatarBg }}">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
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
                {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-22 h-22 rounded-full object-cover" style="width:5.5rem;height:5.5rem;border:4px solid #ffffff;box-shadow:0 4px 14px rgba(0,0,0,0.15)" alt="">
                @else<div class="rounded-full flex items-center justify-center text-2xl font-bold" style="width:5.5rem;height:5.5rem;border:4px solid #ffffff;background:{{ $avatarBg }}">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
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

{{-- ───────────────────────────── BUSINESS CARD ─────────────────────── --}}
@elseif($layout === 'business_card')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="flex items-center gap-4 p-5">
            <div class="shrink-0">
                @if($avatar)<img src="{{ $avatar }}" class="w-20 h-20 rounded-xl object-cover" style="border:1px solid rgba(0,0,0,0.08)" alt="">
                @else<div class="w-20 h-20 rounded-xl flex items-center justify-center text-2xl font-bold" style="background:{{ $avatarBg }};color:{{ $accent }}">{{ $initial }}</div>@endif
            </div>
            <div class="min-w-0 flex-1" style="border-left:2px solid {{ $accent }}33;padding-left:1rem">
                @if($name)<p class="text-lg font-bold leading-tight">{{ $name }}@if($verified)<i class="fas fa-circle-check ml-1" style="color:{{ $accent }}"></i>@endif</p>@endif
                @if($title)<p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color:{{ $accent }}">{{ $title }}</p>@endif
                @if($bio)<p class="text-sm mt-2" style="opacity:.72">{{ $bio }}</p>@endif
                @if($location || $website)
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-xs" style="opacity:.7">
                        @if($location)<span><i class="fas fa-location-dot mr-1" style="color:{{ $accent }}"></i>{{ $location }}</span>@endif
                        @if($website)<a href="{{ $website }}" target="_blank" rel="noopener" class="hover:underline" style="color:{{ $accent }}"><i class="fas fa-link mr-1"></i>{{ preg_replace('#^https?://(www\.)?#', '', $website) }}</a>@endif
                    </div>
                @endif
                @include('common.biolink-profile-socials', ['psocials' => $psocials, 'socialIcons' => $socialIcons, 'accent' => $accent, 'chip' => 'accent_outline', 'align' => 'left'])
            </div>
        </div>
    </div>

{{-- ───────────────────────────── ID BADGE / LANYARD ────────────────── --}}
@elseif($layout === 'id_badge')
    @php $idBar = $blockStyle['text_color'] ?? '#1e293b'; @endphp
    <div class="mb-4 flex flex-col items-center">
        {{-- Lanyard strap + clip --}}
        <div class="flex flex-col items-center" aria-hidden="true">
            <div style="width:8px;height:20px;background:{{ $accent }};border-radius:3px 3px 0 0;opacity:.85"></div>
            <div style="width:36px;height:11px;border:2px solid {{ $accent }};border-radius:6px;background:transparent;margin-top:-2px"></div>
        </div>
        <div class="w-full overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
            {{-- Punch hole --}}
            <div class="flex justify-center pt-3" aria-hidden="true">
                <div style="width:46px;height:8px;border-radius:999px;background:rgba(15,23,42,0.14)"></div>
            </div>
            {{-- Accent header band --}}
            <div class="mt-3 px-5 py-2.5 text-center" style="background:{{ $accent }};color:#fff">
                <p class="text-[10px] font-bold uppercase tracking-[0.3em]">Identification</p>
            </div>
            <div class="px-5 py-5 text-center">
                <div class="flex justify-center">
                    @if($avatar)<img src="{{ $avatar }}" class="w-20 h-20 rounded-lg object-cover" style="border:3px solid {{ $accent }}" alt="">
                    @else<div class="w-20 h-20 rounded-lg flex items-center justify-center text-xl font-bold" style="border:3px solid {{ $accent }};background:{{ $avatarBg }};color:{{ $accent }}">{{ $initial }}</div>@endif
                </div>
                @if($name)<p class="mt-3 text-lg font-bold tracking-tight">{{ $name }}@if($verified)<i class="fas fa-circle-check ml-1" style="color:{{ $accent }}"></i>@endif</p>@endif
                @if($title)<div class="mt-1 inline-block px-3 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider" style="background:{{ $accent }}1a;color:{{ $accent }}">{{ $title }}</div>@endif
                @if($bio)<p class="text-sm mt-3" style="opacity:.7">{{ $bio }}</p>@endif
                {{-- Barcode footer --}}
                <div class="mt-4 flex justify-center items-end gap-[3px]" aria-hidden="true" style="opacity:.55">
                    @foreach([3,1,2,1,3,1,1,2,1,3,2,1,1,3,1,2] as $bw)<span style="display:inline-block;width:{{ $bw }}px;height:22px;background:{{ $idBar }}"></span>@endforeach
                </div>
            </div>
        </div>
    </div>

{{-- ───────────────────────────── TICKET STUB ───────────────────────── --}}
@elseif($layout === 'ticket_stub')
    <div class="mb-4 relative overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        {{-- Perforation notches --}}
        <div class="absolute rounded-full" style="width:22px;height:22px;background:rgba(0,0,0,0.16);left:-11px;top:50%;transform:translateY(-50%)" aria-hidden="true"></div>
        <div class="absolute rounded-full" style="width:22px;height:22px;background:rgba(0,0,0,0.16);right:-11px;top:50%;transform:translateY(-50%)" aria-hidden="true"></div>
        <div class="flex items-stretch">
            <div class="flex-1 p-5 text-center">
                <p class="text-[10px] font-bold uppercase tracking-[0.25em]" style="color:{{ $accent }};opacity:.85">Admit One</p>
                <div class="flex justify-center mt-3">
                    {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-16 h-16 rounded-full object-cover" style="border:2px solid {{ $accent }}" alt="">
                    @else<div class="w-16 h-16 rounded-full flex items-center justify-center text-lg font-bold" style="border:2px solid {{ $accent }};background:{{ $avatarBg }};color:{{ $accent }}">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
                </div>
                @if($name)<p class="mt-3 text-xl font-bold leading-tight">{{ $name }}</p>@endif
                @if($bio)<p class="text-sm mt-2" style="opacity:.7">{{ $bio }}</p>@endif
            </div>
            <div class="self-stretch" style="border-left:2px dashed {{ $accent }}66" aria-hidden="true"></div>
            <div class="w-24 shrink-0 flex flex-col items-center justify-center p-3 text-center">
                <p class="text-[9px] font-bold uppercase tracking-wider" style="opacity:.55">Section</p>
                <p class="text-sm font-bold mt-1" style="color:{{ $accent }}">{{ $title !== '' ? $title : 'GA' }}</p>
                <div class="mt-2 flex items-end gap-[2px]" aria-hidden="true" style="opacity:.5">
                    @foreach([2,1,3,1,2,1,3,1] as $bw)<span style="display:inline-block;width:{{ $bw }}px;height:28px;background:{{ $accent }}"></span>@endforeach
                </div>
            </div>
        </div>
    </div>

{{-- ───────────────────────────── POLAROID ──────────────────────────── --}}
@elseif($layout === 'polaroid')
    <div class="mb-6 flex justify-center">
        <div class="{{ $baseClass }}" style="{{ $cardStyle }};transform:rotate(-2.5deg);max-width:18rem;width:100%">
            <div class="p-3 pb-1">
                <div class="w-full aspect-square overflow-hidden" style="background:{{ $avatarBg }}">
                    @if($avatar)<img src="{{ $avatar }}" class="w-full h-full object-cover" alt="">
                    @else<div class="w-full h-full flex items-center justify-center text-6xl font-bold" style="color:{{ $accent }}">{{ $initial }}</div>@endif
                </div>
            </div>
            <div class="px-4 pb-5 pt-2 text-center">
                @if($name)<p class="text-2xl leading-tight" style="font-family:'Caveat',cursive;color:#1f2937">{{ $name }}</p>@endif
                @if($title)<p class="text-base" style="font-family:'Caveat',cursive;opacity:.75;color:#374151">{{ $title }}</p>@endif
                @if($bio)<p class="text-base mt-1" style="font-family:'Caveat',cursive;opacity:.6;color:#374151">{{ $bio }}</p>@endif
            </div>
        </div>
    </div>

{{-- ───────────────────────────── TERMINAL / CODE ───────────────────── --}}
@elseif($layout === 'terminal')
    <style>
        @keyframes tcblink{0%,49%{opacity:1}50%,100%{opacity:0}}
        .terminal-cursor{animation:tcblink 1s steps(1) infinite}
        @media (prefers-reduced-motion: reduce){.terminal-cursor{animation:none}}
    </style>
    <div class="mb-4 overflow-hidden rounded-xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="flex items-center gap-1.5 px-4 py-2" style="background:rgba(255,255,255,0.06);border-bottom:1px solid rgba(255,255,255,0.08)">
            <span style="width:11px;height:11px;border-radius:999px;background:#ff5f56;display:inline-block"></span>
            <span style="width:11px;height:11px;border-radius:999px;background:#ffbd2e;display:inline-block"></span>
            <span style="width:11px;height:11px;border-radius:999px;background:#27c93f;display:inline-block"></span>
            <span class="ml-2 text-[10px]" style="font-family:'JetBrains Mono',monospace;opacity:.6">~ /profile</span>
        </div>
        <div class="p-4" style="font-family:'JetBrains Mono',monospace;font-size:13px;line-height:1.7">
            <div><span style="opacity:.6">$</span> <span>whoami</span></div>
            @if($avatar || $name)
                <div class="flex items-center gap-3 mt-2 mb-1">
                    @if($avatar)<img src="{{ $avatar }}" class="w-12 h-12 rounded object-cover" style="border:1px solid {{ $accent }}55" alt="">@endif
                    @if($name)<span class="text-base font-bold">{{ $name }}</span>@endif
                </div>
            @endif
            @if($title)<div><span style="opacity:.6">role:</span> <span style="color:{{ $accent }}">{{ $title }}</span></div>@endif
            @if($bio)<div class="mt-1" style="opacity:.85"><span style="opacity:.6">bio:</span> {{ $bio }}</div>@endif
            @if($location)<div style="opacity:.85"><span style="opacity:.6">loc:</span> {{ $location }}</div>@endif
            @if($website)<div><span style="opacity:.6">url:</span> <a href="{{ $website }}" target="_blank" rel="noopener" style="color:{{ $accent }};text-decoration:underline">{{ preg_replace('#^https?://(www\.)?#', '', $website) }}</a></div>@endif
            <div class="flex items-center gap-2 mt-2">
                <span style="opacity:.6">$</span>
                <span class="terminal-cursor" style="display:inline-block;width:8px;height:16px;background:{{ $accent }}"></span>
            </div>
        </div>
    </div>

{{-- ───────────────────────────── SIDEBAR ACCENT ────────────────────── --}}
@elseif($layout === 'sidebar_accent')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="flex items-stretch">
            <div class="shrink-0" style="width:10px;background:linear-gradient(180deg,{{ $accent }},{{ $accent }}99)" aria-hidden="true"></div>
            <div class="flex-1 p-5">
                <div class="flex items-center gap-4">
                    <div class="shrink-0">
                        {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-16 h-16 rounded-full object-cover" style="border:2px solid {{ $accent }}33" alt="">
                        @else<div class="w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold" style="border:2px solid {{ $accent }}33;background:{{ $avatarBg }};color:{{ $accent }}">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
                    </div>
                    <div class="min-w-0">
                        @if($name)<p class="text-lg font-bold leading-tight">{{ $name }}@if($verified)<i class="fas fa-circle-check ml-1" style="color:{{ $accent }}"></i>@endif</p>@endif
                        @if($title)<p class="text-sm font-semibold" style="color:{{ $accent }}">{{ $title }}</p>@endif
                    </div>
                </div>
                @if($bio)<p class="text-sm mt-3" style="opacity:.72">{{ $bio }}</p>@endif
                @include('common.biolink-profile-socials', ['psocials' => $psocials, 'socialIcons' => $socialIcons, 'accent' => $accent, 'chip' => 'accent_outline', 'align' => 'left'])
            </div>
        </div>
    </div>

{{-- ───────────────────────────── PAPER COLLAGE ─────────────────────── --}}
{{-- Task #5929: scrapbook brand intro — an offset muted-green grid-paper
     panel, a torn-edge white paper card (clip-path polygon; the drop-shadow
     lives on a wrapper so it follows the torn silhouette) with the brand
     name in Dancing Script and the tagline in a system serif, plus a
     pressed-botanical SVG sprig on the left. Colours are intrinsic to the
     collage (paper is always light), so both page themes stay legible. --}}
@elseif($layout === 'paper_collage')
    <div class="mb-4 overflow-hidden rounded-2xl relative {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="relative px-4 pt-7 pb-9" style="min-height:230px">
            {{-- Offset grid-paper panel --}}
            <div class="absolute" aria-hidden="true"
                 style="top:0.9rem;right:1rem;left:21%;bottom:1.6rem;background-color:#c6d0c3;background-image:linear-gradient(rgba(255,255,255,0.55) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.55) 1px,transparent 1px);background-size:17px 17px;box-shadow:0 6px 18px rgba(72,80,62,0.16)"></div>
            {{-- Pressed botanical sprig --}}
            <svg class="absolute" viewBox="0 0 120 200" fill="none" aria-hidden="true"
                 style="left:1%;top:4%;width:104px;height:172px;z-index:2;opacity:.9">
                <path d="M74 192 C68 140 48 84 22 30" stroke="#6d7f5e" stroke-width="2.5" stroke-linecap="round"/>
                <path d="M52 118 C40 112 28 112 18 120 C28 130 44 130 52 118 Z" fill="#87977a"/>
                <path d="M60 92 C50 80 36 74 22 76 C26 92 44 100 60 92 Z" fill="#788a68"/>
                <path d="M46 56 C40 44 30 36 18 34 C18 48 32 58 46 56 Z" fill="#93a37e"/>
                <path d="M66 150 C58 142 46 140 36 144 C42 156 58 158 66 150 Z" fill="#9aa887"/>
                <path d="M70 128 C78 118 90 114 102 118 C96 130 80 136 70 128 Z" fill="#7f9070"/>
                <circle cx="24" cy="22" r="4" fill="#b9b2a4"/>
                <circle cx="36" cy="14" r="3" fill="#c8c2b4"/>
                <circle cx="14" cy="36" r="3" fill="#c8c2b4"/>
            </svg>
            {{-- Torn paper card --}}
            <div class="relative" style="z-index:3;margin:1.4rem 5% 0 13%;filter:drop-shadow(0 10px 16px rgba(66,62,48,0.22))">
                <div class="px-7 pt-9 pb-11 text-center"
                     style="background:#fcfbf7;clip-path:polygon(2% 7%, 9% 2%, 21% 5%, 33% 1%, 46% 4%, 58% 0%, 71% 3%, 83% 1%, 94% 5%, 100% 12%, 98% 26%, 100% 41%, 97% 55%, 99% 68%, 96% 82%, 90% 93%, 79% 89%, 68% 98%, 55% 92%, 43% 100%, 30% 94%, 18% 99%, 8% 91%, 3% 95%, 0% 81%, 2% 64%, 0% 48%, 3% 32%, 1% 18%)">
                    @if($name)<p class="text-4xl leading-tight" style="font-family:'Dancing Script','Brush Script MT',cursive;font-weight:600;color:#5b4636;transform:rotate(-2deg)">{{ $name }}</p>@endif
                    @if($title)<p class="mt-3 text-sm" style="font-family:Georgia,'Times New Roman',serif;color:#57534e;line-height:1.55">{{ $title }}</p>@endif
                    @if($bio)<p class="mt-2 text-xs" style="font-family:Georgia,'Times New Roman',serif;color:#78716c;line-height:1.6">{{ $bio }}</p>@endif
                </div>
            </div>
        </div>
    </div>

{{-- ───────────────────────────── BRAND RAIL ────────────────────────── --}}
{{-- Task #5934: solid brand-color panel — brand name in an outlined
     ellipse top-right, a large offset rectangular portrait, and a
     vertical rail of social icons down the right edge. The brand color
     is the block surface's bg_color; text_color drives the outlines and
     copy so a recolor keeps everything legible. --}}
@elseif($layout === 'brand_rail')
    @php $brInk = ($blockStyle['text_color'] ?? '') !== '' ? $blockStyle['text_color'] : '#f3efe6'; @endphp
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="relative px-5 pt-5 pb-6" style="color:{{ $brInk }}">
            @if($name)
                <div class="flex justify-end">
                    <span class="inline-flex items-center justify-center text-center font-semibold"
                          style="border:1.5px solid {{ $brInk }};border-radius:50%;padding:0.9rem 1.6rem;font-size:15px;line-height:1.25;max-width:75%;letter-spacing:.02em">{{ $name }}</span>
                </div>
            @endif
            <div class="flex items-stretch gap-4 mt-4">
                <div class="flex-1 min-w-0" style="margin-right:6%">
                    @if($avatar)
                        <img src="{{ $avatar }}" class="w-full object-cover" style="height:270px;border-radius:6px;box-shadow:0 12px 30px rgba(0,0,0,0.25)" alt="{{ $name }}">
                    @else
                        <div class="w-full flex items-center justify-center text-6xl font-bold" style="height:270px;border-radius:6px;background:rgba(255,255,255,0.14)">{{ $initial }}</div>
                    @endif
                </div>
                @if(!empty($psocials))
                    <div class="flex flex-col items-center justify-center gap-3 shrink-0">
                        @foreach($psocials as $soc)
                            @php
                                $sn   = $soc['name'] ?? '';
                                $def  = $socialIcons[$sn] ?? ['fas fa-link', $brInk];
                                $href = $soc['url'] ?? '';
                            @endphp
                            <a href="{{ $href ?: '#' }}" @if($href) target="_blank" rel="noopener" @endif
                               class="w-9 h-9 rounded-full flex items-center justify-center text-sm transition hover:scale-110"
                               aria-label="{{ ucfirst($sn ?: 'link') }}"
                               style="border:1.5px solid {{ $brInk }}66;color:{{ $brInk }}">
                                <i class="{{ $def[0] }}"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            @if($title)<p class="mt-4 text-[11px] font-bold uppercase" style="letter-spacing:.3em;opacity:.9">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-2" style="opacity:.8">{{ $bio }}</p>@endif
        </div>
    </div>

{{-- ───────────────────────────── SPLIT PILL ────────────────────────── --}}
{{-- Task #5934: large serif display name up top on a two-tone
     horizontally split background, with a stadium-pill portrait
     straddling the color boundary. Top zone = the block's bg_color
     (paints the surface), bottom zone = border_color (arch_band
     precedent), so both zones are user-recolorable. --}}
@elseif($layout === 'split_pill')
    @once('split-pill-playfair')
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&display=swap">
    @endonce
    @php
        $spBottom = ($blockStyle['border_color'] ?? '') !== '' ? $blockStyle['border_color'] : '#8a5a3b';
        $spPillH  = 300;   // pill portrait height px
        $spSplit  = 150;   // how much of the pill sits in the bottom zone px
    @endphp
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="px-6 pt-8 pb-2 text-center">
            @if($name)<p class="leading-tight" style="font-family:'Playfair Display',Georgia,serif;font-size:clamp(2rem,8vw,3rem);font-weight:500;letter-spacing:.08em">{{ $name }}</p>@endif
        </div>
        <div class="relative">
            {{-- Decorative squiggle (left) + dots (right) at the boundary --}}
            <svg class="absolute" viewBox="0 0 90 24" fill="none" aria-hidden="true" style="left:6%;top:{{ $spPillH - $spSplit - 34 }}px;width:74px;height:20px;opacity:.75">
                <path d="M2 12 C10 2, 18 22, 26 12 S 42 2, 50 12 S 66 22, 74 12 S 86 6, 88 10" stroke="{{ $spBottom }}" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
            <div class="absolute flex gap-2" aria-hidden="true" style="right:8%;top:{{ $spPillH - $spSplit + 22 }}px">
                <span style="width:7px;height:7px;border-radius:999px;background:rgba(255,255,255,0.75)"></span>
                <span style="width:7px;height:7px;border-radius:999px;background:rgba(255,255,255,0.55)"></span>
                <span style="width:7px;height:7px;border-radius:999px;background:rgba(255,255,255,0.35)"></span>
            </div>
            {{-- Bottom color zone starts where the pill's midpoint sits --}}
            <div class="absolute left-0 right-0 bottom-0" aria-hidden="true" style="top:{{ $spPillH - $spSplit }}px;background:{{ $spBottom }}"></div>
            <div class="relative flex justify-center" style="padding-top:6px">
                @if($avatar)
                    <img src="{{ $avatar }}" class="object-cover" style="width:210px;height:{{ $spPillH }}px;border-radius:{{ (int) ($spPillH / 2) }}px;box-shadow:0 14px 34px rgba(0,0,0,0.22)" alt="{{ $name }}">
                @else
                    <div class="flex items-center justify-center text-6xl font-bold" style="width:210px;height:{{ $spPillH }}px;border-radius:{{ (int) ($spPillH / 2) }}px;background:{{ $avatarBg }}">{{ $initial }}</div>
                @endif
            </div>
            <div class="relative px-6 pb-8 pt-5 text-center" style="background:{{ $spBottom }};color:#ffffff">
                @if($title)<p class="text-[11px] font-bold uppercase" style="letter-spacing:.32em;opacity:.92">{{ $title }}</p>@endif
                @if($bio)<p class="text-sm mt-2" style="opacity:.85">{{ $bio }}</p>@endif
            </div>
        </div>
    </div>

{{-- ───────────────────────────── BADGE CARD ────────────────────────── --}}
{{-- Task #5934: full-bleed cover photo behind everything, a small
     @handle pill badge up top, and a tall white rounded card at the
     bottom whose top edge is straddled by a large ringed circular
     avatar; script name + divider + uppercase letter-spaced subtitle.
     The white card is intrinsic (always light) so both page themes
     stay legible. --}}
@elseif($layout === 'badge_card')
    @once('badge-card-dancing-script')
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap">
    @endonce
    @php
        // Pill badge text: the website host when set, else an @handle
        // derived from the name.
        $bcHandle = $website !== ''
            ? preg_replace('#^https?://(www\.)?#', '', rtrim($website, '/'))
            : ($name !== '' ? '@' . \Illuminate\Support\Str::slug($name, '') : '');
    @endphp
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="relative flex flex-col" style="min-height:460px;@if($cover)background-image:url('{{ $cover }}');background-size:cover;background-position:center;@else background:linear-gradient(165deg,#a39a8b,#7c7466 55%,#5f594e);@endif">
            <div class="absolute inset-0" aria-hidden="true" style="background:linear-gradient(to bottom,rgba(0,0,0,0.18),rgba(0,0,0,0.02) 40%)"></div>
            @if($bcHandle !== '')
                <div class="relative flex justify-center pt-5">
                    <span class="px-4 py-1.5 rounded-full text-xs font-semibold" style="background:rgba(252,251,247,0.92);color:#3f3a33;letter-spacing:.06em">{{ $bcHandle }}</span>
                </div>
            @endif
            <div class="relative mt-auto px-4 pb-4" style="padding-top:170px">
                <div class="relative rounded-3xl px-5 pb-7 text-center" style="background:#fcfbf7;box-shadow:0 16px 38px rgba(15,23,42,0.22);padding-top:4.6rem">
                    <div class="absolute left-1/2 -translate-x-1/2" style="top:-3.9rem">
                        {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="rounded-full object-cover" style="width:8rem;height:8rem;border:5px solid #fcfbf7;box-shadow:0 0 0 2px rgba(63,58,51,0.35),0 10px 26px rgba(0,0,0,0.28)" alt="{{ $name }}">
                        @else<div class="rounded-full flex items-center justify-center text-4xl font-bold" style="width:8rem;height:8rem;border:5px solid #fcfbf7;background:{{ $avatarBg }};color:#3f3a33;box-shadow:0 0 0 2px rgba(63,58,51,0.35),0 10px 26px rgba(0,0,0,0.28)">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
                    </div>
                    @if($name)<p class="leading-tight" style="font-family:'Dancing Script','Brush Script MT',cursive;font-size:2.4rem;font-weight:600;color:#3f3a33">{{ $name }}</p>@endif
                    @if($name && $title)<div class="mx-auto mt-3" style="width:170px;max-width:65%;height:1px;background:rgba(63,58,51,0.45)"></div>@endif
                    @if($title)<p class="mt-3 text-xs font-semibold uppercase" style="letter-spacing:.32em;color:#57534e">{{ $title }}</p>@endif
                    @if($bio)<p class="text-sm mt-3" style="color:#78716c">{{ $bio }}</p>@endif
                </div>
            </div>
        </div>
    </div>

{{-- ───────────────────────────── LEGACY: STATS (v3 default) ────────── --}}
@elseif($layout === 'stats')
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="p-5 text-center">
            <div class="flex justify-center">
                {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-16 h-16 rounded-full object-cover" style="border:2px solid rgba(255,255,255,0.12)" alt="">
                @else<div class="w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold" style="border:2px solid rgba(255,255,255,0.12);background:{{ $avatarBg }}">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
            </div>
            @if($name)<p class="mt-3 font-semibold">{{ $name }}</p>@endif
            @if($title)<p class="text-xs" style="color:{{ $accent }}">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3" style="opacity:.6">{{ $bio }}</p>@endif
            <div class="flex justify-center gap-6 mt-4" data-pc-stats data-pc-accent="{{ $accent }}" @if(empty($stats))style="display:none"@endif>
                @foreach($stats as $stat)
                    <div class="text-center">
                        <p class="font-bold">{{ $stat['value'] ?? '0' }}</p>
                        <p class="text-[10px]" style="opacity:.45">{{ $stat['label'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

{{-- ───────────────────────────── LEGACY: BADGES (v4 default) & FALLBACK --}}
@else
    <div class="mb-4 overflow-hidden rounded-2xl {{ $baseClass }}" style="{{ $cardStyle }}">
        <div class="p-5 text-center">
            <div class="flex justify-center">
                {!! $pcFrameOpen !!}@if($avatar)<img src="{{ $avatar }}" class="w-16 h-16 rounded-full object-cover" style="border:2px solid rgba(255,255,255,0.12)" alt="">
                @else<div class="w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold" style="border:2px solid rgba(255,255,255,0.12);background:{{ $avatarBg }}">{{ $initial }}</div>@endif{!! $pcFrameClose !!}
            </div>
            @if($name)<p class="mt-3 font-semibold">{{ $name }}</p>@endif
            @if($title)<p class="text-xs" style="color:{{ $accent }}">{{ $title }}</p>@endif
            @if($bio)<p class="text-sm mt-3" style="opacity:.6">{{ $bio }}</p>@endif
            <div class="flex flex-wrap justify-center gap-2 mt-4" data-pc-badges data-pc-accent="{{ $accent }}" @if(empty($badges))style="display:none"@endif>
                @foreach($badges as $badge)
                    @php $bLabel = is_array($badge) ? ($badge['label'] ?? '') : $badge; @endphp
                    @if($bLabel !== '')<span class="px-3 py-1 rounded-full text-xs" style="background:rgba(61,107,255,0.18);color:{{ $accent }}">{{ $bLabel }}</span>@endif
                @endforeach
            </div>
        </div>
    </div>
@endif
