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
                <p class="text-xs text-white/30 mb-2">Paste the URLs you want on the page, portfolio, socials, shop, booking, etc. AI will place them in the right spots.</p>
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
                <p class="text-xs text-white/30 mb-2">Upload images for your avatar, gallery, or featured links. No uploads? We'll pull images from your links automatically — and if none are found, AI can generate a matching avatar and cover (extra coins, included in the estimate).</p>

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
                @if(!empty($imageSearchEnabled))
                {{-- Google image search: candidate suggestions the creator explicitly
                     picks from — never auto-placed. Free of AI coins. Hidden
                     mid-session (searchAvailable) if the admin removes the keys
                     while this page is open. --}}
                <div class="mt-4 rounded-xl border border-white/10 bg-white/[0.03] p-3" x-show="searchAvailable">
                    <button type="button" @click="searchOpen = !searchOpen"
                            class="w-full flex items-center justify-between text-left">
                        <span class="text-sm text-white/70"><i class="fas fa-magnifying-glass text-blue-300 mr-1.5"></i> Search the web for images <span class="text-white/30 font-normal">(free)</span></span>
                        <i class="fas text-white/40 text-xs" :class="searchOpen ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </button>

                    <div x-show="searchOpen" x-cloak class="mt-3 space-y-3">
                        <div class="flex gap-2">
                            <input type="text" x-model="searchQuery" @keydown.enter.prevent="runImageSearch()"
                                   placeholder="e.g. minimalist fitness logo"
                                   class="flex-1 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white placeholder-white/25">
                            <button type="button" @click="runImageSearch()" :disabled="searching || searchQuery.trim().length < 2"
                                    class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white text-sm font-medium">
                                <i class="fas" :class="searching ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'"></i>
                            </button>
                        </div>
                        <p class="text-xs text-red-400" x-show="searchError" x-text="searchError"></p>

                        <template x-if="searchResults.length">
                            <div class="space-y-2.5">
                                <p class="text-[11px] text-amber-300/80"><i class="fas fa-circle-info mr-1"></i> <span x-text="searchDisclaimer"></span></p>
                                <div class="grid grid-cols-4 gap-2">
                                    <template x-for="r in searchResults" :key="r.url">
                                        <button type="button" @click="toggleCandidate(r.url)"
                                                class="relative aspect-square rounded-lg overflow-hidden bg-white/5 border-2 transition-colors"
                                                :class="selectedCandidates.includes(r.url) ? 'border-blue-500' : 'border-transparent hover:border-white/20'">
                                            <img :src="r.thumbnail || r.url" class="w-full h-full object-cover" :alt="r.title || ''" loading="lazy">
                                            <span class="absolute bottom-0 inset-x-0 bg-black/60 text-white/70 text-[9px] px-1 py-0.5 truncate" x-text="r.source || ''"></span>
                                            <span x-show="selectedCandidates.includes(r.url)"
                                                  class="absolute top-1 right-1 w-4 h-4 rounded-full bg-blue-500 text-white text-[9px] flex items-center justify-center"><i class="fas fa-check"></i></span>
                                        </button>
                                    </template>
                                </div>
                                <button type="button" @click="importSelected()" :disabled="importing || !selectedCandidates.length"
                                        class="px-3.5 py-2 rounded-xl bg-white/10 hover:bg-white/15 disabled:opacity-40 text-white text-xs font-medium">
                                    <i class="fas mr-1" :class="importing ? 'fa-spinner fa-spin' : 'fa-download'"></i>
                                    <span x-text="importing ? 'Adding…' : ('Add selected (' + selectedCandidates.length + ')')"></span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
                @endif

                {{-- Auto-sourced image preview (Task #5722): review before building --}}
                <div class="mt-3 rounded-xl border border-white/10 bg-white/[0.03] p-3" x-show="images.length === 0">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs text-white/50">
                            <i class="fas fa-images text-blue-300 mr-1"></i>
                            No uploads — preview the images we'd use so you can pick which to keep.
                        </p>
                        <button type="button" @click="runPreview" :disabled="previewing"
                                class="text-xs text-blue-300 hover:text-blue-200 disabled:opacity-40 transition-colors whitespace-nowrap">
                            <i class="fas fa-spinner fa-spin mr-1" x-show="previewing"></i>
                            <span x-text="previewing ? 'Checking…' : (previewed ? 'Refresh preview' : 'Preview images')"></span>
                        </button>
                    </div>
                    <p class="text-xs text-red-400 mt-1.5" x-show="previewError" x-text="previewError"></p>

                    <template x-if="previewed && extractedImgs.length">
                        <div class="mt-3">
                            <p class="text-xs text-white/40 mb-2">Found on your links — tap to keep or remove (free):</p>
                            <div class="grid grid-cols-4 sm:grid-cols-5 gap-2">
                                <template x-for="img in extractedImgs" :key="img.url">
                                    <button type="button" @click="img.keep = !img.keep"
                                            class="relative aspect-square rounded-lg overflow-hidden bg-white/5 transition-all"
                                            :class="img.keep ? 'ring-2 ring-blue-500/70' : 'opacity-40 grayscale'">
                                        <img :src="img.url" class="w-full h-full object-cover" alt="">
                                        <span class="absolute top-1 right-1 w-5 h-5 rounded-full text-[10px] flex items-center justify-center"
                                              :class="img.keep ? 'bg-blue-500 text-white' : 'bg-black/60 text-white/70'">
                                            <i class="fas" :class="img.keep ? 'fa-check' : 'fa-times'"></i>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="previewed && !keptImages.length && genInfo && genInfo.enabled">
                        <div class="mt-3">
                            <p class="text-xs text-white/40 mb-2">
                                <span x-show="extractedImgs.length">Nothing kept — </span><span x-show="!extractedImgs.length">Nothing found on your links — </span>AI can generate these instead (<span x-text="genInfo.cost_per_image"></span> coins each). Untick any you don't want:
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="slot in genInfo.slots" :key="slot">
                                    <button type="button" @click="genSlots[slot] = !genSlots[slot]"
                                            class="text-xs px-3 py-1.5 rounded-lg border transition-all capitalize"
                                            :class="genSlots[slot] ? 'border-blue-500/60 bg-blue-500/10 text-blue-200' : 'border-white/10 text-white/30'">
                                        <i class="fas mr-1" :class="genSlots[slot] ? 'fa-check' : 'fa-times'"></i>
                                        <span x-text="slot"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="previewed && !extractedImgs.length && (!genInfo || !genInfo.enabled)">
                        <p class="text-xs text-white/40 mt-2">No images found on your links — your page will be built without images.</p>
                    </template>

                    {{-- Inline upload (Task #5735): replace the auto-sourced flow right here --}}
                    <div class="mt-3 pt-3 border-t border-white/10 flex items-center justify-between gap-2">
                        <p class="text-xs text-white/40">Don't like these? Upload your own instead — uploads replace the extracted and generated images.</p>
                        <button type="button" @click="$refs.fileInput.click()" :disabled="uploading"
                                class="text-xs px-3 py-1.5 rounded-lg border border-blue-500/40 text-blue-300 hover:text-blue-200 hover:border-blue-400/60 disabled:opacity-40 transition-colors whitespace-nowrap">
                            <i class="fas mr-1" :class="uploading ? 'fa-spinner fa-spin' : 'fa-cloud-upload-alt'"></i>
                            <span x-text="uploading ? 'Uploading…' : 'Upload instead'"></span>
                        </button>
                    </div>
                </div>
            </div>

            @if(!empty($onBrandAllowed) && $brandKit)
            {{-- On-Brand AI (Task #2664): ground the build in the saved Brand Kit --}}
            <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3">
                <input type="checkbox" x-model="useBrandKit" class="mt-0.5 rounded border-white/20 bg-white/5 text-blue-500">
                <span class="text-sm">
                    <span class="text-white font-medium"><i class="fas fa-palette text-blue-300 mr-1"></i> Keep it on-brand</span>
                    <span class="block text-xs text-white/40 mt-0.5">Use your “{{ $brandKit->name }}” AI Brand Kit (its voice, tone and palette) to guide the page.</span>
                </span>
            </label>
            @endif

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

        {{-- Coins + actions --}}
        <div class="glass rounded-2xl p-4 mb-5 flex items-center justify-between text-sm">
            <div class="text-white/50">
                <i class="fas fa-coins text-amber-400 mr-1.5"></i>
                Coin balance: <span class="text-white font-medium" x-text="balance"></span>
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
            <a href="{{ route('user.links.blocks.editor', $link) }}" class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">Skip, build manually</a>
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
        useBrandKit: @json(!empty($onBrandAllowed) && $brandKit !== null),
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
        searchAvailable: true,
        searchRechecking: false,
        searchOpen: false,
        searchQuery: '',
        searching: false,
        searchError: '',
        searchResults: [],
        searchDisclaimer: '',
        selectedCandidates: [],
        importing: false,

        init() {
            // Inverse of the mid-session collapse: while the picker is hidden
            // (admin removed the CSE keys), a lightweight recheck on window
            // focus lets it reappear without a full page reload once the
            // keys are re-added.
            window.addEventListener('focus', () => this.recheckSearchAvailability());
        },

        async recheckSearchAvailability() {
            if (this.searchAvailable || this.searchRechecking) return;
            this.searchRechecking = true;
            try {
                const res = await fetch(@json(route('user.links.ai-builder.image-search.availability', $link)), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.enabled) {
                    this.searchAvailable = true;
                    this.searchError = '';
                }
            } catch (e) {
                // Network hiccup — the next focus will retry.
            } finally {
                this.searchRechecking = false;
            }
        },

        async runImageSearch() {
            const q = this.searchQuery.trim();
            if (q.length < 2 || this.searching) return;
            this.searching = true;
            this.searchError = '';
            this.searchResults = [];
            this.selectedCandidates = [];
            try {
                const res = await fetch(@json(route('user.links.ai-builder.image-search', $link)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ query: q }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    // Admin removed/disabled the Google CSE keys mid-session:
                    // collapse the picker instead of leaving it retryable forever.
                    if (data.code === 'image_search_unavailable') {
                        this.searchAvailable = false;
                        this.searchOpen = false;
                        this.searchResults = [];
                        this.selectedCandidates = [];
                        return;
                    }
                    this.searchError = data.message || 'Search failed. Please try again.';
                    return;
                }
                this.searchResults = data.results || [];
                this.searchDisclaimer = data.disclaimer || '';
                if (!this.searchResults.length) this.searchError = 'No images found for that search.';
            } catch (e) {
                this.searchError = 'Search failed. Please check your connection and try again.';
            } finally {
                this.searching = false;
            }
        },

        toggleCandidate(url) {
            const i = this.selectedCandidates.indexOf(url);
            if (i >= 0) { this.selectedCandidates.splice(i, 1); return; }
            if (this.selectedCandidates.length >= 6) return;
            this.selectedCandidates.push(url);
        },

        async importSelected() {
            if (!this.selectedCandidates.length || this.importing) return;
            this.importing = true;
            this.searchError = '';
            try {
                const res = await fetch(@json(route('user.links.ai-builder.import-images', $link)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ urls: this.selectedCandidates }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.searchError = data.message || 'Could not add those images.';
                    return;
                }
                for (const img of (data.images || [])) {
                    if (this.images.length >= this.maxImages) break;
                    if (img.url && !this.images.includes(img.url)) this.images.push(img.url);
                }
                this.selectedCandidates = [];
            } catch (e) {
                this.searchError = 'Could not add those images. Please try again.';
            } finally {
                this.importing = false;
            }
        },

        // Auto-sourced image preview (Task #5722)
        previewing: false,
        previewed: false,
        previewError: '',
        extractedImgs: [],
        genInfo: null,
        genSlots: {},

        get cleanLinks() {
            return this.links.map(l => (l || '').trim()).filter(Boolean);
        },
        get keptImages() {
            return this.extractedImgs.filter(i => i.keep).map(i => i.url);
        },
        get skippedSlots() {
            return Object.keys(this.genSlots).filter(s => !this.genSlots[s]);
        },

        async runPreview() {
            if (this.previewing) return;
            this.previewing = true;
            this.previewError = '';
            try {
                const res = await fetch(@json(route('user.links.ai-builder.source-preview', $link)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ links: this.cleanLinks }),
                });
                const body = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.previewError = body.message || 'Could not preview images. Please try again.';
                    return;
                }
                this.extractedImgs = (body.extracted || []).map(url => ({ url, keep: true }));
                this.genInfo = body.generation || null;
                const slots = {};
                ((this.genInfo && this.genInfo.slots) || []).forEach(s => { slots[s] = true; });
                this.genSlots = slots;
                this.previewed = true;
                this.estimate = null;
            } catch (e) {
                this.previewError = 'Could not preview images. Please check your connection and try again.';
            } finally {
                this.previewing = false;
            }
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
                    this.error = data.body.message || 'Not enough coins. Top up and try again.';
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
                    use_brand_kit: this.useBrandKit,
                    // Image preview choices (Task #5722): only sent once the
                    // creator has previewed and no uploads are attached —
                    // presence of kept_images means "use my list verbatim".
                    ...(this.previewed && this.images.length === 0 ? {
                        kept_images: this.keptImages,
                        skip_generated_slots: this.skippedSlots,
                    } : {}),
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
