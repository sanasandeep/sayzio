@php $s = $block->settings ?? []; @endphp
@php
$inputClass = 'theme-input w-full';
$selectClass = $inputClass;
$labelClass = 'block text-xs mb-1';
@endphp
<style>.block-settings-form label { color: var(--text-faint); } .block-settings-form .glass { background: var(--bg-glass); border: 1px solid var(--border-glass); }</style>
<div class="block-settings-form">

@if($block->type === 'link')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Link Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://" class="{{ $inputClass }}"></div>
    @include('user.links.partials.icon-picker', ['fieldName' => 'settings[icon]', 'currentValue' => $s['icon'] ?? '', 'labelText' => 'Icon', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[thumbnail]', 'currentValue' => $s['thumbnail'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Thumbnail', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
</div>

@elseif($block->type === 'link_big')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Link Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://" class="{{ $inputClass }}"></div>
    @include('user.links.partials.icon-picker', ['fieldName' => 'settings[icon]', 'currentValue' => $s['icon'] ?? '', 'labelText' => 'Icon', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[thumbnail]', 'currentValue' => $s['thumbnail'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Thumbnail', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Background Color</label><input type="color" name="settings[bg_color]" value="{{ $s['bg_color'] ?? '#7c3aed' }}" class="w-full h-10 rounded-xl cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
</div>

@elseif($block->type === 'heading')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Size</label><select name="settings[size]" class="{{ $selectClass }}"><option value="h1" {{ ($s['size'] ?? '') === 'h1' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H1</option><option value="h2" {{ ($s['size'] ?? '') === 'h2' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H2</option><option value="h3" {{ ($s['size'] ?? '') === 'h3' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H3</option></select></div>
        <div><label class="{{ $labelClass }}">Align</label><select name="settings[align]" class="{{ $selectClass }}"><option value="left" {{ ($s['align'] ?? '') === 'left' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Left</option><option value="center" {{ ($s['align'] ?? '') === 'center' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Center</option><option value="right" {{ ($s['align'] ?? '') === 'right' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Right</option></select></div>
    </div>
</div>

@elseif($block->type === 'heading_gradient')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">From Color</label><input type="color" name="settings[from_color]" value="{{ $s['from_color'] ?? '#7c3aed' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
        <div><label class="{{ $labelClass }}">To Color</label><input type="color" name="settings[to_color]" value="{{ $s['to_color'] ?? '#ec4899' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Size</label><select name="settings[size]" class="{{ $selectClass }}"><option value="h1" {{ ($s['size'] ?? '') === 'h1' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H1</option><option value="h2" {{ ($s['size'] ?? '') === 'h2' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H2</option><option value="h3" {{ ($s['size'] ?? '') === 'h3' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H3</option></select></div>
        <div><label class="{{ $labelClass }}">Align</label><select name="settings[align]" class="{{ $selectClass }}"><option value="left" {{ ($s['align'] ?? '') === 'left' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Left</option><option value="center" {{ ($s['align'] ?? '') === 'center' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Center</option><option value="right" {{ ($s['align'] ?? '') === 'right' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Right</option></select></div>
    </div>
</div>

@elseif($block->type === 'heading_logo')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[logo_url]', 'currentValue' => $s['logo_url'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Logo', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Size</label><select name="settings[size]" class="{{ $selectClass }}"><option value="h1" {{ ($s['size'] ?? '') === 'h1' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H1</option><option value="h2" {{ ($s['size'] ?? '') === 'h2' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H2</option><option value="h3" {{ ($s['size'] ?? '') === 'h3' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H3</option></select></div>
        <div><label class="{{ $labelClass }}">Align</label><select name="settings[align]" class="{{ $selectClass }}"><option value="center" {{ ($s['align'] ?? '') === 'center' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Center</option><option value="left" {{ ($s['align'] ?? '') === 'left' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Left</option></select></div>
    </div>
</div>

@elseif($block->type === 'heading_morph')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Size</label><select name="settings[size]" class="{{ $selectClass }}"><option value="h1" {{ ($s['size'] ?? '') === 'h1' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H1</option><option value="h2" {{ ($s['size'] ?? '') === 'h2' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H2</option></select></div>
        <div><label class="{{ $labelClass }}">Align</label><select name="settings[align]" class="{{ $selectClass }}"><option value="center" {{ ($s['align'] ?? '') === 'center' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Center</option><option value="left" {{ ($s['align'] ?? '') === 'left' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Left</option></select></div>
    </div>
</div>

@elseif($block->type === 'paragraph')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><textarea name="settings[text]" rows="3" class="{{ $inputClass }}">{{ $s['text'] ?? '' }}</textarea></div>
    <div><label class="{{ $labelClass }}">Align</label><select name="settings[align]" class="{{ $selectClass }}"><option value="left" {{ ($s['align'] ?? '') === 'left' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Left</option><option value="center" {{ ($s['align'] ?? '') === 'center' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Center</option><option value="right" {{ ($s['align'] ?? '') === 'right' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Right</option></select></div>
</div>

@elseif($block->type === 'paragraph_rich')
<div><label class="{{ $labelClass }}">Rich Text HTML</label><textarea name="settings[html]" rows="5" class="{{ $inputClass }}">{{ $s['html'] ?? '' }}</textarea></div>

@elseif($block->type === 'divider')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Style</label><select name="settings[style]" class="{{ $selectClass }}"><option value="solid" {{ ($s['style'] ?? '') === 'solid' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Solid</option><option value="dashed" {{ ($s['style'] ?? '') === 'dashed' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Dashed</option><option value="dotted" {{ ($s['style'] ?? '') === 'dotted' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Dotted</option></select></div>
    <div><label class="{{ $labelClass }}">Color</label><input type="text" name="settings[color]" value="{{ $s['color'] ?? 'rgba(255,255,255,0.1)' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'spacer')
<div><label class="{{ $labelClass }}">Height (px)</label><input type="number" name="settings[height]" value="{{ $s['height'] ?? 20 }}" min="4" max="200" class="{{ $inputClass }}"></div>

@elseif(in_array($block->type, ['list', 'list_numbered']))
<div x-data="{ items: {{ json_encode($s['items'] ?? ['Item 1']) }} }">
    <label class="{{ $labelClass }}">Items</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="flex gap-2 mb-2"><input type="text" x-model="items[i]" :name="'settings[items][' + i + ']'" class="{{ $inputClass }}"><button type="button" @click="items.splice(i,1)" class="text-red-400/60 hover:text-red-400 px-2"><i class="fas fa-times text-xs"></i></button></div>
    </template>
    <button type="button" @click="items.push('')" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add Item</button>
    @if($block->type === 'list')<div class="mt-3">@include('user.links.partials.icon-picker', ['fieldName' => 'settings[icon]', 'currentValue' => $s['icon'] ?? 'fa-check', 'labelText' => 'List Icon', 'inputClass' => $inputClass, 'labelClass' => $labelClass])</div>@endif
</div>

@elseif($block->type === 'list_pricing')
<div x-data="{ items: {{ json_encode($s['items'] ?? [['name'=>'Feature','price'=>'$10','included'=>true]]) }} }">
    <label class="{{ $labelClass }}">Pricing Items</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2">
            <div class="grid grid-cols-2 gap-2 mb-2">
                <input type="text" x-model="items[i].name" :name="'settings[items]['+i+'][name]'" placeholder="Feature" class="{{ $inputClass }}">
                <input type="text" x-model="items[i].price" :name="'settings[items]['+i+'][price]'" placeholder="$10" class="{{ $inputClass }}">
            </div>
            <label class="flex items-center gap-2 text-xs text-white/40"><input type="checkbox" x-model="items[i].included" :name="'settings[items]['+i+'][included]'" value="1" class="rounded text-violet-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">Included</label>
            <button type="button" @click="items.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400 mt-1"><i class="fas fa-times mr-1"></i>Remove</button>
        </div>
    </template>
    <button type="button" @click="items.push({name:'',price:'',included:true})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add Item</button>
</div>

@elseif($block->type === 'alert')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Type</label><select name="settings[type]" class="{{ $selectClass }}"><option value="info" {{ ($s['type'] ?? '') === 'info' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Info</option><option value="success" {{ ($s['type'] ?? '') === 'success' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Success</option><option value="warning" {{ ($s['type'] ?? '') === 'warning' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Warning</option><option value="error" {{ ($s['type'] ?? '') === 'error' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Error</option></select></div>
</div>

@elseif($block->type === 'badge')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Color</label><input type="color" name="settings[color]" value="{{ $s['color'] ?? '#7c3aed' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
        <div><label class="{{ $labelClass }}">Text Color</label><input type="color" name="settings[text_color]" value="{{ $s['text_color'] ?? '#ffffff' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
    </div>
</div>

@elseif($block->type === 'image')
@php $imgStyle = $s['_image_style'] ?? []; $imgLink = $s['_link'] ?? []; @endphp
<div class="space-y-3">
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[url]', 'currentValue' => $s['url'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Image', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Alt Text</label><input type="text" name="settings[alt]" value="{{ $s['alt'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>
@include('user.links.partials.image-style-settings', ['imgStyle' => $imgStyle, 'inputClass' => $inputClass, 'labelClass' => $labelClass, 'selectClass' => $selectClass])
@include('user.links.partials.block-link-settings', ['imgLink' => $imgLink, 'inputClass' => $inputClass, 'labelClass' => $labelClass])

@elseif(in_array($block->type, ['image_grid', 'image_slider', 'image_slider_v2']))
@php
    $imgStyle = $s['_image_style'] ?? [];
    $imgLink = $s['_link'] ?? [];
    $gridImgId = 'gridimg_' . $block->id;
@endphp
<div x-data="imageListUploader_{{ $gridImgId }}()">
    <label class="{{ $labelClass }}">Images</label>
    <template x-for="(img, i) in images" :key="i">
        <div class="flex gap-2 mb-2 items-center">
            <template x-if="isImageUrl(img)">
                <img :src="img" class="w-8 h-8 rounded object-cover flex-shrink-0" alt="">
            </template>
            <input type="url" x-model="images[i]" :name="'settings[images][' + i + ']'" placeholder="https://..." class="{{ $inputClass }} flex-1">
            <button type="button" @click="images.splice(i,1)" class="text-red-400/60 hover:text-red-400 px-1.5 flex-shrink-0"><i class="fas fa-times text-xs"></i></button>
        </div>
    </template>
    <div class="flex items-center gap-2 mt-1 flex-wrap">
        <button type="button" @click="images.push('')" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add URL</button>
        <span class="text-white/10">|</span>
        <button type="button" @click="$refs.gridFileInput.click()" class="text-xs text-emerald-400 hover:text-emerald-300"><i class="fas fa-cloud-upload-alt mr-1"></i>Upload</button>
        <span class="text-white/10">|</span>
        <button type="button" @click="toggleVault()" class="text-xs text-cyan-400 hover:text-cyan-300"><i class="fas fa-folder-open mr-1"></i><span x-text="showVault ? 'Close My Files' : 'From My Files'"></span></button>
    </div>
    <input type="file" x-ref="gridFileInput" accept=".jpg,.jpeg,.png,.gif,.webp,.svg" multiple class="hidden" @change="uploadMultiple($event)">
    <template x-if="uploading">
        <div class="mt-2 rounded-lg p-2" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
            <div class="w-full rounded-full h-1.5 mb-1" style="background: var(--bg-glass-input);">
                <div class="h-1.5 rounded-full bg-gradient-to-r from-violet-500 to-pink-500 transition-all" :style="'width:' + uploadProgress + '%'"></div>
            </div>
            <p class="text-[10px] text-violet-300"><i class="fas fa-spinner fa-spin mr-1"></i>Uploading...</p>
        </div>
    </template>
    <template x-if="showVault">
        <div class="mt-2 rounded-lg overflow-hidden" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
            <div class="p-2 flex items-center gap-2" style="border-bottom: 1px solid var(--border-subtle, rgba(255,255,255,0.06));">
                <input type="text" x-model="vaultSearch" placeholder="Search My Files…" class="flex-1 text-xs px-2.5 py-1.5 rounded-lg outline-none" style="background: var(--bg-glass-input); color: var(--text-primary); border: 1px solid var(--border-glass);">
                <button type="button" @click="loadVault()" class="text-[10px] text-violet-400 hover:text-violet-300 px-2"><i class="fas fa-sync-alt"></i></button>
            </div>
            <div class="max-h-48 overflow-y-auto p-2">
                <template x-if="vaultLoading"><div class="py-6 text-center"><i class="fas fa-spinner fa-spin text-violet-400/60"></i></div></template>
                <template x-if="!vaultLoading && vaultFiles.length === 0"><div class="py-6 text-center text-xs text-white/30">No images in your vault yet</div></template>
                <div class="grid grid-cols-4 gap-1.5">
                    <template x-for="f in filteredVault" :key="f.id">
                        <button type="button" @click="addFromVault(f)" class="rounded-lg overflow-hidden text-left transition-all hover:ring-2 hover:ring-violet-500/50" style="background: var(--bg-glass-input);">
                            <img :src="f.url" class="w-full aspect-square object-cover" :alt="f.original_name">
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </template>
    @if($block->type === 'image_grid')<div class="mt-3"><label class="{{ $labelClass }}">Columns</label><select name="settings[columns]" class="{{ $selectClass }}"><option value="2" {{ ($s['columns'] ?? 3) == 2 ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">2</option><option value="3" {{ ($s['columns'] ?? 3) == 3 ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">3</option><option value="4" {{ ($s['columns'] ?? 3) == 4 ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">4</option></select></div>@endif
</div>
<script>
function imageListUploader_{{ $gridImgId }}() {
    return {
        images: {!! json_encode($s['images'] ?? []) !!},
        uploading: false,
        uploadProgress: 0,
        showVault: false,
        vaultFiles: [],
        vaultLoading: false,
        vaultSearch: '',
        get filteredVault() {
            if (!this.vaultSearch) return this.vaultFiles;
            const s = this.vaultSearch.toLowerCase();
            return this.vaultFiles.filter((f) => (f.original_name || '').toLowerCase().includes(s));
        },
        toggleVault() {
            this.showVault = !this.showVault;
            if (this.showVault && this.vaultFiles.length === 0) this.loadVault();
        },
        async loadVault() {
            this.vaultLoading = true;
            try {
                const r = await fetch('{{ route("user.files.index") }}?type=image&page=1', { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                this.vaultFiles = data.files || [];
            } catch (e) { this.vaultFiles = []; }
            this.vaultLoading = false;
        },
        addFromVault(f) { if (f && f.url) this.images.push(f.url); },
        isImageUrl(u) { return u && (u.startsWith('http') || u.startsWith('/')); },
        async uploadMultiple(e) {
            var files = Array.from(e.target.files);
            if (!files.length) return;
            this.uploading = true;
            this.uploadProgress = 0;
            var done = 0;
            for (var f of files) {
                var fd = new FormData();
                fd.append('file', f);
                try {
                    var r = await fetch('{{ route("user.files.upload") }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                        body: fd
                    });
                    var data = await r.json();
                    if (data.success && data.file) this.images.push(data.file.url);
                } catch(err) {}
                done++;
                this.uploadProgress = Math.round((done / files.length) * 100);
            }
            this.uploading = false;
            e.target.value = '';
        }
    }
}
</script>
@include('user.links.partials.image-style-settings', ['imgStyle' => $imgStyle, 'inputClass' => $inputClass, 'labelClass' => $labelClass, 'selectClass' => $selectClass])
@include('user.links.partials.block-link-settings', ['imgLink' => $imgLink, 'inputClass' => $inputClass, 'labelClass' => $labelClass])

@elseif(in_array($block->type, ['video', 'header_video']))
<div class="space-y-3">
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[url]', 'currentValue' => $s['url'] ?? '', 'acceptTypes' => 'video', 'labelText' => 'Video', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    @if($block->type === 'header_video')
    <div class="flex gap-4">
        <label class="flex items-center gap-2 text-xs text-white/40"><input type="checkbox" name="settings[autoplay]" value="1" {{ ($s['autoplay'] ?? false) ? 'checked' : '' }} class="rounded text-violet-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">Autoplay</label>
        <label class="flex items-center gap-2 text-xs text-white/40"><input type="checkbox" name="settings[muted]" value="1" {{ ($s['muted'] ?? false) ? 'checked' : '' }} class="rounded text-violet-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">Muted</label>
        <label class="flex items-center gap-2 text-xs text-white/40"><input type="checkbox" name="settings[loop]" value="1" {{ ($s['loop'] ?? false) ? 'checked' : '' }} class="rounded text-violet-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">Loop</label>
    </div>
    @endif
</div>

@elseif($block->type === 'audio')
<div class="space-y-3">
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[url]', 'currentValue' => $s['url'] ?? '', 'acceptTypes' => 'audio', 'labelText' => 'Audio File', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif(in_array($block->type, ['pdf_document', 'powerpoint', 'excel']))
<div class="space-y-3">
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[url]', 'currentValue' => $s['url'] ?? '', 'acceptTypes' => 'document', 'labelText' => 'File', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'socials')
@include('user.links.partials.socials-form', ['s' => $s])

@elseif(in_array($block->type, ['socials_multi', 'socials_custom']))
@include('user.links.partials.socials-form', ['s' => $s])
@if($block->type === 'socials_custom')
<div class="mt-3 grid grid-cols-2 gap-3">
    <div><label class="{{ $labelClass }}">Style</label><select name="settings[style]" class="{{ $selectClass }}"><option value="rounded" {{ ($s['style'] ?? '') === 'rounded' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Rounded</option><option value="square" {{ ($s['style'] ?? '') === 'square' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Square</option><option value="circle" {{ ($s['style'] ?? '') === 'circle' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Circle</option></select></div>
    <div><label class="{{ $labelClass }}">Size</label><select name="settings[size]" class="{{ $selectClass }}"><option value="sm" {{ ($s['size'] ?? '') === 'sm' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Small</option><option value="md" {{ ($s['size'] ?? '') === 'md' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Medium</option><option value="lg" {{ ($s['size'] ?? '') === 'lg' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Large</option></select></div>
</div>
@endif

@elseif(in_array($block->type, ['instagram_media', 'tiktok_video', 'twitter_tweet', 'twitter_video', 'facebook_post', 'reddit_post', 'telegram_post', 'rumble_video', 'vk_video', 'soundcloud', 'tidal', 'mixcloud', 'anchor_fm', 'apple_music', 'typeform', 'calendly']))
<div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://..." class="{{ $inputClass }}"></div>

@elseif(in_array($block->type, ['tiktok_profile', 'twitter_profile', 'pinterest_profile', 'snapchat']))
<div><label class="{{ $labelClass }}">Username</label><input type="text" name="settings[username]" value="{{ $s['username'] ?? '' }}" placeholder="@username" class="{{ $inputClass }}"></div>

@elseif($block->type === 'rss_feed')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">RSS Feed URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Number of items</label><input type="number" name="settings[count]" value="{{ $s['count'] ?? 5 }}" min="1" max="20" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'spotify')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Spotify URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Type</label><select name="settings[type]" class="{{ $selectClass }}"><option value="track" {{ ($s['type'] ?? '') === 'track' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Track</option><option value="album" {{ ($s['type'] ?? '') === 'album' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Album</option><option value="playlist" {{ ($s['type'] ?? '') === 'playlist' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Playlist</option></select></div>
</div>

@elseif($block->type === 'youtube')
<div><label class="{{ $labelClass }}">YouTube Video ID or URL</label><input type="text" name="settings[video_id]" value="{{ $s['video_id'] ?? '' }}" class="{{ $inputClass }}"></div>

@elseif($block->type === 'youtube_feed')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Channel ID</label><input type="text" name="settings[channel_id]" value="{{ $s['channel_id'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Videos to show</label><input type="number" name="settings[count]" value="{{ $s['count'] ?? 3 }}" min="1" max="10" class="{{ $inputClass }}"></div>
</div>

@elseif(in_array($block->type, ['vimeo']))
<div><label class="{{ $labelClass }}">Vimeo Video ID</label><input type="text" name="settings[video_id]" value="{{ $s['video_id'] ?? '' }}" class="{{ $inputClass }}"></div>

@elseif(in_array($block->type, ['twitch', 'kick']))
<div><label class="{{ $labelClass }}">Channel Name</label><input type="text" name="settings[channel]" value="{{ $s['channel'] ?? '' }}" class="{{ $inputClass }}"></div>

@elseif($block->type === 'discord_server')
<div><label class="{{ $labelClass }}">Server ID</label><input type="text" name="settings[server_id]" value="{{ $s['server_id'] ?? '' }}" class="{{ $inputClass }}"></div>

@elseif($block->type === 'email_collector')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Placeholder</label><input type="text" name="settings[placeholder]" value="{{ $s['placeholder'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'phone_collector')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Placeholder</label><input type="text" name="settings[placeholder]" value="{{ $s['placeholder'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'contact_form')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif(in_array($block->type, ['whatsapp_widget', 'whatsapp_item']))
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Phone (with country code)</label><input type="text" name="settings[phone]" value="{{ $s['phone'] ?? '' }}" placeholder="+1234567890" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Default Message</label><input type="text" name="settings[message]" value="{{ $s['message'] ?? '' }}" class="{{ $inputClass }}"></div>
    @if($block->type === 'whatsapp_widget')<div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>@endif
    @if($block->type === 'whatsapp_item')<div><label class="{{ $labelClass }}">Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>@endif
</div>

@elseif($block->type === 'verified_heading')
<div class="space-y-3">
    <div class="p-3 rounded-xl text-xs" style="background: rgba(29,155,240,0.1); color: #1d9bf0; border: 1px solid rgba(29,155,240,0.2);">
        <i class="fas fa-check-circle mr-1"></i> Verified block — text is locked and cannot be changed.
    </div>
    <div><label class="{{ $labelClass }}">Verified Name</label><input type="text" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }} opacity-50" disabled></div>
    <div><label class="{{ $labelClass }}">Font Size (px)</label><input type="number" name="settings[font_size]" value="{{ $s['font_size'] ?? '24' }}" min="12" max="72" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Alignment</label>
        <select name="settings[alignment]" class="{{ $inputClass }}">
            <option value="left" {{ ($s['alignment'] ?? 'center') === 'left' ? 'selected' : '' }}>Left</option>
            <option value="center" {{ ($s['alignment'] ?? 'center') === 'center' ? 'selected' : '' }}>Center</option>
            <option value="right" {{ ($s['alignment'] ?? 'center') === 'right' ? 'selected' : '' }}>Right</option>
        </select>
    </div>
    <input type="hidden" name="settings[text]" value="{{ $s['text'] ?? '' }}">
    <input type="hidden" name="settings[verified]" value="1">
    <input type="hidden" name="settings[locked_text]" value="1">
</div>

@elseif($block->type === 'verified_avatar')
<div class="space-y-3">
    <div class="p-3 rounded-xl text-xs" style="background: rgba(29,155,240,0.1); color: #1d9bf0; border: 1px solid rgba(29,155,240,0.2);">
        <i class="fas fa-check-circle mr-1"></i> Verified block — image is locked and cannot be changed.
    </div>
    @if(!empty($s['image_url']))
    <div class="flex justify-center"><img src="{{ $s['image_url'] }}" class="w-20 h-20 rounded-full object-cover" style="border: 2px solid rgba(29,155,240,0.3);"></div>
    @endif
    <div><label class="{{ $labelClass }}">Size (px)</label><input type="number" name="settings[size]" value="{{ $s['size'] ?? '100' }}" min="48" max="200" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Shape</label>
        <select name="settings[shape]" class="{{ $inputClass }}">
            <option value="circle" {{ ($s['shape'] ?? 'circle') === 'circle' ? 'selected' : '' }}>Circle</option>
            <option value="rounded" {{ ($s['shape'] ?? 'circle') === 'rounded' ? 'selected' : '' }}>Rounded Square</option>
        </select>
    </div>
    <input type="hidden" name="settings[image_url]" value="{{ $s['image_url'] ?? '' }}">
    <input type="hidden" name="settings[verified]" value="1">
    <input type="hidden" name="settings[locked_image]" value="1">
</div>

@elseif($block->type === 'email_subscribe')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Placeholder</label><input type="text" name="settings[placeholder]" value="{{ $s['placeholder'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Success Message</label><input type="text" name="settings[success_message]" value="{{ $s['success_message'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="flex items-center gap-2">
        <input type="hidden" name="settings[name_field]" value="0">
        <input type="checkbox" name="settings[name_field]" value="1" {{ ($s['name_field'] ?? false) ? 'checked' : '' }} class="rounded">
        <label class="{{ $labelClass }}">Show Name Field</label>
    </div>
</div>

@elseif($block->type === 'whatsapp_channel_subscribe')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Channel URL</label><input type="url" name="settings[channel_url]" value="{{ $s['channel_url'] ?? '' }}" placeholder="https://whatsapp.com/channel/..." class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'whatsapp_number_subscribe')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Your WhatsApp Number</label><input type="text" name="settings[phone]" value="{{ $s['phone'] ?? '' }}" placeholder="+1234567890" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Default Subscription Message</label><input type="text" name="settings[default_message]" value="{{ $s['default_message'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="flex items-center gap-2">
        <input type="hidden" name="settings[collect_phone]" value="0">
        <input type="checkbox" name="settings[collect_phone]" value="1" {{ ($s['collect_phone'] ?? false) ? 'checked' : '' }} class="rounded">
        <label class="{{ $labelClass }}">Collect visitor's phone number</label>
    </div>
</div>

@elseif(in_array($block->type, ['faq', 'faq_v2']))
<div x-data="{ items: {{ json_encode($s['items'] ?? [['question' => '', 'answer' => '']]) }} }">
    <label class="{{ $labelClass }}">FAQ Items</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2">
            <input type="text" x-model="items[i].question" :name="'settings[items]['+i+'][question]'" placeholder="Question" class="{{ $inputClass }} mb-2">
            <textarea x-model="items[i].answer" :name="'settings[items]['+i+'][answer]'" placeholder="Answer" rows="2" class="{{ $inputClass }}"></textarea>
            <button type="button" @click="items.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400 mt-1"><i class="fas fa-times mr-1"></i>Remove</button>
        </div>
    </template>
    <button type="button" @click="items.push({question:'',answer:''})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add Item</button>
</div>

@elseif($block->type === 'poll')
<div x-data="{ options: {{ json_encode($s['options'] ?? ['Option A', 'Option B']) }} }">
    <div class="mb-3"><label class="{{ $labelClass }}">Question</label><input type="text" name="settings[question]" value="{{ $s['question'] ?? '' }}" class="{{ $inputClass }}"></div>
    <label class="{{ $labelClass }}">Options</label>
    <template x-for="(opt, i) in options" :key="i">
        <div class="flex gap-2 mb-2"><input type="text" x-model="options[i]" :name="'settings[options]['+i+']'" class="{{ $inputClass }}"><button type="button" @click="options.splice(i,1)" class="text-red-400/60 hover:text-red-400 px-2"><i class="fas fa-times text-xs"></i></button></div>
    </template>
    <button type="button" @click="options.push('')" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add Option</button>
</div>

@elseif($block->type === 'testimonials')
<div x-data="{ items: {{ json_encode($s['items'] ?? [['name'=>'','text'=>'','rating'=>5]]) }} }">
    <label class="{{ $labelClass }}">Testimonials</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2 space-y-2">
            <input type="text" x-model="items[i].name" :name="'settings[items]['+i+'][name]'" placeholder="Name" class="{{ $inputClass }}">
            <textarea x-model="items[i].text" :name="'settings[items]['+i+'][text]'" placeholder="Testimonial" rows="2" class="{{ $inputClass }}"></textarea>
            <input type="number" x-model="items[i].rating" :name="'settings[items]['+i+'][rating]'" min="1" max="5" placeholder="Rating 1-5" class="{{ $inputClass }}">
            <button type="button" @click="items.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400"><i class="fas fa-times mr-1"></i>Remove</button>
        </div>
    </template>
    <button type="button" @click="items.push({name:'',text:'',rating:5})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add</button>
</div>

@elseif($block->type === 'review')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Review Text</label><textarea name="settings[text]" rows="2" class="{{ $inputClass }}">{{ $s['text'] ?? '' }}</textarea></div>
    <div><label class="{{ $labelClass }}">Rating (1-5)</label><input type="number" name="settings[rating]" value="{{ $s['rating'] ?? 5 }}" min="1" max="5" class="{{ $inputClass }}"></div>
</div>

@elseif(in_array($block->type, ['timeline', 'timeline_staged']))
<div x-data="{ items: {{ json_encode($s['items'] ?? [['title'=>'','description'=>'']]) }} }">
    <label class="{{ $labelClass }}">Timeline Items</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2 space-y-2">
            <input type="text" x-model="items[i].title" :name="'settings[items]['+i+'][title]'" placeholder="Title" class="{{ $inputClass }}">
            <input type="text" x-model="items[i].description" :name="'settings[items]['+i+'][description]'" placeholder="Description" class="{{ $inputClass }}">
            @if($block->type === 'timeline')<input type="text" x-model="items[i].date" :name="'settings[items]['+i+'][date]'" placeholder="Date" class="{{ $inputClass }}">@endif
            @if($block->type === 'timeline_staged')<select x-model="items[i].status" :name="'settings[items]['+i+'][status]'" class="{{ $selectClass }}"><option value="completed" style="background: var(--bg-body); color: var(--text-primary);">Completed</option><option value="active" style="background: var(--bg-body); color: var(--text-primary);">Active</option><option value="upcoming" style="background: var(--bg-body); color: var(--text-primary);">Upcoming</option></select>@endif
            <button type="button" @click="items.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400"><i class="fas fa-times mr-1"></i>Remove</button>
        </div>
    </template>
    @php $extra = $block->type === 'timeline' ? "date:''" : "status:'upcoming'"; @endphp
    <button type="button" @click="items.push({title:'',description:'',{!! $extra !!}})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add Item</button>
</div>

@elseif($block->type === 'product')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Product Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><textarea name="settings[description]" rows="2" class="{{ $inputClass }}">{{ $s['description'] ?? '' }}</textarea></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Price</label><input type="text" name="settings[price]" value="{{ $s['price'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Badge</label><input type="text" name="settings[badge]" value="{{ $s['badge'] ?? '' }}" placeholder="Sale, New" class="{{ $inputClass }}"></div>
    </div>
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[image]', 'currentValue' => $s['image'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Product Image', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Buy URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'service')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Service Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><textarea name="settings[description]" rows="2" class="{{ $inputClass }}">{{ $s['description'] ?? '' }}</textarea></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Price</label><input type="text" name="settings[price]" value="{{ $s['price'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div>@include('user.links.partials.icon-picker', ['fieldName' => 'settings[icon]', 'currentValue' => $s['icon'] ?? '', 'labelText' => 'Icon', 'inputClass' => $inputClass, 'labelClass' => $labelClass])</div>
    </div>
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'donation')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><textarea name="settings[description]" rows="2" class="{{ $inputClass }}">{{ $s['description'] ?? '' }}</textarea></div>
    <div><label class="{{ $labelClass }}">Donation URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'coupon')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Coupon Code</label><input type="text" name="settings[code]" value="{{ $s['code'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Expires</label><input type="datetime-local" name="settings[expires]" value="{{ $s['expires'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'price')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Plan Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Amount</label><input type="text" name="settings[amount]" value="{{ $s['amount'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Period</label><input type="text" name="settings[period]" value="{{ $s['period'] ?? '' }}" placeholder="/month" class="{{ $inputClass }}"></div>
    </div>
    <div><label class="{{ $labelClass }}">Sign Up URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'paypal')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">PayPal Email</label><input type="email" name="settings[email]" value="{{ $s['email'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Amount</label><input type="text" name="settings[amount]" value="{{ $s['amount'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Currency</label><input type="text" name="settings[currency]" value="{{ $s['currency'] ?? 'USD' }}" class="{{ $inputClass }}"></div>
    </div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? 'Pay Now' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'countdown')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Target Date</label><input type="datetime-local" name="settings[target_date]" value="{{ $s['target_date'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'progress')
<div x-data="{ items: {{ json_encode($s['items'] ?? [['label'=>'Progress','value'=>75,'color'=>'#7c3aed']]) }} }">
    <label class="{{ $labelClass }}">Progress Bars</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2 grid grid-cols-3 gap-2">
            <input type="text" x-model="items[i].label" :name="'settings[items]['+i+'][label]'" placeholder="Label" class="{{ $inputClass }}">
            <input type="number" x-model="items[i].value" :name="'settings[items]['+i+'][value]'" min="0" max="100" placeholder="%" class="{{ $inputClass }}">
            <input type="color" x-model="items[i].color" :name="'settings[items]['+i+'][color]'" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
        </div>
    </template>
    <button type="button" @click="items.push({label:'',value:50,color:'#7c3aed'})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add</button>
</div>

@elseif($block->type === 'cta_button')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Button Color</label><input type="color" name="settings[color]" value="{{ $s['color'] ?? '#7c3aed' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
        <div><label class="{{ $labelClass }}">Text Color</label><input type="color" name="settings[text_color]" value="{{ $s['text_color'] ?? '#ffffff' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
    </div>
    <div><label class="{{ $labelClass }}">Size</label><select name="settings[size]" class="{{ $selectClass }}"><option value="sm" {{ ($s['size'] ?? '') === 'sm' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Small</option><option value="md" {{ ($s['size'] ?? '') === 'md' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Medium</option><option value="lg" {{ ($s['size'] ?? '') === 'lg' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Large</option></select></div>
</div>

@elseif($block->type === 'notification')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Type</label><select name="settings[type]" class="{{ $selectClass }}"><option value="info" {{ ($s['type'] ?? '') === 'info' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Info</option><option value="success" {{ ($s['type'] ?? '') === 'success' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Success</option><option value="warning" {{ ($s['type'] ?? '') === 'warning' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Warning</option></select></div>
</div>

@elseif($block->type === 'ticker')
<div x-data="{ items: {{ json_encode($s['items'] ?? ['Text 1']) }} }">
    <label class="{{ $labelClass }}">Ticker Items</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="flex gap-2 mb-2"><input type="text" x-model="items[i]" :name="'settings[items]['+i+']'" class="{{ $inputClass }}"><button type="button" @click="items.splice(i,1)" class="text-red-400/60 px-2"><i class="fas fa-times text-xs"></i></button></div>
    </template>
    <button type="button" @click="items.push('')" class="text-xs text-violet-400"><i class="fas fa-plus mr-1"></i>Add</button>
</div>

@elseif($block->type === 'iframe_embed')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Embed URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Height (px)</label><input type="number" name="settings[height]" value="{{ $s['height'] ?? 400 }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'form')
@php $userForms = auth()->user()->forms()->orderBy('title')->get(['id','title','is_active']); @endphp
<div class="space-y-3">
    <div>
        <label class="{{ $labelClass }}">Form</label>
        @if($userForms->isEmpty())
            <div class="text-xs px-3 py-2 rounded-lg" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: #fbbf24;">
                You haven't built any forms yet. <a href="{{ route('user.forms.create') }}" class="underline font-semibold">Create one →</a>
            </div>
        @else
            <select name="settings[form_id]" class="{{ $inputClass }}">
                <option value="">— Choose a form —</option>
                @foreach($userForms as $f)
                    <option value="{{ $f->id }}" @selected(($s['form_id'] ?? null) == $f->id)>{{ $f->title }} {{ $f->is_active ? '' : '(disabled)' }}</option>
                @endforeach
            </select>
            <p class="text-[10px] mt-1" style="color: var(--text-faint);">The form auto-resizes — height below is the initial frame height.</p>
        @endif
    </div>
    <div><label class="{{ $labelClass }}">Initial height (px)</label><input type="number" name="settings[height]" value="{{ $s['height'] ?? 600 }}" min="200" max="2000" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'custom_html')
<div><label class="{{ $labelClass }}">HTML Code</label><textarea name="settings[html]" rows="6" class="{{ $inputClass }} font-mono">{{ $s['html'] ?? '' }}</textarea></div>

@elseif($block->type === 'file')
<div class="space-y-3">
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[url]', 'currentValue' => $s['url'] ?? '', 'acceptTypes' => 'all', 'labelText' => 'File', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">File Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">File Size</label><input type="text" name="settings[size]" value="{{ $s['size'] ?? '' }}" placeholder="e.g. 2.5 MB" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'external_item')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[image]', 'currentValue' => $s['image'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Image', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
</div>

@elseif($block->type === 'markdown')
<div><label class="{{ $labelClass }}">Markdown Content</label><textarea name="settings[content]" rows="6" class="{{ $inputClass }} font-mono">{{ $s['content'] ?? '' }}</textarea></div>

@elseif(in_array($block->type, ['map', 'yandex_maps']))
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Address</label><input type="text" name="settings[address]" value="{{ $s['address'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Zoom</label><input type="number" name="settings[zoom]" value="{{ $s['zoom'] ?? 14 }}" min="1" max="20" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'vcard')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Full Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Email</label><input type="email" name="settings[email]" value="{{ $s['email'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Phone</label><input type="text" name="settings[phone]" value="{{ $s['phone'] ?? '' }}" class="{{ $inputClass }}"></div>
    </div>
    <div><label class="{{ $labelClass }}">Company</label><input type="text" name="settings[company]" value="{{ $s['company'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Website</label><input type="url" name="settings[website]" value="{{ $s['website'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'avatar')
<div class="space-y-3">
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[url]', 'currentValue' => $s['url'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Avatar Image', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Size (px)</label><input type="number" name="settings[size]" value="{{ $s['size'] ?? 96 }}" min="32" max="256" class="{{ $inputClass }}"></div>
        <div class="flex items-end pb-1"><label class="flex items-center gap-2 text-xs text-white/40"><input type="hidden" name="settings[rounded]" value="0"><input type="checkbox" name="settings[rounded]" value="1" {{ ($s['rounded'] ?? true) ? 'checked' : '' }} class="rounded text-violet-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">Rounded</label></div>
    </div>
</div>

@elseif(in_array($block->type, ['profile_card_v1', 'profile_card_v2', 'profile_card_v3', 'profile_card_v4']))
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[avatar]', 'currentValue' => $s['avatar'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Avatar', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Bio</label><textarea name="settings[bio]" rows="2" class="{{ $inputClass }}">{{ $s['bio'] ?? '' }}</textarea></div>
    @if($block->type === 'profile_card_v2')
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[cover]', 'currentValue' => $s['cover'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Cover Image', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    @endif
</div>

@elseif($block->type === 'qr_code')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">URL to encode</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Size (px)</label><input type="number" name="settings[size]" value="{{ $s['size'] ?? 200 }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'share')
<div><label class="{{ $labelClass }}">Share Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>

@elseif($block->type === 'one_time_offer')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><textarea name="settings[description]" rows="2" class="{{ $inputClass }}">{{ $s['description'] ?? '' }}</textarea></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Price</label><input type="text" name="settings[price]" value="{{ $s['price'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Original Price</label><input type="text" name="settings[original_price]" value="{{ $s['original_price'] ?? '' }}" class="{{ $inputClass }}"></div>
    </div>
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'card')
<div class="space-y-4" x-data="{ bgType: '{{ $s['bg_type'] ?? 'glass' }}' }">
    <div><label class="{{ $labelClass }}">Card Title (optional)</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}" placeholder="Optional section title"></div>

    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Columns</label>
            <select name="settings[columns]" class="{{ $selectClass }}">
                @foreach([1=>'1 Column',2=>'2 Columns',3=>'3 Columns',4=>'4 Columns'] as $v=>$l)
                <option value="{{ $v }}" {{ ($s['columns'] ?? 2) == $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="{{ $labelClass }}">Gap (px)</label><input type="number" name="settings[gap]" value="{{ $s['gap'] ?? 12 }}" min="0" max="48" class="{{ $inputClass }}"></div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Padding (px)</label><input type="number" name="settings[padding]" value="{{ $s['padding'] ?? 16 }}" min="0" max="64" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Border Radius (px)</label><input type="number" name="settings[border_radius]" value="{{ $s['border_radius'] ?? 16 }}" min="0" max="48" class="{{ $inputClass }}"></div>
    </div>

    <div><label class="{{ $labelClass }}">Background Type</label>
        <select name="settings[bg_type]" x-model="bgType" class="{{ $selectClass }}">
            <option value="glass">Glassmorphism</option>
            <option value="color">Solid Color</option>
            <option value="gradient">Gradient</option>
            <option value="image">Background Image</option>
            <option value="transparent">Transparent</option>
        </select>
    </div>

    <div x-show="bgType === 'color'" x-cloak>
        <label class="{{ $labelClass }}">Background Color</label>
        <input type="text" name="settings[bg_color]" value="{{ $s['bg_color'] ?? 'rgba(255,255,255,0.06)' }}" class="{{ $inputClass }}" placeholder="e.g. #1a1a2e or rgba(...)">
    </div>

    <div x-show="bgType === 'gradient'" x-cloak>
        <label class="{{ $labelClass }}">CSS Gradient</label>
        <input type="text" name="settings[bg_gradient]" value="{{ $s['bg_gradient'] ?? '' }}" class="{{ $inputClass }}" placeholder="linear-gradient(135deg, #7c3aed, #ec4899)">
    </div>

    <div x-show="bgType === 'image'" x-cloak>
        <label class="{{ $labelClass }}">Image URL</label>
        <input type="url" name="settings[bg_image]" value="{{ $s['bg_image'] ?? '' }}" class="{{ $inputClass }}" placeholder="https://...">
    </div>

    <div x-show="bgType === 'glass'" x-cloak class="space-y-3">
        <div><label class="{{ $labelClass }}">Glass Blur (px)</label><input type="range" name="settings[glass_blur]" value="{{ $s['glass_blur'] ?? 12 }}" min="0" max="40" class="w-full accent-purple-500"></div>
        <div><label class="{{ $labelClass }}">Glass Opacity (%)</label><input type="range" name="settings[glass_opacity]" value="{{ $s['glass_opacity'] ?? 6 }}" min="0" max="30" class="w-full accent-purple-500"></div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Border Color</label><input type="text" name="settings[border_color]" value="{{ $s['border_color'] ?? 'rgba(255,255,255,0.08)' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Border Width (px)</label><input type="number" name="settings[border_width]" value="{{ $s['border_width'] ?? 1 }}" min="0" max="8" class="{{ $inputClass }}"></div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Shadow</label>
            <select name="settings[shadow]" class="{{ $selectClass }}">
                @foreach(['none'=>'None','sm'=>'Small','md'=>'Medium','lg'=>'Large','xl'=>'Extra Large'] as $sv=>$sl)
                <option value="{{ $sv }}" {{ ($s['shadow'] ?? 'none') === $sv ? 'selected' : '' }}>{{ $sl }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="{{ $labelClass }}">Shadow Color</label><input type="text" name="settings[shadow_color]" value="{{ $s['shadow_color'] ?? '#00000040' }}" class="{{ $inputClass }}"></div>
    </div>
</div>

@elseif(in_array($block->type, ['catalog', 'market', 'card_slider', 'scroll_cards', 'nav_menu']))
<div x-data="{ items: {{ json_encode($s['items'] ?? $s['cards'] ?? [['name'=>'','title'=>'','url'=>'']]) }} }">
    <label class="{{ $labelClass }}">Items</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2 space-y-2">
            <input type="text" x-model="items[i].name || items[i].title || items[i].text" :name="'settings[items]['+i+'][name]'" placeholder="Name/Title" class="{{ $inputClass }}">
            <input type="url" x-model="items[i].url" :name="'settings[items]['+i+'][url]'" placeholder="URL" class="{{ $inputClass }}">
            <button type="button" @click="items.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400"><i class="fas fa-times mr-1"></i>Remove</button>
        </div>
    </template>
    <button type="button" @click="items.push({name:'',url:''})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add Item</button>
</div>

@elseif($block->type === 'quiz')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Quiz Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <p class="text-xs text-white/20">Quiz questions are managed through the settings JSON.</p>
</div>

@elseif($block->type === 'chart_pie')
<div x-data="{ items: {{ json_encode($s['items'] ?? [['label'=>'Segment','value'=>50,'color'=>'#7c3aed']]) }} }">
    <label class="{{ $labelClass }}">Chart Segments</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2 grid grid-cols-3 gap-2">
            <input type="text" x-model="items[i].label" :name="'settings[items]['+i+'][label]'" placeholder="Label" class="{{ $inputClass }}">
            <input type="number" x-model="items[i].value" :name="'settings[items]['+i+'][value]'" placeholder="Value" class="{{ $inputClass }}">
            <input type="color" x-model="items[i].color" :name="'settings[items]['+i+'][color]'" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
        </div>
    </template>
    <button type="button" @click="items.push({label:'',value:25,color:'#ec4899'})" class="text-xs text-violet-400"><i class="fas fa-plus mr-1"></i>Add</button>
</div>

@elseif($block->type === 'social_proof')
    @php $userSps = \App\Modules\User\Models\SocialProof::where('user_id', auth()->id())->orderByDesc('id')->get(); @endphp
    <label class="{{ $labelClass }}">Pick a Social Proof campaign</label>
    @if($userSps->isEmpty())
        <p class="text-xs text-white/40 mb-2">You haven't created any campaigns yet.</p>
        <a href="{{ route('user.social-proofs.create') }}" target="_blank" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Create one</a>
    @else
        <select name="settings[social_proof_id]" class="{{ $inputClass }}">
            <option value="">— Choose a campaign —</option>
            @foreach($userSps as $sp)
                <option value="{{ $sp->id }}" {{ (string)($s['social_proof_id'] ?? '') === (string)$sp->id ? 'selected' : '' }}>
                    {{ $sp->name }} ({{ $sp->typeLabel() }}){{ $sp->is_active ? '' : ' — paused' }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-white/40 mt-2"><i class="fas fa-info-circle mr-1"></i> The notification will appear as a floating widget on the biolink page.</p>
    @endif

@else
<p class="text-xs text-white/20">Configure this block's settings below.</p>
@foreach($s as $key => $val)
    @if(is_string($val) || is_numeric($val))
    <div class="mt-2"><label class="{{ $labelClass }}">{{ ucwords(str_replace('_', ' ', $key)) }}</label><input type="text" name="settings[{{ $key }}]" value="{{ $val }}" class="{{ $inputClass }}"></div>
    @endif
@endforeach
@endif

@include('user.links.partials.block-style-settings', ['block' => $block, 'inputClass' => $inputClass, 'labelClass' => $labelClass])

@include('user.links.partials.block-display-settings', ['block' => $block, 'inputClass' => $inputClass, 'labelClass' => $labelClass])
</div>
