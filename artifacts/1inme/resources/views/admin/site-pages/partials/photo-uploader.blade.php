{{--
    Shared Alpine helper that powers the "upload or paste URL" photo
    control reused by the About and Contact admin editors. It POSTs the
    chosen file to the existing admin asset uploader and writes the
    returned public URL back into the bound model so the URL text input
    and the live preview stay in sync. Wrapped in @once so it is emitted
    a single time per request even if multiple editors include it.
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
                        this.error = 'Could not load this image for re-cropping. The host may block cross-origin access, re-upload the file instead.';
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
                        fd.append('folder', 'site-pages');
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
