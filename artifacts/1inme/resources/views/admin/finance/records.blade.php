@extends('admin.layouts.app')
@section('title', $section === 'invoices' ? 'Invoice records' : 'Coin purchase records')
@section('page-title', $section === 'invoices' ? 'Invoice records' : 'Coin purchase records')
@section('content')
<div class="space-y-4"><a href="{{ route('admin.finance.index', $filters) }}" class="underline">Financial reports</a><h1 class="text-xl font-semibold">{{ $section === 'invoices' ? 'Invoice records' : 'Coin purchase records' }}</h1><p class="ak-muted">Account identity and contact fields appear only when separately permitted.</p>
<div class="glass border border-white/10 rounded-2xl p-4 overflow-x-auto"><table class="text-sm w-full"><thead><tr><th class="text-left p-3">Record</th><th class="text-left p-3">Date</th><th class="text-left p-3">Account</th><th class="text-left p-3">Details</th><th class="text-right p-3">Amount</th>@if($section === 'coins' && $actor->hasPermission('finance.margin.view'))<th class="text-right p-3">API budget</th><th class="text-right p-3">Margin</th>
@endif
</tr></thead><tbody>
@forelse($rows as $record)<tr class="border-t border-white/10"><td class="p-3">{{ $record['number'] ?? '#'.$record['id'] }} @if($section === 'invoices' && $actor->hasPermission('finance.refunds.view') && \App\Modules\Admin\Support\StaffPermissionCatalog::fullIdentity($actor))<a class="block underline" href="{{ route('admin.invoices.show', $record['id']) }}">Refund details</a>
@endif
</td><td class="p-3">{{ $record['date'] }}</td><td class="p-3">{{ $record['account']['label'] }}@foreach(['name','email','phone'] as $field)@if(isset($record['account'][$field]))<span class="block">{{ $record['account'][$field] }}</span>
@endif

@endforeach
</td><td class="p-3">@if($section === 'invoices'){{ $record['kind'] ?? 'subscription' }} · {{ $record['status'] }}
@else
{{ number_format($record['coins']) }} coins
@endif
</td><td class="text-right p-3">{{ strtoupper($record['currency']) }} {{ number_format($record['amount_minor'] / 100, 2) }}</td>@if(isset($record['margin_minor']))<td class="text-right p-3">{{ number_format($record['api_budget_minor'] / 100, 2) }}</td><td class="text-right p-3">{{ number_format($record['margin_minor'] / 100, 2) }}</td>
@endif
</tr>
@empty
<tr><td class="p-3">No matching records.</td></tr>
@endforelse

</tbody></table><div class="mt-4">{{ $rows->links() }}</div></div></div>
@endsection
