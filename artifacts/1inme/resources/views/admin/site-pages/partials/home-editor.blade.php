@include('admin.partials.icon-picker')

@php
    // Seed the editor from saved link types, falling back to the shared
    // defaults so an admin always sees a populated, editable list.
    $homeLinkTypes = \App\Modules\Common\Support\SitePagesContent::normalizeHomeLinkTypes(
        (array) (data_get($page, 'extra.link_types') ?? [])
    );
    if (empty($homeLinkTypes)) {
        $homeLinkTypes = \App\Modules\Common\Support\SitePagesContent::homeLinkTypesDefault();
    }
    // Legacy data has no explicit `featured` flags — the public page then
    // features the first 6 positionally. Reflect that rule in the editor by
    // pre-checking the first 6 so the hierarchy is visible; saving converts
    // it into explicit flags with identical rendering.
    $homeLtHasFlags = false;
    foreach ($homeLinkTypes as $lt) {
        if (is_array($lt) && array_key_exists('featured', $lt)) {
            $homeLtHasFlags = true;
            break;
        }
    }
    $homeLtCap = \App\Modules\Common\Support\SitePagesContent::HOME_LINK_TYPES_FEATURED_CAP;
    // Stable keys so x-for tracks rows across reorders/removals.
    $homeLinkTypesForJs = array_map(function ($lt, $i) use ($homeLtHasFlags, $homeLtCap) {
        return [
            '_key'     => $i,
            'name'     => (string) ($lt['name'] ?? ''),
            'desc'     => (string) ($lt['desc'] ?? ''),
            'icon'     => (string) ($lt['icon'] ?? 'fa-link'),
            'color'    => (string) ($lt['color'] ?? '#3d6bff'),
            'new'      => (bool) ($lt['new'] ?? false),
            'featured' => $homeLtHasFlags ? (bool) ($lt['featured'] ?? false) : ($i < $homeLtCap),
        ];
    }, array_values($homeLinkTypes), array_keys(array_values($homeLinkTypes)));

    // The current Features-page "Link types" category rows, supplied by the
    // controller, so an admin can pull them into this showcase in one click.
    $featuresLinkTypesForJs = array_map(function ($f) {
        return [
            'name' => (string) ($f['name'] ?? ''),
            'icon' => (string) ($f['icon'] ?? ''),
            'desc' => (string) ($f['description'] ?? ''),
        ];
    }, array_values($featuresLinkTypesSync ?? []));
@endphp

<div class="glass rounded-2xl p-5"
     x-data="{
        rows: {{ json_encode($homeLinkTypesForJs) }},
        featuresSource: {{ json_encode($featuresLinkTypesForJs) }},
        nextKey: {{ count($homeLinkTypesForJs) }},
        featuredCap: {{ $homeLtCap }},
        dragFrom: null,
        featuredCount(){ return this.rows.filter(function(r){ return !!r.featured; }).length; },
        {{-- previewSplit mirrors SitePagesContent::splitHomeLinkTypesFeatured:
             the editor state always carries explicit flags (legacy rows are
             pre-checked positionally when seeded above), so the split is
             flag-driven in list order, capped at featuredCap; everything else
             falls to the compact strip. --}}
        previewSplit(){
            var featured = [], more = [], cap = this.featuredCap;
            this.rows.forEach(function(r){
                if(!!r.featured && featured.length < cap){ featured.push(r); } else { more.push(r); }
            });
            return { featured: featured, more: more };
        },
        previewFeatured(){ return this.previewSplit().featured; },
        previewMore(){ return this.previewSplit().more; },
        toggleFeatured(lt){
            if(!lt.featured && this.featuredCount() >= this.featuredCap){ return; }
            lt.featured = !lt.featured;
        },
        add(){ this.rows.push({ _key: this.nextKey++, name: '', desc: '', icon: 'fa-link', color: '#3d6bff', new: false, featured: false }); },
        remove(i){ this.rows.splice(i, 1); },
        moveUp(i){ if(i>0){ const a=this.rows; [a[i-1],a[i]]=[a[i],a[i-1]]; } },
        moveDown(i){ const a=this.rows; if(i<a.length-1){ [a[i+1],a[i]]=[a[i],a[i+1]]; } },
        onDragStart(i){ this.dragFrom = i; },
        onDragEnd(){ this.dragFrom = null; },
        onDrop(i){
            if(this.dragFrom === null || this.dragFrom === i){ return; }
            const a = this.rows; const moved = a.splice(this.dragFrom, 1)[0];
            a.splice(i, 0, moved); this.dragFrom = null;
        },
        syncFromFeatures(){
            var self = this;
            window.themedConfirm({
                title: 'Pull from Features link types?',
                message: 'This replaces the cards below with the current Features “Link types” list (name, icon and description). Your accent colours and “New” badges are kept for any link type whose name still matches. Nothing is saved until you click Save changes.',
                confirmText: 'Pull from Features',
                confirmIcon: 'fa-rotate',
                iconClass: 'fa-rotate',
                onConfirm: function () {
                    var byName = {};
                    self.rows.forEach(function (r) { byName[(r.name || '').trim().toLowerCase()] = r; });
                    self.rows = self.featuresSource.map(function (f) {
                        var prev = byName[(f.name || '').trim().toLowerCase()];
                        return {
                            _key: self.nextKey++,
                            name: f.name || '',
                            desc: f.desc || '',
                            icon: f.icon || (prev ? prev.icon : 'fa-link') || 'fa-link',
                            color: prev ? prev.color : '#3d6bff',
                            new: prev ? !!prev.new : false,
                            featured: prev ? !!prev.featured : false,
                        };
                    });
                },
            });
        }
     }">
    <div class="flex items-start justify-between mb-2 gap-3">
        <div>
            <label class="text-sm font-semibold text-white">&ldquo;What you can create&rdquo; link types</label>
            <p class="text-[11px] text-white/50 mt-1">The cards in the home-page showcase. Edit each type's name, description, icon, accent colour and &ldquo;New&rdquo; badge, and drag to reorder. Leaving the whole list empty restores the built-in defaults.</p>
            <p class="text-[11px] mt-1" :class="featuredCount() >= featuredCap ? 'text-amber-400/80' : 'text-white/50'">
                <i class="fas fa-star mr-1 text-amber-400/80"></i><span x-text="featuredCount()"></span>/<span x-text="featuredCap"></span> featured, featured types render as the big headline cards; everything else appears in the compact &ldquo;And plenty more&rdquo; strip below them.
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" @click="syncFromFeatures()" :disabled="featuresSource.length===0"
                    class="text-xs px-3 py-1.5 bg-white/5 border border-white/10 hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed rounded-lg text-white"
                    title="Replace these cards with the current Features link-types list">
                <i class="fas fa-rotate mr-1"></i> Pull from Features
            </button>
            <button type="button" @click="add()" class="text-xs px-3 py-1.5 bg-blue-600 hover:bg-blue-700 rounded-lg text-white">
                <i class="fas fa-plus mr-1"></i> Add link type
            </button>
        </div>
    </div>
    <p class="text-[11px] text-white/40 mb-3"><i class="fas fa-grip-vertical mr-1"></i> Drag the handle on the left of any card to reorder. Arrow buttons still work too. <span class="text-white/30">&middot;</span> <i class="fas fa-rotate mr-1"></i> &ldquo;Pull from Features&rdquo; copies the Features page list here, keeping your accent colours and &ldquo;New&rdquo; badges for matching names.</p>

    {{-- Live showcase preview: mirrors the public home split (featured big
         cards + "And plenty more" strip) and re-renders as rows are
         reordered / toggled, so admins can arrange the tiers without
         saving and round-tripping to the home page. --}}
    <div x-show="rows.length > 0" class="mb-4 bg-black/20 border border-white/10 rounded-xl p-4" data-home-showcase-preview>
        <div class="flex items-center justify-between mb-3">
            <span class="text-[10px] uppercase tracking-[.2em] font-bold text-white/40"><i class="fas fa-eye mr-1.5"></i>Live preview, &ldquo;What you can create&rdquo;</span>
            <span class="text-[10px] text-white/30">Updates as you reorder &amp; toggle &middot; nothing is saved until you click Save changes</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            <template x-for="lt in previewFeatured()" :key="'pf-' + lt._key">
                <div class="relative overflow-hidden bg-white/5 border border-white/10 rounded-xl p-3">
                    <div class="absolute -top-6 -right-6 w-16 h-16 rounded-full opacity-20" :style="'background:' + (lt.color || '#3d6bff')"></div>
                    <span x-show="lt.new" class="absolute top-1.5 right-1.5 inline-flex items-center text-[7px] font-bold uppercase tracking-wider px-1 py-px rounded-full"
                          :style="'background:rgba(255,255,255,.08); color:' + (lt.color || '#3d6bff') + '; border:1px solid ' + (lt.color || '#3d6bff') + '66;'">New</span>
                    <div class="relative flex items-start gap-2.5">
                        <div class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center"
                             :style="'background:' + (lt.color || '#3d6bff') + '; box-shadow:0 8px 18px -8px ' + (lt.color || '#3d6bff') + ';'">
                            <i :class="'fas ' + (lt.icon || 'fa-link') + ' text-white text-xs'"></i>
                        </div>
                        <div class="min-w-0 pt-0.5">
                            <div class="text-[12px] font-bold text-white leading-snug truncate" x-text="lt.name || 'Untitled type'"></div>
                            <div class="text-[10px] text-white/50 leading-snug line-clamp-2" x-text="lt.desc"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <div x-show="previewFeatured().length === 0" class="text-[11px] text-white/40 text-center py-2">No featured types, star up to <span x-text="featuredCap"></span> to fill the big-card tier.</div>
        <template x-if="previewMore().length > 0">
            <div>
                <div class="mt-3 mb-2 flex items-center gap-2" aria-hidden="true">
                    <span class="h-px flex-1 bg-white/10"></span>
                    <span class="text-[9px] font-bold uppercase tracking-[.2em] text-white/30">And plenty more</span>
                    <span class="h-px flex-1 bg-white/10"></span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-1.5">
                    <template x-for="lt in previewMore()" :key="'pm-' + lt._key">
                        <div class="relative overflow-hidden bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 flex items-center gap-1.5 min-w-0">
                            <div class="w-5 h-5 shrink-0 rounded flex items-center justify-center"
                                 :style="'background:' + (lt.color || '#3d6bff')">
                                <i :class="'fas ' + (lt.icon || 'fa-link') + ' text-white text-[8px]'"></i>
                            </div>
                            <span class="text-[10px] font-semibold text-white/80 truncate" x-text="lt.name || 'Untitled type'"></span>
                            <span x-show="lt.new" class="shrink-0 inline-flex items-center text-[6px] font-bold uppercase tracking-wider px-1 py-px rounded-full"
                                  :style="'background:rgba(255,255,255,.08); color:' + (lt.color || '#3d6bff') + '; border:1px solid ' + (lt.color || '#3d6bff') + '66;'">New</span>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <div class="space-y-3">
        <template x-for="(lt, i) in rows" :key="lt._key">
            <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-3 transition-opacity"
                 :class="dragFrom === i ? 'opacity-50' : ''"
                 @dragover.prevent="onDrop(i)">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span draggable="true"
                              @dragstart="onDragStart(i)"
                              @dragend="onDragEnd()"
                              class="cursor-grab active:cursor-grabbing text-white/40 hover:text-white/80 px-1 select-none"
                              title="Drag to reorder"><i class="fas fa-grip-vertical"></i></span>
                        <span class="text-[10px] uppercase tracking-wider text-white/40">Type <span x-text="i+1"></span></span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="moveUp(i)" :disabled="i===0" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-2 py-1" title="Move up"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" @click="moveDown(i)" :disabled="i===rows.length-1" class="text-xs text-white/60 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed px-2 py-1" title="Move down"><i class="fas fa-arrow-down"></i></button>
                        <button type="button" @click="remove(i)" class="text-xs text-red-400 hover:text-red-300 px-2 py-1" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-start">
                    {{-- Preview swatch --}}
                    <div class="sm:col-span-1 flex sm:block items-center gap-2">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center"
                             :style="'background:' + (lt.color || '#3d6bff') + '; box-shadow:0 12px 28px -12px ' + (lt.color || '#3d6bff') + ';'">
                            <i :class="'fas ' + (lt.icon || 'fa-link') + ' text-white'"></i>
                        </div>
                    </div>
                    <div class="sm:col-span-7">
                        <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Name</label>
                        <input type="text" :name="'extra[link_types]['+i+'][name]'" x-model="lt.name" maxlength="120" placeholder="Short Link"
                               class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Icon</label>
                        <div class="flex gap-1">
                            <input type="text" :name="'extra[link_types]['+i+'][icon]'" x-model="lt.icon" maxlength="60" placeholder="fa-link"
                                   class="flex-1 min-w-0 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                            <button type="button" @click="$store.iconPicker.openFor(lt.icon, (name) => lt.icon = name)"
                                    class="shrink-0 px-2.5 py-2 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-white/70 hover:text-white text-sm"
                                    title="Pick from gallery"><i class="fas fa-th"></i></button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Description</label>
                    <textarea :name="'extra[link_types]['+i+'][desc]'" x-model="lt.desc" rows="2" maxlength="500" placeholder="Short description shown under the name."
                              class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-[10px] uppercase tracking-wider text-white/40">Accent colour</label>
                        <input type="color" :name="'extra[link_types]['+i+'][color]'" x-model="lt.color"
                               class="h-8 w-12 bg-transparent border border-white/10 rounded cursor-pointer p-0">
                        <input type="text" x-model="lt.color" maxlength="9" placeholder="#3d6bff"
                               class="w-24 px-2.5 py-1.5 bg-white/5 border border-white/10 rounded text-xs text-white font-mono">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-white/80">
                        <input type="hidden" :name="'extra[link_types]['+i+'][new]'" value="0">
                        <input type="checkbox" :name="'extra[link_types]['+i+'][new]'" value="1" x-model="lt.new" class="rounded border-white/20 bg-white/5">
                        Show &ldquo;New&rdquo; badge
                    </label>
                    <label class="flex items-center gap-2 text-sm"
                           :class="(!lt.featured && featuredCount() >= featuredCap) ? 'text-white/40 cursor-not-allowed' : 'text-white/80 cursor-pointer'"
                           :title="(!lt.featured && featuredCount() >= featuredCap) ? 'Featured tier is full, un-feature another type first' : 'Show this type as a big featured card'">
                        <input type="hidden" :name="'extra[link_types]['+i+'][featured]'" value="0">
                        <input type="checkbox" :name="'extra[link_types]['+i+'][featured]'" value="1"
                               :checked="lt.featured"
                               @click.prevent="toggleFeatured(lt)"
                               :disabled="!lt.featured && featuredCount() >= featuredCap"
                               class="rounded border-white/20 bg-white/5 disabled:opacity-40">
                        <span><i class="fas fa-star mr-1 text-[10px]" :class="lt.featured ? 'text-amber-400' : 'text-white/30'"></i>Featured (big card)</span>
                    </label>
                </div>
            </div>
        </template>
    </div>

    <div x-show="rows.length===0" class="text-xs text-white/40 text-center py-4">No link types, the &ldquo;What you can create&rdquo; section falls back to the built-in defaults.</div>
</div>
