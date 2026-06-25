@if($recentClicks->isEmpty())
    <p class="text-sm text-center py-8" style="color: var(--text-faint);">No clicks yet</p>
@else
<div class="overflow-x-auto">
    <table class="w-full text-xs">
        <thead><tr class="text-[10px] uppercase tracking-wider" style="color: var(--text-faint);">
            <th class="text-left py-2 px-2 font-bold">When</th>
            <th class="text-left py-2 px-2 font-bold">IP</th>
            <th class="text-left py-2 px-2 font-bold">Location</th>
            <th class="text-left py-2 px-2 font-bold">Device</th>
            <th class="text-left py-2 px-2 font-bold">Channel</th>
            <th class="text-left py-2 px-2 font-bold">Browser</th>
            <th class="text-left py-2 px-2 font-bold">OS</th>
            <th class="text-left py-2 px-2 font-bold">Lang</th>
            <th class="text-left py-2 px-2 font-bold">Referrer</th>
            <th class="text-left py-2 px-2 font-bold">Block</th>
        </tr></thead>
        <tbody>
        @foreach($recentClicks as $c)
        <tr class="hover:bg-white/[0.02]" style="border-top: 1px solid var(--border-glass);">
            <td class="py-2 px-2 whitespace-nowrap" style="color: var(--text-muted);">{{ $c->clicked_at->format('M d, H:i:s') }}</td>
            <td class="py-2 px-2 font-mono" style="color: var(--text-muted);">{{ $c->ip_address }}</td>
            <td class="py-2 px-2" style="color: var(--text-muted);">{{ $c->city ? $c->city.', ' : '' }}{{ $c->country_code ?? '—' }}</td>
            <td class="py-2 px-2 capitalize" style="color: var(--text-muted);">{{ $c->device_type ?? '—' }}</td>
            <td class="py-2 px-2" style="color: var(--text-muted);">@if($c->channel)<span class="badge text-[10px]" style="background:rgba(56,189,248,0.10); color:#7dd3fc;" title="{{ $c->channel }}">{{ \App\Modules\Common\Services\ChannelClassifier::labelFor($c->channel) }}</span>@else<span style="color:var(--text-faint);">—</span>@endif</td>
            <td class="py-2 px-2" style="color: var(--text-muted);">{{ $c->browser ?? '—' }}</td>
            <td class="py-2 px-2" style="color: var(--text-muted);">{{ $c->os ?? '—' }}</td>
            <td class="py-2 px-2" style="color: var(--text-muted);">{{ $c->language ?? '—' }}</td>
            <td class="py-2 px-2 truncate max-w-xs" style="color: var(--text-faint);">{{ $c->referrer ? (parse_url($c->referrer, PHP_URL_HOST) ?: '—') : '—' }}</td>
            <td class="py-2 px-2" style="color: var(--text-muted);">@if($c->block_id)<span class="badge text-[10px]" style="background:rgba(61,107,255,0.08); color:#90acff;">{{ ($blockTypes[$c->block_type]['label'] ?? $c->block_type) }}</span>@else<span style="color:var(--text-faint);">page</span>@endif</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@if($recentClicks->hasPages())
<div class="mt-4 flex items-center justify-between flex-wrap gap-3">
    <div class="text-xs" style="color: var(--text-faint);">
        Showing {{ $recentClicks->firstItem() }}–{{ $recentClicks->lastItem() }} of {{ number_format($recentClicks->total()) }}
    </div>
    <nav class="flex items-center gap-1.5">
        @if($recentClicks->onFirstPage())
            <span class="px-3 py-1.5 rounded-lg text-xs font-medium opacity-40 cursor-not-allowed" style="background: var(--bg-glass-input); color: var(--text-faint); border: 1px solid var(--border-glass);">‹ Prev</span>
        @else
            <a href="{{ $recentClicks->previousPageUrl() }}" data-rc-page="{{ $recentClicks->currentPage()-1 }}" class="rc-page-btn px-3 py-1.5 rounded-lg text-xs font-medium hover:opacity-80 transition" style="background: var(--bg-glass-input); color: var(--text-primary); border: 1px solid var(--border-glass);">‹ Prev</a>
        @endif

        @php
            $current = $recentClicks->currentPage();
            $last = $recentClicks->lastPage();
            $start = max(1, $current - 2);
            $end = min($last, $current + 2);
        @endphp

        @if($start > 1)
            <a href="{{ $recentClicks->url(1) }}" data-rc-page="1" class="rc-page-btn px-3 py-1.5 rounded-lg text-xs font-medium hover:opacity-80 transition" style="background: var(--bg-glass-input); color: var(--text-primary); border: 1px solid var(--border-glass);">1</a>
            @if($start > 2)<span class="px-1 text-xs" style="color: var(--text-faint);">…</span>@endif
        @endif

        @for($i = $start; $i <= $end; $i++)
            @if($i == $current)
                <span class="px-3 py-1.5 rounded-lg text-xs font-bold" style="background: linear-gradient(135deg, #5c83ff, #90acff); color: white; border: 1px solid rgba(61,107,255,0.4);">{{ $i }}</span>
            @else
                <a href="{{ $recentClicks->url($i) }}" data-rc-page="{{ $i }}" class="rc-page-btn px-3 py-1.5 rounded-lg text-xs font-medium hover:opacity-80 transition" style="background: var(--bg-glass-input); color: var(--text-primary); border: 1px solid var(--border-glass);">{{ $i }}</a>
            @endif
        @endfor

        @if($end < $last)
            @if($end < $last - 1)<span class="px-1 text-xs" style="color: var(--text-faint);">…</span>@endif
            <a href="{{ $recentClicks->url($last) }}" data-rc-page="{{ $last }}" class="rc-page-btn px-3 py-1.5 rounded-lg text-xs font-medium hover:opacity-80 transition" style="background: var(--bg-glass-input); color: var(--text-primary); border: 1px solid var(--border-glass);">{{ $last }}</a>
        @endif

        @if($recentClicks->hasMorePages())
            <a href="{{ $recentClicks->nextPageUrl() }}" data-rc-page="{{ $recentClicks->currentPage()+1 }}" class="rc-page-btn px-3 py-1.5 rounded-lg text-xs font-medium hover:opacity-80 transition" style="background: var(--bg-glass-input); color: var(--text-primary); border: 1px solid var(--border-glass);">Next ›</a>
        @else
            <span class="px-3 py-1.5 rounded-lg text-xs font-medium opacity-40 cursor-not-allowed" style="background: var(--bg-glass-input); color: var(--text-faint); border: 1px solid var(--border-glass);">Next ›</span>
        @endif
    </nav>
</div>
@endif
@endif
