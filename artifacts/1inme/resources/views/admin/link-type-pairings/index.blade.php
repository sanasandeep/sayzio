@extends('admin.layouts.app')
@section('title', 'Perfect Pairings')
@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <a href="{{ route('admin.site-pages.index') }}" class="text-xs text-blue-400 hover:underline">
        <i class="fas fa-arrow-left mr-1"></i>Back to all pages
    </a>

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <h1 class="text-lg font-semibold text-white">Perfect Pairings</h1>
        <p class="text-xs text-white/50 mt-1">
            Control the "Perfect pairings" cross-promo cards shown on public link-type pages.
            Uncheck a card to hide it on that page type — everywhere: web public pages and the
            mobile app. Unchecking every card for a page type hides the whole section there.
            The card copy itself is code-defined and not editable here.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.link-type-pairings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        @foreach($sections as $section)
            <div class="glass rounded-2xl p-6 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-white">{{ $section['label'] }}</h2>
                    <span class="text-[11px] text-white/40 uppercase tracking-wider">{{ $section['key'] }}</span>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach($section['items'] as $item)
                        @php $checked = !in_array($item['type'], $section['disabled'], true); @endphp
                        <label class="flex gap-3 items-start p-3.5 rounded-xl border cursor-pointer transition
                                      {{ $checked ? 'bg-white/[.05] border-white/10' : 'bg-white/[.02] border-white/5 opacity-70' }}">
                            <input type="checkbox"
                                   name="enabled[{{ $section['key'] }}][]"
                                   value="{{ $item['type'] }}"
                                   @checked($checked)
                                   class="mt-0.5 h-4 w-4 rounded border-white/20 bg-transparent text-blue-500 focus:ring-blue-500/40">
                            <span class="min-w-0">
                                <span class="flex items-center gap-2">
                                    <i class="fas {{ $item['icon'] }} text-[12px] text-white/60"></i>
                                    <span class="block font-semibold text-sm text-white">{{ $item['name'] }}</span>
                                </span>
                                <span class="block text-xs text-white/50 mt-1 leading-relaxed">{{ $item['benefit'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex items-center gap-3">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold transition">
                <i class="fas fa-floppy-disk mr-1.5"></i>Save changes
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.link-type-pairings.restore-defaults') }}">
        @csrf
        <button type="submit" class="px-4 py-2 rounded-xl border border-white/15 text-white/70 hover:text-white hover:border-white/30 text-xs font-semibold transition">
            <i class="fas fa-rotate-left mr-1.5"></i>Restore defaults (enable all cards)
        </button>
    </form>
</div>
@endsection
