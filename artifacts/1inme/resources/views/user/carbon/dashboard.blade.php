@extends('user.layouts.app')
@section('title', 'Sustainability')
@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-8">
    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold flex items-center gap-3">
                <span class="inline-flex w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 items-center justify-center">
                    <i class="fa-solid fa-leaf"></i>
                </span>
                Sustainability
            </h1>
            <p class="mt-1" style="color: var(--text-muted);">Page-traffic CO₂ estimates and offset history for {{ $workspace->name }}.</p>
        </div>
        <a href="{{ route('public.carbon.methodology') }}" class="text-sm text-emerald-700 underline">Methodology</a>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="rounded-xl border p-5" style="background: var(--bg-card); border-color: var(--border-glass);">
            <div class="text-xs uppercase tracking-wider" style="color: var(--text-muted);">Estimated CO₂ (12mo)</div>
            <div class="mt-2 text-3xl font-bold">{{ number_format($totals['grams_co2'] / 1000, 2) }} <span class="text-base" style="color: var(--text-faint);">kg</span></div>
        </div>
        <div class="rounded-xl border p-5" style="background: var(--bg-card); border-color: var(--border-glass);">
            <div class="text-xs uppercase tracking-wider" style="color: var(--text-muted);">Offset</div>
            <div class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($totals['grams_offset'] / 1000, 2) }} <span class="text-base" style="color: var(--text-faint);">kg</span></div>
        </div>
        <div class="rounded-xl border p-5" style="background: var(--bg-card); border-color: var(--border-glass);">
            <div class="text-xs uppercase tracking-wider" style="color: var(--text-muted);">Spend</div>
            <div class="mt-2 text-3xl font-bold">{{ $totals['currency'] }} {{ number_format($totals['cost_minor'] / 100, 2) }}</div>
        </div>
        <div class="rounded-xl border p-5" style="background: var(--bg-card); border-color: var(--border-glass);">
            <div class="text-xs uppercase tracking-wider" style="color: var(--text-muted);">Certificates</div>
            <div class="mt-2 text-3xl font-bold">{{ $totals['certificates'] }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('user.carbon.workspace.update') }}" class="rounded-xl border p-5 space-y-4" style="background: var(--bg-card); border-color: var(--border-glass);">
        @csrf
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Workspace defaults</h2>
            <span class="text-xs" style="color: var(--text-muted);">Applied to new biolinks unless overridden.</span>
        </div>
        <div class="grid sm:grid-cols-4 gap-4">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="enabled" value="1" @checked($workspaceDefaults['enabled'])>
                Auto-offset enabled
            </label>
            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted);">Monthly cap (USD)</span>
                <input type="number" min="0" step="0.01" name="monthly_budget" value="{{ number_format(($workspaceDefaults['monthly_budget_minor'] ?? 0) / 100, 2, '.', '') }}" class="w-full border rounded px-3 py-2" style="background: var(--bg-glass-input); color: var(--text-primary); border-color: var(--border-glass);">
            </label>
            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted);">If cap is hit</span>
                <select name="fallback" class="w-full border rounded px-3 py-2" style="background: var(--bg-glass-input); color: var(--text-primary); border-color: var(--border-glass);">
                    <option value="pause" @selected($workspaceDefaults['fallback'] === 'pause')>Pause until next month</option>
                    <option value="partial" @selected($workspaceDefaults['fallback'] === 'partial')>Partial offset to budget</option>
                </select>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="badge_visible" value="1" @checked($workspaceDefaults['badge_visible'])>
                Show badge on biolinks
            </label>
        </div>
        <button class="px-4 py-2 rounded bg-emerald-600 text-white text-sm">Save defaults</button>
    </form>

    <div class="rounded-xl border overflow-hidden" style="background: var(--bg-card); border-color: var(--border-glass);">
        <div class="px-5 py-3 border-b flex items-center justify-between" style="border-color: var(--border-glass);">
            <h2 class="text-lg font-semibold">Per-biolink footprint</h2>
            <span class="text-xs" style="color: var(--text-muted);">Last 12 monthly snapshots</span>
        </div>
        <table class="w-full text-sm">
            <thead class="text-xs uppercase" style="background: var(--bg-glass); color: var(--text-muted);">
                <tr>
                    <th class="px-5 py-2 text-left">Biolink</th>
                    <th class="px-5 py-2 text-right">Page views</th>
                    <th class="px-5 py-2 text-right">CO₂ (g)</th>
                    <th class="px-5 py-2 text-right">Offset (g)</th>
                    <th class="px-5 py-2 text-left">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y">
            @forelse ($perLink as $row)
                <tr>
                    <td class="px-5 py-3">
                        <div class="font-medium">{{ optional($row['link'])->title ?? optional($row['link'])->alias }}</div>
                        <div class="text-xs" style="color: var(--text-muted);">/{{ optional($row['link'])->alias }}</div>
                    </td>
                    <td class="px-5 py-3 text-right">{{ number_format(optional($row['last_snapshot'])->page_views ?? 0) }}</td>
                    <td class="px-5 py-3 text-right">{{ number_format($row['grams_total'], 0) }}</td>
                    <td class="px-5 py-3 text-right text-emerald-700">{{ number_format($row['grams_offset'], 0) }}</td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full
                            {{ optional($row['last_snapshot'])->offset_status === 'purchased' ? 'bg-emerald-100 text-emerald-700' : '' }}
                            {{ optional($row['last_snapshot'])->offset_status === 'sandbox'   ? 'bg-amber-100 text-amber-800'    : '' }}
                            {{ optional($row['last_snapshot'])->offset_status === 'capped'    ? 'bg-orange-100 text-orange-800'  : '' }}
                            {{ in_array(optional($row['last_snapshot'])->offset_status, ['none','failed'], true) ? 'bg-gray-100 text-gray-700' : '' }}">
                            {{ ucfirst(optional($row['last_snapshot'])->offset_status ?? 'none') }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-8 text-center" style="color: var(--text-muted);">No snapshots yet — they're written monthly. Run <code>php artisan carbon:snapshot-monthly</code> to generate now.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="rounded-xl border overflow-hidden" style="background: var(--bg-card); border-color: var(--border-glass);">
        <div class="px-5 py-3 border-b" style="border-color: var(--border-glass);">
            <h2 class="text-lg font-semibold">Offset receipts</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="text-xs uppercase" style="background: var(--bg-glass); color: var(--text-muted);">
                <tr>
                    <th class="px-5 py-2 text-left">When</th>
                    <th class="px-5 py-2 text-left">Provider</th>
                    <th class="px-5 py-2 text-left">Project</th>
                    <th class="px-5 py-2 text-right">Grams</th>
                    <th class="px-5 py-2 text-right">Cost</th>
                    <th class="px-5 py-2 text-left">Certificate</th>
                </tr>
            </thead>
            <tbody class="divide-y">
            @forelse ($purchases as $p)
                <tr>
                    <td class="px-5 py-3">{{ optional($p->purchased_at)->format('Y-m-d') }}</td>
                    <td class="px-5 py-3">{{ ucfirst($p->provider) }}</td>
                    <td class="px-5 py-3">{{ $p->project_name ?? '—' }}</td>
                    <td class="px-5 py-3 text-right">{{ number_format($p->grams_offset, 0) }}</td>
                    <td class="px-5 py-3 text-right">{{ $p->currency }} {{ number_format($p->cost_minor / 100, 2) }}</td>
                    <td class="px-5 py-3">
                        @if ($p->certificate_url)
                            <a href="{{ $p->certificate_url }}" target="_blank" rel="noopener" class="text-emerald-700 underline">Download</a>
                        @else
                            <span class="text-xs" style="color: var(--text-faint);">{{ $p->status === 'sandbox' ? 'Sandbox' : 'Pending' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-8 text-center" style="color: var(--text-muted);">No offset purchases yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
