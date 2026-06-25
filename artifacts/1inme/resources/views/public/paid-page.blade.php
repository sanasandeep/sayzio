<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $link->title ?: $creator->name }} (&#64;{{ $creator->handle }}) - {{ config('app.name') }}</title>
<meta name="description" content="{{ Str::limit($link->description ?: $creator->tagline ?: $creator->bio ?: ($creator->name . ' on 1INME'), 180) }}">
<meta property="og:title" content="{{ $link->title ?: $creator->name }}">
<meta property="og:description" content="{{ Str::limit($link->description ?: $creator->tagline ?: ('Follow ' . $creator->name . ' on 1INME'), 180) }}">
<meta property="og:type" content="profile">
@if($creator->cover_image)
    <meta property="og:image" content="{{ $creator->cover_image }}">
@elseif($creator->avatar)
    <meta property="og:image" content="{{ $creator->avatar }}">
@endif
@vite(['resources/css/app.css', 'resources/js/app.js'])
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family={{ urlencode($template['font']) }}:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
<script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
<style>
    :root{
        --pp-page-bg: {{ $template['page_bg'] }};
        --pp-hero-bg: {{ $template['hero_bg'] }};
        --accent: {{ $template['accent'] }};
        --pp-accent-soft: {{ $template['accent_soft'] }};
        --pp-text: {{ $template['text'] }};
        --pp-text-muted: {{ $template['text_muted'] }};
        --pp-card-bg: {{ $template['card_bg'] }};
        --pp-card-text: {{ $template['card_text'] }};
        --pp-card-border: {{ $template['card_border'] ?? 'rgba(15,23,42,0.08)' }};
        --pp-card-muted: {{ $template['card_muted'] ?? 'rgba(15,23,42,0.55)' }};
        --pp-card-input-bg: {{ $template['card_input_bg'] ?? '#ffffff' }};
        --pp-card-input-border: {{ $template['card_input_border'] ?? 'rgba(15,23,42,0.12)' }};
        --pp-radius: {{ $template['radius'] }};
        --pp-font: '{{ $template['font'] }}', system-ui, sans-serif;
    }
    [x-cloak]{display:none!important}
    html,body{background: var(--pp-page-bg); background-attachment: fixed; color: var(--pp-text); font-family: var(--pp-font);}

    /* ── Fixed full-page background layer stack ───────────────── */
    .pp-bg{position:fixed; inset:0; z-index:-1; overflow:hidden; pointer-events:none;}
    .pp-bg-image{position:absolute; inset:0; background-size:cover; background-position:center; background-repeat:no-repeat;}
    .pp-bg-video{position:absolute; inset:0; width:100%; height:100%; object-fit:cover;}
    .pp-bg-overlay{position:absolute; inset:0;}
    .pp-pattern{position:absolute; inset:-12%;}

    .pp-muted{color: var(--pp-text-muted);}
    .pp-hero{background: var(--pp-hero-bg); border-radius: var(--pp-radius);}
    .pp-btn-accent{background: var(--accent); color:#fff;}
    .pp-chip{background: var(--pp-accent-soft); color: var(--pp-text);}
    .pp-glass{background: rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); backdrop-filter: blur(10px);}

    /* ── Feed cards (fully themed) ────────────────────────────── */
    .cp-card{background: var(--pp-card-bg); color: var(--pp-card-text); border:1px solid var(--pp-card-border); border-radius: var(--pp-radius); box-shadow: 0 18px 50px -24px rgba(0,0,0,0.7);}
    body.pp-cardglass .cp-card{backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);}
    .cp-card a{color: inherit;}
    .cp-card .text-slate-700, .cp-card .text-slate-900, .cp-card .text-slate-600{color: var(--pp-card-text);}
    .cp-card .text-slate-500, .cp-card .text-slate-400{color: var(--pp-card-muted);}
    .cp-card .border-slate-100, .cp-card .border-slate-200{border-color: var(--pp-card-border) !important;}
    .cp-card .bg-slate-200{background: var(--pp-accent-soft);}
    .cp-card input[type=text]{background: var(--pp-card-input-bg); border-color: var(--pp-card-input-border); color: var(--pp-card-text);}
    .cp-card input[type=text]::placeholder{color: var(--pp-card-muted);}
    .cp-card input[type=text]:focus{border-color: var(--accent) !important;}
    .cp-card .bg-slate-900, .cp-card .bg-violet-600{background: var(--accent) !important;}
    /* Reaction chips: the unselected state follows the card surface so they
       read on dark/glass cards too; the active state keeps its reaction colour. */
    .cp-card .cp-reaction-btn.bg-white{background: var(--pp-card-input-bg); color: var(--pp-card-text); border-color: var(--pp-card-border);}

    @media (prefers-reduced-motion: no-preference){
        @keyframes pp-float { 0%,100%{transform:translateY(0) scale(1);} 50%{transform:translateY(-18px) scale(1.06);} }
        @keyframes pp-drift { 0%{transform:translate3d(0,0,0);} 100%{transform:translate3d(-60px,30px,0);} }
        @keyframes pp-pulse { 0%,100%{opacity:.45;} 50%{opacity:1;} }
        @keyframes pp-shimmer { 0%{background-position:0% 50%;} 100%{background-position:200% 50%;} }
        @keyframes pp-spin { 0%{transform:translate(-50%,-50%) rotate(0);} 100%{transform:translate(-50%,-50%) rotate(360deg);} }
        @keyframes pp-morph { 0%,100%{transform:translate(0,0) scale(1);} 50%{transform:translate(4%,-3%) scale(1.1);} }
        @keyframes pp-wavex { 0%{background-position:0 0;} 100%{background-position:1200px 0;} }
        @keyframes pp-mesh { 0%,100%{background-position:0% 0%;} 50%{background-position:100% 100%;} }
        @keyframes pp-aurora { 0%{transform:translate3d(-10%,0,0) skewX(-8deg);} 50%{transform:translate3d(10%,4%,0) skewX(6deg);} 100%{transform:translate3d(-10%,0,0) skewX(-8deg);} }

        .pp-anim-hero{ background-size:200% 200%; animation: pp-shimmer 14s ease infinite; }
        .pp-orb{ animation: pp-float 9s ease-in-out infinite; }
        .pp-orb.alt{ animation: pp-drift 18s ease-in-out infinite alternate; }
        .pp-grid-anim{ animation: pp-drift 24s linear infinite alternate; }
        .pp-spot{ animation: pp-pulse 6s ease-in-out infinite; }

        .pp-pattern-orbs::before{ animation: pp-float 12s ease-in-out infinite; }
        .pp-pattern-orbs::after{ animation: pp-drift 20s ease-in-out infinite alternate; }
        .pp-pattern-blobs::before{ animation: pp-morph 16s ease-in-out infinite; }
        .pp-pattern-blobs::after{ animation: pp-morph 22s ease-in-out infinite reverse; }
        .pp-pattern-spotlight::before{ animation: pp-pulse 7s ease-in-out infinite; }
        .pp-pattern-rays::before{ animation: pp-spin 50s linear infinite; }
        .pp-pattern-grid::before{ animation: pp-drift 26s linear infinite alternate; }
        .pp-pattern-mesh{ background-size:170% 170%; animation: pp-mesh 26s ease infinite; }
        .pp-pattern-aurora::before{ animation: pp-aurora 18s ease-in-out infinite; }
        .pp-pattern-waves::before{ animation: pp-wavex 18s linear infinite; }
        .pp-pattern-waves::after{ animation: pp-wavex 28s linear infinite reverse; }
        .pp-pattern-particles::before{ animation: pp-drift 32s linear infinite alternate; }
        .pp-pattern-particles::after{ animation: pp-float 18s ease-in-out infinite; }
    }

    /* ── Pattern base styles (render statically for reduced-motion too) ── */
    .pp-pattern-orbs::before,.pp-pattern-orbs::after{content:"";position:absolute;border-radius:50%;filter:blur(70px);opacity:.5;}
    .pp-pattern-orbs::before{width:60vw;height:60vw;left:-12vw;top:-12vh;background:radial-gradient(circle,var(--accent),transparent 68%);}
    .pp-pattern-orbs::after{width:55vw;height:55vw;right:-14vw;top:24vh;background:radial-gradient(circle,rgba(255,255,255,.28),transparent 68%);}

    .pp-pattern-blobs::before,.pp-pattern-blobs::after{content:"";position:absolute;width:48vw;height:48vw;filter:blur(60px);opacity:.5;}
    .pp-pattern-blobs::before{left:-8vw;top:-6vh;background:radial-gradient(circle,var(--accent),transparent 70%);border-radius:42% 58% 60% 40%/45% 45% 55% 55%;}
    .pp-pattern-blobs::after{right:-8vw;bottom:-8vh;background:radial-gradient(circle,rgba(255,255,255,.25),transparent 70%);border-radius:60% 40% 35% 65%/55% 60% 40% 45%;}

    .pp-pattern-spotlight::before{content:"";position:absolute;inset:0;background:radial-gradient(60% 50% at 50% -5%, var(--pp-accent-soft), transparent 60%);opacity:.85;}

    .pp-pattern-rays::before{content:"";position:absolute;left:50%;top:30%;width:160vmax;height:160vmax;transform:translate(-50%,-50%);background:repeating-conic-gradient(from 0deg, var(--pp-accent-soft) 0deg 6deg, transparent 6deg 18deg);opacity:.35;}

    .pp-pattern-grid::before{content:"";position:absolute;inset:-25%;background-image:linear-gradient(var(--pp-accent-soft) 1px,transparent 1px),linear-gradient(90deg,var(--pp-accent-soft) 1px,transparent 1px);background-size:46px 46px;opacity:.5;-webkit-mask-image:radial-gradient(70% 70% at 50% 30%,#000,transparent 75%);mask-image:radial-gradient(70% 70% at 50% 30%,#000,transparent 75%);}

    .pp-pattern-mesh{background-image:radial-gradient(40% 40% at 20% 20%, var(--accent), transparent 60%),radial-gradient(40% 40% at 80% 25%, rgba(255,255,255,.18), transparent 60%),radial-gradient(45% 45% at 50% 95%, var(--pp-accent-soft), transparent 60%);opacity:.55;filter:blur(30px);}

    .pp-pattern-aurora::before{content:"";position:absolute;left:-20%;top:-12%;width:140%;height:80%;background:linear-gradient(115deg, transparent 0%, var(--accent) 30%, rgba(255,255,255,.25) 50%, var(--pp-accent-soft) 70%, transparent 100%);filter:blur(60px);opacity:.55;}

    .pp-pattern-waves::before,.pp-pattern-waves::after{content:"";position:absolute;left:-10%;width:140%;height:60vh;opacity:.4;background-repeat:repeat-x;-webkit-mask-image:linear-gradient(transparent,#000);mask-image:linear-gradient(transparent,#000);}
    .pp-pattern-waves::before{bottom:0;background-image:radial-gradient(120px 40px at 60px 60px, var(--accent) 40%, transparent 42%);background-size:120px 80px;}
    .pp-pattern-waves::after{bottom:6vh;background-image:radial-gradient(120px 40px at 60px 60px, rgba(255,255,255,.2) 40%, transparent 42%);background-size:160px 90px;}

    .pp-pattern-particles::before,.pp-pattern-particles::after{content:"";position:absolute;inset:-20%;background-repeat:repeat;opacity:.5;}
    .pp-pattern-particles::before{background-image:radial-gradient(2px 2px at 30px 40px,#fff,transparent),radial-gradient(2px 2px at 120px 90px,var(--accent),transparent),radial-gradient(1px 1px at 200px 150px,#fff,transparent);background-size:240px 240px;}
    .pp-pattern-particles::after{background-image:radial-gradient(2px 2px at 80px 120px,var(--accent),transparent),radial-gradient(1px 1px at 160px 40px,#fff,transparent);background-size:300px 300px;opacity:.35;}

    .pp-pattern-noise{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");opacity:.06;}
</style>
</head>
<body class="min-h-screen {{ ($template['card_glass'] ?? false) ? 'pp-cardglass' : '' }}">

{{-- ── Fixed full-page background: image / video / animated pattern ── --}}
@php
    // Resolve a background asset reference: owner-supplied custom media is an
    // absolute URL (use as-is); bundled theme media is a relative public path
    // resolved through asset().
    $resolveBg = fn ($v) => $v ? (preg_match('#^(https?:)?//#i', $v) ? $v : asset($v)) : null;
    $bgImage   = $resolveBg($template['bg_image'] ?? null);
    $bgVideo   = $resolveBg($template['bg_video'] ?? null);
    $bgPattern = $template['bg_pattern'] ?? 'none';
    $hasMedia  = $bgImage || $bgVideo;
    $bgOverlay = $template['bg_overlay'] ?? 'linear-gradient(180deg, rgba(4,4,10,0.5) 0%, rgba(4,4,10,0.82) 100%)';
@endphp
<div class="pp-bg" aria-hidden="true">
    @if($bgImage)
        <div class="pp-bg-image" style="background-image:url('{{ $bgImage }}');"></div>
    @endif
    @if($bgVideo)
        <video class="pp-bg-video" autoplay muted loop playsinline preload="auto" @if($bgImage) poster="{{ $bgImage }}" @endif>
            <source src="{{ $bgVideo }}" type="video/mp4">
        </video>
    @endif
    @if($bgPattern !== 'none')
        <div class="pp-pattern pp-pattern-{{ $bgPattern }}"></div>
    @endif
    @if($hasMedia)
        <div class="pp-bg-overlay" style="background: {{ $bgOverlay }};"></div>
    @endif
</div>

@include('common.partials.viewer-login-modal')

<div class="max-w-3xl mx-auto px-3 sm:px-4 pb-24">

    {{-- ── Themed hero ─────────────────────────────────────── --}}
    <header class="pp-hero overflow-hidden mt-4 relative {{ ($template['motion'] ?? false) ? 'pp-anim-hero' : '' }}">
        {{-- Ambient motion layer, keyed off the template's hero_style. All
             wrapped in prefers-reduced-motion via the CSS above. --}}
        @php $heroStyle = $template['hero_style'] ?? 'glow'; @endphp
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            @if($heroStyle === 'aurora' || $heroStyle === 'glow')
                <div class="pp-orb absolute -top-16 -left-10 w-56 h-56 rounded-full" style="background: radial-gradient(circle, rgba(255,255,255,.45), transparent 70%); filter: blur(8px);"></div>
                <div class="pp-orb alt absolute top-10 right-0 w-72 h-72 rounded-full" style="background: radial-gradient(circle, rgba(255,255,255,.3), transparent 70%); filter: blur(12px);"></div>
            @elseif($heroStyle === 'grid')
                <div class="pp-grid-anim absolute -inset-1/4" style="background-image: linear-gradient(rgba(255,255,255,.18) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.18) 1px, transparent 1px); background-size: 34px 34px;"></div>
            @elseif($heroStyle === 'spotlight')
                <div class="pp-spot absolute inset-0" style="background: radial-gradient(60% 80% at 80% -10%, rgba(255,255,255,.4), transparent 60%);"></div>
            @elseif($heroStyle === 'wave')
                <div class="pp-orb absolute -bottom-20 -left-10 w-72 h-72 rounded-full" style="background: radial-gradient(circle, rgba(255,255,255,.35), transparent 70%); filter: blur(10px);"></div>
                <div class="pp-orb alt absolute -top-16 right-6 w-60 h-60 rounded-full" style="background: radial-gradient(circle, rgba(255,255,255,.28), transparent 70%); filter: blur(10px);"></div>
            @endif
        </div>

        @if($creator->cover_image)
            <img src="{{ $creator->cover_image }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-30">
        @endif

        <div class="relative px-5 sm:px-8 pt-10 pb-7">
            <div class="flex items-end gap-4 flex-wrap">
                @if($creator->avatar)
                    <img src="{{ $creator->avatar }}" alt="" class="w-24 h-24 rounded-2xl object-cover border-4 border-white/70 shadow-xl bg-white">
                @else
                    <div class="w-24 h-24 rounded-2xl border-4 border-white/70 shadow-xl bg-white/20 text-white flex items-center justify-center font-extrabold text-2xl backdrop-blur">
                        {{ $creator->getInitials() }}
                    </div>
                @endif
                <div class="min-w-0">
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-white drop-shadow flex items-center gap-2 flex-wrap">
                        {{ $link->title ?: $creator->name }}
                        @if(method_exists($creator, 'isVerified') && $creator->isVerified())
                            <span class="text-white/90" title="Verified"><i class="fas fa-circle-check"></i></span>
                        @endif
                    </h1>
                    <p class="text-white/80 text-sm mt-1">@<span class="font-semibold">{{ $creator->handle }}</span></p>
                </div>
            </div>

            @if($link->description ?: $creator->tagline)
                <p class="mt-4 text-white/90 text-base max-w-xl">{{ $link->description ?: $creator->tagline }}</p>
            @endif

            {{-- CTAs ─ subscribe / tip / message. Reuse the exact handle-based
                 routes so the whole monetization stack works unchanged. --}}
            <div class="mt-5 flex flex-wrap items-center gap-2">
                @if($isOwner)
                    <a href="{{ route('user.links.paid-page.editor', ['link' => $link->id]) }}" class="px-4 py-2 rounded-full bg-white text-slate-900 text-xs font-bold shadow hover:bg-white/90">
                        <i class="fas fa-sliders mr-1"></i> Edit page
                    </a>
                    <a href="{{ route('user.posts.index') }}" class="px-4 py-2 rounded-full pp-glass text-white text-xs font-semibold">
                        <i class="fas fa-feather mr-1"></i> Manage posts
                    </a>
                    <a href="{{ route('user.monetization.earnings') }}" class="px-4 py-2 rounded-full pp-glass text-white text-xs font-semibold">
                        <i class="fas fa-gem mr-1"></i> Monetization
                    </a>
                @else
                    @include('public.partials.follow-button', ['creator' => $creator, 'viewer' => $viewer, 'isFollowing' => $isFollowing])

                    @if($tiers->count())
                        @if($viewerSubscription && $viewerSubscription->isCurrent())
                            <a href="{{ route('creator-profile.subscription.manage', ['handle' => $creator->handle]) }}"
                               class="px-4 py-2 rounded-full pp-glass text-white text-xs font-semibold">
                                <i class="fas fa-circle-check mr-1"></i> Subscribed
                            </a>
                        @else
                            <a href="{{ route('creator-profile.subscribe.show', ['handle' => $creator->handle]) }}"
                               class="px-4 py-2 rounded-full pp-btn-accent text-xs font-bold shadow hover:opacity-90">
                                <i class="fas fa-gem mr-1"></i> Subscribe
                            </a>
                        @endif
                    @endif
                    @if($creator->canAcceptTips ?? true)
                        <button type="button"
                                data-cp-open-tip
                                data-cp-tip-creator="{{ $creator->id }}"
                                data-cp-tip-handle="{{ $creator->handle }}"
                                class="px-4 py-2 rounded-full bg-white/90 text-rose-600 text-xs font-bold hover:bg-white">
                            <i class="fas fa-heart mr-1"></i> Tip
                        </button>
                    @endif
                    @if(($creator->dm_access_mode ?? 'open') !== 'closed')
                        <button type="button"
                                data-cp-open-dm
                                data-cp-dm-handle="{{ $creator->handle ?: $creator->id }}"
                                class="px-4 py-2 rounded-full pp-glass text-white text-xs font-semibold">
                            <i class="fas fa-paper-plane mr-1"></i> Message
                        </button>
                    @endif
                @endif
            </div>
        </div>
    </header>

    {{-- ── Stats strip ─────────────────────────────────────── --}}
    <div class="pp-glass mt-3 px-5 py-4 grid grid-cols-3 text-center divide-x divide-white/10 rounded-2xl" style="border-radius: var(--pp-radius);">
        <div>
            <div class="text-xl font-extrabold text-white">{{ number_format($creator->posts_count ?? 0) }}</div>
            <div class="text-[11px] uppercase tracking-wider pp-muted mt-0.5">Posts</div>
        </div>
        <div>
            <div class="text-xl font-extrabold text-white">{{ number_format($creator->followers_count ?? 0) }}</div>
            <div class="text-[11px] uppercase tracking-wider pp-muted mt-0.5">Followers</div>
        </div>
        <div>
            <div class="text-xl font-extrabold text-white">{{ $creator->created_at?->format('M Y') ?: '—' }}</div>
            <div class="text-[11px] uppercase tracking-wider pp-muted mt-0.5">Joined</div>
        </div>
    </div>

    @if($isOwner)
        <div class="mt-3 pp-chip rounded-2xl px-4 py-3 text-xs font-medium flex items-center gap-2" style="border-radius: var(--pp-radius);">
            <i class="fas fa-circle-info"></i>
            <span>
                This is your Bizs Profile preview. It's
                <strong>{{ ($link->visibility ?? 'public') === 'public' ? 'public' : 'gated (sign-in required)' }}</strong>.
                Your posts &amp; tiers appear here automatically — there's no linking step; just publish them from your dashboard.
            </span>
        </div>
    @endif

    {{-- ── Posts feed ─────────────────────────────────────── --}}
    <section class="mt-6">
        <h2 class="text-xs uppercase tracking-[0.2em] pp-muted px-1 mb-3 font-semibold">
            Latest from {{ $creator->name }}
        </h2>
        @if($posts->count() === 0)
            <div class="cp-card p-10 text-center">
                <i class="fas fa-feather text-2xl opacity-30 mb-2"></i>
                <p class="text-sm opacity-70">{{ $isOwner ? 'You haven\'t shared anything yet — create a post to fill this page.' : 'No posts yet — check back soon.' }}</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($posts as $post)
                    @include('public.partials.creator-post-card', [
                        'post'          => $post,
                        'creator'       => $creator,
                        'totals'        => $reactionTotals[$post->id] ?? [],
                        'myReaction'    => $myReactions[$post->id] ?? null,
                        'comments'      => $commentsByPost[$post->id] ?? [],
                        'reactionDefs'  => $reactionDefs,
                        'viewer'        => $viewer,
                        'access'        => $accessByPost[$post->id] ?? ['can' => true, 'reason' => 'free'],
                    ])
                @endforeach
            </div>
            <div class="mt-5">{{ $posts->links() }}</div>
        @endif
    </section>

    <footer class="mt-10 text-center">
        <a href="{{ url('/@' . $creator->handle) }}" class="text-[11px] pp-muted hover:underline">
            <i class="fas fa-arrow-up-right-from-square mr-1"></i> View full profile
        </a>
    </footer>
</div>

@include('public.partials.creator-feed-scripts')

@include('public.partials.creator-dm-modal')

@include('public.partials.creator-tip-modal')

</body>
</html>
