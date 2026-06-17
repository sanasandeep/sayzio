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
    // Stable keys so x-for tracks rows across reorders/removals.
    $homeLinkTypesForJs = array_map(function ($lt, $i) {
        return [
            '_key'  => $i,
            'name'  => (string) ($lt['name'] ?? ''),
            'desc'  => (string) ($lt['desc'] ?? ''),
            'icon'  => (string) ($lt['icon'] ?? 'fa-link'),
            'color' => (string) ($lt['color'] ?? '#7c3aed'),
            'new'   => (bool) ($lt['new'] ?? false),
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
        dragFrom: null,
        add(){ this.rows.push({ _key: this.nextKey++, name: '', desc: '', icon: 'fa-link', color: '#7c3aed', new: false }); },
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
                            color: prev ? prev.color : '#7c3aed',
                            new: prev ? !!prev.new : false,
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
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" @click="syncFromFeatures()" :disabled="featuresSource.length===0"
                    class="text-xs px-3 py-1.5 bg-white/5 border border-white/10 hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed rounded-lg text-white"
                    title="Replace these cards with the current Features link-types list">
                <i class="fas fa-rotate mr-1"></i> Pull from Features
            </button>
            <button type="button" @click="add()" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white">
                <i class="fas fa-plus mr-1"></i> Add link type
            </button>
        </div>
    </div>
    <p class="text-[11px] text-white/40 mb-3"><i class="fas fa-grip-vertical mr-1"></i> Drag the handle on the left of any card to reorder. Arrow buttons still work too. <span class="text-white/30">&middot;</span> <i class="fas fa-rotate mr-1"></i> &ldquo;Pull from Features&rdquo; copies the Features page list here, keeping your accent colours and &ldquo;New&rdquo; badges for matching names.</p>

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
                             :style="'background:' + (lt.color || '#7c3aed') + '; box-shadow:0 12px 28px -12px ' + (lt.color || '#7c3aed') + ';'">
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
                        <input type="text" x-model="lt.color" maxlength="9" placeholder="#7c3aed"
                               class="w-24 px-2.5 py-1.5 bg-white/5 border border-white/10 rounded text-xs text-white font-mono">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-white/80">
                        <input type="hidden" :name="'extra[link_types]['+i+'][new]'" value="0">
                        <input type="checkbox" :name="'extra[link_types]['+i+'][new]'" value="1" x-model="lt.new" class="rounded border-white/20 bg-white/5">
                        Show &ldquo;New&rdquo; badge
                    </label>
                </div>
            </div>
        </template>
    </div>

    <div x-show="rows.length===0" class="text-xs text-white/40 text-center py-4">No link types — the &ldquo;What you can create&rdquo; section falls back to the built-in defaults.</div>
</div>
