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
                :class="mode === 'url' ? 'bg-blue-500/20 text-blue-300 ring-1 ring-blue-500/30' : 'text-white/30 hover:text-white/50'" style="background: var(--bg-glass);">
            <i class="fas fa-link mr-1"></i>URL
        </button>
        <button type="button" @click="mode = 'upload'" class="text-[10px] px-2.5 py-1 rounded-lg transition-all font-medium"
                :class="mode === 'upload' ? 'bg-blue-500/20 text-blue-300 ring-1 ring-blue-500/30' : 'text-white/30 hover:text-white/50'" style="background: var(--bg-glass);">
            <i class="fas fa-cloud-upload-alt mr-1"></i>Upload
        </button>
        <button type="button" @click="mode = 'browse'" class="text-[10px] px-2.5 py-1 rounded-lg transition-all font-medium"
                :class="mode === 'browse' ? 'bg-blue-500/20 text-blue-300 ring-1 ring-blue-500/30' : 'text-white/30 hover:text-white/50'" style="background: var(--bg-glass);">
            <i class="fas fa-folder-open mr-1"></i>My Files
        </button>
    </div>

    <input type="hidden" :name="'{{ $fieldName }}'" x-model="value" x-ref="hiddenInput">

    <div x-show="mode === 'url'" x-cloak>
        <input type="url" x-model="value" @input="fileMeta = null" placeholder="https://..." class="{{ $inputClass ?? 'theme-input w-full' }}">
    </div>

    <div x-show="mode === 'upload'" x-cloak>
        <div class="upload-dropzone relative rounded-xl p-4 text-center transition-all cursor-pointer"
             :class="dragging ? 'ring-2 ring-blue-500/50' : ''"
             style="background: var(--bg-glass); border: 2px dashed var(--border-glass);"
             @dragover.prevent="dragging = true"
             @dragleave.prevent="dragging = false"
             @drop.prevent="dragging = false; handleDrop($event)"
             @click="$refs.fileInput.click()">

            <template x-if="!uploading && !uploadError">
                <div>
                    <i class="fas fa-cloud-upload-alt text-2xl text-blue-400/60 mb-2"></i>
                    <p class="text-xs text-white/40">Drag & drop or click to choose</p>
                    <p class="text-[10px] text-white/20 mt-1">Max: <span x-text="maxFileSizeMb"></span>MB</p>
                </div>
            </template>

            <template x-if="uploading">
                <div>
                    <div class="w-full rounded-full h-1.5 mb-2" style="background: var(--bg-glass-input);">
                        <div class="h-1.5 rounded-full bg-gradient-to-r from-blue-500 to-pink-500 transition-all duration-300" :style="'width:' + uploadProgress + '%'"></div>
                    </div>
                    <p class="text-xs text-blue-300"><i class="fas fa-spinner fa-spin mr-1"></i>Uploading... <span x-text="uploadProgress"></span>%</p>
                </div>
            </template>

            <template x-if="uploadError">
                <div>
                    <i class="fas fa-exclamation-triangle text-red-400 text-lg mb-1"></i>
                    <p class="text-xs text-red-400" x-text="uploadError"></p>
                    <button type="button" @click.stop="uploadError = null" class="text-[10px] text-blue-400 mt-1 hover:underline">Try again</button>
                </div>
            </template>
        </div>
        <input type="file" x-ref="fileInput" accept="{{ $accept }}" class="hidden" @change="handleFile($event)">
    </div>

    <div x-show="mode === 'browse'" x-cloak>
        <div class="rounded-xl overflow-hidden" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
            <div class="p-2 flex items-center gap-2" style="border-bottom: 1px solid var(--border-subtle);">
                <input type="text" x-model="browseSearch" placeholder="Search files..." class="flex-1 text-xs px-2.5 py-1.5 rounded-lg outline-none" style="background: var(--bg-glass-input); color: var(--text-primary); border: 1px solid var(--border-glass);">
                <button type="button" @click="loadFiles()" class="text-[10px] text-blue-400 hover:text-blue-300 px-2"><i class="fas fa-sync-alt"></i></button>
            </div>
            <div class="max-h-48 overflow-y-auto p-2">
                <template x-if="browseLoading">
                    <div class="py-6 text-center"><i class="fas fa-spinner fa-spin text-blue-400/60"></i></div>
                </template>
                <template x-if="!browseLoading && browseFiles.length === 0">
                    <div class="py-6 text-center text-xs text-white/30">No files uploaded yet</div>
                </template>
                <div class="grid grid-cols-3 gap-1.5">
                    <template x-for="f in filteredFiles" :key="f.id">
                        <button type="button" @click="selectFile(f)"
                                class="rounded-lg overflow-hidden text-left transition-all hover:ring-2 hover:ring-blue-500/50 group relative"
                                :class="value === f.url ? 'ring-2 ring-blue-500' : ''"
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
                    <button type="button" @click="loadMoreFiles()" class="w-full text-[10px] text-blue-400 hover:text-blue-300 py-2 text-center">Load more...</button>
                </template>
            </div>
        </div>
    </div>

    <template x-if="value && !uploading">
        <div class="mt-2 rounded-xl overflow-hidden" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
            @if($acceptTypes === 'image')
            <img :src="value" class="w-full max-h-32 object-contain" alt="Preview" x-on:error="$el.style.display='none'">
            @elseif($acceptTypes === 'video')
            <video :src="value" class="w-full max-h-32" controls></video>
            @elseif($acceptTypes === 'audio')
            <audio :src="value" controls class="w-full"></audio>
            @endif

            {{-- Current-file summary: shown for every file type so non-previewable
                 files (PDF/doc/etc.) still clearly show what's attached. --}}
            <div class="flex items-center gap-2.5 p-2.5" @if($previewable) style="border-top: 1px solid var(--border-subtle);" @endif>
                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" style="background: var(--bg-glass-input);">
                    <i class="text-base text-blue-300" :class="currentIcon()"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-medium truncate" style="color: var(--text-primary);" x-text="currentName()" :title="currentName()"></div>
                    <div class="text-[10px]" style="color: var(--text-faint);" x-text="currentMeta() || 'Attached'"></div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" @click="replaceFile()" class="text-[10px] px-2.5 py-1.5 rounded-lg font-medium text-blue-300 hover:bg-blue-500/10 transition-colors" title="Replace this file">
                        <i class="fas fa-arrows-rotate mr-1"></i>Replace
                    </button>
                    <button type="button" @click="removeFile()" class="w-7 h-7 rounded-lg text-white/40 hover:text-rose-300 hover:bg-rose-500/10 flex items-center justify-center transition-colors" title="Remove file">
                        <i class="fas fa-trash-can text-xs"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function fileUploadField_{{ $fieldId }}() {
    return {
        mode: '{{ $currentValue ? "url" : "upload" }}',
        value: '{{ $currentValue }}',
        fileMeta: null,
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
            this.$watch('value', (val) => {
                this.$dispatch('file-url-change', { url: val });
            });
        },

        fileExt() {
            var n = (this.fileMeta && this.fileMeta.name) ? this.fileMeta.name : this.value;
            if (!n) return '';
            n = n.split('?')[0].split('#')[0];
            var i = n.lastIndexOf('.');
            return i >= 0 ? n.slice(i + 1).toLowerCase() : '';
        },

        currentName() {
            if (this.fileMeta && this.fileMeta.name) return this.fileMeta.name;
            if (!this.value) return '';
            try {
                var u = this.value.split('?')[0].split('#')[0];
                var parts = u.split('/');
                var base = parts[parts.length - 1] || u;
                return decodeURIComponent(base) || 'Current file';
            } catch (e) { return 'Current file'; }
        },

        currentMeta() {
            var parts = [];
            var ext = this.fileExt();
            if (ext) parts.push(ext.toUpperCase());
            if (this.fileMeta && this.fileMeta.size_human) parts.push(this.fileMeta.size_human);
            return parts.join(' · ');
        },

        currentIcon() {
            var t = this.fileMeta && this.fileMeta.type;
            if (t === 'image') return 'fas fa-image';
            if (t === 'video') return 'fas fa-film';
            if (t === 'audio') return 'fas fa-music';
            var ext = this.fileExt();
            if (['jpg','jpeg','png','gif','webp','svg'].indexOf(ext) !== -1) return 'fas fa-image';
            if (['mp4','webm','ogg','mov'].indexOf(ext) !== -1) return 'fas fa-film';
            if (['mp3','wav','aac','m4a'].indexOf(ext) !== -1) return 'fas fa-music';
            if (ext === 'pdf') return 'fas fa-file-pdf';
            if (['doc','docx'].indexOf(ext) !== -1) return 'fas fa-file-word';
            if (['xls','xlsx'].indexOf(ext) !== -1) return 'fas fa-file-excel';
            if (['ppt','pptx'].indexOf(ext) !== -1) return 'fas fa-file-powerpoint';
            return 'fas fa-file';
        },

        emitMeta() {
            if (!this.fileMeta) return;
            this.$dispatch('file-meta', {
                name: this.fileMeta.name || '',
                size_human: this.fileMeta.size_human || '',
                type: this.fileMeta.type || '',
            });
        },

        replaceFile() {
            this.mode = 'upload';
            this.uploadError = null;
        },

        removeFile() {
            this.value = '';
            this.fileMeta = null;
            this.mode = 'upload';
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
                        self.fileMeta = { name: data.file.original_name, size_human: data.file.size_human, type: data.file.type };
                        self.mode = 'url';
                        self.emitMeta();
                        self.showUploadToast('File uploaded');
                    } else {
                        self.uploadError = (data.error && data.error.message) || (typeof data.error === 'string' ? data.error : '') || 'Upload failed';
                    }
                } else {
                    try {
                        var err = JSON.parse(xhr.responseText);
                        self.uploadError = (err.error && err.error.message) || (typeof err.error === 'string' ? err.error : '') || err.message || 'Upload failed (' + xhr.status + ')';
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
            this.fileMeta = { name: f.original_name, size_human: f.size_human, type: f.type };
            this.mode = 'url';
            this.emitMeta();
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
