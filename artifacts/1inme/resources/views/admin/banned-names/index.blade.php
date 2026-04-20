@extends('admin.layouts.app')
@section('title', 'Banned Names')
@section('page-title', 'Banned Names')

@section('content')
<div class="max-w-5xl space-y-6">

    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90">Reserved &amp; banned names</h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl">
                    Names on this list cannot be claimed as a profile handle or as any link alias
                    (regular, file, calendar or contact). Matching is case-insensitive. Existing
                    handles/aliases are not retroactively renamed — the conflict count below shows
                    where current values already match a banned entry.
                </p>
            </div>
            <a href="{{ route('admin.banned-names.create') }}"
               class="px-4 py-2 rounded-xl text-sm font-medium bg-violet-600 hover:bg-violet-700 text-white inline-flex items-center gap-2">
                <i class="fas fa-plus text-xs"></i> Add name
            </a>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        @if($items->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-white/40">
                <i class="fas fa-ban text-2xl text-white/20 mb-3"></i>
                <div>No banned names yet. Add one to start reserving handles &amp; aliases.</div>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="text-[11px] uppercase tracking-wider text-white/40 bg-white/[0.02]">
                    <tr>
                        <th class="px-5 py-3 text-left">Name</th>
                        <th class="px-5 py-3 text-left">Note</th>
                        <th class="px-5 py-3 text-right">Existing conflicts</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($items as $item)
                    @php $c = $conflicts[$item->id] ?? ['users'=>0,'links'=>0,'extras'=>0]; @endphp
                    @php $totalC = $c['users'] + $c['links'] + $c['extras']; @endphp
                    <tr class="border-t border-white/5">
                        <td class="px-5 py-3 font-mono text-white/90">{{ $item->name }}</td>
                        <td class="px-5 py-3 text-white/60">{{ $item->note ?: '—' }}</td>
                        <td class="px-5 py-3 text-right">
                            @if($totalC === 0)
                                <span class="text-white/30 text-xs">none</span>
                            @else
                                <span class="inline-flex items-center gap-2 text-xs px-2 py-1 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-200"
                                      title="{{ $c['users'] }} user handle(s), {{ $c['links'] }} primary alias(es), {{ $c['extras'] }} extra alias(es)">
                                    <i class="fas fa-triangle-exclamation"></i>
                                    {{ $totalC }} match{{ $totalC === 1 ? '' : 'es' }}
                                </span>
                                <div class="text-[10px] text-white/40 mt-1">
                                    {{ $c['users'] }} handle{{ $c['users'] === 1 ? '' : 's' }} ·
                                    {{ $c['links'] }} primary ·
                                    {{ $c['extras'] }} extra
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('admin.banned-names.edit', $item) }}"
                                   class="px-2.5 py-1.5 rounded-lg text-xs bg-white/5 hover:bg-white/10 text-white/80 border border-white/10">
                                    <i class="fas fa-pen text-[10px]"></i> Edit
                                </a>
                                <form method="POST" action="{{ route('admin.banned-names.destroy', $item) }}"
                                      onsubmit="return confirm('Remove {{ $item->name }} from the banned list?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="px-2.5 py-1.5 rounded-lg text-xs bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                        <i class="fas fa-trash text-[10px]"></i> Remove
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
