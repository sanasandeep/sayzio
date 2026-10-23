@extends('admin.layouts.app')
@section('title', 'AI feature finances')
@section('page-title', 'AI feature finances')
@section('content')
<div class="space-y-4 max-w-6xl"><a class="underline" href="{{ route('admin.finance.index', $filters) }}">Financial reports</a>
<h1 class="text-xl font-semibold">{{ \App\Services\AI\AiFeatureCatalog::featureLabel($feature) }} · {{ $mode === 'totals' ? 'Totals' : 'Transaction records' }}</h1>
<p class="ak-muted">Recorded AI coin movements for this feature. Includes separately uncatalogued sub-actions; other named feature reports require their own permission. Provider costs and purchase revenue are not inferred from coin amounts.</p>
@if($totals)<div class="glass border border-white/10 rounded-2xl p-5"><p>Transactions: {{ number_format($totals->records) }}</p><p>Coins debited: {{ number_format($totals->spent) }}</p><p>Coins credited: {{ number_format($totals->credited) }}</p></div>
@endif
@if($rows)<div class="glass border border-white/10 rounded-2xl p-5 overflow-x-auto"><table class="text-sm w-full"><thead><tr><th class="text-left p-2">Date</th><th class="text-left p-2">Account</th><th class="text-left p-2">Type</th><th class="text-right p-2">Coins</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td class="p-2">{{ $row['date'] }}</td><td class="p-2">{{ $row['account']['label'] }}
@foreach(['name','email','phone'] as $field)
@if(isset($row['account'][$field]))<span class="block">{{ $row['account'][$field] }}</span>
@endif
@endforeach
</td><td class="p-2">{{ $row['type'] }}</td><td class="text-right p-2">{{ number_format($row['coins']) }}</td></tr>
@empty
<tr><td class="p-2">No recorded transactions for this feature.</td></tr>
@endforelse
</tbody></table>{{ $rows->links() }}</div>
@endif
</div>
@endsection
