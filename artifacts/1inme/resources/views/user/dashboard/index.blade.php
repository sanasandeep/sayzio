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

    /* Task #3848 — 7-day trend sparkline accent under a stats tile's big
       number. Purely decorative (no axes/legend/tooltip), so it's kept
       short and full-width instead of fighting the tile's own layout. */
    .dash-sparkline {
        display: block;
        width: 100%;
        margin-top: 8px;
        max-height: 32px;
    }
</style>
{{-- The bento tile system (grid, glass tiles, live-pulse hero) lives in a
     shared partial so other user surfaces reuse the exact same look. --}}
@include('user.partials.bento-styles')
@endpush

@section('content')
@php
    // Task #3848 — greet in the user's resolved timezone (personal
    // preference, else the platform default Asia/Kolkata) instead of the
    // server/app clock, so "Good afternoon" doesn't show up at 8:41 PM IST.
    $__nowLocal = now(\App\Support\PlatformTimezone::forUser($user));
    $hour = $__nowLocal->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $heroIcon = $hour < 12 ? 'fa-sun' : ($hour < 17 ? 'fa-cloud-sun' : 'fa-moon');
    $heroDay = $__nowLocal->format('l');

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
                <p class="hero-subtitle">Here's your command center, a live look at how your links are performing.</p>
                <div class="flex items-center gap-2 flex-wrap mt-4">
                    <a href="{{ route('user.links.wizard') }}" class="btn-primary text-xs py-2">
                        <i class="fas fa-magic text-[10px]"></i> Create Link in Bio
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

    {{-- ===================== FOLDERS DESK =====================
         The old /user/projects page folded into the dashboard: a desktop-style
         "desk" surface where each folder is a 3D icon that flips open on
         hover, exactly like files on a real computer. Click opens the links
         inside; the dashed folder creates a new one inline (AJAX). --}}
    <style>
        .folders-desk {
            position: relative;
            border-radius: 1.5rem;
            border: 1px solid var(--border-subtle);
            background:
                radial-gradient(circle at 1px 1px, rgba(148,163,184,0.14) 1px, transparent 0) 0 0 / 22px 22px,
                linear-gradient(180deg, rgba(61,107,255,0.05), rgba(2,6,23,0.0) 55%);
            overflow: hidden;
        }
        html.light-mode .folders-desk {
            background:
                radial-gradient(circle at 1px 1px, rgba(100,116,139,0.16) 1px, transparent 0) 0 0 / 22px 22px,
                linear-gradient(180deg, rgba(61,107,255,0.06), rgba(255,255,255,0) 55%);
        }
        .desk-item { width: 118px; }
        .desk-icon-link { display: block; text-align: center; border-radius: 1rem; padding: 10px 6px 8px; transition: background .15s; }
        .desk-icon-link:hover { background: rgba(61,107,255,0.08); }
        html.light-mode .desk-icon-link:hover { background: rgba(61,107,255,0.10); }
        /* 3D folder */
        .fld { position: relative; width: 74px; height: 56px; margin: 0 auto; perspective: 320px; }
        .fld-back, .fld-front { position: absolute; inset: 0; border-radius: 7px; }
        .fld-back { background: color-mix(in srgb, var(--fc) 72%, #0b1220); }
        .fld-back::before {
            content: ''; position: absolute; top: -7px; left: 0; width: 34%; height: 12px;
            border-radius: 6px 8px 0 0; background: inherit;
        }
        .fld-paper {
            position: absolute; left: 8px; right: 8px; top: 3px; bottom: 6px; border-radius: 4px;
            background: linear-gradient(180deg, #fff, #dbe3ef); box-shadow: 0 1px 3px rgba(2,6,23,0.25);
            transition: transform .28s cubic-bezier(.34,1.4,.5,1);
        }
        .fld-front {
            top: 9px; transform-origin: bottom center; transform-style: preserve-3d;
            background: linear-gradient(180deg, color-mix(in srgb, var(--fc) 92%, #fff), color-mix(in srgb, var(--fc) 82%, #0b1220));
            box-shadow: 0 6px 14px -6px color-mix(in srgb, var(--fc) 55%, transparent);
            transition: transform .28s cubic-bezier(.34,1.4,.5,1);
            display: flex; align-items: flex-end; justify-content: flex-end; padding: 4px 6px;
        }
        .desk-icon-link:hover .fld-front, .desk-icon-link:focus-visible .fld-front { transform: rotateX(-30deg); }
        .desk-icon-link:hover .fld-paper, .desk-icon-link:focus-visible .fld-paper { transform: translateY(-7px); }
        .fld-count {
            font-size: 10px; font-weight: 800; line-height: 1; color: #fff;
            background: rgba(2,6,23,0.35); border-radius: 999px; padding: 3px 6px;
        }
        .desk-label {
            margin-top: 8px; font-size: 12.5px; font-weight: 600; color: var(--text-primary);
            max-width: 106px; margin-left: auto; margin-right: auto;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .desk-sub { font-size: 10px; color: var(--text-faint); margin-top: 2px; }
        .fld-new {
            border: 2px dashed var(--border-glass); border-radius: 7px; position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center; color: var(--text-faint);
            transition: all .15s;
        }
        .desk-icon-btn:hover .fld-new { border-color: rgba(61,107,255,0.55); color: #3d6bff; transform: translateY(-3px); }
        @media (prefers-reduced-motion: reduce) {
            .fld-front, .fld-paper, .fld-new { transition: none !important; }
            .desk-icon-link:hover .fld-front { transform: none; }
            .desk-icon-link:hover .fld-paper { transform: none; }
        }
    </style>
    <div id="folders" class="folders-desk mb-6 p-5 sm:p-6"
         x-data="{
            creating: false,
            name: '',
            saving: false,
            error: '',
            async createFolder() {
                const n = this.name.trim();
                if (!n || this.saving) return;
                this.saving = true;
                this.error = '';
                try {
                    const res = await fetch('{{ route('user.projects.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                        },
                        body: JSON.stringify({ name: n })
                    });
                    const data = await res.json().catch(() => null);
                    if (res.ok && data && data.success) { window.location.reload(); return; }
                    this.error = (data && data.error) ? data.error : 'Couldn\'t create the folder. Please try again.';
                } catch (e) {
                    this.error = 'Couldn\'t create the folder. Please try again.';
                }
                this.saving = false;
            }
         }">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.2);">
                    <i class="fas fa-folder text-amber-400 text-xs"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold" style="color: var(--text-primary);">Folders</h2>
                    <p class="text-[11px]" style="color: var(--text-faint);">Your desk — click a folder to open the links inside</p>
                </div>
            </div>
            <a href="{{ route('user.links.index') }}" class="text-[11px] text-blue-400 hover:text-blue-300 font-semibold inline-flex items-center gap-1">
                All links <i class="fas fa-arrow-right text-[9px]"></i>
            </a>
        </div>

        <div class="flex flex-wrap items-start gap-1 sm:gap-2">
            @foreach($deskFolders as $folder)
                @php $fc = $folder->color ?: '#3b82f6'; @endphp
                <div class="desk-item group relative">
                    <a href="{{ route('user.links.index', ['project_id' => $folder->id]) }}"
                       class="desk-icon-link" title="Open {{ $folder->name }}">
                        <div class="fld" style="--fc: {{ $fc }};">
                            <span class="fld-back"></span>
                            <span class="fld-paper"></span>
                            <span class="fld-front"><span class="fld-count">{{ number_format($folder->links_count) }}</span></span>
                        </div>
                        <div class="desk-label" title="{{ $folder->name }}">{{ $folder->name }}</div>
                        <div class="desk-sub">{{ number_format($folder->active_links_count) }} active · {{ $folder->updated_at->diffForHumans(short: true) }}</div>
                    </a>
                    <div class="absolute top-1 right-1 flex items-center gap-0.5 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
                        <a href="{{ route('user.projects.edit', $folder) }}" class="p-1.5 rounded-lg" style="color: var(--text-faint); background: var(--bg-glass-input);" title="Rename / recolor"><i class="fas fa-pen text-[9px]"></i></a>
                        <form action="{{ route('user.projects.destroy', $folder) }}" method="POST" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this folder?', message: 'Links inside will be kept but become unfiled.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                            @csrf @method('DELETE')
                            <button class="p-1.5 rounded-lg hover:text-red-400" style="color: var(--text-faint); background: var(--bg-glass-input);" title="Delete folder"><i class="fas fa-trash text-[9px]"></i></button>
                        </form>
                    </div>
                </div>
            @endforeach

            {{-- New Folder — dashed desk icon with inline naming --}}
            <div class="desk-item">
                <button type="button" x-show="!creating" @click="creating = true; $nextTick(() => $refs.newFolderName.focus())"
                        class="desk-icon-link desk-icon-btn w-full" title="New folder">
                    <div class="fld"><span class="fld-new"><i class="fas fa-plus"></i></span></div>
                    <div class="desk-label" style="color: var(--text-faint);">New Folder</div>
                    <div class="desk-sub">&nbsp;</div>
                </button>
                <div x-show="creating" x-cloak class="pt-2 px-1">
                    <input type="text" x-ref="newFolderName" x-model="name" maxlength="60" placeholder="Folder name"
                           @keydown.enter.prevent="createFolder()" @keydown.escape="creating = false; name = ''"
                           class="w-full text-xs px-2.5 py-2 rounded-lg border focus:outline-none"
                           style="background: var(--bg-glass-input); border-color: var(--border-glass); color: var(--text-primary);">
                    <div class="flex items-center gap-1 mt-1.5">
                        <button type="button" @click="createFolder()" :disabled="saving"
                                class="flex-1 text-[11px] font-bold text-white rounded-lg py-1.5"
                                style="background: linear-gradient(135deg,#3d6bff,#90acff);">
                            <span x-show="!saving">Create</span><span x-show="saving" x-cloak>Saving…</span>
                        </button>
                        <button type="button" @click="creating = false; name = ''; error = ''" class="text-[11px] px-2 py-1.5 rounded-lg" style="color: var(--text-faint);">Cancel</button>
                    </div>
                    <p x-show="error" x-cloak x-text="error" class="text-[10px] text-red-400 mt-1.5 leading-snug"></p>
                </div>
            </div>
        </div>

        @if($deskFolders->isEmpty())
            <p class="text-xs mt-1" style="color: var(--text-faint);">No folders yet — create one and drag your links in from the All Links page, just like on your computer.</p>
        @endif
    </div>

    <div x-data="{
            tab: '{{ !empty($channelFilter) ? 'traffic' : 'overview' }}',
            channelForced: {{ !empty($channelFilter) ? 'true' : 'false' }},
            tabs: ['overview', 'traffic', 'growth'],
            init() {
                /* A ?channel= filter always wins and pins the Traffic tab. */
                if (!this.channelForced) {
                    try {
                        const saved = localStorage.getItem('1inme_dashboard_tab');
                        if (saved && this.tabs.includes(saved)) this.tab = saved;
                    } catch (e) {}
                }
                /* Remember the user's last-selected tab for next time. */
                this.$watch('tab', (val) => {
                    try { localStorage.setItem('1inme_dashboard_tab', val); } catch (e) {}
                });
            }
        }">

        {{-- Dashboard view tabs — split the long scroll into calmer, focused views. --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="dash-tabs" role="tablist" aria-label="Dashboard views">
                <button type="button" role="tab"
                        @click="tab = 'overview'" :aria-selected="tab === 'overview' ? 'true' : 'false'"
                        class="dash-tab" :class="tab === 'overview' ? 'dash-tab-active' : ''">
                    <i class="fas fa-gauge-high text-[11px]"></i> Overview
                </button>
                @if($dashboardTabs['traffic'] ?? true)
                <button type="button" role="tab"
                        @click="tab = 'traffic'" :aria-selected="tab === 'traffic' ? 'true' : 'false'"
                        class="dash-tab" :class="tab === 'traffic' ? 'dash-tab-active' : ''">
                    <i class="fas fa-chart-pie text-[11px]"></i> Traffic
                </button>
                @endif
                @if($dashboardTabs['growth'] ?? true)
                <button type="button" role="tab"
                        @click="tab = 'growth'" :aria-selected="tab === 'growth' ? 'true' : 'false'"
                        class="dash-tab" :class="tab === 'growth' ? 'dash-tab-active' : ''">
                    <i class="fas fa-arrow-trend-up text-[11px]"></i> Growth
                </button>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="$dispatch('open-dashboard-customize', { step: 'picker' })" class="btn-ghost text-xs py-2">
                    <i class="fas fa-wand-magic-sparkles text-[10px]"></i> AI Dashboard Settings
                </button>
            </div>
        </div>

        {{-- ===================== OVERVIEW TAB ===================== --}}
        <div x-show="tab === 'overview'" x-cloak role="tabpanel">

            @if($dashboardTrimmedTabs['overview'] ?? false)
            @include('user.dashboard.partials.trimmed-hint')
            @endif

            {{-- Metric bento: one tall feature tile (Total Clicks) + four regular tiles. --}}
            <div class="bento mb-5">
                {{-- Total Clicks — tall feature tile --}}
                @if(in_array('stat_total_clicks', $dashboardWidgets))
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
                    <canvas data-sparkline='@json($clicksSparkline)' data-sparkline-color="#3b82f6" class="dash-sparkline" height="28"></canvas>
                </div>
                @endif

                {{-- Today --}}
                @if(in_array('stat_today', $dashboardWidgets))
                <div class="bento-tile accent b-2 justify-between p-5" style="--tile-accent: linear-gradient(90deg, #f59e0b, #fbbf24); --tile-glow: rgba(245,158,11,0.18);">
                    <span class="tile-orb"></span>
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Today</p>
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.2);">
                            <i class="fas fa-chart-line text-amber-400 text-xs"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold mt-2" style="color: var(--text-primary);">{{ number_format($clicksToday) }}</p>
                    <canvas data-sparkline='@json($clicksSparkline)' data-sparkline-color="#f59e0b" class="dash-sparkline" height="24"></canvas>
                </div>
                @endif

                {{-- Plan --}}
                @if(in_array('stat_plan', $dashboardWidgets))
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
                @endif

                {{-- Links --}}
                @if(in_array('stat_links', $dashboardWidgets))
                <div class="bento-tile accent b-2 justify-between p-5" style="--tile-accent: linear-gradient(90deg, #10b981, #34d399); --tile-glow: rgba(16,185,129,0.18);">
                    <span class="tile-orb"></span>
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Links</p>
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.2);">
                            <i class="fas fa-link text-emerald-400 text-xs"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold mt-2" style="color: var(--text-primary);">{{ $totalLinks }}</p>
                    <canvas data-sparkline='@json($linksSparkline)' data-sparkline-color="#10b981" class="dash-sparkline" height="24"></canvas>
                </div>
                @endif

                {{-- Projects --}}
                @if(in_array('stat_projects', $dashboardWidgets))
                <div class="bento-tile accent b-2 justify-between p-5" style="--tile-accent: linear-gradient(90deg, #22d3ee, #67e8f9); --tile-glow: rgba(34,211,238,0.18);">
                    <span class="tile-orb"></span>
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Folders</p>
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(34,211,238,0.12); border: 1px solid rgba(34,211,238,0.2);">
                            <i class="fas fa-folder text-cyan-400 text-xs"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold mt-2" style="color: var(--text-primary);">{{ $totalProjects }}</p>
                    <canvas data-sparkline='@json($projectsSparkline)' data-sparkline-color="#22d3ee" class="dash-sparkline" height="24"></canvas>
                </div>
                @endif
            </div>

            {{-- Content bento: wide+tall Recent Links + Quick Actions + Plan detail. --}}
            <div class="bento">
                {{-- Recent Links (wide, tall) --}}
                @if(in_array('recent_links', $dashboardWidgets))
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
                                <p class="text-xs mt-0.5" style="color: var(--text-muted);">Verify a WhatsApp number to sign in faster with a one-time code (no password needed) and follow our channel for updates.</p>
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
                @endif

                {{-- Delivery Projects --}}
                @if(in_array('delivery_projects', $dashboardWidgets))
                <div class="bento-tile b-4-tall">
                    <div class="flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid var(--border-subtle);">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.15);">
                                <i class="fas fa-diagram-project text-blue-400 text-xs"></i>
                            </div>
                            <h2 class="text-sm font-bold" style="color: var(--text-primary);">Delivery Projects</h2>
                        </div>
                        <a href="{{ route('user.delivery-projects.index') }}" class="text-[11px] text-blue-400 hover:text-blue-300 font-semibold transition-all flex items-center gap-1 hover:gap-2">
                            View all <i class="fas fa-arrow-right text-[9px]"></i>
                        </a>
                    </div>
                    @if($deliveryProjects->isEmpty())
                        <div class="px-5 py-8 text-center">
                            <p class="text-xs" style="color: var(--text-faint);">No active projects yet. Turn a finalized sale into a shared project.</p>
                            <a href="{{ route('user.delivery-projects.create') }}" class="inline-block mt-3 text-[11px] px-3 py-1.5 rounded-lg font-semibold text-white" style="background: linear-gradient(135deg,#3d6bff,#90acff);">New project</a>
                        </div>
                    @else
                        <div class="p-4 space-y-3">
                            @foreach($deliveryProjects as $dp)
                                @php $dpPct = $dp->tasks_count > 0 ? (int) round(($dp->done_tasks_count / $dp->tasks_count) * 100) : 0; @endphp
                                <a href="{{ route('user.delivery-projects.show', $dp) }}" class="block rounded-xl p-3 border transition hover:shadow" style="background: var(--bg-glass-input); border-color: var(--border-subtle);">
                                    <div class="flex items-center justify-between gap-2 mb-1.5">
                                        <span class="text-sm font-semibold truncate" style="color: var(--text-primary);">{{ $dp->title }}</span>
                                        <span class="text-xs font-bold" style="color:#3d6bff;">{{ $dpPct }}%</span>
                                    </div>
                                    <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--border-subtle);">
                                        <div class="h-full rounded-full" style="width: {{ $dpPct }}%; background: linear-gradient(90deg,#3d6bff,#90acff);"></div>
                                    </div>
                                    <div class="text-[10px] mt-1" style="color: var(--text-faint);">{{ $dp->done_tasks_count }}/{{ $dp->tasks_count }} tasks @if($dp->warranty_expires_at) · warranty {{ $dp->warrantyExpired() ? 'expired' : 'to '.$dp->warranty_expires_at->format('M j') }}@endif</div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
                @endif

                {{-- Quick Actions --}}
                @if(in_array('quick_actions', $dashboardWidgets))
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
                            <span class="text-xs font-medium" style="color: var(--text-muted);">New Folder</span>
                            <i class="fas fa-chevron-right text-[8px] ml-auto transition-transform group-hover:translate-x-1" style="color: var(--text-faint);"></i>
                        </a>
                        <a href="{{ route('user.pixels.create') }}" class="flex items-center gap-3 p-2.5 rounded-xl transition-all group hover:translate-x-1" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center glow-icon transition-all duration-300" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.12);">
                                <i class="fas fa-bullseye text-emerald-400 text-[10px]"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-muted);">Add Tracker</span>
                            <i class="fas fa-chevron-right text-[8px] ml-auto transition-transform group-hover:translate-x-1" style="color: var(--text-faint);"></i>
                        </a>
                        <a href="{{ route('user.qr-codes.create') }}" class="flex items-center gap-3 p-2.5 rounded-xl transition-all group hover:translate-x-1" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center glow-icon transition-all duration-300" style="background: rgba(6,182,212,0.1); border: 1px solid rgba(6,182,212,0.12);">
                                <i class="fas fa-qrcode text-cyan-400 text-[10px]"></i>
                            </div>
                            <span class="text-xs font-medium" style="color: var(--text-muted);">Generate QR Code</span>
                            <i class="fas fa-chevron-right text-[8px] ml-auto transition-transform group-hover:translate-x-1" style="color: var(--text-faint);"></i>
                        </a>
                    </div>
                </div>
                @endif

                {{-- Your Plan detail --}}
                @if(in_array('plan_detail', $dashboardWidgets))
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
                @endif
            </div>
        </div>
        {{-- /OVERVIEW TAB --}}

        {{-- ===================== TRAFFIC TAB ===================== --}}
        @if($dashboardTabs['traffic'] ?? true)
        <div x-show="tab === 'traffic'" x-cloak role="tabpanel">
        @if($dashboardTrimmedTabs['traffic'] ?? false)
        @include('user.dashboard.partials.trimmed-hint')
        @endif
        {{-- ===================== WORKSPACE-WIDE CHANNEL BREAKDOWN =====================
             Rolls every link's click log up into a single "what share of my traffic
             comes from in-app webviews vs real browsers vs bots" view. The pills
             below double as a workspace-wide channel filter (?channel=…) that
             narrows the click-derived tiles above. --}}
        @if(in_array('traffic_channels', $dashboardWidgets))
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
        @endif
        </div>
        @endif
        {{-- /TRAFFIC TAB --}}

        {{-- ===================== GROWTH TAB ===================== --}}
        @if($dashboardTabs['growth'] ?? true)
        <div x-show="tab === 'growth'" x-cloak role="tabpanel">
        @if($dashboardTrimmedTabs['growth'] ?? false)
        @include('user.dashboard.partials.trimmed-hint')
        @endif
        <div class="bento">
            {{-- Backlink radar at-a-glance: how many new pages around the web have
                 linked back to one of this creator's properties in the last 7 days.
                 Click-through opens the full Backlinks dashboard page. --}}
            @if(in_array('backlinks', $dashboardWidgets))
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
            @endif

            {{-- Coin balance at-a-glance card (only visible when the AI engine is
                 on, since AI usage is now charged straight from the coin wallet). --}}
            @if(in_array('coin_balance', $dashboardWidgets) && \App\Services\AI\AiEngineSettings::isEnabled())
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
        @endif
        {{-- /GROWTH TAB --}}

    </div>
</div>

{{-- Rendered OUTSIDE `.bento-stage` on purpose: that wrapper's
     `.bento-stage > * { position: relative; z-index: 1; }` rule (shared by
     every bento-grid page) tied on CSS specificity with the modal's Tailwind
     `fixed`/`z-[999]` utilities and — because it's injected later in <head>
     via @stack('styles') — won the cascade, silently downgrading the overlay
     to `position: relative`. The "modal" still opened (Alpine state was
     correct) but rendered in-flow far down the page instead of as a
     fullscreen overlay, which is why the button looked unclickable. --}}
@include('user.dashboard.customize-modal')

{{-- Task #3848 — 7-day trend sparklines on the stats tiles, drawn with the
     app's already-vendored Chart.js (no scales/legend/tooltip/points so it
     reads as a small accent, not a full chart). --}}
<script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
<script>
(function () {
    function initDashSparklines() {
        if (!window.Chart) return;
        document.querySelectorAll('canvas[data-sparkline]').forEach(function (canvas) {
            if (canvas.dataset.sparklineInit) return;
            canvas.dataset.sparklineInit = '1';
            var data;
            try { data = JSON.parse(canvas.dataset.sparkline); } catch (e) { data = []; }
            var color = canvas.dataset.sparklineColor || '#5c83ff';
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.map(function (_, i) { return i; }),
                    datasets: [{
                        data: data,
                        borderColor: color,
                        backgroundColor: color + '26',
                        borderWidth: 1.5,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { intersect: false },
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: {
                        x: { display: false },
                        y: { display: false, beginAtZero: true },
                    },
                    elements: { line: { borderJoinStyle: 'round' } },
                },
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashSparklines);
    } else {
        initDashSparklines();
    }
})();
</script>
@endsection
