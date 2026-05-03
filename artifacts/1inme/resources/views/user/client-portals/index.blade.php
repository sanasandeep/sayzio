@extends('user.layouts.app')
@section('title', 'Client Portals')
@section('content')
<div class="page-hero mb-6 flex items-center gap-4">
    <div class="hero-emblem"><i class="fas fa-handshake"></i></div>
    <div class="flex-1 min-w-0">
        <h1 class="hero-title">Client Portals</h1>
        <p class="hero-subtitle">Branded read-only portals you share with clients and sponsors.</p>
    </div>
    <a href="{{ route('user.client-portals.create') }}" class="px-4 py-2 rounded-lg bg-primary-600 text-white font-semibold text-sm">
        <i class="fas fa-plus mr-1"></i> New portal
    </a>
</div>

@if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($portals as $portal)
        <a href="{{ route('user.client-portals.edit', $portal) }}" class="block rounded-xl border p-5 hover:border-primary-500" style="border-color: var(--border-strong); background: var(--bg-card);">
            <div class="flex items-center gap-3 mb-3">
                <div class="h-10 w-10 rounded text-white flex items-center justify-center text-sm font-bold" style="background: {{ $portal->brandingColor() }}">
                    {{ strtoupper(mb_substr($portal->brandingName(), 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <div class="font-semibold truncate">{{ $portal->name }}</div>
                    <div class="text-xs text-gray-400 truncate">
                        {{ optional($portal->vaultClient)->name ?: 'No client linked' }}
                        · {{ $portal->is_enabled ? 'Active' : 'Disabled' }}
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 text-xs text-gray-400">
                <span><i class="fas fa-share-alt mr-1"></i>{{ $portal->shares_count }} shares</span>
                <span><i class="fas fa-link mr-1"></i>{{ $portal->links_count }} links</span>
                <span><i class="fas fa-bolt mr-1"></i>{{ $portal->actions_count }} actions</span>
            </div>
            @if($portal->last_seen_at)
                <div class="text-xs text-gray-500 mt-2">Last seen {{ $portal->last_seen_at->diffForHumans() }}</div>
            @endif
        </a>
    @empty
        <div class="md:col-span-2 lg:col-span-3 rounded-xl border border-dashed p-10 text-center text-gray-400" style="border-color: var(--border-glass);">
            <i class="fas fa-handshake text-4xl mb-3 opacity-50"></i>
            <p>No client portals yet. Create one to start sharing with a client.</p>
        </div>
    @endforelse
</div>
@endsection
