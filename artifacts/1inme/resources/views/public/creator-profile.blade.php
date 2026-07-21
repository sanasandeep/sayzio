@php
    $__cpProfileUrl = route('creator-profile.show', $creator->handle);
    $__cpTitle = $creator->name . ' (@' . $creator->handle . ') - ' . config('app.name');
    $__cpDescription = Str::limit($creator->tagline ?: $creator->bio ?: ($creator->name . ' on Sayzio'), 180);
    $__cpImage = $creator->cover_image ?: $creator->creatorAvatarRaw() ?: null;

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
        '@context'     => 'https://schema.org',
        '@type'        => 'ProfilePage',
        'url'          => $__cpProfileUrl,
        'dateCreated'  => $creator->created_at?->toIso8601String(),
        'dateModified' => $creator->updated_at?->toIso8601String(),
        'mainEntity'   => $__cpPerson,
    ];

    // Tab visibility — a tab is only shown when it has content.
    // Deep-linkable via ?tab=about|posts|links|events (or hash #tab-*).
    $__tab = request()->input('tab', 'about');
    $__hasAbout  = true; // always show: hero/bio/highlights/CTA
    $__hasPosts  = ($sectionsVisible['posts'] ?? true);
    $__hasLinks  = (!empty($featuredLinks) || !empty($showcaseCards));
    $__hasEvents = ($sectionsVisible['events'] ?? true) && $upcomingEvents->isNotEmpty();
    // Normalize tab to a valid visible tab.
    $__validTabs = array_filter(['about' => $__hasAbout, 'posts' => $__hasPosts, 'links' => $__hasLinks, 'events' => $__hasEvents]);
    if (!array_key_exists($__tab, $__validTabs)) {
        $__tab = array_key_first($__validTabs) ?? 'about';
    }

    // Showcase icons for the showcase card section.
    $__showcaseIcons = [
        'qr'              => 'fas fa-qrcode',
        'form'            => 'fas fa-wpforms',
        'ics'             => 'fas fa-calendar-days',
        'vcard'           => 'fas fa-id-card',
        'resume'          => 'fas fa-file-user',
        'restaurant_menu' => 'fas fa-utensils',
        'store_menu'      => 'fas fa-store',
    ];
    $__showcaseLabels = [
        'qr'              => 'QR Code',
        'form'            => 'Form',
        'ics'             => 'Event',
        'vcard'           => 'Digital Card',
        'resume'          => 'Resume',
        'restaurant_menu' => 'Restaurant',
        'store_menu'      => 'Store',
    ];

    // CTA href resolver for each kind.
    $__ctaHref = function (array $btn): string {
        $val = trim($btn['value'] ?? '');
        return match ($btn['kind'] ?? '') {
            'email'    => 'mailto:' . $val,
            'whatsapp' => 'https://wa.me/' . preg_replace('/[^0-9+]/', '', $val),
            'call'     => 'tel:' . $val,
            'form'     => url('/' . $val),
            default    => preg_match('#^https?://#i', $val) ? $val : 'https://' . $val,
        };
    };
    $__ctaIcons = [
        'email'    => 'fas fa-envelope',
        'whatsapp' => 'fab fa-whatsapp',
        'call'     => 'fas fa-phone',
        'link'     => 'fas fa-arrow-up-right-from-square',
        'form'     => 'fas fa-wpforms',
    ];
    // Owner live preview. Two ways in: the web editor iframe (session-
    // authenticated owner) or the mobile app's WebView, which carries a
    // short-lived RELATIVE signed URL minted by the owner-only API
    // endpoint /me/creator-profile/preview-url (Task #5480).
    $__cpLive = request()->boolean('cp_preview') && (
        (auth()->check() && auth()->id() === $creator->id)
        || request()->hasValidSignature(false)
    );
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('common.partials.toolbar-theme-color')
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
    <meta property="og:image" content="{{ \App\Support\PublicStorageUrl::resolve($creator->cover_image) }}">
    <meta property="og:image:alt" content="{{ $creator->name }}">
@elseif($creator->creatorAvatarRaw())
    <meta property="og:image" content="{{ \App\Support\PublicStorageUrl::resolve($creator->creatorAvatarRaw()) }}">
    <meta property="og:image:alt" content="{{ $creator->name }}">
@endif
<meta name="twitter:card" content="{{ $__cpImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $creator->name }} (&#64;{{ $creator->handle }})">
<meta name="twitter:description" content="{{ $__cpDescription }}">
@if($__cpImage)
    <meta name="twitter:image" content="{{ $__cpImage }}">
    <meta name="twitter:image:alt" content="{{ $creator->name }}">
@endif
<script type="application/ld+json">{!! json_encode($__cpJsonLd, JSON_UNESCAPED_UNICODE) !!}</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@include('common.partials.fontawesome')
<script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
<script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
<style>
    [x-cloak]{display:none!important}
    .cp-card{background:#fff;border:1px solid rgba(15,23,42,0.06);border-radius:1rem;}
    html.light-mode .cp-card{background:#fff;border-color:rgba(15,23,42,0.08);}
    .cp-tab-bar{display:flex;gap:0;border-bottom:1px solid rgba(15,23,42,0.07);background:#fff;position:sticky;top:0;z-index:20;}
    html.light-mode .cp-tab-bar{background:#fff;border-color:rgba(15,23,42,0.1);}
    .cp-tab-btn{flex:1;padding:.65rem .5rem;font-size:.75rem;font-weight:700;text-align:center;color:#64748b;border-bottom:2px solid transparent;background:none;border-top:none;border-left:none;border-right:none;cursor:pointer;white-space:nowrap;transition:color .15s,border-color .15s;}
    .cp-tab-btn.active{color:#3d6bff;border-bottom-color:#3d6bff;}
    html.light-mode .cp-tab-btn{color:#475569;}
    html.light-mode .cp-tab-btn.active{color:#3d6bff;}
    .cp-feat-link-card{border-radius:.75rem;border:1px solid rgba(15,23,42,0.08);background:#fff;padding:.875rem;display:flex;flex-direction:column;gap:.375rem;transition:border-color .15s,box-shadow .15s;}
    .cp-feat-link-card:hover{border-color:#3d6bff;box-shadow:0 2px 12px rgba(61,107,255,.08);}
    html.light-mode .cp-feat-link-card{background:#fff;border-color:rgba(15,23,42,0.1);}
    /* ── Featured link style variants (Task #5459) ─────────────── */
    .cp-fl{display:block;text-decoration:none;transition:box-shadow .15s,background .15s,filter .15s;position:relative;}
    .cp-fl--classic{border-radius:.75rem;border:1px solid rgba(15,23,42,0.08);background:#fff;padding:.875rem;display:flex;flex-direction:column;gap:.375rem;}
    .cp-fl--classic:hover{border-color:var(--cp-accent,#3d6bff);box-shadow:0 2px 12px rgba(61,107,255,.08);}
    html.light-mode .cp-fl--classic{background:#fff;border-color:rgba(15,23,42,0.1);}
    .cp-fl--outline{border-radius:.75rem;border:2px solid var(--cp-accent,#3d6bff);background:transparent;padding:.7rem 1rem;display:flex;align-items:center;gap:.75rem;}
    .cp-fl--outline:hover{background:var(--cp-accent-soft,rgba(61,107,255,.06));}
    html.light-mode .cp-fl--outline{border-color:var(--cp-accent,#3d6bff);}
    .cp-fl--solid{border-radius:.75rem;background:var(--cp-accent,#3d6bff);padding:.7rem 1rem;display:flex;align-items:center;gap:.75rem;color:#fff;}
    .cp-fl--solid:hover{filter:brightness(1.1);}
    html.light-mode .cp-fl--solid{color:#fff;}
    .cp-fl--ghost{border-radius:.5rem;background:transparent;padding:.5rem .5rem;display:flex;align-items:center;gap:.75rem;color:var(--cp-accent,#3d6bff);}
    .cp-fl--ghost:hover{background:rgba(61,107,255,.05);}
    html.light-mode .cp-fl--ghost{color:var(--cp-accent,#3d6bff);}
    .cp-fl--pill{border-radius:9999px;background:var(--cp-accent,#3d6bff);padding:.65rem 1.5rem;display:flex;align-items:center;justify-content:center;gap:.5rem;color:#fff;}
    .cp-fl--pill:hover{filter:brightness(1.1);}
    html.light-mode .cp-fl--pill{color:#fff;}
    .cp-fl--card_heading{border-radius:1rem;border:1px solid rgba(15,23,42,0.08);background:#fff;padding:1rem;border-left:4px solid var(--cp-accent,#3d6bff);display:flex;flex-direction:column;gap:.25rem;}
    .cp-fl--card_heading:hover{box-shadow:0 4px 16px rgba(0,0,0,.07);}
    html.light-mode .cp-fl--card_heading{background:#fff;border-color:rgba(15,23,42,0.1);}
    .cp-highlight-pill{display:flex;flex-direction:column;align-items:center;padding:.5rem .25rem;}
    @if($ageGateRequired ?? false)
    body{overflow:hidden}
    @endif
    @if($creator->profile_theme_color)
    :root{
        --cp-accent:{{ $creator->profile_theme_color }};
        --cp-accent-soft:{{ $creator->profile_theme_color }}33;
        --cp-accent-mid:{{ $creator->profile_theme_color }}88;
    }
    @endif
    @if($__cpLive)
    /* ── Owner live-preview only (cp_preview=1) ─────────────────
       Density modes: the editor posts {density:'small'|'medium'|'large'}
       and the script below sets cp-d-small / cp-d-medium on <html>.
       Sections are tagged data-cpd="m" (medium+) or data-cpd="l" (large only).
       Real visitors never get these classes, so the public page is unchanged. */
    html.cp-d-small [data-cpd="m"],
    html.cp-d-small [data-cpd="l"],
    html.cp-d-medium [data-cpd="l"]{display:none!important}
    html.cp-d-small [data-cp-cover]{height:4.5rem!important}
    /* Dark preview theme: the editor posts {theme:'dark'|'light'} and the
       script sets cp-pv-dark. Scoped overrides for the page's hardcoded
       light utilities — preview-only, gated by $__cpLive. */
    html.cp-pv-dark body{background:#0b0d15;color:#e2e8f0}
    html.cp-pv-dark .cp-card,
    html.cp-pv-dark .cp-tab-bar,
    html.cp-pv-dark .cp-fl--classic,
    html.cp-pv-dark .cp-fl--card_heading,
    html.cp-pv-dark .cp-feat-link-card{background:#141826;border-color:rgba(255,255,255,0.09)}
    html.cp-pv-dark .cp-tab-btn{color:#8b95ab}
    html.cp-pv-dark .cp-tab-btn.active{color:#7d9bff;border-bottom-color:#7d9bff}
    html.cp-pv-dark .bg-white{background-color:#141826!important}
    html.cp-pv-dark .bg-slate-50{background-color:#0f1220!important}
    html.cp-pv-dark .bg-blue-50{background-color:rgba(61,107,255,0.16)!important}
    html.cp-pv-dark .text-slate-900,html.cp-pv-dark .text-slate-800{color:#f1f5f9!important}
    html.cp-pv-dark .text-slate-700,html.cp-pv-dark .text-slate-600{color:#cbd5e1!important}
    html.cp-pv-dark .text-slate-500,html.cp-pv-dark .text-slate-400{color:#94a3b8!important}
    html.cp-pv-dark .border-slate-200,html.cp-pv-dark .border-slate-100,
    html.cp-pv-dark .border-blue-100,html.cp-pv-dark .border-blue-200{border-color:rgba(255,255,255,0.10)!important}
    html.cp-pv-dark .divide-slate-100>:not([hidden])~:not([hidden]){border-color:rgba(255,255,255,0.08)}
    html.cp-pv-dark .cp-fl--classic span[style*="color:#0f172a"],
    html.cp-pv-dark .cp-fl--classic p[style*="color:#0f172a"]{color:#f1f5f9!important}
    @endif
</style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
@if($ageGateRequired ?? false)
    @include('public.partials.age-gate-overlay', ['creator' => $creator])
@endif
@include('common.partials.viewer-login-modal')
@include('common.partials.mini-profile-popover')

<div class="max-w-3xl mx-auto px-3 sm:px-4 pb-24" x-data="{ activeTab: @js($__tab) }">

    {{-- ── Hero ─────────────────────────────────────────────── --}}
    <header class="cp-card overflow-hidden mt-4">
        <div class="h-40 sm:h-56 relative" data-cp-cover style="background: linear-gradient(135deg, var(--cp-accent, #3b82f6), var(--cp-accent-mid, #a855f7));">
            @if($creator->cover_image)
                <img src="{{ \App\Support\PublicStorageUrl::resolve($creator->cover_image) }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            @endif
        </div>
        <div class="px-5 sm:px-7 pb-6 -mt-12 relative z-10">
            <div class="flex items-end justify-between gap-3 flex-wrap">
                <div class="flex items-end gap-4">
                    @if($creator->creatorAvatarRaw())
                        <img src="{{ \App\Support\PublicStorageUrl::resolve($creator->creatorAvatarRaw()) }}" alt="{{ $creator->name }}" class="w-24 h-24 rounded-2xl object-cover border-4 border-white shadow-md bg-white">
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

                                {{-- Report modal --}}
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
                        <span data-cpd="m">
                        @if($creator->verificationTickType)
                            {!! $creator->verificationTickType->tickHtml('text-xl') !!}
                        @else
                            <span class="text-blue-600" title="Verified"><i class="fas fa-circle-check"></i></span>
                        @endif
                        </span>
                    @endif
                </h1>
                <p class="text-slate-500 text-sm mt-0.5">@<span class="font-medium">{{ $creator->handle }}</span>
                    @if($creator->location || $__cpLive)
                        <span class="ml-2 text-slate-400" data-cp="location-wrap" data-cpd="m" @if(!$creator->location) style="display:none" @endif><i class="fas fa-location-dot mr-1"></i><span data-cp="location">{{ $creator->location }}</span></span>
                    @endif
                </p>
                @if($creator->tagline || $__cpLive)
                    <p class="mt-2 text-slate-700 text-base" data-cp="tagline" data-cpd="m" @if(!$creator->tagline) style="display:none" @endif>{{ $creator->tagline }}</p>
                @endif
                @if(is_array($creator->niche_tags) && count($creator->niche_tags))
                    <div class="mt-3 flex flex-wrap gap-1.5" data-cpd="m">
                        @foreach($creator->niche_tags as $tag)
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 font-medium">#{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </header>

    {{-- ── Highlights strip (Task #5431) ──────────────────── --}}
    @php
        $__hl = $showcase['highlights'] ?? [];
        $__showHl = ($sectionsVisible['highlights'] ?? true)
            && (($__hl['show_followers'] ?? true)
                || ($__hl['show_links'] ?? true)
                || ($__hl['show_member_since'] ?? true)
                || ($__hl['show_verified'] ?? true));
    @endphp
    @if($__showHl)
        <div class="cp-card mt-3 px-4 py-3 flex items-center justify-around gap-2 flex-wrap">
            @if($__hl['show_followers'] ?? true)
                <div class="cp-highlight-pill flex-1 min-w-[60px]">
                    <div class="text-lg font-extrabold text-slate-900">{{ number_format($creator->followers_count ?? 0) }}</div>
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 mt-0.5">Followers</div>
                </div>
            @endif
            @if(($__hl['show_links'] ?? true) && $totalPublicLinks > 0)
                <div class="cp-highlight-pill flex-1 min-w-[60px]">
                    <div class="text-lg font-extrabold text-slate-900">{{ number_format($totalPublicLinks) }}</div>
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 mt-0.5">Links</div>
                </div>
            @endif
            @if($__hl['show_member_since'] ?? true)
                <div class="cp-highlight-pill flex-1 min-w-[60px]">
                    <div class="text-lg font-extrabold text-slate-900">{{ $creator->created_at?->format('Y') ?: '—' }}</div>
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 mt-0.5">Since</div>
                </div>
            @endif
            @if(($__hl['show_verified'] ?? true) && method_exists($creator, 'isVerified') && $creator->isVerified())
                <div class="cp-highlight-pill flex-1 min-w-[60px]">
                    @if($creator->verificationTickType)
                        <div class="text-lg font-extrabold">{!! $creator->verificationTickType->tickHtml('text-lg') !!}</div>
                        <div class="text-[10px] uppercase tracking-wider text-slate-500 mt-0.5">{{ $creator->verificationTickType->name }}</div>
                    @else
                        <div class="text-lg font-extrabold text-blue-600"><i class="fas fa-circle-check"></i></div>
                        <div class="text-[10px] uppercase tracking-wider text-slate-500 mt-0.5">Verified</div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- ── CTA block (Task #5431) ──────────────────────────── --}}
    @php
        $__cta = $showcase['cta'] ?? [];
        $__ctaPrimary = is_array($__cta['primary'] ?? null) ? $__cta['primary'] : null;
        $__ctaSecondary = is_array($__cta['secondary'] ?? null) ? $__cta['secondary'] : [];
        $__showCta = ($sectionsVisible['cta'] ?? true)
            && ($__ctaPrimary !== null || count($__ctaSecondary) > 0);
    @endphp
    @if($__showCta)
        <section class="cp-card mt-3 p-5" data-cpd="m">
            <div class="flex flex-wrap gap-2 justify-center">
                @if($__ctaPrimary)
                    @php
                        $__primLabel = $__ctaPrimary['label'] ?: ($__ctaIcons[$__ctaPrimary['kind'] ?? ''] ? '' : 'Contact');
                        $__primIcon  = $__ctaIcons[$__ctaPrimary['kind'] ?? ''] ?? 'fas fa-arrow-right';
                        $__primHref  = $__ctaHref($__ctaPrimary);
                        $__primIsExt = in_array($__ctaPrimary['kind'] ?? '', ['link', 'form']);
                    @endphp
                    <a href="{{ $__primHref }}"
                       @if($__primIsExt) target="_blank" rel="noopener nofollow" @endif
                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white shadow-sm bg-gradient-to-r from-blue-600 to-fuchsia-600 hover:from-blue-700 hover:to-fuchsia-700">
                        <i class="{{ $__primIcon }}"></i>
                        {{ $__ctaPrimary['label'] ?: 'Get in touch' }}
                    </a>
                @endif
                @foreach($__ctaSecondary as $__secBtn)
                    @php
                        $__secIcon  = $__ctaIcons[$__secBtn['kind'] ?? ''] ?? 'fas fa-arrow-right';
                        $__secHref  = $__ctaHref($__secBtn);
                        $__secIsExt = in_array($__secBtn['kind'] ?? '', ['link', 'form']);
                    @endphp
                    <a href="{{ $__secHref }}"
                       @if($__secIsExt) target="_blank" rel="noopener nofollow" @endif
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:border-blue-400 hover:text-blue-700">
                        <i class="{{ $__secIcon }}"></i>
                        {{ $__secBtn['label'] }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ── Tab bar (Task #5431) ───────────────────────────── --}}
    @php
        $__tabDefs = [];
        if ($__hasAbout)  $__tabDefs['about']  = 'About';
        if ($__hasPosts)  $__tabDefs['posts']  = 'Posts';
        if ($__hasLinks)  $__tabDefs['links']  = 'Links';
        if ($__hasEvents) $__tabDefs['events'] = 'Events';
    @endphp
    @if(count($__tabDefs) > 1)
        <nav class="cp-tab-bar mt-3 rounded-t-xl overflow-hidden" data-cpd="l">
            @foreach($__tabDefs as $__tabKey => $__tabLabel)
                <button type="button"
                        class="cp-tab-btn"
                        :class="{ active: activeTab === @js($__tabKey) }"
                        @click="activeTab = @js($__tabKey); history.replaceState(null,'',location.pathname+'?tab='+@js($__tabKey))">
                    {{ $__tabLabel }}
                </button>
            @endforeach
        </nav>
    @endif

    {{-- ═══════════════════════════════════════════════════════
         Tab: About
         ═══════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'about'" x-cloak>

        {{-- ── Stats strip ─────────────────────────────────────── --}}
        @if(($sectionsVisible['stats'] ?? true))
            <div class="cp-card mt-3 px-5 py-4 grid grid-cols-3 text-center divide-x divide-slate-100" data-cpd="m">
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
        @if(($sectionsVisible['about'] ?? true) && (!empty($creator->bio) || $__cpLive))
            <section class="cp-card mt-3 p-5" data-cp="bio-section" data-cpd="m" @if(empty($creator->bio)) style="display:none" @endif>
                <h2 class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-2">About</h2>
                <p class="text-sm text-slate-700 whitespace-pre-line leading-relaxed" data-cp="bio">{{ $creator->bio }}</p>
            </section>
        @endif

        {{-- ── Socials ─────────────────────────────────────────── --}}
        @if(($sectionsVisible['socials'] ?? true) && is_array($creator->socials) && count($creator->socials))
            <section class="cp-card mt-3 p-5" data-cpd="m">
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
            <section class="cp-card mt-3 p-5 flex items-center justify-between gap-4" data-cpd="l">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">My links</p>
                    <p class="text-sm text-slate-700 mt-1 truncate">All my projects, services, and current focus.</p>
                </div>
                <a href="{{ url('/' . $primaryBiolink->alias) }}" class="shrink-0 px-4 py-2 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700">
                    Open biolink <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </section>
        @endif

        {{-- ── Featured links preview (on About tab when links tab exists) --}}
        @php
            $__flStyle   = $showcase['featured_links_style'] ?? 'classic';
            $__flOneCol  = in_array($__flStyle, ['ghost', 'pill']);
        @endphp
        @if(($sectionsVisible['featured_links'] ?? true) && count($featuredLinks) > 0 && $__hasLinks)
            <section class="cp-card mt-3 p-5" data-cpd="l">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Featured</h2>
                    <button type="button" @click="activeTab='links'"
                            class="text-xs font-semibold text-blue-600 hover:underline">See all →</button>
                </div>
                <div class="{{ $__flOneCol ? 'flex flex-col' : 'grid grid-cols-2' }} gap-2">
                    @foreach(array_slice($featuredLinks, 0, 2) as $fl)
                        <a href="{{ url('/' . $fl->alias) }}" target="_blank" rel="noopener nofollow"
                           class="cp-fl cp-fl--{{ $__flStyle }} min-w-0">
                            @if(in_array($__flStyle, ['classic', 'card_heading']))
                                <div class="flex items-center gap-2 min-w-0">
                                    @if($__flStyle === 'classic')
                                        <span class="shrink-0 text-sm" style="color:var(--cp-accent,#3d6bff)"><i class="fas fa-link"></i></span>
                                    @endif
                                    <span class="text-xs font-semibold truncate flex-1"
                                          style="color:{{ $__flStyle === 'card_heading' ? 'var(--cp-accent,#3d6bff)' : '#0f172a' }}">{{ $fl->title ?: $fl->alias }}</span>
                                </div>
                                <span class="text-[10px] uppercase" style="color:#94a3b8">{{ $fl->type }}</span>
                            @else
                                <span class="shrink-0 text-xs"><i class="fas fa-link"></i></span>
                                <span class="flex-1 text-xs font-semibold truncate">{{ $fl->title ?: $fl->alias }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

    </div>{{-- /about --}}

    {{-- ═══════════════════════════════════════════════════════
         Tab: Posts
         ═══════════════════════════════════════════════════════ --}}
    @if($__hasPosts)
    <div x-show="activeTab === 'posts'" x-cloak data-cpd="l">
        <section class="mt-4">
            <h2 class="text-xs uppercase tracking-wider text-slate-500 font-semibold px-1 mb-2">
                Latest posts
            </h2>
            @if($posts->count() === 0)
                <div class="cp-card p-8 text-center">
                    <i class="fas fa-feather text-2xl text-slate-300 mb-2"></i>
                    <p class="text-slate-500 text-sm">{{ $isOwner ? 'You haven\'t shared anything yet.' : 'No posts yet. Check back soon.' }}</p>
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
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════
         Tab: Links & Showcase (Task #5431)
         ═══════════════════════════════════════════════════════ --}}
    @if($__hasLinks)
    <div x-show="activeTab === 'links'" x-cloak data-cpd="l">

        {{-- ── Featured links ─────────────────────────────────── --}}
        @if(($sectionsVisible['featured_links'] ?? true) && count($featuredLinks) > 0)
            @php
                $__flStyleFull  = $showcase['featured_links_style'] ?? 'classic';
                $__flOneColFull = in_array($__flStyleFull, ['ghost', 'pill']);
            @endphp
            <section class="cp-card mt-3 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Featured links</h2>
                    @if($showcase['show_link_stats'] ?? false)
                        <span class="text-[10px] text-slate-400">Click counts shown</span>
                    @endif
                </div>
                <div class="{{ $__flOneColFull ? 'flex flex-col' : 'grid grid-cols-1 sm:grid-cols-2' }} gap-2.5">
                    @foreach($featuredLinks as $__i => $fl)
                        @php
                            $__flStats = ($showcase['show_link_stats'] ?? false) ? ($fl->clicks_count ?? 0) : null;
                            $__isTop   = $__i === 0 && $__flStats !== null && $__flStats > 0;
                        @endphp
                        <a href="{{ url('/' . $fl->alias) }}" target="_blank" rel="noopener nofollow"
                           class="cp-fl cp-fl--{{ $__flStyleFull }} min-w-0">
                            @if($__isTop)
                                <span class="absolute top-2 right-2 text-[9px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 font-bold uppercase tracking-wide z-10">Popular</span>
                            @endif
                            @if($__flStyleFull === 'classic')
                                <div class="flex items-start gap-2 pr-2">
                                    <span class="text-base mt-0.5 shrink-0" style="color:var(--cp-accent,#3d6bff)"><i class="fas fa-link"></i></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold truncate" style="color:#0f172a">{{ $fl->title ?: $fl->alias }}</p>
                                        <p class="text-[11px] uppercase truncate" style="color:#94a3b8">{{ $fl->type }}</p>
                                    </div>
                                </div>
                                @if($__flStats !== null)
                                    <p class="text-[11px] mt-1" style="color:#64748b"><i class="fas fa-mouse-pointer mr-1"></i>{{ number_format($__flStats) }} click{{ $__flStats === 1 ? '' : 's' }}</p>
                                @endif
                            @elseif($__flStyleFull === 'card_heading')
                                <p class="text-base font-bold truncate" style="color:var(--cp-accent,#3d6bff)">{{ $fl->title ?: $fl->alias }}</p>
                                <p class="text-xs uppercase font-semibold" style="color:#94a3b8">{{ $fl->type }}</p>
                                @if($__flStats !== null)
                                    <p class="text-[11px] mt-0.5" style="color:#64748b"><i class="fas fa-mouse-pointer mr-1"></i>{{ number_format($__flStats) }} click{{ $__flStats === 1 ? '' : 's' }}</p>
                                @endif
                            @else
                                <span class="shrink-0 text-sm"><i class="fas fa-link"></i></span>
                                <span class="flex-1 text-sm font-semibold truncate">{{ $fl->title ?: $fl->alias }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ── Showcase items ──────────────────────────────────── --}}
        @if(($sectionsVisible['showcase'] ?? true) && count($showcaseCards) > 0)
            <section class="cp-card mt-3 p-5">
                <h2 class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-3">Showcase</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($showcaseCards as $__card)
                        @php
                            $__cardLink = $__card['link'];
                            $__cardType = $__card['type'];
                            $__cardIcs  = $__card['ics_data'];
                            $__cardIcon = $__showcaseIcons[$__cardType] ?? 'fas fa-link';
                            $__cardTypeLabel = $__showcaseLabels[$__cardType] ?? $__cardType;
                        @endphp
                        <a href="{{ url('/' . $__cardLink->alias) }}" target="_blank" rel="noopener nofollow"
                           class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-blue-400 hover:bg-blue-50 transition">
                            <div class="w-10 h-10 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 shrink-0 text-base">
                                <i class="{{ $__cardIcon }}"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $__cardLink->title ?: $__cardLink->alias }}</p>
                                @if($__cardType === 'ics' && $__cardIcs && $__cardIcs->start_date)
                                    <p class="text-[11px] text-slate-500 mt-0.5 truncate">
                                        <i class="fas fa-calendar-days mr-1"></i>{{ $__cardIcs->start_date->format('M j, Y') }}
                                    </p>
                                    <p class="text-[10px] text-slate-400 uppercase font-semibold mt-0.5">Event</p>
                                @elseif($__cardType === 'form')
                                    <p class="text-[11px] text-slate-500 mt-0.5">Fill out this form</p>
                                    <p class="text-[10px] text-slate-400 uppercase font-semibold mt-0.5">Form</p>
                                @else
                                    <p class="text-[11px] text-slate-400 mt-0.5 uppercase font-semibold">{{ $__cardTypeLabel }}</p>
                                @endif
                            </div>
                            <i class="fas fa-arrow-right text-slate-300 text-xs shrink-0 mt-1"></i>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════
         Tab: Events (Task #3666)
         ═══════════════════════════════════════════════════════ --}}
    @if($__hasEvents)
    <div x-show="activeTab === 'events'" x-cloak data-cpd="l">
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
    </div>
    @endif

    {{-- ── Related creators (always shown below tabs) ──────── --}}
    @if(!empty($relatedCreators) && count($relatedCreators) > 0)
        <section class="cp-card mt-4 px-5 py-4" data-cpd="l">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-slate-900">More creators like {{ $creator->name }}</h3>
                <a href="{{ route('creators.index') }}" class="text-xs font-semibold text-blue-600 hover:underline">Browse all →</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach($relatedCreators as $rc)
                    <a href="{{ url('/@' . $rc->handle) }}" class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 hover:border-blue-400 hover:bg-blue-50 transition">
                        @if($rc->avatar)
                            <img src="{{ \App\Support\PublicStorageUrl::resolve($rc->avatar) }}" alt="" class="w-9 h-9 rounded-full object-cover bg-slate-100 shrink-0">
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

@if($__cpLive)
<script>
(function () {
    function txt(key, val) {
        var el = document.querySelector('[data-cp="' + key + '"]');
        if (el) el.textContent = val || '';
        return el;
    }
    window.addEventListener('message', function (e) {
        if (e.origin !== window.location.origin) return;
        var d = e.data || {};
        if (d.type !== 'cpLive') return;
        var root = document.documentElement;
        if (typeof d.density === 'string') {
            root.classList.toggle('cp-d-small', d.density === 'small');
            root.classList.toggle('cp-d-medium', d.density === 'medium');
        }
        if (d.theme === 'dark' || d.theme === 'light') {
            root.classList.toggle('light-mode', d.theme === 'light');
            root.classList.toggle('cp-pv-dark', d.theme === 'dark');
        }
        var tagEl = txt('tagline', d.tagline);
        if (tagEl) tagEl.style.display = d.tagline ? '' : 'none';
        txt('location', d.location);
        var locWrap = document.querySelector('[data-cp="location-wrap"]');
        if (locWrap) locWrap.style.display = d.location ? '' : 'none';
        txt('bio', d.bio);
        var bioSec = document.querySelector('[data-cp="bio-section"]');
        if (bioSec) bioSec.style.display = d.bio ? '' : 'none';
        if (typeof d.color === 'string') {
            var r = document.documentElement.style;
            if (/^#[0-9a-fA-F]{6}$/.test(d.color)) {
                r.setProperty('--cp-accent', d.color);
                r.setProperty('--cp-accent-soft', d.color + '33');
                r.setProperty('--cp-accent-mid', d.color + '88');
            } else if (d.color === '') {
                // Cleared in the editor: drop the inline overrides so the
                // preview falls back to the server-rendered value (or the
                // default gradient), matching what would actually be saved.
                r.removeProperty('--cp-accent');
                r.removeProperty('--cp-accent-soft');
                r.removeProperty('--cp-accent-mid');
            }
        }
    });
})();
</script>
@endif
</body>
</html>
