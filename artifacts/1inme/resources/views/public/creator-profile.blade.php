@php
    $__cpProfileUrl = route('creator-profile.show', $creator->handle);
    $__cpTitle = $creator->name . ' (@' . $creator->handle . ') - ' . config('app.name');
    $__cpDescription = Str::limit($creator->tagline ?: $creator->bio ?: ($creator->name . ' on Sayzio'), 180);
    $__cpImage = $creator->cover_image ?: $creator->avatar ?: null;

    // JSON-LD: identify the page as a ProfilePage whose mainEntity is the
    // creator, so search engines get a structured understanding of who the
    // page is about (Task #3883). Reuses the same social-link normalisation
    // as the "Find me on" section below so sameAs stays in lockstep.
    $__cpPlatforms = \App\Modules\User\Controllers\CreatorProfileController::SOCIAL_PLATFORMS;
    $__cpSameAs = [];
    if (is_array($creator->socials)) {
        foreach ($creator->socials as $key => $value) {
            if (!isset($__cpPlatforms[$key]) || $key === 'email' || empty($value)) continue;
            $href = $value;
            if ($key === 'twitter')   $href = preg_match('#^https?://#', $value) ? $value : 'https://twitter.com/'   . ltrim($value, '@');
            if ($key === 'instagram') $href = preg_match('#^https?://#', $value) ? $value : 'https://instagram.com/' . ltrim($value, '@');
            if ($key === 'tiktok')    $href = preg_match('#^https?://#', $value) ? $value : 'https://tiktok.com/@'   . ltrim($value, '@');
            if ($key === 'youtube')   $href = preg_match('#^https?://#', $value) ? $value : 'https://youtube.com/@'  . ltrim($value, '@');
            if ($key === 'github')    $href = preg_match('#^https?://#', $value) ? $value : 'https://github.com/'    . ltrim($value, '@');
            if ($key === 'twitch')    $href = preg_match('#^https?://#', $value) ? $value : 'https://twitch.tv/'     . ltrim($value, '@');
            $__cpSameAs[] = $href;
        }
    }
    if ($primaryBiolink ?? null) {
        $__cpSameAs[] = url('/' . $primaryBiolink->alias);
    }

    $__cpPerson = [
        '@type'         => 'Person',
        'name'          => $creator->name,
        'alternateName' => '@' . $creator->handle,
        'url'           => $__cpProfileUrl,
        'identifier'    => $creator->handle,
    ];
    if ($__cpImage)                $__cpPerson['image'] = $__cpImage;
    if ($creator->bio)             $__cpPerson['description'] = Str::limit($creator->bio, 500);
    elseif ($creator->tagline)     $__cpPerson['description'] = $creator->tagline;
    if ($creator->location)        $__cpPerson['address'] = ['@type' => 'PostalAddress', 'addressLocality' => $creator->location];
    if (!empty($__cpSameAs))       $__cpPerson['sameAs'] = array_values(array_unique($__cpSameAs));

    $__cpJsonLd = [
        '@context'    => 'https://schema.org',
        '@type'       => 'ProfilePage',
        'url'         => $__cpProfileUrl,
        'dateCreated' => $creator->created_at?->toIso8601String(),
        'mainEntity'  => $__cpPerson,
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $__cpTitle }}</title>
<meta name="description" content="{{ $__cpDescription }}">
<meta property="og:title" content="{{ $creator->name }} (&#64;{{ $creator->handle }})">
<meta property="og:description" content="{{ $__cpDescription }}">
<meta property="og:type" content="profile">
{{-- Single canonical URL for the profile: always the @-prefixed form,
     so the bare /handle and /@handle entry points are treated as one
     page for SEO + sharing and never double-counted. Host is normalised
     to the primary brand domain (see PlatformHosts::canonicalUrl) so a
     visit on any recognised brand domain advertises the same URL. --}}
<link rel="canonical" href="{{ \App\Modules\Common\Support\PlatformHosts::canonicalUrl() }}">
<meta property="og:url" content="{{ \App\Modules\Common\Support\PlatformHosts::canonicalUrl() }}">
@if($creator->cover_image)
    <meta property="og:image" content="{{ $creator->cover_image }}">
@elseif($creator->avatar)
    <meta property="og:image" content="{{ $creator->avatar }}">
@endif
<meta name="twitter:card" content="{{ $__cpImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $creator->name }} (&#64;{{ $creator->handle }})">
<meta name="twitter:description" content="{{ $__cpDescription }}">
@if($__cpImage)
    <meta name="twitter:image" content="{{ $__cpImage }}">
@endif
<script type="application/ld+json">{!! json_encode($__cpJsonLd, JSON_UNESCAPED_UNICODE) !!}</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@include('common.partials.fontawesome')
<script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
<script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
<style>
    [x-cloak]{display:none!important}
    .cp-card{background:#fff;border:1px solid rgba(15,23,42,0.06);border-radius:1rem;}
    @if($ageGateRequired ?? false)
    body{overflow:hidden}
    @endif
</style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
@if($ageGateRequired ?? false)
    @include('public.partials.age-gate-overlay', ['creator' => $creator])
@endif
@include('common.partials.viewer-login-modal')

<div class="max-w-3xl mx-auto px-3 sm:px-4 pb-24">

    {{-- ── Hero ─────────────────────────────────────────────── --}}
    <header class="cp-card overflow-hidden mt-4">
        <div class="h-40 sm:h-56 bg-gradient-to-br from-blue-500 via-fuchsia-500 to-indigo-500 relative">
            @if($creator->cover_image)
                <img src="{{ $creator->cover_image }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            @endif
        </div>
        <div class="px-5 sm:px-7 pb-6 -mt-12">
            <div class="flex items-end justify-between gap-3 flex-wrap">
                <div class="flex items-end gap-4">
                    @if($creator->avatar)
                        <img src="{{ $creator->avatar }}" alt="" class="w-24 h-24 rounded-2xl object-cover border-4 border-white shadow-md bg-white">
                    @else
                        <div class="w-24 h-24 rounded-2xl border-4 border-white shadow-md bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center font-extrabold text-2xl">
                            {{ $creator->getInitials() }}
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-2 mb-1 flex-wrap justify-end">
                    @if($isOwner)
                        <a href="{{ route('user.creator-profile.edit') }}" class="px-3.5 py-2 rounded-lg bg-slate-900 text-white text-xs font-semibold hover:bg-slate-700">
                            <i class="fas fa-pen mr-1"></i> Edit profile
                        </a>
                        <a href="{{ route('user.monetization.earnings') }}" class="px-3.5 py-2 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 text-xs font-semibold hover:bg-blue-100">
                            <i class="fas fa-gem mr-1"></i> Monetization
                        </a>
                    @else
                        @include('public.partials.follow-button', ['creator' => $creator, 'viewer' => $viewer, 'isFollowing' => $isFollowing])

                        {{-- ── Monetization CTAs (Task #1209) ──────────────── --}}
                        @if($tiers->count())
                            @if($viewerSubscription && $viewerSubscription->isCurrent())
                                <a href="{{ route('creator-profile.subscription.manage', ['handle' => $creator->handle]) }}"
                                   class="px-3.5 py-2 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 text-xs font-semibold hover:bg-blue-100">
                                    <i class="fas fa-circle-check mr-1"></i> Subscribed
                                </a>
                            @else
                                <a href="{{ route('creator-profile.subscribe.show', ['handle' => $creator->handle]) }}"
                                   class="px-3.5 py-2 rounded-lg text-xs font-semibold text-white shadow-sm bg-gradient-to-r from-blue-600 to-fuchsia-600 hover:from-blue-700 hover:to-fuchsia-700">
                                    <i class="fas fa-gem mr-1"></i> Subscribe
                                </a>
                            @endif
                        @endif
                        @if($creator->canAcceptTips ?? true)
                            <button type="button"
                                    data-cp-open-tip
                                    data-cp-tip-creator="{{ $creator->id }}"
                                    data-cp-tip-handle="{{ $creator->handle }}"
                                    class="px-3.5 py-2 rounded-lg bg-white border border-slate-200 text-rose-600 text-xs font-semibold hover:border-rose-400">
                                <i class="fas fa-heart mr-1"></i> Tip
                            </button>

                            {{-- Task #1211 — Block / report kebab. Visible only to
                                 logged-in viewers (otherwise both endpoints would
                                 require auth and bounce them through OTP). --}}
                            @auth
                            <div class="relative" x-data="{open:false, reportOpen:false}">
                                <button type="button" @click="open=!open"
                                        class="px-2.5 py-2 rounded-lg bg-white border border-slate-200 text-slate-500 text-xs hover:border-slate-400" title="More">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div x-show="open" @click.outside="open=false" x-transition x-cloak
                                     class="absolute right-0 mt-1 w-48 bg-white border border-slate-200 rounded-lg shadow-lg z-30 overflow-hidden">
                                    <form method="POST" action="{{ route('users.block.toggle', ['creator' => $creator->id]) }}">
                                        @csrf
                                        <button class="w-full text-left px-3 py-2 text-xs hover:bg-slate-50 text-slate-700">
                                            <i class="fas fa-ban mr-1.5 text-slate-500"></i> Block creator
                                        </button>
                                    </form>
                                    <button type="button" @click="reportOpen=true; open=false"
                                            class="w-full text-left px-3 py-2 text-xs hover:bg-slate-50 text-rose-600 border-t border-slate-100">
                                        <i class="fas fa-flag mr-1.5"></i> Report creator
                                    </button>
                                    <a href="{{ route('legal.dmca.show', ['handle' => $creator->handle]) }}"
                                       class="block px-3 py-2 text-xs hover:bg-slate-50 text-slate-700 border-t border-slate-100">
                                        <i class="fas fa-gavel mr-1.5 text-slate-500"></i> DMCA takedown
                                    </a>
                                </div>

                                {{-- Report modal — submits the enum reason + optional
                                     free-text comment that UserReportController validates. --}}
                                <div x-show="reportOpen" x-transition x-cloak
                                     class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] flex items-center justify-center"
                                     style="display:none;">
                                    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-[95%] p-5" @click.outside="reportOpen=false">
                                        <div class="flex items-start justify-between mb-3">
                                            <h3 class="text-base font-bold text-slate-900">Report {{ $creator->name }}</h3>
                                            <button type="button" @click="reportOpen=false" class="text-slate-400 hover:text-slate-700">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <form method="POST" action="{{ route('users.report', ['creator' => $creator->id]) }}" class="space-y-3">
                                            @csrf
                                            <div>
                                                <label class="text-xs font-semibold text-slate-700">Reason</label>
                                                <select name="reason" required class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                                                    @foreach(\App\Modules\Common\Models\UserReport::REASONS as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-700">Add a note (optional)</label>
                                                <textarea name="comment" rows="3" maxlength="1000"
                                                          placeholder="More context for our moderators…"
                                                          class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"></textarea>
                                            </div>
                                            <button type="submit" class="w-full py-2 rounded-lg text-sm font-bold text-white bg-rose-600 hover:bg-rose-700">
                                                Send report
                                            </button>
                                            <p class="text-[11px] text-slate-500 text-center">Reports are private and reviewed by our moderators.</p>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endauth
                        @endif
                        @if($creator->isSectionVisible('contact'))
                            <a href="mailto:{{ $creator->email }}" class="px-3.5 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:border-blue-400 hover:text-blue-600">
                                <i class="fas fa-envelope mr-1"></i> Contact
                            </a>
                        @endif
                        {{-- Paid DMs (Task #1210): Message button --}}
                        @if(($creator->dm_access_mode ?? 'open') !== 'closed')
                            <button type="button"
                                    data-cp-open-dm
                                    data-cp-dm-handle="{{ $creator->handle ?: $creator->id }}"
                                    class="px-3.5 py-2 rounded-lg text-xs font-semibold text-white shadow-sm bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700">
                                <i class="fas fa-paper-plane mr-1"></i> Message
                            </button>
                        @endif
                    @endif
                </div>
            </div>

            <div class="mt-4">
                <h1 class="text-2xl sm:text-3xl font-extrabold flex items-center gap-2 flex-wrap">
                    {{ $creator->name }}
                    @if(method_exists($creator, 'isVerified') && $creator->isVerified())
                        <span class="text-blue-600" title="Verified"><i class="fas fa-circle-check"></i></span>
                    @endif
                </h1>
                <p class="text-slate-500 text-sm mt-0.5">@<span class="font-medium">{{ $creator->handle }}</span>
                    @if($creator->location)
                        <span class="ml-2 text-slate-400"><i class="fas fa-location-dot mr-1"></i>{{ $creator->location }}</span>
                    @endif
                </p>
                @if($creator->tagline)
                    <p class="mt-2 text-slate-700 text-base">{{ $creator->tagline }}</p>
                @endif
                @if(is_array($creator->niche_tags) && count($creator->niche_tags))
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach($creator->niche_tags as $tag)
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 font-medium">#{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </header>

    {{-- ── Stats strip ─────────────────────────────────────── --}}
    @if(($sectionsVisible['stats'] ?? true))
        <div class="cp-card mt-3 px-5 py-4 grid grid-cols-3 text-center divide-x divide-slate-100">
            <div>
                <div class="text-xl font-extrabold">{{ number_format($creator->posts_count ?? 0) }}</div>
                <div class="text-[11px] uppercase tracking-wider text-slate-500 mt-0.5">Posts</div>
            </div>
            <div>
                <div class="text-xl font-extrabold">{{ number_format($creator->followers_count ?? 0) }}</div>
                <div class="text-[11px] uppercase tracking-wider text-slate-500 mt-0.5">Followers</div>
            </div>
            <div>
                <div class="text-xl font-extrabold">{{ $creator->created_at?->format('M Y') ?: '—' }}</div>
                <div class="text-[11px] uppercase tracking-wider text-slate-500 mt-0.5">Joined</div>
            </div>
        </div>
    @endif

    {{-- ── About ───────────────────────────────────────────── --}}
    @if(($sectionsVisible['about'] ?? true) && !empty($creator->bio))
        <section class="cp-card mt-3 p-5">
            <h2 class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-2">About</h2>
            <p class="text-sm text-slate-700 whitespace-pre-line leading-relaxed">{{ $creator->bio }}</p>
        </section>
    @endif

    {{-- ── Socials ─────────────────────────────────────────── --}}
    @if(($sectionsVisible['socials'] ?? true) && is_array($creator->socials) && count($creator->socials))
        <section class="cp-card mt-3 p-5">
            <h2 class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-3">Find me on</h2>
            <div class="flex flex-wrap gap-2">
                @php
                    $platforms = \App\Modules\User\Controllers\CreatorProfileController::SOCIAL_PLATFORMS;
                @endphp
                @foreach($creator->socials as $key => $value)
                    @php
                        $p = $platforms[$key] ?? null;
                        if (!$p) continue;
                        $href = $value;
                        if ($key === 'twitter')   $href = preg_match('#^https?://#', $value) ? $value : 'https://twitter.com/'   . ltrim($value, '@');
                        if ($key === 'instagram') $href = preg_match('#^https?://#', $value) ? $value : 'https://instagram.com/' . ltrim($value, '@');
                        if ($key === 'tiktok')    $href = preg_match('#^https?://#', $value) ? $value : 'https://tiktok.com/@'   . ltrim($value, '@');
                        if ($key === 'youtube')   $href = preg_match('#^https?://#', $value) ? $value : 'https://youtube.com/@'  . ltrim($value, '@');
                        if ($key === 'github')    $href = preg_match('#^https?://#', $value) ? $value : 'https://github.com/'    . ltrim($value, '@');
                        if ($key === 'twitch')    $href = preg_match('#^https?://#', $value) ? $value : 'https://twitch.tv/'     . ltrim($value, '@');
                        if ($key === 'email')     $href = preg_match('#^mailto:#', $value)   ? $value : 'mailto:' . $value;
                    @endphp
                    <a href="{{ $href }}" target="_blank" rel="noopener nofollow"
                       class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-blue-50 hover:text-blue-700 text-slate-700 text-xs font-semibold border border-slate-200">
                        <i class="{{ $p['icon'] }}"></i> {{ $p['label'] }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ── Biolink callout ────────────────────────────────── --}}
    @if(($sectionsVisible['biolink'] ?? true) && $primaryBiolink)
        <section class="cp-card mt-3 p-5 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">My links</p>
                <p class="text-sm text-slate-700 mt-1 truncate">All my projects, services, and current focus.</p>
            </div>
            <a href="{{ url('/' . $primaryBiolink->alias) }}" class="shrink-0 px-4 py-2 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700">
                Open biolink <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </section>
    @endif

    {{-- ── Events (Task #3666) — a few upcoming public events, gated by
         the same $sectionsVisible pattern and only shown when the
         creator actually has upcoming events. ── --}}
    @if(($sectionsVisible['events'] ?? true) && $upcomingEvents->isNotEmpty())
        <section class="cp-card mt-3 p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Upcoming events</h2>
                <a href="{{ route('creator-profile.events', $creator->handle) }}" class="text-xs font-semibold text-blue-600 hover:underline">See all events →</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach($upcomingEvents as $event)
                    @php $eventIcs = $event->icsData; @endphp
                    <a href="{{ url('/' . $event->alias) }}" class="flex items-center gap-3 p-2.5 rounded-lg border border-slate-200 hover:border-blue-400 hover:bg-blue-50 transition">
                        @if($eventIcs && $eventIcs->start_date)
                            <div class="shrink-0 w-11 h-11 rounded-lg bg-slate-50 border border-slate-200 text-center leading-none flex flex-col items-center justify-center">
                                <div class="text-[9px] font-bold uppercase text-blue-600">{{ $eventIcs->start_date->format('M') }}</div>
                                <div class="text-sm font-extrabold text-slate-900">{{ $eventIcs->start_date->format('j') }}</div>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-slate-900 truncate">{{ $event->title }}</div>
                            @if($eventIcs && $eventIcs->start_date)
                                <div class="text-[11px] text-slate-500 truncate">{{ $eventIcs->start_date->format('D, M j · g:i A') }}</div>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ── Posts feed ─────────────────────────────────────── --}}
    @if(($sectionsVisible['posts'] ?? true))
        <section class="mt-4">
            <h2 class="text-xs uppercase tracking-wider text-slate-500 font-semibold px-1 mb-2">
                Latest posts
            </h2>
            @if($posts->count() === 0)
                <div class="cp-card p-8 text-center">
                    <i class="fas fa-feather text-2xl text-slate-300 mb-2"></i>
                    <p class="text-slate-500 text-sm">{{ $isOwner ? 'You haven\'t shared anything yet.' : 'No posts yet — check back soon.' }}</p>
                </div>
            @else
                <div class="space-y-3">
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
                <div class="mt-4">{{ $posts->links() }}</div>
            @endif
        </section>
    @endif

    {{-- Task #1211 — "More creators like {{ $creator->name }}". Surfaces only
         when there are matches; uses the cached helper on CreatorsController
         so we don't run discovery queries on every profile view. --}}
    @if(!empty($relatedCreators) && count($relatedCreators) > 0)
        <section class="cp-card mt-4 px-5 py-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-slate-900">More creators like {{ $creator->name }}</h3>
                <a href="{{ route('creators.index') }}" class="text-xs font-semibold text-blue-600 hover:underline">Browse all →</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach($relatedCreators as $rc)
                    <a href="{{ url('/@' . $rc->handle) }}" class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 hover:border-blue-400 hover:bg-blue-50 transition">
                        @if($rc->avatar)
                            <img src="{{ $rc->avatar }}" alt="" class="w-9 h-9 rounded-full object-cover bg-slate-100 shrink-0">
                        @else
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                {{ $rc->getInitials() }}
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-slate-900 truncate">{{ $rc->name }}</div>
                            <div class="text-[11px] text-slate-500 truncate">@ {{ $rc->handle }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>

@include('public.partials.creator-feed-scripts')

@include('public.partials.creator-dm-modal')

@include('public.partials.creator-tip-modal')

</body>
</html>
