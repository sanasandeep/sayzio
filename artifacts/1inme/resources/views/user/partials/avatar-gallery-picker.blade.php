{{--
    Curated avatar gallery (Task #6015) — platform-provided avatars listed
    live from S3 (people-avatars / stock-avatars / hand-drawn), available on
    every plan. Picking one fills a hidden input with the asset's S3 key;
    the parent form's controller resolves + stores the absolute CDN URL
    (an uploaded file, if any, still wins server-side).

    Usage:
        @include('user.partials.avatar-gallery-picker', ['inputName' => 'avatar_asset'])
--}}
@php
    $inputName = $inputName ?? 'avatar_asset';
    $agId = 'ag_' . substr(md5($inputName . uniqid('', true)), 0, 8);
@endphp
<div x-data="avatarGallery_{{ $agId }}()" class="mt-3 space-y-2">
    <input type="hidden" name="{{ $inputName }}" :value="selected">
    <button type="button" @click="toggle()"
            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl transition-all"
            style="background: var(--bg-glass-input, rgba(0,0,0,0.20)); border: 1px solid var(--border-glass, rgba(255,255,255,0.10));">
        <span class="text-xs font-semibold" style="color: var(--text-primary, #fff);">
            <i class="fas fa-user-circle text-blue-400 text-[10px] mr-1.5"></i> Or pick from our avatar gallery
        </span>
        <i class="fas text-[10px]" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'" style="color: var(--text-faint, rgba(255,255,255,0.4));"></i>
    </button>
    <div x-show="open" x-transition class="space-y-2" style="display: none;">
        <div class="flex items-center gap-1.5">
            <template x-for="t in tabs" :key="t.folder">
                <button type="button" @click="tab = t.folder"
                        class="text-[10px] px-2.5 py-1 rounded-full font-semibold transition-all"
                        :style="tab === t.folder ? 'background: rgba(61,107,255,0.25); color:#bccfff; border:1px solid rgba(61,107,255,0.5)' : 'background: var(--bg-glass-input, rgba(0,0,0,0.20)); color: var(--text-muted, rgba(255,255,255,0.6)); border:1px solid var(--border-glass, rgba(255,255,255,0.10))'"
                        x-text="t.label"></button>
            </template>
        </div>
        <template x-if="loading"><p class="text-[11px] text-center py-3" style="color: var(--text-dimmed, rgba(255,255,255,0.35));">Loading avatars…</p></template>
        <template x-if="!loading && current().length === 0"><p class="text-[11px] text-center py-3" style="color: var(--text-dimmed, rgba(255,255,255,0.35));">No gallery avatars available yet.</p></template>
        <div class="grid grid-cols-5 sm:grid-cols-6 gap-1.5 max-h-64 overflow-y-auto pr-1">
            <template x-for="a in current().slice(0, limit)" :key="a.key">
                <button type="button"
                        @click="selected = selected === a.key ? '' : a.key"
                        :class="selected === a.key ? 'ring-2 ring-blue-400' : ''"
                        class="rounded-full overflow-hidden relative transition-all hover:scale-[1.06] hover:z-10 aspect-square"
                        style="border: 1px solid var(--border-glass, rgba(255,255,255,0.10)); background: var(--bg-glass-input, rgba(0,0,0,0.20));"
                        :title="a.label">
                    <img :src="a.url" :alt="a.label" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
                    <div x-show="selected === a.key"
                         class="absolute bottom-0 inset-x-0 py-0.5 text-center"
                         style="background: rgba(61,107,255,0.9); color:#fff; font-size:7px;">
                        <i class="fas fa-check"></i>
                    </div>
                </button>
            </template>
        </div>
        <div class="flex items-center justify-between">
            <p class="text-[10px]" style="color: var(--text-dimmed, rgba(255,255,255,0.35));">Click to select, click again to deselect. Save to apply.</p>
            <button type="button" x-show="current().length > limit" @click="limit += 30"
                    class="text-[10px] font-semibold px-2 py-1 rounded-md" style="color:#90acff; border: 1px dashed rgba(61,107,255,0.3);">
                Show more
            </button>
        </div>
    </div>
</div>
<script>
function avatarGallery_{{ $agId }}() {
    return {
        open: false,
        loading: false,
        loaded: false,
        tab: 'people-avatars',
        tabs: [
            { folder: 'people-avatars', label: 'Photos' },
            { folder: 'stock-avatars', label: 'Illustrated' },
            { folder: 'hand-drawn', label: 'Hand-drawn' },
        ],
        assets: {},
        selected: '',
        limit: 30,

        current() {
            return this.assets[this.tab] || [];
        },

        async toggle() {
            this.open = !this.open;
            if (!this.open || this.loaded || this.loading) return;
            this.loading = true;
            try {
                const results = await Promise.all(this.tabs.map(async (t) => {
                    const r = await fetch('{{ route("user.platform-assets.index", "__F__") }}'.replace('__F__', t.folder), { headers: { 'Accept': 'application/json' } });
                    const data = await r.json();
                    return [t.folder, data.assets || []];
                }));
                this.assets = Object.fromEntries(results);
                this.loaded = true;
            } catch (e) {
                this.assets = {};
            }
            this.loading = false;
        },
    };
}
</script>
