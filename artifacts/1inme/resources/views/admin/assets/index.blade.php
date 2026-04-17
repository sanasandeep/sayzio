@extends('admin.layouts.app')
@section('title', 'Asset Vault')

@section('content')
<div x-data="adminAssetVault()" x-init="init()" class="space-y-5">

    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color: var(--text-primary);">Asset Vault</h1>
            <p class="text-sm mt-1" style="color: var(--text-faint);">
                Centralised storage for admin uploads — branding assets, marketing media, internal docs.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1.5 rounded-lg"
                  :class="storage.is_s3 ? 'text-emerald-300' : 'text-violet-300'"
                  :style="(storage.is_s3 ? 'background: rgba(16,185,129,0.10); border: 1px solid rgba(16,185,129,0.25);' : 'background: rgba(124,58,237,0.10); border: 1px solid rgba(124,58,237,0.25);')">
                <i :class="storage.is_s3 ? 'fab fa-aws' : 'fas fa-server'"></i>
                <span x-text="storage.is_s3 ? 'AWS S3' : 'Local Disk'"></span>
                <span class="opacity-60">·</span>
                <span x-text="storage.disk"></span>
            </span>
            <button @click="$refs.fileInput.click()"
                    class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 transition-all shadow-sm">
                <i class="fas fa-cloud-upload-alt"></i> Upload Assets
            </button>
            <input type="file" x-ref="fileInput" @change="handleFiles($event)" multiple class="hidden">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="rounded-xl p-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <p class="text-[10px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Total assets</p>
            <p class="text-2xl font-bold" style="color: var(--text-primary);" x-text="storage.file_count"></p>
        </div>
        <div class="rounded-xl p-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <p class="text-[10px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Storage used</p>
            <p class="text-2xl font-bold" style="color: var(--text-primary);" x-text="storage.total_human"></p>
        </div>
        <div class="rounded-xl p-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <p class="text-[10px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Backing driver</p>
            <p class="text-2xl font-bold capitalize" style="color: var(--text-primary);" x-text="storage.driver"></p>
        </div>
    </div>

    <div class="rounded-xl p-3 flex flex-col md:flex-row items-stretch md:items-center gap-3"
         style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color: var(--text-faint);"></i>
            <input type="text" x-model="search" @input.debounce.300ms="load(1)" placeholder="Search by name, label, or description"
                   class="w-full pl-9 pr-3 py-2 text-sm rounded-lg"
                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
        </div>
        <div class="flex items-center gap-2 overflow-x-auto">
            <template x-for="t in types" :key="t.k">
                <button @click="type = t.k; load(1)"
                        class="text-xs px-3 py-1.5 rounded-lg font-medium whitespace-nowrap transition-all"
                        :class="type === t.k ? 'text-white bg-violet-600' : ''"
                        :style="type === t.k ? '' : 'background: var(--bg-glass); color: var(--text-faint); border: 1px solid var(--border-subtle);'">
                    <i class="mr-1" :class="t.icon"></i>
                    <span x-text="t.label"></span>
                </button>
            </template>
        </div>
    </div>

    <div x-show="uploading" x-cloak class="rounded-xl p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
        <div class="flex items-center gap-3 mb-2">
            <div class="animate-spin w-4 h-4 border-2 border-violet-400 border-t-transparent rounded-full"></div>
            <span class="text-sm font-medium" style="color: var(--text-secondary);"
                  x-text="'Uploading ' + uploadDone + ' / ' + uploadTotal"></span>
        </div>
    </div>

    <div x-show="loading" x-cloak class="text-center py-10 text-sm" style="color: var(--text-faint);">
        <i class="fas fa-spinner fa-spin mr-2"></i> Loading assets…
    </div>

    <div x-show="!loading && assets.length === 0" x-cloak
         class="text-center py-16 rounded-xl" style="background: var(--bg-card); border: 1px dashed var(--border-glass);">
        <i class="fas fa-folder-open text-4xl mb-3" style="color: var(--text-faint);"></i>
        <p class="text-sm font-medium" style="color: var(--text-secondary);">No assets yet</p>
        <p class="text-xs mt-1" style="color: var(--text-faint);">Click <strong>Upload Assets</strong> to add your first file.</p>
    </div>

    <div x-show="!loading && assets.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <template x-for="a in assets" :key="a.id">
            <div class="group relative rounded-xl overflow-hidden transition-all hover:-translate-y-0.5"
                 style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                <div class="aspect-square flex items-center justify-center" style="background: var(--bg-glass-input);">
                    <template x-if="a.type === 'image'">
                        <img :src="a.url" :alt="a.original_name" class="w-full h-full object-cover" loading="lazy">
                    </template>
                    <template x-if="a.type === 'video'">
                        <i class="fas fa-film text-3xl text-violet-400"></i>
                    </template>
                    <template x-if="a.type === 'audio'">
                        <i class="fas fa-music text-3xl text-pink-400"></i>
                    </template>
                    <template x-if="a.type === 'document'">
                        <i class="fas fa-file-lines text-3xl text-cyan-400"></i>
                    </template>
                    <template x-if="a.type === 'archive'">
                        <i class="fas fa-file-zipper text-3xl text-amber-400"></i>
                    </template>
                    <template x-if="a.type === 'other'">
                        <i class="fas fa-file text-3xl text-slate-400"></i>
                    </template>
                </div>
                <div class="p-2.5">
                    <p class="text-xs font-semibold truncate" :title="a.original_name" style="color: var(--text-primary);" x-text="a.label || a.original_name"></p>
                    <p class="text-[10px] mt-0.5 flex items-center gap-1.5" style="color: var(--text-faint);">
                        <span x-text="a.size_human"></span>
                        <span>·</span>
                        <span x-text="a.type"></span>
                    </p>
                </div>
                <div class="absolute top-1.5 right-1.5 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button @click="copyUrl(a)" title="Copy URL"
                            class="w-7 h-7 rounded-md flex items-center justify-center text-xs"
                            style="background: rgba(0,0,0,0.55); color: #fff;">
                        <i class="fas fa-link"></i>
                    </button>
                    <a :href="a.url" target="_blank" title="Open"
                       class="w-7 h-7 rounded-md flex items-center justify-center text-xs"
                       style="background: rgba(0,0,0,0.55); color: #fff;">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <button @click="remove(a)" title="Delete"
                            class="w-7 h-7 rounded-md flex items-center justify-center text-xs"
                            style="background: rgba(220,38,38,0.85); color: #fff;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </template>
    </div>

    <div x-show="!loading && pagination.last_page > 1" class="flex items-center justify-center gap-2 pt-4">
        <button @click="load(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
                class="px-3 py-1.5 text-xs rounded-lg disabled:opacity-40"
                style="background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-secondary);">
            <i class="fas fa-chevron-left"></i>
        </button>
        <span class="text-xs" style="color: var(--text-faint);"
              x-text="'Page ' + pagination.current_page + ' of ' + pagination.last_page + ' (' + pagination.total + ' files)'"></span>
        <button @click="load(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page"
                class="px-3 py-1.5 text-xs rounded-lg disabled:opacity-40"
                style="background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-secondary);">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>

</div>

<script>
function adminAssetVault() {
    return {
        types: [
            { k: 'all',      label: 'All',       icon: 'fas fa-layer-group' },
            { k: 'image',    label: 'Images',    icon: 'fas fa-image' },
            { k: 'video',    label: 'Videos',    icon: 'fas fa-film' },
            { k: 'audio',    label: 'Audio',     icon: 'fas fa-music' },
            { k: 'document', label: 'Documents', icon: 'fas fa-file-lines' },
            { k: 'archive',  label: 'Archives',  icon: 'fas fa-file-zipper' },
            { k: 'other',    label: 'Other',     icon: 'fas fa-file' },
        ],
        type: @json($type ?? 'all'),
        search: @json($search ?? ''),
        assets: [],
        pagination: { current_page: 1, last_page: 1, total: 0 },
        storage: @json($storage),
        loading: false,
        uploading: false,
        uploadTotal: 0,
        uploadDone: 0,

        init() { this.load(1); },

        async load(page = 1) {
            this.loading = true;
            try {
                const params = new URLSearchParams({ page: page, type: this.type, q: this.search });
                const r = await fetch(`{{ route('admin.assets.index') }}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await r.json();
                if (data.success) {
                    this.assets = data.assets;
                    this.pagination = data.pagination;
                    this.storage = data.storage;
                }
            } finally { this.loading = false; }
        },

        async handleFiles(e) {
            const files = Array.from(e.target.files || []);
            if (!files.length) return;
            this.uploading = true;
            this.uploadTotal = files.length;
            this.uploadDone = 0;
            for (const f of files) {
                const fd = new FormData();
                fd.append('file', f);
                try {
                    const r = await fetch(`{{ route('admin.assets.upload') }}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: fd,
                    });
                    const data = await r.json();
                    if (data.success) {
                        this.storage = data.storage;
                    } else {
                        alert(data.error || 'Upload failed');
                    }
                } catch (_) {
                    alert('Upload failed');
                }
                this.uploadDone++;
            }
            e.target.value = '';
            this.uploading = false;
            await this.load(1);
        },

        async remove(asset) {
            if (!confirm(`Delete "${asset.original_name}"?`)) return;
            const r = await fetch(`/admin/assets/${asset.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            });
            const data = await r.json();
            if (data.success) {
                this.assets = this.assets.filter(x => x.id !== asset.id);
                this.storage = data.storage;
            }
        },

        async copyUrl(asset) {
            try {
                await navigator.clipboard.writeText(asset.url);
            } catch (_) { /* ignore */ }
        },
    }
}
</script>
@endsection
