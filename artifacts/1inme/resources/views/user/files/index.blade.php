@extends('user.layouts.app')
@section('title', 'My Files')

@section('content')
@include('user.files._top-tabs')
@include('user.partials._plan_lock', ['feature' => 'files', 'kind' => 'flag', 'label' => 'File hosting'])
<div x-data="fileManager()" x-init="init()">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">My Files</h1>
            <p class="text-sm mt-1" style="color: var(--text-faint);">Upload, browse, and manage your media files</p>
        </div>
        <button @click="$refs.fileInput.click()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 transition-all">
            <i class="fas fa-cloud-upload-alt"></i> Upload Files
        </button>
        <input type="file" x-ref="fileInput" @change="handleFileSelect($event)" multiple accept="image/*,video/*,audio/*,.pdf,.ppt,.pptx,.xls,.xlsx,.doc,.docx" class="hidden">
    </div>

    @if(!empty($reoptimizeNotice))
        <div x-show="showReoptimizeNotice" x-transition x-cloak
             class="glass rounded-2xl p-4 mb-4 border border-emerald-500/30"
             style="background: linear-gradient(135deg, rgba(16,185,129,0.12), rgba(99,102,241,0.08));">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0">
                    <i class="fas fa-leaf text-emerald-300"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold mb-0.5" style="color: var(--text-primary);">
                        We tidied up your vault
                    </div>
                    <div class="text-xs" style="color: var(--text-faint);">
                        Re-compressed <strong style="color: var(--text-secondary);" x-text="reoptimizeNotice.files_count + ' old image' + (reoptimizeNotice.files_count === 1 ? '' : 's')"></strong>
                        and recovered <strong style="color: var(--text-secondary);" x-text="reoptimizeNotice.bytes_human"></strong>
                        of storage on your account.
                    </div>
                </div>
                <button type="button" @click="dismissReoptimizeNotice()"
                        class="text-xs px-2 py-1 rounded-lg hover:bg-white/5 transition-colors shrink-0"
                        style="color: var(--text-faint);"
                        aria-label="Dismiss vault cleanup notice">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    @endif

    <div class="glass rounded-2xl p-4 mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex-1 w-full sm:w-auto">
                <div class="flex items-center gap-2 text-xs mb-2" style="color: var(--text-faint);">
                    <i class="fas fa-database"></i>
                    <span>Storage: <strong x-text="quota.used_mb + ' MB'" style="color: var(--text-secondary);"></strong> of <strong x-text="quota.limit_mb < 0 ? 'Unlimited' : quota.limit_mb + ' MB'" style="color: var(--text-secondary);"></strong></span>
                    <span>(<span x-text="quota.file_count"></span> files)</span>
                </div>
                <div class="w-full rounded-full h-2 overflow-hidden" style="background: var(--bg-glass-input);">
                    <div class="h-full rounded-full transition-all duration-500"
                         :class="quota.percent > 90 ? 'bg-red-500' : quota.percent > 70 ? 'bg-yellow-500' : 'bg-blue-500'"
                         :style="'width: ' + Math.min(quota.percent, 100) + '%'"></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color: var(--text-faint);"></i>
                    <input type="text" x-model="search" @input.debounce.300ms="filterFiles()" placeholder="Search files..."
                           class="pl-8 pr-3 py-2 text-xs rounded-xl w-40" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                </div>
                <select x-model="viewMode" class="text-xs px-3 py-2 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    <option value="grid" style="background: var(--bg-body);">Grid</option>
                    <option value="list" style="background: var(--bg-body);">List</option>
                </select>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-2 mb-4 overflow-x-auto pb-1">
        <template x-for="t in ['all','image','video','audio','document']" :key="t">
            <button @click="activeType = t; loadFiles(1)" class="text-xs px-3 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap"
                    :class="activeType === t ? 'bg-blue-500/20 text-blue-300 ring-1 ring-blue-500/30' : 'hover:text-white/50'"
                    :style="activeType !== t ? 'color: var(--text-faint); background: var(--bg-glass);' : 'background: var(--bg-glass);'">
                <i class="mr-1" :class="typeIcon(t)"></i>
                <span x-text="t === 'all' ? 'All Files' : t.charAt(0).toUpperCase() + t.slice(1) + 's'"></span>
            </button>
        </template>
    </div>

    <div x-show="uploading" x-transition class="glass rounded-2xl p-4 mb-4">
        <div class="flex items-center gap-3 mb-2">
            <div class="animate-spin w-5 h-5 border-2 border-blue-400 border-t-transparent rounded-full"></div>
            <span class="text-sm font-medium" style="color: var(--text-secondary);" x-text="'Uploading ' + uploadQueue.length + ' file(s)...'"></span>
        </div>
        <div class="w-full rounded-full h-1.5 overflow-hidden" style="background: var(--bg-glass-input);">
            <div class="h-full bg-blue-500 rounded-full transition-all duration-300" :style="'width: ' + uploadProgress + '%'"></div>
        </div>
    </div>

    <div x-show="loading && files.length === 0" class="glass rounded-2xl p-12 text-center">
        <div class="animate-spin w-8 h-8 border-2 border-blue-400 border-t-transparent rounded-full mx-auto"></div>
        <p class="text-sm mt-3" style="color: var(--text-faint);">Loading files...</p>
    </div>

    <div x-show="!loading && filteredFiles.length === 0" x-cloak class="glass rounded-2xl p-12 text-center">
        <div class="w-16 h-16 bg-blue-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-cloud-upload-alt text-blue-400 text-2xl"></i>
        </div>
        <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
            <span x-show="search">No files match your search</span>
            <span x-show="!search && activeType !== 'all'" x-text="'No ' + activeType + ' files yet'"></span>
            <span x-show="!search && activeType === 'all'">No files uploaded yet</span>
        </h3>
        <p class="text-sm mb-4" style="color: var(--text-faint);">Drag files here or click Upload to get started</p>
        <button @click="$refs.fileInput.click()" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium">
            <i class="fas fa-plus"></i> Upload Files
        </button>
    </div>

    <div x-show="filteredFiles.length > 0" x-cloak>
        <div x-show="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
            <template x-for="file in filteredFiles" :key="file.id">
                <div class="glass rounded-xl overflow-hidden group relative cursor-pointer hover:ring-1 hover:ring-blue-500/30 transition-all"
                     @click="selectedFile = file; showDetail = true">
                    <div class="aspect-square relative overflow-hidden" style="background: var(--bg-glass-input);">
                        <template x-if="file.type === 'image'">
                            <img :src="file.url" :alt="file.original_name" class="w-full h-full object-cover" loading="lazy">
                        </template>
                        <template x-if="file.type === 'video'">
                            <div class="w-full h-full flex items-center justify-center">
                                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-blue-500/10"></div>
                                <i class="fas fa-play-circle text-4xl text-blue-400/60"></i>
                            </div>
                        </template>
                        <template x-if="file.type === 'audio'">
                            <div class="w-full h-full flex items-center justify-center">
                                <div class="absolute inset-0 bg-gradient-to-br from-green-500/10 to-teal-500/10"></div>
                                <i class="fas fa-music text-4xl text-green-400/60"></i>
                            </div>
                        </template>
                        <template x-if="file.type === 'document'">
                            <div class="w-full h-full flex items-center justify-center">
                                <div class="absolute inset-0 bg-gradient-to-br from-orange-500/10 to-red-500/10"></div>
                                <i class="fas fa-file-alt text-4xl text-orange-400/60"></i>
                            </div>
                        </template>
                        <div class="absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                            <button @click.stop="copyUrl(file)" class="w-7 h-7 rounded-lg bg-black/60 backdrop-blur-sm flex items-center justify-center text-white/70 hover:text-white text-xs">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button @click.stop="confirmDelete(file)" class="w-7 h-7 rounded-lg bg-red-500/60 backdrop-blur-sm flex items-center justify-center text-white/70 hover:text-white text-xs">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-2">
                        <p class="text-[11px] font-medium truncate" style="color: var(--text-secondary);" x-text="file.original_name"></p>
                        <p class="text-[10px] mt-0.5" style="color: var(--text-faint);" x-text="formatSize(file.size_bytes)"></p>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="viewMode === 'list'" class="glass rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="border-b" style="border-color: var(--border-glass);">
                    <tr>
                        <th class="text-left py-3 px-4 text-xs font-medium" style="color: var(--text-faint);">File</th>
                        <th class="text-left py-3 px-4 text-xs font-medium hidden sm:table-cell" style="color: var(--text-faint);">Type</th>
                        <th class="text-left py-3 px-4 text-xs font-medium hidden sm:table-cell" style="color: var(--text-faint);">Size</th>
                        <th class="text-left py-3 px-4 text-xs font-medium hidden md:table-cell" style="color: var(--text-faint);">Uploaded</th>
                        <th class="text-right py-3 px-4 text-xs font-medium" style="color: var(--text-faint);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="file in filteredFiles" :key="file.id">
                        <tr class="border-b hover:bg-white/[0.02] transition-colors cursor-pointer" style="border-color: var(--border-glass);"
                            @click="selectedFile = file; showDetail = true">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center" style="background: var(--bg-glass-input);">
                                        <template x-if="file.type === 'image'">
                                            <img :src="file.url" class="w-full h-full object-cover" loading="lazy">
                                        </template>
                                        <template x-if="file.type !== 'image'">
                                            <i :class="typeIcon(file.type)" class="text-sm" style="color: var(--text-faint);"></i>
                                        </template>
                                    </div>
                                    <span class="truncate max-w-[200px] text-xs font-medium" style="color: var(--text-secondary);" x-text="file.original_name"></span>
                                </div>
                            </td>
                            <td class="py-3 px-4 hidden sm:table-cell">
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-medium"
                                      :class="file.type === 'image' ? 'bg-blue-500/10 text-blue-300' : file.type === 'video' ? 'bg-blue-500/10 text-blue-300' : file.type === 'audio' ? 'bg-green-500/10 text-green-300' : 'bg-orange-500/10 text-orange-300'"
                                      x-text="file.type"></span>
                            </td>
                            <td class="py-3 px-4 text-xs hidden sm:table-cell" style="color: var(--text-faint);" x-text="formatSize(file.size_bytes)"></td>
                            <td class="py-3 px-4 text-xs hidden md:table-cell" style="color: var(--text-faint);" x-text="formatDate(file.created_at)"></td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click.stop="copyUrl(file)" class="w-7 h-7 rounded-lg flex items-center justify-center text-xs hover:bg-white/5 transition-colors" style="color: var(--text-faint);" title="Copy URL">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button @click.stop="confirmDelete(file)" class="w-7 h-7 rounded-lg flex items-center justify-center text-xs hover:bg-red-500/10 text-red-400/50 hover:text-red-400 transition-colors" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="pagination.last_page > 1" x-cloak class="flex items-center justify-center gap-2 mt-6">
        <button @click="loadFiles(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all disabled:opacity-30"
                style="background: var(--bg-glass); color: var(--text-secondary); border: 1px solid var(--border-glass);">
            <i class="fas fa-chevron-left"></i>
        </button>
        <span class="text-xs px-3" style="color: var(--text-faint);">
            Page <span x-text="pagination.current_page"></span> of <span x-text="pagination.last_page"></span>
        </span>
        <button @click="loadFiles(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all disabled:opacity-30"
                style="background: var(--bg-glass); color: var(--text-secondary); border: 1px solid var(--border-glass);">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>

    <div x-show="showDetail && selectedFile" x-cloak
         @click.self="showDetail = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.7); backdrop-filter: blur(8px);"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="glass rounded-2xl w-full max-w-lg overflow-hidden" @click.stop>
            <div class="flex items-center justify-between p-4 border-b" style="border-color: var(--border-glass);">
                <h3 class="text-sm font-semibold truncate pr-4" style="color: var(--text-primary);" x-text="selectedFile?.original_name"></h3>
                <button @click="showDetail = false" class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-white/5" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-4">
                <template x-if="selectedFile?.type === 'image'">
                    <div class="rounded-xl overflow-hidden mb-4" style="background: var(--bg-glass-input);">
                        <img :src="selectedFile.url" :alt="selectedFile.original_name" class="w-full max-h-80 object-contain">
                    </div>
                </template>
                <template x-if="selectedFile?.type === 'video'">
                    <div class="rounded-xl overflow-hidden mb-4" style="background: var(--bg-glass-input);">
                        <video :src="selectedFile.url" controls class="w-full max-h-80"></video>
                    </div>
                </template>
                <template x-if="selectedFile?.type === 'audio'">
                    <div class="rounded-xl p-4 mb-4 flex items-center justify-center" style="background: var(--bg-glass-input);">
                        <audio :src="selectedFile.url" controls class="w-full"></audio>
                    </div>
                </template>

                <div class="space-y-2 text-xs">
                    <div class="flex justify-between py-1.5 border-b" style="border-color: var(--border-glass);">
                        <span style="color: var(--text-faint);">Type</span>
                        <span style="color: var(--text-secondary);" x-text="selectedFile?.mime_type"></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b" style="border-color: var(--border-glass);">
                        <span style="color: var(--text-faint);">Size</span>
                        <span style="color: var(--text-secondary);" x-text="formatSize(selectedFile?.size_bytes)"></span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b" style="border-color: var(--border-glass);">
                        <span style="color: var(--text-faint);">Uploaded</span>
                        <span style="color: var(--text-secondary);" x-text="formatDate(selectedFile?.created_at)"></span>
                    </div>
                </div>

                <div class="mt-4 p-2.5 rounded-xl flex items-center gap-2" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                    <input type="text" :value="selectedFile?.url" readonly class="flex-1 text-[11px] bg-transparent outline-none" style="color: var(--text-secondary);">
                    <button @click="copyUrl(selectedFile)" class="text-[10px] px-2 py-1 rounded-lg bg-blue-500/20 text-blue-300 hover:bg-blue-500/30 transition-colors flex-shrink-0">
                        <i class="fas fa-copy mr-1"></i>Copy
                    </button>
                </div>

                <div class="flex gap-2 mt-4">
                    <a :href="selectedFile?.url" target="_blank" class="flex-1 text-center py-2 rounded-xl text-xs font-medium transition-all hover:bg-blue-500/30" style="background: var(--bg-glass); color: var(--text-secondary); border: 1px solid var(--border-glass);">
                        <i class="fas fa-external-link-alt mr-1"></i> Open
                    </a>
                    <button @click="confirmDelete(selectedFile); showDetail = false" class="flex-1 text-center py-2 rounded-xl text-xs font-medium bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-all">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showDeleteConfirm" x-cloak
         @click.self="showDeleteConfirm = false"
         class="fixed inset-0 z-[60] flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.7); backdrop-filter: blur(8px);"
         x-transition>
        <div class="glass rounded-2xl w-full max-w-sm p-6 text-center" @click.stop>
            <div class="w-12 h-12 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-trash text-red-400 text-lg"></i>
            </div>
            <h3 class="text-sm font-semibold mb-1" style="color: var(--text-primary);">Delete File?</h3>
            <p class="text-xs mb-4" style="color: var(--text-faint);" x-text="'This will permanently delete ' + (deleteTarget?.original_name || 'this file')"></p>
            <div class="flex gap-2">
                <button @click="showDeleteConfirm = false" class="flex-1 py-2 rounded-xl text-xs font-medium transition-all" style="background: var(--bg-glass); color: var(--text-secondary); border: 1px solid var(--border-glass);">Cancel</button>
                <button @click="doDelete()" class="flex-1 py-2 rounded-xl text-xs font-medium bg-red-500 hover:bg-red-600 text-white transition-all">Delete</button>
            </div>
        </div>
    </div>

    <div x-ref="dropOverlay"
         @dragover.prevent="$refs.dropOverlay.classList.add('active')"
         @dragleave.prevent="$refs.dropOverlay.classList.remove('active')"
         @drop.prevent="$refs.dropOverlay.classList.remove('active'); handleDrop($event)"
         class="fixed inset-0 z-40 pointer-events-none transition-all duration-200"
         :class="dragging ? 'pointer-events-auto' : ''"
         :style="dragging ? 'background: rgba(61,107,255,0.08); backdrop-filter: blur(2px);' : ''">
        <div x-show="dragging" class="absolute inset-8 border-2 border-dashed border-blue-500/40 rounded-3xl flex items-center justify-center">
            <div class="text-center">
                <i class="fas fa-cloud-upload-alt text-4xl text-blue-400/60 mb-2"></i>
                <p class="text-sm font-medium" style="color: var(--text-secondary);">Drop files to upload</p>
            </div>
        </div>
    </div>
</div>

<div id="fm-toast" class="fixed bottom-6 right-6 z-[70] transition-all duration-300 translate-y-16 opacity-0 pointer-events-none">
    <div class="glass rounded-xl px-4 py-3 flex items-center gap-2 text-xs font-medium shadow-xl" style="color: var(--text-secondary);">
        <i id="fm-toast-icon" class="fas fa-check text-green-400"></i>
        <span id="fm-toast-msg"></span>
    </div>
</div>

<script>
function fileManager() {
    return {
        files: [],
        filteredFiles: [],
        activeType: 'all',
        search: '',
        viewMode: 'grid',
        loading: true,
        uploading: false,
        uploadProgress: 0,
        uploadQueue: [],
        pagination: { current_page: 1, last_page: 1, total: 0 },
        quota: @json($quota),
        reoptimizeNotice: @json($reoptimizeNotice ?? null),
        showReoptimizeNotice: !!@json($reoptimizeNotice ?? null),
        selectedFile: null,
        showDetail: false,
        showDeleteConfirm: false,
        deleteTarget: null,
        dragging: false,

        init() {
            this.loadFiles(1);
            document.addEventListener('dragenter', (e) => { e.preventDefault(); this.dragging = true; });
            document.addEventListener('dragleave', (e) => {
                if (e.relatedTarget === null) this.dragging = false;
            });
            document.addEventListener('drop', (e) => { e.preventDefault(); this.dragging = false; });
            document.addEventListener('dragover', (e) => e.preventDefault());
        },

        async loadFiles(page) {
            this.loading = true;
            const params = new URLSearchParams({ page, type: this.activeType });
            try {
                const r = await fetch(`/user/files?${params}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const d = await r.json();
                if (d.success) {
                    this.files = d.files;
                    this.pagination = d.pagination;
                    this.quota = d.quota;
                    this.filterFiles();
                }
            } catch (e) { console.error(e); }
            this.loading = false;
        },

        filterFiles() {
            if (!this.search) {
                this.filteredFiles = this.files;
            } else {
                const q = this.search.toLowerCase();
                this.filteredFiles = this.files.filter(f => f.original_name.toLowerCase().includes(q));
            }
        },

        handleFileSelect(e) {
            const files = Array.from(e.target.files);
            if (files.length) this.uploadFiles(files);
            e.target.value = '';
        },

        handleDrop(e) {
            const files = Array.from(e.dataTransfer.files);
            if (files.length) this.uploadFiles(files);
        },

        async uploadFiles(fileList) {
            this.uploading = true;
            this.uploadQueue = fileList;
            this.uploadProgress = 0;
            let done = 0;

            for (const file of fileList) {
                const fd = new FormData();
                fd.append('file', file);
                try {
                    const r = await fetch('/user/files/upload', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: fd
                    });
                    const d = await r.json();
                    if (d.success) {
                        this.quota = d.quota;
                    } else {
                        this.showToast(d.error || 'Upload failed', 'error');
                    }
                } catch (e) {
                    this.showToast('Upload failed', 'error');
                }
                done++;
                this.uploadProgress = Math.round((done / fileList.length) * 100);
            }

            this.uploading = false;
            this.uploadQueue = [];
            this.showToast(done + ' file(s) uploaded', 'success');
            this.loadFiles(1);
        },

        copyUrl(file) {
            if (!file) return;
            const url = window.location.origin + file.url;
            navigator.clipboard.writeText(url).then(() => {
                this.showToast('URL copied to clipboard', 'success');
            });
        },

        confirmDelete(file) {
            this.deleteTarget = file;
            this.showDeleteConfirm = true;
        },

        async doDelete() {
            if (!this.deleteTarget) return;
            try {
                const r = await fetch(`/user/files/${this.deleteTarget.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    }
                });
                const d = await r.json();
                if (d.success) {
                    this.quota = d.quota;
                    this.files = this.files.filter(f => f.id !== this.deleteTarget.id);
                    this.filterFiles();
                    this.showToast('File deleted', 'success');
                } else {
                    this.showToast(d.error || 'Delete failed', 'error');
                }
            } catch (e) {
                this.showToast('Delete failed', 'error');
            }
            this.showDeleteConfirm = false;
            this.deleteTarget = null;
        },

        typeIcon(type) {
            const map = { all: 'fas fa-th', image: 'fas fa-image', video: 'fas fa-video', audio: 'fas fa-music', document: 'fas fa-file-alt' };
            return map[type] || 'fas fa-file';
        },

        formatSize(bytes) {
            if (!bytes) return '0 B';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },

        formatDate(d) {
            if (!d) return '';
            return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        async dismissReoptimizeNotice() {
            // Optimistically hide so the banner disappears instantly even
            // if the network call is slow / fails (worst case it reappears
            // on next page load, which is fine).
            this.showReoptimizeNotice = false;
            try {
                await fetch('/user/files/reoptimize-notice/dismiss', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    }
                });
            } catch (e) { /* swallow — banner already hidden */ }
        },

        showToast(msg, type) {
            const el = document.getElementById('fm-toast');
            const icon = document.getElementById('fm-toast-icon');
            const msgEl = document.getElementById('fm-toast-msg');
            msgEl.textContent = msg;
            icon.className = type === 'error' ? 'fas fa-exclamation-circle text-red-400' : 'fas fa-check-circle text-green-400';
            el.classList.remove('translate-y-16', 'opacity-0', 'pointer-events-none');
            setTimeout(() => { el.classList.add('translate-y-16', 'opacity-0', 'pointer-events-none'); }, 3000);
        }
    };
}
</script>
@endsection
