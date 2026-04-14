@extends('user.layouts.app')
@section('title', $link->title ?: $link->alias)

@section('content')
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('user.links.index') }}" class="text-white/30 hover:text-white/50"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">{{ $link->title ?: $link->alias }}</h1>
            <div class="flex items-center gap-2 text-sm text-purple-400 mt-1" x-data="{ copied: false }">
                <span>{{ $link->getShortUrl() }}</span>
                <button @click="navigator.clipboard.writeText('{{ $link->getShortUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-white/30 hover:text-purple-400">
                    <i x-show="!copied" class="fas fa-copy"></i>
                    <i x-show="copied" x-cloak class="fas fa-check text-emerald-400"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <form action="{{ route('user.links.toggle-active', $link) }}" method="POST">
            @csrf
            <button class="px-3 py-2 text-sm rounded-xl border {{ $link->is_active ? 'border-green-200 text-green-700 bg-emerald-500/10 hover:bg-green-100' : 'border-red-200 text-red-700 bg-red-500/10 hover:bg-red-100' }}">
                <i class="fas {{ $link->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }} mr-1"></i>
                {{ $link->is_active ? 'Active' : 'Inactive' }}
            </button>
        </form>
        <a href="{{ route('user.links.qrcode', $link) }}" class="px-3 py-2 text-sm rounded-xl border border-white/10 text-white/60 hover:bg-white/5">
            <i class="fas fa-qrcode mr-1"></i> QR Code
        </a>
        <a href="{{ route('user.links.edit', $link) }}" class="px-3 py-2 text-sm rounded-xl border border-white/10 text-white/60 hover:bg-white/5">
            <i class="fas fa-edit mr-1"></i> Edit
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="glass rounded-2xl p-4">
        <div class="text-sm text-white/40 mb-1">Total Clicks</div>
        <div class="text-2xl font-bold text-white">{{ number_format($link->total_clicks) }}</div>
    </div>
    <div class="glass rounded-2xl p-4">
        <div class="text-sm text-white/40 mb-1">Unique Clicks</div>
        <div class="text-2xl font-bold text-white">{{ number_format($link->unique_clicks) }}</div>
    </div>
    <div class="glass rounded-2xl p-4">
        <div class="text-sm text-white/40 mb-1">Created</div>
        <div class="text-lg font-semibold text-white">{{ $link->created_at->format('M d, Y') }}</div>
    </div>
    <div class="glass rounded-2xl p-4">
        <div class="text-sm text-white/40 mb-1">Type</div>
        <div class="text-lg font-semibold text-white capitalize">{{ $link->type }}</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="glass rounded-2xl p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Clicks Over Time (30 Days)</h3>
        @if($clicksOverTime->isEmpty())
            <p class="text-white/30 text-sm text-center py-8">No click data yet</p>
        @else
            <div class="space-y-2">
                @php $maxClicks = $clicksOverTime->max('count') ?: 1; @endphp
                @foreach($clicksOverTime->takeLast(14) as $day)
                <div class="flex items-center gap-3 text-sm">
                    <span class="text-white/40 w-20 flex-shrink-0">{{ \Carbon\Carbon::parse($day->date)->format('M d') }}</span>
                    <div class="flex-1 bg-white/10 rounded-full h-5 overflow-hidden">
                        <div class="bg-purple-500/100 h-full rounded-full" style="width: {{ ($day->count / $maxClicks) * 100 }}%"></div>
                    </div>
                    <span class="text-white/60 font-medium w-10 text-right">{{ $day->count }}</span>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="glass rounded-2xl p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Top Referrers</h3>
        @if($topReferrers->isEmpty())
            <p class="text-white/30 text-sm text-center py-8">No referrer data yet</p>
        @else
            <div class="space-y-3">
                @foreach($topReferrers as $ref)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-white/60 truncate flex-1">{{ parse_url($ref->referrer, PHP_URL_HOST) ?: $ref->referrer }}</span>
                    <span class="text-white/40 font-medium ml-3">{{ $ref->count }}</span>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="glass rounded-2xl p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Browsers</h3>
        @if($browserStats->isEmpty())
            <p class="text-white/30 text-sm text-center py-4">No data</p>
        @else
            @php $totalBrowser = $browserStats->sum('count') ?: 1; @endphp
            <div class="space-y-3">
                @foreach($browserStats as $stat)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-white/60">{{ $stat->browser }}</span>
                        <span class="text-white/40">{{ round(($stat->count / $totalBrowser) * 100) }}%</span>
                    </div>
                    <div class="bg-white/10 rounded-full h-2">
                        <div class="bg-purple-500 h-full rounded-full" style="width: {{ ($stat->count / $totalBrowser) * 100 }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="glass rounded-2xl p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Operating Systems</h3>
        @if($osStats->isEmpty())
            <p class="text-white/30 text-sm text-center py-4">No data</p>
        @else
            @php $totalOS = $osStats->sum('count') ?: 1; @endphp
            <div class="space-y-3">
                @foreach($osStats as $stat)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-white/60">{{ $stat->os }}</span>
                        <span class="text-white/40">{{ round(($stat->count / $totalOS) * 100) }}%</span>
                    </div>
                    <div class="bg-white/10 rounded-full h-2">
                        <div class="bg-emerald-500/100 h-full rounded-full" style="width: {{ ($stat->count / $totalOS) * 100 }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="glass rounded-2xl p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Devices</h3>
        @if($deviceStats->isEmpty())
            <p class="text-white/30 text-sm text-center py-4">No data</p>
        @else
            @php $totalDevice = $deviceStats->sum('count') ?: 1; @endphp
            <div class="space-y-3">
                @foreach($deviceStats as $stat)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-white/60 capitalize">{{ $stat->device_type }}</span>
                        <span class="text-white/40">{{ round(($stat->count / $totalDevice) * 100) }}%</span>
                    </div>
                    <div class="bg-white/10 rounded-full h-2">
                        <div class="bg-purple-500 h-full rounded-full" style="width: {{ ($stat->count / $totalDevice) * 100 }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="glass rounded-2xl p-6">
    <h3 class="text-lg font-semibold text-white mb-4">Link Details</h3>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        @if($link->long_url)
        <div>
            <dt class="text-white/40">Destination URL</dt>
            <dd class="text-white truncate mt-1">{{ $link->long_url }}</dd>
        </div>
        @endif
        @if($link->project)
        <div>
            <dt class="text-white/40">Project</dt>
            <dd class="flex items-center gap-2 mt-1">
                <span class="w-3 h-3 rounded-full" style="background-color: {{ $link->project->color }}"></span>
                {{ $link->project->name }}
            </dd>
        </div>
        @endif
        @if($link->expires_at)
        <div>
            <dt class="text-white/40">Expires</dt>
            <dd class="text-white mt-1">{{ $link->expires_at->format('M d, Y H:i') }}</dd>
        </div>
        @endif
        <div>
            <dt class="text-white/40">Password Protected</dt>
            <dd class="text-white mt-1">{{ $link->is_password_protected ? 'Yes' : 'No' }}</dd>
        </div>
        @if($link->pixels->count())
        <div class="md:col-span-2">
            <dt class="text-white/40">Tracking Pixels</dt>
            <dd class="flex flex-wrap gap-2 mt-1">
                @foreach($link->pixels as $pixel)
                    <span class="bg-white/10 text-white/60 px-2 py-1 rounded text-xs">{{ $pixel->name }}</span>
                @endforeach
            </dd>
        </div>
        @endif
    </dl>
</div>
@endsection
