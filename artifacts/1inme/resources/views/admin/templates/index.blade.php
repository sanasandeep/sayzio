@extends('admin.layouts.app')
@section('title', 'Templates')
@section('page-title', 'Page & Card Templates')

@section('content')
@php
    $coverPersonas = $coverPersonas ?? [];
    $coverSlugs = collect($coverPersonas)->pluck('slug')->all();
    $coverParam = !empty($coverSlugs) ? implode(',', $coverSlugs) : null;
@endphp
<div x-data="{ search: '', category: 'all', persona: 'all', customized: 'all', outdated: 'all', active: 'all', coverPersonas: @js($coverSlugs), selected: [], preview: { open: false, url: '', name: '' }, previewDevice: 'phone', previewWidths: { phone: 420, tablet: 768, desktop: 1100 }, openPreview(url, name) { this.previewDevice = 'phone'; this.preview = { open: true, url: url, name: name }; }, closePreview() { this.preview = { open: false, url: '', name: '' }; }, toggleAllVisible(ids) { var allSel = ids.every(i => this.selected.includes(i)); this.selected = allSel ? this.selected.filter(i => !ids.includes(i)) : Array.from(new Set(this.selected.concat(ids))); } }"
     @keydown.escape.window="closePreview()">
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-white/40 ak-note">Curate full-page presets and reusable card-block presets.</p>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.templates.create', ['kind' => $tab]) }}" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>New {{ $tab === 'card' ? 'Card' : 'Page' }} Template
        </a>
    </div>
</div>

<div class="flex items-center gap-1 mb-4 p-1 rounded-xl bg-white/5 border border-white/5 w-max">
    <a href="{{ route('admin.templates.index', ['tab' => 'page']) }}"
       class="px-4 py-1.5 text-xs font-semibold rounded-lg transition {{ $tab === 'page' ? 'bg-blue-600 text-white' : 'text-white/50 hover:text-white ak-muted' }}">
        Page Templates ({{ $pageTemplates->count() }})
    </a>
    <a href="{{ route('admin.templates.index', ['tab' => 'card']) }}"
       class="px-4 py-1.5 text-xs font-semibold rounded-lg transition {{ $tab === 'card' ? 'bg-blue-600 text-white' : 'text-white/50 hover:text-white ak-muted' }}">
        Card Templates ({{ $cardTemplates->count() }})
    </a>
</div>

@if($tab === 'page' && !empty($coverPersonas))
    @php $coverNames = collect($coverPersonas)->pluck('label')->filter()->values(); @endphp
    <div class="mb-4 rounded-2xl p-4 border flex items-start gap-3" style="border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.08);">
        <div class="w-9 h-9 shrink-0 bg-amber-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-wand-magic-sparkles text-amber-400 ak-amber"></i>
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="text-sm font-semibold text-amber-300 ak-amber">
                Covering {{ $coverNames->count() === 1 ? 'an uncovered persona' : 'uncovered personas' }}:
                <span class="text-amber-200 ak-amber">{{ $coverNames->join(', ', ' and ') }}</span>
            </h2>
            <p class="text-xs text-white/70 mt-1 ak-strong">
                Showing only page templates <span class="text-amber-200 ak-amber">not yet recommended</span> for
                {{ $coverNames->count() === 1 ? 'this persona' : 'these personas' }}. Edit any one and its
                persona box will be pre-checked, save to clear the dashboard warning. Or
                <a href="{{ route('admin.templates.create', ['kind' => 'page', 'persona' => $coverParam]) }}" class="underline text-amber-200 hover:text-amber-100 ak-amber">add a new template</a>
                pre-tagged for {{ $coverNames->count() === 1 ? 'it' : 'them' }}.
            </p>
        </div>
        <a href="{{ route('admin.templates.index', ['tab' => 'page']) }}"
           class="shrink-0 text-xs text-white/50 hover:text-white px-2 py-1 ak-muted" title="Clear coverage filter">
            <i class="fas fa-xmark mr-1"></i>Clear
        </a>
    </div>
@endif

@php
    $rows = $tab === 'card' ? $cardTemplates : $pageTemplates;
    $cats = $tab === 'card' ? \App\Modules\Admin\Models\CardTemplate::categories() : \App\Modules\Admin\Models\PageTemplate::categories();
    $personaOptions = \App\Modules\User\Services\PersonaCatalog::slugLabelMap();
@endphp

<div class="grid grid-cols-1 md:grid-cols-{{ $tab === 'page' ? '4' : '3' }} gap-3 mb-5">
    <div class="md:col-span-2 relative">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-white/30 ak-note"></i>
        <input type="text" x-model="search" placeholder="Search by name or description…" class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-sm text-white ak-strong ak-input">
    </div>
    <select x-model="category" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white ak-strong ak-input">
        <option value="all" class="bg-[#0d0818]">All categories</option>
        @foreach($cats as $key => $label)
            <option value="{{ $key }}" class="bg-[#0d0818]">{{ $label }}</option>
        @endforeach
    </select>
    @if($tab === 'page')
        <select x-model="persona" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white ak-strong ak-input">
            <option value="all" class="bg-[#0d0818]">All personas</option>
            @foreach($personaOptions as $slug => $label)
                <option value="{{ $slug }}" class="bg-[#0d0818]">{{ $label }}</option>
            @endforeach
        </select>
    @endif
</div>

@php
    $activeCount = $rows->where('is_active', true)->count();
    $hiddenCount = $rows->count() - $activeCount;
@endphp
<div class="flex items-center gap-2 mb-4 flex-wrap">
    <div class="flex items-center gap-1 p-1 rounded-xl bg-white/5 border border-white/5 w-max">
        <button type="button" @click="active = 'all'"
                :class="active === 'all' ? 'bg-blue-600 text-white' : 'text-white/50 hover:text-white ak-muted'"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition">
            All ({{ $rows->count() }})
        </button>
        <button type="button" @click="active = 'yes'"
                :class="active === 'yes' ? 'bg-emerald-600 text-white' : 'text-white/50 hover:text-white ak-muted'"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition">
            <i class="fas fa-eye mr-1 text-[10px]"></i>Active ({{ $activeCount }})
        </button>
        <button type="button" @click="active = 'no'"
                :class="active === 'no' ? 'bg-white/20 text-white ak-strong' : 'text-white/50 hover:text-white ak-muted'"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition">
            <i class="fas fa-eye-slash mr-1 text-[10px]"></i>Hidden ({{ $hiddenCount }})
        </button>
    </div>
</div>

@if($tab === 'page')
    @php
        $customizedCount = $rows->filter(fn($t) => $t->wasCustomized())->count();
        $untouchedCount = $rows->count() - $customizedCount;
        $outdatedCount = $rows->filter(fn($t) => $t->isOutdatedBlueprint())->count();
        $currentSeedVersion = \Database\Seeders\ExpandedPageTemplateLibrarySeeder::SEED_VERSION;
    @endphp
    <div class="flex items-center gap-2 mb-4 flex-wrap">
        <div class="flex items-center gap-1 p-1 rounded-xl bg-white/5 border border-white/5 w-max">
            <button type="button" @click="customized = 'all'"
                    :class="customized === 'all' ? 'bg-blue-600 text-white' : 'text-white/50 hover:text-white ak-muted'"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition">
                All ({{ $rows->count() }})
            </button>
            <button type="button" @click="customized = 'yes'"
                    :class="customized === 'yes' ? 'bg-blue-600 text-white' : 'text-white/50 hover:text-white ak-muted'"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                    title="Templates an admin has saved at least once since they were created">
                <i class="fas fa-pen-nib mr-1 text-[10px]"></i>Customized ({{ $customizedCount }})
            </button>
            <button type="button" @click="customized = 'no'"
                    :class="customized === 'no' ? 'bg-blue-600 text-white' : 'text-white/50 hover:text-white ak-muted'"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                    title="Untouched seed defaults, never edited in the admin panel">
                <i class="fas fa-seedling mr-1 text-[10px]"></i>Untouched ({{ $untouchedCount }})
            </button>
        </div>
        <div class="flex items-center gap-1 p-1 rounded-xl bg-white/5 border border-white/5 w-max">
            <button type="button" @click="outdated = 'all'"
                    :class="outdated === 'all' ? 'bg-blue-600 text-white' : 'text-white/50 hover:text-white ak-muted'"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                    title="Show templates regardless of blueprint version">
                Any blueprint
            </button>
            <button type="button" @click="outdated = 'yes'"
                    :class="outdated === 'yes' ? 'bg-amber-500 text-white' : 'text-white/50 hover:text-white ak-muted'"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                    title="Persona templates whose stored blueprint is older than the seeder's current SEED_VERSION ({{ $currentSeedVersion }}). Untouched ones get auto-refreshed on the next deploy; admin-edited ones stay until you reset them.">
                <i class="fas fa-triangle-exclamation mr-1 text-[10px]"></i>Outdated design ({{ $outdatedCount }})
            </button>
        </div>
    </div>
@endif

@if($rows->isEmpty())
    <div class="glass rounded-2xl border border-white/10 p-12 text-center">
        <i class="fas fa-layer-group text-3xl text-blue-400 mb-3 ak-blue"></i>
        <h3 class="text-base font-semibold text-white mb-1 ak-strong">No {{ $tab }} templates yet</h3>
        <p class="text-sm text-white/40 mb-4 ak-note">Capture a snapshot from any Link in Bio page to seed the gallery.</p>
        <a href="{{ route('admin.templates.create', ['kind' => $tab]) }}" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition">
            Create Template
        </a>
    </div>
@else
    @php $allIds = $rows->pluck('id')->all(); @endphp
    {{-- Bulk action toolbar --}}
    <div class="flex flex-wrap items-center gap-2 mb-3 p-3 rounded-xl border border-white/10 bg-white/5"
         x-show="selected.length > 0" x-cloak>
        <span class="text-xs text-white/70 font-medium ak-strong"><span x-text="selected.length"></span> selected</span>
        <form action="{{ route('admin.templates.bulk-toggle', ['kind' => $tab]) }}" method="POST" class="inline">
            @csrf
            <input type="hidden" name="action" value="activate">
            <template x-for="id in selected" :key="'a'+id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
            <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition">
                <i class="fas fa-eye mr-1 text-[10px]"></i>Activate selected
            </button>
        </form>
        <form action="{{ route('admin.templates.bulk-toggle', ['kind' => $tab]) }}" method="POST" class="inline">
            @csrf
            <input type="hidden" name="action" value="deactivate">
            <template x-for="id in selected" :key="'d'+id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
            <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white/10 hover:bg-white/20 text-white transition ak-strong">
                <i class="fas fa-eye-slash mr-1 text-[10px]"></i>Deactivate selected
            </button>
        </form>
        <button type="button" @click="selected = []" class="ml-auto text-xs text-white/40 hover:text-white ak-note">Clear</button>
    </div>
    <div class="flex items-center gap-2 mb-3">
        <button type="button"
                @click="toggleAllVisible(@js($allIds).filter(id => document.querySelector('[data-tpl-id=&quot;'+id+'&quot;]') && !document.querySelector('[data-tpl-id=&quot;'+id+'&quot;]').hasAttribute('hidden')))"
                class="text-[11px] text-white/50 hover:text-white ak-muted">
            Select all visible
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($rows as $tpl)
        @php
            $tplPersonas = $tab === 'page' ? (array) ($tpl->recommended_personas ?? []) : [];
            $tplCustomized = $tab === 'page' ? $tpl->wasCustomized() : false;
            $tplOutdated = $tab === 'page' ? $tpl->isOutdatedBlueprint() : false;
            $tplDesignIssues = $tpl->designIssues();
        @endphp
        <div x-show="(category === 'all' || category === '{{ $tpl->category }}')
                  && (persona === 'all' || @js($tplPersonas).includes(persona))
                  && (coverPersonas.length === 0 || coverPersonas.some(cp => !@js($tplPersonas).includes(cp)))
                  && (customized === 'all' || (customized === 'yes') === {{ $tplCustomized ? 'true' : 'false' }})
                  && (outdated === 'all' || (outdated === 'yes') === {{ $tplOutdated ? 'true' : 'false' }})
                  && (active === 'all' || (active === 'yes') === {{ $tpl->is_active ? 'true' : 'false' }})
                  && (search === '' || '{{ strtolower(addslashes($tpl->name . ' ' . $tpl->description)) }}'.includes(search.toLowerCase()))"
             x-cloak
             data-tpl-id="{{ $tpl->id }}"
             class="glass rounded-2xl border border-white/10 p-4 relative"
             :class="selected.includes({{ $tpl->id }}) ? 'ring-2 ring-blue-500/60' : ''">
            <label class="absolute top-3 left-3 z-10 flex items-center cursor-pointer">
                <input type="checkbox" :value="{{ $tpl->id }}" x-model="selected"
                       class="w-4 h-4 rounded border-white/30 bg-black/30 text-blue-500 focus:ring-blue-500 cursor-pointer">
            </label>
            <div class="group relative aspect-[4/3] rounded-xl mb-3 flex items-center justify-center overflow-hidden" style="background: linear-gradient(135deg, rgba(61,107,255,0.12), rgba(92,131,255,0.04));">
                @if($tpl->thumbnail_url)
                    <img src="{{ $tpl->thumbnail_url }}" alt="{{ $tpl->name }}" class="w-full h-full object-cover">
                @else
                    <img src="{{ asset('template-placeholders/page.svg') }}" alt="{{ $tpl->name }} preview" class="w-full h-full object-cover">
                @endif

                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition flex items-end justify-end p-2 gap-1.5 opacity-0 group-hover:opacity-100">
                    <form action="{{ route('admin.templates.thumbnail.upload', ['kind' => $tab, 'id' => $tpl->id]) }}"
                          method="POST"
                          enctype="multipart/form-data"
                          class="inline"
                          x-data="{ id: 'tpl-thumb-{{ $tab }}-{{ $tpl->id }}' }">
                        @csrf
                        <input type="file"
                               name="thumbnail"
                               accept="image/png,image/jpeg,image/webp,image/gif"
                               class="hidden"
                               :id="id"
                               @change="$el.form.submit()">
                        <button type="button"
                                @click="document.getElementById(id).click()"
                                class="px-2.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[11px] font-medium shadow"
                                title="{{ $tpl->thumbnail_url ? 'Replace thumbnail' : 'Upload thumbnail' }}">
                            <i class="fas {{ $tpl->thumbnail_url ? 'fa-rotate' : 'fa-upload' }} mr-1 text-[10px]"></i>
                            {{ $tpl->thumbnail_url ? 'Replace' : 'Upload' }}
                        </button>
                    </form>
                    @if($tpl->thumbnail_url)
                        <form action="{{ route('admin.templates.thumbnail.remove', ['kind' => $tab, 'id' => $tpl->id]) }}"
                              method="POST"
                              class="inline"
                              onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove this thumbnail?', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-image'})">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="px-2.5 py-1.5 bg-white/10 hover:bg-red-500/80 text-white rounded-lg text-[11px] font-medium shadow ak-strong"
                                    title="Remove thumbnail">
                                <i class="fas fa-trash text-[10px]"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="flex items-start justify-between gap-2 mb-1">
                <h3 class="text-sm font-semibold text-white truncate ak-strong">{{ $tpl->name }}</h3>
                <div class="flex items-center gap-1 shrink-0">
                    @if(!empty($tplDesignIssues))
                        <a href="{{ route('admin.templates.design.fix', ['kind' => $tab, 'id' => $tpl->id]) }}"
                           class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-red-500/10 text-red-300 hover:bg-red-500/20 ak-red"
                           title="{{ count($tplDesignIssues) }} design issue(s), unknown block types or stale design-variant keys that would silently degrade on the public page. Click to fix.">
                            <i class="fas fa-bug mr-1 text-[9px]"></i>Design issues ({{ count($tplDesignIssues) }})
                        </a>
                    @endif
                    @if($tab === 'page' && $tplOutdated)
                        <a href="{{ route('admin.templates.blueprint.diff', ['id' => $tpl->id]) }}"
                           class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-500/10 text-amber-300 hover:bg-amber-500/20 ak-amber"
                           title="Stored blueprint v{{ $tpl->seedVersion() }}, current design is v{{ $currentSeedVersion }}. Click to compare and optionally reset.">
                            <i class="fas fa-triangle-exclamation mr-1 text-[9px]"></i>Outdated v{{ $tpl->seedVersion() }}
                        </a>
                    @endif
                    @if($tab === 'page' && $tplCustomized)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-blue-500/10 text-blue-300 ak-blue"
                              title="Edited in admin on {{ optional($tpl->updated_at)->format('M j, Y') }} (vs created {{ optional($tpl->created_at)->format('M j, Y') }})">
                            <i class="fas fa-pen-nib mr-1 text-[9px]"></i>Customized
                        </span>
                    @endif
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium {{ $tpl->is_active ? 'bg-emerald-500/10 text-emerald-400 ak-green' : 'bg-white/10 text-white/60 ak-muted' }}">
                        {{ $tpl->is_active ? 'Active' : 'Hidden' }}
                    </span>
                </div>
            </div>
            <p class="text-xs text-white/40 mb-3 truncate ak-note">{{ $cats[$tpl->category] ?? $tpl->category }} · {{ $tpl->plan_tier ? 'Plan: '.$tpl->plan_tier : 'All plans' }}</p>
            @if($tpl->description)
                <p class="text-xs text-white/50 mb-3 line-clamp-2 ak-muted">{{ $tpl->description }}</p>
            @endif

            <div class="flex items-center justify-between pt-3 border-t border-white/5">
                <div class="flex items-center gap-2 text-[10px] text-white/30 ak-note">
                    @if($tab === 'page')
                        {{ count($tpl->snapshot['blocks'] ?? []) }} blocks
                    @else
                        {{ count(($tpl->snapshot['children'] ?? [])) }} child blocks
                    @endif
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="button"
                            @click="openPreview('{{ route('admin.templates.preview', ['kind' => $tab, 'id' => $tpl->id]) }}', @js($tpl->name))"
                            class="text-white/30 hover:text-blue-400 p-1.5 ak-note"
                            title="Preview as a published page">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    <form action="{{ route('admin.templates.toggle', ['kind' => $tab, 'id' => $tpl->id]) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-white/30 hover:text-amber-400 p-1.5 ak-note" title="{{ $tpl->is_active ? 'Deactivate' : 'Activate' }}">
                            <i class="fas {{ $tpl->is_active ? 'fa-eye-slash' : 'fa-eye' }} text-xs"></i>
                        </button>
                    </form>
                    <a href="{{ route('admin.templates.edit', array_filter(['kind' => $tab, 'id' => $tpl->id, 'persona' => $tab === 'page' ? $coverParam : null])) }}" class="text-white/30 hover:text-blue-400 p-1.5 ak-note"><i class="fas fa-edit text-xs"></i></a>
                    <form action="{{ route('admin.templates.destroy', ['kind' => $tab, 'id' => $tpl->id]) }}" method="POST" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this template?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-white/30 hover:text-red-400 p-1.5 ak-note"><i class="fas fa-trash text-xs"></i></button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
    </div>
@endif

{{-- Full public-style preview modal: renders the template's snapshot through
     the real biolink view inside a phone-style frame so admins can confirm
     layout/spacing before activating (publishing) it. --}}
<div x-show="preview.open" x-cloak
     class="fixed inset-0 z-[120] flex items-center justify-center p-4"
     @click.self="closePreview()">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="closePreview()"></div>
    <div class="relative w-full flex flex-col transition-[max-width] duration-300 ease-out"
         :style="{ maxWidth: previewWidths[previewDevice] + 'px', maxHeight: 'calc(100vh - 2rem)' }">
        <div class="flex items-center justify-between mb-3 gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <i class="fas fa-mobile-screen-button text-blue-400 ak-blue"></i>
                <h3 class="text-sm font-semibold text-white truncate ak-strong" x-text="preview.name"></h3>
                <span class="text-[10px] uppercase tracking-wide text-white/40 shrink-0 ak-note">Preview</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <div class="flex items-center gap-0.5 rounded-lg bg-white/5 p-0.5">
                    <button type="button" @click="previewDevice = 'phone'"
                            :class="previewDevice === 'phone' ? 'bg-blue-500/30 text-white ak-strong' : 'text-white/45 hover:text-white ak-muted'"
                            class="px-2 py-1 rounded-md transition" title="Phone width">
                        <i class="fas fa-mobile-screen-button text-xs"></i>
                    </button>
                    <button type="button" @click="previewDevice = 'tablet'"
                            :class="previewDevice === 'tablet' ? 'bg-blue-500/30 text-white ak-strong' : 'text-white/45 hover:text-white ak-muted'"
                            class="px-2 py-1 rounded-md transition" title="Tablet width">
                        <i class="fas fa-tablet-screen-button text-xs"></i>
                    </button>
                    <button type="button" @click="previewDevice = 'desktop'"
                            :class="previewDevice === 'desktop' ? 'bg-blue-500/30 text-white ak-strong' : 'text-white/45 hover:text-white ak-muted'"
                            class="px-2 py-1 rounded-md transition" title="Desktop width">
                        <i class="fas fa-desktop text-xs"></i>
                    </button>
                </div>
                <a :href="preview.url" target="_blank" rel="noopener"
                   class="text-white/50 hover:text-white p-1.5 ak-muted" title="Open in a new tab">
                    <i class="fas fa-up-right-from-square text-xs"></i>
                </a>
                <button type="button" @click="closePreview()" class="text-white/50 hover:text-white p-1.5 ak-muted" title="Close">
                    <i class="fas fa-xmark text-base"></i>
                </button>
            </div>
        </div>
        <div class="flex-1 min-h-0 rounded-3xl border-4 border-black/60 bg-black overflow-hidden shadow-2xl">
            <template x-if="preview.open">
                <iframe :src="preview.url" :title="preview.name + ' preview'"
                        class="w-full h-full bg-white" style="min-height: 60vh;"></iframe>
            </template>
        </div>
    </div>
</div>
</div>
@endsection
