{{--
    Modern drag-and-drop file input with three modes:
      • Upload — drag/drop or click (default)
      • URL    — paste a remote URL (server-side imported into vault)
      • Vault  — pick from "My Files"

    Wraps a real <input type="file"> so the parent form still submits as
    multipart/form-data — no AJAX, no special endpoint required. URL and
    Vault picks are fetched as Blobs and injected into the underlying input
    via DataTransfer so existing controllers keep working unchanged.

    Usage (preferred — pass a plan-driven policy array):
        @php $policy = \App\Services\UploadPolicy::for('vcf.photo', auth()->user()); @endphp
        @include('user.partials.dropzone-input', [
            'name'        => 'photo',
            'policy'      => $policy,
            'currentUrl'  => $vcf?->photoUrl(),
            'label'       => 'Photo',
        ])

    Usage (manual — back-compat with explicit accept/maxMb):
        @include('user.partials.dropzone-input', [
            'name'        => 'photo',
            'accept'      => 'image/*',
            'maxMb'       => 5,
            'multiple'    => false,
            'currentUrl'  => null,
            'currentName' => null,
            'label'       => null,
            'hint'        => 'JPG/PNG up to 5 MB',
            'previewKind' => 'image',
            'compact'     => false,
            'required'    => false,
            'form'        => null,
            'extraInput'  => null,
            'allowAlternateSources' => true, // set false to hide URL/Vault tabs
        ])
--}}
@php
    $dzId        = 'dz_' . substr(md5($name . uniqid('', true)), 0, 10);
    $policy      = $policy      ?? null;
    $accept      = $accept      ?? ($policy['accept']   ?? '*/*');
    $maxMb       = $maxMb       ?? ($policy['max_mb']   ?? null);
    $multiple    = $multiple    ?? ($policy['multiple'] ?? false);
    $extensions  = $policy['extensions'] ?? null;
    $currentUrl  = $currentUrl  ?? null;
    $currentName = $currentName ?? null;
    $label       = $label       ?? null;
    $hint        = $hint        ?? null;
    if (!$hint && $extensions) {
        $hint = strtoupper(implode(', ', array_slice($extensions, 0, 4))) . (count($extensions) > 4 ? '…' : '');
    }
    $previewKind = $previewKind ?? 'image';
    $compact     = $compact     ?? false;
    $required    = $required    ?? false;
    $form        = $form        ?? null;
    $extraInput  = $extraInput  ?? null;
    $allowAlternateSources = $allowAlternateSources ?? true;
    $inputName   = $multiple ? rtrim($name, '[]') . '[]' : $name;

    // Vault browse type filter — derived from accept patterns.
    $browseType = 'all';
    if (is_string($accept)) {
        $a = strtolower($accept);
        if (str_contains($a, 'image')) $browseType = 'image';
        elseif (str_contains($a, 'video')) $browseType = 'video';
        elseif (str_contains($a, 'audio')) $browseType = 'audio';
        elseif (str_contains($a, 'pdf') || str_contains($a, 'doc') || str_contains($a, 'xls') || str_contains($a, 'ppt')) $browseType = 'document';
    }
@endphp

<div x-data="dropzoneInput_{{ $dzId }}()" class="dz-wrap">
    @if($label)
        <label class="block text-xs font-medium text-white/60 mb-1.5">{{ $label }}@if($required)<span class="text-red-400 ml-0.5">*</span>@endif</label>
    @endif

    @if($allowAlternateSources && !$multiple)
    <div class="flex items-center gap-1 mb-1.5">
        <button type="button" @click="mode = 'upload'"
                class="text-[10px] px-2 py-0.5 rounded-md transition-all font-medium"
                :class="mode === 'upload' ? 'bg-blue-500/20 text-blue-300 ring-1 ring-blue-500/30' : 'text-white/40 hover:text-white/60'">
            <i class="fas fa-cloud-upload-alt mr-1"></i>Upload
        </button>
        <button type="button" @click="mode = 'url'"
                class="text-[10px] px-2 py-0.5 rounded-md transition-all font-medium"
                :class="mode === 'url' ? 'bg-blue-500/20 text-blue-300 ring-1 ring-blue-500/30' : 'text-white/40 hover:text-white/60'">
            <i class="fas fa-link mr-1"></i>URL
        </button>
        <button type="button" @click="mode = 'vault'; if (vaultFiles.length === 0) loadVault()"
                class="text-[10px] px-2 py-0.5 rounded-md transition-all font-medium"
                :class="mode === 'vault' ? 'bg-blue-500/20 text-blue-300 ring-1 ring-blue-500/30' : 'text-white/40 hover:text-white/60'">
            <i class="fas fa-folder-open mr-1"></i>My Files
        </button>
    </div>
    @endif

    {{-- Upload mode (and the underlying real <input> always lives here so
         the form has something to submit regardless of mode). --}}
    <div x-show="mode === 'upload'" x-cloak>
    <div class="relative rounded-xl overflow-hidden transition-all"
         :class="{ 'ring-2 ring-blue-500/60 bg-blue-500/5': dragging, 'ring-2 ring-red-500/60 bg-red-500/5': error, 'bg-white/5': !dragging && !error }"
         style="border: 1.5px dashed rgba(255,255,255,0.18);"
         @dragover.prevent="dragging = true"
         @dragleave.prevent="dragging = false"
         @drop.prevent="onDrop($event)">

        <input type="file"
               name="{{ $inputName }}"
               accept="{{ $accept }}"
               x-ref="input"
               @change="onChange($event)"
               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
               @if($form) form="{{ $form }}" @endif
               @if($multiple) multiple @endif
               @if($required) required @endif>

        @if($extraInput) {!! $extraInput !!} @endif

        <template x-if="files.length === 0 && !currentUrl && !currentName">
            <div class="{{ $compact ? 'px-3 py-3' : 'px-4 py-5' }} text-center pointer-events-none">
                <div class="flex items-center justify-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background: rgba(61,107,255,0.12); border: 1px solid rgba(61,107,255,0.25);">
                        <i class="fas fa-cloud-upload-alt text-blue-400 text-sm"></i>
                    </div>
                    <div class="text-left">
                        <p class="text-xs text-white/80"><span class="text-blue-300 font-medium">Drop {{ $multiple ? 'files' : 'a file' }}</span> or click to browse</p>
                        @if($hint || $maxMb)
                            <p class="text-[10px] text-white/30 mt-0.5">{{ $hint }}@if($hint && $maxMb) · @endif @if($maxMb) Max {{ $maxMb }} MB @endif</p>
                        @endif
                    </div>
                </div>
            </div>
        </template>

        <template x-if="files.length === 0 && (currentUrl || currentName)">
            <div class="flex items-center gap-3 p-2.5 pointer-events-none">
                @if($previewKind === 'image')
                    <template x-if="currentUrl">
                        <img :src="currentUrl" alt="Current" class="w-12 h-12 rounded-lg object-cover bg-white/5 flex-shrink-0">
                    </template>
                @endif
                <template x-if="!currentUrl || '{{ $previewKind }}' !== 'image'">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0" style="background: rgba(61,107,255,0.10);">
                        <i class="fas fa-file text-blue-400"></i>
                    </div>
                </template>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-white/80 truncate" x-text="currentName || 'Current file'"></p>
                    <p class="text-[10px] text-white/40">Drop a new file to replace · or click</p>
                </div>
            </div>
        </template>

        <template x-if="files.length > 0">
            <div class="p-2 space-y-1.5">
                <template x-for="(f, i) in files" :key="i">
                    <div class="flex items-center gap-2.5 p-2 rounded-lg bg-white/5 border border-white/5 pointer-events-none">
                        <template x-if="f.preview">
                            <img :src="f.preview" :alt="f.name" class="w-10 h-10 rounded-md object-cover flex-shrink-0">
                        </template>
                        <template x-if="!f.preview">
                            <div class="w-10 h-10 rounded-md flex items-center justify-center flex-shrink-0" style="background: rgba(61,107,255,0.10);">
                                <i :class="iconFor(f)" class="text-blue-400 text-sm"></i>
                            </div>
                        </template>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-white truncate" x-text="f.name"></p>
                            <p class="text-[10px] text-white/40" x-text="formatSize(f.size) + (f.source ? ' · ' + f.source : '')"></p>
                        </div>
                        <button type="button" @click.stop="removeAt(i)" class="pointer-events-auto w-6 h-6 rounded-md flex items-center justify-center text-white/40 hover:text-red-400 hover:bg-red-500/10 transition" title="Remove">
                            <i class="fas fa-times text-[11px]"></i>
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </div>
    </div>

    {{-- URL mode --}}
    @if($allowAlternateSources && !$multiple)
    <div x-show="mode === 'url'" x-cloak>
        <div class="rounded-xl p-2.5" style="background: var(--bg-glass, rgba(255,255,255,0.04)); border: 1px solid var(--border-glass, rgba(255,255,255,0.10));">
            <div class="flex gap-2">
                <input type="url" x-model="urlInput" placeholder="https://example.com/image.jpg"
                       class="flex-1 text-xs px-2.5 py-1.5 rounded-lg outline-none"
                       style="background: var(--bg-glass-input, rgba(0,0,0,0.20)); color: var(--text-primary, #fff); border: 1px solid var(--border-glass, rgba(255,255,255,0.10));"
                       @keydown.enter.prevent="importFromUrl()">
                <button type="button" @click="importFromUrl()" :disabled="urlImporting || !urlInput"
                        class="text-xs px-3 py-1.5 rounded-lg font-medium transition-all"
                        :class="urlImporting || !urlInput ? 'opacity-50 cursor-not-allowed bg-white/5 text-white/40' : 'bg-blue-500/20 text-blue-300 ring-1 ring-blue-500/30 hover:bg-blue-500/30'">
                    <template x-if="!urlImporting"><span><i class="fas fa-download mr-1"></i>Use</span></template>
                    <template x-if="urlImporting"><span><i class="fas fa-spinner fa-spin mr-1"></i>Fetching</span></template>
                </button>
            </div>
            <p class="text-[10px] text-white/30 mt-1.5">The file is downloaded into your vault and counts toward your storage quota.</p>
        </div>
    </div>

    {{-- Vault mode --}}
    <div x-show="mode === 'vault'" x-cloak>
        <div class="rounded-xl overflow-hidden" style="background: var(--bg-glass, rgba(255,255,255,0.04)); border: 1px solid var(--border-glass, rgba(255,255,255,0.10));">
            <div class="p-2 flex items-center gap-2" style="border-bottom: 1px solid var(--border-subtle, rgba(255,255,255,0.06));">
                <input type="text" x-model="vaultSearch" placeholder="Search My Files…"
                       class="flex-1 text-xs px-2.5 py-1.5 rounded-lg outline-none"
                       style="background: var(--bg-glass-input, rgba(0,0,0,0.20)); color: var(--text-primary, #fff); border: 1px solid var(--border-glass, rgba(255,255,255,0.10));">
                <button type="button" @click="loadVault()" class="text-[10px] text-blue-400 hover:text-blue-300 px-2"><i class="fas fa-sync-alt"></i></button>
            </div>
            <div class="max-h-48 overflow-y-auto p-2">
                <template x-if="vaultLoading">
                    <div class="py-6 text-center"><i class="fas fa-spinner fa-spin text-blue-400/60"></i></div>
                </template>
                <template x-if="!vaultLoading && vaultFiles.length === 0">
                    <div class="py-6 text-center text-xs text-white/30">No files in your vault yet</div>
                </template>
                <div class="grid grid-cols-4 gap-1.5">
                    <template x-for="f in filteredVault" :key="f.id">
                        <button type="button" @click="pickVault(f)"
                                :disabled="vaultPicking"
                                class="rounded-lg overflow-hidden text-left transition-all hover:ring-2 hover:ring-blue-500/50 group relative"
                                style="background: var(--bg-glass-input, rgba(0,0,0,0.20));">
                            <template x-if="f.type === 'image'">
                                <img :src="f.url" class="w-full aspect-square object-cover" :alt="f.original_name">
                            </template>
                            <template x-if="f.type !== 'image'">
                                <div class="w-full aspect-square flex flex-col items-center justify-center p-1">
                                    <i class="text-lg text-white/20"
                                       :class="f.type === 'video' ? 'fas fa-video' : f.type === 'audio' ? 'fas fa-music' : 'fas fa-file'"></i>
                                    <span class="text-[8px] text-white/30 mt-1 truncate w-full text-center" x-text="f.original_name"></span>
                                </div>
                            </template>
                        </button>
                    </template>
                </div>
                <template x-if="!vaultLoading && vaultHasMore">
                    <button type="button" @click="loadMoreVault()" class="w-full text-[10px] text-blue-400 hover:text-blue-300 py-2 text-center">Load more…</button>
                </template>
            </div>
        </div>
    </div>
    @endif

    <template x-if="error">
        <p class="text-[11px] text-red-400 mt-1.5 flex items-center gap-1.5">
            <i class="fas fa-exclamation-circle"></i>
            <span x-text="error"></span>
        </p>
    </template>
</div>

<script>
function dropzoneInput_{{ $dzId }}() {
    return {
        files: [],
        dragging: false,
        error: '',
        mode: 'upload',
        currentUrl: @js($currentUrl),
        currentName: @js($currentName),
        multiple: @js($multiple),
        accept: @js($accept),
        maxMb: @js($maxMb),

        urlInput: '',
        urlImporting: false,

        vaultFiles: [],
        vaultLoading: false,
        vaultSearch: '',
        vaultPage: 1,
        vaultHasMore: false,
        vaultPicking: false,
        vaultType: @js($browseType),

        get filteredVault() {
            if (!this.vaultSearch) return this.vaultFiles;
            const s = this.vaultSearch.toLowerCase();
            return this.vaultFiles.filter((f) => (f.original_name || '').toLowerCase().includes(s));
        },

        onDrop(e) {
            this.dragging = false;
            this.error = '';
            const dropped = Array.from(e.dataTransfer.files || []);
            if (!dropped.length) return;
            const accepted = [];
            for (const f of dropped) {
                if (!this.matchesAccept(f)) { this.error = `"${f.name}" is not an allowed file type.`; continue; }
                if (this.maxMb && f.size > this.maxMb * 1024 * 1024) { this.error = `"${f.name}" is ${this.formatSize(f.size)} — over the ${this.maxMb} MB limit.`; continue; }
                accepted.push(f);
            }
            if (!accepted.length) return;
            const dt = new DataTransfer();
            const start = this.multiple ? Array.from(this.$refs.input.files || []) : [];
            start.concat(accepted).forEach((f) => dt.items.add(f));
            this.$refs.input.files = dt.files;
            this.refreshFiles();
        },

        onChange() {
            this.error = '';
            const picked = Array.from(this.$refs.input.files || []);
            const dt = new DataTransfer();
            for (const f of picked) {
                if (!this.matchesAccept(f)) { this.error = `"${f.name}" is not an allowed file type.`; continue; }
                if (this.maxMb && f.size > this.maxMb * 1024 * 1024) { this.error = `"${f.name}" is ${this.formatSize(f.size)} — over the ${this.maxMb} MB limit.`; continue; }
                dt.items.add(f);
            }
            this.$refs.input.files = dt.files;
            this.refreshFiles();
        },

        refreshFiles(extraSource) {
            const list = Array.from(this.$refs.input.files || []);
            this.files = list.map((f) => ({
                name: f.name,
                size: f.size,
                type: f.type,
                preview: f.type && f.type.startsWith('image/') ? URL.createObjectURL(f) : null,
                source: extraSource || null,
            }));
        },

        async importFromUrl() {
            if (!this.urlInput || this.urlImporting) return;
            this.error = '';
            this.urlImporting = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch('{{ route("user.files.import-url") }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: this.urlInput }),
                });
                const data = await res.json();
                if (!data.success || !data.file) {
                    this.error = (data.error && data.error.message) || (typeof data.error === 'string' ? data.error : '') || 'Could not import URL.';
                    return;
                }
                await this.injectVaultFile(data.file, 'from URL');
                this.urlInput = '';
                this.mode = 'upload';
            } catch (e) {
                this.error = 'Network error while importing URL.';
            } finally {
                this.urlImporting = false;
            }
        },

        async pickVault(f) {
            if (this.vaultPicking) return;
            this.vaultPicking = true;
            this.error = '';
            try {
                await this.injectVaultFile(f, 'from My Files');
                this.mode = 'upload';
            } catch (e) {
                this.error = 'Could not load that file.';
            } finally {
                this.vaultPicking = false;
            }
        },

        async injectVaultFile(f, sourceLabel) {
            const r = await fetch(f.url, { credentials: 'include' });
            if (!r.ok) throw new Error('fetch failed');
            const blob = await r.blob();
            const file = new File([blob], f.original_name || ('file' + (f.id || '')), { type: f.mime_type || blob.type || 'application/octet-stream' });
            if (this.maxMb && file.size > this.maxMb * 1024 * 1024) {
                this.error = `"${file.name}" is ${this.formatSize(file.size)} — over the ${this.maxMb} MB limit.`;
                return;
            }
            if (!this.matchesAccept(file)) {
                this.error = `"${file.name}" is not an allowed file type here.`;
                return;
            }
            const dt = new DataTransfer();
            const start = this.multiple ? Array.from(this.$refs.input.files || []) : [];
            start.concat([file]).forEach((f) => dt.items.add(f));
            this.$refs.input.files = dt.files;
            this.refreshFiles(sourceLabel);
            this.currentUrl = null;
            this.currentName = null;
        },

        async loadVault() {
            this.vaultLoading = true;
            this.vaultPage = 1;
            try {
                const r = await fetch('{{ route("user.files.index") }}?type=' + encodeURIComponent(this.vaultType) + '&page=1', { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                this.vaultFiles = data.files || [];
                this.vaultHasMore = data.pagination && data.pagination.current_page < data.pagination.last_page;
            } catch (e) {
                this.vaultFiles = [];
            }
            this.vaultLoading = false;
        },

        async loadMoreVault() {
            this.vaultPage++;
            try {
                const r = await fetch('{{ route("user.files.index") }}?type=' + encodeURIComponent(this.vaultType) + '&page=' + this.vaultPage, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                this.vaultFiles = this.vaultFiles.concat(data.files || []);
                this.vaultHasMore = data.pagination && data.pagination.current_page < data.pagination.last_page;
            } catch (e) {}
        },

        removeAt(i) {
            const dt = new DataTransfer();
            Array.from(this.$refs.input.files).forEach((f, idx) => { if (idx !== i) dt.items.add(f); });
            this.$refs.input.files = dt.files;
            this.refreshFiles();
            this.error = '';
        },

        matchesAccept(file) {
            if (!this.accept || this.accept === '*/*') return true;
            const tokens = this.accept.split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);
            const name = (file.name || '').toLowerCase();
            const mime = (file.type || '').toLowerCase();
            return tokens.some((t) => {
                if (t.startsWith('.')) return name.endsWith(t);
                if (t.endsWith('/*')) return mime.startsWith(t.slice(0, -1));
                return mime === t;
            });
        },

        iconFor(f) {
            const t = (f.type || '').toLowerCase();
            const n = (f.name || '').toLowerCase();
            if (t.startsWith('video/')) return 'fas fa-video';
            if (t.startsWith('audio/')) return 'fas fa-music';
            if (t === 'application/pdf' || n.endsWith('.pdf')) return 'fas fa-file-pdf';
            if (n.match(/\.(docx?|odt)$/)) return 'fas fa-file-word';
            if (n.match(/\.(xlsx?|csv|ods)$/)) return 'fas fa-file-excel';
            if (n.match(/\.(pptx?|odp)$/)) return 'fas fa-file-powerpoint';
            if (n.match(/\.zip$|\.rar$|\.7z$/)) return 'fas fa-file-archive';
            return 'fas fa-file';
        },

        formatSize(b) {
            if (b < 1024) return b + ' B';
            if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
            if (b < 1024 * 1024 * 1024) return (b / 1024 / 1024).toFixed(1) + ' MB';
            return (b / 1024 / 1024 / 1024).toFixed(2) + ' GB';
        },
    };
}
</script>
