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
    .range-date-input {
        background: var(--bg-glass-input, var(--bg-glass));
        border: 1px solid var(--border-glass);
        color: var(--text-primary);
        border-radius: 9px;
        padding: 6px 8px;
        font-size: 11px;
        font-weight: 600;
        color-scheme: light dark;
    }
    .view-toggle {
        display: inline-flex;
        border: 1px solid var(--border-glass);
        border-radius: 10px;
        overflow: hidden;
    }
    .view-toggle button {
        padding: 6px 11px;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        background: var(--bg-glass);
    }
    .view-toggle button.active {
        background: linear-gradient(135deg, #3d6bff, #5c83ff);
        color: #fff;
    }
</style>
@endpush

@php
    $heroActions = [
        ['label' => 'Back to Overview', 'url' => route('user.links.show', $link), 'icon' => 'fa-arrow-left', 'class' => 'btn-ghost'],
    ];
    if (workspace_owner()?->getPlanFeature('analytics_export', true)) {
        $heroActions[] = ['label' => 'Export CSV', 'url' => route('user.links.visitors.export', array_merge(['link' => $link], request()->query())), 'icon' => 'fa-download', 'class' => 'btn-primary'];
    } else {
        $heroActions[] = ['label' => 'Export CSV', 'url' => route('user.upgrade'), 'icon' => 'fa-lock', 'class' => 'btn-ghost', 'title' => 'Exporting visitor data is a paid feature. Upgrade your plan to download CSV exports.'];
    }
    if ($link->type === 'biolink') {
        $heroActions[] = ['label' => 'Edit Blocks', 'url' => route('user.links.blocks.editor', $link), 'icon' => 'fa-th-large', 'class' => 'btn-primary'];
    }
    $heroActions[] = ['label' => 'QR', 'url' => route('user.qr-codes.create', ['link_id' => $link->id]), 'icon' => 'fa-qrcode', 'class' => 'btn-ghost'];
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
@include('user.partials.visitor-range-control', ['buildUrl' => $buildUrl, 'period' => $period, 'startDate' => $startDate, 'endDate' => $endDate])

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

    @if(!empty($qrConnect) || $dailySeries->isNotEmpty())
        {{-- Shared chart runtime — loaded once for both the QR Connect funnel
             chart and the daily visitors chart below. --}}
        <script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
        @vite(['resources/js/analytics-charts.js'])
    @endif

    @if(!empty($qrConnect))
        {{-- QR Connect panel (Task #6685) — event links only. Scan-to-connect
             funnel attributed to the event's Connect QR, within the range. --}}
        <div class="rounded-2xl border p-5 mb-6" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <div>
                    <h2 class="font-bold" style="color: var(--text-primary);"><i class="fas fa-qrcode mr-1.5 text-blue-500"></i> QR Connect</h2>
                    <p class="text-xs mt-0.5" style="color: var(--text-muted);">Scans of your Connect QR and what they turned into.</p>
                </div>
                <a href="{{ route('user.links.connect-qr', $link) }}" class="text-sm px-3 py-1.5 rounded-lg border font-semibold" style="border-color: var(--border-soft); color: var(--text-primary);">Get the Connect QR →</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                <div class="rounded-xl p-4 border" style="border-color: var(--border-soft);">
                    <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">Scans</p>
                    <p class="text-2xl font-extrabold mt-1" style="color: var(--text-primary);">{{ number_format($qrConnect['scans']) }}</p>
                </div>
                <div class="rounded-xl p-4 border" style="border-color: var(--border-soft);">
                    <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">New signups</p>
                    <p class="text-2xl font-extrabold mt-1 text-emerald-600">{{ number_format($qrConnect['new_users']) }}</p>
                </div>
                <div class="rounded-xl p-4 border" style="border-color: var(--border-soft);">
                    <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">Existing users</p>
                    <p class="text-2xl font-extrabold mt-1 text-blue-600">{{ number_format($qrConnect['existing']) }}</p>
                </div>
                <div class="rounded-xl p-4 border" style="border-color: var(--border-soft);">
                    <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">RSVPs</p>
                    <p class="text-2xl font-extrabold mt-1" style="color: var(--text-primary);">{{ number_format($qrConnect['rsvps']) }}</p>
                </div>
                <div class="rounded-xl p-4 border" style="border-color: var(--border-soft);">
                    <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">New followers</p>
                    <p class="text-2xl font-extrabold mt-1 text-amber-600">{{ number_format($qrConnect['follows']) }}</p>
                </div>
                <div class="rounded-xl p-4 border" style="border-color: var(--border-soft);">
                    <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">Conversion</p>
                    <p class="text-2xl font-extrabold mt-1 text-violet-600">{{ $qrConnect['conversion_pct'] !== null ? $qrConnect['conversion_pct'] . '%' : '—' }}</p>
                    <p class="text-[10px] mt-0.5" style="color: var(--text-faint);">Scans → connects</p>
                </div>
            </div>

            @if(($qrConnect['daily'] ?? collect())->isNotEmpty())
                {{-- Daily funnel trend (Task #6689) — scans vs completed connects per day. --}}
                <div class="mt-5">
                    <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide" style="color: var(--text-faint);">Daily scans vs connects</span>
                        <div class="view-toggle" data-chart-toggle="qrConnectChart">
                            <button type="button" class="active" data-view="bar">Bar</button>
                            <button type="button" data-view="line">Line</button>
                        </div>
                    </div>
                    <div style="height: 200px;"><canvas id="qrConnectChart"></canvas></div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const chart = AnalyticsCharts.createTrendChart('qrConnectChart',
                            @json($qrConnect['daily']->pluck('d')),
                            [
                                { label: 'Scans', data: @json($qrConnect['daily']->pluck('scans')), color: '#3d6bff' },
                                { label: 'Connects', data: @json($qrConnect['daily']->pluck('connects')), color: '#10b981' },
                            ],
                            { defaultView: 'bar' }
                        );
                        const toggle = document.querySelector('[data-chart-toggle="qrConnectChart"]');
                        if (toggle && chart) {
                            toggle.querySelectorAll('button').forEach((btn) => {
                                btn.addEventListener('click', () => {
                                    toggle.querySelectorAll('button').forEach((b) => b.classList.remove('active'));
                                    btn.classList.add('active');
                                    AnalyticsCharts.setTrendView(chart, btn.dataset.view);
                                });
                            });
                        }
                    });
                </script>
            @endif
        </div>
    @endif

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

    @if($dailySeries->isNotEmpty())
        <div class="rounded-2xl border p-5 mb-6" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <div>
                    <h2 class="font-bold" style="color: var(--text-primary);">Daily uniques &amp; returning rate</h2>
                    <span class="text-xs" style="color: var(--text-faint);">% of daily uniques who had visited before</span>
                </div>
                <div class="view-toggle" data-chart-toggle="visitorChart">
                    <button type="button" class="active" data-view="line">Line</button>
                    <button type="button" data-view="bar">Bar</button>
                    <button type="button" data-view="area">Area</button>
                </div>
            </div>
            <div style="height: 240px;"><canvas id="visitorChart"></canvas></div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const labels = @json($dailySeries->pluck('d'));
                const visitors = @json($dailySeries->pluck('visitors'));
                const returningPct = @json($dailySeries->pluck('returning_pct'));

                const chart = AnalyticsCharts.createTrendChart('visitorChart', labels, [
                    { label: 'Daily uniques', data: visitors, color: '#3d6bff' },
                    { label: 'Returning %', data: returningPct, color: '#f59e0b' },
                ]);

                const toggle = document.querySelector('[data-chart-toggle="visitorChart"]');
                if (toggle && chart) {
                    toggle.querySelectorAll('button').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            toggle.querySelectorAll('button').forEach((b) => b.classList.remove('active'));
                            btn.classList.add('active');
                            AnalyticsCharts.setTrendView(chart, btn.dataset.view);
                        });
                    });
                }
            });
        </script>
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
                                        <img src="{{ \App\Support\PublicStorageUrl::resolve($row->avatar) }}" class="w-7 h-7 rounded-full object-cover"/>
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
