@extends('user.layouts.app')
@section('title', 'Expenses')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">Expenses</h1>
            <p class="hero-subtitle">Track business costs that feed your ledger &amp; profit report.</p>
        </div>
        <div class="text-right">
            <p class="text-xs" style="color: var(--text-muted);">Total (filtered)</p>
            <p class="text-lg font-bold" style="color: var(--text-primary);">{{ number_format($totalMinor / 100, 2) }}</p>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ $errors->first() }}</div>@endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <form action="{{ route('user.billing.expenses.store') }}" method="POST" class="p-4 rounded-xl border h-fit" style="border-color: var(--border-soft); background: var(--bg-card);">
            @csrf
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Record expense</h2>
            <label class="text-xs block" style="color: var(--text-muted);">Date *<input type="date" name="spent_at" value="{{ now()->toDateString() }}" required class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
            <label class="text-xs block mt-3" style="color: var(--text-muted);">Vendor<input name="vendor" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
            <label class="text-xs block mt-3" style="color: var(--text-muted);">Description<input name="description" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
            <div class="grid grid-cols-2 gap-3 mt-3">
                <label class="text-xs" style="color: var(--text-muted);">Amount (minor) *<input type="number" min="0" name="amount_minor" value="0" required class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                <label class="text-xs" style="color: var(--text-muted);">Tax (minor)<input type="number" min="0" name="tax_minor" value="0" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-3">
                <label class="text-xs" style="color: var(--text-muted);">Currency<input name="currency" maxlength="3" value="USD" class="block w-full mt-1 p-2 rounded-lg border uppercase" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                <label class="text-xs" style="color: var(--text-muted);">Category
                    <select name="category_id" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="">— None —</option>
                        @foreach($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                    </select>
                </label>
            </div>
            <label class="text-xs block mt-3" style="color: var(--text-muted);">Billing company
                <select name="billing_company_id" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    <option value="">— None —</option>
                    @foreach($companies as $co)<option value="{{ $co->id }}">{{ $co->name }}</option>@endforeach
                </select>
            </label>
            <button class="btn-primary w-full mt-4">Record</button>
        </form>

        <div class="md:col-span-2 space-y-2">
            @forelse($expenses as $expense)
                <div class="p-3 rounded-xl border flex items-center justify-between" style="border-color: var(--border-soft); background: var(--bg-card);">
                    <div>
                        <p class="font-semibold text-sm" style="color: var(--text-primary);">{{ $expense->vendor ?: 'Expense' }}</p>
                        <p class="text-xs" style="color: var(--text-muted);">{{ optional($expense->spent_at)->format('M j, Y') }}@if($expense->description) · {{ $expense->description }}@endif</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-sm" style="color: var(--text-primary);">{{ strtoupper($expense->currency ?: 'USD') }} {{ number_format(($expense->amount_minor + $expense->tax_minor) / 100, 2) }}</span>
                        <form action="{{ route('user.billing.expenses.destroy', $expense) }}" method="POST" onsubmit="return confirm('Delete expense?');">@csrf @method('DELETE')<button class="text-rose-600 text-xs"><i class="fas fa-trash"></i></button></form>
                    </div>
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted);">No expenses recorded.</p>
            @endforelse
            <div class="mt-4">{{ $expenses->links() }}</div>
        </div>
    </div>
</div>
@endsection
