@extends('admin.layouts.app')
@section('title', 'Event Categories')
@section('page-title', 'Event Categories')

@section('content')
<div class="max-w-6xl space-y-6">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90 ak-strong">/events browse-by-category tiles</h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl ak-muted">
                    Every enabled category appears on the public <code class="text-white/60 ak-muted">/events</code> page,
                    ordered by how many upcoming events use it. Disabling a category hides it from the browse row
                    without touching events already saved under it. Deleting it makes those events fall back to a
                    guessed icon/label instead.
                </p>
            </div>
            <a href="{{ route('admin.event-categories.create') }}"
               class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white inline-flex items-center gap-2">
                <i class="fas fa-plus text-xs"></i> New category
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm ak-green">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] uppercase tracking-wider text-white/40 border-b border-white/10 ak-note">
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3">Sort</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($categories as $cat)
                    <tr class="border-b border-white/5 last:border-0">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-white text-sm flex-shrink-0 ak-strong"
                                     style="background: linear-gradient(135deg, {{ $cat->color_from }} 0%, {{ $cat->color_to }} 100%);">
                                    <i class="fas {{ $cat->icon }}"></i>
                                </div>
                                <span class="text-white/90 font-medium ak-strong">{{ $cat->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-white/50 text-xs ak-muted">{{ $cat->slug }}</td>
                        <td class="px-4 py-3 text-white/50 ak-muted">{{ $cat->sort_order }}</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('admin.event-categories.toggle', $cat) }}">
                                @csrf
                                <button type="submit"
                                        class="text-[11px] font-semibold px-2.5 py-1 rounded-full {{ $cat->is_enabled ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 ak-green' : 'bg-white/5 text-white/50 border border-white/10 ak-muted' }}">
                                    <i class="fas fa-{{ $cat->is_enabled ? 'eye' : 'eye-slash' }} text-[10px] mr-1"></i>
                                    {{ $cat->is_enabled ? 'Enabled' : 'Disabled' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('admin.event-categories.edit', $cat) }}"
                                   class="text-[11px] font-semibold px-2 py-1.5 rounded-md bg-blue-600/20 hover:bg-blue-600/30 text-blue-200 ak-blue">
                                    <i class="fas fa-pen text-[10px] mr-1"></i> Edit
                                </a>
                                <form method="POST" action="{{ route('admin.event-categories.destroy', $cat) }}"
                                      onsubmit="return confirm('Delete &quot;{{ addslashes($cat->name) }}&quot;? Events already using it will fall back to a guessed icon/label.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-[11px] font-semibold px-2 py-1.5 rounded-md bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 ak-red">
                                        <i class="fas fa-trash text-[10px]"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($categories->isEmpty())
            <div class="p-8 text-center text-white/60 text-sm ak-muted">No categories yet.</div>
        @endif
    </div>
</div>
@endsection
