@php
    $ist = $imgStyle ?? [];
    $maskShapes = [
        'none' => 'None (Default)',
        'rounded' => 'Rounded',
        'circle' => 'Circle',
        'square' => 'Square',
        'diamond' => 'Diamond',
        'hexagon' => 'Hexagon',
        'octagon' => 'Octagon',
        'star' => 'Star',
        'blob' => 'Blob',
        'arch' => 'Arch',
        'heart' => 'Heart',
        'torn' => 'Torn Edge',
    ];
    // Hero-photo decorations (Task #5922) live in _style so curated
    // variants can carry them; this form just exposes the same keys.
    // Only the single-image block renders decorations.
    $phShowDecor = isset($block) && $block->type === 'image';
    $phSt = $s['_style'] ?? [];
    $phSt = is_array($phSt) ? $phSt : [];
    $phAccentsSel = array_filter(explode(',', (string) ($phSt['_photo_accents'] ?? '')));
    // Custom sticker overlays (Task #5939): sanitized entries persisted in
    // _style already carry the server-derived url for thumbnails.
    $phStickersSaved = is_array($phSt['_photo_stickers'] ?? null) ? array_values($phSt['_photo_stickers']) : [];
    $phStickerMax = \App\Modules\User\Models\BiolinkBlock::PHOTO_STICKER_MAX;
    $phStickerPositions = [
        'top_left' => 'Top left', 'top_right' => 'Top right',
        'bottom_left' => 'Bottom left', 'bottom_right' => 'Bottom right',
        'center_left' => 'Left edge', 'center_right' => 'Right edge',
    ];
    $phAccentOptions = ['starburst' => 'Starburst', 'dots' => 'Dot cluster', 'squiggle' => 'Squiggle', 'ring' => 'Ring', 'blob' => 'Blob'];
    $imgBorderStyles = ['none' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'];
    $imgShadowTypes = [
        'none' => 'None',
        'soft' => 'Soft Shadow',
        'hard' => 'Hard Shadow',
        'glow' => 'Glow',
        'neon' => 'Neon Glow',
        'drop' => 'Drop Shadow (CSS filter)',
    ];
@endphp

<div class="mt-4 pt-4" style="border-top: 1px solid var(--border-subtle);" x-data="{ showImgStyle: false }">
    <button type="button" @click="showImgStyle = !showImgStyle"
            class="w-full flex items-center justify-between text-sm font-medium py-1" style="color: var(--text-muted);">
        <span><i class="fas fa-crop-simple mr-2 text-cyan-400"></i>Image Styling</span>
        <i :class="showImgStyle ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-xs"></i>
    </button>

    <div x-show="showImgStyle" x-cloak x-transition class="mt-3 space-y-4">

        <div>
            <label class="{{ $labelClass }}">Mask / Crop Shape</label>
            <select name="settings[_image_style][mask_shape]" class="{{ $selectClass }}">
                @foreach($maskShapes as $mVal => $mLabel)
                <option value="{{ $mVal }}" {{ ($ist['mask_shape'] ?? 'none') === $mVal ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">{{ $mLabel }}</option>
                @endforeach
            </select>
            <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Clips the image into a specific shape</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="{{ $labelClass }}">Border Radius (px)</label>
                <input type="number" name="settings[_image_style][border_radius]" value="{{ $ist['border_radius'] ?? '' }}" placeholder="12" min="0" max="999" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Object Fit</label>
                <select name="settings[_image_style][object_fit]" class="{{ $selectClass }}">
                    <option value="cover" {{ ($ist['object_fit'] ?? 'cover') === 'cover' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Cover</option>
                    <option value="contain" {{ ($ist['object_fit'] ?? '') === 'contain' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Contain</option>
                    <option value="fill" {{ ($ist['object_fit'] ?? '') === 'fill' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Fill</option>
                    <option value="none" {{ ($ist['object_fit'] ?? '') === 'none' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">None</option>
                </select>
            </div>
        </div>

        <div class="pt-3" style="border-top: 1px solid var(--border-subtle);">
            <p class="text-xs font-semibold mb-2" style="color: var(--text-muted);"><i class="fas fa-border-all mr-1 text-blue-400"></i>Border</p>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $labelClass }}">Style</label>
                    <select name="settings[_image_style][border_style]" class="{{ $selectClass }}">
                        @foreach($imgBorderStyles as $bsVal => $bsLabel)
                        <option value="{{ $bsVal }}" {{ ($ist['border_style'] ?? 'none') === $bsVal ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">{{ $bsLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Width (px)</label>
                    <input type="number" name="settings[_image_style][border_width]" value="{{ $ist['border_width'] ?? '' }}" placeholder="1" min="0" max="10" class="{{ $inputClass }}">
                </div>
            </div>
            <div class="mt-2">
                <label class="{{ $labelClass }}">Border Color</label>
                <input type="color" name="settings[_image_style][border_color]" value="{{ $ist['border_color'] ?? '#ffffff20' }}" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
            </div>
        </div>

        <div class="pt-3" style="border-top: 1px solid var(--border-subtle);">
            <p class="text-xs font-semibold mb-2" style="color: var(--text-muted);"><i class="fas fa-cloud mr-1 text-blue-400"></i>Shadow</p>
            <div>
                <label class="{{ $labelClass }}">Shadow Type</label>
                <select name="settings[_image_style][shadow_type]" class="{{ $selectClass }}">
                    @foreach($imgShadowTypes as $shVal => $shLabel)
                    <option value="{{ $shVal }}" {{ ($ist['shadow_type'] ?? 'none') === $shVal ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">{{ $shLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-2">
                <div>
                    <label class="{{ $labelClass }}">Shadow Color</label>
                    <input type="color" name="settings[_image_style][shadow_color]" value="{{ $ist['shadow_color'] ?? '#00000040' }}" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Shadow Blur (px)</label>
                    <input type="number" name="settings[_image_style][shadow_blur]" value="{{ $ist['shadow_blur'] ?? 12 }}" min="0" max="80" class="{{ $inputClass }}">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-2">
                <div>
                    <label class="{{ $labelClass }}">X Offset</label>
                    <input type="number" name="settings[_image_style][shadow_x]" value="{{ $ist['shadow_x'] ?? 0 }}" min="-40" max="40" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Y Offset</label>
                    <input type="number" name="settings[_image_style][shadow_y]" value="{{ $ist['shadow_y'] ?? 4 }}" min="-40" max="40" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Spread</label>
                    <input type="number" name="settings[_image_style][shadow_spread]" value="{{ $ist['shadow_spread'] ?? 0 }}" min="-20" max="40" class="{{ $inputClass }}">
                </div>
            </div>
        </div>

        @if($phShowDecor)
        <div class="pt-3" style="border-top: 1px solid var(--border-subtle);"
             x-data="{ phAccents: @js(array_values($phAccentsSel)) }">
            <p class="text-xs font-semibold mb-2" style="color: var(--text-muted);"><i class="fas fa-wand-magic-sparkles mr-1 text-blue-400"></i>Photo Decorations</p>

            <div>
                <label class="{{ $labelClass }}">Photo Shape (when no mask is set)</label>
                <select name="style[_photo_mask]" class="{{ $selectClass }}">
                    <option value="" {{ ($phSt['_photo_mask'] ?? '') === '' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">None</option>
                    <option value="arch" {{ ($phSt['_photo_mask'] ?? '') === 'arch' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Arch</option>
                    <option value="torn" {{ ($phSt['_photo_mask'] ?? '') === 'torn' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Torn Paper</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3 mt-2">
                <div>
                    <label class="{{ $labelClass }}">Arch Outline Frame</label>
                    <select name="style[_photo_frame]" class="{{ $selectClass }}">
                        <option value="" {{ ($phSt['_photo_frame'] ?? '') === '' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">None</option>
                        <option value="concentric_arch" {{ ($phSt['_photo_frame'] ?? '') === 'concentric_arch' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Concentric Arch</option>
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Frame Strokes (2–5)</label>
                    <input type="number" name="style[_photo_frame_strokes]" value="{{ $phSt['_photo_frame_strokes'] ?? 3 }}" min="2" max="5" class="{{ $inputClass }}">
                </div>
            </div>
            <div class="mt-2">
                <label class="{{ $labelClass }}">Frame Color</label>
                <input type="color" name="style[_photo_frame_color]" value="{{ $phSt['_photo_frame_color'] ?? '#57534e' }}" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
            </div>

            <div class="mt-3">
                <label class="{{ $labelClass }}">Title Banner Text</label>
                <input type="text" name="style[_photo_banner_text]" value="{{ $phSt['_photo_banner_text'] ?? '' }}" maxlength="60" placeholder="FASHION BLOGGER" class="{{ $inputClass }}">
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Shown as a band half-overlapping the photo's bottom edge. Leave empty to hide.</p>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-2">
                <div>
                    <label class="{{ $labelClass }}">Banner Background</label>
                    <input type="color" name="style[_photo_banner_bg]" value="{{ $phSt['_photo_banner_bg'] ?? '#2a201c' }}" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Banner Text Color</label>
                    <input type="color" name="style[_photo_banner_text_color]" value="{{ $phSt['_photo_banner_text_color'] ?? '#ffffff' }}" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                </div>
            </div>

            <div class="mt-3">
                <label class="{{ $labelClass }}">Collage Accents</label>
                <div class="grid grid-cols-2 gap-1.5 mt-1">
                    @foreach($phAccentOptions as $accVal => $accLabel)
                    <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--text-muted);">
                        <input type="checkbox" value="{{ $accVal }}" x-model="phAccents" class="rounded">
                        <span>{{ $accLabel }}</span>
                    </label>
                    @endforeach
                </div>
                <input type="hidden" name="style[_photo_accents]" :value="phAccents.join(',')" value="{{ implode(',', $phAccentsSel) }}">
            </div>
            <div class="mt-2">
                <label class="{{ $labelClass }}">Accent Color</label>
                <input type="color" name="style[_photo_accent_color]" value="{{ $phSt['_photo_accent_color'] ?? '#3f4e63' }}" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
            </div>

            {{-- ── Custom sticker overlays (Task #5939) ─────────────────── --}}
            <div class="mt-4 pt-3" style="border-top: 1px solid var(--border-subtle);"
                 x-data="{
                    stickers: @js($phStickersSaved),
                    max: {{ $phStickerMax }},
                    uploading: false,
                    pickerOpen: false,
                    vaultFiles: [],
                    vaultLoading: false,
                    error: '',
                    sync() {
                        this.$nextTick(() => {
                            const el = this.$refs.stickersInput;
                            el.value = this.stickers.length ? JSON.stringify(this.stickers) : '';
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                        });
                    },
                    addFile(f) {
                        if (this.stickers.length >= this.max) { this.error = 'Sticker limit reached ({{ $phStickerMax }} max).'; return; }
                        this.error = '';
                        this.stickers.push({ file_id: f.id, url: f.url_path || f.url, pos: 'top_right', size: 64, rotate: 0, dx: 0, dy: 0 });
                        this.pickerOpen = false;
                        this.sync();
                    },
                    remove(i) { this.stickers.splice(i, 1); this.error = ''; this.sync(); },
                    async uploadSticker(ev) {
                        const file = ev.target.files && ev.target.files[0];
                        ev.target.value = '';
                        if (!file) return;
                        if (this.stickers.length >= this.max) { this.error = 'Sticker limit reached ({{ $phStickerMax }} max).'; return; }
                        this.uploading = true; this.error = '';
                        try {
                            const fd = new FormData();
                            fd.append('file', file);
                            const resp = await fetch(@js(route('user.files.upload')), {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                },
                                body: fd,
                            });
                            const data = await resp.json().catch(() => ({}));
                            if (!resp.ok || !data.success || !data.file) {
                                this.error = data.error || data.message || 'Upload failed.';
                            } else if (data.file.type !== 'image') {
                                this.error = 'Stickers must be image files (PNG, WebP or SVG with transparency work best).';
                            } else {
                                this.addFile(data.file);
                            }
                        } catch (e) {
                            this.error = 'Upload failed.';
                        }
                        this.uploading = false;
                    },
                    async openPicker() {
                        this.pickerOpen = !this.pickerOpen;
                        if (!this.pickerOpen || this.vaultFiles.length) return;
                        this.vaultLoading = true;
                        try {
                            const resp = await fetch(@js(route('user.files.index')) + '?type=image', {
                                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            });
                            const data = await resp.json().catch(() => ({}));
                            this.vaultFiles = (data.files || []).filter(f => f.type === 'image');
                        } catch (e) { this.vaultFiles = []; }
                        this.vaultLoading = false;
                    },
                 }">
                <p class="text-xs font-semibold mb-1" style="color: var(--text-muted);"><i class="fas fa-note-sticky mr-1 text-blue-400"></i>Custom Stickers</p>
                <p class="text-[10px] mb-2" style="color: var(--text-dimmed);">Layer up to {{ $phStickerMax }} of your own sticker images (PNG/WebP/SVG with transparency) over the photo.</p>

                <input type="hidden" name="style[_photo_stickers]" x-ref="stickersInput"
                       value="{{ $phStickersSaved ? json_encode($phStickersSaved) : '' }}">

                <template x-for="(stk, i) in stickers" :key="i">
                    <div class="rounded-lg p-2 mb-2" style="border: 1px solid var(--border-subtle); background: var(--bg-glass-input);">
                        <div class="flex items-center gap-2 mb-2">
                            <img :src="stk.url" alt="" class="w-9 h-9 rounded object-contain" style="background: rgba(127,127,127,0.15);">
                            <select x-model="stk.pos" @change="sync()" class="{{ $selectClass }} flex-1">
                                @foreach($phStickerPositions as $pVal => $pLabel)
                                <option value="{{ $pVal }}" style="background: var(--bg-body); color: var(--text-primary);">{{ $pLabel }}</option>
                                @endforeach
                            </select>
                            <button type="button" @click="remove(i)" class="text-red-400 hover:text-red-300 px-1.5" title="Remove sticker"><i class="fas fa-trash-can text-xs"></i></button>
                        </div>
                        <div class="grid grid-cols-4 gap-1.5">
                            <div>
                                <label class="text-[10px] block" style="color: var(--text-dimmed);">Size</label>
                                <input type="number" min="24" max="160" x-model.number="stk.size" @input="sync()" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="text-[10px] block" style="color: var(--text-dimmed);">Rotate°</label>
                                <input type="number" min="-180" max="180" x-model.number="stk.rotate" @input="sync()" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="text-[10px] block" style="color: var(--text-dimmed);">Offset X</label>
                                <input type="number" min="-80" max="80" x-model.number="stk.dx" @input="sync()" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="text-[10px] block" style="color: var(--text-dimmed);">Offset Y</label>
                                <input type="number" min="-80" max="80" x-model.number="stk.dy" @input="sync()" class="{{ $inputClass }}">
                            </div>
                        </div>
                    </div>
                </template>

                <div class="flex items-center gap-2" x-show="stickers.length < max">
                    <label class="flex-1 text-center text-xs py-2 rounded-lg cursor-pointer" style="border: 1px dashed var(--border-glass); color: var(--text-muted);">
                        <span x-show="!uploading"><i class="fas fa-arrow-up-from-bracket mr-1"></i>Upload sticker</span>
                        <span x-show="uploading" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i>Uploading…</span>
                        <input type="file" accept="image/png,image/webp,image/svg+xml,image/gif,image/jpeg" class="hidden" @change="uploadSticker($event)" :disabled="uploading">
                    </label>
                    <button type="button" @click="openPicker()" class="flex-1 text-xs py-2 rounded-lg" style="border: 1px dashed var(--border-glass); color: var(--text-muted);">
                        <i class="fas fa-folder-open mr-1"></i>Pick from vault
                    </button>
                </div>
                <p class="text-[10px] mt-1 text-red-400" x-show="error" x-text="error" x-cloak></p>

                <div x-show="pickerOpen" x-cloak class="mt-2 rounded-lg p-2 max-h-44 overflow-y-auto" style="border: 1px solid var(--border-subtle); background: var(--bg-glass-input);">
                    <p class="text-[10px] mb-1" style="color: var(--text-dimmed);" x-show="vaultLoading">Loading your images…</p>
                    <p class="text-[10px] mb-1" style="color: var(--text-dimmed);" x-show="!vaultLoading && !vaultFiles.length">No images in your vault yet — upload one above.</p>
                    <div class="grid grid-cols-4 gap-1.5">
                        <template x-for="vf in vaultFiles" :key="vf.id">
                            <button type="button" @click="addFile(vf)" class="rounded overflow-hidden aspect-square" style="border: 1px solid var(--border-subtle);" :title="vf.original_name || vf.filename">
                                <img :src="vf.url_path || vf.url" alt="" class="w-full h-full object-contain" style="background: rgba(127,127,127,0.15);">
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
