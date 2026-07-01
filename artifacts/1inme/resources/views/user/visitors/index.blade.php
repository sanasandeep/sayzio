@extends('user.layouts.app')
@section('title', 'Visitor Insights · ' . ($link->title ?: $link->alias))

@section('content')
@php
    $qs = request()->query();
    $buildUrl = fn($overrides = []) => route('user.links.visitors', $link) . '?' . http_build_query(array_merge($qs, $overrides));
@endphp

@push('styles')
<style>
    /* Re-use the same visual language as show.blade.php / followers.blade.php
       so the Visitor Insights tab feels like part of the same dashboard. */
    .period-bar {
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        border-radius: 18px;
        padding: 10px 14px;
        backdrop-filter: blur(20px);
    }
    .pill {
        padding: 7px 13px;
        border-radius: 11px;
        font-size: 11px;
        font-weight: 600;
        transition: all .2s ease;
        color: var(--text-muted);
    }
    .pill:hover { background: var(--bg-glass-hover); color: var(--text-primary); transform: translateY(-1px); }
    .pill-active {
        background: linear-gradient(135deg, #3d6bff, #5c83ff);
        color: #fff !important;
        box-shadow: 0 6px 18px rgba(61,107,255,0.4);
    }
</style>
@endpush

@php
    $heroActions = [
        ['label' => 'Back to Overview', 'url' => route('user.links.show', $link), 'icon' => 'fa-arrow-left', 'class' => 'btn-ghost'],
    ];
    if ($link->type === 'biolink') {
        $heroActions[] = ['label' => 'Edit Blocks', 'url' => route('user.links.blocks.editor', $link), 'icon' => 'fa-th-large', 'class' => 'btn-primary'];
    }
    $heroActions[] = ['label' => 'QR', 'url' => route('user.links.qrcode', $link), 'icon' => 'fa-qrcode', 'class' => 'btn-ghost'];
    $heroActions[] = ['label' => 'Edit', 'url' => route('user.links.edit', $link), 'icon' => 'fa-edit', 'class' => 'btn-ghost'];
@endphp
@include('user.partials.page-hero', [
    'title'    => ($link->title ?: $link->alias) . ' · Visitor Insights',
    'icon'     => 'fa-fingerprint',
    'url'      => $link->getShortUrl(),
    'chips'    => [
        ['icon' => 'fa-circle text-emerald-400', 'text' => ($link->is_active ?? true) ? 'Active' : 'Inactive'],
        ['icon' => $link->type === 'biolink' ? 'fa-th-large' : 'fa-link', 'text' => \App\Modules\User\Models\Link::typeLabel($link->type)],
        ['icon' => 'fa-calendar', 'text' => $startDate->format('M d') . ' – ' . $endDate->format('M d, Y')],
    ],
    'back'     => route('user.links.show', $link),
    'actions'  => $heroActions,
])

@include('user.links.partials.analytics-tabs', ['link' => $link, 'active' => 'visitors'])

{{-- ===================== PERIOD CONTROLS ===================== --}}
<div class="period-bar mb-6">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);"><i class="fas fa-clock text-blue-400"></i> Period</span>
        @foreach(['today'=>'Today','7d'=>'7d','30d'=>'30d','90d'=>'90d','year'=>'Year','all'=>'All'] as $k=>$lbl)
            <a href="{{ $buildUrl(['period'=>$k]) }}" class="pill {{ ($period ?? '30d')===$k ? 'pill-active' : '' }}">{{ $lbl }}</a>
        @endforeach
    </div>
</div>

    <div class="rounded-2xl border p-5 mb-6" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
        <div class="flex items-center justify-between mb-3">
            <div>
                <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">Times written to NFC</p>
                <p class="text-3xl font-extrabold mt-1" style="color: var(--text-primary);">{{ number_format($nfcCount ?? 0) }}</p>
                <p class="text-xs mt-1" style="color: var(--text-muted);">From the Sayzio mobile app's NFC writer.</p>
            </div>
            <a href="{{ route('user.links.nfc-writes', $link) }}" class="text-sm px-3 py-1.5 rounded-lg border font-semibold" style="border-color: var(--border-soft); color: var(--text-primary);">View full history →</a>
        </div>
        @if(($nfcRecent ?? collect())->isNotEmpty())
            <ul class="divide-y mt-3" style="border-color: var(--border-soft);">
                @foreach($nfcRecent as $w)
                    <li class="py-2 flex items-center justify-between text-xs" style="color: var(--text-muted);">
                        <span class="truncate" title="{{ $w->written_url }}">{{ $w->label ?: $w->written_url }}</span>
                        <span class="ml-3 whitespace-nowrap" style="color: var(--text-faint);">
                            {{ ucfirst($w->platform ?? $w->source ?? 'mobile') }} · {{ ($w->written_at ?? $w->created_at)?->diffForHumans() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="grid grid-cols-3 gap-3 mb-6">
        <div class="rounded-xl p-4 border" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
            <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">Unique visitors</p>
            <p class="text-2xl font-extrabold mt-1" style="color: var(--text-primary);">{{ number_format($totalVisitors) }}</p>
        </div>
        <div class="rounded-xl p-4 border" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
            <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">New</p>
            <p class="text-2xl font-extrabold mt-1 text-emerald-600">{{ number_format($newCount) }}</p>
        </div>
        <div class="rounded-xl p-4 border" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
            <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">Returning</p>
            <p class="text-2xl font-extrabold mt-1 text-blue-600">{{ number_format($returningCount) }}</p>
        </div>
    </div>

    {{-- AR Business Card breakdown. Counts visitors who came through
         /ar/{alias} (page_sessions.source = 'ar') and block taps inside
         AR (link_clicks.source = 'ar'), plus the wider source breakdown
         so creators can compare AR vs web/social pulls at a glance. --}}
    @if($link->ar_enabled || ($arSessions ?? 0) > 0 || ($arClicks ?? 0) > 0)
    <div class="rounded-2xl border p-5 mb-6" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-vr-cardboard mr-1.5" style="color:#90acff;"></i> AR Business Card
                </h2>
                <p class="text-xs mt-0.5" style="color: var(--text-faint);">
                    Scans, block taps and source share for {{ $startDate->format('M d') }} – {{ $endDate->format('M d, Y') }}.
                </p>
            </div>
            @if($link->ar_enabled)
                <a href="{{ route('ar.card.view', $link->alias) }}?preview=1" target="_blank" class="text-sm px-3 py-1.5 rounded-lg border font-semibold" style="border-color: var(--border-soft); color: var(--text-primary);">
                    Open AR card →
                </a>
            @endif
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <div class="rounded-xl p-3 border" style="background: var(--bg-glass-input); border-color: var(--border-soft);">
                <p class="text-[11px] uppercase tracking-wide" style="color: var(--text-faint);">AR scans</p>
                <p class="text-2xl font-extrabold mt-1" style="color: var(--text-primary);">{{ number_format($arSessions ?? 0) }}</p>
                <p class="text-[11px] mt-0.5" style="color: var(--text-muted);">Sessions opened from a QR or NFC scan into /ar/.</p>
            </div>
            <div class="rounded-xl p-3 border" style="background: var(--bg-glass-input); border-color: var(--border-soft);">
                <p class="text-[11px] uppercase tracking-wide" style="color: var(--text-faint);">Block taps in AR</p>
                <p class="text-2xl font-extrabold mt-1 text-blue-600">{{ number_format($arClicks ?? 0) }}</p>
                <p class="text-[11px] mt-0.5" style="color: var(--text-muted);">Hotspot or list taps attributed to the AR surface.</p>
            </div>
            <div class="rounded-xl p-3 border md:col-span-1 col-span-2" style="background: var(--bg-glass-input); border-color: var(--border-soft);">
                <p class="text-[11px] uppercase tracking-wide" style="color: var(--text-faint);">Tap-through rate</p>
                <p class="text-2xl font-extrabold mt-1 text-emerald-600">
                    @php $rate = ($arSessions ?? 0) > 0 ? round((($arClicks ?? 0) / $arSessions) * 100, 1) : 0; @endphp
                    {{ $rate }}%
                </p>
                <p class="text-[11px] mt-0.5" style="color: var(--text-muted);">Block taps per AR scan.</p>
            </div>
        </div>
        @if(!empty($sourceBreakdown) && count($sourceBreakdown) > 0)
            <div class="mt-4">
                <p class="text-[11px] uppercase tracking-wide mb-2" style="color: var(--text-faint);">Click source share</p>
                <ul class="space-y-1.5">
                    @php $srcMax = max(1, collect($sourceBreakdown)->max('n')); @endphp
                    @foreach($sourceBreakdown as $row)
                        <li class="flex items-center gap-3 text-xs">
                            <span class="w-16 font-semibold uppercase tracking-wide" style="color: {{ $row->src === 'ar' ? '#90acff' : 'var(--text-muted)' }};">{{ $row->src }}</span>
                            <span class="flex-1 h-2 rounded-full overflow-hidden" style="background: var(--border-soft);">
                                <span class="block h-full" style="width: {{ round(($row->n / $srcMax) * 100) }}%; background: {{ $row->src === 'ar' ? 'linear-gradient(90deg,#90acff,#67e8f9)' : 'var(--text-muted)' }};"></span>
                            </span>
                            <span class="w-16 text-right tabular-nums" style="color: var(--text-primary);">{{ number_format($row->n) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    @endif

    @if($dailySeries->isNotEmpty())
        <div class="rounded-2xl border p-5 mb-6" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold" style="color: var(--text-primary);">Returning visitor rate</h2>
                <span class="text-xs" style="color: var(--text-faint);">% of daily uniques who had visited before</span>
            </div>
            @php
                $maxV = max(1, $dailySeries->max('visitors'));
                $w = 720; $h = 140; $n = $dailySeries->count();
                $stepX = $n > 1 ? ($w - 24) / ($n - 1) : 0;
                $pts = $dailySeries->values()->map(function($r,$i) use ($stepX,$h){
                    return [12 + $i*$stepX, $h - 10 - ($r->returning_pct/100)*($h - 30)];
                });
                $bars = $dailySeries->values();
            @endphp
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full" preserveAspectRatio="none">
                @foreach($bars as $i => $r)
                    @php $bh = ($r->visitors / $maxV) * ($h - 30); $bx = 12 + $i*$stepX - 4; @endphp
                    <rect x="{{ $bx }}" y="{{ $h - 10 - $bh }}" width="8" height="{{ max(1,$bh) }}" fill="#bccfff" opacity="0.55" rx="2"/>
                @endforeach
                <polyline fill="none" stroke="#3d6bff" stroke-width="2"
                    points="{{ $pts->map(fn($p)=>$p[0].','.$p[1])->join(' ') }}" />
                @foreach($pts as $i => $p)
                    <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="3" fill="#3d6bff">
                        <title>{{ $bars[$i]->d }}: {{ $bars[$i]->returning_pct }}% returning ({{ $bars[$i]->returning }}/{{ $bars[$i]->visitors }})</title>
                    </circle>
                @endforeach
            </svg>
            <div class="flex items-center gap-4 mt-2 text-xs" style="color: var(--text-faint);">
                <span><span class="inline-block w-3 h-3 rounded bg-blue-200 mr-1"></span>Daily uniques</span>
                <span><span class="inline-block w-3 h-3 rounded-full bg-blue-600 mr-1"></span>Returning %</span>
            </div>
        </div>
    @endif

    <div class="rounded-2xl border p-5" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
        <h2 class="font-bold mb-4" style="color: var(--text-primary);">Identified visitors ({{ $identified->count() }})</h2>
        @if($identified->isEmpty())
            <p class="text-sm" style="color: var(--text-muted);">No visitors have signed in on this Link in Bio yet. When viewers opt in via the sign-in card on your Link in Bio, they'll appear here.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase" style="color: var(--text-faint);">
                        <th class="py-2 pr-4">Visitor</th><th class="py-2 pr-4">Email</th>
                        <th class="py-2 pr-4">Visits</th><th class="py-2 pr-4">First seen</th>
                        <th class="py-2 pr-4">Last seen</th><th class="py-2">Status</th>
                    </tr></thead>
                    <tbody>
                        @foreach($identified as $row)
                            <tr class="border-t" style="border-color: var(--border-soft);">
                                <td class="py-2 pr-4 flex items-center gap-2">
                                    @if($row->avatar)
                                        <img src="{{ $row->avatar }}" class="w-7 h-7 rounded-full object-cover"/>
                                    @else
                                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center text-xs font-bold">{{ strtoupper(substr($row->name ?? '?', 0, 1)) }}</div>
                                    @endif
                                    <span style="color: var(--text-primary);">{{ $row->name }}</span>
                                </td>
                                <td class="py-2 pr-4" style="color: var(--text-muted);">{{ $row->email }}</td>
                                <td class="py-2 pr-4 font-semibold" style="color: var(--text-primary);">{{ $row->visit_count }}</td>
                                <td class="py-2 pr-4 text-xs" style="color: var(--text-faint);">{{ \Carbon\Carbon::parse($row->first_seen)->diffForHumans() }}</td>
                                <td class="py-2 pr-4 text-xs" style="color: var(--text-faint);">{{ \Carbon\Carbon::parse($row->last_seen)->diffForHumans() }}</td>
                                <td class="py-2">
                                    @if($followerSet->has($row->id))
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">Follower</span>
                                    @else
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background: var(--bg-glass-light); color: var(--text-muted);">Visitor</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
