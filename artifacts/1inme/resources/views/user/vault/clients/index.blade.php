@extends('user.layouts.app')
@section('title', 'Vault — Clients')
@section('content')
@include('user.vault._tabs')

@php use App\Modules\User\Services\WorkspacePermissions as WP; $canCreate = WP::userCan('vault.create'); @endphp

<div class="flex flex-wrap items-center gap-3 mb-4">
    <form method="get" class="flex-1 min-w-[240px]">
        <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by name, company, email or tag…"
                   class="w-full pl-9 pr-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
        </div>
    </form>
    @if($canCreate)
        <a href="{{ route('user.vault.clients.create') }}" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold">
            <i class="fas fa-plus mr-1"></i> New client
        </a>
    @endif
</div>

<div class="rounded-xl border border-white/10 overflow-hidden" style="background: var(--bg-card);">
    <table class="min-w-full text-sm">
        <thead class="bg-white/5 text-xs uppercase tracking-wide text-gray-400">
            <tr>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left">Company</th>
                <th class="px-4 py-3 text-left">Email</th>
                <th class="px-4 py-3 text-left">Phone</th>
                <th class="px-4 py-3 text-left">Updated</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse($items as $c)
                <tr class="hover:bg-white/5">
                    <td class="px-4 py-3">
                        <a href="{{ route('user.vault.clients.show', $c) }}" class="text-amber-300 hover:underline font-semibold">{{ $c->name }}</a>
                        @if($c->visibility === 'private')<span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-red-500/20 text-red-300">private</span>@endif
                    </td>
                    <td class="px-4 py-3">{{ $c->company }}</td>
                    <td class="px-4 py-3">{{ $c->primary_email }}</td>
                    <td class="px-4 py-3">{{ $c->primary_phone }}</td>
                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $c->updated_at?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-12 text-center text-gray-400">No clients yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
