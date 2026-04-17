@extends('user.layouts.app')
@section('title', 'Dashboard')

@section('content')
@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $heroIcon = $hour < 12 ? 'fa-sun' : ($hour < 17 ? 'fa-cloud-sun' : 'fa-moon');
    $heroDay = now()->format('l');
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
        ['label' => 'Create Link', 'url' => route('user.links.create'), 'icon' => 'fa-plus', 'class' => 'btn-primary'],
    ],
])

<div class="grid grid-cols-2 md:grid-cols-5 gap-5 mb-8">
    <div class="stat-card group shimmer" style="--stat-accent: linear-gradient(90deg, #8b5cf6, #a78bfa); --stat-glow: rgba(124,58,237,0.12); --stat-border-color: rgba(124,58,237,0.2);">
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Plan</p>
                <p class="text-xl font-bold gradient-text">{{ $user->plan->name ?? 'Free' }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center glow-icon group-hover:scale-110 transition-all duration-500" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.15);">
                <i class="fas fa-crown text-violet-400 text-sm"></i>
            </div>
        </div>
    </div>

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
                <a href="{{ route('user.links.create') }}" class="text-[11px] text-violet-400 hover:text-violet-300 font-semibold transition-all flex items-center gap-1 hover:gap-2">
                    <i class="fas fa-plus text-[9px]"></i> New
                </a>
            </div>

            @if($recentLinks->isEmpty())
            <div class="p-12 text-center">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4 animate-pulse-glow" style="background: rgba(124,58,237,0.08); border: 1px solid rgba(124,58,237,0.12);">
                    <i class="fas fa-link text-xl text-violet-400"></i>
                </div>
                <p class="text-sm mb-1 font-bold" style="color: var(--text-muted);">No links yet</p>
                <p class="text-xs mb-5" style="color: var(--text-dimmed);">Create your first link to get started</p>
                <a href="{{ route('user.links.create') }}" class="btn-primary text-xs py-2.5">
                    <i class="fas fa-plus text-[10px]"></i> Create Link
                </a>
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
