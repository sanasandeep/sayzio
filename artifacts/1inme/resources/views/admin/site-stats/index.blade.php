@extends('admin.layouts.app')

@section('title', 'Marketing Stats')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-end justify-between flex-wrap gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-white">Marketing Stats</h1>
            <p class="text-sm text-gray-400 mt-1">Numbers shown across the marketing site (homepage, About, Features, etc.).</p>
        </div>
        <a href="{{ route('admin.site-stats.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-cyan-500 to-violet-500 text-white text-sm font-semibold hover:opacity-90">
            <i class="fas fa-plus"></i> Add stat
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-200 text-sm">{{ session('success') }}</div>
    @endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-white/[0.04] text-xs uppercase tracking-wider text-gray-400">
                <tr>
                    <th class="text-left px-4 py-3">#</th>
                    <th class="text-left px-4 py-3">Icon</th>
                    <th class="text-left px-4 py-3">Value</th>
                    <th class="text-left px-4 py-3">Label</th>
                    <th class="text-left px-4 py-3">Color</th>
                    <th class="text-left px-4 py-3">Status</th>
                    <th class="text-right px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
            @forelse($stats as $stat)
                <tr class="hover:bg-white/[0.03]">
                    <td class="px-4 py-3 text-gray-400">{{ $stat->sort_order }}</td>
                    <td class="px-4 py-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: linear-gradient(135deg, {{ $stat->color }}, #7c3aed);">
                            <i class="fas {{ $stat->icon }} text-white"></i>
                        </div>
                    </td>
                    <td class="px-4 py-3 font-bold text-white">{{ $stat->value }}<span class="text-cyan-300">{{ $stat->suffix }}</span></td>
                    <td class="px-4 py-3 text-gray-200">{{ $stat->label }}</td>
                    <td class="px-4 py-3"><span class="inline-flex items-center gap-2 text-xs text-gray-300"><span class="w-4 h-4 rounded" style="background: {{ $stat->color }}"></span>{{ $stat->color }}</span></td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.site-stats.toggle', $stat) }}">@csrf
                            <button class="text-xs px-2 py-1 rounded-full {{ $stat->is_active ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-gray-500/15 text-gray-400 border border-gray-500/30' }}">{{ $stat->is_active ? 'Active' : 'Hidden' }}</button>
                        </form>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.site-stats.edit', $stat) }}" class="text-cyan-300 hover:text-cyan-200 mr-3"><i class="fas fa-pen"></i></a>
                        <form method="POST" action="{{ route('admin.site-stats.destroy', $stat) }}" class="inline" onsubmit="event.preventDefault(); themedConfirmAsync({title:'Delete stat?',body:'This cannot be undone.',confirmText:'Delete',variant:'danger'}).then(ok=>{ if(ok) this.submit(); });">@csrf @method('DELETE')
                            <button class="text-rose-300 hover:text-rose-200"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">No stats yet. <a href="{{ route('admin.site-stats.create') }}" class="text-cyan-300">Add your first stat</a>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
