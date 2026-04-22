@extends('user.layouts.app')
@section('title', 'Dashboard')

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
@endphp
@include('user.partials.page-hero', [
    'title'    => $greeting . ', ' . $user->name,
    'subtitle' => "Here's an overview of your link performance.",
    'icon'     => $heroIcon,
    'chips'    => [
        ['icon' => 'fa-calendar-day', 'text' => $heroDay],
        ['icon' => 'fa-link', 'text' => ($stats['total_links'] ?? 0) . ' links'],
    ],
    'actions'  => [
        ['label' => 'Create Bio Link', 'url' => route('user.links.wizard'), 'icon' => 'fa-magic', 'class' => 'btn-primary'],
        ['label' => 'Quick Link',      'url' => route('user.links.create'), 'icon' => 'fa-plus',  'class' => 'btn-secondary'],
    ],
])

@if(!empty($channelFilter))
    <div class="mb-5 flex flex-wrap items-center gap-2 px-4 py-3 rounded-xl"
         style="background: rgba(168,85,247,0.08); border: 1px solid rgba(168,85,247,0.25);">
        <span class="text-[11px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Filtered by channel</span>
        <span class="badge inline-flex items-center gap-1.5"
              style="background: rgba(168,85,247,0.15); color: #f0abfc; border: 1px solid rgba(168,85,247,0.3);">
            <i class="fas {{ $channelIcon($channelFilter) }} text-[10px]"></i>
            {{ $channelLabelMap[$channelFilter] ?? $channelFilter }}
        </span>
        <span class="text-[11px]" style="color: var(--text-faint);">Click totals below reflect this bucket only.</span>
        <a href="{{ $channelBuildUrl(null) }}" class="ml-auto text-[11px] text-violet-400 hover:text-violet-300 font-semibold inline-flex items-center gap-1">
            <i class="fas fa-times text-[9px]"></i> Clear filter
        </a>
    </div>
@endif

<div class="grid grid-cols-2 md:grid-cols-5 gap-5 mb-8">
    {{-- Plan widget: name + price resolved via PricingResolver so the
         user sees their billing-country currency (₹ for IN, $ otherwise).
         Falls back to "Free" with no price line if the user has no plan. --}}
    @php
        $planPrice = $user->plan
            ? \App\Services\PricingResolver::priceFor($user->plan, $user, 'monthly')
            : null;
    @endphp
    <a href="{{ route('user.upgrade') }}" class="stat-card group shimmer block" style="--stat-accent: linear-gradient(90deg, #8b5cf6, #a78bfa); --stat-glow: rgba(124,58,237,0.12); --stat-border-color: rgba(124,58,237,0.2);">
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Plan</p>
                <p class="text-xl font-bold gradient-text">{{ $user->plan->name ?? 'Free' }}</p>
                @if ($planPrice)
                    <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">{{ $planPrice['formatted'] }}<span class="opacity-60">/mo</span></p>
                @endif
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center glow-icon group-hover:scale-110 transition-all duration-500" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.15);">
                <i class="fas fa-crown text-violet-400 text-sm"></i>
            </div>
        </div>
    </a>

    <div class="stat-card group shimmer" style="--stat-accent: linear-gradient(90deg, #10b981, #34d399); --stat-glow: rgba(16,185,129,0.12); --stat-border-color: rgba(16,185,129,0.2);">
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Links</p>
                <p class="text-xl font-bold" style="color: var(--text-primary);">{{ $totalLinks }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center glow-icon group-hover:scale-110 transition-all duration-500" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.15);">
                <i class="fas fa-link text-emerald-400 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="stat-card group shimmer" style="--stat-accent: linear-gradient(90deg, #3b82f6, #a78bfa); --stat-glow: rgba(59,130,246,0.12); --stat-border-color: rgba(59,130,246,0.2);">
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Total Clicks</p>
                <p class="text-xl font-bold" style="color: var(--text-primary);">{{ number_format($totalClicks) }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center glow-icon group-hover:scale-110 transition-all duration-500" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.15);">
                <i class="fas fa-mouse-pointer text-violet-400 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="stat-card group shimmer" style="--stat-accent: linear-gradient(90deg, #f59e0b, #fbbf24); --stat-glow: rgba(245,158,11,0.12); --stat-border-color: rgba(245,158,11,0.2);">
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Today</p>
                <p class="text-xl font-bold" style="color: var(--text-primary);">{{ number_format($clicksToday) }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center glow-icon group-hover:scale-110 transition-all duration-500" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.15);">
                <i class="fas fa-chart-line text-amber-400 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="stat-card group shimmer" style="--stat-accent: linear-gradient(90deg, #6366f1, #818cf8); --stat-glow: rgba(99,102,241,0.12); --stat-border-color: rgba(99,102,241,0.2);">
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Projects</p>
                <p class="text-xl font-bold" style="color: var(--text-primary);">{{ $totalProjects }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center glow-icon group-hover:scale-110 transition-all duration-500" style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.15);">
                <i class="fas fa-folder text-indigo-400 text-sm"></i>
            </div>
        </div>
    </div>
</div>

{{-- AI credits at-a-glance card (only visible when the engine is on). --}}
@if(\App\Services\AI\AiEngineSettings::isEnabled())
    @php
        $aiBal = app(\App\Services\AI\AiCreditService::class)->balanceFor($user);
    @endphp
    <a href="{{ route('user.ai-credits.show') }}" class="block mb-8">
        <div class="card-premium px-5 py-4 flex items-center justify-between hover:border-violet-500/40 transition-colors">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center"
                     style="background: rgba(168,85,247,0.12); border: 1px solid rgba(168,85,247,0.25);">
                    <i class="fas fa-brain text-violet-300"></i>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">AI credits</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($aiBal->balance) }} <span class="text-violet-300">✦</span>
                    </p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs" style="color: var(--text-faint);">
                    Spent {{ number_format($aiBal->lifetime_spent) }} lifetime
                </p>
                <p class="text-xs text-violet-300 mt-1">Manage &amp; top up <i class="fas fa-arrow-right ml-1"></i></p>
            </div>
        </div>
    </a>
@endif

{{-- ===================== WORKSPACE-WIDE CHANNEL BREAKDOWN =====================
     Rolls every link's click log up into a single "what share of my traffic
     comes from in-app webviews vs real browsers vs bots" view. The pills
     below double as a workspace-wide channel filter (?channel=…) that
     narrows the click-derived tiles above. --}}
<div class="card-premium overflow-hidden mb-6">
    <div class="flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid var(--border-subtle);">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(168,85,247,0.1); border: 1px solid rgba(168,85,247,0.18);">
                <i class="fas fa-window-restore text-fuchsia-400 text-xs"></i>
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
                            <div class="h-full rounded-full bg-gradient-to-r from-fuchsia-500 to-violet-500" style="width: {{ $pct }}%;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-2 pt-2" style="border-top: 1px solid var(--border-subtle);">
                <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);">Filter:</span>
                <a href="{{ $channelBuildUrl(null) }}" class="badge {{ empty($channelFilter) ? 'badge-active' : '' }}"
                   style="{{ empty($channelFilter) ? 'background: rgba(168,85,247,0.18); color: #f0abfc; border: 1px solid rgba(168,85,247,0.35);' : 'background: rgba(255,255,255,0.04); color: var(--text-muted); border: 1px solid var(--border-subtle);' }}">All</a>
                @foreach($channelStats as $row)
                    @php $key = $row->channel ?: 'unknown'; @endphp
                    @continue($key === \App\Modules\Common\Services\ChannelClassifier::KEY_UNKNOWN)
                    @php $isActive = ($channelFilter ?? '') === $key; @endphp
                    <a href="{{ $channelBuildUrl($key) }}"
                       class="badge inline-flex items-center gap-1.5"
                       style="{{ $isActive ? 'background: rgba(168,85,247,0.18); color: #f0abfc; border: 1px solid rgba(168,85,247,0.35);' : 'background: rgba(255,255,255,0.04); color: var(--text-muted); border: 1px solid var(--border-subtle);' }}">
                        <i class="fas {{ $channelIcon($key) }} text-[9px] opacity-80"></i>
                        {{ $channelLabelMap[$key] ?? $key }}
                        <span class="opacity-60">({{ number_format($row->count) }})</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="card-premium overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid var(--border-subtle);">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.15);">
                        <i class="fas fa-clock text-violet-400 text-xs"></i>
                    </div>
                    <h2 class="text-sm font-bold" style="color: var(--text-primary);">Recent Links</h2>
                </div>
                <a href="{{ route('user.links.wizard') }}" class="text-[11px] text-violet-400 hover:text-violet-300 font-semibold transition-all flex items-center gap-1 hover:gap-2" title="Guided bio link wizard (or use Quick Link for short URLs)">
                    <i class="fas fa-magic text-[9px]"></i> New Bio Link
                </a>
            </div>

            @php
                $personaBannerDismissed = !empty($user->settings['persona_banner_dismissed_at'] ?? null);
                $showPersonaBanner = $user->onboarded_at && empty($user->persona) && !$personaBannerDismissed;
            @endphp
            @if($showPersonaBanner)
            <div class="m-4 mb-0 rounded-2xl border border-violet-500/20 bg-gradient-to-r from-violet-600/10 to-fuchsia-500/5 p-4 flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-violet-500/15 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-sparkles text-violet-300"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-white">Want personalised template suggestions?</p>
                    <p class="text-xs text-white/50 mt-0.5">Tell us what you do in 10 seconds and we'll recommend the templates that fit.</p>
                </div>
                <a href="{{ route('user.onboarding.persona') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-violet-600 hover:bg-violet-700 text-white transition flex-shrink-0">Personalise</a>
                <form method="POST" action="{{ route('user.onboarding.dismiss-banner') }}">
                    @csrf
                    <button type="submit" class="text-white/30 hover:text-white/70 px-2 py-1.5" title="Dismiss"><i class="fas fa-times text-xs"></i></button>
                </form>
            </div>
            @endif
            @if($recentLinks->isEmpty())
            <div class="p-12 text-center">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4 animate-pulse-glow" style="background: rgba(124,58,237,0.08); border: 1px solid rgba(124,58,237,0.12);">
                    <i class="fas fa-link text-xl text-violet-400"></i>
                </div>
                <p class="text-sm mb-1 font-bold" style="color: var(--text-muted);">No links yet</p>
                <p class="text-xs mb-5" style="color: var(--text-dimmed);">Launch the guided wizard or build a quick short link.</p>
                <div class="flex items-center justify-center gap-2">
                    <a href="{{ route('user.links.wizard') }}" class="btn-primary text-xs py-2.5">
                        <i class="fas fa-magic text-[10px]"></i> Bio Link Wizard
                    </a>
                    <a href="{{ route('user.links.create') }}" class="btn-secondary text-xs py-2.5">
                        <i class="fas fa-plus text-[10px]"></i> Quick Link
                    </a>
                </div>
            </div>
            @else
            <div>
                @foreach($recentLinks as $link)
                <a href="{{ route('user.links.show', $link) }}" class="block px-5 py-3.5 transition-all hover:bg-white/[0.025] group" style="border-bottom: 1px solid var(--border-subtle);">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-sm font-semibold truncate group-hover:text-violet-400 transition-colors" style="color: var(--text-primary);">{{ $link->title ?: $link->alias }}</span>
                                <span class="badge" style="background: rgba(124,58,237,0.08); color: var(--accent-light); border: 1px solid rgba(124,58,237,0.12);">{{ $link->type }}</span>
                                @if(!$link->is_active)
                                <span class="badge" style="background: rgba(239,68,68,0.08); color: #f87171; border: 1px solid rgba(239,68,68,0.12);">off</span>
                                @endif
                            </div>
                            <div class="text-xs text-violet-400/60 truncate">{{ $link->getShortUrl() }}</div>
                        </div>
                        <div class="text-right ml-4 flex-shrink-0">
                            <div class="text-base font-bold" style="color: var(--text-primary);">{{ number_format($link->total_clicks) }}</div>
                            <div class="text-[10px]" style="color: var(--text-faint);">clicks</div>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
            <div class="px-5 py-3 text-center">
                <a href="{{ route('user.links.index') }}" class="text-xs text-violet-400 hover:text-violet-300 font-semibold transition-all inline-flex items-center gap-1 hover:gap-2">
                    View all links <i class="fas fa-arrow-right text-[10px]"></i>
                </a>
            </div>
            @endif
        </div>
    </div>

    <div class="space-y-5">
        <div class="card-premium p-5">
            <div class="flex items-center gap-2.5 mb-4">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.15);">
                    <i class="fas fa-bolt text-violet-400 text-xs"></i>
                </div>
                <h2 class="text-sm font-bold" style="color: var(--text-primary);">Quick Actions</h2>
            </div>
            <div class="space-y-1">
                <a href="{{ route('user.links.wizard') }}" class="flex items-center gap-3 p-2.5 rounded-xl transition-all group hover:translate-x-1" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center glow-icon transition-all duration-300" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.12);">
                        <i class="fas fa-magic text-violet-400 text-[10px]"></i>
                    </div>
                    <span class="text-xs font-medium" style="color: var(--text-muted);">Bio Link Wizard</span>
                    <i class="fas fa-chevron-right text-[8px] ml-auto transition-transform group-hover:translate-x-1" style="color: var(--text-faint);"></i>
                </a>
                <a href="{{ route('user.links.create') }}" class="flex items-center gap-3 p-2.5 rounded-xl transition-all group hover:translate-x-1" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center glow-icon transition-all duration-300" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.12);">
                        <i class="fas fa-link text-violet-400 text-[10px]"></i>
                    </div>
                    <span class="text-xs font-medium" style="color: var(--text-muted);">Shorten a URL</span>
                    <i class="fas fa-chevron-right text-[8px] ml-auto transition-transform group-hover:translate-x-1" style="color: var(--text-faint);"></i>
                </a>
                <a href="{{ route('user.projects.create') }}" class="flex items-center gap-3 p-2.5 rounded-xl transition-all group hover:translate-x-1" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center glow-icon transition-all duration-300" style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.12);">
                        <i class="fas fa-folder-plus text-indigo-400 text-[10px]"></i>
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

        <div class="card-premium p-5 shimmer">
            <div class="flex items-center gap-2.5 mb-4">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.15);">
                    <i class="fas fa-gem text-violet-400 text-xs"></i>
                </div>
                <h2 class="text-sm font-bold" style="color: var(--text-primary);">Your Plan</h2>
            </div>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-violet-500 via-violet-500 to-violet-700 flex items-center justify-center shadow-lg" style="box-shadow: 0 4px 16px rgba(124,58,237,0.3);">
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
                    <div class="h-full rounded-full bg-gradient-to-r from-violet-500 via-violet-500 to-violet-400 transition-all duration-1000" style="width: {{ $pct }}%; box-shadow: 0 0 8px rgba(124,58,237,0.4);"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
