@php
    $isEdit = isset($tpl);
    $kind = $kind ?? ($tpl->getTable() === 'card_templates' ? 'card' : 'page');
    $action = $isEdit ? route('admin.templates.update', ['kind' => $kind, 'id' => $tpl->id]) : route('admin.templates.store');
    $name = old('name', $isEdit ? $tpl->name : '');
    $slug = old('slug', $isEdit ? $tpl->slug : '');
    $category = old('category', $isEdit ? $tpl->category : 'general');
    $description = old('description', $isEdit ? $tpl->description : '');
    $thumb = old('thumbnail_url', $isEdit ? $tpl->thumbnail_url : '');
    $planTier = old('plan_tier', $isEdit ? $tpl->plan_tier : '');
    $isActive = old('is_active', $isEdit ? ($tpl->is_active ? '1' : '0') : '1');
    $sortOrder = old('sort_order', $isEdit ? $tpl->sort_order : 0);
@endphp
<form action="{{ $action }}" method="POST" x-data="templateForm()" class="space-y-5">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <input type="hidden" name="kind" value="{{ $kind }}">

    <div class="glass rounded-2xl border border-white/10 p-5">
        <h3 class="text-sm font-semibold text-white mb-4">Basic Info</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1.5">Name <span class="text-red-400">*</span></label>
                <input type="text" name="name" value="{{ $name }}" required class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
                @error('name')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1.5">Slug</label>
                <input type="text" name="slug" value="{{ $slug }}" placeholder="auto from name" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
                @error('slug')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1.5">Category <span class="text-red-400">*</span></label>
                <select name="category" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" {{ $category === $key ? 'selected' : '' }} class="bg-[#0d0818]">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1.5">Plan Tier</label>
                <select name="plan_tier" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
                    <option value="" class="bg-[#0d0818]">Available to all plans</option>
                    @foreach($plans as $p)
                        <option value="{{ $p->slug }}" {{ $planTier === $p->slug ? 'selected' : '' }} class="bg-[#0d0818]">{{ $p->name }}</option>
                    @endforeach
                </select>
                <p class="text-[10px] text-white/30 mt-1">Lower-tier users will see a lock badge and upgrade prompt.</p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-white/60 mb-1.5">Description</label>
                <textarea name="description" rows="2" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">{{ $description }}</textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-white/60 mb-1.5">Thumbnail URL</label>
                <input type="url" name="thumbnail_url" value="{{ $thumb }}" placeholder="https://…/preview.png" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1.5">Sort Order</label>
                <input type="number" name="sort_order" value="{{ $sortOrder }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
            </div>
            <div class="flex items-end gap-3">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ $isActive ? 'checked' : '' }} class="rounded bg-white/5 border-white/20 text-violet-600">
                    <span class="text-sm text-white/70">Active (visible to users)</span>
                </label>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-5">
        <h3 class="text-sm font-semibold text-white mb-1">{{ $isEdit ? 'Re-capture Snapshot' : 'Snapshot Source' }}</h3>
        <p class="text-xs text-white/40 mb-4">
            @if($kind === 'card')
                Pick a biolink, then choose a card block from it to snapshot.
            @else
                Pick a biolink to snapshot all of its page settings + block tree.
            @endif
        </p>

        @if($isEdit)
            <label class="inline-flex items-center gap-2 mb-4 cursor-pointer">
                <input type="checkbox" name="recapture" value="1" x-model="recapture" class="rounded bg-white/5 border-white/20 text-violet-600">
                <span class="text-sm text-white/70">Replace stored snapshot with a new capture</span>
            </label>
        @endif

        <div :class="{{ $isEdit ? '!recapture && \'opacity-40 pointer-events-none\'' : 'false' }}">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2 relative">
                    <label class="block text-xs font-medium text-white/60 mb-1.5">Search biolink (title, alias, user email)</label>
                    <input type="text" x-model="search" @input.debounce.300ms="searchLinks()" placeholder="Type to search…" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
                    <div x-show="results.length" x-cloak class="absolute z-10 left-0 right-0 mt-1 max-h-60 overflow-y-auto rounded-xl border border-white/10 bg-[#0d0818] shadow-2xl">
                        <template x-for="r in results" :key="r.id">
                            <button type="button" @click="pickLink(r)" class="w-full text-left px-3 py-2 text-xs text-white/80 hover:bg-violet-600/20 border-b border-white/5">
                                <span x-text="r.label"></span>
                                <span class="text-white/30 text-[10px]" x-text="'#' + r.id"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-white/60 mb-1.5">Selected link ID</label>
                    <input type="number" name="source_link_id" x-model="sourceLinkId" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
                </div>
            </div>

            @if($kind === 'card')
                <div class="mt-3" x-show="cards.length" x-cloak>
                    <label class="block text-xs font-medium text-white/60 mb-1.5">Card block in this link</label>
                    <select name="source_card_id" x-model="sourceCardId" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
                        <option value="" class="bg-[#0d0818]">— pick a card —</option>
                        <template x-for="c in cards" :key="c.id">
                            <option :value="c.id" x-text="c.label" class="bg-[#0d0818]"></option>
                        </template>
                    </select>
                </div>
                <p x-show="sourceLinkId && !cards.length" x-cloak class="text-xs text-amber-400 mt-2">No card blocks found in that link.</p>
            @endif
            @error('source_link_id')<p class="text-red-400 text-xs mt-2">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.templates.index', ['tab' => $kind]) }}" class="px-5 py-2 text-sm text-white/40 hover:text-white">Cancel</a>
        <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2 rounded-xl text-sm font-medium">
            {{ $isEdit ? 'Save Changes' : 'Create Template' }}
        </button>
    </div>
</form>

<script>
function templateForm() {
    return {
        search: '',
        results: [],
        sourceLinkId: '',
        sourceCardId: '',
        cards: [],
        recapture: false,
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
