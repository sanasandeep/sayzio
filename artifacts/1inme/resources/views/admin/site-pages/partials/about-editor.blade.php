@php
    $aboutDefaults = \App\Modules\Common\Support\SitePagesContent::aboutExtraDefault();
    $aboutExtra = old('extra', is_array($page->extra) && !empty($page->extra)
        ? \App\Modules\Common\Support\SitePagesContent::normalizeAboutExtra($page->extra)
        : $aboutDefaults);
    $founder = $aboutExtra['founder'] ?? $aboutDefaults['founder'];
    $coFounders = array_values((array)($aboutExtra['co_founders'] ?? []));
    $teamRows = array_values((array)($aboutExtra['team'] ?? []));
    $milestoneRows = array_values((array)($aboutExtra['milestones'] ?? []));
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
            const viewport = 320;
            return {
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
                        <template x-if="photo">
                            <img :src="photo" alt="" class="w-16 h-16 rounded-lg object-cover border border-white/10 bg-white/5" @error="$el.style.display='none'">
                        </template>
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
                        <template x-if="p.photo">
                            <img :src="p.photo" alt="" class="w-14 h-14 rounded-lg object-cover border border-white/10 bg-white/5" @error="$el.style.display='none'">
                        </template>
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
                        <template x-if="p.photo">
                            <img :src="p.photo" alt="" class="w-14 h-14 rounded-lg object-cover border border-white/10 bg-white/5" @error="$el.style.display='none'">
                        </template>
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
