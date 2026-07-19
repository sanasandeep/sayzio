@extends('admin.layouts.app')
@section('title', 'Fix design issues')
@section('page-title', 'Fix design issues, ' . $tpl->name)

@php
    $snapshotJson = json_encode($tpl->snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $stripFullyFixes = empty($afterStrip);
@endphp

@section('content')
<div class="max-w-5xl" x-data="designFix()">
    <a href="{{ route('admin.templates.index', ['tab' => $kind]) }}" class="text-xs text-white/40 hover:text-white mb-4 inline-block">
        <i class="fas fa-arrow-left mr-1"></i>Back to templates
    </a>

    <div class="glass rounded-2xl border border-red-500/30 bg-red-500/5 p-4 mb-5">
        <div class="flex items-start gap-3">
            <i class="fas fa-bug text-red-400 text-lg mt-0.5"></i>
            <div class="flex-1">
                <div class="text-sm font-semibold text-white mb-1">
                    This {{ $kind }} template's stored design has {{ count($issues) }} issue{{ count($issues) === 1 ? '' : 's' }}
                </div>
                <p class="text-xs text-white/60">
                    Unknown block types and stale design-variant keys silently fall back to
                    default styling on the public page, visitors see a broken or plain version
                    of this template. Fix it below, then the "Design issues" badge will clear.
                </p>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-5 mb-5">
        <h3 class="text-sm font-semibold text-white mb-3">Concrete issues found</h3>
        <ul class="space-y-2">
            @foreach($issues as $issue)
                <li class="flex items-start gap-2 text-xs text-white/70">
                    <i class="fas fa-circle-exclamation text-red-400 mt-0.5 text-[11px]"></i>
                    <span>{{ $issue }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    @if($errors->any())
        <div class="glass rounded-xl border border-red-500/30 bg-red-500/5 p-3 mb-5 text-xs text-red-300">
            <ul class="space-y-1">
                @foreach($errors->all() as $error)
                    <li><i class="fas fa-circle-exclamation mr-1"></i>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
        {{-- Option A: strip stale variant keys --}}
        <div class="glass rounded-2xl border border-white/10 p-5 flex flex-col">
            <h3 class="text-sm font-semibold text-white mb-1">
                <i class="fas fa-eraser text-blue-300 mr-1"></i>Strip stale design-variant keys
            </h3>
            <p class="text-xs text-white/50 mb-3 flex-1">
                Surgically removes only the design-variant keys that no longer resolve, so each
                affected block reverts to its default styling. All other content and styling is
                kept exactly as-is.
                @if($stripFullyFixes)
                    <span class="block mt-2 text-emerald-300"><i class="fas fa-check mr-1"></i>This alone fully resolves every issue on this template.</span>
                @else
                    <span class="block mt-2 text-amber-300"><i class="fas fa-triangle-exclamation mr-1"></i>This won't fully fix the row, other issues (e.g. unknown block types) would remain. Use re-capture instead.</span>
                @endif
            </p>
            <form action="{{ route('admin.templates.design.repair', ['kind' => $kind, 'id' => $tpl->id]) }}"
                  method="POST"
                  onsubmit="return window.themedConfirmSubmit(this, {title: 'Strip stale variant keys?', body: 'This permanently removes the unresolved design-variant keys from the stored snapshot.', confirmText: 'Strip', confirmIcon: 'fa-eraser', iconClass: 'fa-eraser'})">
                @csrf
                <input type="hidden" name="mode" value="strip">
                <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-semibold">
                    <i class="fas fa-eraser mr-1"></i>Strip stale variant keys
                </button>
            </form>
        </div>

        {{-- Option B: re-capture from a source --}}
        <div class="glass rounded-2xl border border-white/10 p-5">
            <h3 class="text-sm font-semibold text-white mb-1">
                <i class="fas fa-camera text-blue-300 mr-1"></i>Re-capture from a source
            </h3>
            <p class="text-xs text-white/50 mb-3">
                @if($kind === 'card')
                    Pick a Link in Bio page, choose a card block from it, and replace this
                    template's snapshot with a fresh, valid capture.
                @else
                    Pick a Link in Bio page and replace this template's snapshot with a fresh,
                    valid capture of its full block tree.
                @endif
            </p>
            <form action="{{ route('admin.templates.design.repair', ['kind' => $kind, 'id' => $tpl->id]) }}" method="POST">
                @csrf
                <input type="hidden" name="mode" value="recapture">

                <div class="relative mb-3">
                    <label class="block text-xs font-medium text-white/60 mb-1.5">Search Link in Bio page (title, alias, user email)</label>
                    <input type="text" x-model="search" @input.debounce.300ms="searchLinks()" placeholder="Type to search…" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
                    <div x-show="results.length" x-cloak class="absolute z-10 left-0 right-0 mt-1 max-h-60 overflow-y-auto rounded-xl border border-white/10 bg-[#0d0818] shadow-2xl">
                        <template x-for="r in results" :key="r.id">
                            <button type="button" @click="pickLink(r)" class="w-full text-left px-3 py-2 text-xs text-white/80 hover:bg-blue-600/20 border-b border-white/5">
                                <span x-text="r.label"></span>
                                <span class="text-white/30 text-[10px]" x-text="'#' + r.id"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="block text-xs font-medium text-white/60 mb-1.5">Selected link ID</label>
                    <input type="number" name="source_link_id" x-model="sourceLinkId" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
                </div>

                @if($kind === 'card')
                    <div class="mb-3" x-show="cards.length" x-cloak>
                        <label class="block text-xs font-medium text-white/60 mb-1.5">Card block in this link</label>
                        <select name="source_card_id" x-model="sourceCardId" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
                            <option value="" class="bg-[#0d0818]">pick a card</option>
                            <template x-for="c in cards" :key="c.id">
                                <option :value="c.id" x-text="c.label" class="bg-[#0d0818]"></option>
                            </template>
                        </select>
                    </div>
                    <p x-show="sourceLinkId && !cards.length" x-cloak class="text-xs text-amber-400 mb-3">No card blocks found in that link.</p>
                @endif

                <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold">
                    <i class="fas fa-camera mr-1"></i>Re-capture &amp; save
                </button>
            </form>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-4">
        <div class="flex items-center justify-between mb-2">
            <div class="text-[10px] uppercase tracking-wide text-white/40">Current stored snapshot JSON</div>
            <a href="{{ route('admin.templates.edit', ['kind' => $kind, 'id' => $tpl->id]) }}" class="text-xs text-white/40 hover:text-white">
                <i class="fas fa-edit mr-1"></i>Edit manually instead
            </a>
        </div>
        <pre class="text-[11px] text-white/70 bg-black/40 rounded-lg p-3 overflow-auto max-h-[50vh] whitespace-pre-wrap break-words">{{ $snapshotJson }}</pre>
    </div>
</div>

<script>
function designFix() {
    return {
        search: '',
        results: [],
        sourceLinkId: '',
        sourceCardId: '',
        cards: [],
        searchLinks() {
            if (this.search.trim().length < 1) { this.results = []; return; }
            fetch('{{ route('admin.templates.search-links') }}?kind={{ $kind }}&q=' + encodeURIComponent(this.search), { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(d => { this.results = d.items || []; });
        },
        pickLink(r) {
            this.sourceLinkId = r.id;
            this.search = r.label;
            this.results = [];
            this.cards = r.cards || [];
            this.sourceCardId = '';
        },
    };
}
</script>
@endsection
