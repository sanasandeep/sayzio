@php
    /**
     * Searchable font picker — drop in anywhere we used to render a hard-
     * coded <select name="..."> with 8 fonts.
     *
     * Required vars:
     *   $name     — input name (e.g. "font_family", "block_theme[font_family]",
     *               "style[font_family]")
     *   $value    — currently-selected family / token (may be 'custom:Foo' for
     *               an uploaded font, '' for "inherit").
     * Optional:
     *   $allowInherit — show an "Inherit from page" option (default false).
     *   $pickerId     — unique id for Alpine scope when multiple pickers
     *                   appear on a page (auto-generated if omitted).
     *
     * The picker shows "My Fonts" at the top, then Google Fonts grouped by
     * category. Search filters by family name. Selected family is mirrored
     * into a hidden <input name=$name> so the surrounding form serializes it
     * exactly the same as the old <select>.
     *
     * Selected fonts are loaded on-demand via a <link rel=stylesheet> we
     * append to <head>, so the in-picker preview shows the actual face. The
     * public biolink page re-loads them server-side via FontCatalog.
     */
    use App\Modules\User\Support\FontCatalog;
    $pickerId = $pickerId ?? ('fontPicker_' . uniqid());
    $allowInherit = $allowInherit ?? false;
    $fontEntries = FontCatalog::all();
    $fontCats = FontCatalog::CATEGORIES;
    // Pre-load currently-selected family if it's a known Google Font so the
    // input shows the real face on first render. Custom fonts get their
    // @font-face injected via the customFonts JSON below.
    $selectedFamily = (string) $value;
    $selectedIsCustom = str_starts_with($selectedFamily, 'custom:');
    $selectedDisplayName = $selectedIsCustom ? substr($selectedFamily, 7) : $selectedFamily;
    $customFonts = auth()->check() ? auth()->user()->customFonts()->orderBy('family')->get() : collect();
@endphp

<div x-data="fontPickerComponent_{{ $pickerId }}()" x-init="init()" class="font-picker" data-picker-id="{{ $pickerId }}">
    <input type="hidden" name="{{ $name }}" :value="selected">

    {{-- Trigger: shows current selection, opens picker on click. --}}
    <button type="button" @click="open = !open" class="theme-input w-full flex items-center justify-between text-left" style="min-height: 38px;">
        <span class="truncate" :style="selected ? ('font-family: ' + previewFamilyCss(selected) + ', sans-serif;') : ''">
            <span x-show="!selected" class="opacity-60">{{ $allowInherit ? 'Inherit from page' : 'Pick a font' }}</span>
            <span x-show="selected" x-text="selectedLabel()"></span>
        </span>
        <i class="fas fa-chevron-down text-[10px] opacity-60 ml-2"></i>
    </button>

    {{-- Dropdown panel --}}
    <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity
         class="mt-1 rounded-xl p-3 space-y-3"
         style="background: var(--bg-glass); border: 1px solid var(--border-glass); max-height: 480px; overflow-y: auto; backdrop-filter: blur(16px);">

        <div class="flex items-center gap-2">
            <input type="text" x-model="search" placeholder="Search fonts…"
                   class="theme-input w-full" style="padding: 6px 10px; font-size: 12px;">
            @if($allowInherit)
            <button type="button" @click="select(''); open = false"
                    class="text-[10px] px-2 py-1.5 rounded-lg whitespace-nowrap"
                    style="background: var(--bg-glass-input); color: var(--text-faint);">
                Inherit
            </button>
            @endif
        </div>

        {{-- Category chips --}}
        <div class="flex flex-wrap gap-1">
            <button type="button" @click="cat = 'all'"
                    :class="cat === 'all' ? 'bg-blue-600 text-white' : ''"
                    class="text-[10px] font-semibold px-2 py-1 rounded"
                    style="background: var(--bg-glass-input); color: var(--text-faint);">All</button>
            <button type="button" @click="cat = 'mine'"
                    :class="cat === 'mine' ? 'bg-blue-600 text-white' : ''"
                    class="text-[10px] font-semibold px-2 py-1 rounded"
                    style="background: var(--bg-glass-input); color: var(--text-faint);">
                <i class="fas fa-star text-[8px] mr-1"></i>My Fonts (<span x-text="customFonts.length"></span>)
            </button>
            @foreach($fontCats as $catKey => $catLabel)
            <button type="button" @click="cat = '{{ $catKey }}'"
                    :class="cat === '{{ $catKey }}' ? 'bg-blue-600 text-white' : ''"
                    class="text-[10px] font-semibold px-2 py-1 rounded"
                    style="background: var(--bg-glass-input); color: var(--text-faint);">{{ $catLabel }}</button>
            @endforeach
        </div>

        {{-- "My Fonts" section: pinned at top whenever there are any uploads
             AND we're either on the All filter or the My Fonts filter. --}}
        <div x-show="customFonts.length > 0 && (cat === 'all' || cat === 'mine') && (search === '' || matchesSearch('mine'))" class="space-y-1">
            <div class="flex items-center justify-between">
                <p class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--text-muted);">My Fonts</p>
                <button type="button" @click="$refs.fileInput.click()"
                        class="text-[10px] font-semibold px-2 py-0.5 rounded"
                        style="color: #90acff; background: rgba(61,107,255,0.08);">
                    <i class="fas fa-upload text-[8px] mr-1"></i>Upload
                </button>
            </div>
            <template x-for="font in filteredCustomFonts()" :key="font.id">
                <div class="group flex items-center justify-between rounded-lg px-2 py-1.5 cursor-pointer hover:bg-white/5"
                     :style="selected === font.token ? 'background: rgba(61,107,255,0.18); border: 1px solid rgba(61,107,255,0.3);' : ''"
                     @click="select(font.token)">
                    <span class="text-sm truncate" :style="'font-family: ' + safeQuote(font.family) + ', sans-serif;'" x-text="font.family"></span>
                    <button type="button" @click.stop="removeCustomFont(font.id)"
                            class="text-[10px] opacity-60 group-hover:opacity-100 hover:!opacity-100 px-1.5"
                            style="color: var(--text-faint);"
                            title="Delete">
                        <i class="fas fa-trash text-[10px]"></i>
                    </button>
                </div>
            </template>
        </div>

        {{-- Empty-state for My Fonts when filter = mine and no uploads. --}}
        <div x-show="cat === 'mine' && customFonts.length === 0" class="text-center py-6">
            <p class="text-xs mb-2" style="color: var(--text-faint);">No custom fonts yet.</p>
            <button type="button" @click="$refs.fileInput.click()"
                    class="text-[11px] font-semibold px-3 py-1.5 rounded-lg"
                    style="background: rgba(61,107,255,0.12); color: #90acff;">
                <i class="fas fa-upload text-[10px] mr-1"></i>Upload .woff/.woff2/.ttf/.otf
            </button>
        </div>

        {{-- Google Fonts grid --}}
        <div x-show="cat !== 'mine'" class="space-y-1">
            <p x-show="cat === 'all'" class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--text-muted);">Google Fonts</p>
            <template x-for="font in filteredGoogleFonts()" :key="font.family">
                <div class="rounded-lg px-2 py-1.5 cursor-pointer hover:bg-white/5"
                     x-init="queuePreviewFont(font.family)"
                     :style="selected === font.family ? 'background: rgba(61,107,255,0.18); border: 1px solid rgba(61,107,255,0.3);' : ''"
                     @click="select(font.family)">
                    <span class="text-base" :style="'font-family: ' + safeQuote(font.family) + ', sans-serif;'" x-text="font.family"></span>
                </div>
            </template>
            <p x-show="filteredGoogleFonts().length === 0 && cat !== 'mine'" class="text-xs text-center py-4" style="color: var(--text-faint);">
                No fonts match "<span x-text="search"></span>".
            </p>
        </div>

        {{-- Hidden file input for uploads. --}}
        <input type="file" x-ref="fileInput" accept=".woff,.woff2,.ttf,.otf" class="hidden"
               @change="uploadCustomFont($event)">
        <p x-show="uploadError" class="text-[11px] text-red-400" x-text="uploadError"></p>
        <p x-show="uploading" class="text-[11px]" style="color: var(--text-muted);">
            <i class="fas fa-spinner fa-spin mr-1"></i>Uploading…
        </p>
    </div>
</div>

<script>
window.fontPickerComponent_{{ $pickerId }} = window.fontPickerComponent_{{ $pickerId }} || function () {
    return {
        open: false,
        search: '',
        cat: 'all',
        selected: @json($selectedFamily),
        uploading: false,
        uploadError: '',
        // Static catalogs (rendered once into the page).
        @php
            $__googleFontsPayload = array_map(fn ($e) => ['family' => $e['family'], 'category' => $e['category']], $fontEntries);
            $__customFontsPayload = $customFonts->map(fn ($f) => [
                'id'     => $f->id,
                'family' => $f->family,
                'token'  => $f->settingsToken(),
                'url'    => $f->url,
                'format' => $f->format,
            ])->values();
        @endphp
        googleFonts: {!! json_encode($__googleFontsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!},
        // Mutable: starts from server, mutates on upload/delete so the picker
        // updates without a reload.
        customFonts: {!! json_encode($__customFontsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!},
        init() {
            // Inject @font-face for every existing custom font once per page
            // (idempotent across multiple pickers — we keyed by family in
            // window._customFontFaces).
            window._customFontFaces = window._customFontFaces || new Set();
            this.customFonts.forEach((f) => this.injectFontFace(f));
            // If the current selection is a known Google Font, pre-load its
            // stylesheet so the trigger button shows the real face.
            if (this.selected && !this.selected.startsWith('custom:')) {
                this.loadGoogleFont(this.selected);
            }
        },
        select(family) {
            this.selected = family;
            if (family && !family.startsWith('custom:')) this.loadGoogleFont(family);
            // Mirror change into the hidden input so any external listeners
            // (live preview, dirty-state guards) pick it up.
            this.$root.querySelectorAll('input[type=hidden][name="{{ $name }}"]').forEach((el) => {
                el.dispatchEvent(new Event('change', { bubbles: true }));
            });
        },
        selectedLabel() {
            if (!this.selected) return '';
            if (this.selected.startsWith('custom:')) return this.selected.substring(7) + ' · custom';
            return this.selected;
        },
        previewFamilyCss(token) {
            const family = token.startsWith('custom:') ? token.substring(7) : token;
            return this.safeQuote(family);
        },
        safeQuote(family) {
            // CSS font-family values with spaces must be quoted.
            return /\s/.test(family) ? '"' + family.replace(/"/g, '') + '"' : family;
        },
        matchesSearch(scope) {
            if (!this.search) return true;
            const q = this.search.toLowerCase();
            if (scope === 'mine') return this.customFonts.some((f) => f.family.toLowerCase().includes(q));
            return false;
        },
        filteredCustomFonts() {
            const q = this.search.toLowerCase();
            return this.customFonts.filter((f) => !q || f.family.toLowerCase().includes(q));
        },
        filteredGoogleFonts() {
            const q = this.search.toLowerCase();
            return this.googleFonts.filter((f) => {
                if (this.cat !== 'all' && this.cat !== f.category) return false;
                if (q && !f.family.toLowerCase().includes(q)) return false;
                return true;
            }).slice(0, 200);
        },
        loadGoogleFont(family) {
            window._loadedGoogleFonts = window._loadedGoogleFonts || new Set();
            if (window._loadedGoogleFonts.has(family)) return;
            window._loadedGoogleFonts.add(family);
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(family).replace(/%20/g, '+') + ':wght@300;400;500;600;700&display=swap';
            document.head.appendChild(link);
        },
        // Batches preview-font requests so each dropdown item shows in its
        // actual typeface without firing one <link> per item. Collected
        // families are flushed in a single combined Google Fonts URL.
        queuePreviewFont(family) {
            window._loadedGoogleFonts = window._loadedGoogleFonts || new Set();
            window._previewFontQueue = window._previewFontQueue || new Set();
            if (window._loadedGoogleFonts.has(family) || window._previewFontQueue.has(family)) return;
            window._previewFontQueue.add(family);
            if (window._previewFontFlush) clearTimeout(window._previewFontFlush);
            window._previewFontFlush = setTimeout(() => {
                const families = Array.from(window._previewFontQueue);
                window._previewFontQueue.clear();
                if (!families.length) return;
                // Google Fonts caps URL length, so chunk to ~40 families per
                // stylesheet request.
                for (let i = 0; i < families.length; i += 40) {
                    const chunk = families.slice(i, i + 40);
                    chunk.forEach((f) => window._loadedGoogleFonts.add(f));
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://fonts.googleapis.com/css2?' +
                        chunk.map((f) => 'family=' + encodeURIComponent(f).replace(/%20/g, '+')).join('&') +
                        '&display=swap';
                    document.head.appendChild(link);
                }
            }, 80);
        },
        injectFontFace(font) {
            if (window._customFontFaces.has(font.family)) return;
            window._customFontFaces.add(font.family);
            const formatMap = { 'woff2': 'woff2', 'woff': 'woff', 'truetype': 'truetype', 'opentype': 'opentype' };
            const fmt = formatMap[font.format] || 'truetype';
            const style = document.createElement('style');
            style.textContent = "@font-face { font-family: '" + font.family.replace(/'/g, '') + "'; src: url('" + font.url + "') format('" + fmt + "'); font-display: swap; }";
            document.head.appendChild(style);
        },
        uploadCustomFont(ev) {
            const file = ev.target.files && ev.target.files[0];
            if (!file) return;
            this.uploading = true;
            this.uploadError = '';
            const family = (file.name.replace(/\.(woff2?|ttf|otf)$/i, '').replace(/[^A-Za-z0-9 \-_]/g, '').trim()) || 'Custom Font';
            const fd = new FormData();
            fd.append('file', file);
            fd.append('family', family);
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            fetch(@json(route('user.custom-fonts.store')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            }).then((r) => r.json()).then((d) => {
                this.uploading = false;
                ev.target.value = '';
                if (!d.success) {
                    this.uploadError = d.error || 'Upload failed.';
                    return;
                }
                this.customFonts.push(d.font);
                this.injectFontFace(d.font);
                this.cat = 'mine';
                this.select(d.font.token);
            }).catch(() => {
                this.uploading = false;
                this.uploadError = 'Upload failed.';
            });
        },
        removeCustomFont(id) {
            if (!confirm('Delete this font? Pages using it will fall back to the default.')) return;
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            fetch(@json(url('/user/custom-fonts')) + '/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            }).then((r) => r.json()).then((d) => {
                if (!d.success) return;
                const removed = this.customFonts.find((f) => f.id === id);
                this.customFonts = this.customFonts.filter((f) => f.id !== id);
                // Route through select('') so live-preview / dirty-state
                // listeners fire the same change event as a manual pick.
                if (removed && this.selected === removed.token) this.select('');
            });
        },
    };
};
</script>
