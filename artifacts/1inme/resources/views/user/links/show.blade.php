@extends('user.layouts.app')
@section('title', $link->title ?: $link->alias)

@section('content')
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('user.links.index') }}" class="p-2 rounded-xl transition-all hover:bg-white/[0.04]" style="color: var(--text-faint);"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold gradient-text">{{ $link->title ?: $link->alias }}</h1>
            <div class="flex items-center gap-2 text-sm text-purple-400 mt-1" x-data="{ copied: false }">
                <span>{{ $link->getShortUrl() }}</span>
                <button @click="navigator.clipboard.writeText('{{ $link->getShortUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)" class="transition-colors hover:text-purple-300" style="color: var(--text-faint);">
                    <i x-show="!copied" class="fas fa-copy"></i>
                    <i x-show="copied" x-cloak class="fas fa-check text-emerald-400"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <form action="{{ route('user.links.toggle-active', $link) }}" method="POST">
            @csrf
            <button class="btn-ghost text-xs py-2 {{ $link->is_active ? 'text-emerald-400 border-emerald-500/20' : 'text-red-400 border-red-500/20' }}">
                <i class="fas {{ $link->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                {{ $link->is_active ? 'Active' : 'Inactive' }}
            </button>
        </form>
        <a href="{{ route('user.links.qrcode', $link) }}" class="btn-ghost text-xs py-2">
            <i class="fas fa-qrcode text-[10px]"></i> QR Code
        </a>
        @if($link->type === 'biolink')
        <a href="{{ route('user.links.blocks.editor', $link) }}" class="btn-primary text-xs py-2">
            <i class="fas fa-th-large text-[10px]"></i> Edit Blocks
        </a>
        @endif
        <a href="{{ route('user.links.edit', $link) }}" class="btn-ghost text-xs py-2">
            <i class="fas fa-edit text-[10px]"></i> Edit
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
    <div class="stat-card group" style="--stat-accent: linear-gradient(90deg, #8b5cf6, #a78bfa); --stat-glow: rgba(139,92,246,0.12); --stat-border-color: rgba(139,92,246,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Total Clicks</p>
        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($link->total_clicks) }}</p>
    </div>
    <div class="stat-card group" style="--stat-accent: linear-gradient(90deg, #10b981, #34d399); --stat-glow: rgba(16,185,129,0.12); --stat-border-color: rgba(16,185,129,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Unique Clicks</p>
        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($link->unique_clicks) }}</p>
    </div>
    <div class="stat-card group" style="--stat-accent: linear-gradient(90deg, #3b82f6, #60a5fa); --stat-glow: rgba(59,130,246,0.12); --stat-border-color: rgba(59,130,246,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Created</p>
        <p class="text-lg font-bold" style="color: var(--text-primary);">{{ $link->created_at->format('M d, Y') }}</p>
    </div>
    <div class="stat-card group" style="--stat-accent: linear-gradient(90deg, #f59e0b, #fbbf24); --stat-glow: rgba(245,158,11,0.12); --stat-border-color: rgba(245,158,11,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Type</p>
        <p class="text-lg font-bold capitalize" style="color: var(--text-primary);">{{ $link->type }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
    <div class="card-premium p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.15);">
                <i class="fas fa-chart-area text-purple-400 text-xs"></i>
            </div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Clicks Over Time (30 Days)</h3>
        </div>
        @if($clicksOverTime->isEmpty())
            <p class="text-sm text-center py-8" style="color: var(--text-faint);">No click data yet</p>
        @else
            <div class="space-y-2">
                @php $maxClicks = $clicksOverTime->max('count') ?: 1; @endphp
                @foreach($clicksOverTime->slice(-14) as $day)
                <div class="flex items-center gap-3 text-sm group">
                    <span class="w-16 flex-shrink-0 text-[11px] font-medium" style="color: var(--text-dimmed);">{{ \Carbon\Carbon::parse($day->date)->format('M d') }}</span>
                    <div class="flex-1 rounded-full h-5 overflow-hidden" style="background: var(--bg-glass-input);">
                        <div class="bg-gradient-to-r from-purple-600 to-violet-500 h-full rounded-full transition-all duration-700 group-hover:shadow-lg group-hover:shadow-purple-500/20" style="width: {{ ($day->count / $maxClicks) * 100 }}%"></div>
                    </div>
                    <span class="font-medium w-8 text-right text-xs" style="color: var(--text-muted);">{{ $day->count }}</span>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card-premium p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.15);">
                <i class="fas fa-globe text-emerald-400 text-xs"></i>
            </div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Top Referrers</h3>
        </div>
        @if($topReferrers->isEmpty())
            <p class="text-sm text-center py-8" style="color: var(--text-faint);">No referrer data yet</p>
        @else
            <div class="space-y-3">
                @foreach($topReferrers as $ref)
                <div class="flex items-center justify-between text-sm p-2 rounded-lg transition-all hover:bg-white/[0.02]">
                    <span class="truncate flex-1" style="color: var(--text-muted);">{{ parse_url($ref->referrer, PHP_URL_HOST) ?: $ref->referrer }}</span>
                    <span class="font-medium ml-3 text-xs" style="color: var(--text-dimmed);">{{ $ref->count }}</span>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
    <div class="card-premium p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.15);">
                <i class="fas fa-globe text-indigo-400 text-xs"></i>
            </div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Browsers</h3>
        </div>
        @if($browserStats->isEmpty())
            <p class="text-sm text-center py-4" style="color: var(--text-faint);">No data</p>
        @else
            @php $totalBrowser = $browserStats->sum('count') ?: 1; @endphp
            <div class="space-y-3">
                @foreach($browserStats as $stat)
                <div>
                    <div class="flex justify-between text-xs mb-1.5">
                        <span style="color: var(--text-muted);">{{ $stat->browser }}</span>
                        <span style="color: var(--text-dimmed);">{{ round(($stat->count / $totalBrowser) * 100) }}%</span>
                    </div>
                    <div class="rounded-full h-1.5" style="background: var(--bg-glass-input);">
                        <div class="bg-gradient-to-r from-indigo-500 to-purple-500 h-full rounded-full" style="width: {{ ($stat->count / $totalBrowser) * 100 }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card-premium p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.15);">
                <i class="fas fa-laptop text-emerald-400 text-xs"></i>
            </div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Operating Systems</h3>
        </div>
        @if($osStats->isEmpty())
            <p class="text-sm text-center py-4" style="color: var(--text-faint);">No data</p>
        @else
            @php $totalOS = $osStats->sum('count') ?: 1; @endphp
            <div class="space-y-3">
                @foreach($osStats as $stat)
                <div>
                    <div class="flex justify-between text-xs mb-1.5">
                        <span style="color: var(--text-muted);">{{ $stat->os }}</span>
                        <span style="color: var(--text-dimmed);">{{ round(($stat->count / $totalOS) * 100) }}%</span>
                    </div>
                    <div class="rounded-full h-1.5" style="background: var(--bg-glass-input);">
                        <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-full rounded-full" style="width: {{ ($stat->count / $totalOS) * 100 }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card-premium p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.15);">
                <i class="fas fa-mobile-alt text-amber-400 text-xs"></i>
            </div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Devices</h3>
        </div>
        @if($deviceStats->isEmpty())
            <p class="text-sm text-center py-4" style="color: var(--text-faint);">No data</p>
        @else
            @php $totalDevice = $deviceStats->sum('count') ?: 1; @endphp
            <div class="space-y-3">
                @foreach($deviceStats as $stat)
                <div>
                    <div class="flex justify-between text-xs mb-1.5">
                        <span class="capitalize" style="color: var(--text-muted);">{{ $stat->device_type }}</span>
                        <span style="color: var(--text-dimmed);">{{ round(($stat->count / $totalDevice) * 100) }}%</span>
                    </div>
                    <div class="rounded-full h-1.5" style="background: var(--bg-glass-input);">
                        <div class="bg-gradient-to-r from-amber-500 to-orange-500 h-full rounded-full" style="width: {{ ($stat->count / $totalDevice) * 100 }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="card-premium p-5">
    <div class="flex items-center gap-2.5 mb-4">
        <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.15);">
            <i class="fas fa-info-circle text-purple-400 text-xs"></i>
        </div>
        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Link Details</h3>
    </div>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        @if($link->long_url)
        <div class="p-3 rounded-xl" style="background: var(--bg-glass-input);">
            <dt class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Destination URL</dt>
            <dd class="truncate" style="color: var(--text-primary);">{{ $link->long_url }}</dd>
        </div>
        @endif
        @if($link->project)
        <div class="p-3 rounded-xl" style="background: var(--bg-glass-input);">
            <dt class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Project</dt>
            <dd class="flex items-center gap-2" style="color: var(--text-primary);">
                <span class="w-3 h-3 rounded-full" style="background-color: {{ $link->project->color }}"></span>
                {{ $link->project->name }}
            </dd>
        </div>
        @endif
        @if($link->expires_at)
        <div class="p-3 rounded-xl" style="background: var(--bg-glass-input);">
            <dt class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Expires</dt>
            <dd style="color: var(--text-primary);">{{ $link->expires_at->format('M d, Y H:i') }}</dd>
        </div>
        @endif
        <div class="p-3 rounded-xl" style="background: var(--bg-glass-input);">
            <dt class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Password Protected</dt>
            <dd style="color: var(--text-primary);">{{ $link->is_password_protected ? 'Yes' : 'No' }}</dd>
        </div>
        @if($link->pixels->count())
        <div class="md:col-span-2 p-3 rounded-xl" style="background: var(--bg-glass-input);">
            <dt class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Tracking Pixels</dt>
            <dd class="flex flex-wrap gap-2">
                @foreach($link->pixels as $pixel)
                    <span class="badge" style="background: rgba(139,92,246,0.08); color: var(--accent-light); border: 1px solid rgba(139,92,246,0.12);">{{ $pixel->name }}</span>
                @endforeach
            </dd>
        </div>
        @endif
    </dl>
</div>
@endsection
