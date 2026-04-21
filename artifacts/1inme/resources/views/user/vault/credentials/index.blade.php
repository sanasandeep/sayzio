@extends('user.layouts.app')
@section('title', 'Vault — Credentials')
@section('content')
@include('user.vault._tabs')

@php
    use App\Modules\User\Services\WorkspacePermissions as WP;
    $canCreate = WP::userCan('vault.create');
@endphp

<div class="flex flex-wrap items-center gap-3 mb-4">
    <form method="get" class="flex-1 min-w-[240px]">
        <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by label, username, URL or tag…"
                   class="w-full pl-9 pr-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm">
        </div>
    </form>
    @if($canCreate)
        <a href="{{ route('user.vault.credentials.create') }}" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold">
            <i class="fas fa-plus mr-1"></i> New credential
        </a>
    @endif
</div>

<div class="rounded-xl border border-white/10 overflow-hidden" style="background: var(--bg-card);">
    <table class="min-w-full text-sm">
        <thead class="bg-white/5 text-xs uppercase tracking-wide text-gray-400">
            <tr>
                <th class="px-4 py-3 text-left">Label</th>
                <th class="px-4 py-3 text-left">Username</th>
                <th class="px-4 py-3 text-left">URL</th>
                <th class="px-4 py-3 text-left">Visibility</th>
                <th class="px-4 py-3 text-left">Updated</th>
                <th></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse($items as $c)
                <tr class="hover:bg-white/5">
                    <td class="px-4 py-3 font-semibold">
                        <a href="{{ route('user.vault.credentials.show', $c) }}" class="text-amber-300 hover:underline">{{ $c->label }}</a>
                        @foreach(($c->tags ?? []) as $t)
                            <span class="ml-1 px-2 py-0.5 text-[10px] rounded-full bg-white/5">{{ $t }}</span>
                        @endforeach
                    </td>
                    <td class="px-4 py-3">{{ $c->username }}</td>
                    <td class="px-4 py-3 truncate max-w-[260px]">
                        @if($c->url)<a href="{{ $c->url }}" target="_blank" rel="noopener" class="text-blue-400 hover:underline">{{ $c->url }}</a>@endif
                    </td>
                    <td class="px-4 py-3">
                        @if($c->visibility === 'private')
                            <span class="text-xs px-2 py-1 rounded bg-red-500/20 text-red-300"><i class="fas fa-lock mr-1"></i>Private</span>
                        @else
                            <span class="text-xs px-2 py-1 rounded bg-emerald-500/20 text-emerald-300">Shared</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $c->updated_at?->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('user.vault.credentials.show', $c) }}" class="text-gray-400 hover:text-white"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">No credentials yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($audits->count())
    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-300 mb-2">Recent activity</h2>
        <div class="rounded-xl border border-white/10 divide-y divide-white/5" style="background: var(--bg-card);">
            @foreach($audits as $a)
                <div class="px-4 py-2 text-xs text-gray-400 flex items-center gap-3">
                    <span class="w-20 text-gray-500">{{ $a->occurred_at?->diffForHumans() }}</span>
                    <span class="px-2 py-0.5 rounded bg-white/5 uppercase tracking-wider text-[10px]">{{ $a->action }}</span>
                    <span>{{ $a->target_type }}</span>
                    <span class="text-gray-300">{{ $a->target_label }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
@endsection
