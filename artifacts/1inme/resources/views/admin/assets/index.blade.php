@extends('admin.layouts.app')
@section('title', 'Asset Vault')

@section('content')
<div x-data="adminAssetVault()" x-init="init()" class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color: var(--text-primary);">Asset Vault</h1>
            <p class="text-sm mt-1" style="color: var(--text-faint);">
                Centralised storage for admin uploads — organised into folders, backed by local disk or S3.
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
            <button @click="newFolderModal = true"
                    class="px-3 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 transition-all"
                    style="background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                <i class="fas fa-folder-plus"></i> New Folder
            </button>
            <button @click="$refs.fileInput.click()"
                    class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 transition-all shadow-sm">
                <i class="fas fa-cloud-upload-alt"></i> Upload
            </button>
            <input type="file" x-ref="fileInput" @change="handleFiles($event)" multiple class="hidden">
        </div>
    </div>

    {{-- Stat tiles --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-xl p-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <p class="text-[10px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Total assets</p>
            <p class="text-2xl font-bold" style="color: var(--text-primary);" x-text="storage.file_count"></p>
        </div>
        <div class="rounded-xl p-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <p class="text-[10px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Storage used</p>
            <p class="text-2xl font-bold" style="color: var(--text-primary);" x-text="storage.total_human"></p>
        </div>
        <div class="rounded-xl p-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <p class="text-[10px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Folders</p>
            <p class="text-2xl font-bold" style="color: var(--text-primary);" x-text="folders.filter(f => !f.system).length"></p>
        </div>
        <div class="rounded-xl p-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <p class="text-[10px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Driver</p>
            <p class="text-2xl font-bold capitalize" style="color: var(--text-primary);" x-text="storage.driver"></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-5">

        {{-- Folder sidebar --}}
        <aside class="rounded-xl overflow-hidden" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="px-4 py-3 flex items-center justify-between" style="border-bottom: 1px solid var(--border-subtle);">
                <span class="text-xs font-bold uppercase tracking-wider" style="color: var(--text-faint);">Folders</span>
                <button @click="newFolderModal = true" class="text-xs text-violet-400 hover:text-violet-300" title="New folder">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <div class="p-2 max-h-[480px] overflow-y-auto">
                <button @click="folder = ''; load(1)"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-all"
                        :class="folder === '' ? 'bg-violet-500/15 text-violet-200' : 'hover:bg-white/5'"
                        :style="folder !== '' ? 'color: var(--text-secondary);' : ''">
                    <span class="flex items-center gap-2"><i class="fas fa-layer-group text-xs"></i> All assets</span>
                    <span class="text-[10px] opacity-70" x-text="storage.file_count"></span>
                </button>

                <template x-for="f in folders" :key="f.slug">
                    <div class="group flex items-center">
                        <button @click="folder = f.slug; load(1)"
                                class="flex-1 flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-all min-w-0"
                                :class="folder === f.slug ? 'bg-violet-500/15 text-violet-200' : 'hover:bg-white/5'"
                                :style="folder !== f.slug ? 'color: var(--text-secondary);' : ''">
                            <span class="flex items-center gap-2 min-w-0">
                                <i class="fas text-xs" :class="f.system ? 'fa-inbox' : 'fa-folder'"></i>
                                <span class="truncate" x-text="f.name"></span>
                            </span>
                            <span class="text-[10px] opacity-70 flex-shrink-0 ml-2" x-text="f.count"></span>
                        </button>
                        <template x-if="!f.system">
                            <button @click="deleteFolder(f)" title="Delete folder"
                                    class="opacity-0 group-hover:opacity-100 text-xs text-red-400 hover:text-red-300 px-2 transition-opacity">
                                <i class="fas fa-trash"></i>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </aside>

        {{-- Main panel --}}
        <div class="space-y-4">
            {{-- Toolbar --}}
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

            {{-- Breadcrumb --}}
            <div class="flex items-center gap-2 text-xs" style="color: var(--text-faint);">
                <i class="fas fa-folder-open"></i>
                <button @click="folder = ''; load(1)" class="hover:text-violet-400">Asset Vault</button>
                <template x-if="folder">
                    <span class="flex items-center gap-2">
                        <i class="fas fa-chevron-right text-[8px]"></i>
                        <span class="font-semibold" style="color: var(--text-secondary);"
                              x-text="(folders.find(f => f.slug === folder) || { name: folder }).name"></span>
                    </span>
                </template>
            </div>

            <div x-show="uploading" x-cloak class="rounded-xl p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                <div class="flex items-center gap-3 mb-2">
                    <div class="animate-spin w-4 h-4 border-2 border-violet-400 border-t-transparent rounded-full"></div>
                    <span class="text-sm font-medium" style="color: var(--text-secondary);"
                          x-text="'Uploading ' + uploadDone + ' / ' + uploadTotal"></span>
                </div>
            </div>

            <div x-show="loading" x-cloak class="text-center py-10 text-sm" style="color: var(--text-faint);">
                <i class="fas fa-spinner fa-spin mr-2"></i> Loading…
            </div>

            <div x-show="!loading && assets.length === 0" x-cloak
                 class="text-center py-16 rounded-xl" style="background: var(--bg-card); border: 1px dashed var(--border-glass);">
                <i class="fas fa-folder-open text-4xl mb-3" style="color: var(--text-faint);"></i>
                <p class="text-sm font-medium" style="color: var(--text-secondary);">No assets in this view</p>
                <p class="text-xs mt-1" style="color: var(--text-faint);">Click <strong>Upload</strong> to add files to this folder.</p>
            </div>

            <div x-show="!loading && assets.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <template x-for="a in assets" :key="a.id">
                    <div class="group relative rounded-xl overflow-hidden transition-all hover:-translate-y-0.5"
                         style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                        <div class="aspect-square flex items-center justify-center" style="background: var(--bg-glass-input);">
                            <template x-if="a.type === 'image'">
                                <img :src="a.url" :alt="a.original_name" class="w-full h-full object-cover" loading="lazy">
                            </template>
                            <template x-if="a.type === 'video'"><i class="fas fa-film text-3xl text-violet-400"></i></template>
                            <template x-if="a.type === 'audio'"><i class="fas fa-music text-3xl text-pink-400"></i></template>
                            <template x-if="a.type === 'document'"><i class="fas fa-file-lines text-3xl text-cyan-400"></i></template>
                            <template x-if="a.type === 'archive'"><i class="fas fa-file-zipper text-3xl text-amber-400"></i></template>
                            <template x-if="a.type === 'other'"><i class="fas fa-file text-3xl text-slate-400"></i></template>
                        </div>
                        <div class="p-2.5">
                            <p class="text-xs font-semibold truncate" :title="a.original_name" style="color: var(--text-primary);" x-text="a.label || a.original_name"></p>
                            <p class="text-[10px] mt-0.5 flex items-center gap-1.5" style="color: var(--text-faint);">
                                <span x-text="a.size_human"></span><span>·</span><span x-text="a.type"></span>
                                <template x-if="a.folder"><span class="ml-auto truncate" x-text="a.folder"></span></template>
                            </p>
                        </div>
                        <div class="absolute top-1.5 right-1.5 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click="openMove(a)" title="Move to folder"
                                    class="w-7 h-7 rounded-md flex items-center justify-center text-xs"
                                    style="background: rgba(0,0,0,0.55); color: #fff;">
                                <i class="fas fa-folder-tree"></i>
                            </button>
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

            <div x-show="!loading && pagination.last_page > 1" class="flex items-center justify-center gap-2 pt-2">
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
    </div>

    {{-- New folder modal --}}
    <div x-show="newFolderModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.55);">
        <div @click.outside="newFolderModal = false" class="w-full max-w-sm rounded-xl p-5"
             style="background: var(--bg-card); border: 1px solid var(--border-strong);">
            <h3 class="text-base font-bold mb-3" style="color: var(--text-primary);">New folder</h3>
            <input x-model="newFolderName" @keydown.enter="createFolder()" type="text" placeholder="e.g. Brand Logos"
                   class="w-full px-3 py-2 text-sm rounded-lg mb-4"
                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
            <div class="flex justify-end gap-2">
                <button @click="newFolderModal = false; newFolderName = ''"
                        class="px-3 py-2 text-sm rounded-lg"
                        style="background: var(--bg-glass); border: 1px solid var(--border-subtle); color: var(--text-secondary);">Cancel</button>
                <button @click="createFolder()" class="px-3 py-2 text-sm rounded-lg bg-violet-600 hover:bg-violet-700 text-white">Create</button>
            </div>
        </div>
    </div>

    {{-- Move modal --}}
    <div x-show="moveModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.55);">
        <div @click.outside="moveModal = false" class="w-full max-w-sm rounded-xl p-5"
             style="background: var(--bg-card); border: 1px solid var(--border-strong);">
            <h3 class="text-base font-bold mb-3" style="color: var(--text-primary);">Move asset</h3>
            <p class="text-xs mb-3 truncate" style="color: var(--text-faint);" x-text="moveAsset?.original_name || ''"></p>
            <select x-model="moveTarget"
                    class="w-full px-3 py-2 text-sm rounded-lg mb-4"
                    style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                <option value="">— Unfiled —</option>
                <template x-for="f in folders.filter(f => !f.system)" :key="f.slug">
                    <option :value="f.slug" x-text="f.name"></option>
                </template>
            </select>
            <div class="flex justify-end gap-2">
                <button @click="moveModal = false"
                        class="px-3 py-2 text-sm rounded-lg"
                        style="background: var(--bg-glass); border: 1px solid var(--border-subtle); color: var(--text-secondary);">Cancel</button>
                <button @click="commitMove()" class="px-3 py-2 text-sm rounded-lg bg-violet-600 hover:bg-violet-700 text-white">Move</button>
            </div>
        </div>
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
        folder: @json($folder ?? ''),
        folders: @json($folders ?? []),
        assets: [],
        pagination: { current_page: 1, last_page: 1, total: 0 },
        storage: @json($storage),
        loading: false,
        uploading: false,
        uploadTotal: 0,
        uploadDone: 0,

        newFolderModal: false,
        newFolderName: '',

        moveModal: false,
        moveAsset: null,
        moveTarget: '',

        init() { this.load(1); },

        async load(page = 1) {
            this.loading = true;
            try {
                const params = new URLSearchParams({ page, type: this.type, q: this.search, folder: this.folder });
                const r = await fetch(`{{ route('admin.assets.index') }}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await r.json();
                if (data.success) {
                    this.assets = data.assets;
                    this.pagination = data.pagination;
                    this.storage = data.storage;
                    this.folders = data.folders;
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
                if (this.folder && this.folder !== '__root__') fd.append('folder', this.folder);
                try {
                    const r = await fetch(`{{ route('admin.assets.upload') }}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: fd,
                    });
                    const data = await r.json();
                    if (!data.success) alert(data.error || 'Upload failed');
                    else this.storage = data.storage;
                } catch (_) { alert('Upload failed'); }
                this.uploadDone++;
            }
            e.target.value = '';
            this.uploading = false;
            await this.load(1);
        },

        async createFolder() {
            const name = (this.newFolderName || '').trim();
            if (!name) return;
            const fd = new FormData();
            fd.append('name', name);
            const r = await fetch(`{{ route('admin.assets.folders.store') }}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json();
            if (data.success) {
                this.folders = data.folders;
                this.folder = data.folder.slug;
                this.newFolderModal = false;
                this.newFolderName = '';
                await this.load(1);
            } else {
                alert(data.error || 'Could not create folder');
            }
        },

        async deleteFolder(f) {
            const hasFiles = f.count > 0;
            const msg = hasFiles
                ? `"${f.name}" contains ${f.count} file(s). Delete folder AND all files inside?`
                : `Delete empty folder "${f.name}"?`;
            if (!confirm(msg)) return;
            const url = `/admin/assets/folders/${f.id}` + (hasFiles ? '?cascade=1' : '');
            const r = await fetch(url, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            });
            const data = await r.json();
            if (data.success) {
                this.folders = data.folders;
                this.storage = data.storage;
                if (this.folder === f.slug) this.folder = '';
                await this.load(1);
            } else {
                alert(data.error || 'Could not delete folder');
            }
        },

        openMove(a) {
            this.moveAsset = a;
            this.moveTarget = a.folder || '';
            this.moveModal = true;
        },

        async commitMove() {
            if (!this.moveAsset) return;
            const fd = new FormData();
            if (this.moveTarget) fd.append('folder', this.moveTarget);
            const r = await fetch(`/admin/assets/${this.moveAsset.id}/move`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json();
            if (data.success) {
                this.moveModal = false;
                this.moveAsset = null;
                this.folders = data.folders;
                await this.load(this.pagination.current_page);
            } else {
                alert('Move failed');
            }
        },

        async remove(a) {
            if (!confirm(`Delete "${a.original_name}"?`)) return;
            const r = await fetch(`/admin/assets/${a.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            });
            const data = await r.json();
            if (data.success) {
                this.assets = this.assets.filter(x => x.id !== a.id);
                this.storage = data.storage;
                this.folders = data.folders;
            }
        },

        async copyUrl(a) {
            try { await navigator.clipboard.writeText(a.url); } catch (_) {}
        },
    }
}
</script>
@endsection
