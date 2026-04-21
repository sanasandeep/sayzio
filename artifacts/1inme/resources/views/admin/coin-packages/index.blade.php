@extends('admin.layouts.app')
@section('title', 'Coin Packages')
@section('page-title', 'Coin Packages')

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-white/40">Buyable coin bundles. Customers top up coins and spend them on coin-priced add-ons.</p>
    <a href="{{ route('admin.coin-packages.create') }}" class="px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700"><i class="fas fa-plus mr-2"></i>Add Package</a>
</div>

@if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($packages as $pkg)
    <div class="glass rounded-2xl border border-white/10 p-6 {{ $pkg->is_archived ? 'opacity-60' : '' }}">
        <div class="flex items-start justify-between mb-2">
            <div>
                <h3 class="font-semibold text-white">{{ $pkg->name }}</h3>
                <p class="text-[11px] text-white/30 font-mono">{{ $pkg->slug }}</p>
            </div>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                {{ $pkg->status === 'active' && !$pkg->is_archived ? 'bg-emerald-500/10 text-emerald-400' : 'bg-white/10 text-white/60' }}">
                {{ $pkg->is_archived ? 'Archived' : ucfirst($pkg->status) }}
            </span>
        </div>
        <p class="text-sm text-white/40 mb-4">{{ $pkg->description ?? 'No description' }}</p>

        <div class="space-y-1 mb-4 text-sm">
            <div class="flex justify-between"><span class="text-white/40">Coins</span><span class="font-semibold text-amber-300">{{ number_format($pkg->coin_amount) }}</span></div>
            @if($pkg->bonus_coins > 0)
            <div class="flex justify-between"><span class="text-white/40">Bonus</span><span class="font-semibold text-emerald-300">+{{ number_format($pkg->bonus_coins) }}</span></div>
            @endif
            @php $pUsd = $pkg->prices->first(fn($p)=>$p->currency==='USD' && $p->billing_cycle==='monthly'); @endphp
            @php $pInr = $pkg->prices->first(fn($p)=>$p->currency==='INR' && $p->billing_cycle==='monthly'); @endphp
            <div class="flex justify-between"><span class="text-white/40">Price USD</span><span class="font-semibold text-white">${{ number_format(($pUsd->amount_minor_units ?? 0)/100, 2) }}</span></div>
            <div class="flex justify-between"><span class="text-white/40">Price INR</span><span class="font-semibold text-white">₹{{ number_format(($pInr->amount_minor_units ?? 0)/100, 2) }}</span></div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-4 border-t border-white/5">
            <a href="{{ route('admin.coin-packages.edit', $pkg) }}" class="text-white/30 hover:text-violet-400" title="Edit"><i class="fas fa-edit"></i></a>
            <form action="{{ route('admin.coin-packages.archive', $pkg) }}" method="POST" class="inline">@csrf
                <button class="text-white/30 hover:text-amber-400" title="{{ $pkg->is_archived ? 'Restore' : 'Archive' }}">
                    <i class="fas {{ $pkg->is_archived ? 'fa-box-open' : 'fa-box-archive' }}"></i>
                </button>
            </form>
            <form action="{{ route('admin.coin-packages.destroy', $pkg) }}" method="POST" class="inline" onsubmit="return confirm('Delete this coin package?')">@csrf @method('DELETE')
                <button class="text-white/30 hover:text-red-400" title="Delete"><i class="fas fa-trash"></i></button>
            </form>
        </div>
    </div>
    @empty
    <div class="col-span-full text-center text-white/40 py-12">
        No coin packages yet. <a href="{{ route('admin.coin-packages.create') }}" class="text-violet-400 hover:underline">Create your first one</a>.
    </div>
    @endforelse
</div>
@endsection
