@extends('admin.layouts.app')
@section('title', 'Addons')
@section('page-title', 'Addons')

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-white/40">Standalone upgrades that attach to one or more plans.</p>
    <a href="{{ route('admin.addons.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Addon
    </a>
</div>

@if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($addons as $addon)
    <div class="glass rounded-2xl border border-white/10 p-6 {{ $addon->is_archived ? 'opacity-60' : '' }}">
        <div class="flex items-start justify-between mb-2">
            <div>
                <h3 class="font-semibold text-white">{{ $addon->name }}</h3>
                <p class="text-[11px] text-white/30 font-mono">{{ $addon->slug }}</p>
            </div>
            <div class="flex flex-col items-end gap-1">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                    {{ $addon->status === 'active' && !$addon->is_archived ? 'bg-emerald-500/10 text-emerald-400' : 'bg-white/10 text-white/60' }}">
                    {{ $addon->is_archived ? 'Archived' : ucfirst($addon->status) }}
                </span>
                <span class="text-[10px] uppercase tracking-wider text-white/40">{{ str_replace('_',' ',$addon->type) }}</span>
            </div>
        </div>
        <p class="text-sm text-white/40 mb-4">{{ $addon->description ?? 'No description' }}</p>

        @php
            // Shared dual-currency display logic (single source of truth
            // with the admin Plans cards — see PricingResolver).
            $monthly = \App\Services\PricingResolver::adminDisplayPair($addon, 'monthly');
            $annual  = \App\Services\PricingResolver::adminDisplayPair($addon, 'annual');
        @endphp
        <div class="space-y-1 mb-4 text-sm">
            <div class="flex justify-between">
                <span class="text-white/40">Monthly</span>
                <span class="font-semibold text-white">
                    {{ $monthly['usd'] }}
                    @if($monthly['inr'] !== null)
                        <span class="text-white/50 font-normal"> / {{ $monthly['inr'] }}</span>
                    @else
                        <span class="text-white/30 font-normal"> / —</span>
                    @endif
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-white/40">Annual</span>
                <span class="font-semibold text-white">
                    {{ $annual['usd'] }}
                    @if($annual['inr'] !== null)
                        <span class="text-white/50 font-normal"> / {{ $annual['inr'] }}</span>
                    @else
                        <span class="text-white/30 font-normal"> / —</span>
                    @endif
                </span>
            </div>
            @if(!is_null($addon->coin_cost))
                <div class="flex justify-between"><span class="text-white/40">Coin price</span><span class="font-semibold text-amber-300">{{ number_format($addon->coin_cost) }} 🪙</span></div>
            @endif
        </div>

        @if($addon->plans->isNotEmpty())
        <div class="mb-4">
            <p class="text-[11px] uppercase tracking-wider text-white/40 mb-1">Eligible plans</p>
            <div class="flex flex-wrap gap-1">
                @foreach($addon->plans as $p)
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-300 border border-blue-500/20">{{ $p->name }}</span>
                @endforeach
            </div>
        </div>
        @endif

        <div class="flex items-center justify-end gap-2 pt-4 border-t border-white/5">
            <a href="{{ route('admin.addons.edit', $addon) }}" class="text-white/30 hover:text-blue-400" title="Edit"><i class="fas fa-edit"></i></a>
            <form action="{{ route('admin.addons.archive', $addon) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="text-white/30 hover:text-amber-400" title="{{ $addon->is_archived ? 'Restore' : 'Archive' }}">
                    <i class="fas {{ $addon->is_archived ? 'fa-box-open' : 'fa-box-archive' }}"></i>
                </button>
            </form>
            <form action="{{ route('admin.addons.destroy', $addon) }}" method="POST" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this addon?', message: 'This cannot be undone.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                @csrf @method('DELETE')
                <button type="submit" class="text-white/30 hover:text-red-400" title="Delete"><i class="fas fa-trash"></i></button>
            </form>
        </div>
    </div>
    @empty
    <div class="col-span-full text-center text-white/40 py-12">
        No addons yet. <a href="{{ route('admin.addons.create') }}" class="text-blue-400 hover:underline">Create your first one</a>.
    </div>
    @endforelse
</div>
@endsection
