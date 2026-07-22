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
@if($isEdit)
    <div class="glass rounded-2xl border border-white/10 p-5 mb-5">
        <h3 class="text-sm font-semibold text-white mb-1 ak-strong">Thumbnail Image</h3>
        <p class="text-xs text-white/40 mb-4 ak-note">
            Upload a finished screenshot of this {{ $kind === 'card' ? 'card' : 'page' }} to use as the gallery preview.
            When set, it overrides the auto-generated blueprint preview. PNG, JPG, WebP or GIF, up to 5&nbsp;MB.
        </p>
        <div class="flex items-start gap-4">
            <div class="w-40 aspect-[4/3] rounded-xl overflow-hidden border border-white/10 bg-black/30 flex items-center justify-center shrink-0">
                @if($tpl->thumbnail_url)
                    <img src="{{ $tpl->thumbnail_url }}" alt="Current thumbnail" class="w-full h-full object-cover">
                @else
                    <span class="text-[11px] text-white/30 px-2 text-center ak-note">No upload yet, auto-blueprint will be shown.</span>
                @endif
            </div>
            <div class="flex flex-col gap-2">
                <form action="{{ route('admin.templates.thumbnail.upload', ['kind' => $kind, 'id' => $tpl->id]) }}"
                      method="POST"
                      enctype="multipart/form-data"
                      class="flex items-center gap-2"
                      x-data="{ id: 'tpl-thumb-edit-{{ $kind }}-{{ $tpl->id }}', name: '' }">
                    @csrf
                    <input type="file"
                           name="thumbnail"
                           accept="image/png,image/jpeg,image/webp,image/gif"
                           class="hidden"
                           :id="id"
                           @change="name = $el.files[0]?.name || ''; if($el.files[0]) $el.form.submit();">
                    <button type="button"
                            @click="document.getElementById(id).click()"
                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium">
                        <i class="fas {{ $tpl->thumbnail_url ? 'fa-rotate' : 'fa-upload' }} mr-1.5 text-[10px]"></i>
                        {{ $tpl->thumbnail_url ? 'Replace image' : 'Upload image' }}
                    </button>
                    <span x-text="name" class="text-[11px] text-white/40 ak-note"></span>
                </form>
                @if($tpl->thumbnail_url)
                    <form action="{{ route('admin.templates.thumbnail.remove', ['kind' => $kind, 'id' => $tpl->id]) }}"
                          method="POST"
                          onsubmit="return window.themedConfirmSubmit ? window.themedConfirmSubmit(this, {title: 'Remove this thumbnail?', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-image'}) : confirm('Remove this thumbnail?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-3 py-1.5 bg-white/5 hover:bg-red-500/80 text-white/80 hover:text-white rounded-lg text-xs font-medium border border-white/10 ak-strong">
                            <i class="fas fa-trash mr-1.5 text-[10px]"></i>Remove image
                        </button>
                    </form>
                @endif
                @error('thumbnail')<p class="text-red-400 text-xs ak-red">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>
@endif

<form action="{{ $action }}" method="POST" x-data="templateForm()" class="space-y-5">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <input type="hidden" name="kind" value="{{ $kind }}">

    <div class="glass rounded-2xl border border-white/10 p-5">
        <h3 class="text-sm font-semibold text-white mb-4 ak-strong">Basic Info</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1.5 ak-muted">Name <span class="text-red-400 ak-red">*</span></label>
                <input type="text" name="name" value="{{ $name }}" required class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white ak-strong ak-input">
                @error('name')<p class="text-red-400 text-xs mt-1 ak-red">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1.5 ak-muted">Slug</label>
                <input type="text" name="slug" value="{{ $slug }}" placeholder="auto from name" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white ak-strong ak-input">
                @error('slug')<p class="text-red-400 text-xs mt-1 ak-red">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1.5 ak-muted">Category <span class="text-red-400 ak-red">*</span></label>
                <select name="category" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white ak-strong ak-input">
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" {{ $category === $key ? 'selected' : '' }} class="bg-[#0d0818]">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1.5 ak-muted">Plan Tier</label>
                <select name="plan_tier" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white ak-strong ak-input">
                    <option value="" class="bg-[#0d0818]">Available to all plans</option>
                    @foreach($plans as $p)
                        <option value="{{ $p->slug }}" {{ $planTier === $p->slug ? 'selected' : '' }} class="bg-[#0d0818]">{{ $p->name }}</option>
                    @endforeach
                </select>
                <p class="text-[10px] text-white/30 mt-1 ak-note">Lower-tier users will see a lock badge and upgrade prompt.</p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-white/60 mb-1.5 ak-muted">Description</label>
                <textarea name="description" rows="2" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white ak-strong ak-input">{{ $description }}</textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-white/60 mb-1.5 ak-muted">Thumbnail URL</label>
                <input type="url" name="thumbnail_url" value="{{ $thumb }}" placeholder="https://…/preview.png" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white ak-strong ak-input">
            </div>
            <div>
                <label class="block text-xs font-medium text-white/60 mb-1.5 ak-muted">Sort Order</label>
                <input type="number" name="sort_order" value="{{ $sortOrder }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white ak-strong ak-input">
            </div>
            <div class="flex items-end gap-3">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ $isActive ? 'checked' : '' }} class="rounded bg-white/5 border-white/20 text-blue-600 ak-input">
                    <span class="text-sm text-white/70 ak-strong">Active (visible to users)</span>
                </label>
            </div>
            @if($kind === 'page')
                @php
                    $selectedPersonas = old('recommended_personas', $isEdit ? ($tpl->recommended_personas ?? []) : []);
                    if (!is_array($selectedPersonas)) $selectedPersonas = [];
                    // Personas carried from the dashboard coverage warning are
                    // pre-checked on top of the existing tags (additive) so an
                    // admin can cover the gap in one save. Only prefill when the
                    // form wasn't just bounced back with validation errors.
                    $prefillPersonas = ($prefillPersonas ?? []);
                    if (!old('recommended_personas') && !empty($prefillPersonas)) {
                        $selectedPersonas = array_values(array_unique(array_merge($selectedPersonas, $prefillPersonas)));
                    }
                @endphp
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-white/60 mb-1.5 ak-muted">Recommended for personas</label>
                    <p class="text-[11px] text-white/30 mb-2 ak-note">Tag the personas this template fits best. Picked personas will see it first in the onboarding wizard and template picker. Leave all unchecked if it suits everyone equally.</p>
                    @if(!empty($prefillPersonas))
                        <p class="text-[11px] text-amber-300 mb-2 ak-amber">
                            <i class="fas fa-wand-magic-sparkles mr-1"></i>Pre-checked to cover
                            {{ count($prefillPersonas) === 1 ? 'a persona gap' : count($prefillPersonas) . ' persona gaps' }}
                            flagged on the dashboard, save to fix the coverage warning.
                        </p>
                    @endif
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-72 overflow-y-auto pr-1 rounded-xl">
                        @foreach(\App\Modules\User\Services\PersonaCatalog::all() as $p)
                            <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-white/10 bg-white/5 cursor-pointer hover:border-white/30">
                                <input type="checkbox" name="recommended_personas[]" value="{{ $p['slug'] }}" {{ in_array($p['slug'], $selectedPersonas, true) ? 'checked' : '' }} class="rounded bg-white/5 border-white/20 text-blue-600 ak-input">
                                <i class="fas {{ $p['icon'] }} text-blue-300 text-xs ak-blue"></i>
                                <span class="text-xs text-white/70 ak-strong">{{ $p['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-5">
        <h3 class="text-sm font-semibold text-white mb-1 ak-strong">{{ $isEdit ? 'Re-capture Snapshot' : 'Snapshot Source' }}</h3>
        <p class="text-xs text-white/40 mb-4 ak-note">
            @if($kind === 'card')
                Pick a Link in Bio page, then choose a card block from it to snapshot.
            @else
                Pick a Link in Bio page to snapshot all of its page settings + block tree.
            @endif
        </p>

        @if($isEdit)
            <label class="inline-flex items-center gap-2 mb-4 cursor-pointer">
                <input type="checkbox" name="recapture" value="1" x-model="recapture" class="rounded bg-white/5 border-white/20 text-blue-600 ak-input">
                <span class="text-sm text-white/70 ak-strong">Replace stored snapshot with a new capture</span>
            </label>
        @endif

        <div :class="{{ $isEdit ? '!recapture && !showJson && \'opacity-40 pointer-events-none\'' : 'false' }}">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2 relative">
                    <label class="block text-xs font-medium text-white/60 mb-1.5 ak-muted">Search Link in Bio page (title, alias, user email)</label>
                    <input type="text" x-model="search" @input.debounce.300ms="searchLinks()" placeholder="Type to search…" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white ak-strong ak-input">
                    <div x-show="results.length" x-cloak class="absolute z-10 left-0 right-0 mt-1 max-h-60 overflow-y-auto rounded-xl border border-white/10 bg-[#0d0818] shadow-2xl">
                        <template x-for="r in results" :key="r.id">
                            <button type="button" @click="pickLink(r)" class="w-full text-left px-3 py-2 text-xs text-white/80 hover:bg-blue-600/20 border-b border-white/5 ak-strong">
                                <span x-text="r.label"></span>
                                <span class="text-white/30 text-[10px] ak-note" x-text="'#' + r.id"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-white/60 mb-1.5 ak-muted">Selected link ID</label>
                    <input type="number" name="source_link_id" x-model="sourceLinkId" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white ak-strong ak-input">
                </div>
            </div>

            @if($kind === 'card')
                <div class="mt-3" x-show="cards.length" x-cloak>
                    <label class="block text-xs font-medium text-white/60 mb-1.5 ak-muted">Card block in this link</label>
                    <select name="source_card_id" x-model="sourceCardId" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white ak-strong ak-input">
                        <option value="" class="bg-[#0d0818]">pick a card</option>
                        <template x-for="c in cards" :key="c.id">
                            <option :value="c.id" x-text="c.label" class="bg-[#0d0818]"></option>
                        </template>
                    </select>
                </div>
                <p x-show="sourceLinkId && !cards.length" x-cloak class="text-xs text-amber-400 mt-2 ak-amber">No card blocks found in that link.</p>
            @endif
            @error('source_link_id')<p class="text-red-400 text-xs mt-2 ak-red">{{ $message }}</p>@enderror
        </div>

        <div class="mt-5 pt-5 border-t border-white/5">
            <label class="inline-flex items-center gap-2 mb-3 cursor-pointer">
                <input type="checkbox" x-model="showJson" class="rounded bg-white/5 border-white/20 text-blue-600 ak-input">
                <span class="text-sm text-white/70 font-medium ak-strong">Advanced: edit snapshot JSON directly</span>
            </label>
            <div x-show="showJson" x-cloak>
                <p class="text-xs text-white/40 mb-2 ak-note">Paste/edit raw snapshot JSON. If valid, this will override any captured snapshot. All block payloads are re-sanitized on apply.</p>
                <textarea name="snapshot_json" rows="14" spellcheck="false"
                    x-model="snapshotJson" @input.debounce.500ms="validateSnapshot()"
                    class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-xs text-emerald-300 font-mono ak-green"
                    placeholder='{"blocks":[…]}'>{{ old('snapshot_json', $isEdit ? json_encode($tpl->snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
                @error('snapshot_json')<p class="text-red-400 text-xs mt-1 ak-red">{{ $message }}</p>@enderror

                <div class="mt-2 min-h-[1.25rem]" aria-live="polite">
                    <p x-show="checking" x-cloak class="text-[11px] text-white/40 ak-note">
                        <i class="fas fa-circle-notch fa-spin mr-1"></i>Checking design…
                    </p>
                    <div x-show="!checking && snapshotIssues.length" x-cloak
                        class="rounded-xl border border-red-500/30 bg-red-500/10 p-3">
                        <p class="text-xs font-medium text-red-300 mb-1.5 ak-red">
                            <i class="fas fa-triangle-exclamation mr-1"></i>This snapshot has design problems that would silently degrade on the public page:
                        </p>
                        <ul class="list-disc list-inside space-y-1">
                            <template x-for="issue in snapshotIssues" :key="issue">
                                <li class="text-[11px] text-red-200/90 ak-red" x-text="issue"></li>
                            </template>
                        </ul>
                        <p class="text-[10px] text-red-200/60 mt-1.5 ak-red">Saving is still allowed for valid JSON, but these blocks won't render as designed.</p>
                    </div>
                    <p x-show="!checking && snapshotChecked && !snapshotIssues.length && snapshotJson.trim()" x-cloak
                        class="text-[11px] text-emerald-300 ak-green">
                        <i class="fas fa-circle-check mr-1"></i>No design issues found.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.templates.index', ['tab' => $kind]) }}" class="px-5 py-2 text-sm text-white/40 hover:text-white ak-note">Cancel</a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-xl text-sm font-medium">
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
        showJson: false,
        snapshotJson: @json(old('snapshot_json', $isEdit ? json_encode($tpl->snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '')),
        snapshotIssues: [],
        snapshotChecked: false,
        checking: false,
        _validateSeq: 0,
        init() {
            // Validate any pre-filled JSON (edit page or a bounced-back create form)
            // so issues surface on load, not just on the next keystroke.
            if (this.snapshotJson && this.snapshotJson.trim()) this.validateSnapshot();
        },
        validateSnapshot() {
            const json = (this.snapshotJson || '').trim();
            if (!json) { this.snapshotIssues = []; this.snapshotChecked = false; this.checking = false; return; }
            const seq = ++this._validateSeq;
            this.checking = true;
            fetch('{{ route('admin.templates.validate-snapshot') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ kind: '{{ $kind }}', snapshot_json: json }),
            })
                .then(r => r.json())
                .then(d => {
                    if (seq !== this._validateSeq) return; // a newer request superseded this one
                    this.snapshotIssues = d.issues || [];
                    this.snapshotChecked = true;
                    this.checking = false;
                })
                .catch(() => {
                    if (seq !== this._validateSeq) return;
                    this.checking = false;
                });
        },
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
