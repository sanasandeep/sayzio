@extends('user.layouts.app')
@section('title', 'Tax Rules')
@section('content')
<div class="max-w-4xl mx-auto px-4 py-8" x-data="{ editing: null }">
    <div class="page-hero mb-6">
        <h1 class="hero-title">Tax Rules</h1>
        <p class="hero-subtitle">Reusable tax rates applied to invoice line items.</p>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ $errors->first() }}</div>@endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-3">
            @forelse($rules as $rule)
                <div class="p-4 rounded-xl border flex items-center justify-between" style="border-color: var(--border-soft); background: var(--bg-card);">
                    <div>
                        <h3 class="font-bold" style="color: var(--text-primary);">
                            {{ $rule->name }}
                            @if($rule->is_default)<span class="ml-2 text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Default</span>@endif
                            @unless($rule->is_active)<span class="ml-2 text-[10px] px-2 py-0.5 rounded-full bg-slate-200 text-slate-600">Inactive</span>@endunless
                        </h3>
                        <p class="text-xs" style="color: var(--text-muted);">
                            {{ number_format($rule->rate_bps / 100, 2) }}%
                            · {{ $rule->inclusive ? 'Inclusive' : 'Exclusive' }}
                            @if($rule->is_compound) · Compound @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--border-soft); color: var(--text-primary);"
                                @click="editing = editing === {{ $rule->id }} ? null : {{ $rule->id }}"><i class="fas fa-pen"></i></button>
                        <form action="{{ route('user.billing.tax-rules.destroy', $rule) }}" method="POST" onsubmit="return confirm('Delete this tax rule?');">
                            @csrf @method('DELETE')
                            <button class="text-xs px-3 py-1.5 rounded-lg text-rose-600"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <form action="{{ route('user.billing.tax-rules.update', $rule) }}" method="POST" x-show="editing === {{ $rule->id }}" x-cloak class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-xs" style="color: var(--text-muted);">Name<input name="name" value="{{ $rule->name }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                        <label class="text-xs" style="color: var(--text-muted);">Rate (basis points, 2000 = 20%)<input type="number" name="rate_bps" value="{{ $rule->rate_bps }}" class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
                    </div>
                    <div class="flex flex-wrap gap-4 mt-3 text-sm" style="color: var(--text-primary);">
                        <label class="flex items-center gap-2"><input type="checkbox" name="inclusive" value="1" @checked($rule->inclusive)> Inclusive</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_compound" value="1" @checked($rule->is_compound)> Compound</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_default" value="1" @checked($rule->is_default)> Default</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($rule->is_active)> Active</label>
                    </div>
                    <div class="mt-3 text-right"><button class="btn-primary">Save</button></div>
                </form>
            @empty
                <p class="text-sm" style="color: var(--text-muted);">No tax rules yet.</p>
            @endforelse
        </div>

        <form action="{{ route('user.billing.tax-rules.store') }}" method="POST" class="p-4 rounded-xl border h-fit" style="border-color: var(--border-soft); background: var(--bg-card);">
            @csrf
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Add tax rule</h2>
            <label class="text-xs block" style="color: var(--text-muted);">Name<input name="name" required class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
            <label class="text-xs block mt-3" style="color: var(--text-muted);">Rate (basis points)<input type="number" name="rate_bps" value="0" required class="block w-full mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
            <div class="space-y-2 mt-3 text-sm" style="color: var(--text-primary);">
                <label class="flex items-center gap-2"><input type="checkbox" name="inclusive" value="1"> Tax inclusive</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_compound" value="1"> Compound</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_default" value="1"> Default</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            </div>
            <button class="btn-primary w-full mt-4">Add rule</button>
        </form>
    </div>
</div>
@endsection
