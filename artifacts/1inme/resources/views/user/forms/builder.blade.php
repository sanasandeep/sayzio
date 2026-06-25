@extends('user.layouts.app')
@section('title', 'Build · ' . $form->title)

@section('content')
<div class="max-w-7xl mx-auto"
     x-data="formBuilder({
        title: @js($form->title),
        description: @js($form->description ?? ''),
        fields: @js($form->fields ?? []),
        types: @js($fieldTypes),
        canPrice: @js($canPrice),
     })"
     x-init="$nextTick(() => initSortable())">

    @include('user.partials.page-hero', [
        'title' => 'Build: ' . $form->title,
        'subtitle' => 'Drag fields onto your form, then click any field to edit its options.',
        'icon' => 'fa-pen-ruler',
        'back' => route('user.forms.show', $form),
    ])

    @include('user.forms._tabs')

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    <form method="POST" action="{{ route('user.forms.builder.update', $form) }}" @submit="serializeFields">
        @csrf @method('PUT')
        <input type="hidden" name="title" :value="title">
        <input type="hidden" name="description" :value="description">
        <input type="hidden" name="fields_json" id="fieldsJson">
        <template x-for="(f, i) in fields" :key="f.id + '-' + i">
            <div>
                <input type="hidden" :name="`fields[${i}][id]`" :value="f.id">
                <input type="hidden" :name="`fields[${i}][type]`" :value="f.type">
                <input type="hidden" :name="`fields[${i}][label]`" :value="f.label || ''">
                <input type="hidden" :name="`fields[${i}][placeholder]`" :value="f.placeholder || ''">
                <input type="hidden" :name="`fields[${i}][help]`" :value="f.help || ''">
                <input type="hidden" :name="`fields[${i}][required]`" :value="f.required ? 1 : 0">
                <input type="hidden" :name="`fields[${i}][rows]`" :value="f.rows || ''">
                <input type="hidden" :name="`fields[${i}][min]`" :value="f.min ?? ''">
                <input type="hidden" :name="`fields[${i}][max]`" :value="f.max ?? ''">
                <input type="hidden" :name="`fields[${i}][width]`" :value="f.width || 12">
                <input type="hidden" :name="`fields[${i}][min_length]`" :value="f.min_length ?? ''">
                <input type="hidden" :name="`fields[${i}][max_length]`" :value="f.max_length ?? ''">
                <input type="hidden" :name="`fields[${i}][pattern]`" :value="f.pattern || ''">
                <input type="hidden" :name="`fields[${i}][pattern_message]`" :value="f.pattern_message || ''">
                <input type="hidden" :name="`fields[${i}][error_message]`" :value="f.error_message || ''">
                <input type="hidden" :name="`fields[${i}][file_max_kb]`" :value="f.file_max_kb ?? ''">
                <input type="hidden" :name="`fields[${i}][file_types]`" :value="f.file_types || ''">
                <input type="hidden" :name="`fields[${i}][parent]`" :value="f.parent || ''">
                <template x-for="(opt, j) in (f.options || [])" :key="`${f.id}-opt-${j}`">
                    <input type="hidden" :name="`fields[${i}][options][${j}]`" :value="opt">
                </template>
                {{-- Per-field pricing (cents). f.price / f.option_prices are kept
                     in dollars in the editor and serialized to cents here. --}}
                <template x-if="canPrice && PRICED_TYPES.includes(f.type)">
                    <div>
                        <input type="hidden" :name="`fields[${i}][price_cents]`" :value="f.price > 0 ? Math.round(f.price * 100) : ''">
                        <template x-for="entry in Object.entries(f.option_prices || {})" :key="`${f.id}-op-${entry[0]}`">
                            <input type="hidden" :name="`fields[${i}][option_prices][${entry[0]}]`" :value="entry[1] > 0 ? Math.round(entry[1] * 100) : ''">
                        </template>
                    </div>
                </template>
                {{-- Pricing / Package field: option list (radio) + addon list (checkboxes). --}}
                <template x-for="(po, j) in (f.price_options || [])" :key="`${f.id}-po-${j}`">
                    <span>
                        <input type="hidden" :name="`fields[${i}][price_options][${j}][label]`" :value="po.label || ''">
                        <input type="hidden" :name="`fields[${i}][price_options][${j}][price]`" :value="po.price ?? 0">
                    </span>
                </template>
                <template x-for="(ad, j) in (f.addons || [])" :key="`${f.id}-ad-${j}`">
                    <span>
                        <input type="hidden" :name="`fields[${i}][addons][${j}][label]`" :value="ad.label || ''">
                        <input type="hidden" :name="`fields[${i}][addons][${j}][price]`" :value="ad.price ?? 0">
                    </span>
                </template>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- LEFT: field type palette --}}
            <aside class="lg:col-span-3">
                <div class="card-premium p-4 lg:sticky lg:top-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--text-faint);">Add a field</h4>
                    <div class="space-y-1.5 max-h-[70vh] overflow-y-auto pr-1 custom-scrollbar">
                        <template x-for="(meta, type) in types" :key="type">
                            <button type="button" @click="addField(type)"
                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs text-left transition-all hover:translate-x-1"
                                    style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                                <i :class="`fas ${meta.icon}`" class="text-blue-400 text-xs w-4 text-center"></i>
                                <span class="flex-1 truncate" x-text="meta.label"></span>
                                <i class="fas fa-plus text-[9px] opacity-40"></i>
                            </button>
                        </template>
                    </div>
                </div>
            </aside>

            {{-- CENTER: form preview / editor --}}
            <main class="lg:col-span-6">
                <div class="card-premium p-6 mb-4">
                    <input type="text" x-model="title" placeholder="Form title" class="w-full text-2xl font-extrabold bg-transparent border-0 outline-none mb-2" style="color: var(--text-primary); letter-spacing: -0.02em;">
                    <textarea x-model="description" rows="2" placeholder="Optional description shown under the title…" class="w-full text-sm bg-transparent border-0 outline-none resize-none" style="color: var(--text-muted);"></textarea>
                </div>

                <div id="fieldsList" class="min-h-[300px]"
                     style="display:grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 0.75rem;">
                    <template x-for="(f, i) in fields" :key="f.id">
                        <div :data-id="f.id"
                             :style="`grid-column: span ${f.type === 'section' ? 12 : (f.width || 12)} / span ${f.type === 'section' ? 12 : (f.width || 12)}; ${(!isTopLevel(f)) ? 'display:none;' : ''}`"
                             :class="f.type === 'section' ? 'section-card' : ''">
                            {{-- ============ SECTION (group) card ============ --}}
                            <template x-if="f.type === 'section'">
                                <div class="card-premium p-5 field-card"
                                     :class="selectedIndex === i ? 'ring-2 ring-blue-500' : ''"
                                     style="border-style: dashed; border-width: 2px;"
                                     @click="selectedIndex = i">
                                    <div class="flex items-center gap-3 mb-3">
                                        <i class="fas fa-grip-vertical text-xs handle" style="color: var(--text-faint); cursor: grab;"></i>
                                        <i class="fas fa-layer-group text-blue-400 text-xs"></i>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Section</div>
                                            <div class="text-sm font-bold" style="color: var(--text-primary);" x-text="f.label || '(untitled section)'"></div>
                                        </div>
                                        <button type="button" @click.stop="addFieldToSection(f.id)" class="text-[10px] px-2.5 py-1.5 rounded-lg font-semibold" style="background: rgba(92,131,255,0.12); color: #3d6bff;">
                                            <i class="fas fa-plus text-[9px] mr-1"></i> Add field here
                                        </button>
                                        <button type="button" @click.stop="duplicateField(i)" class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px]" style="background: var(--bg-glass-input); color: var(--text-muted);" title="Duplicate"><i class="fas fa-clone"></i></button>
                                        <button type="button" @click.stop="removeField(i)" class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px]" style="background: rgba(239,68,68,0.1); color: #f87171;" title="Delete section"><i class="fas fa-trash"></i></button>
                                    </div>
                                    {{-- Children of this section, in their own 12-col mini-grid --}}
                                    <div style="display:grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 0.75rem; padding: 0.5rem; border-radius: 8px; background: var(--bg-body);">
                                        <template x-for="(cf, ci) in fields" :key="'child-'+cf.id">
                                            <template x-if="cf.parent === f.id">
                                                <div class="card-premium p-3 field-card cursor-pointer"
                                                     :class="selectedIndex === ci ? 'ring-2 ring-blue-500' : ''"
                                                     :style="`grid-column: span ${cf.width || 12} / span ${cf.width || 12};`"
                                                     @click.stop="selectedIndex = ci">
                                                    <div class="flex items-start gap-2">
                                                        <div class="flex-1 min-w-0">
                                                            <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                                                <i :class="`fas ${types[cf.type]?.icon || 'fa-question'} text-blue-400 text-[10px]`"></i>
                                                                <span class="text-[9px] uppercase tracking-wider font-bold" style="color: var(--text-faint);" x-text="types[cf.type]?.label || cf.type"></span>
                                                                <template x-if="cf.required"><span class="text-[8px] px-1 py-0.5 rounded font-bold" style="background: rgba(239,68,68,0.12); color: #f87171;">REQ</span></template>
                                                            </div>
                                                            <div class="text-xs font-semibold" style="color: var(--text-primary);" x-text="cf.label || '(no label)'"></div>
                                                        </div>
                                                        <button type="button" @click.stop="cf.parent = ''" class="w-6 h-6 rounded flex items-center justify-center text-[9px]" style="background: var(--bg-glass-input); color: var(--text-muted);" title="Move out of section"><i class="fas fa-up-right-from-square"></i></button>
                                                        <button type="button" @click.stop="removeField(ci)" class="w-6 h-6 rounded flex items-center justify-center text-[9px]" style="background: rgba(239,68,68,0.1); color: #f87171;" title="Delete"><i class="fas fa-trash"></i></button>
                                                    </div>
                                                </div>
                                            </template>
                                        </template>
                                        <template x-if="!fields.some(cf => cf.parent === f.id)">
                                            <div class="text-center py-4 text-[11px]" style="color: var(--text-faint); grid-column: span 12;">
                                                Empty section. Click <strong>Add field here</strong> above, or pick "{{ '{' }}This section{{ '}' }}" in any field's <em>Section</em> dropdown.
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- ============ NORMAL field card (top-level only) ============ --}}
                            <template x-if="f.type !== 'section'">
                                <div class="card-premium p-4 field-card"
                                     :class="selectedIndex === i ? 'ring-2 ring-blue-500' : ''"
                                     @click="selectedIndex = i">
                                    <div class="flex items-start gap-3">
                                        <i class="fas fa-grip-vertical text-xs mt-1.5 handle" style="color: var(--text-faint); cursor: grab;"></i>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                                <i :class="`fas ${types[f.type]?.icon || 'fa-question'} text-blue-400 text-[10px]`"></i>
                                                <span class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);" x-text="types[f.type]?.label || f.type"></span>
                                                <template x-if="f.required">
                                                    <span class="text-[9px] px-1.5 py-0.5 rounded font-bold" style="background: rgba(239,68,68,0.12); color: #f87171;">REQUIRED</span>
                                                </template>
                                                <template x-if="(f.width || 12) !== 12">
                                                    <span class="text-[9px] px-1.5 py-0.5 rounded font-bold" style="background: rgba(92,131,255,0.14); color: #90acff;"
                                                          x-text="f.width === 6 ? '½ row' : (f.width === 4 ? '⅓ row' : '⅔ row')"></span>
                                                </template>
                                            </div>
                                            <div class="text-sm font-semibold mb-1" style="color: var(--text-primary);" x-text="f.label || '(no label)'"></div>
                                            <div x-show="f.type === 'text' || f.type === 'email' || f.type === 'phone' || f.type === 'url' || f.type === 'number'">
                                                <div class="px-3 py-2 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);" x-text="f.placeholder || 'Enter value…'"></div>
                                            </div>
                                            <div x-show="f.type === 'textarea'" class="px-3 py-2 rounded-lg text-xs h-16" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);" x-text="f.placeholder || 'Type your message…'"></div>
                                            <div x-show="f.type === 'select' || f.type === 'radio' || f.type === 'checkbox'" class="text-xs mt-1 space-y-1" style="color: var(--text-muted);">
                                                <template x-for="opt in (f.options || [])" :key="opt">
                                                    <div class="flex items-center gap-1.5">
                                                        <i :class="f.type === 'select' ? 'fa-caret-right' : (f.type === 'radio' ? 'fa-circle' : 'fa-square')" class="far text-[9px]"></i>
                                                        <span x-text="opt"></span>
                                                    </div>
                                                </template>
                                            </div>
                                            <div x-show="f.type === 'signature'" class="px-3 py-3 rounded-lg text-center" style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass); color: var(--text-muted);">
                                                <i class="fas fa-signature text-base mb-1" style="color: #90acff;"></i>
                                                <div class="text-[11px]">Signature pad — drawn by user</div>
                                            </div>
                                            <div x-show="f.type === 'file'" class="px-3 py-2 rounded-lg text-xs" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);">
                                                <i class="fas fa-paperclip mr-1"></i> File upload
                                                <span x-show="f.file_types" x-text="` · ${(f.file_types || '').toUpperCase()}`"></span>
                                                <span x-show="f.file_max_kb" x-text="` · max ${((f.file_max_kb || 0) / 1024).toFixed(1)} MB`"></span>
                                            </div>
                                            <div x-show="f.type === 'rating'" class="text-amber-400 text-sm">
                                                <template x-for="n in (parseInt(f.max) || 5)" :key="n"><i class="fas fa-star mr-0.5"></i></template>
                                            </div>
                                            <div x-show="f.type === 'scale'" class="flex gap-1 mt-1">
                                                <template x-for="n in ((parseInt(f.max) || 10) - (parseInt(f.min) || 0) + 1)" :key="n">
                                                    <span class="w-6 h-6 rounded text-[10px] flex items-center justify-center" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);" x-text="(parseInt(f.min) || 0) + n - 1"></span>
                                                </template>
                                            </div>
                                            <div x-show="f.type === 'page_break'" class="text-center py-3 border-t-2 border-b-2 border-dashed" style="border-color: var(--border-glass); color: var(--text-faint);">
                                                <i class="fas fa-file-export mr-1"></i> Next page →
                                            </div>
                                            <div x-show="f.type === 'divider'" class="border-t-2" style="border-color: var(--border-glass);"></div>
                                            <div x-show="f.type === 'paragraph'" class="text-sm" style="color: var(--text-muted);" x-text="f.label"></div>
                                            <div x-show="f.help" class="text-[11px] mt-1" style="color: var(--text-faint);" x-text="f.help"></div>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <button type="button" @click.stop="duplicateField(i)" class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px]" style="background: var(--bg-glass-input); color: var(--text-muted);" title="Duplicate"><i class="fas fa-clone"></i></button>
                                            <button type="button" @click.stop="removeField(i)" class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px]" style="background: rgba(239,68,68,0.1); color: #f87171;" title="Delete"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <div x-show="fields.length === 0" class="card-premium p-12 text-center" style="grid-column: span 12;">
                        <i class="fas fa-mouse-pointer text-3xl mb-3" style="color: var(--text-faint);"></i>
                        <p class="text-sm" style="color: var(--text-muted);">Click a field type from the left to add it to your form.</p>
                        <p class="text-xs mt-2" style="color: var(--text-faint);">Tip: add a <strong>Section / Group</strong> first, then drop multiple fields inside it for a single grouped card.</p>
                    </div>
                </div>

                <div class="sticky bottom-0 mt-6 py-4 flex items-center gap-3" style="background: var(--bg-body); z-index: 10;">
                    @canInWorkspace('inbox.edit')
                    <button type="submit" class="btn-primary px-8 py-3 text-sm font-semibold inline-flex items-center gap-2 shadow-lg">
                        <i class="fas fa-save text-xs"></i> Save Form
                    </button>
                    @else
                    <button type="button" disabled class="btn-primary px-8 py-3 text-sm font-semibold inline-flex items-center gap-2 shadow-lg opacity-60 cursor-not-allowed" title="Your role doesn't allow editing forms — ask a workspace admin">
                        <i class="fas fa-lock text-xs"></i> Save Form
                    </button>
                    <span class="text-xs" style="color:#b45309;"><i class="fas fa-lock"></i> View-only — saving is reserved for admins.</span>
                    @endcanInWorkspace
                    <a href="{{ $form->getPublicUrl() }}" target="_blank" class="text-xs px-4 py-2 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                        <i class="fas fa-external-link-alt text-[10px] mr-1"></i> Preview live
                    </a>
                </div>
            </main>

            {{-- RIGHT: per-field editor --}}
            <aside class="lg:col-span-3">
                <div class="card-premium p-5 lg:sticky lg:top-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--text-faint);">
                        <span x-show="selectedIndex === null">Field options</span>
                        <span x-show="selectedIndex !== null" x-text="`Editing: ${fields[selectedIndex]?.type}`"></span>
                    </h4>

                    <template x-if="selectedIndex === null">
                        <p class="text-xs leading-relaxed" style="color: var(--text-muted);">
                            Click any field on the left to edit its label, placeholder, options and validation rules.
                        </p>
                    </template>

                    <template x-if="selectedIndex !== null">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-[11px] font-medium mb-1" style="color: var(--text-muted);">Field ID <span class="text-[10px]" style="color: var(--text-faint);">(used in exports & webhooks)</span></label>
                                <input type="text" x-model="fields[selectedIndex].id" class="theme-input w-full text-xs" pattern="[a-z0-9_-]+">
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium mb-1" style="color: var(--text-muted);">Label</label>
                                <input type="text" x-model="fields[selectedIndex].label" class="theme-input w-full text-xs">
                            </div>
                            <div x-show="['text','email','phone','url','number','textarea','date','time'].includes(fields[selectedIndex].type)">
                                <label class="block text-[11px] font-medium mb-1" style="color: var(--text-muted);">Placeholder</label>
                                <input type="text" x-model="fields[selectedIndex].placeholder" class="theme-input w-full text-xs">
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium mb-1" style="color: var(--text-muted);">Help text <span class="text-[10px]" style="color: var(--text-faint);">— shown under field</span></label>
                                <input type="text" x-model="fields[selectedIndex].help" class="theme-input w-full text-xs">
                            </div>
                            <div x-show="fields[selectedIndex].type === 'textarea'">
                                <label class="block text-[11px] font-medium mb-1" style="color: var(--text-muted);">Rows</label>
                                <input type="number" x-model="fields[selectedIndex].rows" min="2" max="20" class="theme-input w-full text-xs">
                            </div>
                            <div x-show="['number','rating','scale'].includes(fields[selectedIndex].type)" class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[11px] font-medium mb-1" style="color: var(--text-muted);">Min</label>
                                    <input type="number" x-model="fields[selectedIndex].min" class="theme-input w-full text-xs">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium mb-1" style="color: var(--text-muted);">Max</label>
                                    <input type="number" x-model="fields[selectedIndex].max" class="theme-input w-full text-xs">
                                </div>
                            </div>
                            <div x-show="['select','radio','checkbox'].includes(fields[selectedIndex].type)">
                                <label class="block text-[11px] font-medium mb-1" style="color: var(--text-muted);">Options <span class="text-[10px]" style="color: var(--text-faint);">— one per line</span></label>
                                <textarea
                                    rows="5"
                                    class="theme-input w-full text-xs"
                                    @input="fields[selectedIndex].options = $event.target.value.split('\n').map(s => s.trim()).filter(Boolean)"
                                    x-text="(fields[selectedIndex].options || []).join('\n')"></textarea>
                            </div>

                            {{-- Pricing / Package editor --}}
                            <div x-show="fields[selectedIndex].type === 'pricing'" class="space-y-3">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-[11px] font-medium" style="color: var(--text-muted);">Pricing options <span class="text-[10px]" style="color: var(--text-faint);">— pick one (radio)</span></label>
                                        <button type="button" @click="addPriceOption()" class="text-[11px] font-semibold text-blue-400"><i class="fas fa-plus mr-0.5"></i> Add</button>
                                    </div>
                                    <div class="space-y-1.5">
                                        <template x-for="(po, j) in (fields[selectedIndex].price_options || [])" :key="`po-${j}`">
                                            <div class="flex items-center gap-1.5">
                                                <input type="text" x-model="po.label" placeholder="Label" class="theme-input flex-1 text-xs">
                                                <input type="number" min="0" step="0.01" x-model.number="po.price" placeholder="0.00" class="theme-input w-20 text-xs">
                                                <button type="button" @click="removePriceOption(j)" class="text-rose-400 px-1" title="Remove"><i class="fas fa-times"></i></button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-[11px] font-medium" style="color: var(--text-muted);">Add-on services <span class="text-[10px]" style="color: var(--text-faint);">— optional (checkboxes)</span></label>
                                        <button type="button" @click="addAddon()" class="text-[11px] font-semibold text-blue-400"><i class="fas fa-plus mr-0.5"></i> Add</button>
                                    </div>
                                    <div class="space-y-1.5">
                                        <template x-for="(ad, j) in (fields[selectedIndex].addons || [])" :key="`ad-${j}`">
                                            <div class="flex items-center gap-1.5">
                                                <input type="text" x-model="ad.label" placeholder="Label" class="theme-input flex-1 text-xs">
                                                <input type="number" min="0" step="0.01" x-model.number="ad.price" placeholder="0.00" class="theme-input w-20 text-xs">
                                                <button type="button" @click="removeAddon(j)" class="text-rose-400 px-1" title="Remove"><i class="fas fa-times"></i></button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <p class="text-[10px] leading-relaxed" style="color: var(--text-faint);">
                                    Prices are in <strong>{{ $paymentCurrency }}</strong> (set on the Payments tab). The submitter's chosen option + add-ons are totalled and charged at submit.
                                    @if(!$canPaidForms)
                                        <span class="block mt-1 text-amber-400"><i class="fas fa-triangle-exclamation mr-0.5"></i> Your plan doesn't include paid forms, so selections are captured but not charged.</span>
                                    @elseif(!$hasGateway)
                                        <span class="block mt-1 text-amber-400"><i class="fas fa-triangle-exclamation mr-0.5"></i> Connect a payment gateway in Payouts to actually collect — until then selections are captured but not charged.</span>
                                    @endif
                                </p>
                            </div>
                            <label class="flex items-center gap-2 text-xs cursor-pointer mt-2" style="color: var(--text-secondary);" x-show="!['heading','paragraph','divider','page_break','section'].includes(fields[selectedIndex].type)">
                                <input type="checkbox" x-model="fields[selectedIndex].required" class="rounded text-blue-500">
                                Required field
                            </label>

                            {{-- Pricing (paid forms, Pro+). Charged only when the
                                 form's payment mode is per-field — see the Payments tab. --}}
                            <template x-if="canPrice && PRICED_TYPES.includes(fields[selectedIndex].type)">
                                <div class="pt-3 mt-1" style="border-top: 1px solid var(--border-glass);">
                                    <label class="block text-[11px] font-medium mb-1.5" style="color: var(--text-muted);">
                                        <i class="fas fa-tag text-emerald-400 mr-1"></i> Pricing
                                        <span class="text-[10px]" style="color: var(--text-faint);">— amounts in {{ $priceCurrency }}</span>
                                    </label>

                                    {{-- number: price per unit (× quantity entered) --}}
                                    <div x-show="fields[selectedIndex].type === 'number'">
                                        <label class="block text-[10px] font-medium mb-1" style="color: var(--text-muted);">Price per unit</label>
                                        <input type="number" min="0" step="0.01" x-model.number="fields[selectedIndex].price" class="theme-input w-full text-xs" placeholder="0.00">
                                        <p class="text-[10px] mt-1" style="color: var(--text-faint);">Multiplied by the quantity the visitor enters.</p>
                                    </div>

                                    {{-- consent: flat add-on when ticked --}}
                                    <div x-show="fields[selectedIndex].type === 'consent'">
                                        <label class="block text-[10px] font-medium mb-1" style="color: var(--text-muted);">Add-on price</label>
                                        <input type="number" min="0" step="0.01" x-model.number="fields[selectedIndex].price" class="theme-input w-full text-xs" placeholder="0.00">
                                        <p class="text-[10px] mt-1" style="color: var(--text-faint);">Added to the total when the visitor ticks this box.</p>
                                    </div>

                                    {{-- select/radio/checkbox: per-option price --}}
                                    <div x-show="['select','radio','checkbox'].includes(fields[selectedIndex].type)" class="space-y-1.5">
                                        <p class="text-[10px]" style="color: var(--text-faint);">Set a price per option — leave blank for free options.</p>
                                        <template x-if="(fields[selectedIndex].options || []).length === 0">
                                            <p class="text-[10px]" style="color: var(--text-faint);">Add options above to price them.</p>
                                        </template>
                                        <template x-for="opt in (fields[selectedIndex].options || [])" :key="opt">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[11px] flex-1 truncate" style="color: var(--text-secondary);" x-text="opt"></span>
                                                <input type="number" min="0" step="0.01"
                                                       :value="(fields[selectedIndex].option_prices && fields[selectedIndex].option_prices[opt]) || ''"
                                                       @input="setOptionPrice(opt, $event.target.value)"
                                                       class="theme-input w-24 text-xs" placeholder="0.00">
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Section assignment (group fields into one card) --}}
                            <div x-show="fields[selectedIndex].type !== 'section' && sectionOptions.length > 0" class="pt-3 mt-1" style="border-top: 1px solid var(--border-glass);">
                                <label class="block text-[11px] font-medium mb-1.5" style="color: var(--text-muted);">
                                    <i class="fas fa-layer-group text-blue-400 mr-1"></i> Place in section
                                    <span class="text-[10px]" style="color: var(--text-faint);">— groups multiple fields into one card</span>
                                </label>
                                <select x-model="fields[selectedIndex].parent" class="theme-input w-full text-xs">
                                    <option value="">— Top level (own card) —</option>
                                    <template x-for="s in sectionOptions" :key="s.id">
                                        <option :value="s.id" x-text="s.label || '(untitled section)'"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- Width / column layout --}}
                            <div x-show="!['hidden','page_break','section'].includes(fields[selectedIndex].type)" class="pt-3 mt-1" style="border-top: 1px solid var(--border-glass);">
                                <label class="block text-[11px] font-medium mb-1.5" style="color: var(--text-muted);">Field width <span class="text-[10px]" style="color: var(--text-faint);">— place 2+ fields per row</span></label>
                                <div class="grid grid-cols-4 gap-1">
                                    <template x-for="opt in [{v:12,l:'Full'},{v:8,l:'⅔'},{v:6,l:'½'},{v:4,l:'⅓'}]" :key="opt.v">
                                        <button type="button" @click="fields[selectedIndex].width = opt.v"
                                                :class="(fields[selectedIndex].width || 12) === opt.v ? 'ring-2 ring-blue-500' : ''"
                                                class="px-2 py-2 rounded-lg text-[11px] font-semibold"
                                                style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);"
                                                x-text="opt.l"></button>
                                    </template>
                                </div>
                                <p class="text-[10px] mt-1.5" style="color: var(--text-faint);">Adjacent fields with widths that fit in 12 columns will share a row.</p>
                            </div>

                            {{-- Validation accordion --}}
                            <div x-show="!['heading','paragraph','divider','page_break','hidden','consent','signature'].includes(fields[selectedIndex].type)"
                                 x-data="{ open: false }" class="pt-3 mt-1" style="border-top: 1px solid var(--border-glass);">
                                <button type="button" @click="open = !open" class="flex items-center justify-between w-full text-[11px] font-bold uppercase tracking-wider"
                                        style="color: var(--text-faint);">
                                    <span><i class="fas fa-shield-halved mr-1.5 text-blue-400"></i> Validation</span>
                                    <i class="fas fa-chevron-down text-[10px] transition-transform" :class="open ? 'rotate-180' : ''"></i>
                                </button>
                                <div x-show="open" class="space-y-3 mt-3">
                                    {{-- Min/max length for text-like --}}
                                    <div x-show="['text','email','phone','url','textarea'].includes(fields[selectedIndex].type)" class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-[10px] font-medium mb-1" style="color: var(--text-muted);">Min length</label>
                                            <input type="number" min="0" x-model.number="fields[selectedIndex].min_length" class="theme-input w-full text-xs">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-medium mb-1" style="color: var(--text-muted);">Max length</label>
                                            <input type="number" min="1" x-model.number="fields[selectedIndex].max_length" class="theme-input w-full text-xs">
                                        </div>
                                    </div>
                                    {{-- Regex pattern --}}
                                    <div x-show="['text','phone'].includes(fields[selectedIndex].type)">
                                        <label class="block text-[10px] font-medium mb-1" style="color: var(--text-muted);">Pattern (regex) <span class="text-[10px]" style="color: var(--text-faint);">e.g. <code>^[A-Z0-9]{6}$</code></span></label>
                                        <input type="text" x-model="fields[selectedIndex].pattern" class="theme-input w-full text-xs font-mono" placeholder="^[A-Za-z]+$">
                                        <input type="text" x-model="fields[selectedIndex].pattern_message" class="theme-input w-full text-xs mt-1" placeholder="Pattern error message (optional)">
                                    </div>
                                    {{-- File upload extras --}}
                                    <div x-show="fields[selectedIndex].type === 'file'" class="space-y-2">
                                        <div>
                                            <label class="block text-[10px] font-medium mb-1" style="color: var(--text-muted);">Allowed file types <span class="text-[10px]" style="color: var(--text-faint);">— comma-separated extensions</span></label>
                                            <input type="text" x-model="fields[selectedIndex].file_types" class="theme-input w-full text-xs font-mono" placeholder="jpg,png,pdf">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-medium mb-1" style="color: var(--text-muted);">Max file size (KB)</label>
                                            <input type="number" min="1" x-model.number="fields[selectedIndex].file_max_kb" class="theme-input w-full text-xs" placeholder="10240">
                                        </div>
                                    </div>
                                    {{-- Custom required-error message --}}
                                    <div>
                                        <label class="block text-[10px] font-medium mb-1" style="color: var(--text-muted);">Custom error message <span class="text-[10px]" style="color: var(--text-faint);">— shown on any validation failure</span></label>
                                        <input type="text" x-model="fields[selectedIndex].error_message" class="theme-input w-full text-xs" placeholder="This field is invalid">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </aside>
        </div>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
function formBuilder(initial) {
    return {
        title: initial.title,
        description: initial.description,
        fields: initial.fields,
        types: initial.types,
        canPrice: !!initial.canPrice,
        PRICED_TYPES: ['number', 'select', 'radio', 'checkbox', 'consent'],
        selectedIndex: null,

        init() {
            // Stored pricing is in cents; the editor works in dollars. Normalize
            // each field's price_cents → price and option_prices (cents → dollars)
            // once on load. Hidden inputs convert back to cents on submit.
            (this.fields || []).forEach(f => {
                if (f.price_cents != null && f.price == null) {
                    f.price = (Number(f.price_cents) || 0) / 100;
                }
                if (f.option_prices && typeof f.option_prices === 'object') {
                    const dollars = {};
                    Object.keys(f.option_prices).forEach(k => {
                        dollars[k] = (Number(f.option_prices[k]) || 0) / 100;
                    });
                    f.option_prices = dollars;
                }
            });
        },

        setOptionPrice(opt, val) {
            const f = this.fields[this.selectedIndex];
            if (!f) return;
            if (!f.option_prices) f.option_prices = {};
            const n = parseFloat(val);
            if (!val || isNaN(n) || n <= 0) {
                delete f.option_prices[opt];
            } else {
                f.option_prices[opt] = n;
            }
        },

        get sectionOptions() {
            return this.fields.filter(f => f.type === 'section');
        },
        isTopLevel(f) {
            // A field is top-level when it has no parent OR its parent no longer exists.
            if (!f || f.type === 'section') return true;
            if (!f.parent) return true;
            return !this.fields.some(s => s.id === f.parent && s.type === 'section');
        },
        addFieldToSection(sectionId) {
            // Add a Short Text by default; user can change type via builder cards.
            const id = 'text_' + Math.random().toString(36).slice(2, 7);
            const f = { id, type: 'text', label: 'New field', required: false, width: 6, parent: sectionId };
            this.fields.push(f);
            this.selectedIndex = this.fields.length - 1;
        },
        addField(type) {
            const meta = this.types[type] || {};
            const id = type + '_' + Math.random().toString(36).slice(2, 7);
            const f = { id, type, label: meta.label || 'Untitled', required: false, width: 12 };
            if (type === 'section') f.label = 'New Section';
            if (['select','radio','checkbox'].includes(type)) f.options = ['Option 1', 'Option 2'];
            if (type === 'rating') { f.min = 0; f.max = 5; }
            if (type === 'scale')  { f.min = 0; f.max = 10; }
            if (type === 'textarea') f.rows = 4;
            if (type === 'paragraph') f.label = 'Some descriptive text here…';
            if (type === 'heading') f.label = 'Section Heading';
            if (type === 'divider') f.label = '';
            if (type === 'page_break') f.label = 'Next page';
            if (type === 'signature') f.label = 'Your Signature';
            if (type === 'file') { f.file_max_kb = 10240; }
            if (type === 'pricing') {
                f.label = 'Choose a package';
                f.price_options = [{ label: 'Standard', price: 9.99 }];
                f.addons = [];
            }
            this.fields.push(f);
            this.selectedIndex = this.fields.length - 1;
            this.$nextTick(() => this.initSortable());
        },
        addPriceOption() {
            const f = this.fields[this.selectedIndex];
            if (!f.price_options) f.price_options = [];
            f.price_options.push({ label: 'Option ' + (f.price_options.length + 1), price: 0 });
        },
        removePriceOption(j) {
            const f = this.fields[this.selectedIndex];
            if (f.price_options) f.price_options.splice(j, 1);
        },
        addAddon() {
            const f = this.fields[this.selectedIndex];
            if (!f.addons) f.addons = [];
            f.addons.push({ label: 'Add-on ' + (f.addons.length + 1), price: 0 });
        },
        removeAddon(j) {
            const f = this.fields[this.selectedIndex];
            if (f.addons) f.addons.splice(j, 1);
        },
        removeField(i) {
            this.fields.splice(i, 1);
            if (this.selectedIndex === i) this.selectedIndex = null;
            else if (this.selectedIndex > i) this.selectedIndex--;
        },
        duplicateField(i) {
            const copy = JSON.parse(JSON.stringify(this.fields[i]));
            copy.id = copy.id + '_copy_' + Math.random().toString(36).slice(2,5);
            this.fields.splice(i + 1, 0, copy);
            this.$nextTick(() => this.initSortable());
        },
        initSortable() {
            const list = document.getElementById('fieldsList');
            if (!list || list._sortable) return;
            list._sortable = new Sortable(list, {
                handle: '.handle',
                animation: 150,
                ghostClass: 'opacity-30',
                // Reorder by data-id so hidden child wrappers (display:none) don't
                // throw off array indices.
                onEnd: (evt) => {
                    const movedId = evt.item.getAttribute('data-id');
                    const beforeEl = evt.item.nextElementSibling;
                    const beforeId = beforeEl ? beforeEl.getAttribute('data-id') : null;
                    const fromIdx = this.fields.findIndex(f => String(f.id) === String(movedId));
                    if (fromIdx < 0) return;
                    const moved = this.fields.splice(fromIdx, 1)[0];
                    let toIdx = this.fields.length;
                    if (beforeId) {
                        const t = this.fields.findIndex(f => String(f.id) === String(beforeId));
                        if (t >= 0) toIdx = t;
                    }
                    this.fields.splice(toIdx, 0, moved);
                    this.selectedIndex = toIdx;
                }
            });
        },
        serializeFields() { /* hidden inputs handle it */ },
    };
}
</script>
@endsection
