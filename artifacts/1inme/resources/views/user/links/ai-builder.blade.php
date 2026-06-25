@extends('user.layouts.app')
@section('title', 'Build with AI')

@section('content')
<div class="max-w-2xl mx-auto" x-data="aiBiolinkBuilder()">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.blocks.editor', $link) }}" class="text-white/30 hover:text-white transition-colors" title="Skip and open the editor"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-wand-magic-sparkles text-blue-400"></i> Build with AI
            </h1>
            <p class="text-xs text-white/40 mt-0.5">Describe your page and let AI assemble it. You can refine everything in the editor afterwards.</p>
        </div>
    </div>

    @if(!$aiEnabled)
        <div class="glass rounded-2xl p-6 text-center">
            <i class="fas fa-robot text-3xl text-white/20 mb-3"></i>
            <p class="text-white/60 text-sm">The AI Engine is currently disabled. You can still build your page manually.</p>
            <a href="{{ route('user.links.blocks.editor', $link) }}" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all">Open the editor</a>
        </div>
    @else
    <form @submit.prevent="generate">
        <div class="glass rounded-2xl p-6 mb-5 space-y-5">
            {{-- Description --}}
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1.5">What's this page about? <span class="text-red-400">*</span></label>
                <textarea x-model="description" rows="5" maxlength="4000"
                          placeholder="e.g. I'm a freelance photographer. I want a hero with my name and bio, buttons to my portfolio and Instagram, a gallery of my best shots, and a contact form."
                          class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none transition-all resize-y"></textarea>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-xs text-white/30">The more detail you give, the better the result.</p>
                    <p class="text-[11px] text-white/25" x-text="description.length + ' / 4000'"></p>
                </div>
            </div>

            {{-- Links --}}
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1.5">Your links <span class="text-white/30 font-normal">(optional)</span></label>
                <p class="text-xs text-white/30 mb-2">Paste the URLs you want on the page — portfolio, socials, shop, booking, etc. AI will place them in the right spots.</p>
                <div class="space-y-2">
                    <template x-for="(link, i) in links" :key="i">
                        <div class="flex items-center gap-2">
                            <input type="url" x-model="links[i]" placeholder="https://…" maxlength="2048"
                                   class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none">
                            <button type="button" @click="links.splice(i, 1)" class="text-white/30 hover:text-red-400 transition-colors w-8 h-8 flex items-center justify-center" title="Remove">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="if (links.length < {{ $maxLinks }}) links.push('')"
                        class="mt-2 text-xs text-blue-300 hover:text-blue-200 transition-colors">
                    <i class="fas fa-plus mr-1"></i> Add a link
                </button>
            </div>

            {{-- Photos --}}
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1.5">Photos <span class="text-white/30 font-normal">(optional)</span></label>
                <p class="text-xs text-white/30 mb-2">Upload images for your avatar, gallery, or featured links. Only uploaded photos will be used.</p>

                <div class="relative rounded-xl overflow-hidden transition-all"
                     :class="{ 'ring-2 ring-blue-500/60 bg-blue-500/5': dragging, 'bg-white/5': !dragging }"
                     style="border: 1.5px dashed rgba(255,255,255,0.18);"
                     @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                     @drop.prevent="dragging = false; handleFiles($event.dataTransfer.files)">
                    <input type="file" accept="image/*" multiple x-ref="fileInput"
                           @change="handleFiles($event.target.files); $event.target.value = ''"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" :disabled="uploading">
                    <div class="px-4 py-5 text-center pointer-events-none">
                        <i class="fas fa-cloud-upload-alt text-2xl text-white/30 mb-1.5" x-show="!uploading"></i>
                        <i class="fas fa-spinner fa-spin text-2xl text-blue-300 mb-1.5" x-show="uploading"></i>
                        <p class="text-xs text-white/50" x-text="uploading ? 'Uploading…' : 'Drag & drop or click to upload images'"></p>
                    </div>
                </div>
                <p class="text-xs text-red-400 mt-1.5" x-show="uploadError" x-text="uploadError"></p>

                <div class="grid grid-cols-4 sm:grid-cols-5 gap-2 mt-3" x-show="images.length">
                    <template x-for="(img, i) in images" :key="img">
                        <div class="relative aspect-square rounded-lg overflow-hidden bg-white/5 group">
                            <img :src="img" class="w-full h-full object-cover" alt="">
                            <button type="button" @click="images.splice(i, 1)"
                                    class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white/80 hover:bg-red-500 text-[10px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Documents / files --}}
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1.5">Files <span class="text-white/30 font-normal">(optional)</span></label>
                <p class="text-xs text-white/30 mb-2">Upload PDFs or documents to offer as downloads (menus, resumes, brochures, etc).</p>

                <div class="relative rounded-xl overflow-hidden transition-all"
                     :class="{ 'ring-2 ring-blue-500/60 bg-blue-500/5': draggingDocs, 'bg-white/5': !draggingDocs }"
                     style="border: 1.5px dashed rgba(255,255,255,0.18);"
                     @dragover.prevent="draggingDocs = true" @dragleave.prevent="draggingDocs = false"
                     @drop.prevent="draggingDocs = false; handleDocs($event.dataTransfer.files)">
                    <input type="file" multiple x-ref="docInput"
                           @change="handleDocs($event.target.files); $event.target.value = ''"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" :disabled="uploadingDocs">
                    <div class="px-4 py-5 text-center pointer-events-none">
                        <i class="fas fa-file-arrow-up text-2xl text-white/30 mb-1.5" x-show="!uploadingDocs"></i>
                        <i class="fas fa-spinner fa-spin text-2xl text-blue-300 mb-1.5" x-show="uploadingDocs"></i>
                        <p class="text-xs text-white/50" x-text="uploadingDocs ? 'Uploading…' : 'Drag & drop or click to upload files'"></p>
                    </div>
                </div>
                <p class="text-xs text-red-400 mt-1.5" x-show="docError" x-text="docError"></p>

                <div class="space-y-1.5 mt-3" x-show="files.length">
                    <template x-for="(f, i) in files" :key="f.url">
                        <div class="flex items-center gap-2 bg-white/5 rounded-lg px-3 py-2 group">
                            <i class="fas fa-file text-white/40 text-sm"></i>
                            <span class="text-xs text-white/70 truncate flex-1" x-text="f.name"></span>
                            <button type="button" @click="files.splice(i, 1)"
                                    class="text-white/30 hover:text-red-400 transition-colors w-6 h-6 flex items-center justify-center" title="Remove">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Credits + actions --}}
        <div class="glass rounded-2xl p-4 mb-5 flex items-center justify-between text-sm">
            <div class="text-white/50">
                <i class="fas fa-coins text-amber-400 mr-1.5"></i>
                AI credit balance: <span class="text-white font-medium" x-text="balance"></span>
                <template x-if="estimate !== null">
                    <span class="text-white/40 ml-2">· est. cost ~<span x-text="estimate"></span></span>
                </template>
            </div>
            <button type="button" @click="runEstimate" :disabled="!canSubmit || estimating"
                    class="text-xs text-blue-300 hover:text-blue-200 disabled:opacity-40 transition-colors">
                <i class="fas fa-calculator mr-1"></i> <span x-text="estimating ? 'Estimating…' : 'Estimate cost'"></span>
            </button>
        </div>

        <p class="text-sm text-red-400 mb-3" x-show="error" x-text="error"></p>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('user.links.blocks.editor', $link) }}" class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">Skip — build manually</a>
            <button type="submit" :disabled="!canSubmit || generating || uploading"
                    class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-blue-500/20">
                <i class="fas fa-spinner fa-spin mr-1.5" x-show="generating"></i>
                <i class="fas fa-wand-magic-sparkles mr-1.5 text-xs" x-show="!generating"></i>
                <span x-text="generating ? 'Building your page…' : 'Generate my page'"></span>
            </button>
        </div>
    </form>
    @endif
</div>

@if($aiEnabled)
<script>
function aiBiolinkBuilder() {
    return {
        description: '',
        links: [''],
        images: [],
        files: [],
        balance: @json($balance),
        estimate: null,
        estimating: false,
        generating: false,
        uploading: false,
        uploadingDocs: false,
        dragging: false,
        draggingDocs: false,
        error: '',
        uploadError: '',
        docError: '',
        maxImages: {{ $maxImages }},
        maxFiles: {{ $maxFiles }},

        get cleanLinks() {
            return this.links.map(l => (l || '').trim()).filter(Boolean);
        },
        get canSubmit() {
            return this.description.trim().length >= 10;
        },

        async handleFiles(fileList) {
            this.uploadError = '';
            const files = Array.from(fileList || []);
            for (const file of files) {
                if (this.images.length >= this.maxImages) {
                    this.uploadError = 'You can upload up to ' + this.maxImages + ' photos.';
                    break;
                }
                if (!file.type.startsWith('image/')) {
                    this.uploadError = 'Only image files are supported.';
                    continue;
                }
                await this.uploadOne(file);
            }
        },

        async uploadOne(file) {
            this.uploading = true;
            try {
                const fd = new FormData();
                fd.append('file', file);
                const res = await fetch(@json(route('user.files.upload')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: fd,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success || !data.file || !data.file.url) {
                    this.uploadError = (data && data.error) ? data.error : 'Upload failed. Please try again.';
                    return;
                }
                const url = data.file.url;
                if (!this.images.includes(url)) this.images.push(url);
            } catch (e) {
                this.uploadError = 'Upload failed. Please check your connection and try again.';
            } finally {
                this.uploading = false;
            }
        },

        async handleDocs(fileList) {
            this.docError = '';
            const files = Array.from(fileList || []);
            for (const file of files) {
                if (this.files.length >= this.maxFiles) {
                    this.docError = 'You can upload up to ' + this.maxFiles + ' files.';
                    break;
                }
                await this.uploadOneDoc(file);
            }
        },

        async uploadOneDoc(file) {
            this.uploadingDocs = true;
            try {
                const fd = new FormData();
                fd.append('file', file);
                const res = await fetch(@json(route('user.files.upload')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: fd,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success || !data.file || !data.file.url) {
                    this.docError = (data && data.error) ? data.error : 'Upload failed. Please try again.';
                    return;
                }
                const url = data.file.url;
                const name = (data.file.name || data.file.original_name || file.name || 'Download');
                if (!this.files.some(f => f.url === url)) this.files.push({ url, name });
            } catch (e) {
                this.docError = 'Upload failed. Please check your connection and try again.';
            } finally {
                this.uploadingDocs = false;
            }
        },

        async runEstimate() {
            if (!this.canSubmit) return;
            this.estimating = true;
            this.error = '';
            try {
                const data = await this.post(@json(route('user.links.ai-builder.estimate', $link)));
                if (data.ok) {
                    this.estimate = data.body.estimated_credits;
                    if (typeof data.body.balance === 'number') this.balance = data.body.balance;
                } else {
                    this.error = data.body.message || 'Could not estimate the cost.';
                }
            } finally {
                this.estimating = false;
            }
        },

        async generate() {
            if (!this.canSubmit || this.generating || this.uploading) return;
            this.generating = true;
            this.error = '';
            try {
                const data = await this.post(@json(route('user.links.ai-builder.generate', $link)));
                if (data.ok && data.body.redirect) {
                    window.location.href = data.body.redirect;
                    return;
                }
                if (data.status === 402) {
                    this.error = data.body.message || 'Not enough AI credits. Top up and try again.';
                } else {
                    this.error = data.body.message || 'Something went wrong building your page. Please try again.';
                }
                if (typeof data.body.balance === 'number') this.balance = data.body.balance;
            } catch (e) {
                this.error = 'Something went wrong building your page. Please try again.';
            } finally {
                this.generating = false;
            }
        },

        async post(url) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    description: this.description.trim(),
                    links: this.cleanLinks,
                    images: this.images,
                    files: this.files.map(f => f.url),
                }),
            });
            const body = await res.json().catch(() => ({}));
            return { ok: res.ok, status: res.status, body };
        },
    };
}
</script>
@endif
@endsection
