@extends('admin.layouts.app')
@section('title', 'Perfect Pairings')
@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <a href="{{ route('admin.site-pages.index') }}" class="text-xs text-blue-400 hover:underline ak-blue">
        <i class="fas fa-arrow-left mr-1"></i>Back to all pages
    </a>

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm ak-green">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <h1 class="text-lg font-semibold text-white ak-strong">Perfect Pairings</h1>
        <p class="text-xs text-white/50 mt-1 ak-muted">
            Control the "Perfect pairings" cross-promo cards shown on public link-type pages.
            Uncheck a card to hide it on that page type, everywhere: web public pages and the
            mobile app. Unchecking every card for a page type hides the whole section there.
            You can also edit each card's name and benefit text; leave a field blank (or use
            the per-card reset) to fall back to the shipped default wording.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.link-type-pairings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        @foreach($sections as $section)
            <div class="glass rounded-2xl p-6 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-white ak-strong">{{ $section['label'] }}</h2>
                    <span class="text-[11px] text-white/40 uppercase tracking-wider ak-note">{{ $section['key'] }}</span>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach($section['items'] as $item)
                        @php
                            $checked = !in_array($item['type'], $section['disabled'], true);
                            $customized = $item['name'] !== $item['default_name'] || $item['benefit'] !== $item['default_benefit'];
                        @endphp
                        <div class="p-3.5 rounded-xl border transition space-y-2.5
                                    {{ $checked ? 'bg-white/[.05] border-white/10' : 'bg-white/[.02] border-white/5 opacity-70' }}"
                             data-pairing-card>
                            <div class="flex items-start justify-between gap-3">
                                <label class="flex gap-3 items-center cursor-pointer min-w-0">
                                    <input type="checkbox"
                                           name="enabled[{{ $section['key'] }}][]"
                                           value="{{ $item['type'] }}"
                                           @checked($checked)
                                           class="h-4 w-4 rounded border-white/20 bg-transparent text-blue-500 focus:ring-blue-500/40">
                                    <span class="flex items-center gap-2 min-w-0">
                                        <i class="fas {{ $item['icon'] }} text-[12px] text-white/60 ak-muted"></i>
                                        <span class="block font-semibold text-sm text-white truncate ak-strong">{{ $item['default_name'] }}</span>
                                        <span data-custom-badge
                                              class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-400/15 text-amber-300 border border-amber-400/25 whitespace-nowrap {{ $customized ? '' : 'hidden' }} ak-amber">
                                            customized
                                        </span>
                                    </span>
                                </label>
                                <button type="button"
                                        data-reset-card
                                        title="Reset this card's wording to the shipped default"
                                        class="text-[11px] text-white/40 hover:text-white whitespace-nowrap transition ak-note">
                                    <i class="fas fa-rotate-left mr-1"></i>Reset
                                </button>
                            </div>
                            <div class="space-y-2">
                                <input type="text"
                                       name="copy[{{ $section['key'] }}][{{ $item['type'] }}][name]"
                                       value="{{ $item['name'] }}"
                                       maxlength="80"
                                       placeholder="{{ $item['default_name'] }}"
                                       data-copy-field
                                       data-default="{{ $item['default_name'] }}"
                                       class="w-full px-2.5 py-1.5 rounded-lg bg-white/[.04] border border-white/10 text-sm text-white placeholder-white/30 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/30 outline-none transition ak-strong">
                                <textarea name="copy[{{ $section['key'] }}][{{ $item['type'] }}][benefit]"
                                          rows="2"
                                          maxlength="220"
                                          placeholder="{{ $item['default_benefit'] }}"
                                          data-copy-field
                                          data-default="{{ $item['default_benefit'] }}"
                                          class="w-full px-2.5 py-1.5 rounded-lg bg-white/[.04] border border-white/10 text-xs text-white/80 placeholder-white/30 leading-relaxed resize-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/30 outline-none transition ak-strong">{{ $item['benefit'] }}</textarea>
                            </div>
                        </div>
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
        <button type="submit" class="px-4 py-2 rounded-xl border border-white/15 text-white/70 hover:text-white hover:border-white/30 text-xs font-semibold transition ak-strong">
            <i class="fas fa-rotate-left mr-1.5"></i>Restore defaults (enable all cards)
        </button>
    </form>
</div>

<script>
    // Per-card "Reset": restore both copy fields to the shipped defaults
    // (kept in data-default). Saving then drops the overrides because the
    // controller only stores values that differ from the default.
    document.querySelectorAll('[data-pairing-card]').forEach(function (card) {
        var resetBtn = card.querySelector('[data-reset-card]');
        var badge = card.querySelector('[data-custom-badge]');
        var fields = card.querySelectorAll('[data-copy-field]');

        function refreshBadge() {
            var customized = Array.prototype.some.call(fields, function (f) {
                return f.value.trim() !== '' && f.value.trim() !== f.dataset.default;
            });
            if (badge) badge.classList.toggle('hidden', !customized);
        }

        fields.forEach(function (f) { f.addEventListener('input', refreshBadge); });

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                fields.forEach(function (f) { f.value = f.dataset.default; });
                refreshBadge();
            });
        }
    });
</script>
@endsection
