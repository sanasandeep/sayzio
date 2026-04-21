<form method="POST" action="{{ route('admin.site-pages.update', $page->slug) }}"
      x-data="featuresEditor({{ json_encode(array_values($categories)) }})"
      class="glass rounded-2xl p-6 space-y-5">
    @csrf
    @method('PUT')
    <div>
        <h2 class="text-lg font-semibold text-white">{{ $page->title }} <span class="text-xs text-white/40 ml-2">/{{ $page->slug }}</span></h2>
        <p class="text-xs text-white/50 mt-1">Edit every category and feature row shown on the public Features page. Categories stay in the order listed below — nothing is collapsed or merged automatically.</p>
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Page title</label>
        <input type="text" name="title" required value="{{ old('title', $page->title) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
        @error('title')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Meta description</label>
        <textarea name="meta_description" rows="2" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old('meta_description', $page->meta_description) }}</textarea>
    </div>

    <div>
        <div class="flex items-center justify-between mb-2">
            <label class="text-xs font-semibold uppercase tracking-wider text-white/60">Feature categories</label>
            <button type="button" @click="addCategory()" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white">
                <i class="fas fa-plus mr-1"></i> Add category
            </button>
        </div>

        <template x-for="(cat, ci) in categories" :key="ci">
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-4 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] uppercase tracking-wider text-white/40">Category <span x-text="ci+1"></span></span>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="moveCategory(ci, -1)" :disabled="ci===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-2 py-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" @click="moveCategory(ci, 1)" :disabled="ci===categories.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-2 py-1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                        <button type="button" @click="removeCategory(ci)" class="text-xs text-red-400 hover:text-red-300 px-2 py-1" title="Delete category"><i class="fas fa-trash"></i></button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Anchor id</label>
                        <input type="text" :name="'categories['+ci+'][id]'" x-model="cat.id" placeholder="biolink"
                               pattern="[a-z0-9\-]*"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">FontAwesome icon</label>
                        <input type="text" :name="'categories['+ci+'][icon]'" x-model="cat.icon" placeholder="fa-square-share-nodes"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    </div>
                    <div class="sm:col-span-1 flex items-end">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500/30 to-fuchsia-500/20 border border-violet-400/30 flex items-center justify-center">
                            <i :class="'fas ' + (cat.icon || 'fa-circle') + ' text-violet-300 text-lg'"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Heading</label>
                    <input type="text" :name="'categories['+ci+'][heading]'" x-model="cat.heading" placeholder="Category heading"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Intro</label>
                    <textarea :name="'categories['+ci+'][intro]'" x-model="cat.intro" rows="2" placeholder="One-paragraph intro shown below the heading."
                              class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
                </div>

                <div class="border-t border-white/10 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-[10px] uppercase tracking-wider text-white/40">Features in this category</label>
                        <button type="button" @click="addFeature(ci)" class="text-[11px] px-2 py-1 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-white">
                            <i class="fas fa-plus mr-1"></i> Add feature
                        </button>
                    </div>
                    <template x-for="(feat, fi) in cat.features" :key="fi">
                        <div class="bg-black/20 border border-white/5 rounded-lg p-3 mb-2 space-y-2">
                            <div class="flex items-start gap-2">
                                <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-2">
                                    <input type="text" :name="'categories['+ci+'][features]['+fi+'][name]'" x-model="feat.name" placeholder="Feature name"
                                           class="md:col-span-1 px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-white">
                                    <textarea :name="'categories['+ci+'][features]['+fi+'][description]'" x-model="feat.description" rows="2" placeholder="Short description"
                                              class="md:col-span-2 px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-white"></textarea>
                                </div>
                                <div class="flex flex-col gap-1 shrink-0">
                                    <button type="button" @click="moveFeature(ci, fi, -1)" :disabled="fi===0" class="text-[11px] text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5" title="Move up"><i class="fas fa-arrow-up"></i></button>
                                    <button type="button" @click="moveFeature(ci, fi, 1)" :disabled="fi===cat.features.length-1" class="text-[11px] text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-1.5" title="Move down"><i class="fas fa-arrow-down"></i></button>
                                    <button type="button" @click="removeFeature(ci, fi)" class="text-[11px] text-red-400 hover:text-red-300 px-1.5" title="Delete feature"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div x-show="cat.features.length===0" class="text-[11px] text-white/40 text-center py-2">No features in this category yet.</div>
                </div>
            </div>
        </template>

        <div x-show="categories.length===0" class="text-xs text-white/40 text-center py-4">No categories yet — click "Add category".</div>
    </div>

    <div class="pt-4 border-t border-white/10 flex items-center justify-between">
        <a href="/{{ $page->slug }}" target="_blank" class="text-xs text-violet-400 hover:underline">View live page <i class="fas fa-external-link-alt ml-1 text-[10px]"></i></a>
        <button type="submit" class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-xl font-medium">Save changes</button>
    </div>
</form>

@push('scripts')
<script>
    function featuresEditor(initial) {
        return {
            categories: (initial || []).map(c => ({
                id: c.id || '',
                icon: c.icon || 'fa-circle',
                heading: c.heading || '',
                intro: c.intro || '',
                features: (c.features || []).map(f => ({
                    name: f.name || '',
                    description: f.description || '',
                })),
            })),
            addCategory() {
                this.categories.push({ id: '', icon: 'fa-circle', heading: '', intro: '', features: [] });
            },
            removeCategory(i) {
                if (!confirm('Delete this category and all its features?')) return;
                this.categories.splice(i, 1);
            },
            moveCategory(i, dir) {
                const j = i + dir;
                if (j < 0 || j >= this.categories.length) return;
                const tmp = this.categories[i];
                this.categories[i] = this.categories[j];
                this.categories[j] = tmp;
            },
            addFeature(ci) {
                this.categories[ci].features.push({ name: '', description: '' });
            },
            removeFeature(ci, fi) {
                this.categories[ci].features.splice(fi, 1);
            },
            moveFeature(ci, fi, dir) {
                const list = this.categories[ci].features;
                const j = fi + dir;
                if (j < 0 || j >= list.length) return;
                const tmp = list[fi];
                list[fi] = list[j];
                list[j] = tmp;
            },
        };
    }
</script>
@endpush
