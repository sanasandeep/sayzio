@extends('user.layouts.app')
@section('title', 'Dashboard')

@push('styles')
<style>
    /* ===================== Dashboard view tabs ===================== */
    .dash-tabs {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px;
        border-radius: 14px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        box-shadow: var(--card-shadow);
        max-width: 100%;
        overflow-x: auto;
    }
    .dash-tab {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--text-muted);
        background: transparent;
        border: none;
        cursor: pointer;
        white-space: nowrap;
        transition: all .2s ease;
    }
    .dash-tab:hover { color: var(--text-primary); background: var(--bg-glass-hover); }
    .dash-tab:focus-visible { outline: 2px solid rgba(61,107,255,0.5); outline-offset: 2px; }
    .dash-tab-active {
        color: #fff;
        background: linear-gradient(135deg, #3d6bff, #5c83ff);
        box-shadow: 0 6px 16px -4px rgba(61,107,255,0.45);
    }
    .dash-tab-active:hover { color: #fff; }
    .dash-row { transition: background-color .18s ease; }
    .dash-row:hover { background: var(--bg-glass-hover); }

    @media (prefers-reduced-motion: reduce) {
        .dash-tab, .dash-row { transition: none; }
    }
</style>
{{-- The bento tile system (grid, glass tiles, live-pulse hero) lives in a
     shared partial so other user surfaces reuse the exact same look. --}}
@include('user.partials.bento-styles')
@endpush

@section('content')
@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $heroIcon = $hour < 12 ? 'fa-sun' : ($hour < 17 ? 'fa-cloud-sun' : 'fa-moon');
    $heroDay = now()->format('l');

    $channelLabelMap = \App\Modules\Common\Services\ChannelClassifier::LABELS;
    $channelTotal    = (int) ($channelStats->sum('count') ?? 0);
    $channelIcon = function (string $key): string {
        return [
            \App\Modules\Common\Services\ChannelClassifier::KEY_1INME_APP       => 'fa-mobile-screen-button',
            \App\Modules\Common\Services\ChannelClassifier::KEY_INSTAGRAM       => 'fa-brands fa-instagram',
            \App\Modules\Common\Services\ChannelClassifier::KEY_TIKTOK          => 'fa-brands fa-tiktok',
            \App\Modules\Common\Services\ChannelClassifier::KEY_FACEBOOK        => 'fa-brands fa-facebook',
            \App\Modules\Common\Services\ChannelClassifier::KEY_MESSENGER       => 'fa-brands fa-facebook-messenger',
            \App\Modules\Common\Services\ChannelClassifier::KEY_SNAPCHAT        => 'fa-brands fa-snapchat',
            \App\Modules\Common\Services\ChannelClassifier::KEY_LINKEDIN        => 'fa-brands fa-linkedin',
            \App\Modules\Common\Services\ChannelClassifier::KEY_TWITTER         => 'fa-brands fa-x-twitter',
            \App\Modules\Common\Services\ChannelClassifier::KEY_PINTEREST       => 'fa-brands fa-pinterest',
            \App\Modules\Common\Services\ChannelClassifier::KEY_YOUTUBE         => 'fa-brands fa-youtube',
            \App\Modules\Common\Services\ChannelClassifier::KEY_WHATSAPP        => 'fa-brands fa-whatsapp',
            \App\Modules\Common\Services\ChannelClassifier::KEY_TELEGRAM        => 'fa-brands fa-telegram',
            \App\Modules\Common\Services\ChannelClassifier::KEY_GENERIC_WEBVIEW => 'fa-window-maximize',
            \App\Modules\Common\Services\ChannelClassifier::KEY_BROWSER         => 'fa-globe',
            \App\Modules\Common\Services\ChannelClassifier::KEY_BOT             => 'fa-robot',
            \App\Modules\Common\Services\ChannelClassifier::KEY_UNKNOWN         => 'fa-circle-question',
        ][$key] ?? 'fa-circle-question';
    };
    $channelBuildUrl = function (?string $key): string {
        return $key ? route('user.dashboard', ['channel' => $key]) : route('user.dashboard');
    };

    $planPrice = $user->plan
        ? \App\Services\PricingResolver::priceFor($user->plan, $user, 'monthly')
        : null;
@endphp

<div class="bento-stage">

    {{-- ===================== LIVE-PULSE HERO TILE =====================
         Anchors the whole grid: greeting + primary CTAs on the left, a live
         "clicks today" pulse orb with the lifetime total folded in on the
         right. Absorbs the old page-hero header. --}}
    <div class="bento-hero">
        <div class="hero-grid">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-2">
                    <span class="hero-chip"><i class="fas {{ $heroIcon }}"></i> {{ $heroDay }}</span>
                    <span class="hero-chip"><i class="fas fa-link"></i> {{ $totalLinks }} links</span>
                    @if($activeLinks !== $totalLinks)
                        <span class="hero-chip"><i class="fas fa-circle text-emerald-400" style="font-size:6px;"></i> {{ $activeLinks }} active</span>
                    @endif
                </div>
                <h1 class="hero-title gradient-text" style="font-size: clamp(1.5rem, 3.2vw, 2.1rem);">{{ $greeting }}, {{ $user->name }}</h1>
                <p class="hero-subtitle">Here's your command center — a live look at how your links are performing.</p>
                <div class="flex items-center gap-2 flex-wrap mt-4">
                    <a href="{{ route('user.links.wizard') }}" class="btn-primary text-xs py-2">
                        <i class="fas fa-magic text-[10px]"></i> Create Link in Bio
                    </a>
                    <a href="{{ route('user.links.create') }}" class="btn-ghost text-xs py-2">
                        <i class="fas fa-plus text-[10px]"></i> Quick Link
                    </a>
                    <a href="{{ route('user.onboarding.template') }}" class="btn-ghost text-xs py-2">
                        <i class="fas fa-layer-group text-[10px]"></i> Switch Template
                    </a>
                </div>
            </div>

            {{-- Live pulse: today's clicks --}}
            <div class="flex items-center gap-4">
                <div class="pulse-orb">
                    <span class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($clicksToday) }}</span>
                    <span class="text-[9px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">today</span>
                </div>
                <div>
                    <span class="live-dot"><span class="dot"></span> Live</span>
                    <p class="text-sm font-semibold mt-1.5" style="color: var(--text-primary);">Clicks today</p>
                    <p class="text-xs mt-0.5" style="color: var(--text-muted);">
                        <strong style="color: var(--text-secondary);">{{ number_format($totalClicks) }}</strong> total all-time
                    </p>
                    <a href="{{ route('user.links.index') }}" class="text-[11px] text-blue-400 hover:text-blue-300 font-semibold inline-flex items-center gap-1 mt-2">
                        View links <i class="fas fa-arrow-right text-[9px]"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if($user->accountBadges->isNotEmpty())
        <div class="mb-5 flex flex-wrap items-center gap-2">
            <span class="text-[11px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Your badges</span>
            @foreach($user->accountBadges as $badge)
                <span class="badge inline-flex items-center gap-1.5"
                      style="background: {{ $badge->color }}26; color: {{ $badge->color }}; border: 1px solid {{ $badge->color }}40;">
                    <i class="fas fa-certificate text-[10px]"></i>{{ $badge->name }}
                </span>
            @endforeach
        </div>
    @endif

    @if(!empty($channelFilter))
        <div class="mb-5 flex flex-wrap items-center gap-2 px-4 py-3 rounded-xl"
             style="background: rgba(34,211,238,0.08); border: 1px solid rgba(34,211,238,0.25);">
            <span class="text-[11px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Filtered by channel</span>
            <span class="badge inline-flex items-center gap-1.5"
                  style="background: rgba(34,211,238,0.15); color: #67e8f9; border: 1px solid rgba(34,211,238,0.3);">
                <i class="fas {{ $channelIcon($channelFilter) }} text-[10px]"></i>
                {{ $channelLabelMap[$channelFilter] ?? $channelFilter }}
            </span>
            <span class="text-[11px]" style="color: var(--text-faint);">Click totals below reflect this bucket only.</span>
            <a href="{{ $channelBuildUrl(null) }}" class="ml-auto text-[11px] text-blue-400 hover:text-blue-300 font-semibold inline-flex items-center gap-1">
                <i class="fas fa-times text-[9px]"></i> Clear filter
            </a>
        </div>
    @endif

    <div x-data="{
            tab: '{{ !empty($channelFilter) ? 'traffic' : 'overview' }}',
            channelForced: {{ !empty($channelFilter) ? 'true' : 'false' }},
            tabs: ['overview', 'traffic', 'growth'],
            init() {
                // A ?channel= filter always wins and pins the Traffic tab.
                if (!this.channelForced) {
                    try {
                        const saved = localStorage.getItem('1inme_dashboard_tab');
                        if (saved && this.tabs.includes(saved)) this.tab = saved;
                    } catch (e) {}
                }
                // Remember the user's last-selected tab for next time.
                this.$watch('tab', (val) => {
                    try { localStorage.setItem('1inme_dashboard_tab', val); } catch (e) {}
                });
            }
        }">

        {{-- Dashboard view tabs — split the long scroll into calmer, focused views. --}}
        <div class="dash-tabs mb-6" role="tablist" aria-label="Dashboard views">
            <button type="button" role="tab"
                    @click="tab = 'overview'" :aria-selected="tab === 'overview' ? 'true' : 'false'"
                    class="dash-tab" :class="tab === 'overview' ? 'dash-tab-active' : ''">
                <i class="fas fa-gauge-high text-[11px]"></i> Overview
            </button>
            <button type="button" role="tab"
                    @click="tab = 'traffic'" :aria-selected="tab === 'traffic' ? 'true' : 'false'"
                    class="dash-tab" :class="tab === 'traffic' ? 'dash-tab-active' : ''">
                <i class="fas fa-chart-pie text-[11px]"></i> Traffic
            </button>
            <button type="button" role="tab"
                    @click="tab = 'growth'" :aria-selected="tab === 'growth' ? 'true' : 'false'"
                    class="dash-tab" :class="tab === 'growth' ? 'dash-tab-active' : ''">
                <i class="fas fa-arrow-trend-up text-[11px]"></i> Growth
            </button>
        </div>

        {{-- ===================== OVERVIEW TAB ===================== --}}
        <div x-show="tab === 'overview'" x-cloak role="tabpanel">

            {{-- Metric bento: one tall feature tile (Total Clicks) + four regular tiles. --}}
            <div class="bento mb-5">
                {{-- Total Clicks — tall feature tile --}}
                <div class="bento-tile accent b-feat justify-between p-6" style="--tile-accent: linear-gradient(90deg, #3b82f6, #90acff); --tile-glow: rgba(59,130,246,0.20);">
                    <span class="tile-orb"></span>
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Total Clicks</p>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.2);">
                            <i class="fas fa-mouse-pointer text-blue-400 text-sm"></i>
                        </div>
                    </div>
                    <div>
                        <p class="font-bold leading-none" style="color: var(--text-primary); font-size: clamp(2rem, 5vw, 3.25rem);">{{ number_format($totalClicks) }}</p>
                        <p class="text-xs mt-2" style="color: var(--text-muted);">
                            <i class="fas fa-bolt text-amber-400 text-[10px] mr-1"></i>{{ number_format($clicksToday) }} today
                        </p>
                    </div>
                </div>

                {{-- Today --}}
                <div class="bento-tile accent b-2 justify-between p-5" style="--tile-accent: linear-gradient(90deg, #f59e0b, #fbbf24); --tile-glow: rgba(245,158,11,0.18);">
                    <span class="tile-orb"></span>
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Today</p>
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.2);">
                            <i class="fas fa-chart-line text-amber-400 text-xs"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold mt-2" style="color: var(--text-primary);">{{ number_format($clicksToday) }}</p>
                </div>

                {{-- Plan --}}
                <a href="{{ route('user.upgrade') }}" class="bento-tile accent b-2 justify-between p-5 group" style="--tile-accent: linear-gradient(90deg, #5c83ff, #90acff); --tile-glow: rgba(61,107,255,0.16);">
                    <span class="tile-orb"></span>
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Plan</p>
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform" style="background: rgba(61,107,255,0.12); border: 1px solid rgba(61,107,255,0.2);">
                            <i class="fas fa-crown text-blue-400 text-xs"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <p class="text-xl font-bold gradient-text">{{ $user->plan->name ?? 'Free' }}</p>
                        @if ($planPrice)
                            <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">{{ $planPrice['formatted'] }}<span class="opacity-60">/mo</span></p>
                        @endif
                    </div>
                </a>

                {{-- Links --}}
                <div class="bento-tile accent b-2 justify-between p-5" style="--tile-accent: linear-gradient(90deg, #10b981, #34d399); --tile-glow: rgba(16,185,129,0.18);">
                    <span class="tile-orb"></span>
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Links</p>
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.2);">
                            <i class="fas fa-link text-emerald-400 text-xs"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold mt-2" style="color: var(--text-primary);">{{ $totalLinks }}</p>
                </div>

                {{-- Projects --}}
                <div class="bento-tile accent b-2 justify-between p-5" style="--tile-accent: linear-gradient(90deg, #22d3ee, #67e8f9); --tile-glow: rgba(34,211,238,0.18);">
                    <span class="tile-orb"></span>
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Projects</p>
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(34,211,238,0.12); border: 1px solid rgba(34,211,238,0.2);">
                            <i class="fas fa-folder text-cyan-400 text-xs"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold mt-2" style="color: var(--text-primary);">{{ $totalProjects }}</p>
                </div>
            </div>

            {{-- Content bento: wide+tall Recent Links + Quick Actions + Plan detail. --}}
            <div class="bento">
                {{-- Recent Links (wide, tall) --}}
                <div class="bento-tile b-4-tall">
                    <div class="flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid var(--border-subtle);">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.15);">
                                <i class="fas fa-clock text-blue-400 text-xs"></i>
                            </div>
                            <h2 class="text-sm font-bold" style="color: var(--text-primary);">Recent Links</h2>
                        </div>
                        <a href="{{ route('user.links.wizard') }}" class="text-[11px] text-blue-400 hover:text-blue-300 font-semibold transition-all flex items-center gap-1 hover:gap-2" title="Guided Link in Bio wizard (or use Quick Link for short URLs)">
                            <i class="fas fa-magic text-[9px]"></i> New Link in Bio
                        </a>
                    </div>

                    @php
                        $personaBannerDismissed = !empty($user->settings['persona_banner_dismissed_at'] ?? null);
                        $showPersonaBanner = $user->onboarded_at && empty($user->persona) && !$personaBannerDismissed;
                    @endphp
                    @if($user->onboarded_at)
                    <div class="mx-4 mt-4 flex items-center justify-between gap-3 px-3 py-2 rounded-xl border" style="background: var(--bg-glass-input); border-color: var(--border-subtle);">
                        <span class="text-[11px] truncate" style="color: var(--text-faint);"><i class="fas fa-compass text-[10px] mr-1.5 text-blue-400/70"></i>Re-run onboarding</span>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <a href="{{ route('user.onboarding.persona') }}" class="text-[11px] px-2.5 py-1 rounded-lg border font-semibold transition" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-secondary);">
                                <i class="fas fa-user-tag text-[9px] mr-1"></i>Choose persona
                            </a>
                            <a href="{{ route('user.onboarding.template') }}" class="text-[11px] px-2.5 py-1 rounded-lg border font-semibold transition" style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-secondary);">
                                <i class="fas fa-layer-group text-[9px] mr-1"></i>Switch Template
                            </a>
                        </div>
                    </div>
                    @endif
                    @if($showWhatsappPrompt ?? false)
                    @php $waPending = session('whatsapp_connect_pending'); @endphp
                    <div class="m-4 mb-0 rounded-2xl border border-emerald-500/20 bg-gradient-to-r from-emerald-600/10 to-green-500/5 p-4"
                         x-data="{ open: {{ ($waPending || session('status') || $errors->any()) ? 'true' : 'false' }}, phase: '{{ $waPending ? 'code' : 'number' }}' }">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center flex-shrink-0">
                                <i class="fab fa-whatsapp text-emerald-300 text-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold" style="color: var(--text-primary);">Add your WhatsApp number</p>
                                <p class="text-xs mt-0.5" style="color: var(--text-muted);">Verify a WhatsApp number to sign in faster with a one-time code — no password needed — and follow our channel for updates.</p>
                            </div>
                            <button type="button" @click="open = !open"
                                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition flex-shrink-0">
                                <span x-show="!open">Add WhatsApp</span>
                                <span x-show="open" x-cloak>Close</span>
                            </button>
                            <form method="POST" action="{{ route('user.onboarding.dismiss-whatsapp-prompt') }}">
                                @csrf
                                <button type="submit" class="text-white/30 hover:text-white/70 px-2 py-1.5" title="Dismiss for now"><i class="fas fa-times text-xs"></i></button>
                            </form>
                        </div>

                        {{-- Inline add / verify --}}
                        <div x-show="open" x-cloak class="mt-4 pt-4 border-t border-white/10 space-y-3">
                            @if(session('status'))
                                <div class="px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 text-xs">{{ session('status') }}</div>
                            @endif
                            @if(session('otp_demo_reveal'))
                                <div class="px-3 py-2 rounded-lg bg-amber-500/10 border border-amber-400/30 text-amber-200 text-xs"><i class="fas fa-flask text-[10px] mr-1"></i> {{ session('otp_demo_reveal') }}</div>
                            @endif
                            @if(session('error'))
                                <div class="px-3 py-2 rounded-lg bg-red-500/10 border border-red-400/30 text-red-200 text-xs">{{ session('error') }}</div>
                            @endif
                            @if($errors->any())
                                <div class="px-3 py-2 rounded-lg bg-red-500/10 border border-red-400/30 text-red-200 text-xs">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
                            @endif

                            {{-- Phase 1: number --}}
                            <form method="POST" action="{{ route('user.onboarding.whatsapp.send') }}" x-show="phase === 'number'" class="flex flex-col sm:flex-row gap-2">
                                @csrf
                                <input type="tel" name="mobile" value="{{ old('mobile', $waPending) }}" required placeholder="+1 555 123 4567" autocomplete="tel"
                                       class="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm focus:border-emerald-400/50 focus:outline-none" style="color: var(--text-primary);">
                                <button type="submit" class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition whitespace-nowrap">Send code</button>
                            </form>

                            {{-- Phase 2: code --}}
                            <form method="POST" action="{{ route('user.onboarding.whatsapp.verify') }}" x-show="phase === 'code'" x-cloak class="flex flex-col sm:flex-row gap-2">
                                @csrf
                                <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required placeholder="123456" autocomplete="one-time-code"
                                       class="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm tracking-[0.3em] text-center font-mono focus:border-emerald-400/50 focus:outline-none" style="color: var(--text-primary);">
                                <button type="submit" class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition whitespace-nowrap">Verify &amp; connect</button>
                            </form>

                            @if(($whatsappChannelUrl ?? '') !== '')
                                <a href="{{ $whatsappChannelUrl }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-emerald-400/30 text-emerald-200 text-xs font-semibold transition">
                                    <i class="fab fa-whatsapp"></i> Follow our channel
                                </a>
                            @endif
                        </div>
                    </div>
                    @endif
                    @if($showPersonaBanner)
                    <div class="m-4 mb-0 rounded-2xl border border-blue-500/20 bg-gradient-to-r from-blue-600/10 to-cyan-500/5 p-4 flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-500/15 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-sparkles text-blue-300"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold" style="color: var(--text-primary);">Want personalised template suggestions?</p>
                            <p class="text-xs mt-0.5" style="color: var(--text-muted);">Tell us what you do in 10 seconds and we'll recommend the templates that fit.</p>
                        </div>
                        <a href="{{ route('user.onboarding.persona') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition flex-shrink-0">Personalise</a>
                        <form method="POST" action="{{ route('user.onboarding.dismiss-banner') }}">
                            @csrf
                            <button type="submit" class="text-white/30 hover:text-white/70 px-2 py-1.5" title="Dismiss"><i class="fas fa-times text-xs"></i></button>
                        </form>
                    </div>
                    @endif
                    @if($recentLinks->isEmpty())
                    <div class="p-12 text-center flex-1 flex flex-col items-center justify-center">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4 animate-pulse-glow" style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.12);">
                            <i class="fas fa-link text-xl text-blue-400"></i>
                        </div>
                        <p class="text-sm mb-1 font-bold" style="color: var(--text-muted);">No links yet</p>
                        <p class="text-xs mb-5" style="color: var(--text-dimmed);">Launch the guided wizard or build a quick short link.</p>
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('user.links.wizard') }}" class="btn-primary text-xs py-2.5">
                                <i class="fas fa-magic text-[10px]"></i> Link in Bio Wizard
                            </a>
                            <a href="{{ route('user.links.create') }}" class="btn-secondary text-xs py-2.5">
                                <i class="fas fa-plus text-[10px]"></i> Quick Link
                            </a>
                        </div>
                    </div>
                    @else
                    <div>
                        @foreach($recentLinks as $link)
                        <a href="{{ route('user.links.show', $link) }}" class="block px-5 py-3.5 dash-row group" style="border-bottom: 1px solid var(--border-subtle);">
                            <div class="flex items-center justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <span class="text-sm font-semibold truncate group-hover:text-blue-400 transition-colors" style="color: var(--text-primary);">{{ $link->title ?: $link->alias }}</span>
                                        <span class="badge" style="background: rgba(61,107,255,0.08); color: var(--accent-light); border: 1px solid rgba(61,107,255,0.12);">{{ $link->type }}</span>
                                        @if(!$link->is_active)
                                        <span class="badge" style="background: rgba(239,68,68,0.08); color: #f87171; border: 1px solid rgba(239,68,68,0.12);">off</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-blue-400/60 truncate">{{ $link->getShortUrl() }}</div>
                                </div>
                                <div class="text-right ml-4 flex-shrink-0">
                                    <div class="text-base font-bold" style="color: var(--text-primary);">{{ number_format($link->total_clicks) }}</div>
                                    <div class="text-[10px]" style="color: var(--text-faint);">clicks</div>
                                </div>
                            </div>
                        </a>
                        @endforeach
                    </div>
                    <div class="px-5 py-3 text-center mt-auto">
                        <a href="{{ route('user.links.index') }}" class="text-xs text-blue-400 hover:text-blue-300 font-semibold transition-all inline-flex items-center gap-1 hover:gap-2">
                            View all links <i class="fas fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                    @endif
                </div>

                {{-- Quick Actions --}}
                <div class="bento-tile b-2 p-5">
                    <div class="flex items-center gap-2.5 mb-4">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.15);">
                            <i class="fas fa-bolt text-blue-400 text-xs"></i>
                        </div>
                        <h2 class="text-sm font-bold" style="color: var(--text-primary);">Quick Actions</h2>
                    </div>
                    <div class="space-y-1">
                        <a href="{{ route('user.links.wizard') }}" class="flex items-center gap-3 p-2.5 rounded-xl transition-all group hover:translate-x-1" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center glow-icon transition-all duration-300" style="background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.12);">
                                <i class="fas fa-magic text-blue-400 text-[10px]"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-muted);">Link in Bio Wizard</span>
                            <i class="fas fa-chevron-right text-[8px] ml-auto transition-transform group-hover:translate-x-1" style="color: var(--text-faint);"></i>
                        </a>
                        <a href="{{ route('user.links.create') }}" class="flex items-center gap-3 p-2.5 rounded-xl transition-all group hover:translate-x-1" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center glow-icon transition-all duration-300" style="background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.12);">
                                <i class="fas fa-link text-blue-400 text-[10px]"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-muted);">Shorten a URL</span>
                            <i class="fas fa-chevron-right text-[8px] ml-auto transition-transform group-hover:translate-x-1" style="color: var(--text-faint);"></i>
                        </a>
                        <a href="{{ route('user.projects.create') }}" class="flex items-center gap-3 p-2.5 rounded-xl transition-all group hover:translate-x-1" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center glow-icon transition-all duration-300" style="background: rgba(34,211,238,0.1); border: 1px solid rgba(34,211,238,0.12);">
                                <i class="fas fa-folder-plus text-cyan-400 text-[10px]"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-muted);">Create Project</span>
                            <i class="fas fa-chevron-right text-[8px] ml-auto transition-transform group-hover:translate-x-1" style="color: var(--text-faint);"></i>
                        </a>
                        <a href="{{ route('user.pixels.create') }}" class="flex items-center gap-3 p-2.5 rounded-xl transition-all group hover:translate-x-1" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center glow-icon transition-all duration-300" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.12);">
                                <i class="fas fa-bullseye text-emerald-400 text-[10px]"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-muted);">Add Tracker</span>
                            <i class="fas fa-chevron-right text-[8px] ml-auto transition-transform group-hover:translate-x-1" style="color: var(--text-faint);"></i>
                        </a>
                        <a href="{{ route('user.qrcode') }}" class="flex items-center gap-3 p-2.5 rounded-xl transition-all group hover:translate-x-1" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center glow-icon transition-all duration-300" style="background: rgba(6,182,212,0.1); border: 1px solid rgba(6,182,212,0.12);">
                                <i class="fas fa-qrcode text-cyan-400 text-[10px]"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-muted);">Generate QR Code</span>
                            <i class="fas fa-chevron-right text-[8px] ml-auto transition-transform group-hover:translate-x-1" style="color: var(--text-faint);"></i>
                        </a>
                    </div>
                </div>

                {{-- Your Plan detail --}}
                <div class="bento-tile b-2 p-5">
                    <div class="flex items-center gap-2.5 mb-4">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.15);">
                            <i class="fas fa-gem text-blue-400 text-xs"></i>
                        </div>
                        <h2 class="text-sm font-bold" style="color: var(--text-primary);">Your Plan</h2>
                    </div>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-500 via-blue-500 to-blue-700 flex items-center justify-center shadow-lg" style="box-shadow: 0 4px 16px rgba(61,107,255,0.3);">
                            <i class="fas fa-gem text-white text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold gradient-text">{{ $user->plan->name ?? 'Free' }}</p>
                            <p class="text-[10px]" style="color: var(--text-dimmed);">{{ $user->plan_expires_at ? 'Expires ' . $user->plan_expires_at->format('M d, Y') : 'No expiration' }}</p>
                        </div>
                    </div>
                    <div class="mb-3" style="height: 1px; background: var(--border-subtle);"></div>
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] font-medium" style="color: var(--text-dimmed);">{{ $totalLinks }} / {{ $user->plan->settings['links_limit'] ?? '∞' }} links</p>
                        <div class="w-24 h-1.5 rounded-full overflow-hidden" style="background: var(--bg-glass-input);">
                            @php
                                $limit = $user->plan->settings['links_limit'] ?? 100;
                                $pct = min(100, ($totalLinks / max(1, $limit)) * 100);
                            @endphp
                            <div class="h-full rounded-full bg-gradient-to-r from-blue-500 via-blue-500 to-blue-400 transition-all duration-1000" style="width: {{ $pct }}%; box-shadow: 0 0 8px rgba(61,107,255,0.4);"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- /OVERVIEW TAB --}}

        {{-- ===================== TRAFFIC TAB ===================== --}}
        <div x-show="tab === 'traffic'" x-cloak role="tabpanel">
        {{-- ===================== WORKSPACE-WIDE CHANNEL BREAKDOWN =====================
             Rolls every link's click log up into a single "what share of my traffic
             comes from in-app webviews vs real browsers vs bots" view. The pills
             below double as a workspace-wide channel filter (?channel=…) that
             narrows the click-derived tiles above. --}}
        <div class="bento">
            <div class="bento-tile b-6">
                <div class="flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid var(--border-subtle);">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(34,211,238,0.1); border: 1px solid rgba(34,211,238,0.18);">
                            <i class="fas fa-window-restore text-cyan-400 text-xs"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold" style="color: var(--text-primary);">Channel breakdown</h2>
                            <p class="text-[11px]" style="color: var(--text-faint);">Across every link in your workspace &middot; detected from user-agent</p>
                        </div>
                    </div>
                    <span class="text-[11px]" style="color: var(--text-faint);">Total: <strong style="color: var(--text-primary);">{{ number_format($channelTotal) }}</strong></span>
                </div>
                <div class="p-5">
                    @if($channelStats->isEmpty() || $channelTotal === 0)
                        <p class="text-sm text-center py-6" style="color: var(--text-faint);">
                            No data yet. Channel detection began once user-agents started being recorded; older clicks show as Unknown.
                        </p>
                    @else
                        <div class="space-y-2 mb-4">
                            @foreach($channelStats as $row)
                                @php
                                    $key   = $row->channel ?: 'unknown';
                                    $label = $channelLabelMap[$key] ?? ucfirst(str_replace('_', ' ', $key));
                                    $pct   = $channelTotal > 0 ? round(($row->count / $channelTotal) * 100, 1) : 0;
                                @endphp
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-1" style="color: var(--text-faint);">
                                        <span class="inline-flex items-center gap-2">
                                            <i class="fas {{ $channelIcon($key) }} text-[12px] opacity-80"></i>
                                            <span style="color: var(--text-muted);">{{ $label }}</span>
                                        </span>
                                        <span><strong style="color: var(--text-primary);">{{ number_format($row->count) }}</strong> &middot; {{ $pct }}%</span>
                                    </div>
                                    <div class="w-full h-1.5 rounded-full overflow-hidden" style="background: var(--bg-glass-input);">
                                        <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-blue-500" style="width: {{ $pct }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap items-center gap-2 pt-2" style="border-top: 1px solid var(--border-subtle);">
                            <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);">Filter:</span>
                            <a href="{{ $channelBuildUrl(null) }}" class="badge {{ empty($channelFilter) ? 'badge-active' : '' }}"
                               style="{{ empty($channelFilter) ? 'background: rgba(34,211,238,0.18); color: #67e8f9; border: 1px solid rgba(34,211,238,0.35);' : 'background: rgba(255,255,255,0.04); color: var(--text-muted); border: 1px solid var(--border-subtle);' }}">All</a>
                            @foreach($channelStats as $row)
                                @php $key = $row->channel ?: 'unknown'; @endphp
                                @continue($key === \App\Modules\Common\Services\ChannelClassifier::KEY_UNKNOWN)
                                @php $isActive = ($channelFilter ?? '') === $key; @endphp
                                <a href="{{ $channelBuildUrl($key) }}"
                                   class="badge inline-flex items-center gap-1.5"
                                   style="{{ $isActive ? 'background: rgba(34,211,238,0.18); color: #67e8f9; border: 1px solid rgba(34,211,238,0.35);' : 'background: rgba(255,255,255,0.04); color: var(--text-muted); border: 1px solid var(--border-subtle);' }}">
                                    <i class="fas {{ $channelIcon($key) }} text-[9px] opacity-80"></i>
                                    {{ $channelLabelMap[$key] ?? $key }}
                                    <span class="opacity-60">({{ number_format($row->count) }})</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        </div>
        {{-- /TRAFFIC TAB --}}

        {{-- ===================== GROWTH TAB ===================== --}}
        <div x-show="tab === 'growth'" x-cloak role="tabpanel">
        <div class="bento">
            {{-- Backlink radar at-a-glance: how many new pages around the web have
                 linked back to one of this creator's properties in the last 7 days.
                 Click-through opens the full Backlinks dashboard page. --}}
            <a href="{{ route('user.backlinks.index', ['days' => 7]) }}" class="bento-tile accent {{ \App\Services\AI\AiEngineSettings::isEnabled() ? 'b-3' : 'b-6' }} p-5 hover:border-cyan-500/40" style="--tile-accent: linear-gradient(90deg, #22d3ee, #67e8f9); --tile-glow: rgba(34,211,238,0.18);">
                <span class="tile-orb"></span>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center"
                             style="background: rgba(34,211,238,0.12); border: 1px solid rgba(34,211,238,0.25);">
                            <i class="fas fa-bullseye text-cyan-300"></i>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Backlinks</p>
                            <p class="text-2xl font-bold" style="color: var(--text-primary);">
                                {{ number_format($backlinksThisWeek) }}
                                <span class="text-sm font-medium" style="color: var(--text-faint);">this week</span>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs" style="color: var(--text-faint);">Pages linking back to your properties</p>
                        <p class="text-xs text-cyan-300 mt-1">View all <i class="fas fa-arrow-right ml-1"></i></p>
                    </div>
                </div>
            </a>

            {{-- Coin balance at-a-glance card (only visible when the AI engine is
                 on, since AI usage is now charged straight from the coin wallet). --}}
            @if(\App\Services\AI\AiEngineSettings::isEnabled())
                @php
                    $aiCoins = app(\App\Services\Billing\WalletService::class)->getBalance($user);
                @endphp
                <a href="{{ route('user.wallet.show') }}" class="bento-tile accent b-3 p-5 hover:border-blue-500/40" style="--tile-accent: linear-gradient(90deg, #5c83ff, #90acff); --tile-glow: rgba(61,107,255,0.18);">
                    <span class="tile-orb"></span>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center"
                                 style="background: rgba(34,211,238,0.12); border: 1px solid rgba(34,211,238,0.25);">
                                <i class="fas fa-brain text-blue-300"></i>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Coin balance</p>
                                <p class="text-2xl font-bold" style="color: var(--text-primary);">
                                    {{ number_format($aiCoins) }} <span class="text-blue-300">coins</span>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs" style="color: var(--text-faint);">
                                AI usage is paid from your wallet
                            </p>
                            <p class="text-xs text-blue-300 mt-1">Manage &amp; top up <i class="fas fa-arrow-right ml-1"></i></p>
                        </div>
                    </div>
                </a>
            @endif
        </div>
        </div>
        {{-- /GROWTH TAB --}}

    </div>
</div>
@endsection
