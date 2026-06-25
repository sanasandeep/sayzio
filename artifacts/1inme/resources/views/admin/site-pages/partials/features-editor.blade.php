@include('admin.partials.icon-picker')
<form method="POST" action="{{ route('admin.site-pages.update', $page->slug) }}"
      x-data="featuresEditor({{ json_encode(array_values($categories)) }}, {{ json_encode(array_values($homeLinkTypesSync ?? [])) }})"
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
            <button type="button" @click="addCategory()" class="text-xs px-3 py-1.5 bg-blue-600 hover:bg-blue-700 rounded-lg text-white">
                <i class="fas fa-plus mr-1"></i> Add category
            </button>
        </div>
        <p class="text-[11px] text-white/40 mb-3"><i class="fas fa-grip-vertical mr-1"></i> Tip: drag the handle on the left of any category or feature row to reorder. Arrow buttons still work too.</p>

        <template x-for="(cat, ci) in categories" :key="cat._key">
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-4 space-y-3 transition-opacity"
                 :class="dragCat.from === ci ? 'opacity-50' : ''"
                 @dragover.prevent="onCategoryDragOver(ci, $event)"
                 @drop.prevent="onCategoryDrop(ci)">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span draggable="true"
                              @dragstart="onCategoryDragStart(ci, $event)"
                              @dragend="onCategoryDragEnd()"
                              class="cursor-grab active:cursor-grabbing text-white/40 hover:text-white/80 px-1 select-none"
                              title="Drag to reorder category"><i class="fas fa-grip-vertical"></i></span>
                        <span class="text-[10px] uppercase tracking-wider text-white/40">Category <span x-text="ci+1"></span></span>
                    </div>
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
                        <div class="flex gap-1">
                            <input type="text" :name="'categories['+ci+'][icon]'" x-model="cat.icon" placeholder="fa-square-share-nodes"
                                   class="flex-1 min-w-0 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            <button type="button" @click="$store.iconPicker.openFor(cat.icon, (name) => cat.icon = name)"
                                    class="shrink-0 px-2.5 py-2 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-white/70 hover:text-white text-sm"
                                    title="Pick from gallery">
                                <i class="fas fa-th"></i>
                            </button>
                        </div>
                    </div>
                    <div class="sm:col-span-1 flex items-end">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/30 to-fuchsia-500/20 border border-blue-400/30 flex items-center justify-center">
                            <i :class="'fas ' + (cat.icon || 'fa-circle') + ' text-blue-300 text-lg'"></i>
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
                        <div class="flex items-center gap-2">
                            <button type="button" x-show="cat.id === 'link-types' && homeSource.length > 0"
                                    @click="syncFromHome(ci)"
                                    class="text-[11px] px-2 py-1 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-white"
                                    title="Replace these features with the current home-page link-types showcase">
                                <i class="fas fa-rotate mr-1"></i> Pull from Home
                            </button>
                            <button type="button" @click="addFeature(ci)" class="text-[11px] px-2 py-1 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-white">
                                <i class="fas fa-plus mr-1"></i> Add feature
                            </button>
                        </div>
                    </div>
                    <p x-show="cat.id === 'link-types' && homeSource.length > 0" class="text-[11px] text-white/40 mb-2">
                        <i class="fas fa-rotate mr-1"></i> &ldquo;Pull from Home&rdquo; copies the home-page showcase here (name, icon, description). Optional links are kept for any feature whose name still matches.
                    </p>
                    <template x-for="(feat, fi) in cat.features" :key="feat._key">
                        <div class="bg-black/20 border border-white/5 rounded-lg p-3 mb-2 space-y-2 transition-opacity"
                             :class="dragFeat.fromCi === ci && dragFeat.fromFi === fi ? 'opacity-50' : ''"
                             @dragover.prevent="onFeatureDragOver(ci, fi, $event)"
                             @drop.prevent="onFeatureDrop(ci, fi)">
                            <div class="flex items-start gap-2">
                                <span draggable="true"
                                      @dragstart="onFeatureDragStart(ci, fi, $event)"
                                      @dragend="onFeatureDragEnd()"
                                      class="cursor-grab active:cursor-grabbing text-white/40 hover:text-white/80 pt-1.5 select-none"
                                      title="Drag to reorder feature"><i class="fas fa-grip-vertical text-[11px]"></i></span>
                                <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-2">
                                    <input type="text" :name="'categories['+ci+'][features]['+fi+'][name]'" x-model="feat.name" placeholder="Feature name"
                                           class="md:col-span-1 px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-white">
                                    <textarea :name="'categories['+ci+'][features]['+fi+'][description]'" x-model="feat.description" rows="2" placeholder="Short description"
                                              class="md:col-span-2 px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-white"></textarea>
                                    <input type="text" :name="'categories['+ci+'][features]['+fi+'][link]'" x-model="feat.link" placeholder="Optional link (e.g. /ai-chatbot or https://…)"
                                           class="md:col-span-3 px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-[11px] text-white/80">
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
        <a href="/{{ $page->slug }}" target="_blank" class="text-xs text-blue-400 hover:underline">View live page <i class="fas fa-external-link-alt ml-1 text-[10px]"></i></a>
        <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">Save changes</button>
    </div>
</form>

@push('scripts')
<script>
    function featuresEditor(initial, homeSource) {
        let _uid = 0;
        const nextKey = () => `k${++_uid}_${Date.now()}`;
        return {
            categories: (initial || []).map(c => ({
                _key: nextKey(),
                id: c.id || '',
                icon: c.icon || 'fa-circle',
                heading: c.heading || '',
                intro: c.intro || '',
                features: (c.features || []).map(f => ({
                    _key: nextKey(),
                    name: f.name || '',
                    description: f.description || '',
                    link: f.link || '',
                })),
            })),
            homeSource: (homeSource || []).map(h => ({
                name: h.name || '',
                icon: h.icon || '',
                desc: h.desc || '',
            })),
            dragCat: { from: null },
            dragFeat: { fromCi: null, fromFi: null },
            syncFromHome(ci) {
                var self = this;
                window.themedConfirm({
                    title: 'Pull from Home link types?',
                    message: 'This replaces the features in this category with the current home-page “What you can create” showcase (name, icon and description). Optional links are kept for any feature whose name still matches. Nothing is saved until you click Save changes.',
                    confirmText: 'Pull from Home',
                    confirmIcon: 'fa-rotate',
                    iconClass: 'fa-rotate',
                    onConfirm: function () {
                        var byName = {};
                        (self.categories[ci].features || []).forEach(function (f) {
                            byName[(f.name || '').trim().toLowerCase()] = f;
                        });
                        self.categories[ci].features = self.homeSource.map(function (h) {
                            var prev = byName[(h.name || '').trim().toLowerCase()];
                            return {
                                _key: nextKey(),
                                name: h.name || '',
                                description: h.desc || '',
                                icon: h.icon || (prev ? prev.icon : '') || '',
                                link: prev ? (prev.link || '') : '',
                            };
                        });
                    },
                });
            },
            addCategory() {
                this.categories.push({ _key: nextKey(), id: '', icon: 'fa-circle', heading: '', intro: '', features: [] });
            },
            removeCategory(i) {
                var self = this;
                window.themedConfirm({
                    title: 'Delete this category?',
                    message: 'The category and all of its features will be removed when you save.',
                    confirmText: 'Delete',
                    confirmIcon: 'fa-trash',
                    iconClass: 'fa-trash',
                    onConfirm: function () { self.categories.splice(i, 1); },
                });
            },
            moveCategory(i, dir) {
                const j = i + dir;
                if (j < 0 || j >= this.categories.length) return;
                const [item] = this.categories.splice(i, 1);
                this.categories.splice(j, 0, item);
            },
            addFeature(ci) {
                this.categories[ci].features.push({ _key: nextKey(), name: '', description: '', link: '' });
            },
            removeFeature(ci, fi) {
                this.categories[ci].features.splice(fi, 1);
            },
            moveFeature(ci, fi, dir) {
                const list = this.categories[ci].features;
                const j = fi + dir;
                if (j < 0 || j >= list.length) return;
                const [item] = list.splice(fi, 1);
                list.splice(j, 0, item);
            },
            onCategoryDragStart(ci, e) {
                this.dragCat.from = ci;
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                    try { e.dataTransfer.setData('text/plain', 'cat:' + ci); } catch (_) {}
                }
            },
            onCategoryDragOver(ci, e) {
                if (this.dragCat.from === null) return;
                if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
            },
            onCategoryDrop(ci) {
                const from = this.dragCat.from;
                this.dragCat.from = null;
                if (from === null || from === ci) return;
                const [item] = this.categories.splice(from, 1);
                this.categories.splice(ci, 0, item);
            },
            onCategoryDragEnd() {
                this.dragCat.from = null;
            },
            onFeatureDragStart(ci, fi, e) {
                this.dragFeat.fromCi = ci;
                this.dragFeat.fromFi = fi;
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                    try { e.dataTransfer.setData('text/plain', 'feat:' + ci + ':' + fi); } catch (_) {}
                }
                e.stopPropagation();
            },
            onFeatureDragOver(ci, fi, e) {
                if (this.dragFeat.fromCi === null) return;
                if (this.dragFeat.fromCi !== ci) return;
                if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
                e.stopPropagation();
            },
            onFeatureDrop(ci, fi) {
                const fromCi = this.dragFeat.fromCi;
                const fromFi = this.dragFeat.fromFi;
                this.dragFeat.fromCi = null;
                this.dragFeat.fromFi = null;
                if (fromCi === null || fromCi !== ci) return;
                if (fromFi === fi) return;
                const list = this.categories[ci].features;
                const [item] = list.splice(fromFi, 1);
                list.splice(fi, 0, item);
            },
            onFeatureDragEnd() {
                this.dragFeat.fromCi = null;
                this.dragFeat.fromFi = null;
            },
        };
    }
</script>
@endpush
