@extends('user.layouts.app')
@section('title', $link->title ?: $link->alias)

@section('content')
@php
    $countryNames = ['US'=>'United States','IN'=>'India','GB'=>'United Kingdom','CA'=>'Canada','AU'=>'Australia','DE'=>'Germany','FR'=>'France','BR'=>'Brazil','JP'=>'Japan','CN'=>'China','RU'=>'Russia','MX'=>'Mexico','ES'=>'Spain','IT'=>'Italy','NL'=>'Netherlands','SE'=>'Sweden','SG'=>'Singapore','ZA'=>'South Africa','AE'=>'UAE','PK'=>'Pakistan','BD'=>'Bangladesh','ID'=>'Indonesia','TR'=>'Turkey','PH'=>'Philippines','TH'=>'Thailand','VN'=>'Vietnam','KR'=>'South Korea'];
    $blockTypes = \App\Modules\User\Models\BiolinkBlock::TYPES;
    $qs = request()->query();
    $buildUrl = fn($overrides = []) => route('user.links.show', $link) . '?' . http_build_query(array_merge($qs, $overrides));
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
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
    <div class="flex items-center gap-2 flex-wrap">
        <a href="{{ route('user.links.clicks.export', $link) }}?{{ http_build_query($qs) }}" class="btn-ghost text-xs py-2"><i class="fas fa-file-csv text-[10px]"></i> Export CSV</a>
        <a href="{{ route('user.links.qrcode', $link) }}" class="btn-ghost text-xs py-2"><i class="fas fa-qrcode text-[10px]"></i> QR</a>
        @if($link->type === 'biolink')
        <a href="{{ route('user.links.blocks.editor', $link) }}" class="btn-primary text-xs py-2"><i class="fas fa-th-large text-[10px]"></i> Edit Blocks</a>
        @endif
        <a href="{{ route('user.links.edit', $link) }}" class="btn-ghost text-xs py-2"><i class="fas fa-edit text-[10px]"></i> Edit</a>
    </div>
</div>

<div class="card-premium p-3 mb-5">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);">Period:</span>
        @foreach(['today'=>'Today','7d'=>'7d','30d'=>'30d','90d'=>'90d','year'=>'Year','all'=>'All'] as $k=>$lbl)
            <a href="{{ $buildUrl(['period'=>$k]) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ ($period ?? '30d')===$k ? 'text-white' : 'hover:bg-white/[0.04]' }}" style="{{ ($period ?? '30d')===$k ? 'background: linear-gradient(135deg,#7c3aed,#a855f7);' : 'color: var(--text-muted);' }}">{{ $lbl }}</a>
        @endforeach
        <span class="mx-3 h-5 w-px" style="background: var(--border-glass);"></span>
        <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);">Group:</span>
        @foreach(['day'=>'Day','week'=>'Week','month'=>'Month','year'=>'Year'] as $k=>$lbl)
            <a href="{{ $buildUrl(['group'=>$k]) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ ($groupBy ?? 'day')===$k ? 'text-white' : 'hover:bg-white/[0.04]' }}" style="{{ ($groupBy ?? 'day')===$k ? 'background: rgba(139,92,246,0.2); border: 1px solid rgba(139,92,246,0.4);' : 'color: var(--text-muted);' }}">{{ $lbl }}</a>
        @endforeach
        <span class="mx-3 h-5 w-px" style="background: var(--border-glass);"></span>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="period" value="custom">
            <input type="hidden" name="group" value="{{ $groupBy }}">
            <input type="date" name="from" value="{{ request('from', $startDate->format('Y-m-d')) }}" class="theme-input text-xs py-1.5 px-2">
            <span class="text-xs" style="color:var(--text-faint);">to</span>
            <input type="date" name="to" value="{{ request('to', $endDate->format('Y-m-d')) }}" class="theme-input text-xs py-1.5 px-2">
            <button class="btn-ghost text-xs py-1.5 px-3">Apply</button>
        </form>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
    <div class="stat-card" style="--stat-accent: linear-gradient(90deg, #8b5cf6, #a78bfa); --stat-glow: rgba(139,92,246,0.12); --stat-border-color: rgba(139,92,246,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Total (Range)</p>
        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($totalInRange) }}</p>
    </div>
    <div class="stat-card" style="--stat-accent: linear-gradient(90deg, #10b981, #34d399); --stat-glow: rgba(16,185,129,0.12); --stat-border-color: rgba(16,185,129,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Unique IPs</p>
        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($uniqueInRange) }}</p>
    </div>
    <div class="stat-card" style="--stat-accent: linear-gradient(90deg, #3b82f6, #60a5fa); --stat-glow: rgba(59,130,246,0.12); --stat-border-color: rgba(59,130,246,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Page Visits</p>
        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($pageVisitsInRange) }}</p>
    </div>
    <div class="stat-card" style="--stat-accent: linear-gradient(90deg, #f59e0b, #fbbf24); --stat-glow: rgba(245,158,11,0.12); --stat-border-color: rgba(245,158,11,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Block Clicks</p>
        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($blockClicksInRange) }}</p>
    </div>
    <div class="stat-card" style="--stat-accent: linear-gradient(90deg, #ec4899, #f472b6); --stat-glow: rgba(236,72,153,0.12); --stat-border-color: rgba(236,72,153,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">All-Time Total</p>
        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($link->total_clicks) }}</p>
    </div>
    <div class="stat-card" style="--stat-accent: linear-gradient(90deg, #06b6d4, #22d3ee); --stat-glow: rgba(6,182,212,0.12); --stat-border-color: rgba(6,182,212,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">All-Time Unique</p>
        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($link->unique_clicks) }}</p>
    </div>
</div>

@php
    function _fmtSecs($s){ $s=(int)$s; if($s<60) return $s.'s'; $m=intdiv($s,60); $r=$s%60; if($m<60) return $m.'m '.$r.'s'; $h=intdiv($m,60); return $h.'h '.($m%60).'m'; }
    function _fmtMs($ms){ return _fmtSecs(intdiv((int)$ms,1000)); }
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="stat-card" style="--stat-accent: linear-gradient(90deg, #14b8a6, #2dd4bf); --stat-glow: rgba(20,184,166,0.12); --stat-border-color: rgba(20,184,166,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Sessions</p>
        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($totalSessions) }}</p>
    </div>
    <div class="stat-card" style="--stat-accent: linear-gradient(90deg, #6366f1, #818cf8); --stat-glow: rgba(99,102,241,0.12); --stat-border-color: rgba(99,102,241,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Avg. Time on Page</p>
        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ _fmtSecs($avgSessionSeconds) }}</p>
    </div>
    <div class="stat-card" style="--stat-accent: linear-gradient(90deg, #f59e0b, #fbbf24); --stat-glow: rgba(245,158,11,0.12); --stat-border-color: rgba(245,158,11,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Total Engaged Time</p>
        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ _fmtSecs($totalEngagedSeconds) }}</p>
    </div>
    <div class="stat-card" style="--stat-accent: linear-gradient(90deg, #ef4444, #f87171); --stat-glow: rgba(239,68,68,0.12); --stat-border-color: rgba(239,68,68,0.2);">
        <p class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Bounce Rate</p>
        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $bounceRate }}%</p>
        <p class="text-[10px] mt-0.5" style="color: var(--text-faint);">Sessions under 5s</p>
    </div>
</div>

<div class="card-premium p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.15);"><i class="fas fa-chart-line text-purple-400 text-xs"></i></div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Clicks Over Time ({{ ucfirst($groupBy) }})</h3>
        </div>
        <span class="text-xs" style="color: var(--text-faint);">{{ $startDate->format('M d, Y') }} → {{ $endDate->format('M d, Y') }}</span>
    </div>
    @if($clicksOverTime->isEmpty())
        <p class="text-sm text-center py-12" style="color: var(--text-faint);">No click data in this range</p>
    @else
        <div style="height: 300px;"><canvas id="clicksChart"></canvas></div>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
    <div class="card-premium p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.15);"><i class="fas fa-globe text-indigo-400 text-xs"></i></div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Browsers</h3>
        </div>
        @if($browserStats->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
        @else<div style="height: 220px;"><canvas id="browserChart"></canvas></div>@endif
    </div>
    <div class="card-premium p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.15);"><i class="fas fa-laptop text-emerald-400 text-xs"></i></div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Operating Systems</h3>
        </div>
        @if($osStats->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
        @else<div style="height: 220px;"><canvas id="osChart"></canvas></div>@endif
    </div>
    <div class="card-premium p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.15);"><i class="fas fa-mobile-alt text-amber-400 text-xs"></i></div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Devices</h3>
        </div>
        @if($deviceStats->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
        @else<div style="height: 220px;"><canvas id="deviceChart"></canvas></div>@endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
    <div class="card-premium p-5">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.15);"><i class="fas fa-flag text-blue-400 text-xs"></i></div>
                <h3 class="text-sm font-bold" style="color: var(--text-primary);">Top Countries</h3>
            </div>
        </div>
        @if($countryStats->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
        @else
        <div class="overflow-y-auto max-h-72">
            <table class="w-full text-sm">
                <thead><tr class="text-[10px] uppercase tracking-wider" style="color: var(--text-faint);"><th class="text-left py-2 px-2 font-bold">Country</th><th class="text-right py-2 px-2 font-bold">Clicks</th><th class="text-right py-2 px-2 font-bold">%</th></tr></thead>
                <tbody>
                @php $totalC = $countryStats->sum('count') ?: 1; @endphp
                @foreach($countryStats as $stat)
                <tr class="hover:bg-white/[0.02]" style="border-top: 1px solid var(--border-glass);">
                    <td class="py-2 px-2" style="color: var(--text-primary);">{{ $countryNames[$stat->country_code] ?? $stat->country_code }} <span style="color: var(--text-faint);">({{ $stat->country_code }})</span></td>
                    <td class="py-2 px-2 text-right" style="color: var(--text-muted);">{{ $stat->count }}</td>
                    <td class="py-2 px-2 text-right text-xs" style="color: var(--text-dimmed);">{{ round(($stat->count / $totalC) * 100, 1) }}%</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    <div class="card-premium p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(236,72,153,0.1); border: 1px solid rgba(236,72,153,0.15);"><i class="fas fa-city text-pink-400 text-xs"></i></div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Top Cities</h3>
        </div>
        @if($cityStats->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
        @else
        <div class="overflow-y-auto max-h-72">
            <table class="w-full text-sm">
                <thead><tr class="text-[10px] uppercase tracking-wider" style="color: var(--text-faint);"><th class="text-left py-2 px-2 font-bold">City</th><th class="text-left py-2 px-2 font-bold">Country</th><th class="text-right py-2 px-2 font-bold">Clicks</th></tr></thead>
                <tbody>
                @foreach($cityStats as $stat)
                <tr class="hover:bg-white/[0.02]" style="border-top: 1px solid var(--border-glass);">
                    <td class="py-2 px-2" style="color: var(--text-primary);">{{ $stat->city }}</td>
                    <td class="py-2 px-2" style="color: var(--text-muted);">{{ $stat->country_code }}</td>
                    <td class="py-2 px-2 text-right" style="color: var(--text-muted);">{{ $stat->count }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@if($link->type === 'biolink')
<div class="card-premium p-5 mb-6">
    <div class="flex items-center gap-2.5 mb-4">
        <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(168,85,247,0.1); border: 1px solid rgba(168,85,247,0.15);"><i class="fas fa-th-large text-purple-400 text-xs"></i></div>
        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Block-Level Clicks</h3>
        <span class="text-[10px] px-2 py-0.5 rounded-full ml-auto" style="background: rgba(168,85,247,0.1); color: #a855f7;">Internal biolink links</span>
    </div>
    @if($blockStats->isEmpty())
        <p class="text-sm text-center py-8" style="color: var(--text-faint);">No block clicks recorded yet. Make sure your biolink blocks are using tracked links.</p>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-[10px] uppercase tracking-wider" style="color: var(--text-faint);">
                <th class="text-left py-2 px-2 font-bold">Block</th><th class="text-left py-2 px-2 font-bold">Type</th><th class="text-left py-2 px-2 font-bold">Destination</th><th class="text-right py-2 px-2 font-bold">Clicks</th><th class="text-right py-2 px-2 font-bold">Unique</th>
            </tr></thead>
            <tbody>
            @foreach($blockStats as $b)
            @php $info = $blockTypes[$b->block_type] ?? ['label'=>ucfirst($b->block_type), 'icon'=>'fa-cube']; @endphp
            <tr class="hover:bg-white/[0.02]" style="border-top: 1px solid var(--border-glass);">
                <td class="py-2 px-2" style="color: var(--text-primary);"><i class="fas {{ $info['icon'] }} mr-1.5 text-purple-400"></i> #{{ $b->block_id }}</td>
                <td class="py-2 px-2"><span class="badge text-[10px]" style="background:rgba(139,92,246,0.08); color:#a78bfa;">{{ $info['label'] }}</span></td>
                <td class="py-2 px-2 text-xs truncate max-w-md" style="color: var(--text-muted);">{{ $b->destination_url }}</td>
                <td class="py-2 px-2 text-right font-medium" style="color: var(--text-primary);">{{ $b->count }}</td>
                <td class="py-2 px-2 text-right" style="color: var(--text-muted);">{{ $b->unique_count }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<div class="card-premium p-5 mb-6">
    <div class="flex items-center gap-2.5 mb-4">
        <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(20,184,166,0.1); border: 1px solid rgba(20,184,166,0.15);"><i class="fas fa-eye text-teal-400 text-xs"></i></div>
        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Block Engagement (Visibility)</h3>
        <span class="text-[10px] px-2 py-0.5 rounded-full ml-auto" style="background: rgba(20,184,166,0.1); color: #2dd4bf;">Time visible on screen</span>
    </div>
    @if($blockEngagement->isEmpty())
        <p class="text-sm text-center py-8" style="color: var(--text-faint);">No view data yet. Visit the public biolink page to start collecting engagement data.</p>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-[10px] uppercase tracking-wider" style="color: var(--text-faint);">
                <th class="text-left py-2 px-2 font-bold">Block</th>
                <th class="text-left py-2 px-2 font-bold">Type</th>
                <th class="text-right py-2 px-2 font-bold">Impressions</th>
                <th class="text-right py-2 px-2 font-bold">Viewers</th>
                <th class="text-right py-2 px-2 font-bold">Total Time</th>
                <th class="text-right py-2 px-2 font-bold">Avg / View</th>
                <th class="text-right py-2 px-2 font-bold">Clicks</th>
                <th class="text-right py-2 px-2 font-bold">CTR</th>
            </tr></thead>
            <tbody>
            @foreach($blockEngagement as $b)
            @php
                $info = $blockTypes[$b->block_type] ?? ['label'=>ucfirst($b->block_type ?? 'block'), 'icon'=>'fa-cube'];
                $clicks = $blockClickMap[$b->block_id] ?? 0;
                $ctr = $b->impressions > 0 ? round(($clicks / $b->impressions) * 100, 1) : 0;
            @endphp
            <tr class="hover:bg-white/[0.02]" style="border-top: 1px solid var(--border-glass);">
                <td class="py-2 px-2" style="color: var(--text-primary);"><i class="fas {{ $info['icon'] }} mr-1.5 text-teal-400"></i> #{{ $b->block_id }}</td>
                <td class="py-2 px-2"><span class="badge text-[10px]" style="background:rgba(20,184,166,0.08); color:#2dd4bf;">{{ $info['label'] }}</span></td>
                <td class="py-2 px-2 text-right" style="color: var(--text-muted);">{{ number_format($b->impressions) }}</td>
                <td class="py-2 px-2 text-right" style="color: var(--text-muted);">{{ number_format($b->unique_viewers) }}</td>
                <td class="py-2 px-2 text-right font-medium" style="color: var(--text-primary);">{{ _fmtMs($b->total_ms) }}</td>
                <td class="py-2 px-2 text-right" style="color: var(--text-muted);">{{ number_format(($b->avg_ms ?? 0)/1000, 1) }}s</td>
                <td class="py-2 px-2 text-right" style="color: var(--text-muted);">{{ $clicks }}</td>
                <td class="py-2 px-2 text-right text-xs" style="color: var(--text-dimmed);">{{ $ctr }}%</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
    <div class="card-premium p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.15);"><i class="fas fa-link text-emerald-400 text-xs"></i></div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Top Referrers</h3>
        </div>
        @if($topReferrers->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No referrer data</p>
        @else
        <div class="space-y-2 max-h-72 overflow-y-auto">
            @foreach($topReferrers as $ref)
            <div class="flex items-center justify-between text-sm p-2 rounded-lg hover:bg-white/[0.02]">
                <span class="truncate flex-1" style="color: var(--text-muted);">{{ parse_url($ref->referrer, PHP_URL_HOST) ?: $ref->referrer }}</span>
                <span class="font-medium ml-3 text-xs" style="color: var(--text-dimmed);">{{ $ref->count }}</span>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    <div class="card-premium p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.15);"><i class="fas fa-bullseye text-amber-400 text-xs"></i></div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">UTM Campaigns</h3>
        </div>
        @if($utmStats->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No UTM data</p>
        @else
        <div class="space-y-2 max-h-72 overflow-y-auto">
            @foreach($utmStats as $u)
            @php $p = $u->utm_params; if (is_string($p)) $p = json_decode($p, true) ?: []; @endphp
            <div class="flex items-center justify-between text-xs p-2 rounded-lg hover:bg-white/[0.02]">
                <div class="flex-1 truncate">
                    <span style="color: var(--text-primary);">{{ $p['utm_source'] ?? '—' }}</span>
                    <span style="color: var(--text-faint);"> / {{ $p['utm_medium'] ?? '—' }}</span>
                    <span style="color: var(--text-faint);"> / {{ $p['utm_campaign'] ?? '—' }}</span>
                </div>
                <span class="font-medium ml-3" style="color: var(--text-dimmed);">{{ $u->count }}</span>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

<div class="card-premium p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.15);"><i class="fas fa-list text-purple-400 text-xs"></i></div>
            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Recent Clicks</h3>
        </div>
        <a href="{{ route('user.links.clicks.export', $link) }}?{{ http_build_query($qs) }}" class="text-xs text-purple-400 hover:text-purple-300"><i class="fas fa-file-csv"></i> Export full log</a>
    </div>
    <div id="recent-clicks-container" data-endpoint="{{ route('user.links.clicks.partial', $link) }}?{{ http_build_query($qs) }}">
        @include('user.links.partials.recent-clicks-table')
    </div>
</div>

@push('scripts')
<script>
(function(){
    var container = document.getElementById('recent-clicks-container');
    if(!container) return;
    var endpoint = container.dataset.endpoint;
    container.addEventListener('click', function(e){
        var btn = e.target.closest('.rc-page-btn');
        if(!btn) return;
        e.preventDefault();
        var page = btn.getAttribute('data-rc-page');
        if(!page) return;
        var sep = endpoint.indexOf('?') === -1 ? '?' : '&';
        var url = endpoint + sep + 'page=' + encodeURIComponent(page);
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
        fetch(url, {headers: {'X-Requested-With':'XMLHttpRequest', 'Accept':'text/html'}, credentials:'same-origin'})
            .then(function(r){ return r.text(); })
            .then(function(html){
                container.innerHTML = html;
                container.style.opacity = '';
                container.style.pointerEvents = '';
                container.scrollIntoView({behavior:'smooth', block:'start'});
            })
            .catch(function(){
                container.style.opacity = '';
                container.style.pointerEvents = '';
                window.location.href = btn.getAttribute('href');
            });
    });
})();
</script>
@endpush


<div class="card-premium p-5">
    <div class="flex items-center gap-2.5 mb-4">
        <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.15);"><i class="fas fa-info-circle text-purple-400 text-xs"></i></div>
        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Link Details</h3>
    </div>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        @if($link->long_url)<div class="p-3 rounded-xl" style="background: var(--bg-glass-input);"><dt class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Destination</dt><dd class="truncate" style="color: var(--text-primary);">{{ $link->long_url }}</dd></div>@endif
        <div class="p-3 rounded-xl" style="background: var(--bg-glass-input);"><dt class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Created</dt><dd style="color: var(--text-primary);">{{ $link->created_at->format('M d, Y H:i') }}</dd></div>
        <div class="p-3 rounded-xl" style="background: var(--bg-glass-input);"><dt class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Type</dt><dd class="capitalize" style="color: var(--text-primary);">{{ $link->type }}</dd></div>
        <div class="p-3 rounded-xl" style="background: var(--bg-glass-input);"><dt class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Password Protected</dt><dd style="color: var(--text-primary);">{{ $link->is_password_protected ? 'Yes' : 'No' }}</dd></div>
    </dl>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isDark = document.documentElement.classList.contains('dark') || !document.documentElement.classList.contains('light');
    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
    const tickColor = isDark ? 'rgba(255,255,255,0.55)' : 'rgba(0,0,0,0.6)';
    const palette = ['#7c3aed','#10b981','#3b82f6','#f59e0b','#ec4899','#06b6d4','#a855f7','#ef4444','#14b8a6','#eab308'];

    @if(!$clicksOverTime->isEmpty())
    new Chart(document.getElementById('clicksChart'), {
        type: 'line',
        data: {
            labels: @json($clicksOverTime->pluck('bucket')),
            datasets: [
                { label: 'Total Clicks', data: @json($clicksOverTime->pluck('count')), borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,0.15)', tension: 0.4, fill: true, borderWidth: 2, pointRadius: 3 },
                { label: 'Unique IPs', data: @json($clicksOverTime->pluck('unique_count')), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', tension: 0.4, fill: true, borderWidth: 2, pointRadius: 3 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: tickColor } } }, scales: { x: { grid: { color: gridColor }, ticks: { color: tickColor } }, y: { grid: { color: gridColor }, ticks: { color: tickColor }, beginAtZero: true } } }
    });
    @endif

    function doughnut(id, labels, data) {
        const el = document.getElementById(id); if (!el) return;
        new Chart(el, { type: 'doughnut', data: { labels, datasets: [{ data, backgroundColor: palette, borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { color: tickColor, font: { size: 11 } } } } } });
    }

    @if(!$browserStats->isEmpty())doughnut('browserChart', @json($browserStats->pluck('browser')), @json($browserStats->pluck('count')));@endif
    @if(!$osStats->isEmpty())doughnut('osChart', @json($osStats->pluck('os')), @json($osStats->pluck('count')));@endif
    @if(!$deviceStats->isEmpty())doughnut('deviceChart', @json($deviceStats->pluck('device_type')), @json($deviceStats->pluck('count')));@endif
});
</script>
@endpush
@endsection
