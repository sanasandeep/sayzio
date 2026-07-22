@extends('admin.layouts.app')
@section('title', 'Tax Jurisdictions')
@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-white ak-strong">Tax Jurisdictions</h1>
            <p class="text-sm text-white/50 ak-muted">GST, VAT and sales-tax rates per country/region. Used by the tax engine on every checkout.</p>
        </div>
        <a href="{{ route('admin.taxes.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700">+ Add jurisdiction</a>
    </div>
    @if(session('success'))<div class="px-4 py-2 rounded-xl bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 text-sm ak-green">{{ session('success') }}</div>@endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-white/5 text-white/60 text-xs uppercase tracking-wider ak-muted">
                <tr>
                    <th class="px-4 py-2 text-left">Country</th>
                    <th class="px-4 py-2 text-left">Region</th>
                    <th class="px-4 py-2 text-left">Kind</th>
                    <th class="px-4 py-2 text-right">Rate</th>
                    <th class="px-4 py-2 text-left">Label</th>
                    <th class="px-4 py-2 text-center">Reverse charge</th>
                    <th class="px-4 py-2 text-center">Active</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="text-white/80 ak-strong">
                @foreach($rows as $row)
                    <tr class="border-t border-white/5">
                        <td class="px-4 py-2 font-mono">{{ $row->country }}</td>
                        <td class="px-4 py-2 font-mono">{{ $row->region ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $row->kind }}</td>
                        <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($row->rate_percent, 3), '0'), '.') }}%</td>
                        <td class="px-4 py-2 text-white/60 ak-muted">{{ $row->label }}</td>
                        <td class="px-4 py-2 text-center">{{ $row->b2b_reverse_charge ? '✓' : '—' }}</td>
                        <td class="px-4 py-2 text-center">{{ $row->is_active ? '✓' : '—' }}</td>
                        <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('admin.taxes.edit', $row) }}" class="text-blue-300 hover:text-blue-200 ak-blue">Edit</a>
                            <form method="POST" action="{{ route('admin.taxes.destroy', $row) }}" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this jurisdiction?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                                @csrf @method('DELETE')
                                <button class="text-red-400 hover:text-red-300 ak-red">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div>{{ $rows->links() }}</div>
</div>
@endsection
