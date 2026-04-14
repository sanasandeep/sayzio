@extends('user.layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="mb-8">
    <div class="flex items-center gap-3 mb-1">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
            @php
                $hour = now()->hour;
                $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
            @endphp
            {{ $greeting }}, {{ $user->name }}
        </h1>
        <span class="text-lg">
            @if($hour < 12)☀️@elseif($hour < 17)🌤️@else🌙@endif
        </span>
    </div>
    <p class="text-sm" style="color: var(--text-dimmed);">Here's an overview of your link performance</p>
</div>

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-8">
    <div class="stat-card group" style="--stat-accent: linear-gradient(90deg, #8b5cf6, #a78bfa);">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Plan</p>
                <p class="text-xl font-bold" style="color: var(--text-primary);">{{ $user->plan->name ?? 'Free' }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300" style="background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.12);">
                <i class="fas fa-crown text-purple-400 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="stat-card group" style="--stat-accent: linear-gradient(90deg, #10b981, #34d399);">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Links</p>
                <p class="text-xl font-bold" style="color: var(--text-primary);">{{ $totalLinks }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300" style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.12);">
                <i class="fas fa-link text-emerald-400 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="stat-card group" style="--stat-accent: linear-gradient(90deg, #3b82f6, #60a5fa);">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Total Clicks</p>
                <p class="text-xl font-bold" style="color: var(--text-primary);">{{ number_format($totalClicks) }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300" style="background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.12);">
                <i class="fas fa-mouse-pointer text-blue-400 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="stat-card group" style="--stat-accent: linear-gradient(90deg, #f59e0b, #fbbf24);">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Today</p>
                <p class="text-xl font-bold" style="color: var(--text-primary);">{{ number_format($clicksToday) }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.12);">
                <i class="fas fa-chart-line text-amber-400 text-sm"></i>
            </div>
        </div>
    </div>

    <div class="stat-card group" style="--stat-accent: linear-gradient(90deg, #6366f1, #818cf8);">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Projects</p>
                <p class="text-xl font-bold" style="color: var(--text-primary);">{{ $totalProjects }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300" style="background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.12);">
                <i class="fas fa-folder text-indigo-400 text-sm"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2">
        <div class="card-premium overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid var(--border-subtle);">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.12);">
                        <i class="fas fa-clock text-purple-400 text-[10px]"></i>
                    </div>
                    <h2 class="text-sm font-semibold" style="color: var(--text-primary);">Recent Links</h2>
                </div>
                <a href="{{ route('user.links.create') }}" class="text-[11px] text-purple-400 hover:text-purple-300 font-semibold transition-colors flex items-center gap-1">
                    <i class="fas fa-plus text-[9px]"></i> New
                </a>
            </div>

            @if($recentLinks->isEmpty())
            <div class="p-10 text-center">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-3" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                    <i class="fas fa-link text-lg" style="color: var(--text-faint);"></i>
                </div>
                <p class="text-sm mb-1 font-medium" style="color: var(--text-muted);">No links yet</p>
                <p class="text-xs mb-4" style="color: var(--text-dimmed);">Create your first link to get started</p>
                <a href="{{ route('user.links.create') }}" class="btn-primary text-xs py-2">
                    <i class="fas fa-plus text-[10px]"></i> Create Link
                </a>
            </div>
            @else
            <div style="border-color: var(--border-subtle);">
                @foreach($recentLinks as $link)
                <a href="{{ route('user.links.show', $link) }}" class="block px-5 py-3.5 transition-all hover:bg-white/[0.02]" style="border-bottom: 1px solid var(--border-subtle);">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-sm font-semibold truncate" style="color: var(--text-primary);">{{ $link->title ?: $link->alias }}</span>
                                <span class="badge" style="background: rgba(139,92,246,0.08); color: var(--accent-light); border: 1px solid rgba(139,92,246,0.12);">{{ $link->type }}</span>
                                @if(!$link->is_active)
                                <span class="badge" style="background: rgba(239,68,68,0.08); color: #f87171; border: 1px solid rgba(239,68,68,0.12);">off</span>
                                @endif
                            </div>
                            <div class="text-xs text-purple-400/60 truncate">{{ $link->getShortUrl() }}</div>
                        </div>
                        <div class="text-right ml-4 flex-shrink-0">
                            <div class="text-base font-bold" style="color: var(--text-primary);">{{ number_format($link->total_clicks) }}</div>
                            <div class="text-[10px]" style="color: var(--text-faint);">clicks</div>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
            <div class="px-5 py-3 text-center" style="border-top: 1px solid var(--border-subtle);">
                <a href="{{ route('user.links.index') }}" class="text-xs text-purple-400 hover:text-purple-300 font-semibold transition-colors">
                    View all links <i class="fas fa-arrow-right text-[10px] ml-0.5"></i>
                </a>
            </div>
            @endif
        </div>
    </div>

    <div class="space-y-5">
        <div class="card-premium p-5">
            <div class="flex items-center gap-2.5 mb-4">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.12);">
                    <i class="fas fa-bolt text-purple-400 text-[10px]"></i>
                </div>
                <h2 class="text-sm font-semibold" style="color: var(--text-primary);">Quick Actions</h2>
            </div>
            <div class="space-y-1">
                <a href="{{ route('user.links.create') }}" class="flex items-center gap-3 p-2.5 rounded-lg transition-all group" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform" style="background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.1);">
                        <i class="fas fa-link text-purple-400 text-[10px]"></i>
                    </div>
                    <span class="text-xs font-medium" style="color: var(--text-muted);">Shorten a URL</span>
                    <i class="fas fa-chevron-right text-[8px] ml-auto" style="color: var(--text-faint);"></i>
                </a>
                <a href="{{ route('user.projects.create') }}" class="flex items-center gap-3 p-2.5 rounded-lg transition-all group" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform" style="background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.1);">
                        <i class="fas fa-folder-plus text-indigo-400 text-[10px]"></i>
                    </div>
                    <span class="text-xs font-medium" style="color: var(--text-muted);">Create Project</span>
                    <i class="fas fa-chevron-right text-[8px] ml-auto" style="color: var(--text-faint);"></i>
                </a>
                <a href="{{ route('user.pixels.create') }}" class="flex items-center gap-3 p-2.5 rounded-lg transition-all group" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform" style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.1);">
                        <i class="fas fa-bullseye text-emerald-400 text-[10px]"></i>
                    </div>
                    <span class="text-xs font-medium" style="color: var(--text-muted);">Add Tracking Pixel</span>
                    <i class="fas fa-chevron-right text-[8px] ml-auto" style="color: var(--text-faint);"></i>
                </a>
                <a href="{{ route('user.qrcode') }}" class="flex items-center gap-3 p-2.5 rounded-lg transition-all group" style="background: transparent;" onmouseover="this.style.background='var(--bg-glass-input)'" onmouseout="this.style.background='transparent'">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform" style="background: rgba(6,182,212,0.08); border: 1px solid rgba(6,182,212,0.1);">
                        <i class="fas fa-qrcode text-cyan-400 text-[10px]"></i>
                    </div>
                    <span class="text-xs font-medium" style="color: var(--text-muted);">Generate QR Code</span>
                    <i class="fas fa-chevron-right text-[8px] ml-auto" style="color: var(--text-faint);"></i>
                </a>
            </div>
        </div>

        <div class="card-premium p-5">
            <div class="flex items-center gap-2.5 mb-4">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.12);">
                    <i class="fas fa-gem text-purple-400 text-[10px]"></i>
                </div>
                <h2 class="text-sm font-semibold" style="color: var(--text-primary);">Your Plan</h2>
            </div>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center shadow-md" style="box-shadow: 0 4px 12px rgba(139,92,246,0.25);">
                    <i class="fas fa-gem text-white text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-bold" style="color: var(--text-primary);">{{ $user->plan->name ?? 'Free' }}</p>
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
                    <div class="h-full rounded-full bg-gradient-to-r from-purple-500 to-violet-500" style="width: {{ $pct }}%;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
