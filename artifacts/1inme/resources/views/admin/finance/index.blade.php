@extends('admin.layouts.app')
@section('title', 'Financial reports')
@section('page-title', 'Financial reports')
@section('content')
<div class="space-y-5 max-w-6xl"><h1 class="text-xl font-semibold">Financial reports</h1>
<p class="ak-muted">Totals, individual purchases and internal margins are separately controlled. Currencies are never added together. Invoice totals are face values grouped by status, not net revenue.</p>
<form method="GET" class="flex flex-wrap items-end gap-3"><label>From<input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="ak-input border rounded-xl p-2 block"></label><label>To<input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="ak-input border rounded-xl p-2 block"></label><button class="bg-blue-600 text-white p-3 rounded-xl">Apply date range</button></form>
@if($actor->hasPermission('finance.invoices.totals'))<section class="glass border border-white/10 rounded-2xl p-5 overflow-x-auto"><h2 class="font-semibold">Invoice totals</h2><p class="ak-muted text-sm">Platform invoices only; creators’ client invoices are excluded.</p><table class="text-sm w-full"><thead><tr><th class="text-left p-2">Currency</th><th class="text-left p-2">Status</th><th class="text-right p-2">Count</th><th class="text-right p-2">Face value</th></tr></thead><tbody>@forelse($invoiceTotals as $total)<tr><td class="p-2">{{ strtoupper($total->currency) }}</td><td class="p-2">{{ $total->status }}</td><td class="text-right p-2">{{ $total->records }}</td><td class="text-right p-2">{{ number_format($total->total_minor / 100, 2) }}</td></tr>
@empty
<tr><td class="p-2">No invoices in this period.</td></tr>
@endforelse
</tbody></table></section>
@endif

@if($actor->hasPermission('finance.coins.totals'))<section class="glass border border-white/10 rounded-2xl p-5 overflow-x-auto"><h2 class="font-semibold">Completed coin purchase totals</h2><table class="text-sm w-full"><thead><tr><th class="text-left p-2">Currency</th><th class="text-right p-2">Purchases</th><th class="text-right p-2">Amount</th><th class="text-right p-2">Coins</th></tr></thead><tbody>@forelse($coinTotals as $total)<tr><td class="p-2">{{ strtoupper($total->currency) }}</td><td class="text-right p-2">{{ $total->records }}</td><td class="text-right p-2">{{ number_format($total->total_minor / 100, 2) }}</td><td class="text-right p-2">{{ number_format($total->coins) }}</td></tr>
@empty
<tr><td class="p-2">No completed coin purchases in this period.</td></tr>
@endforelse
</tbody></table></section>
@endif

<div class="flex flex-wrap gap-3">@foreach(['invoices' => 'Invoice records', 'coins' => 'Coin purchase records'] as $section => $label)@if($actor->hasPermission('finance.'.$section.'.view'))<a class="border rounded-xl p-3 underline" href="{{ route('admin.finance.records', array_merge($filters, ['section' => $section])) }}">{{ $label }}</a>
@endif

@endforeach
</div>
<section class="glass border border-white/10 rounded-2xl p-5"><h2 class="font-semibold">AI finances by feature</h2><p class="text-sm ak-muted">Only permitted feature reports are listed. A totals grant does not unlock transaction records.</p>
<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
@foreach(\App\Services\AI\AiFeatureCatalog::FEATURES as $aiFeature)
    @if($actor->hasAnyPermission(['finance.ai.'.$aiFeature.'.totals', 'finance.ai.'.$aiFeature.'.records']))
    <div class="border rounded-xl p-3"><h3 class="font-medium">{{ \App\Services\AI\AiFeatureCatalog::featureLabel($aiFeature) }}</h3>
        @foreach(['totals' => 'Totals', 'records' => 'Transaction records'] as $mode => $label)
            @if($actor->hasPermission('finance.ai.'.$aiFeature.'.'.$mode))<a class="inline-block underline p-2" href="{{ route('admin.finance.ai', array_merge($filters, ['feature' => $aiFeature, 'mode' => $mode])) }}">{{ $label }}</a>
            @endif
        @endforeach
    </div>
    @endif
@endforeach
</div></section></div>
@endsection
