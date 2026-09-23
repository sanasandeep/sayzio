@extends('admin.layouts.app')
@section('title', 'Background Library')
@section('page-title', 'Background Library')

@section('content')
<div class="max-w-7xl space-y-6">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90 ak-strong">The looks that used to need a deploy</h2>
                <p class="text-xs text-white/50 mt-1 max-w-3xl ak-muted">
                    Presets, gradients, mesh, patterns, tiles and torn paper all ship compiled into the code.
                    Editing one here creates an override, and deleting that override puts the shipped version back,
                    so nothing you do on this page can lose a default.
                    <strong class="text-white/70 ak-strong">Hiding</strong> takes a look out of the picker and leaves it
                    rendering on pages that already chose it.
                    Animated and illustrated backgrounds live in
                    <a href="{{ route('admin.bg-templates.index') }}" class="text-blue-300 underline ak-blue">Background Templates</a>.
                </p>
            </div>
            <a href="{{ route('admin.bg-catalog.create', $kind) }}"
               class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white inline-flex items-center gap-2 shrink-0">
                <i class="fas fa-plus text-xs"></i> New {{ strtolower($kinds[$kind]) }}
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.bg-catalog.index', $kind) }}"
          class="glass rounded-2xl border border-white/10 p-4 space-y-3">
        <div class="flex items-center gap-1.5 flex-wrap">
            @foreach($kinds as $k => $kLabel)
                <a href="{{ route('admin.bg-catalog.index', $k) }}"
                   class="text-[12px] font-semibold px-3 py-1.5 rounded-full {{ $kind === $k ? 'bg-blue-600/30 text-blue-200 border border-blue-500/40 ak-blue' : 'bg-white/5 text-white/70 border border-white/10 ak-strong' }}">
                    {{ $kLabel }} <span class="opacity-60">{{ $counts[$k] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by name or key…"
                   class="flex-1 min-w-[220px] bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white ak-strong">
            <button type="submit" class="px-3 py-2 rounded-lg text-sm font-medium bg-white/10 hover:bg-white/15 text-white/90 ak-strong">
                Search
            </button>
            <span class="text-[11px] text-white/40 ak-note">
                {{ count($items) }} shown · {{ $customCount }} edited or added here
            </span>
        </div>
    </form>

    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
        @foreach($items as $item)
            @php
                $badge = match ($item['origin']) {
                    'custom'   => ['Custom',  'bg-emerald-500/25 text-emerald-100'],
                    'override' => ['Edited',  'bg-blue-500/25 text-blue-100'],
                    default    => null,
                };
            @endphp
            <div class="glass rounded-2xl border {{ $item['is_active'] ? 'border-white/10' : 'border-amber-500/30' }} p-3 flex flex-col gap-2">
                <div class="rounded-xl overflow-hidden relative" style="aspect-ratio: 9/16; {{ $item['swatch'] }}">
                    @if(!$item['is_active'])
                        <div class="absolute top-1.5 left-1.5 text-[10px] px-1.5 py-0.5 rounded-md bg-amber-500/30 text-amber-100 backdrop-blur ak-amber">Hidden</div>
                    @elseif($badge)
                        <div class="absolute top-1.5 left-1.5 text-[10px] px-1.5 py-0.5 rounded-md {{ $badge[1] }} backdrop-blur ak-strong">{{ $badge[0] }}</div>
                    @endif
                </div>
                <div>
                    <p class="text-sm font-semibold text-white/90 truncate ak-strong" title="{{ $item['label'] }}">{{ $item['label'] }}</p>
                    <p class="text-[10px] text-white/40 font-mono truncate ak-note">{{ $item['key'] }}</p>
                </div>
                <div class="flex items-center gap-1.5 mt-auto">
                    <a href="{{ route('admin.bg-catalog.edit', [$kind, $item['key']]) }}"
                       class="flex-1 text-center text-[11px] font-semibold px-2 py-1.5 rounded-md bg-blue-600/20 hover:bg-blue-600/30 text-blue-200 ak-blue">
                        <i class="fas fa-pen text-[10px] mr-1"></i> Edit
                    </a>
                    <form method="POST" action="{{ route('admin.bg-catalog.toggle', [$kind, $item['key']]) }}" class="inline">
                        @csrf
                        <button type="submit" title="{{ $item['is_active'] ? 'Hide from the picker' : 'Show in the picker' }}"
                                class="text-[11px] font-semibold px-2 py-1.5 rounded-md bg-white/5 hover:bg-white/10 text-white/80 ak-strong">
                            <i class="fas fa-{{ $item['is_active'] ? 'eye' : 'eye-slash' }} text-[10px]"></i>
                        </button>
                    </form>
                    @if($item['origin'] !== 'shipped')
                        <form method="POST" action="{{ route('admin.bg-catalog.destroy', [$kind, $item['key']]) }}"
                              onsubmit="return confirm('{{ $item['origin'] === 'override' ? 'Put this look back to the version that ships with the code?' : 'Delete this look? Pages already using it will fall back to their plain background.' }}');"
                              class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" title="{{ $item['origin'] === 'override' ? 'Restore the shipped version' : 'Delete' }}"
                                    class="text-[11px] font-semibold px-2 py-1.5 rounded-md {{ $item['origin'] === 'override' ? 'bg-white/5 hover:bg-white/10 text-white/80 ak-strong' : 'bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 ak-red' }}">
                                <i class="fas fa-{{ $item['origin'] === 'override' ? 'rotate-left' : 'trash' }} text-[10px]"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if(empty($items))
        <div class="glass rounded-2xl border border-white/10 p-8 text-center text-white/60 text-sm ak-muted">
            Nothing here matches “{{ $q }}”.
        </div>
    @endif
</div>
@endsection
