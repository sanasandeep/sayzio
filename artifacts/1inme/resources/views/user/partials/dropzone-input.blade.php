{{--
    Modern drag-and-drop file input.
    Wraps a real <input type="file"> so the parent form still submits as
    multipart/form-data — no AJAX, no special endpoint required. Drop a
    file (or click) to assign it to the input. Shows image previews when
    the file is an image, otherwise a filename + size chip.

    Usage:
        @include('user.partials.dropzone-input', [
            'name'        => 'photo',
            'accept'      => 'image/*',
            'multiple'    => false,
            'currentUrl'  => $vcf?->photoUrl(),
            'currentName' => null,
            'label'       => null,
            'hint'        => 'JPG/PNG up to 5 MB',
            'maxMb'       => 5,
            'previewKind' => 'image',  // 'image' | 'file'
            'compact'     => false,
            'required'    => false,
            'form'        => null,       // optional 'form' attribute target
            'extraInput'  => null,       // optional extra hidden input HTML rendered inside the component
        ])
--}}
@php
    $dzId        = 'dz_' . substr(md5($name . uniqid('', true)), 0, 10);
    $accept      = $accept      ?? '*/*';
    $multiple    = $multiple    ?? false;
    $currentUrl  = $currentUrl  ?? null;
    $currentName = $currentName ?? null;
    $label       = $label       ?? null;
    $hint        = $hint        ?? null;
    $maxMb       = $maxMb       ?? null;
    $previewKind = $previewKind ?? 'image';
    $compact     = $compact     ?? false;
    $required    = $required    ?? false;
    $form        = $form        ?? null;
    $extraInput  = $extraInput  ?? null;
    $inputName   = $multiple ? rtrim($name, '[]') . '[]' : $name;
@endphp

<div x-data="dropzoneInput_{{ $dzId }}()" class="dz-wrap">
    @if($label)
        <label class="block text-xs font-medium text-white/60 mb-1.5">{{ $label }}@if($required)<span class="text-red-400 ml-0.5">*</span>@endif</label>
    @endif

    <div class="relative rounded-xl overflow-hidden transition-all"
         :class="{ 'ring-2 ring-violet-500/60 bg-violet-500/5': dragging, 'bg-white/5': !dragging }"
         style="border: 1.5px dashed rgba(255,255,255,0.18);"
         @dragover.prevent="dragging = true"
         @dragleave.prevent="dragging = false"
         @drop.prevent="onDrop($event)">

        {{-- The actual file input — kept inside the component but invisible.
             It is what the form submits, so name/accept/multiple/required
             must mirror the wrapper's API. --}}
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

        {{-- Empty state --}}
        <template x-if="files.length === 0 && !currentUrl && !currentName">
            <div class="{{ $compact ? 'px-3 py-3' : 'px-4 py-5' }} text-center pointer-events-none">
                <div class="flex items-center justify-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background: rgba(124,58,237,0.12); border: 1px solid rgba(124,58,237,0.25);">
                        <i class="fas fa-cloud-upload-alt text-violet-400 text-sm"></i>
                    </div>
                    <div class="text-left">
                        <p class="text-xs text-white/80"><span class="text-violet-300 font-medium">Drop {{ $multiple ? 'files' : 'a file' }}</span> or click to browse</p>
                        @if($hint || $maxMb)
                            <p class="text-[10px] text-white/30 mt-0.5">{{ $hint }}@if($hint && $maxMb) · @endif @if($maxMb) Max {{ $maxMb }} MB @endif</p>
                        @endif
                    </div>
                </div>
            </div>
        </template>

        {{-- Existing-file state (an image already saved server-side) --}}
        <template x-if="files.length === 0 && (currentUrl || currentName)">
            <div class="flex items-center gap-3 p-2.5 pointer-events-none">
                @if($previewKind === 'image')
                    <template x-if="currentUrl">
                        <img :src="currentUrl" alt="Current" class="w-12 h-12 rounded-lg object-cover bg-white/5 flex-shrink-0">
                    </template>
                @endif
                <template x-if="!currentUrl || '{{ $previewKind }}' !== 'image'">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0" style="background: rgba(124,58,237,0.10);">
                        <i class="fas fa-file text-violet-400"></i>
                    </div>
                </template>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-white/80 truncate" x-text="currentName || 'Current file'"></p>
                    <p class="text-[10px] text-white/40">Drop a new file to replace · or click</p>
                </div>
            </div>
        </template>

        {{-- Selected-files state --}}
        <template x-if="files.length > 0">
            <div class="p-2 space-y-1.5">
                <template x-for="(f, i) in files" :key="i">
                    <div class="flex items-center gap-2.5 p-2 rounded-lg bg-white/5 border border-white/5 pointer-events-none">
                        <template x-if="f.preview">
                            <img :src="f.preview" :alt="f.name" class="w-10 h-10 rounded-md object-cover flex-shrink-0">
                        </template>
                        <template x-if="!f.preview">
                            <div class="w-10 h-10 rounded-md flex items-center justify-center flex-shrink-0" style="background: rgba(124,58,237,0.10);">
                                <i :class="iconFor(f)" class="text-violet-400 text-sm"></i>
                            </div>
                        </template>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-white truncate" x-text="f.name"></p>
                            <p class="text-[10px] text-white/40" x-text="formatSize(f.size)"></p>
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

<script>
function dropzoneInput_{{ $dzId }}() {
    return {
        files: [],
        dragging: false,
        currentUrl: @js($currentUrl),
        currentName: @js($currentName),
        multiple: @js($multiple),
        accept: @js($accept),

        onDrop(e) {
            this.dragging = false;
            const dropped = Array.from(e.dataTransfer.files || []);
            if (!dropped.length) return;
            const accepted = dropped.filter((f) => this.matchesAccept(f));
            if (!accepted.length) return;
            // Push the dropped files into the underlying <input> using a
            // DataTransfer so the form sees them on submit.
            const dt = new DataTransfer();
            const start = this.multiple ? Array.from(this.$refs.input.files || []) : [];
            start.concat(accepted).forEach((f) => dt.items.add(f));
            this.$refs.input.files = dt.files;
            this.refreshFiles();
        },

        onChange() {
            this.refreshFiles();
        },

        refreshFiles() {
            const list = Array.from(this.$refs.input.files || []);
            this.files = list.map((f) => ({
                name: f.name,
                size: f.size,
                type: f.type,
                preview: f.type.startsWith('image/') ? URL.createObjectURL(f) : null,
            }));
        },

        removeAt(i) {
            const dt = new DataTransfer();
            Array.from(this.$refs.input.files).forEach((f, idx) => { if (idx !== i) dt.items.add(f); });
            this.$refs.input.files = dt.files;
            this.refreshFiles();
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
