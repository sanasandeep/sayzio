@extends('admin.layouts.app')
@section('title', 'Templates')
@section('page-title', 'Page & Card Templates')

@section('content')
<div x-data="{ search: '', category: 'all', persona: 'all', customized: 'all' }">
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-white/40">Curate full-page presets and reusable card-block presets.</p>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.templates.create', ['kind' => $tab]) }}" class="px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700 transition">
            <i class="fas fa-plus mr-2"></i>New {{ $tab === 'card' ? 'Card' : 'Page' }} Template
        </a>
    </div>
</div>

<div class="flex items-center gap-1 mb-4 p-1 rounded-xl bg-white/5 border border-white/5 w-max">
    <a href="{{ route('admin.templates.index', ['tab' => 'page']) }}"
       class="px-4 py-1.5 text-xs font-semibold rounded-lg transition {{ $tab === 'page' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white' }}">
        Page Templates ({{ $pageTemplates->count() }})
    </a>
    <a href="{{ route('admin.templates.index', ['tab' => 'card']) }}"
       class="px-4 py-1.5 text-xs font-semibold rounded-lg transition {{ $tab === 'card' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white' }}">
        Card Templates ({{ $cardTemplates->count() }})
    </a>
</div>

@php
    $rows = $tab === 'card' ? $cardTemplates : $pageTemplates;
    $cats = $tab === 'card' ? \App\Modules\Admin\Models\CardTemplate::categories() : \App\Modules\Admin\Models\PageTemplate::categories();
    $personaOptions = \App\Modules\User\Services\PersonaCatalog::slugLabelMap();
@endphp

<div class="grid grid-cols-1 md:grid-cols-{{ $tab === 'page' ? '4' : '3' }} gap-3 mb-5">
    <div class="md:col-span-2 relative">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-white/30"></i>
        <input type="text" x-model="search" placeholder="Search by name or description…" class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-sm text-white">
    </div>
    <select x-model="category" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
        <option value="all" class="bg-[#0d0818]">All categories</option>
        @foreach($cats as $key => $label)
            <option value="{{ $key }}" class="bg-[#0d0818]">{{ $label }}</option>
        @endforeach
    </select>
    @if($tab === 'page')
        <select x-model="persona" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
            <option value="all" class="bg-[#0d0818]">All personas</option>
            @foreach($personaOptions as $slug => $label)
                <option value="{{ $slug }}" class="bg-[#0d0818]">{{ $label }}</option>
            @endforeach
        </select>
    @endif
</div>

@if($tab === 'page')
    @php
        $customizedCount = $rows->filter(fn($t) => $t->wasCustomized())->count();
        $untouchedCount = $rows->count() - $customizedCount;
    @endphp
    <div class="flex items-center gap-1 mb-4 p-1 rounded-xl bg-white/5 border border-white/5 w-max">
        <button type="button" @click="customized = 'all'"
                :class="customized === 'all' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white'"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition">
            All ({{ $rows->count() }})
        </button>
        <button type="button" @click="customized = 'yes'"
                :class="customized === 'yes' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white'"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                title="Templates an admin has saved at least once since they were created">
            <i class="fas fa-pen-nib mr-1 text-[10px]"></i>Customized ({{ $customizedCount }})
        </button>
        <button type="button" @click="customized = 'no'"
                :class="customized === 'no' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white'"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                title="Untouched seed defaults — never edited in the admin panel">
            <i class="fas fa-seedling mr-1 text-[10px]"></i>Untouched ({{ $untouchedCount }})
        </button>
    </div>
@endif

@if($rows->isEmpty())
    <div class="glass rounded-2xl border border-white/10 p-12 text-center">
        <i class="fas fa-layer-group text-3xl text-violet-400 mb-3"></i>
        <h3 class="text-base font-semibold text-white mb-1">No {{ $tab }} templates yet</h3>
        <p class="text-sm text-white/40 mb-4">Capture a snapshot from any Link in Bio page to seed the gallery.</p>
        <a href="{{ route('admin.templates.create', ['kind' => $tab]) }}" class="inline-block px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700 transition">
            Create Template
        </a>
    </div>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($rows as $tpl)
        @php
            $tplPersonas = $tab === 'page' ? (array) ($tpl->recommended_personas ?? []) : [];
            $tplCustomized = $tab === 'page' ? $tpl->wasCustomized() : false;
        @endphp
        <div x-show="(category === 'all' || category === '{{ $tpl->category }}')
                  && (persona === 'all' || @json($tplPersonas).includes(persona))
                  && (customized === 'all' || (customized === 'yes') === {{ $tplCustomized ? 'true' : 'false' }})
                  && (search === '' || '{{ strtolower(addslashes($tpl->name . ' ' . $tpl->description)) }}'.includes(search.toLowerCase()))"
             x-cloak
             class="glass rounded-2xl border border-white/10 p-4">
            <div class="group relative aspect-[4/3] rounded-xl mb-3 flex items-center justify-center overflow-hidden" style="background: linear-gradient(135deg, rgba(124,58,237,0.12), rgba(139,92,246,0.04));">
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
                                class="px-2.5 py-1.5 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-[11px] font-medium shadow"
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
                                    class="px-2.5 py-1.5 bg-white/10 hover:bg-red-500/80 text-white rounded-lg text-[11px] font-medium shadow"
                                    title="Remove thumbnail">
                                <i class="fas fa-trash text-[10px]"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="flex items-start justify-between gap-2 mb-1">
                <h3 class="text-sm font-semibold text-white truncate">{{ $tpl->name }}</h3>
                <div class="flex items-center gap-1 shrink-0">
                    @if($tab === 'page' && $tplCustomized)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-violet-500/10 text-violet-300"
                              title="Edited in admin on {{ optional($tpl->updated_at)->format('M j, Y') }} (vs created {{ optional($tpl->created_at)->format('M j, Y') }})">
                            <i class="fas fa-pen-nib mr-1 text-[9px]"></i>Customized
                        </span>
                    @endif
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium {{ $tpl->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-white/10 text-white/60' }}">
                        {{ $tpl->is_active ? 'Active' : 'Hidden' }}
                    </span>
                </div>
            </div>
            <p class="text-xs text-white/40 mb-3 truncate">{{ $cats[$tpl->category] ?? $tpl->category }} · {{ $tpl->plan_tier ? 'Plan: '.$tpl->plan_tier : 'All plans' }}</p>
            @if($tpl->description)
                <p class="text-xs text-white/50 mb-3 line-clamp-2">{{ $tpl->description }}</p>
            @endif

            <div class="flex items-center justify-between pt-3 border-t border-white/5">
                <div class="flex items-center gap-2 text-[10px] text-white/30">
                    @if($tab === 'page')
                        {{ count($tpl->snapshot['blocks'] ?? []) }} blocks
                    @else
                        {{ count(($tpl->snapshot['children'] ?? [])) }} child blocks
                    @endif
                </div>
                <div class="flex items-center gap-1.5">
                    <form action="{{ route('admin.templates.toggle', ['kind' => $tab, 'id' => $tpl->id]) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-white/30 hover:text-amber-400 p-1.5" title="{{ $tpl->is_active ? 'Deactivate' : 'Activate' }}">
                            <i class="fas {{ $tpl->is_active ? 'fa-eye-slash' : 'fa-eye' }} text-xs"></i>
                        </button>
                    </form>
                    <a href="{{ route('admin.templates.edit', ['kind' => $tab, 'id' => $tpl->id]) }}" class="text-white/30 hover:text-violet-400 p-1.5"><i class="fas fa-edit text-xs"></i></a>
                    <form action="{{ route('admin.templates.destroy', ['kind' => $tab, 'id' => $tpl->id]) }}" method="POST" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this template?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-white/30 hover:text-red-400 p-1.5"><i class="fas fa-trash text-xs"></i></button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
    </div>
@endif
</div>
@endsection
