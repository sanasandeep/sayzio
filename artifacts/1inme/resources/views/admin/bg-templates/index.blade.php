@extends('admin.layouts.app')
@section('title', 'Background Templates')
@section('page-title', 'Background Templates')

@section('content')
<div class="max-w-7xl space-y-6">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90">Link in Bio background templates</h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl">
                    Animated, gradient and pattern backgrounds that users can apply to their Link in Bio pages.
                    Each template renders as <code class="text-white/60">.bg-template-&lt;slug&gt;</code>. Toggle
                    visibility to hide a template from the user picker without deleting it.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.bg-templates.create') }}"
                   class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white inline-flex items-center gap-2">
                    <i class="fas fa-plus text-xs"></i> New template
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.bg-templates.index') }}"
          class="glass rounded-2xl border border-white/10 p-4 flex items-center gap-3 flex-wrap">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search by name or slug…"
               class="flex-1 min-w-[220px] bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white">
        <div class="flex items-center gap-1.5 flex-wrap">
            <a href="{{ route('admin.bg-templates.index', ['q' => $q]) }}"
               class="text-[12px] font-semibold px-3 py-1.5 rounded-full {{ $currentCat === '' ? 'bg-blue-600/30 text-blue-200 border border-blue-500/40' : 'bg-white/5 text-white/70 border border-white/10' }}">
                All <span class="opacity-60">{{ $totalCount }}</span>
            </a>
            @foreach($categories as $cat)
                <a href="{{ route('admin.bg-templates.index', ['q' => $q, 'cat' => $cat]) }}"
                   class="text-[12px] font-semibold px-3 py-1.5 rounded-full {{ $currentCat === $cat ? 'bg-blue-600/30 text-blue-200 border border-blue-500/40' : 'bg-white/5 text-white/70 border border-white/10' }}">
                    {{ ucfirst($cat) }} <span class="opacity-60">{{ $categoryCounts[$cat] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
        <button type="submit" class="px-3 py-2 rounded-lg text-sm font-medium bg-white/10 hover:bg-white/15 text-white/90">
            Apply
        </button>
    </form>

    {{-- Live previews for the visible page (scoped to .bg-thumb-<slug>). --}}
    <style>
    @foreach($templates as $tpl)
    {!! str_replace(['.bg-template-','position:fixed','position: fixed','z-index:-1','z-index: -1'], ['.bg-thumb-','position:absolute','position:absolute','z-index:0','z-index:0'], $tpl->css) !!}
    @endforeach
    </style>

    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
        @foreach($templates as $tpl)
            <div class="glass rounded-2xl border {{ $tpl->is_active ? 'border-white/10' : 'border-amber-500/30' }} p-3 flex flex-col gap-2">
                <div class="rounded-xl overflow-hidden relative" style="aspect-ratio: 9/16; background: {{ $tpl->preview_color }};">
                    <div class="bg-thumb-{{ $tpl->slug }}" style="position:absolute;inset:0;"></div>
                    @if(!$tpl->is_active)
                        <div class="absolute top-1.5 left-1.5 text-[10px] px-1.5 py-0.5 rounded-md bg-amber-500/30 text-amber-100 backdrop-blur">Hidden</div>
                    @endif
                    <div class="absolute bottom-1.5 right-1.5 text-[10px] px-1.5 py-0.5 rounded-md bg-black/50 text-white/80 backdrop-blur">{{ $tpl->category }}</div>
                </div>
                <div>
                    <p class="text-sm font-semibold text-white/90 truncate" title="{{ $tpl->name }}">{{ $tpl->name }}</p>
                    <p class="text-[10px] text-white/40 font-mono truncate">{{ $tpl->slug }}</p>
                </div>
                <div class="flex items-center gap-1.5 mt-auto">
                    <a href="{{ route('admin.bg-templates.edit', $tpl) }}"
                       class="flex-1 text-center text-[11px] font-semibold px-2 py-1.5 rounded-md bg-blue-600/20 hover:bg-blue-600/30 text-blue-200">
                        <i class="fas fa-pen text-[10px] mr-1"></i> Edit
                    </a>
                    <form method="POST" action="{{ route('admin.bg-templates.toggle', $tpl) }}" class="inline">
                        @csrf
                        <button type="submit" title="{{ $tpl->is_active ? 'Hide from users' : 'Show to users' }}"
                                class="text-[11px] font-semibold px-2 py-1.5 rounded-md bg-white/5 hover:bg-white/10 text-white/80">
                            <i class="fas fa-{{ $tpl->is_active ? 'eye' : 'eye-slash' }} text-[10px]"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.bg-templates.destroy', $tpl) }}"
                          onsubmit="return confirm('Delete &quot;{{ addslashes($tpl->name) }}&quot;? This cannot be undone.');" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" title="Delete"
                                class="text-[11px] font-semibold px-2 py-1.5 rounded-md bg-rose-500/20 hover:bg-rose-500/30 text-rose-200">
                            <i class="fas fa-trash text-[10px]"></i>
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    @if($templates->isEmpty())
        <div class="glass rounded-2xl border border-white/10 p-8 text-center text-white/60 text-sm">
            No templates match your filters.
        </div>
    @endif

    <div>{{ $templates->links() }}</div>
</div>
@endsection
