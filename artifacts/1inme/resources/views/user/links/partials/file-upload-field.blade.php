@php
$fieldName = $fieldName ?? 'settings[url]';
$currentValue = $currentValue ?? '';
$acceptTypes = $acceptTypes ?? 'image';
$labelText = $labelText ?? 'Image';
$fieldId = 'upload_' . md5($fieldName . rand());
$acceptMap = [
    'image' => '.jpg,.jpeg,.png,.gif,.webp,.svg',
    'video' => '.mp4,.webm,.ogg,.mov',
    'audio' => '.mp3,.wav,.ogg,.webm,.aac,.m4a',
    'document' => '.pdf,.ppt,.pptx,.xls,.xlsx,.doc,.docx',
    'all' => '.jpg,.jpeg,.png,.gif,.webp,.svg,.mp4,.webm,.ogg,.mov,.mp3,.wav,.aac,.m4a,.pdf,.ppt,.pptx,.xls,.xlsx,.doc,.docx',
];
$accept = $acceptMap[$acceptTypes] ?? $acceptMap['all'];
$previewable = in_array($acceptTypes, ['image', 'video', 'audio']);
@endphp

<div x-data="fileUploadField_{{ $fieldId }}()" class="file-upload-field">
    <label class="{{ $labelClass ?? 'block text-xs mb-1' }}">{{ $labelText }}</label>

    <div class="flex items-center gap-1.5 mb-2">
        <button type="button" @click="mode = 'url'" class="text-[10px] px-2.5 py-1 rounded-lg transition-all font-medium"
                :class="mode === 'url' ? 'bg-purple-500/20 text-purple-300 ring-1 ring-purple-500/30' : 'text-white/30 hover:text-white/50'" style="background: var(--bg-glass);">
            <i class="fas fa-link mr-1"></i>URL
        </button>
        <button type="button" @click="mode = 'upload'" class="text-[10px] px-2.5 py-1 rounded-lg transition-all font-medium"
                :class="mode === 'upload' ? 'bg-purple-500/20 text-purple-300 ring-1 ring-purple-500/30' : 'text-white/30 hover:text-white/50'" style="background: var(--bg-glass);">
            <i class="fas fa-cloud-upload-alt mr-1"></i>Upload
        </button>
        <button type="button" @click="mode = 'browse'" class="text-[10px] px-2.5 py-1 rounded-lg transition-all font-medium"
                :class="mode === 'browse' ? 'bg-purple-500/20 text-purple-300 ring-1 ring-purple-500/30' : 'text-white/30 hover:text-white/50'" style="background: var(--bg-glass);">
            <i class="fas fa-folder-open mr-1"></i>My Files
        </button>
    </div>

    <input type="hidden" :name="'{{ $fieldName }}'" x-model="value" x-ref="hiddenInput">

    <div x-show="mode === 'url'" x-cloak>
        <input type="url" x-model="value" placeholder="https://..." class="{{ $inputClass ?? 'theme-input w-full' }}">
    </div>

    <div x-show="mode === 'upload'" x-cloak>
        <div class="upload-dropzone relative rounded-xl p-4 text-center transition-all cursor-pointer"
             :class="dragging ? 'ring-2 ring-purple-500/50' : ''"
             style="background: var(--bg-glass); border: 2px dashed var(--border-glass);"
             @dragover.prevent="dragging = true"
             @dragleave.prevent="dragging = false"
             @drop.prevent="dragging = false; handleDrop($event)"
             @click="$refs.fileInput.click()">

            <template x-if="!uploading && !uploadError">
                <div>
                    <i class="fas fa-cloud-upload-alt text-2xl text-purple-400/60 mb-2"></i>
                    <p class="text-xs text-white/40">Drag & drop or click to choose</p>
                    <p class="text-[10px] text-white/20 mt-1">Max: <span x-text="maxFileSizeMb"></span>MB</p>
                </div>
            </template>

            <template x-if="uploading">
                <div>
                    <div class="w-full rounded-full h-1.5 mb-2" style="background: var(--bg-glass-input);">
                        <div class="h-1.5 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 transition-all duration-300" :style="'width:' + uploadProgress + '%'"></div>
                    </div>
                    <p class="text-xs text-purple-300"><i class="fas fa-spinner fa-spin mr-1"></i>Uploading... <span x-text="uploadProgress"></span>%</p>
                </div>
            </template>

            <template x-if="uploadError">
                <div>
                    <i class="fas fa-exclamation-triangle text-red-400 text-lg mb-1"></i>
                    <p class="text-xs text-red-400" x-text="uploadError"></p>
                    <button type="button" @click.stop="uploadError = null" class="text-[10px] text-purple-400 mt-1 hover:underline">Try again</button>
                </div>
            </template>
        </div>
        <input type="file" x-ref="fileInput" accept="{{ $accept }}" class="hidden" @change="handleFile($event)">
    </div>

    <div x-show="mode === 'browse'" x-cloak>
        <div class="rounded-xl overflow-hidden" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
            <div class="p-2 flex items-center gap-2" style="border-bottom: 1px solid var(--border-subtle);">
                <input type="text" x-model="browseSearch" placeholder="Search files..." class="flex-1 text-xs px-2.5 py-1.5 rounded-lg outline-none" style="background: var(--bg-glass-input); color: var(--text-primary); border: 1px solid var(--border-glass);">
                <button type="button" @click="loadFiles()" class="text-[10px] text-purple-400 hover:text-purple-300 px-2"><i class="fas fa-sync-alt"></i></button>
            </div>
            <div class="max-h-48 overflow-y-auto p-2">
                <template x-if="browseLoading">
                    <div class="py-6 text-center"><i class="fas fa-spinner fa-spin text-purple-400/60"></i></div>
                </template>
                <template x-if="!browseLoading && browseFiles.length === 0">
                    <div class="py-6 text-center text-xs text-white/30">No files uploaded yet</div>
                </template>
                <div class="grid grid-cols-3 gap-1.5">
                    <template x-for="f in filteredFiles" :key="f.id">
                        <button type="button" @click="selectFile(f)"
                                class="rounded-lg overflow-hidden text-left transition-all hover:ring-2 hover:ring-purple-500/50 group relative"
                                :class="value === f.url ? 'ring-2 ring-purple-500' : ''"
                                style="background: var(--bg-glass-input);">
                            <template x-if="f.type === 'image'">
                                <img :src="f.url" class="w-full aspect-square object-cover" :alt="f.original_name">
                            </template>
                            <template x-if="f.type !== 'image'">
                                <div class="w-full aspect-square flex flex-col items-center justify-center p-1">
                                    <i class="text-lg text-white/20"
                                       :class="f.type === 'video' ? 'fas fa-video' : f.type === 'audio' ? 'fas fa-music' : 'fas fa-file'"></i>
                                    <span class="text-[8px] text-white/20 mt-1 truncate w-full text-center" x-text="f.original_name"></span>
                                </div>
                            </template>
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-1">
                                <span class="text-[8px] text-white truncate w-full" x-text="f.size_human"></span>
                            </div>
                        </button>
                    </template>
                </div>
                <template x-if="!browseLoading && browseHasMore">
                    <button type="button" @click="loadMoreFiles()" class="w-full text-[10px] text-purple-400 hover:text-purple-300 py-2 text-center">Load more...</button>
                </template>
            </div>
        </div>
    </div>

    @if($previewable)
    <template x-if="value && !uploading">
        <div class="mt-2 rounded-xl overflow-hidden relative group" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
            @if($acceptTypes === 'image')
            <img :src="value" class="w-full max-h-32 object-contain" alt="Preview" @error="$el.style.display='none'">
            @elseif($acceptTypes === 'video')
            <video :src="value" class="w-full max-h-32" controls></video>
            @elseif($acceptTypes === 'audio')
            <audio :src="value" controls class="w-full"></audio>
            @endif
            <button type="button" @click="value = ''" class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-black/60 text-white/60 hover:text-white flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </template>
    @endif
</div>

<script>
function fileUploadField_{{ $fieldId }}() {
    return {
        mode: '{{ $currentValue ? "url" : "upload" }}',
        value: '{{ $currentValue }}',
        dragging: false,
        uploading: false,
        uploadProgress: 0,
        uploadError: null,
        maxFileSizeMb: 5,
        browseFiles: [],
        browseLoading: false,
        browseSearch: '',
        browsePage: 1,
        browseHasMore: false,

        init() {
            this.loadQuota();
            this.$watch('mode', (val) => {
                if (val === 'browse' && this.browseFiles.length === 0) this.loadFiles();
            });
        },

        async loadQuota() {
            try {
                var r = await fetch('{{ route("user.files.quota") }}', { headers: { 'Accept': 'application/json' } });
                var data = await r.json();
                if (data.quota) this.maxFileSizeMb = data.quota.max_file_size_mb;
            } catch(e) {}
        },

        handleDrop(e) {
            var files = e.dataTransfer.files;
            if (files.length > 0) this.uploadFile(files[0]);
        },

        handleFile(e) {
            var files = e.target.files;
            if (files.length > 0) this.uploadFile(files[0]);
            e.target.value = '';
        },

        async uploadFile(file) {
            this.uploading = true;
            this.uploadProgress = 0;
            this.uploadError = null;

            var formData = new FormData();
            formData.append('file', file);

            var xhr = new XMLHttpRequest();
            var self = this;

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) self.uploadProgress = Math.round((e.loaded / e.total) * 100);
            });

            xhr.addEventListener('load', function() {
                self.uploading = false;
                if (xhr.status >= 200 && xhr.status < 300) {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success && data.file) {
                        self.value = data.file.url;
                        self.mode = 'url';
                        self.showUploadToast('File uploaded');
                    } else {
                        self.uploadError = data.error || 'Upload failed';
                    }
                } else {
                    try {
                        var err = JSON.parse(xhr.responseText);
                        self.uploadError = err.error || err.message || 'Upload failed (' + xhr.status + ')';
                    } catch(e) {
                        self.uploadError = 'Upload failed (' + xhr.status + ')';
                    }
                }
            });

            xhr.addEventListener('error', function() {
                self.uploading = false;
                self.uploadError = 'Network error';
            });

            xhr.open('POST', '{{ route("user.files.upload") }}');
            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.send(formData);
        },

        async loadFiles() {
            this.browseLoading = true;
            this.browsePage = 1;
            try {
                var typeParam = '{{ $acceptTypes }}';
                var r = await fetch('{{ route("user.files.index") }}?type=' + typeParam + '&page=1', { headers: { 'Accept': 'application/json' } });
                var data = await r.json();
                this.browseFiles = data.files || [];
                this.browseHasMore = data.pagination && data.pagination.current_page < data.pagination.last_page;
            } catch(e) {
                this.browseFiles = [];
            }
            this.browseLoading = false;
        },

        async loadMoreFiles() {
            this.browsePage++;
            var typeParam = '{{ $acceptTypes }}';
            try {
                var r = await fetch('{{ route("user.files.index") }}?type=' + typeParam + '&page=' + this.browsePage, { headers: { 'Accept': 'application/json' } });
                var data = await r.json();
                this.browseFiles = this.browseFiles.concat(data.files || []);
                this.browseHasMore = data.pagination && data.pagination.current_page < data.pagination.last_page;
            } catch(e) {}
        },

        get filteredFiles() {
            if (!this.browseSearch) return this.browseFiles;
            var s = this.browseSearch.toLowerCase();
            return this.browseFiles.filter(function(f) { return f.original_name.toLowerCase().includes(s); });
        },

        selectFile(f) {
            this.value = f.url;
            this.mode = 'url';
        },

        showUploadToast(msg) {
            var toast = document.createElement('div');
            toast.className = 'fixed bottom-4 right-4 z-[9999] px-4 py-2.5 rounded-xl text-xs font-medium text-white shadow-lg';
            toast.style.cssText = 'background: linear-gradient(135deg, #10b981, #059669);';
            toast.innerHTML = '<i class="fas fa-check-circle mr-1.5"></i>' + msg;
            document.body.appendChild(toast);
            setTimeout(function() { toast.remove(); }, 2500);
        }
    }
}
</script>
