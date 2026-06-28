@extends('user.layouts.app')
@section('title', 'Item Catalog')
@section('content')
<div class="max-w-5xl mx-auto px-4 py-8" x-data="{ editing: null }">
    <div class="page-hero mb-6">
        <h1 class="hero-title">Item Catalog</h1>
        <p class="hero-subtitle">Reusable products &amp; services for fast invoicing.</p>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ $errors->first() }}</div>@endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-3">
            @forelse($items as $item)
                <div class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-bold" style="color: var(--text-primary);">{{ $item->name }}
                                @unless($item->is_active)<span class="ml-2 text-[10px] px-2 py-0.5 rounded-full bg-slate-200 text-slate-600">Inactive</span>@endunless
                            </h3>
                            <p class="text-xs" style="color: var(--text-muted);">
                                {{ strtoupper($item->currency ?: 'USD') }} {{ number_format($item->unit_price_minor / 100, 2) }}
                                @if($item->unit_label) / {{ $item->unit_label }}@endif
                                @if($item->sku) · SKU {{ $item->sku }}@endif
                            </p>
                            @if($item->description)<p class="text-xs mt-1" style="color: var(--text-muted);">{{ \Illuminate\Support\Str::limit($item->description, 120) }}</p>@endif
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="text-xs px-3 py-1.5 rounded-lg border" style="border-color: var(--border-soft); color: var(--text-primary);" @click="editing = editing === {{ $item->id }} ? null : {{ $item->id }}"><i class="fas fa-pen"></i></button>
                            <form action="{{ route('user.billing.catalog.items.destroy', $item) }}" method="POST" onsubmit="return confirm('Delete this item?');">
                                @csrf @method('DELETE')
                                <button class="text-xs px-3 py-1.5 rounded-lg text-rose-600"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <form action="{{ route('user.billing.catalog.items.update', $item) }}" method="POST" x-show="editing === {{ $item->id }}" x-cloak class="mt-3 pt-3 border-t" style="border-color: var(--border-soft);">
                        @csrf @method('PUT')
                        @include('user.billing.catalog._item_fields', ['item' => $item])
                        <div class="mt-3 text-right"><button class="btn-primary">Save</button></div>
                    </form>
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted);">No catalog items yet.</p>
            @endforelse
        </div>

        <div class="space-y-6">
            <form action="{{ route('user.billing.catalog.items.store') }}" method="POST" class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
                @csrf
                <h2 class="font-bold mb-3" style="color: var(--text-primary);">Add item</h2>
                @include('user.billing.catalog._item_fields', ['item' => null])
                <button class="btn-primary w-full mt-4">Add item</button>
            </form>

            <div class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
                <h2 class="font-bold mb-3" style="color: var(--text-primary);">Categories</h2>
                <ul class="space-y-1 mb-3 text-sm" style="color: var(--text-primary);">
                    @forelse($categories as $cat)
                        <li class="flex items-center justify-between">
                            <span>{{ $cat->name }} <span class="text-[10px]" style="color: var(--text-muted);">({{ $cat->kind }})</span></span>
                            <form action="{{ route('user.billing.catalog.categories.destroy', $cat) }}" method="POST" onsubmit="return confirm('Delete category?');">@csrf @method('DELETE')<button class="text-rose-600 text-xs"><i class="fas fa-times"></i></button></form>
                        </li>
                    @empty
                        <li class="text-xs" style="color: var(--text-muted);">No categories.</li>
                    @endforelse
                </ul>
                <form action="{{ route('user.billing.catalog.categories.store') }}" method="POST" class="flex gap-2">
                    @csrf
                    <input name="name" placeholder="New category" required class="flex-1 p-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                    <select name="kind" class="p-2 rounded-lg border text-sm" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                        <option value="item">Item</option>
                        <option value="expense">Expense</option>
                        <option value="both">Both</option>
                    </select>
                    <button class="btn-primary px-3"><i class="fas fa-plus"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
