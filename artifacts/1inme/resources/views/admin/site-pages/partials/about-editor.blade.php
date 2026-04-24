@php
    $aboutDefaults = \App\Modules\Common\Support\SitePagesContent::aboutExtraDefault();
    $aboutExtra = old('extra', is_array($page->extra) && !empty($page->extra)
        ? \App\Modules\Common\Support\SitePagesContent::normalizeAboutExtra($page->extra)
        : $aboutDefaults);
    $aboutHero = array_replace_recursive($aboutDefaults['hero'], (array)($aboutExtra['hero'] ?? []));
    // Repeatable hero stats: prefer admin-saved values, otherwise use defaults so the editor renders three rows.
    $aboutHeroStats = array_values((array)($aboutExtra['hero']['stats'] ?? $aboutDefaults['hero']['stats']));
    $aboutValues = array_replace($aboutDefaults['values'], array_intersect_key((array)($aboutExtra['values'] ?? []), ['heading' => 1, 'subheading' => 1]));
    $aboutValueCards = array_values((array)($aboutExtra['values']['cards'] ?? $aboutDefaults['values']['cards']));
    $aboutStoryImages = array_replace_recursive($aboutDefaults['story_images'], (array)($aboutExtra['story_images'] ?? []));
    $aboutSectionTitles = array_replace($aboutDefaults['section_titles'], (array)($aboutExtra['section_titles'] ?? []));
    $aboutCta = array_replace($aboutDefaults['cta'], (array)($aboutExtra['cta'] ?? []));
    $founder = $aboutExtra['founder'] ?? $aboutDefaults['founder'];
    $coFounders = array_values((array)($aboutExtra['co_founders'] ?? []));
    $teamRows = array_values((array)($aboutExtra['team'] ?? []));
    $milestoneRows = array_values((array)($aboutExtra['milestones'] ?? []));

    // Section order control: build the editor list in the saved order
    // (sanitised to drop unknowns/dupes and pad missing slugs at the end)
    // so admins drag the same seven cards the public page actually
    // renders. Labels are kept in PHP so the strings are translatable
    // and reusable.
    $aboutSectionOrderSlugs = \App\Modules\Common\Support\SitePagesContent::aboutLowerSectionSlugs();
    $aboutSectionLabels = [
        'story'       => ['label' => 'Story', 'desc' => 'Heading + body cards above the team band.'],
        'team_band'   => ['label' => 'Team photo band', 'desc' => 'Wide team image strip.'],
        'founder'     => ['label' => 'Founder', 'desc' => 'Featured founder card with photo and bio.'],
        'co_founders' => ['label' => 'Co-founders', 'desc' => 'Three-up grid of co-founder cards.'],
        'team'        => ['label' => 'Team grid', 'desc' => 'Smaller member cards under the co-founders.'],
        'milestones'  => ['label' => 'Milestones', 'desc' => 'Vertical timeline of dated milestones.'],
        'cta'         => ['label' => 'Bottom call to action', 'desc' => 'The "Want to build with us?" panel.'],
    ];
    $savedSectionOrder = (array)($aboutExtra['section_order'] ?? []);
    $aboutSectionOrder = [];
    $seenSectionSlugs = [];
    foreach ($savedSectionOrder as $slug) {
        if (!is_string($slug)) continue;
        $slug = trim($slug);
        if (!in_array($slug, $aboutSectionOrderSlugs, true)) continue;
        if (in_array($slug, $seenSectionSlugs, true)) continue;
        $aboutSectionOrder[] = $slug;
        $seenSectionSlugs[] = $slug;
    }
    foreach ($aboutSectionOrderSlugs as $slug) {
        if (!in_array($slug, $seenSectionSlugs, true)) {
            $aboutSectionOrder[] = $slug;
        }
    }
    // Build the per-section visibility map alongside the order. Saved
    // values win; anything missing defaults to visible (true) so a row
    // never silently disappears just because the slug was added later.
    $savedSectionVisibility = (array)($aboutExtra['section_visibility'] ?? []);
    $aboutSectionVisibility = [];
    foreach ($aboutSectionOrderSlugs as $slug) {
        if (array_key_exists($slug, $savedSectionVisibility)) {
            $aboutSectionVisibility[$slug] = filter_var($savedSectionVisibility[$slug], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        } else {
            $aboutSectionVisibility[$slug] = true;
        }
    }
    // Hand the editor a list of {slug, label, desc, visible} so Alpine
    // can render each card without re-doing the lookup in the template.
    $aboutSectionOrderItems = array_map(function ($slug) use ($aboutSectionLabels, $aboutSectionVisibility) {
        return [
            'slug'    => $slug,
            'label'   => $aboutSectionLabels[$slug]['label'] ?? $slug,
            'desc'    => $aboutSectionLabels[$slug]['desc'] ?? '',
            'visible' => (bool) ($aboutSectionVisibility[$slug] ?? true),
        ];
    }, $aboutSectionOrder);
@endphp

{{--
    Reusable Alpine helper that powers the "upload or paste URL" photo
    control rendered next to every photo field below. It POSTs the
    chosen file to the existing admin asset uploader and writes the
    returned public URL back into the bound model so the URL text input
    and the live preview stay in sync.
--}}
@once
    <script>
        window.aboutPhotoUploader = function (config) {
            const aspect = (config && config.aspect) || 1;
            const outputSize = (config && config.outputSize) || 800;
            const isCircle = (config && config.isCircle !== undefined) ? !!config.isCircle : true;
            const viewport = 320;
            return {
                isCircle: isCircle,
                uploading: false,
                progress: 0,
                error: '',
                cropping: false,
                previewUrl: '',
                pendingFile: null,
                natW: 0,
                natH: 0,
                baseScale: 1,
                zoom: 1,
                tx: 0,
                ty: 0,
                dragging: false,
                _dragStartX: 0,
                _dragStartY: 0,
                _dragStartTx: 0,
                _dragStartTy: 0,
                get model() { return config.get(); },
                set model(v) { config.set(v); },
                get vpW() { return viewport; },
                get vpH() { return Math.round(viewport / aspect); },
                get totalScale() { return this.baseScale * this.zoom; },
                get imgStyle() {
                    return 'transform: translate(calc(-50% + ' + this.tx + 'px), calc(-50% + ' + this.ty + 'px)) scale(' + this.totalScale + '); transform-origin: center center;';
                },
                pickFile() { this.$refs.fileInput.click(); },
                _loadedImg: null,
                _resetCropState() {
                    this.zoom = 1;
                    this.tx = 0;
                    this.ty = 0;
                    this.natW = 0;
                    this.natH = 0;
                    this.baseScale = 1;
                    this._loadedImg = null;
                },
                _releasePreview() {
                    if (this.previewUrl && this.previewUrl.indexOf('blob:') === 0) {
                        try { URL.revokeObjectURL(this.previewUrl); } catch (_) {}
                    }
                    this.previewUrl = '';
                },
                handleFile(e) {
                    const file = (e.target.files || [])[0];
                    e.target.value = '';
                    if (!file) return;
                    if (!/^image\//.test(file.type)) {
                        this.error = 'Please choose an image file.';
                        return;
                    }
                    if (file.size > 10 * 1024 * 1024) {
                        this.error = 'Image must be 10 MB or smaller.';
                        return;
                    }
                    this.error = '';
                    this.pendingFile = file;
                    this._releasePreview();
                    this.previewUrl = URL.createObjectURL(file);
                    this._resetCropState();
                    this.cropping = true;
                    const img = new Image();
                    img.onload = () => {
                        this.natW = img.naturalWidth || 1;
                        this.natH = img.naturalHeight || 1;
                        this.baseScale = Math.max(this.vpW / this.natW, this.vpH / this.natH);
                        this._loadedImg = img;
                        this.clampPan();
                    };
                    img.src = this.previewUrl;
                },
                recropFromUrl() {
                    const url = (this.model || '').trim();
                    if (!url) { this.error = 'Add a photo URL first.'; return; }
                    this.error = '';
                    this.pendingFile = null;
                    this._releasePreview();
                    this.previewUrl = url;
                    this._resetCropState();
                    this.cropping = true;
                    const img = new Image();
                    img.crossOrigin = 'anonymous';
                    img.onload = () => {
                        this.natW = img.naturalWidth || 1;
                        this.natH = img.naturalHeight || 1;
                        this.baseScale = Math.max(this.vpW / this.natW, this.vpH / this.natH);
                        this._loadedImg = img;
                        this.clampPan();
                    };
                    img.onerror = () => {
                        this.cropping = false;
                        this._releasePreview();
                        this.error = 'Could not load this image for re-cropping. The host may block cross-origin access — re-upload the file instead.';
                    };
                    img.src = url;
                },
                clampPan() {
                    if (!this.natW || !this.natH) return;
                    const halfW = (this.natW * this.totalScale) / 2;
                    const halfH = (this.natH * this.totalScale) / 2;
                    const maxTx = Math.max(0, halfW - this.vpW / 2);
                    const maxTy = Math.max(0, halfH - this.vpH / 2);
                    if (this.tx > maxTx) this.tx = maxTx;
                    if (this.tx < -maxTx) this.tx = -maxTx;
                    if (this.ty > maxTy) this.ty = maxTy;
                    if (this.ty < -maxTy) this.ty = -maxTy;
                },
                onZoom(v) { this.zoom = parseFloat(v) || 1; this.clampPan(); },
                startDrag(e) {
                    if (!this.cropping) return;
                    const p = e.touches ? e.touches[0] : e;
                    this.dragging = true;
                    this._dragStartX = p.clientX;
                    this._dragStartY = p.clientY;
                    this._dragStartTx = this.tx;
                    this._dragStartTy = this.ty;
                    if (e.preventDefault) e.preventDefault();
                },
                moveDrag(e) {
                    if (!this.dragging) return;
                    const p = e.touches ? e.touches[0] : e;
                    this.tx = this._dragStartTx + (p.clientX - this._dragStartX);
                    this.ty = this._dragStartTy + (p.clientY - this._dragStartY);
                    this.clampPan();
                },
                endDrag() { this.dragging = false; },
                cancelCrop() {
                    this._releasePreview();
                    this.pendingFile = null;
                    this.cropping = false;
                },
                async confirmCrop() {
                    if (!this.natW || !this.natH) return;
                    if (!this.pendingFile && !this.previewUrl) return;
                    this.error = '';
                    try {
                        const s = this.totalScale;
                        const sw = this.vpW / s;
                        const sh = this.vpH / s;
                        const cx = this.natW / 2 - this.tx / s;
                        const cy = this.natH / 2 - this.ty / s;
                        const sx = cx - sw / 2;
                        const sy = cy - sh / 2;
                        const outW = outputSize;
                        const outH = Math.round(outputSize / aspect);
                        const canvas = document.createElement('canvas');
                        canvas.width = outW;
                        canvas.height = outH;
                        const ctx = canvas.getContext('2d');
                        let img = this._loadedImg;
                        if (!img) {
                            img = new Image();
                            const isRemote = this.previewUrl.indexOf('blob:') !== 0;
                            if (isRemote) img.crossOrigin = 'anonymous';
                            img.src = this.previewUrl;
                            await new Promise((res, rej) => {
                                if (img.complete && img.naturalWidth) res();
                                else { img.onload = res; img.onerror = () => rej(new Error('Could not load image for cropping.')); }
                            });
                        }
                        ctx.drawImage(img, sx, sy, sw, sh, 0, 0, outW, outH);
                        let blob;
                        try {
                            blob = await new Promise((res, rej) => {
                                try { canvas.toBlob((b) => res(b), 'image/jpeg', 0.92); }
                                catch (e) { rej(e); }
                            });
                        } catch (e) {
                            throw new Error('This image host blocks cross-origin access, so it cannot be re-cropped here. Re-upload the file instead.');
                        }
                        if (!blob) throw new Error('Could not generate cropped image.');
                        let baseName = 'photo';
                        if (this.pendingFile) {
                            baseName = (this.pendingFile.name || 'photo').replace(/\.[^.]+$/, '');
                        } else {
                            try {
                                const path = (new URL(this.previewUrl, window.location.href)).pathname;
                                const last = path.split('/').pop() || '';
                                baseName = (last.replace(/\.[^.]+$/, '') || 'photo');
                            } catch (_) { /* keep default */ }
                        }
                        const file = new File([blob], baseName + '-cropped.jpg', { type: 'image/jpeg' });
                        const previousFile = this.pendingFile;
                        this._releasePreview();
                        this.pendingFile = null;
                        this.cropping = false;
                        try {
                            await this.uploadFile(file);
                        } catch (err) {
                            // Restore so user can retry / skip; surface error.
                            this.pendingFile = previousFile;
                            throw err;
                        }
                    } catch (err) {
                        this.error = err.message || 'Crop failed.';
                    }
                },
                async skipCrop() {
                    const file = this.pendingFile;
                    if (!file) {
                        // Re-crop from URL has no underlying file to upload as-is —
                        // skipping is equivalent to cancelling.
                        this.cancelCrop();
                        return;
                    }
                    this._releasePreview();
                    this.pendingFile = null;
                    this.cropping = false;
                    try {
                        await this.uploadFile(file);
                    } catch (_) {
                        // uploadFile already surfaces the message via this.error.
                    }
                },
                async uploadFile(file) {
                    this.uploading = true;
                    this.progress = 0;
                    try {
                        const fd = new FormData();
                        fd.append('file', file);
                        fd.append('folder', 'about-page');
                        const url = await new Promise((resolve, reject) => {
                            const xhr = new XMLHttpRequest();
                            xhr.open('POST', @json(route('admin.assets.upload')));
                            xhr.setRequestHeader('X-CSRF-TOKEN', @json(csrf_token()));
                            xhr.setRequestHeader('Accept', 'application/json');
                            xhr.upload.onprogress = (ev) => {
                                if (ev.lengthComputable) this.progress = Math.round((ev.loaded / ev.total) * 100);
                            };
                            xhr.onload = () => {
                                let data = {};
                                try { data = JSON.parse(xhr.responseText); } catch (_) {}
                                if (xhr.status >= 200 && xhr.status < 300 && data.success && data.asset) {
                                    resolve(data.asset.url || data.asset.url_path);
                                } else {
                                    reject(new Error(data.error || ('Upload failed (' + xhr.status + ')')));
                                }
                            };
                            xhr.onerror = () => reject(new Error('Network error during upload.'));
                            xhr.send(fd);
                        });
                        this.model = url;
                    } catch (err) {
                        this.error = err.message || 'Upload failed.';
                        throw err;
                    } finally {
                        this.uploading = false;
                    }
                },
                clear() { this.model = ''; this.error = ''; },
            };
        };
    </script>
    <style>[x-cloak]{display:none !important}</style>
@endonce

<div class="pt-2 border-t border-white/10 space-y-6">

    {{-- ========== HERO ========== --}}
    <div>
        <h3 class="text-sm font-semibold text-white">Hero</h3>
        <p class="text-xs text-white/50 mb-3">Top of /about — badge pill, side image, location card and the small stats trio.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-4">
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Badge label</label>
                    <input type="text" name="extra[hero][badge_label]" value="{{ $aboutHero['badge_label'] }}" maxlength="60" placeholder="About" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('extra.hero.badge_label')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Badge icon (FontAwesome class)</label>
                    <input type="text" name="extra[hero][badge_icon]" value="{{ $aboutHero['badge_icon'] }}" maxlength="60" placeholder="fa-heart" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                    @error('extra.hero.badge_icon')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div x-data="{ url: @js((string)($aboutHero['side_image'] ?? '')) }">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Hero side image <span class="normal-case tracking-normal text-white/40">(16:10)</span></label>
                <div x-data="aboutPhotoUploader({ get: () => url, set: (v) => url = v, aspect: 16/10, outputSize: 1200, isCircle: false })" class="space-y-2">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 text-center">
                            <template x-if="url">
                                <img :src="url" alt="" class="w-40 h-25 object-cover rounded-md border border-white/10 bg-white/5" style="height:100px" x-on:error="$el.style.display='none'">
                            </template>
                            <template x-if="!url">
                                <div class="w-40 rounded-md border-2 border-dashed border-white/15 bg-white/5 flex items-center justify-center text-[10px] text-white/40 text-center px-2" style="height:100px">Falls back to bundled hero.png</div>
                            </template>
                            <div class="text-[10px] text-white/40 mt-1">Live preview</div>
                        </div>
                        <div class="flex-1 space-y-2">
                            <input type="url" name="extra[hero][side_image]" x-model="url" placeholder="https://… or /storage/… (or upload below)" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            @error('extra.hero.side_image')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button" @click="pickFile()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1">
                                    <i class="fas fa-upload"></i>
                                    <span x-text="uploading ? ('Uploading… ' + progress + '%') : 'Upload image'"></span>
                                </button>
                                <button type="button" x-show="url" @click="recropFromUrl()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/20 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1"><i class="fas fa-crop"></i><span>Re-crop current photo</span></button>
                                <button type="button" x-show="url" @click="clear()" class="text-xs px-2 py-1.5 text-white/60 hover:text-white"><i class="fas fa-times mr-1"></i>Remove</button>
                            </div>
                            <p x-show="error" x-text="error" class="text-xs text-red-400"></p>
                        </div>
                    </div>
                    <input type="file" x-ref="fileInput" @change="handleFile($event)" accept="image/*" class="hidden">
                    @include('admin.site-pages.partials.about-crop-modal')
                </div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1 mt-2">Hero side image alt text</label>
                <input type="text" name="extra[hero][side_image_alt]" value="{{ $aboutHero['side_image_alt'] }}" maxlength="200" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.hero.side_image_alt')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Floating card title</label>
                    <input type="text" name="extra[hero][location_title]" value="{{ $aboutHero['location_title'] }}" maxlength="120" placeholder="Hyderabad · India" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('extra.hero.location_title')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Floating card subtitle</label>
                    <input type="text" name="extra[hero][location_subtitle]" value="{{ $aboutHero['location_subtitle'] }}" maxlength="120" placeholder="Remote-friendly" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('extra.hero.location_subtitle')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Floating card icon</label>
                    <input type="text" name="extra[hero][location_icon]" value="{{ $aboutHero['location_icon'] }}" maxlength="60" placeholder="fa-location-dot" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                    @error('extra.hero.location_icon')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div x-data="{ rows: {{ json_encode($aboutHeroStats) }}, moveUp(i){ if(i>0){ const a=this.rows; [a[i-1],a[i]]=[a[i],a[i-1]]; } }, moveDown(i){ const a=this.rows; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } } }">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <label class="text-[10px] uppercase tracking-wider text-white/40">Hero stats trio</label>
                        <p class="text-[11px] text-white/40">Numeric values animate; non-numeric values are shown as plain text.</p>
                    </div>
                    <button type="button" @click="if(rows.length<6) rows.push({value:'',suffix:'',label:'',visible:true})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 rounded-lg text-white" :disabled="rows.length>=6"><i class="fas fa-plus mr-1"></i>Add stat</button>
                </div>
                <template x-for="(s, i) in rows" :key="i">
                    <div class="bg-white/[0.04] border border-white/10 rounded-lg p-3 mb-2 space-y-2" :class="{'opacity-50': !s.visible}">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <span class="text-[10px] uppercase tracking-wider text-white/40">Stat <span x-text="i+1"></span></span>
                            <div class="flex items-center gap-2">
                                <label class="inline-flex items-center gap-1.5 text-[11px] text-white/70 cursor-pointer select-none">
                                    <input type="hidden" :name="'extra[hero][stats]['+i+'][visible]'" value="0">
                                    <input type="checkbox" :name="'extra[hero][stats]['+i+'][visible]'" value="1" x-model="s.visible" class="rounded border-white/20 bg-white/5">
                                    <span x-text="s.visible ? 'Visible' : 'Hidden'"></span>
                                </label>
                                <button type="button" @click="moveUp(i)" :disabled="i===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1"><i class="fas fa-arrow-up"></i></button>
                                <button type="button" @click="moveDown(i)" :disabled="i===rows.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1"><i class="fas fa-arrow-down"></i></button>
                                <button type="button" @click="rows.splice(i,1)" class="text-xs text-red-400 hover:text-red-300 px-1.5 py-1"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <div class="grid sm:grid-cols-3 gap-2">
                            <input type="text" :name="'extra[hero][stats]['+i+'][value]'" x-model="s.value" maxlength="40" placeholder="120000" class="w-full px-2.5 py-1.5 bg-white/5 border border-white/10 rounded text-sm text-white">
                            <input type="text" :name="'extra[hero][stats]['+i+'][suffix]'" x-model="s.suffix" maxlength="10" placeholder="+" class="w-full px-2.5 py-1.5 bg-white/5 border border-white/10 rounded text-sm text-white">
                            <input type="text" :name="'extra[hero][stats]['+i+'][label]'" x-model="s.label" maxlength="120" placeholder="Creators served" class="w-full px-2.5 py-1.5 bg-white/5 border border-white/10 rounded text-sm text-white">
                        </div>
                    </div>
                </template>
                <div x-show="rows.length===0" class="text-xs text-white/40 text-center py-3">No hero stats — the trio under the headline will be hidden.</div>
            </div>
        </div>
    </div>

    {{-- ========== VALUES SECTION ========== --}}
    <div>
        <h3 class="text-sm font-semibold text-white">Values section</h3>
        <p class="text-xs text-white/50 mb-3">"What we believe in" heading, supporting line and the value cards row.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-4">
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Heading</label>
                <input type="text" name="extra[values][heading]" value="{{ $aboutValues['heading'] }}" maxlength="200" placeholder="What we believe in" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.values.heading')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Subheading</label>
                <textarea name="extra[values][subheading]" rows="2" maxlength="500" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ $aboutValues['subheading'] }}</textarea>
                @error('extra.values.subheading')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div x-data="{ rows: {{ json_encode($aboutValueCards) }}, moveUp(i){ if(i>0){ const a=this.rows; [a[i-1],a[i]]=[a[i],a[i-1]]; } }, moveDown(i){ const a=this.rows; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } } }">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <label class="text-[10px] uppercase tracking-wider text-white/40">Value cards</label>
                        <p class="text-[11px] text-white/40">Removing all cards hides the entire row on /about.</p>
                    </div>
                    <button type="button" @click="if(rows.length<8) rows.push({icon:'fa-circle-dot',title:'',desc:''})" :disabled="rows.length>=8" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 rounded-lg text-white"><i class="fas fa-plus mr-1"></i>Add card</button>
                </div>
                <template x-for="(c, i) in rows" :key="i">
                    <div class="bg-white/[0.04] border border-white/10 rounded-lg p-3 mb-2 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] uppercase tracking-wider text-white/40">Card <span x-text="i+1"></span></span>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="moveUp(i)" :disabled="i===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1"><i class="fas fa-arrow-up"></i></button>
                                <button type="button" @click="moveDown(i)" :disabled="i===rows.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1"><i class="fas fa-arrow-down"></i></button>
                                <button type="button" @click="rows.splice(i,1)" class="text-xs text-red-400 hover:text-red-300 px-1.5 py-1"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <div class="grid sm:grid-cols-[140px_1fr] gap-2">
                            <input type="text" :name="'extra[values][cards]['+i+'][icon]'" x-model="c.icon" maxlength="60" placeholder="fa-bolt" class="w-full px-2.5 py-1.5 bg-white/5 border border-white/10 rounded text-sm text-white font-mono">
                            <input type="text" :name="'extra[values][cards]['+i+'][title]'" x-model="c.title" maxlength="200" placeholder="Title" class="w-full px-2.5 py-1.5 bg-white/5 border border-white/10 rounded text-sm text-white">
                        </div>
                        <textarea :name="'extra[values][cards]['+i+'][desc]'" x-model="c.desc" rows="2" maxlength="500" placeholder="Short description shown under the title." class="w-full px-2.5 py-1.5 bg-white/5 border border-white/10 rounded text-sm text-white"></textarea>
                    </div>
                </template>
                <div x-show="rows.length===0" class="text-xs text-white/40 text-center py-3">No value cards — the "What we believe in" cards row will be hidden.</div>
            </div>
        </div>
    </div>

    {{-- ========== STORY IMAGES ========== --}}
    <div>
        <h3 class="text-sm font-semibold text-white">Story images</h3>
        <p class="text-xs text-white/50 mb-3">The two side images next to the story sections, plus the wide team band beneath.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-4">
            @foreach([
                ['key' => 'office',    'label' => 'Office image (story side, top)',     'aspect' => '4/3',  'aspectVal' => 4/3,  'output' => 1000, 'placeholderH' => 90],
                ['key' => 'values',    'label' => 'Values image (story side, bottom)',  'aspect' => '4/3',  'aspectVal' => 4/3,  'output' => 1000, 'placeholderH' => 90],
                ['key' => 'team_band', 'label' => 'Team band image (wide banner)',      'aspect' => '16/7', 'aspectVal' => 16/7, 'output' => 1400, 'placeholderH' => 56],
            ] as $img)
                @php $cfg = $aboutStoryImages[$img['key']] ?? ['url' => '', 'alt' => '']; @endphp
                <div x-data="{ url: @js((string)($cfg['url'] ?? '')) }">
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">{{ $img['label'] }} <span class="normal-case tracking-normal text-white/40">({{ $img['aspect'] }})</span></label>
                    <div x-data="aboutPhotoUploader({ get: () => url, set: (v) => url = v, aspect: {{ $img['aspectVal'] }}, outputSize: {{ $img['output'] }}, isCircle: false })" class="space-y-2">
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 text-center">
                                <template x-if="url">
                                    <img :src="url" alt="" class="w-40 object-cover rounded-md border border-white/10 bg-white/5" style="height:{{ $img['placeholderH'] }}px" x-on:error="$el.style.display='none'">
                                </template>
                                <template x-if="!url">
                                    <div class="w-40 rounded-md border-2 border-dashed border-white/15 bg-white/5 flex items-center justify-center text-[10px] text-white/40 text-center px-2" style="height:{{ $img['placeholderH'] }}px">Falls back to bundled image</div>
                                </template>
                                <div class="text-[10px] text-white/40 mt-1">Live preview</div>
                            </div>
                            <div class="flex-1 space-y-2">
                                <input type="url" name="extra[story_images][{{ $img['key'] }}][url]" x-model="url" placeholder="https://… or /storage/…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                                @error('extra.story_images.'.$img['key'].'.url')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                                <div class="flex items-center gap-2 flex-wrap">
                                    <button type="button" @click="pickFile()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1">
                                        <i class="fas fa-upload"></i>
                                        <span x-text="uploading ? ('Uploading… ' + progress + '%') : 'Upload image'"></span>
                                    </button>
                                    <button type="button" x-show="url" @click="recropFromUrl()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/20 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1"><i class="fas fa-crop"></i><span>Re-crop current photo</span></button>
                                    <button type="button" x-show="url" @click="clear()" class="text-xs px-2 py-1.5 text-white/60 hover:text-white"><i class="fas fa-times mr-1"></i>Remove</button>
                                </div>
                                <p x-show="error" x-text="error" class="text-xs text-red-400"></p>
                                <input type="text" name="extra[story_images][{{ $img['key'] }}][alt]" value="{{ $cfg['alt'] ?? '' }}" maxlength="200" placeholder="Alt text" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                                @error('extra.story_images.'.$img['key'].'.alt')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <input type="file" x-ref="fileInput" @change="handleFile($event)" accept="image/*" class="hidden">
                        @include('admin.site-pages.partials.about-crop-modal')
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ========== SECTION TITLES ========== --}}
    <div>
        <h3 class="text-sm font-semibold text-white">Section titles</h3>
        <p class="text-xs text-white/50 mb-3">Headings (and a couple of subheadings) for the lower four sections of /about.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">"Meet the founder" heading</label>
                <input type="text" name="extra[section_titles][founder]" value="{{ $aboutSectionTitles['founder'] }}" maxlength="200" placeholder="Meet the founder" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.section_titles.founder')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">"Co-founders" heading</label>
                <input type="text" name="extra[section_titles][co_founders]" value="{{ $aboutSectionTitles['co_founders'] }}" maxlength="200" placeholder="Co-founders" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.section_titles.co_founders')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">"The team" heading</label>
                <input type="text" name="extra[section_titles][team_title]" value="{{ $aboutSectionTitles['team_title'] }}" maxlength="200" placeholder="The team" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.section_titles.team_title')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">"The team" subtitle</label>
                <input type="text" name="extra[section_titles][team_subtitle]" value="{{ $aboutSectionTitles['team_subtitle'] }}" maxlength="300" placeholder="The folks shipping 1INME every week." class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.section_titles.team_subtitle')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">"Milestones" heading</label>
                <input type="text" name="extra[section_titles][milestones_title]" value="{{ $aboutSectionTitles['milestones_title'] }}" maxlength="200" placeholder="Milestones" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.section_titles.milestones_title')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">"Milestones" subtitle</label>
                <input type="text" name="extra[section_titles][milestones_subtitle]" value="{{ $aboutSectionTitles['milestones_subtitle'] }}" maxlength="300" placeholder="A short history of how we got here." class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.section_titles.milestones_subtitle')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    {{-- ========== SECTION ORDER ========== --}}
    <div x-data="{
            items: {{ json_encode($aboutSectionOrderItems) }},
            dragIndex: null,
            overIndex: null,
            moveUp(i){ if(i>0){ const a=this.items; [a[i-1],a[i]]=[a[i],a[i-1]]; } },
            moveDown(i){ const a=this.items; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } },
            onDragStart(i, ev){
                this.dragIndex = i;
                this.overIndex = i;
                if (ev.dataTransfer) {
                    ev.dataTransfer.effectAllowed = 'move';
                    // Some browsers require setData() for drag to actually start.
                    try { ev.dataTransfer.setData('text/plain', String(i)); } catch(_) {}
                }
            },
            onDragOver(i, ev){
                if (this.dragIndex === null) return;
                if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'move';
                this.overIndex = i;
            },
            onDrop(i){
                if (this.dragIndex === null || this.dragIndex === i) {
                    this.dragIndex = null; this.overIndex = null; return;
                }
                const a = this.items;
                const moved = a.splice(this.dragIndex, 1)[0];
                a.splice(i, 0, moved);
                this.dragIndex = null;
                this.overIndex = null;
            },
            onDragEnd(){ this.dragIndex = null; this.overIndex = null; },
            resetOrder(){
                const defaults = {{ json_encode($aboutSectionOrderSlugs) }};
                const labels = {{ json_encode($aboutSectionLabels) }};
                this.items = defaults.map(function(s){
                    return { slug: s, label: (labels[s] && labels[s].label) || s, desc: (labels[s] && labels[s].desc) || '', visible: true };
                });
            },
            toggleVisible(i){ this.items[i].visible = !this.items[i].visible; }
        }">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h3 class="text-sm font-semibold text-white">Section order</h3>
                <p class="text-xs text-white/50">Drag the cards (or use the arrows) to change the order of the lower sections of /about, and use the toggle to show or hide each one without losing its content. The hero, stats, values and story-images blocks above always stay where they are.</p>
            </div>
            <button type="button" @click="resetOrder()" class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/20 rounded-lg text-white"><i class="fas fa-rotate-left mr-1"></i>Reset to default</button>
        </div>
        <ul class="space-y-2">
            <template x-for="(s, i) in items" :key="s.slug">
                <li
                    draggable="true"
                    @dragstart="onDragStart(i, $event)"
                    @dragover.prevent="onDragOver(i, $event)"
                    @drop.prevent="onDrop(i)"
                    @dragend="onDragEnd()"
                    class="bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 flex items-center gap-3 transition"
                    :class="{
                        'opacity-50': dragIndex === i,
                        'border-violet-400/60 bg-violet-500/10': overIndex === i && dragIndex !== null && dragIndex !== i,
                        'opacity-60': !s.visible && dragIndex !== i,
                    }"
                >
                    <input type="hidden" name="extra[section_order][]" :value="s.slug">
                    {{-- Always submit a value for every slug so the server map is complete:
                         a "1" when visible, "0" when hidden. --}}
                    <input type="hidden" :name="'extra[section_visibility][' + s.slug + ']'" :value="s.visible ? '1' : '0'">
                    <i class="fas fa-grip-vertical text-white/40 cursor-move" title="Drag to reorder"></i>
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-violet-500/20 border border-violet-400/30 text-[11px] font-semibold text-violet-200" x-text="i + 1"></span>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium flex items-center gap-2" :class="s.visible ? 'text-white' : 'text-white/60'">
                            <span x-text="s.label"></span>
                            <span x-show="!s.visible" class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-white/10 text-white/60 border border-white/10">Hidden</span>
                        </div>
                        <div class="text-[11px] text-white/50 truncate" x-text="s.desc"></div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        {{-- Visibility toggle: button-styled switch so it works without extra CSS. --}}
                        <button type="button" @click="toggleVisible(i)"
                            :title="s.visible ? 'Hide this section on /about' : 'Show this section on /about'"
                            :aria-pressed="s.visible ? 'true' : 'false'"
                            class="relative inline-flex h-5 w-9 items-center rounded-full transition mr-1"
                            :class="s.visible ? 'bg-violet-500' : 'bg-white/15'">
                            <span class="sr-only">Toggle visibility</span>
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
                                :class="s.visible ? 'translate-x-4' : 'translate-x-0.5'"></span>
                        </button>
                        <button type="button" @click="moveUp(i)" :disabled="i===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" @click="moveDown(i)" :disabled="i===items.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                    </div>
                </li>
            </template>
        </ul>
        @error('extra.section_order')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
        @error('extra.section_order.*')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
        @error('extra.section_visibility')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
        @error('extra.section_visibility.*')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
    </div>

    {{-- ========== BOTTOM CTA ========== --}}
    <div>
        <h3 class="text-sm font-semibold text-white">Bottom call to action</h3>
        <p class="text-xs text-white/50 mb-3">The "Want to build with us?" panel at the foot of /about.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-3">
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Heading</label>
                <input type="text" name="extra[cta][heading]" value="{{ $aboutCta['heading'] }}" maxlength="200" placeholder="Want to build with us?" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('extra.cta.heading')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Body paragraph</label>
                <textarea name="extra[cta][body]" rows="3" maxlength="1000" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ $aboutCta['body'] }}</textarea>
                @error('extra.cta.body')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Primary button label</label>
                    <input type="text" name="extra[cta][primary_label]" value="{{ $aboutCta['primary_label'] }}" maxlength="120" placeholder="Try 1INME free" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('extra.cta.primary_label')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Primary button URL <span class="normal-case tracking-normal text-white/40">(blank = register page)</span></label>
                    <input type="text" name="extra[cta][primary_url]" value="{{ $aboutCta['primary_url'] }}" maxlength="500" placeholder="/register or https://…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('extra.cta.primary_url')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Secondary button label</label>
                    <input type="text" name="extra[cta][secondary_label]" value="{{ $aboutCta['secondary_label'] }}" maxlength="120" placeholder="Say hello" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('extra.cta.secondary_label')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Secondary button URL <span class="normal-case tracking-normal text-white/40">(blank = contact page)</span></label>
                    <input type="text" name="extra[cta][secondary_url]" value="{{ $aboutCta['secondary_url'] }}" maxlength="500" placeholder="/contact or https://…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('extra.cta.secondary_url')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-white">Founder</h3>
        <p class="text-xs text-white/50 mb-3">The featured founder card at the top of /about.</p>
        <div class="bg-white/5 border border-white/10 rounded-xl p-4 grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Name</label>
                <input type="text" name="extra[founder][name]" value="{{ $founder['name'] ?? '' }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Role / title</label>
                <input type="text" name="extra[founder][role]" value="{{ $founder['role'] ?? '' }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div class="sm:col-span-2" x-data="{ photo: @js((string)($founder['photo'] ?? '')) }">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Photo <span class="normal-case tracking-normal text-white/40">(upload an image or paste a URL)</span></label>
                <div x-data="aboutPhotoUploader({ get: () => photo, set: (v) => photo = v })" class="space-y-2">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 text-center">
                            <template x-if="photo">
                                <img :src="photo" alt="" class="w-32 h-32 rounded-full object-cover border-2 border-violet-400/40 bg-white/5" x-on:error="$el.style.display='none'">
                            </template>
                            <template x-if="!photo">
                                <div class="w-32 h-32 rounded-full border-2 border-dashed border-white/15 bg-white/5 flex items-center justify-center text-[10px] text-white/40 text-center px-2">As shown on /about</div>
                            </template>
                            <div class="text-[10px] text-white/40 mt-1">Live /about preview</div>
                        </div>
                        <div class="flex-1 space-y-2">
                            <input type="url" name="extra[founder][photo]" x-model="photo" placeholder="https://… or upload below" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button" @click="pickFile()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1">
                                    <i class="fas fa-upload"></i>
                                    <span x-text="uploading ? ('Uploading… ' + progress + '%') : 'Upload image'"></span>
                                </button>
                                <button type="button" x-show="photo" @click="recropFromUrl()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/20 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1" title="Re-crop the photo currently in the URL field"><i class="fas fa-crop"></i><span>Re-crop current photo</span></button>
                                <button type="button" x-show="photo" @click="clear()" class="text-xs px-2 py-1.5 text-white/60 hover:text-white"><i class="fas fa-times mr-1"></i>Remove</button>
                            </div>
                            <p x-show="error" x-text="error" class="text-xs text-red-400"></p>
                        </div>
                    </div>
                    <input type="file" x-ref="fileInput" @change="handleFile($event)" accept="image/*" class="hidden">
                    @include('admin.site-pages.partials.about-crop-modal')
                </div>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Short bio</label>
                <textarea name="extra[founder][bio]" rows="3" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ $founder['bio'] ?? '' }}</textarea>
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Twitter / X URL</label>
                <input type="url" name="extra[founder][links][twitter]" value="{{ $founder['links']['twitter'] ?? '' }}" placeholder="https://x.com/…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">LinkedIn URL</label>
                <input type="url" name="extra[founder][links][linkedin]" value="{{ $founder['links']['linkedin'] ?? '' }}" placeholder="https://linkedin.com/in/…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
        </div>
    </div>

    <div x-data="{ rows: {{ json_encode($coFounders) }}, moveUp(i){ if(i>0){ const a=this.rows; [a[i-1],a[i]]=[a[i],a[i-1]]; } }, moveDown(i){ const a=this.rows; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } } }">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h3 class="text-sm font-semibold text-white">Co-founders</h3>
                <p class="text-xs text-white/50">Three cards by default.</p>
            </div>
            <button type="button" @click="rows.push({name:'',role:'',photo:'',bio:'',links:{twitter:'',linkedin:''}})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white"><i class="fas fa-plus mr-1"></i>Add co-founder</button>
        </div>
        <template x-for="(p, i) in rows" :key="i">
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-3 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] uppercase tracking-wider text-white/40">Co-founder <span x-text="i+1"></span></span>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="moveUp(i)" :disabled="i===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" @click="moveDown(i)" :disabled="i===rows.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                        <button type="button" @click="rows.splice(i,1)" class="text-xs text-red-400 hover:text-red-300 px-1.5 py-1"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input type="text" :name="'extra[co_founders]['+i+'][name]'" x-model="p.name" placeholder="Name" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <input type="text" :name="'extra[co_founders]['+i+'][role]'" x-model="p.role" placeholder="Role" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <div x-data="aboutPhotoUploader({ get: () => p.photo, set: (v) => p.photo = v })" class="space-y-2">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 text-center">
                            <template x-if="p.photo">
                                <img :src="p.photo" alt="" class="w-24 h-24 rounded-full object-cover border-2 border-white/10 bg-white/5" x-on:error="$el.style.display='none'">
                            </template>
                            <template x-if="!p.photo">
                                <div class="w-24 h-24 rounded-full border-2 border-dashed border-white/15 bg-white/5 flex items-center justify-center text-[10px] text-white/40 text-center px-2">As shown on /about</div>
                            </template>
                            <div class="text-[10px] text-white/40 mt-1">Live /about preview</div>
                        </div>
                        <div class="flex-1 space-y-2">
                            <input type="url" :name="'extra[co_founders]['+i+'][photo]'" x-model="p.photo" placeholder="Photo URL or upload below" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button" @click="pickFile()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1">
                                    <i class="fas fa-upload"></i>
                                    <span x-text="uploading ? ('Uploading… ' + progress + '%') : 'Upload image'"></span>
                                </button>
                                <button type="button" x-show="p.photo" @click="recropFromUrl()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/20 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1" title="Re-crop the photo currently in the URL field"><i class="fas fa-crop"></i><span>Re-crop current photo</span></button>
                                <button type="button" x-show="p.photo" @click="clear()" class="text-xs px-2 py-1.5 text-white/60 hover:text-white"><i class="fas fa-times mr-1"></i>Remove</button>
                            </div>
                            <p x-show="error" x-text="error" class="text-xs text-red-400"></p>
                        </div>
                    </div>
                    <input type="file" x-ref="fileInput" @change="handleFile($event)" accept="image/*" class="hidden">
                    @include('admin.site-pages.partials.about-crop-modal')
                </div>
                <textarea :name="'extra[co_founders]['+i+'][bio]'" x-model="p.bio" rows="2" placeholder="Short bio" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input type="url" :name="'extra[co_founders]['+i+'][links][twitter]'" x-model="p.links.twitter" placeholder="Twitter / X URL" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <input type="url" :name="'extra[co_founders]['+i+'][links][linkedin]'" x-model="p.links.linkedin" placeholder="LinkedIn URL" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
            </div>
        </template>
        <div x-show="rows.length===0" class="text-xs text-white/40 text-center py-4">No co-founders yet — click "Add co-founder".</div>
    </div>

    <div x-data="{ rows: {{ json_encode($teamRows) }}, moveUp(i){ if(i>0){ const a=this.rows; [a[i-1],a[i]]=[a[i],a[i-1]]; } }, moveDown(i){ const a=this.rows; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } } }">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h3 class="text-sm font-semibold text-white">Team grid</h3>
                <p class="text-xs text-white/50">Smaller cards under the co-founders.</p>
            </div>
            <button type="button" @click="rows.push({name:'',role:'',photo:'',bio:'',links:{twitter:'',linkedin:''}})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white"><i class="fas fa-plus mr-1"></i>Add team member</button>
        </div>
        <template x-for="(p, i) in rows" :key="i">
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-3 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] uppercase tracking-wider text-white/40">Member <span x-text="i+1"></span></span>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="moveUp(i)" :disabled="i===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" @click="moveDown(i)" :disabled="i===rows.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                        <button type="button" @click="rows.splice(i,1)" class="text-xs text-red-400 hover:text-red-300 px-1.5 py-1"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input type="text" :name="'extra[team]['+i+'][name]'" x-model="p.name" placeholder="Name" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <input type="text" :name="'extra[team]['+i+'][role]'" x-model="p.role" placeholder="Role" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <div x-data="aboutPhotoUploader({ get: () => p.photo, set: (v) => p.photo = v })" class="space-y-2">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 text-center">
                            <template x-if="p.photo">
                                <img :src="p.photo" alt="" class="w-16 h-16 rounded-full object-cover bg-white/5" x-on:error="$el.style.display='none'">
                            </template>
                            <template x-if="!p.photo">
                                <div class="w-16 h-16 rounded-full border-2 border-dashed border-white/15 bg-white/5 flex items-center justify-center text-[9px] text-white/40 text-center px-1">/about</div>
                            </template>
                            <div class="text-[10px] text-white/40 mt-1">Live preview</div>
                        </div>
                        <div class="flex-1 space-y-2">
                            <input type="url" :name="'extra[team]['+i+'][photo]'" x-model="p.photo" placeholder="Photo URL or upload below" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button" @click="pickFile()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1">
                                    <i class="fas fa-upload"></i>
                                    <span x-text="uploading ? ('Uploading… ' + progress + '%') : 'Upload image'"></span>
                                </button>
                                <button type="button" x-show="p.photo" @click="recropFromUrl()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/20 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1" title="Re-crop the photo currently in the URL field"><i class="fas fa-crop"></i><span>Re-crop current photo</span></button>
                                <button type="button" x-show="p.photo" @click="clear()" class="text-xs px-2 py-1.5 text-white/60 hover:text-white"><i class="fas fa-times mr-1"></i>Remove</button>
                            </div>
                            <p x-show="error" x-text="error" class="text-xs text-red-400"></p>
                        </div>
                    </div>
                    <input type="file" x-ref="fileInput" @change="handleFile($event)" accept="image/*" class="hidden">
                    @include('admin.site-pages.partials.about-crop-modal')
                </div>
                <textarea :name="'extra[team]['+i+'][bio]'" x-model="p.bio" rows="2" placeholder="One-line bio" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
            </div>
        </template>
        <div x-show="rows.length===0" class="text-xs text-white/40 text-center py-4">No team members yet — click "Add team member".</div>
    </div>

    <div x-data="{ rows: {{ json_encode($milestoneRows) }}, moveUp(i){ if(i>0){ const a=this.rows; [a[i-1],a[i]]=[a[i],a[i-1]]; } }, moveDown(i){ const a=this.rows; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } } }">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h3 class="text-sm font-semibold text-white">Milestones timeline</h3>
                <p class="text-xs text-white/50">Use <code class="text-white/60">YYYY-MM</code> or <code class="text-white/60">YYYY-MM-DD</code> for dates.</p>
            </div>
            <button type="button" @click="rows.push({date:'',title:'',description:''})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white"><i class="fas fa-plus mr-1"></i>Add milestone</button>
        </div>
        <template x-for="(m, i) in rows" :key="i">
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-3 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] uppercase tracking-wider text-white/40">Milestone <span x-text="i+1"></span></span>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="moveUp(i)" :disabled="i===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" @click="moveDown(i)" :disabled="i===rows.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5 py-1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                        <button type="button" @click="rows.splice(i,1)" class="text-xs text-red-400 hover:text-red-300 px-1.5 py-1"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="grid sm:grid-cols-3 gap-3">
                    <input type="text" :name="'extra[milestones]['+i+'][date]'" x-model="m.date" placeholder="2024-03" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                    <input type="text" :name="'extra[milestones]['+i+'][title]'" x-model="m.title" placeholder="Title" class="sm:col-span-2 w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <textarea :name="'extra[milestones]['+i+'][description]'" x-model="m.description" rows="2" placeholder="What happened" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
            </div>
        </template>
        <div x-show="rows.length===0" class="text-xs text-white/40 text-center py-4">No milestones yet — click "Add milestone".</div>
    </div>
</div>
