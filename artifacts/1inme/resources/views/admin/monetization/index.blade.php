@extends('admin.layouts.app')
@section('title', 'Monetization Overview')
@section('page-title', 'Monetization Overview')

@section('content')
@php use App\Services\PricingResolver; @endphp

<form method="GET" class="flex flex-wrap items-end gap-3 mb-5">
    <div>
        <label class="text-[10px] uppercase tracking-wider text-white/40 block mb-1 ak-note">Period</label>
        <select name="period" class="bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-white text-sm ak-strong ak-input">
            @foreach($periods as $key => $label)
                <option value="{{ $key }}" {{ $period === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button class="px-4 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Apply</button>
    <span class="text-xs text-white/40 ak-note">
        Applies to the plan-profit section. AI burn vs top-up always compares this month with last month.
    </span>
    <a href="{{ route('admin.coin-packages.index') }}" class="text-xs text-blue-300 hover:underline ml-auto ak-blue">Coin packages →</a>
    <a href="{{ route('admin.coin-packages.allocations') }}" class="text-xs text-blue-300 hover:underline ak-blue">Allocations →</a>
    <a href="{{ route('admin.ai-usage.index') }}" class="text-xs text-blue-300 hover:underline ak-blue">AI usage →</a>
</form>

{{-- ── Section 1: coin packages vs AI credits ─────────────────── --}}
<div class="glass rounded-2xl border border-white/10 p-6 mb-6">
    <div class="flex items-start justify-between gap-3">
        <h3 class="font-semibold text-white mb-1 ak-strong"><i class="fas fa-coins text-yellow-400 mr-1"></i> Coin packages vs AI credits</h3>
        @if(!empty($packages))
            <button type="button" onclick="monetizationCsvExport('packages')" class="shrink-0 bg-white/5 hover:bg-white/10 text-white border border-white/10 px-3 py-1.5 rounded-lg text-xs font-medium transition-all ak-strong">
                <i class="fas fa-download mr-1 text-white/50"></i> Export CSV
            </button>
        @endif
    </div>
    <p class="text-xs text-white/40 mb-4 ak-note">
        Purchasing power at today's live AI rates:
        chat on <span class="text-white/60 ak-muted">{{ $aiRates['chat_model'] ?? 'n/a' }}</span>
        ({{ rtrim(rtrim(number_format($aiRates['chat_blended_per_1k'], 3), '0'), '.') }} coins/1k tokens blended, 1:3 in:out mix),
        artistic QR {{ $aiRates['qr_coins'] }} coins, TTS {{ $aiRates['tts_per_1k_chars'] }} coins/1k chars,
        STT {{ $aiRates['stt_per_minute'] }} coins/min, brand asset {{ $aiRates['brand_asset_coins'] }} coins.
    </p>

    @if(empty($packages))
        <p class="text-sm text-white/40 py-4 ak-note">No active coin packages.</p>
    @else
        <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider ak-note">
                <th class="text-left py-2">Package</th>
                <th class="text-right">Coins (base + bonus)</th>
                <th class="text-right">Price</th>
                <th class="text-right">Per coin</th>
                <th class="text-right">API budget</th>
                <th class="text-right">≈ Chat tokens</th>
                <th class="text-right">≈ Artistic QRs</th>
                <th class="text-right">≈ TTS chars</th>
                <th class="text-right">≈ STT min</th>
            </tr></thead>
            <tbody>
            @foreach($packages as $p)
                <tr class="border-t border-white/5 align-top">
                    <td class="py-2 text-white ak-strong">{{ $p['name'] }}</td>
                    <td class="text-right text-white/70 ak-strong">
                        {{ number_format($p['total_coins']) }}
                        <span class="text-[10px] text-white/30 ak-note">({{ number_format($p['coin_amount']) }} + {{ number_format($p['bonus_coins']) }})</span>
                    </td>
                    <td class="text-right text-white/70 ak-strong">
                        @forelse($p['prices'] as $cur => $pr)
                            <div>{{ PricingResolver::money($pr['amount_minor'], $cur) }}</div>
                        @empty
                            <span class="text-white/30 ak-note">—</span>
                        @endforelse
                    </td>
                    <td class="text-right text-white/70 ak-strong">
                        @forelse($p['prices'] as $cur => $pr)
                            <div>{{ $pr['per_coin_minor'] !== null ? PricingResolver::money((int) round($pr['per_coin_minor']), $cur) : '—' }}<span class="text-[10px] text-white/30 ak-note">/coin</span></div>
                        @empty
                            <span class="text-white/30 ak-note">—</span>
                        @endforelse
                    </td>
                    <td class="text-right text-white/50 text-xs ak-muted">{{ $p['api_budget_pct'] }}% / {{ $p['margin_pct'] }}%</td>
                    <td class="text-right text-sky-300">{{ $p['buys']['chat_tokens'] !== null ? number_format($p['buys']['chat_tokens']) : '—' }}</td>
                    <td class="text-right text-fuchsia-300">{{ $p['buys']['qr_generations'] !== null ? number_format($p['buys']['qr_generations']) : '—' }}</td>
                    <td class="text-right text-emerald-300 ak-green">{{ $p['buys']['tts_chars'] !== null ? number_format($p['buys']['tts_chars']) : '—' }}</td>
                    <td class="text-right text-amber-300">{{ $p['buys']['stt_minutes'] !== null ? number_format($p['buys']['stt_minutes'], 1) : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <p class="text-[10px] text-white/30 mt-3 ak-note">Lower price-per-coin = better customer value. "API budget / margin" is the internal split applied to each purchase, never user-facing.</p>
    @endif
</div>

{{-- ── Section 2: AI credit spend, burn vs top-up ─────────────── --}}
<div class="glass rounded-2xl border border-white/10 p-6 mb-6">
    <div class="flex items-start justify-between gap-3">
        <h3 class="font-semibold text-white mb-1 ak-strong"><i class="fas fa-fire text-red-400 mr-1"></i> AI coin burn vs top-up</h3>
        <button type="button" onclick="monetizationCsvExport('aiSpend')" class="shrink-0 bg-white/5 hover:bg-white/10 text-white border border-white/10 px-3 py-1.5 rounded-lg text-xs font-medium transition-all ak-strong">
            <i class="fas fa-download mr-1 text-white/50"></i> Export CSV
        </button>
    </div>
    <p class="text-xs text-white/40 mb-4 ak-note">This month vs last month.</p>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <div>
            <p class="text-[10px] uppercase text-white/40 ak-note">AI coins spent (this month)</p>
            <p class="text-xl font-bold text-red-300 ak-red">{{ number_format($aiSpend['totals']['ai_spent']['this']) }}</p>
            <p class="text-[10px] text-white/30 ak-note">last month: {{ number_format($aiSpend['totals']['ai_spent']['last']) }}</p>
        </div>
        <div>
            <p class="text-[10px] uppercase text-white/40 ak-note">Coins purchased (this month)</p>
            <p class="text-xl font-bold text-emerald-300 ak-green">{{ number_format($aiSpend['totals']['coins_purchased']['this']) }}</p>
            <p class="text-[10px] text-white/30 ak-note">last month: {{ number_format($aiSpend['totals']['coins_purchased']['last']) }}</p>
        </div>
        @forelse($aiSpend['purchase_money'] as $cur => $m)
            <div>
                <p class="text-[10px] uppercase text-white/40 ak-note">Top-up revenue {{ $cur }} (this month)</p>
                <p class="text-xl font-bold text-white ak-strong">{{ PricingResolver::money($m['this'], $cur) }}</p>
                <p class="text-[10px] text-white/30 ak-note">last month: {{ PricingResolver::money($m['last'], $cur) }}</p>
            </div>
        @empty
            <div>
                <p class="text-[10px] uppercase text-white/40 ak-note">Top-up revenue</p>
                <p class="text-xl font-bold text-white/40 ak-note">—</p>
                <p class="text-[10px] text-white/30 ak-note">no coin purchases in the last two months</p>
            </div>
        @endforelse
    </div>

    @if(empty($aiSpend['features']))
        <p class="text-sm text-white/40 py-2 ak-note">No AI coin spend in the last two months.</p>
    @else
        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider ak-note">
                <th class="text-left py-2">Feature</th>
                <th class="text-right">Calls (this month)</th>
                <th class="text-right">Coins this month</th>
                <th class="text-right">Coins last month</th>
                <th class="text-right">Δ</th>
            </tr></thead>
            <tbody>
            @foreach($aiSpend['features'] as $f)
                @php $delta = (int) $f->this_month - (int) $f->last_month; @endphp
                <tr class="border-t border-white/5">
                    <td class="py-2 text-white ak-strong">
                        {{ \App\Services\AI\AiFeatureCatalog::featureLabel($f->feature) }}
                        <span class="text-[10px] text-white/30 ml-1 ak-note">{{ $f->feature }}</span>
                    </td>
                    <td class="text-right text-white/70 ak-strong">{{ number_format($f->calls_this) }}</td>
                    <td class="text-right text-red-300 font-semibold ak-red">{{ number_format($f->this_month) }}</td>
                    <td class="text-right text-white/50 ak-muted">{{ number_format($f->last_month) }}</td>
                    <td class="text-right {{ $delta > 0 ? 'text-red-300 ak-red' : 'text-emerald-300 ak-green' }}">{{ $delta > 0 ? '+' : '' }}{{ number_format($delta) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ── Section 2b: month-by-month trend ───────────────────────── --}}
<div class="glass rounded-2xl border border-white/10 p-6 mb-6">
    <h3 class="font-semibold text-white mb-1 ak-strong"><i class="fas fa-chart-line text-sky-400 mr-1"></i> Monthly trend</h3>
    <p class="text-xs text-white/40 mb-4 ak-note">
        Last {{ count($trend['months']) }} months (oldest first, current month included). Coin figures are coins; revenue is per currency, never mixed. Subscription revenue counts paid invoices by paid date.
    </p>

    @php
        $trendMax = max(1, collect($trend['months'])->max(fn ($m) => max($m['ai_coins_spent'], $m['coins_purchased'])));
        $hasTrendData = collect($trend['months'])->contains(fn ($m) =>
            $m['ai_coins_spent'] > 0 || $m['coins_purchased'] > 0 || !empty($m['topup_revenue']) || !empty($m['subscription_revenue']));
    @endphp

    @if(!$hasTrendData)
        <p class="text-sm text-white/40 py-2 ak-note">No monetization activity in the last {{ count($trend['months']) }} months.</p>
    @else
        {{-- Mini bar chart: AI burn vs coins purchased per month --}}
        <div class="flex items-end gap-2 h-28 mb-2 overflow-x-auto pb-1" aria-hidden="true">
            @foreach($trend['months'] as $m)
                <div class="flex-1 min-w-[34px] flex items-end justify-center gap-1 h-full">
                    <div class="w-2.5 rounded-t bg-red-400/70" style="height: {{ max(2, round($m['ai_coins_spent'] / $trendMax * 100)) }}%" title="{{ $m['label'] }}, AI coins spent: {{ number_format($m['ai_coins_spent']) }}"></div>
                    <div class="w-2.5 rounded-t bg-emerald-400/70" style="height: {{ max(2, round($m['coins_purchased'] / $trendMax * 100)) }}%" title="{{ $m['label'] }}, coins purchased: {{ number_format($m['coins_purchased']) }}"></div>
                </div>
            @endforeach
        </div>
        <div class="flex items-center gap-4 text-[10px] text-white/40 mb-5 ak-note">
            <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-red-400/70 mr-1 align-middle"></span>AI coins spent</span>
            <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-emerald-400/70 mr-1 align-middle"></span>Coins purchased</span>
        </div>

        <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider ak-note">
                <th class="text-left py-2">Month</th>
                <th class="text-right">AI coins spent</th>
                <th class="text-right">Coins purchased</th>
                @foreach($trend['currencies'] as $cur)
                    <th class="text-right">Top-up rev ({{ $cur }})</th>
                @endforeach
                @foreach($trend['currencies'] as $cur)
                    <th class="text-right">Subscription rev ({{ $cur }})</th>
                @endforeach
            </tr></thead>
            <tbody>
            @foreach($trend['months'] as $m)
                <tr class="border-t border-white/5 {{ $loop->last ? 'bg-white/[0.03]' : '' }}">
                    <td class="py-2 text-white ak-strong">{{ $m['label'] }}@if($loop->last)<span class="text-[9px] uppercase text-sky-300 ml-1">current</span>@endif</td>
                    <td class="text-right text-red-300 ak-red">{{ number_format($m['ai_coins_spent']) }}</td>
                    <td class="text-right text-emerald-300 ak-green">{{ number_format($m['coins_purchased']) }}</td>
                    @foreach($trend['currencies'] as $cur)
                        <td class="text-right text-white/70 ak-strong">{{ isset($m['topup_revenue'][$cur]) ? PricingResolver::money($m['topup_revenue'][$cur], $cur) : '—' }}</td>
                    @endforeach
                    @foreach($trend['currencies'] as $cur)
                        <td class="text-right text-white/70 ak-strong">{{ isset($m['subscription_revenue'][$cur]) ? PricingResolver::money($m['subscription_revenue'][$cur], $cur) : '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

{{-- ── Section 3: plan-wise profit ─────────────────────────────── --}}
<div class="glass rounded-2xl border border-white/10 p-6">
    <div class="flex items-start justify-between gap-3">
        <h3 class="font-semibold text-white mb-1 ak-strong"><i class="fas fa-scale-balanced text-emerald-400 mr-1"></i> Plan-wise profit</h3>
        @if(!empty($plans))
            <button type="button" onclick="monetizationCsvExport('plans')" class="shrink-0 bg-white/5 hover:bg-white/10 text-white border border-white/10 px-3 py-1.5 rounded-lg text-xs font-medium transition-all ak-strong">
                <i class="fas fa-download mr-1 text-white/50"></i> Export CSV
            </button>
        @endif
    </div>
    <p class="text-xs text-white/40 mb-4 ak-note">
        {{ $periods[$period] }}{{ $since ? ' (since ' . $since->toFormattedDateString() . ')' : '' }}.
        Estimated AI cost = the plan holders' AI coin spend priced at the observed API-budget-per-coin from their own coin purchases (capped at the purchased API budget). Margin = subscription revenue + coin revenue − estimated AI cost, per currency.
    </p>

    @if(empty($plans))
        <p class="text-sm text-white/40 py-4 ak-note">No plans configured.</p>
    @else
        <div class="overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider ak-note">
                <th class="text-left py-2">Plan</th>
                <th class="text-right">Users</th>
                <th class="text-right">Active subs</th>
                <th class="text-right">AI coins spent</th>
                <th class="text-left pl-6">Currency</th>
                <th class="text-right">Subscription revenue</th>
                <th class="text-right">Coin revenue</th>
                <th class="text-right">API budget</th>
                <th class="text-right">Est. AI cost</th>
                <th class="text-right">Margin</th>
            </tr></thead>
            <tbody>
            @foreach($plans as $row)
                @php $span = max(1, count($row['currencies'])); $first = true; @endphp
                @forelse($row['currencies'] as $cur => $c)
                    <tr class="border-t {{ $first ? 'border-white/10' : 'border-white/5' }}">
                        @if($first)
                            <td class="py-2 text-white ak-strong" rowspan="{{ $span }}">
                                {{ $row['plan']->name }}
                                @if($row['plan']->is_internal)<span class="text-[9px] uppercase text-amber-300 ml-1">internal</span>@endif
                            </td>
                            <td class="text-right text-white/70 ak-strong" rowspan="{{ $span }}">{{ number_format($row['users']) }}</td>
                            <td class="text-right text-white/70 ak-strong" rowspan="{{ $span }}">{{ number_format($row['active_subs']) }}</td>
                            <td class="text-right text-red-300 ak-red" rowspan="{{ $span }}">{{ number_format($row['ai_coins_spent']) }}</td>
                        @endif
                        <td class="pl-6 text-white/60 ak-muted">{{ $cur }}</td>
                        <td class="text-right text-white/80 ak-strong">{{ PricingResolver::money($c['revenue_minor'], $cur) }}</td>
                        <td class="text-right text-white/80 ak-strong">{{ PricingResolver::money($c['coin_amount_minor'], $cur) }}</td>
                        <td class="text-right text-white/50 text-xs ak-muted">{{ PricingResolver::money($c['coin_api_budget_minor'], $cur) }}</td>
                        <td class="text-right text-amber-300">{{ PricingResolver::money($c['est_ai_cost_minor'], $cur) }}</td>
                        <td class="text-right font-semibold {{ $c['margin_minor'] >= 0 ? 'text-emerald-300 ak-green' : 'text-red-300 ak-red' }}">{{ PricingResolver::money($c['margin_minor'], $cur) }}</td>
                    </tr>
                    @php $first = false; @endphp
                @empty
                    <tr class="border-t border-white/10">
                        <td class="py-2 text-white ak-strong">
                            {{ $row['plan']->name }}
                            @if($row['plan']->is_internal)<span class="text-[9px] uppercase text-amber-300 ml-1">internal</span>@endif
                        </td>
                        <td class="text-right text-white/70 ak-strong">{{ number_format($row['users']) }}</td>
                        <td class="text-right text-white/70 ak-strong">{{ number_format($row['active_subs']) }}</td>
                        <td class="text-right text-red-300 ak-red">{{ number_format($row['ai_coins_spent']) }}</td>
                        <td class="pl-6 text-white/30 ak-note" colspan="6">No revenue in this period.</td>
                    </tr>
                @endforelse
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

<script>
window.__monetizationCsv = @js($csvExports);
function monetizationCsvExport(section) {
    const payload = window.__monetizationCsv[section];
    if (!payload) return;
    const escape = (v) => {
        const s = (v ?? '').toString();
        return /[",\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    };
    const lines = [payload.header.map(escape).join(',')];
    for (const row of payload.rows) {
        lines.push(row.map(escape).join(','));
    }
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = payload.filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
@endsection
