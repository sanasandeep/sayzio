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
    // Text overlays on the photo (Task #5954) — same anchor + dx/dy drag
    // model as image stickers, but the payload is caption text + font.
    $phTextsSaved = is_array($phSt['_photo_text_stickers'] ?? null) ? array_values($phSt['_photo_text_stickers']) : [];
    $phTextMax = \App\Modules\User\Models\BiolinkBlock::PHOTO_TEXT_STICKER_MAX;
    $phTextFonts = array_values(array_map(
        fn ($e) => $e['family'],
        array_filter(\App\Modules\User\Support\FontCatalog::all(), fn ($e) => in_array($e['category'], ['display', 'handwriting'], true))
    ));
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
                    stageW: 0,
                    stageH: 0,
                    drag: null,
                    resize: null,
                    pinch: null,
                    pts: {},
                    init() {
                        /* Keep the drag stage dimensions reactive so anchor
                           math re-runs when the drawer resizes or the block
                           image finishes loading. */
                        this.$nextTick(() => {
                            const stage = this.$refs.dragStage;
                            if (!stage || typeof ResizeObserver === 'undefined') return;
                            const ro = new ResizeObserver(() => {
                                this.stageW = stage.clientWidth;
                                this.stageH = stage.clientHeight;
                            });
                            ro.observe(stage);
                            this.stageW = stage.clientWidth;
                            this.stageH = stage.clientHeight;
                        });
                    },
                    /* Mirrors the public renderer's anchor CSS: top-left px
                       coords of a sticker of size S at each preset, before
                       dx/dy are applied. */
                    anchorBase(pos, S) {
                        const W = this.stageW, H = this.stageH;
                        switch (pos) {
                            case 'top_left':     return { x: -10, y: -10 };
                            case 'bottom_left':  return { x: -10, y: H - S + 10 };
                            case 'bottom_right': return { x: W - S + 10, y: H - S + 10 };
                            case 'center_left':  return { x: -12, y: H / 2 - S / 2 };
                            case 'center_right': return { x: W - S + 12, y: H / 2 - S / 2 };
                            default:             return { x: W - S + 10, y: -10 }; /* top_right */
                        }
                    },
                    clampSize(v) { return Math.max(24, Math.min(160, parseInt(v, 10) || 64)); },
                    previewStyle(stk) {
                        const S = this.clampSize(stk.size);
                        const b = this.anchorBase(stk.pos, S);
                        const dx = parseInt(stk.dx, 10) || 0, dy = parseInt(stk.dy, 10) || 0;
                        return 'left:' + (b.x + dx) + 'px;top:' + (b.y + dy) + 'px;width:' + S + 'px;height:' + S + 'px;';
                    },
                    active(i) {
                        return (this.drag && this.drag.i === i) || (this.resize && this.resize.i === i) || (this.pinch && this.pinch.i === i);
                    },
                    pinchDist() {
                        const ids = Object.keys(this.pts);
                        if (ids.length < 2) return 0;
                        const a = this.pts[ids[0]], b = this.pts[ids[1]];
                        return Math.hypot(a.x - b.x, a.y - b.y);
                    },
                    startDrag(i, ev) {
                        const stage = this.$refs.dragStage;
                        if (!stage) return;
                        this.pts[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
                        try { stage.setPointerCapture(ev.pointerId); } catch (e) {}
                        /* Second finger on the same sticker → pinch-to-resize
                           instead of a second drag (touch parity for the
                           corner handle). */
                        if (this.drag && this.drag.i === i && Object.keys(this.pts).length >= 2) {
                            const stk = this.stickers[i];
                            this.pinch = { i: i, startDist: Math.max(1, this.pinchDist()), startSize: this.clampSize(stk.size) };
                            this.drag = null;
                            return;
                        }
                        const rect = stage.getBoundingClientRect();
                        const stk = this.stickers[i];
                        const S = this.clampSize(stk.size);
                        const b = this.anchorBase(stk.pos, S);
                        this.drag = {
                            i: i,
                            offX: (ev.clientX - rect.left) - (b.x + (parseInt(stk.dx, 10) || 0)),
                            offY: (ev.clientY - rect.top) - (b.y + (parseInt(stk.dy, 10) || 0)),
                        };
                    },
                    startResize(i, ev) {
                        const stage = this.$refs.dragStage;
                        if (!stage) return;
                        const stk = this.stickers[i];
                        this.drag = null;
                        this.pinch = null;
                        this.resize = { i: i, startX: ev.clientX, startY: ev.clientY, startSize: this.clampSize(stk.size) };
                        try { stage.setPointerCapture(ev.pointerId); } catch (e) {}
                    },
                    onDrag(ev) {
                        if (this.pts[ev.pointerId]) this.pts[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
                        if (this.pinch) {
                            const d = this.pinchDist();
                            if (d > 0) {
                                const stk = this.stickers[this.pinch.i];
                                stk.size = this.clampSize(Math.round(this.pinch.startSize * (d / this.pinch.startDist)));
                            }
                            return;
                        }
                        if (this.resize) {
                            const stk = this.stickers[this.resize.i];
                            /* Bottom-right handle: growing toward the corner
                               (down/right) enlarges; use the dominant axis so
                               diagonal drags feel 1:1. */
                            const delta = Math.max(ev.clientX - this.resize.startX, ev.clientY - this.resize.startY);
                            stk.size = this.clampSize(this.resize.startSize + Math.round(delta));
                            return;
                        }
                        if (!this.drag) return;
                        const stage = this.$refs.dragStage;
                        const rect = stage.getBoundingClientRect();
                        const stk = this.stickers[this.drag.i];
                        const S = this.clampSize(stk.size);
                        const left = (ev.clientX - rect.left) - this.drag.offX;
                        const top = (ev.clientY - rect.top) - this.drag.offY;
                        /* Nearest anchor preset wins; dx/dy is the clamped
                           remainder relative to that anchor (server clamps
                           identically, so what you see is what persists). */
                        let best = null;
                        for (const pos of ['top_left', 'top_right', 'bottom_left', 'bottom_right', 'center_left', 'center_right']) {
                            const b = this.anchorBase(pos, S);
                            const dx = left - b.x, dy = top - b.y;
                            const d = dx * dx + dy * dy;
                            if (!best || d < best.d) best = { pos: pos, dx: dx, dy: dy, d: d };
                        }
                        stk.pos = best.pos;
                        stk.dx = Math.max(-80, Math.min(80, Math.round(best.dx)));
                        stk.dy = Math.max(-80, Math.min(80, Math.round(best.dy)));
                    },
                    endDrag(ev) {
                        delete this.pts[ev.pointerId];
                        try { this.$refs.dragStage.releasePointerCapture(ev.pointerId); } catch (e) {}
                        if (this.pinch) {
                            if (Object.keys(this.pts).length < 2) { this.pinch = null; this.sync(); }
                            return;
                        }
                        if (this.resize) { this.resize = null; this.sync(); return; }
                        if (!this.drag) return;
                        this.drag = null;
                        this.sync();
                    },
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

                {{-- Drag-to-place stage (Task #5945): drag a sticker on the
                     photo; the nearest anchor preset + clamped dx/dy are
                     computed automatically and written to the hidden JSON. --}}
                <div class="mb-3" x-show="stickers.length" x-cloak style="padding:12px;">
                    <div x-ref="dragStage" class="relative select-none rounded-lg"
                         style="touch-action:none; background: rgba(127,127,127,0.12);"
                         @pointermove="onDrag($event)" @pointerup="endDrag($event)" @pointercancel="endDrag($event)">
                        @if(!empty($s['url']))
                            <img src="{{ $s['url'] }}" alt="" class="w-full block rounded-lg pointer-events-none" draggable="false">
                        @else
                            <div class="w-full rounded-lg" style="aspect-ratio: 4 / 3;"></div>
                        @endif
                        <template x-for="(stk, i) in stickers" :key="'drag' + i">
                            <div class="absolute z-10 group"
                                 :class="drag && drag.i === i ? 'cursor-grabbing' : 'cursor-grab'"
                                 :style="previewStyle(stk)"
                                 @pointerdown.prevent="startDrag(i, $event)">
                                <img :src="stk.url" alt="" draggable="false"
                                     class="w-full h-full rounded pointer-events-none"
                                     :class="active(i) ? 'ring-2 ring-blue-400' : ''"
                                     :style="'object-fit:contain;transform:rotate(' + (parseInt(stk.rotate, 10) || 0) + 'deg);'">
                                {{-- Corner resize handle (Task #5949): drag to
                                     resize; shown on hover or while active. --}}
                                <span class="absolute z-20 rounded-full opacity-0 group-hover:opacity-100"
                                      style="right:-6px; bottom:-6px; width:14px; height:14px; background:#3b82f6; border:2px solid #fff; cursor:nwse-resize; touch-action:none; box-shadow:0 1px 3px rgba(0,0,0,0.4);"
                                      :style="active(i) ? { opacity: 1 } : {}"
                                      title="Drag to resize"
                                      @pointerdown.prevent.stop="startResize(i, $event)"></span>
                            </div>
                        </template>
                    </div>
                    <p class="text-[10px] mt-1" style="color: var(--text-dimmed);"><i class="fas fa-hand-pointer mr-1"></i>Drag a sticker to place it: position and offsets update automatically.</p>
                </div>

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
                    <p class="text-[10px] mb-1" style="color: var(--text-dimmed);" x-show="!vaultLoading && !vaultFiles.length">No images in your vault yet: upload one above.</p>
                    <div class="grid grid-cols-4 gap-1.5">
                        <template x-for="vf in vaultFiles" :key="vf.id">
                            <button type="button" @click="addFile(vf)" class="rounded overflow-hidden aspect-square" style="border: 1px solid var(--border-subtle);" :title="vf.original_name || vf.filename">
                                <img :src="vf.url_path || vf.url" alt="" class="w-full h-full object-contain" style="background: rgba(127,127,127,0.15);">
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ── Text overlays on the photo (Task #5954) ──────────────── --}}
            <div class="mt-4 pt-3" style="border-top: 1px solid var(--border-subtle);"
                 x-data="{
                    texts: @js($phTextsSaved),
                    max: {{ $phTextMax }},
                    error: '',
                    stageW: 0,
                    stageH: 0,
                    drag: null,
                    init() {
                        this.$nextTick(() => {
                            const stage = this.$refs.textStage;
                            if (!stage || typeof ResizeObserver === 'undefined') return;
                            const ro = new ResizeObserver(() => {
                                this.stageW = stage.clientWidth;
                                this.stageH = stage.clientHeight;
                            });
                            ro.observe(stage);
                            this.stageW = stage.clientWidth;
                            this.stageH = stage.clientHeight;
                        });
                    },
                    /* Top-left px coords for a text box of size w×h at each
                       anchor preset — mirrors the public renderer's CSS. */
                    anchorTextBase(pos, w, h) {
                        const W = this.stageW, H = this.stageH;
                        switch (pos) {
                            case 'top_left':     return { x: -10, y: -10 };
                            case 'bottom_left':  return { x: -10, y: H - h + 10 };
                            case 'bottom_right': return { x: W - w + 10, y: H - h + 10 };
                            case 'center_left':  return { x: -12, y: H / 2 - h / 2 };
                            case 'center_right': return { x: W - w + 12, y: H / 2 - h / 2 };
                            default:             return { x: W - w + 10, y: -10 }; /* top_right */
                        }
                    },
                    /* Static preview uses the exact renderer CSS (anchor +
                       transforms), so no element measurement is needed. */
                    previewTextStyle(t) {
                        const anchors = {
                            top_left: 'left:-10px;top:-10px;', top_right: 'right:-10px;top:-10px;',
                            bottom_left: 'left:-10px;bottom:-10px;', bottom_right: 'right:-10px;bottom:-10px;',
                            center_left: 'left:-12px;top:50%;', center_right: 'right:-12px;top:50%;',
                        };
                        const pos = anchors[t.pos] ? t.pos : 'top_right';
                        const size = Math.max(10, Math.min(64, parseInt(t.size, 10) || 20));
                        const dx = Math.max(-80, Math.min(80, parseInt(t.dx, 10) || 0));
                        const dy = Math.max(-80, Math.min(80, parseInt(t.dy, 10) || 0));
                        const rot = Math.max(-180, Math.min(180, parseInt(t.rotate, 10) || 0));
                        let tf = 'translate(' + dx + 'px,' + dy + 'px)';
                        if (pos === 'center_left' || pos === 'center_right') tf = 'translateY(-50%) ' + tf;
                        if (rot !== 0) tf += ' rotate(' + rot + 'deg)';
                        let fam = String(t.font || '').replace(/[^a-zA-Z0-9 :_\-]/g, '');
                        if (fam.indexOf('custom:') === 0) fam = fam.slice(7);
                        const color = /^#[0-9a-fA-F]{3,8}$/.test(String(t.color || '')) ? t.color : '#ffffff';
                        return anchors[pos]
                            + (fam ? &quot;font-family:'&quot; + fam + &quot;';&quot; : '')
                            + 'color:' + color + ';font-size:' + size + 'px;line-height:1.15;white-space:nowrap;'
                            + 'text-shadow:0 1px 6px rgba(0,0,0,0.35);transform:' + tf + ';';
                    },
                    startTextDrag(i, ev) {
                        const stage = this.$refs.textStage;
                        if (!stage) return;
                        const el = ev.target.closest('[data-text-drag]');
                        if (!el) return;
                        const r = el.getBoundingClientRect();
                        this.drag = {
                            i: i, w: r.width, h: r.height,
                            offX: ev.clientX - r.left,
                            offY: ev.clientY - r.top,
                        };
                        try { stage.setPointerCapture(ev.pointerId); } catch (e) {}
                    },
                    onTextDrag(ev) {
                        if (!this.drag) return;
                        const stage = this.$refs.textStage;
                        const rect = stage.getBoundingClientRect();
                        const t = this.texts[this.drag.i];
                        const left = (ev.clientX - rect.left) - this.drag.offX;
                        const top = (ev.clientY - rect.top) - this.drag.offY;
                        let best = null;
                        for (const pos of ['top_left', 'top_right', 'bottom_left', 'bottom_right', 'center_left', 'center_right']) {
                            const b = this.anchorTextBase(pos, this.drag.w, this.drag.h);
                            const dx = left - b.x, dy = top - b.y;
                            const d = dx * dx + dy * dy;
                            if (!best || d < best.d) best = { pos: pos, dx: dx, dy: dy, d: d };
                        }
                        t.pos = best.pos;
                        t.dx = Math.max(-80, Math.min(80, Math.round(best.dx)));
                        t.dy = Math.max(-80, Math.min(80, Math.round(best.dy)));
                    },
                    endTextDrag(ev) {
                        if (!this.drag) return;
                        this.drag = null;
                        try { this.$refs.textStage.releasePointerCapture(ev.pointerId); } catch (e) {}
                        this.syncTexts();
                    },
                    syncTexts() {
                        this.$nextTick(() => {
                            const el = this.$refs.textsInput;
                            el.value = this.texts.length ? JSON.stringify(this.texts) : '';
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                        });
                    },
                    addText() {
                        if (this.texts.length >= this.max) { this.error = 'Text overlay limit reached ({{ $phTextMax }} max).'; return; }
                        this.error = '';
                        this.texts.push({ text: 'Your text', font: '', color: '#ffffff', pos: 'top_right', size: 20, rotate: -6, dx: 0, dy: 0 });
                        this.syncTexts();
                    },
                    removeText(i) { this.texts.splice(i, 1); this.error = ''; this.syncTexts(); },
                 }">
                <p class="text-xs font-semibold mb-1" style="color: var(--text-muted);"><i class="fas fa-font mr-1 text-amber-400"></i>Text on Photo</p>
                <p class="text-[10px] mb-2" style="color: var(--text-dimmed);">Layer up to {{ $phTextMax }} short captions over the photo: drag them anywhere, tilt them, pick a poster font.</p>

                <input type="hidden" name="style[_photo_text_stickers]" x-ref="textsInput"
                       value="{{ $phTextsSaved ? json_encode($phTextsSaved) : '' }}">

                <div class="mb-3" x-show="texts.length" x-cloak style="padding:12px;">
                    <div x-ref="textStage" class="relative select-none rounded-lg"
                         style="touch-action:none; background: rgba(127,127,127,0.12);"
                         @pointermove="onTextDrag($event)" @pointerup="endTextDrag($event)" @pointercancel="endTextDrag($event)">
                        @if(!empty($s['url']))
                            <img src="{{ $s['url'] }}" alt="" class="w-full block rounded-lg pointer-events-none" draggable="false">
                        @else
                            <div class="w-full rounded-lg" style="aspect-ratio: 4 / 3;"></div>
                        @endif
                        <template x-for="(t, i) in texts" :key="'tdrag' + i">
                            <span data-text-drag class="absolute z-10 font-bold"
                                  :class="drag && drag.i === i ? 'cursor-grabbing ring-2 ring-amber-400' : 'cursor-grab'"
                                  :style="previewTextStyle(t)"
                                  x-text="(t.text || '').trim() || 'Your text'"
                                  @pointerdown.prevent="startTextDrag(i, $event)"></span>
                        </template>
                    </div>
                    <p class="text-[10px] mt-1" style="color: var(--text-dimmed);"><i class="fas fa-hand-pointer mr-1"></i>Drag a caption to place it: position and offsets update automatically.</p>
                </div>

                <template x-for="(t, i) in texts" :key="'t' + i">
                    <div class="rounded-lg p-2 mb-2" style="border: 1px solid var(--border-subtle); background: var(--bg-glass-input);">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" maxlength="80" x-model="t.text" @input="syncTexts()" placeholder="Caption text" class="{{ $inputClass }} flex-1">
                            <input type="color" :value="/^#[0-9a-fA-F]{6}$/.test(t.color || '') ? t.color : '#ffffff'"
                                   @input="t.color = $event.target.value; syncTexts()"
                                   class="w-9 h-9 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                            <button type="button" @click="removeText(i)" class="text-red-400 hover:text-red-300 px-1.5" title="Remove text"><i class="fas fa-trash-can text-xs"></i></button>
                        </div>
                        <div class="grid grid-cols-2 gap-1.5 mb-1.5">
                            <div>
                                <label class="text-[10px] block" style="color: var(--text-dimmed);">Font</label>
                                <select x-model="t.font" @change="syncTexts()" class="{{ $selectClass }}">
                                    <option value="" style="background: var(--bg-body); color: var(--text-primary);">Default</option>
                                    @foreach($phTextFonts as $ftf)
                                    <option value="{{ $ftf }}" style="background: var(--bg-body); color: var(--text-primary);">{{ $ftf }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] block" style="color: var(--text-dimmed);">Anchor</label>
                                <select x-model="t.pos" @change="syncTexts()" class="{{ $selectClass }}">
                                    @foreach($phStickerPositions as $pVal => $pLabel)
                                    <option value="{{ $pVal }}" style="background: var(--bg-body); color: var(--text-primary);">{{ $pLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-4 gap-1.5">
                            <div>
                                <label class="text-[10px] block" style="color: var(--text-dimmed);">Size</label>
                                <input type="number" min="10" max="64" x-model.number="t.size" @input="syncTexts()" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="text-[10px] block" style="color: var(--text-dimmed);">Rotate°</label>
                                <input type="number" min="-180" max="180" x-model.number="t.rotate" @input="syncTexts()" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="text-[10px] block" style="color: var(--text-dimmed);">Offset X</label>
                                <input type="number" min="-80" max="80" x-model.number="t.dx" @input="syncTexts()" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="text-[10px] block" style="color: var(--text-dimmed);">Offset Y</label>
                                <input type="number" min="-80" max="80" x-model.number="t.dy" @input="syncTexts()" class="{{ $inputClass }}">
                            </div>
                        </div>
                    </div>
                </template>

                <button type="button" @click="addText()" x-show="texts.length < max" class="w-full text-center text-xs py-2 rounded-lg" style="border: 1px dashed var(--border-glass); color: var(--text-muted);">
                    <i class="fas fa-plus mr-1"></i>Add text overlay
                </button>
                <p class="text-[10px] mt-1 text-red-400" x-show="error" x-text="error" x-cloak></p>
            </div>
        </div>
        @endif

    </div>
</div>
