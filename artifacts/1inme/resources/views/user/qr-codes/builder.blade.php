@extends('user.layouts.app')
@section('title', $qrCode && $qrCode->exists ? 'Edit · ' . $qrCode->name : 'New QR Code')

@php
    use App\Modules\User\Support\QrCodeCatalog;

    $isEdit       = $qrCode && $qrCode->exists;
    $action       = $isEdit ? route('user.qr-codes.update', $qrCode) : route('user.qr-codes.store');
    $design       = ($isEdit ? ($qrCode->design ?? []) : []) + $defaultDesign;
    $design['frame'] = ($isEdit ? ($qrCode->design['frame'] ?? []) : []) + $defaultDesign['frame'];
    foreach (['logo_center','logo_background','logo_foreground','gradient','eye_outer_gradient','eye_inner_gradient','bg_gradient'] as $k) {
        $design[$k] = ($isEdit ? ($qrCode->design[$k] ?? []) : []) + $defaultDesign[$k];
    }
    // Backwards compat: migrate legacy logo_url/logo_size into logo_center
    if ($isEdit) {
        $legacy = $qrCode->design ?? [];
        if (!empty($legacy['logo_url']) && empty($design['logo_center']['url'])) {
            $design['logo_center']['url']  = $legacy['logo_url'];
            $design['logo_center']['show'] = true;
            $design['logo_center']['size'] = $legacy['logo_size'] ?? 0.25;
        }
    }
    $payload      = $isEdit ? ($qrCode->payload ?? []) : [];
    $currentType  = $isEdit ? $qrCode->type : 'url';
    $linkId       = $isEdit ? $qrCode->link_id : null;

    $catalog = [
        'dots'      => QrCodeCatalog::dotShapes(),
        'outerEyes' => QrCodeCatalog::outerEyeShapes(),
        'innerEyes' => QrCodeCatalog::innerEyeShapes(),
        'frames'    => QrCodeCatalog::frames(),
        'fonts'     => QrCodeCatalog::fonts(),
    ];
    $presets = $presets ?? [];
@endphp

@section('content')
<style>
    [x-cloak] { display: none !important; }
    .qr-type-tab { transition: all .15s; }
    .qr-type-tab.active { background: var(--c-primary-soft); color: var(--c-primary); border-color: var(--accent); }
    .qr-tab { padding: 8px 12px; border-bottom: 2px solid transparent; cursor: pointer; font-size: 12px; font-weight: 600; color: var(--text-muted); }
    .qr-tab.active { color: var(--accent); border-color: var(--accent); }
    .qr-shape-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 6px; }
    .qr-shape-card {
        cursor: pointer; padding: 6px; border-radius: 8px; border: 1.5px solid var(--border-glass);
        background: var(--bg-glass-hover); display: flex; align-items: center; justify-content: center;
        aspect-ratio: 1; transition: all .12s;
    }
    .qr-shape-card:hover { border-color: var(--accent); }
    .qr-shape-card.active { border-color: var(--accent); background: var(--c-primary-soft); box-shadow: 0 0 0 2px var(--c-primary-soft); }
    .qr-shape-card svg { width: 80%; height: 80%; }
    .qr-frame-card { aspect-ratio: 1.2; }
    .qr-cat-pill {
        padding: 4px 10px; font-size: 11px; font-weight: 600; border-radius: 999px;
        cursor: pointer; border: 1px solid var(--border-glass); color: var(--text-muted);
        background: transparent; white-space: nowrap;
    }
    .qr-cat-pill.active { background: var(--accent); color: #fff; border-color: var(--accent); }
    .qr-shape-scroll { max-height: 280px; overflow-y: auto; padding-right: 4px; }
    .qr-shape-scroll::-webkit-scrollbar { width: 6px; }
    .qr-shape-scroll::-webkit-scrollbar-thumb { background: var(--border-glass); border-radius: 3px; }
    details > summary { list-style: none; cursor: pointer; }
    details > summary::-webkit-details-marker { display: none; }
    .qr-section { padding: 14px; border-radius: 10px; border: 1px solid var(--border-glass); background: var(--bg-glass-hover); }
    .qr-template-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .qr-template-card {
        cursor: pointer; padding: 8px; border-radius: 10px;
        border: 1.5px solid var(--border-glass); background: var(--bg-glass-hover);
        display: flex; flex-direction: column; gap: 6px; transition: all .12s;
    }
    .qr-template-card:hover { border-color: var(--accent); transform: translateY(-1px); }
    .qr-template-card.active { border-color: var(--accent); box-shadow: 0 0 0 2px var(--c-primary-soft); }
    .qr-template-thumb {
        aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
        border-radius: 6px; overflow: hidden; background: #fff;
    }
    .qr-template-thumb svg { width: 100%; height: 100%; display: block; }
    .qr-template-meta { display: flex; flex-direction: column; gap: 1px; }
    .qr-template-name { font-size: 12px; font-weight: 700; color: var(--text-primary); line-height: 1.1; }
    .qr-template-tag { font-size: 10px; color: var(--text-muted); line-height: 1.2; }
</style>

<div class="max-w-[1500px] mx-auto" x-data="qrBuilder()" x-init="init()" x-cloak>
    <form method="POST" action="{{ $action }}" id="qrForm">
        @csrf
        @if($isEdit) @method('PUT') @endif

        {{-- Top bar --}}
        <div class="card-premium p-4 mb-4 flex flex-wrap items-center gap-3">
            <a href="{{ route('user.qr-codes.index') }}" class="text-sm" style="color: var(--text-muted);">
                <i class="fas fa-arrow-left"></i>
            </a>
            <input type="text" name="name" required maxlength="160" placeholder="Untitled QR code"
                   value="{{ old('name', $isEdit ? $qrCode->name : '') }}"
                   class="flex-1 min-w-[200px] px-3 py-2 text-base font-bold rounded-lg outline-none"
                   style="background: transparent; border: 1px solid transparent; color: var(--text-primary);">
            <select name="project_id" class="px-3 py-2 text-sm rounded-lg outline-none"
                    style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                <option value="">No project</option>
                @foreach($projects as $p)
                    <option value="{{ $p->id }}" @selected(old('project_id', $isEdit ? $qrCode->project_id : '') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <button type="button" @click="downloadPng()" class="px-3 py-2 text-sm rounded-lg" style="background: var(--bg-glass-hover); color: var(--text-primary);"><i class="fas fa-download"></i> PNG</button>
            <button type="button" @click="downloadSvg()" class="px-3 py-2 text-sm rounded-lg" style="background: var(--bg-glass-hover); color: var(--text-primary);"><i class="fas fa-download"></i> SVG</button>
            <button type="submit" class="px-4 py-2 text-sm rounded-lg font-semibold" style="background: var(--accent); color: #fff;"><i class="fas fa-save"></i> Save</button>
        </div>

        @if($errors->any())
            <div class="card-premium p-3 mb-4" style="border-color: var(--c-danger); background: var(--c-danger-soft);">
                <ul class="text-xs" style="color: var(--c-danger);">@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <input type="hidden" name="type" :value="type">
        <input type="hidden" name="link_id" :value="linkId || ''">
        {{-- payload + design are serialized as JSON blobs (controller decodes them
             before validation) to sidestep Alpine x-for reactivity edge cases when
             new keys are added by user input. --}}
        <input type="hidden" name="payload_json" :value="JSON.stringify(payload || {})">
        <input type="hidden" name="design_json" :value="JSON.stringify(designForServer())">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            {{-- LEFT: type picker + content form --}}
            <div class="lg:col-span-4 space-y-4">
                <div class="card-premium p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Source</h3>
                        <label class="inline-flex items-center gap-1.5 text-xs cursor-pointer" style="color: var(--text-muted);">
                            <input type="checkbox" x-model="useExistingLink" class="w-3.5 h-3.5"> Use existing link
                        </label>
                    </div>
                    <div x-show="!useExistingLink">
                        <div class="text-[11px] font-semibold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Type</div>
                        <div class="grid grid-cols-4 gap-1.5 mb-4">
                            @foreach($types as $key => $info)
                                <button type="button" @click="setType('{{ $key }}')"
                                        class="qr-type-tab flex flex-col items-center gap-1 p-2 rounded-lg text-[10px]"
                                        :class="type === '{{ $key }}' ? 'active' : ''"
                                        style="border: 1px solid var(--border-glass); color: var(--text-secondary);">
                                    <i class="fas {{ $info['icon'] }} text-base"></i>
                                    <span>{{ $info['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                        @include('user.qr-codes._type-forms')
                    </div>
                    <div x-show="useExistingLink" x-cloak>
                        <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-secondary);">Link to encode</label>
                        @if($links->isEmpty())
                            <div class="text-xs p-3 rounded" style="background: var(--bg-glass-hover); color: var(--text-muted);">
                                You don't have any active links. <a href="{{ route('user.links.create') }}" style="color: var(--accent);">Create one</a> first.
                            </div>
                        @else
                            <select x-model.number="linkId" @change="resolveLinkPayload()"
                                    class="w-full px-3 py-2 text-sm rounded-lg outline-none"
                                    style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                                <option value="">— Choose a link —</option>
                                @foreach($links as $link)
                                    <option value="{{ $link->id }}">{{ $link->title ?: $link->alias }} (/{{ $link->alias }})</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] mt-2" style="color: var(--text-muted);">Scans count toward your link's analytics.</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- CENTER: live preview --}}
            <div class="lg:col-span-4">
                <div class="card-premium p-6 sticky top-4">
                    <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Live preview</h3>
                    <div class="flex items-center justify-center rounded-lg p-4 min-h-[420px]" style="background: var(--bg-glass-hover); border: 1px solid var(--border-glass);">
                        <div id="qrTarget" class="w-full max-w-[380px]"></div>
                    </div>
                    <div class="mt-3 text-center text-[11px] break-all" style="color: var(--text-muted);">
                        <span x-text="encodedPreview"></span>
                    </div>
                </div>
            </div>

            {{-- RIGHT: design panel with tabs --}}
            <div class="lg:col-span-4 space-y-4">
                <div class="card-premium p-2">
                    <div class="flex border-b" style="border-color: var(--border-glass);">
                        @foreach(['templates'=>'Templates','shapes'=>'Shapes','colors'=>'Colors','logos'=>'Logos','frames'=>'Frames','more'=>'More'] as $k => $lbl)
                            <button type="button" @click="tab = '{{ $k }}'"
                                    class="qr-tab" :class="tab === '{{ $k }}' ? 'active' : ''">{{ $lbl }}</button>
                        @endforeach
                    </div>

                    {{-- TEMPLATES tab --}}
                    <div x-show="tab === 'templates'" class="p-3 space-y-3"
                         x-data="templatesPicker({ presets: @js($presets) })" x-init="$nextTick(() => renderThumbs())">
                        <p class="text-[11px]" style="color: var(--text-muted);">
                            Pick a ready-made look. Your content and uploaded logos stay as-is.
                        </p>
                        <div class="qr-template-grid">
                            <template x-for="preset in presets" :key="preset.id">
                                <div class="qr-template-card"
                                     :class="activeId === preset.id ? 'active' : ''"
                                     @click="apply(preset)">
                                    <div class="qr-template-thumb" :id="'qr-tpl-thumb-' + preset.id"></div>
                                    <div class="qr-template-meta">
                                        <span class="qr-template-name" x-text="preset.name"></span>
                                        <span class="qr-template-tag" x-text="preset.tagline"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- SHAPES tab --}}
                    <div x-show="tab === 'shapes'" x-cloak class="p-3 space-y-4">
                        @php $sections = [
                            ['Dot shape', 'design.dot_style', $catalog['dots'], 'dot'],
                            ['Outer eye',  'design.corner_square_style', $catalog['outerEyes'], 'outer'],
                            ['Inner eye',  'design.corner_dot_style',    $catalog['innerEyes'], 'inner'],
                        ]; @endphp
                        @foreach($sections as [$label, $bind, $groups, $kind])
                            <div x-data="shapePicker({ kind: '{{ $kind }}', groups: @js($groups) })">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="text-[11px] font-semibold uppercase tracking-wider" style="color: var(--text-faint);">{{ $label }}</div>
                                    <input type="search" x-model="search" placeholder="Search…"
                                           class="px-2 py-1 text-[11px] rounded outline-none w-28"
                                           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                                </div>
                                <div class="flex gap-1.5 overflow-x-auto mb-2 pb-1">
                                    <template x-for="cat in cats" :key="cat">
                                        <button type="button" class="qr-cat-pill" :class="activeCat === cat ? 'active' : ''"
                                                @click="activeCat = cat" x-text="cat"></button>
                                    </template>
                                </div>
                                <div class="qr-shape-scroll qr-shape-grid">
                                    <template x-for="id in filtered()" :key="id">
                                        <div class="qr-shape-card" :class="{{ $bind }} === id ? 'active' : ''"
                                             :title="id" @click="{{ $bind }} = id; render()" x-html="thumb(id)"></div>
                                    </template>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- COLORS tab --}}
                    <div x-show="tab === 'colors'" x-cloak class="p-3 space-y-3">
                        <div class="qr-section">
                            <label class="block text-[11px] font-semibold mb-1.5" style="color: var(--text-secondary);">Foreground (dots)</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="design.fg_color" @input="syncFg(); render()" class="w-10 h-10 rounded cursor-pointer">
                                <input type="text" x-model="design.fg_color" @input="render()" class="flex-1 px-2 py-1.5 text-xs font-mono rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                            </div>
                            <div class="mt-2">
                                <x-qr-gradient-controls field="gradient" label="Dot gradient" />
                            </div>
                        </div>
                        <div class="qr-section">
                            <label class="block text-[11px] font-semibold mb-1.5" style="color: var(--text-secondary);">Background</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="design.bg_color" @input="render()" class="w-10 h-10 rounded cursor-pointer" :disabled="design.transparent_bg">
                                <input type="text" x-model="design.bg_color" @input="render()" class="flex-1 px-2 py-1.5 text-xs font-mono rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);" :disabled="design.transparent_bg">
                            </div>
                            <label class="inline-flex items-center gap-1.5 text-[11px] mt-1.5 cursor-pointer" style="color: var(--text-muted);">
                                <input type="checkbox" x-model="design.transparent_bg" @change="render()"> Transparent background
                            </label>
                            <div class="mt-2">
                                <x-qr-gradient-controls field="bg_gradient" label="Background gradient" />
                            </div>
                        </div>
                        <div class="qr-section">
                            <label class="block text-[11px] font-semibold mb-1.5" style="color: var(--text-secondary);">Outer eye color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="design.corner_square_color" @input="render()" class="w-10 h-10 rounded cursor-pointer">
                                <input type="text" x-model="design.corner_square_color" @input="render()" class="flex-1 px-2 py-1.5 text-xs font-mono rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                            </div>
                            <div class="mt-2">
                                <x-qr-gradient-controls field="eye_outer_gradient" label="Outer eye gradient" />
                            </div>
                        </div>
                        <div class="qr-section">
                            <label class="block text-[11px] font-semibold mb-1.5" style="color: var(--text-secondary);">Inner eye color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="design.corner_dot_color" @input="render()" class="w-10 h-10 rounded cursor-pointer">
                                <input type="text" x-model="design.corner_dot_color" @input="render()" class="flex-1 px-2 py-1.5 text-xs font-mono rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                            </div>
                            <div class="mt-2">
                                <x-qr-gradient-controls field="eye_inner_gradient" label="Inner eye gradient" />
                            </div>
                        </div>
                    </div>

                    {{-- LOGOS tab --}}
                    <div x-show="tab === 'logos'" x-cloak class="p-3 space-y-3">
                        @foreach(['logo_background'=>'Background image','logo_center'=>'Center logo','logo_foreground'=>'Foreground sticker'] as $field => $label)
                            <div class="qr-section">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="text-[11px] font-semibold uppercase tracking-wider" style="color: var(--text-secondary);">{{ $label }}</label>
                                    <label class="inline-flex items-center gap-1.5 text-[11px] cursor-pointer" style="color: var(--text-muted);">
                                        <input type="checkbox" x-model="design.{{ $field }}.show" @change="render()"> Show
                                    </label>
                                </div>
                                <div class="space-y-2">
                                    <input type="url" x-model="design.{{ $field }}.url" @input="render()" placeholder="https://… image URL"
                                           class="w-full px-2 py-1.5 text-xs rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                                    <div class="flex items-center gap-2">
                                        <label class="px-2 py-1.5 text-[11px] rounded cursor-pointer" style="background: var(--bg-glass-hover); color: var(--text-primary); border: 1px solid var(--border-glass);">
                                            <i class="fas fa-upload"></i> Upload
                                            <input type="file" class="hidden" accept="image/*" @change="uploadLogo($event, '{{ $field }}')">
                                        </label>
                                        <button type="button" x-show="design.{{ $field }}.url"
                                                @click="design.{{ $field }}.url = null; design.{{ $field }}.show = false; render()"
                                                class="text-[11px]" style="color: var(--c-danger);"><i class="fas fa-times"></i> Remove</button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-[11px]" style="color: var(--text-muted);">
                                        <label>Size <span class="font-mono" x-text="Math.round(design.{{ $field }}.size * 100) + '%'"></span>
                                            <input type="range" min="0.02" max="1" step="0.01" x-model.number="design.{{ $field }}.size" @input="render()" class="w-full"></label>
                                        <label>Opacity <span class="font-mono" x-text="Math.round(design.{{ $field }}.opacity * 100) + '%'"></span>
                                            <input type="range" min="0" max="1" step="0.05" x-model.number="design.{{ $field }}.opacity" @input="render()" class="w-full"></label>
                                        <label>X <span class="font-mono" x-text="Math.round(design.{{ $field }}.x) + '%'"></span>
                                            <input type="range" min="0" max="100" step="1" x-model.number="design.{{ $field }}.x" @input="render()" class="w-full"></label>
                                        <label>Y <span class="font-mono" x-text="Math.round(design.{{ $field }}.y) + '%'"></span>
                                            <input type="range" min="0" max="100" step="1" x-model.number="design.{{ $field }}.y" @input="render()" class="w-full"></label>
                                        <label class="col-span-2">Rotation <span class="font-mono" x-text="design.{{ $field }}.rotation + '°'"></span>
                                            <input type="range" min="-180" max="180" step="5" x-model.number="design.{{ $field }}.rotation" @input="render()" class="w-full"></label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <label class="inline-flex items-center gap-1.5 text-[11px] cursor-pointer" style="color: var(--text-muted);">
                            <input type="checkbox" x-model="design.hide_dots_behind_logo" @change="render()"> Hide dots behind center logo (recommended)
                        </label>
                    </div>

                    {{-- FRAMES tab --}}
                    <div x-show="tab === 'frames'" x-cloak class="p-3 space-y-3"
                         x-data="framePicker({ groups: @js($catalog['frames']) })">
                        <div class="flex items-center justify-between mb-2">
                            <input type="search" x-model="search" placeholder="Search frames…"
                                   class="px-2 py-1 text-[11px] rounded outline-none flex-1"
                                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        </div>
                        <div class="flex gap-1.5 overflow-x-auto mb-2 pb-1">
                            <template x-for="cat in cats" :key="cat">
                                <button type="button" class="qr-cat-pill" :class="activeCat === cat ? 'active' : ''"
                                        @click="activeCat = cat" x-text="cat"></button>
                            </template>
                        </div>
                        <div class="qr-shape-scroll qr-shape-grid" style="grid-template-columns: repeat(3, 1fr);">
                            <template x-for="id in filtered()" :key="id">
                                <div class="qr-shape-card qr-frame-card" :class="design.frame.template === id ? 'active' : ''"
                                     :title="id" @click="design.frame.template = id; render()" x-html="thumb(id)"></div>
                            </template>
                        </div>
                        <div x-show="design.frame.template !== 'none'" class="space-y-2 pt-2 border-t" style="border-color: var(--border-glass);">
                            <input type="text" x-model="design.frame.text" @input="render()" maxlength="60" placeholder="Frame text"
                                   class="w-full px-2 py-1.5 text-xs rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                            <select x-model="design.frame.font" @change="render()" class="w-full px-2 py-1.5 text-xs rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                                @foreach($catalog['fonts'] as $f)
                                    <option value="{{ $f }}">{{ $f }}</option>
                                @endforeach
                            </select>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="text-[11px]" style="color: var(--text-secondary);">Frame fill
                                    <input type="color" x-model="design.frame.bg_color" @input="render()" class="w-full h-8 rounded cursor-pointer">
                                </label>
                                <label class="text-[11px]" style="color: var(--text-secondary);">Text color
                                    <input type="color" x-model="design.frame.text_color" @input="render()" class="w-full h-8 rounded cursor-pointer">
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- MORE tab --}}
                    <div x-show="tab === 'more'" x-cloak class="p-3 space-y-3">
                        <div class="qr-section">
                            <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">Error correction</label>
                            <select x-model="design.error_correction" @change="render()" class="w-full px-2 py-1.5 text-xs rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                                <option value="L">L — Low (~7%)</option>
                                <option value="M">M — Medium (~15%)</option>
                                <option value="Q">Q — Quartile (~25%)</option>
                                <option value="H">H — High (~30%, recommended for logos)</option>
                            </select>
                            <p x-show="(design.logo_center && design.logo_center.show) && design.error_correction !== 'H'"
                               class="text-[10px] mt-1.5" style="color: var(--c-warning, #d97706);">
                                <i class="fas fa-info-circle"></i>
                                A center logo is in use — bump error correction to <strong>H</strong> for the most reliable scans. (Not auto-forced; you decide.)
                            </p>
                        </div>
                        <div class="qr-section">
                            <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">Output size (px) <span x-text="design.size" class="font-mono"></span></label>
                            <div class="grid grid-cols-4 gap-1 mb-2">
                                @foreach(['S'=>400,'M'=>800,'L'=>1200,'XL'=>2000] as $lbl => $px)
                                    <button type="button" @click="design.size = {{ $px }}"
                                            class="px-2 py-1.5 text-[11px] rounded"
                                            :class="design.size === {{ $px }} ? 'active' : ''"
                                            style="border: 1px solid var(--border-glass); color: var(--text-primary); background: var(--bg-glass-hover);">
                                        {{ $lbl }} <span class="opacity-60">{{ $px }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <input type="range" min="200" max="2000" step="50" x-model.number="design.size" class="w-full">
                            <p class="text-[10px] mt-1" style="color: var(--text-faint);">
                                ≈ <span x-text="Math.round(design.size / 96 * 25.4)"></span>mm at 96 DPI ·
                                ≈ <span x-text="Math.round(design.size / 300 * 25.4)"></span>mm at 300 DPI (print)
                            </p>

                            <label class="block text-[11px] font-semibold mb-1 mt-3" style="color: var(--text-secondary);">Quiet zone (modules)</label>
                            <div class="flex items-center gap-2">
                                <input type="range" min="0" max="20" step="1" x-model.number="design.margin" @input="render()" class="flex-1">
                                <input type="number" min="0" max="20" step="1" x-model.number="design.margin" @input="render()"
                                       class="w-16 px-2 py-1 text-xs rounded outline-none text-center font-mono"
                                       style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                            </div>
                            <p class="text-[10px] mt-1" style="color: var(--text-faint);">Spec recommends ≥ 4 modules of clear space around the QR.</p>
                        </div>
                        <div class="qr-section">
                            <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">Rotation</label>
                            <div class="grid grid-cols-4 gap-1">
                                @foreach([0,90,180,270] as $r)
                                    <button type="button" @click="design.qr_rotation = {{ $r }}; render()"
                                            class="px-2 py-1.5 text-[11px] rounded" :class="design.qr_rotation === {{ $r }} ? 'active' : ''"
                                            style="border: 1px solid var(--border-glass); color: var(--text-primary); background: var(--bg-glass-hover);">{{ $r }}°</button>
                                @endforeach
                            </div>
                            <label class="inline-flex items-center gap-1.5 text-[11px] mt-3 cursor-pointer" style="color: var(--text-muted);">
                                <input type="checkbox" x-model="design.drop_shadow" @change="render()"> Drop shadow
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- qrcode-generator from CDN; QrStudio engine reads window.qrcode --}}
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script src="{{ asset('js/qr-studio/engine.js') }}?v={{ filemtime(public_path('js/qr-studio/engine.js')) }}"></script>

<script>
function shapePicker({ kind, groups }) {
    return {
        groups, kind,
        cats: ['All', ...Object.keys(groups)],
        activeCat: 'All',
        search: '',
        all() { return Object.values(this.groups).flat(); },
        filtered() {
            const list = this.activeCat === 'All' ? this.all() : (this.groups[this.activeCat] || []);
            const q = this.search.trim().toLowerCase();
            return q ? list.filter(id => id.toLowerCase().includes(q)) : list;
        },
        thumb(id) {
            if (!window.QrStudio) return '';
            const fg = (this.$root && this.$root.design && this.$root.design.fg_color) || '#0f172a';
            if (this.kind === 'dot')   return window.QrStudio.thumbDot(id, fg);
            if (this.kind === 'outer') return window.QrStudio.thumbOuter(id, fg);
            if (this.kind === 'inner') return window.QrStudio.thumbInner(id, fg);
            return '';
        },
    };
}
function templatesPicker({ presets }) {
    return {
        presets,
        get activeId() { return (this.$root && this.$root.lastPresetId) || null; },
        apply(preset) {
            if (this.$root && typeof this.$root.applyPreset === 'function') {
                this.$root.applyPreset(preset);
            }
        },
        renderThumbs() {
            if (!window.QrStudio) {
                // Engine still loading — try again next tick.
                setTimeout(() => this.renderThumbs(), 120);
                return;
            }
            const sample = 'https://1inme.app';
            this.presets.forEach(preset => {
                const el = document.getElementById('qr-tpl-thumb-' + preset.id);
                if (!el || el.dataset.rendered === '1') return;
                try {
                    const opts = this.previewOpts(preset.design, sample);
                    const result = window.QrStudio.render(opts);
                    el.innerHTML = result.svg;
                    el.dataset.rendered = '1';
                } catch (e) { /* ignore preview failure for one card */ }
            });
        },
        previewOpts(d, data) {
            const f = d.frame || {};
            return {
                data,
                errorCorrection: 'M',
                modulePx: 6,
                margin: 2,
                dotShape: d.dot_style,
                outerEyeShape: d.corner_square_style,
                innerEyeShape: d.corner_dot_style,
                fgColor: d.fg_color,
                bgColor: d.bg_color,
                transparentBg: !!d.transparent_bg,
                cornerSquareColor: d.corner_square_color,
                cornerDotColor: d.corner_dot_color,
                gradient: d.gradient,
                eyeOuterGradient: d.eye_outer_gradient,
                eyeInnerGradient: d.eye_inner_gradient,
                bgGradient: d.bg_gradient,
                logos: { background: null, center: null, foreground: null },
                hideDotsBehindLogo: false,
                qrRotation: 0,
                dropShadow: false,
                frame: { template: f.template || 'none', text: 'SCAN ME', font: f.font || 'Inter', bg_color: f.bg_color || '#000', text_color: f.text_color || '#fff' },
                fontFamily: f.font || 'Inter',
            };
        },
    };
}
function framePicker({ groups }) {
    return {
        groups,
        cats: ['All', ...Object.keys(groups)],
        activeCat: 'All',
        search: '',
        all() { return Object.values(this.groups).flat(); },
        filtered() {
            const list = this.activeCat === 'All' ? this.all() : (this.groups[this.activeCat] || []);
            const q = this.search.trim().toLowerCase();
            return q ? list.filter(id => id.toLowerCase().includes(q)) : list;
        },
        thumb(id) { return window.QrStudio ? window.QrStudio.thumbFrame(id) : ''; },
    };
}

function qrBuilder() {
    return {
        type: @js($currentType),
        useExistingLink: @js((bool) $linkId),
        linkId: @js($linkId),
        payload: @js((object) $payload),
        design: @js($design),
        encoded: '',
        encodedPreview: '',
        renderTimer: null,
        resolveTimer: null,
        tab: 'templates',
        lastResult: null,
        matrix: null,
        matrixKey: '',
        lastPresetId: null,

        init() {
            this.$watch('payload', () => this.scheduleResolve(), { deep: true });
            this.$watch('type', () => this.scheduleResolve());
            this.$watch('linkId', () => this.useExistingLink && this.resolveLinkPayload());
            this.$watch('useExistingLink', v => { if (!v) { this.linkId = null; this.scheduleResolve(); } });
            this.$watch('design', () => this.render(), { deep: true });
            this.scheduleResolve();
        },

        setType(t) { this.type = t; this.payload = {}; this.scheduleResolve(); },

        scheduleResolve() {
            clearTimeout(this.resolveTimer);
            this.resolveTimer = setTimeout(() => this.resolve(), 250);
        },

        async resolve() {
            try {
                const fd = new FormData();
                fd.append('type', this.type);
                fd.append('_token', '{{ csrf_token() }}');
                if (this.useExistingLink && this.linkId) fd.append('link_id', this.linkId);
                Object.entries(this.payload || {}).forEach(([k, v]) => fd.append(`payload[${k}]`, v ?? ''));
                const r = await fetch(@js(route('user.qr-codes.resolve')), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const j = await r.json();
                this.encoded = j.encoded || '';
                this.encodedPreview = this.encoded.length > 80 ? this.encoded.slice(0, 80) + '…' : this.encoded;
                this.render();
            } catch (e) { console.warn(e); }
        },

        async resolveLinkPayload() { await this.resolve(); },

        render() {
            clearTimeout(this.renderTimer);
            this.renderTimer = setTimeout(async () => {
                if (!window.QrStudio) return;
                const opts = this.engineOpts(this.encoded || 'preview');
                // Preload remote/uploaded logos as data URLs so the SVG (and any
                // PNG export from it) is fully self-contained and won't taint
                // the canvas with cross-origin pixels.
                try { await window.QrStudio.preloadLogos(opts); } catch (e) {}
                // Cache the QR matrix and only rebuild it when the inputs that
                // actually change the bit pattern change (data + EC level).
                // Decoration-only edits (shape, color, frame, logo, margin…)
                // skip the encoder entirely and just re-skin the cached matrix.
                const key = (opts.data || '') + '|' + (opts.errorCorrection || 'M');
                if (!this.matrix || this.matrixKey !== key) {
                    this.matrix = window.QrStudio.buildMatrix(opts);
                    this.matrixKey = key;
                }
                const result = window.QrStudio.renderFromMatrix(this.matrix, opts);
                this.lastResult = result;
                const t = document.getElementById('qrTarget');
                if (t) t.innerHTML = result.svg;
            }, 60);
        },

        engineOpts(data) {
            const d = this.design;
            return {
                data,
                errorCorrection: d.error_correction,
                modulePx: 10,
                margin: d.margin,
                dotShape: d.dot_style,
                outerEyeShape: d.corner_square_style,
                innerEyeShape: d.corner_dot_style,
                fgColor: d.fg_color,
                bgColor: d.bg_color,
                transparentBg: !!d.transparent_bg,
                cornerSquareColor: d.corner_square_color,
                cornerDotColor: d.corner_dot_color,
                gradient: d.gradient,
                eyeOuterGradient: d.eye_outer_gradient,
                eyeInnerGradient: d.eye_inner_gradient,
                bgGradient: d.bg_gradient,
                logos: { background: d.logo_background, center: d.logo_center, foreground: d.logo_foreground },
                hideDotsBehindLogo: !!d.hide_dots_behind_logo,
                qrRotation: d.qr_rotation || 0,
                dropShadow: !!d.drop_shadow,
                frame: d.frame,
                fontFamily: (d.frame && d.frame.font) || 'Inter',
            };
        },

        applyPreset(preset) {
            if (!preset || !preset.design) return;
            const PRESERVE = new Set(['logo_center','logo_background','logo_foreground']);
            Object.entries(preset.design).forEach(([k, v]) => {
                if (PRESERVE.has(k)) return;
                if (k === 'frame' && v && typeof v === 'object') {
                    // Keep user's existing frame text if present.
                    const existingText = (this.design.frame && this.design.frame.text) || 'SCAN ME';
                    this.design.frame = { ...this.design.frame, ...v, text: existingText };
                    return;
                }
                if (v && typeof v === 'object' && !Array.isArray(v)) {
                    this.design[k] = { ...(this.design[k] || {}), ...v };
                } else {
                    this.design[k] = v;
                }
            });
            this.lastPresetId = preset.id;
            this.render();
        },

        syncFg() {
            if (this.design.corner_square_color === '#071437' || !this.design.corner_square_color) this.design.corner_square_color = this.design.fg_color;
            if (this.design.corner_dot_color === '#071437' || !this.design.corner_dot_color) this.design.corner_dot_color = this.design.fg_color;
        },

        designForServer() {
            // Send entire design object as nested keys via JSON (server decodes design_json into design array)
            return this.design;
        },

        async uploadLogo(ev, slot) {
            const file = ev.target.files && ev.target.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('logo', file);
            fd.append('slot', slot.replace('logo_',''));
            fd.append('_token', '{{ csrf_token() }}');
            try {
                const r = await fetch(@js(route('user.qr-codes.upload-logo')), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const j = await r.json();
                if (r.ok && j.url) {
                    this.design[slot].url = j.url;
                    this.design[slot].show = true;
                    this.render();
                } else {
                    alert(j.error || 'Upload failed');
                }
            } catch (e) { alert('Upload failed: ' + e.message); }
            ev.target.value = '';
        },

        async _renderForExport() {
            // Force a fresh, fully-embedded render right before exporting so
            // that any logos picked or pasted moments ago are inlined as data
            // URLs (PNG canvases would otherwise taint on cross-origin images).
            const opts = this.engineOpts(this.encoded || 'preview');
            const r = await window.QrStudio.preloadLogos(opts);
            const result = window.QrStudio.render(opts);
            this.lastResult = result;
            return { result, preload: r };
        },
        async downloadPng() {
            try {
                const { result, preload } = await this._renderForExport();
                if (!preload.ok) alert('Some logos could not be embedded for download (CORS): ' + Object.keys(preload.errors).join(', '));
                const target = Math.max(this.design.size || 800, 400);
                const scale = target / result.width;
                const dataUrl = await window.QrStudio.toPngDataUrl(result.svg, result.width, result.height, Math.max(1, scale));
                window.QrStudio.downloadDataUrl(dataUrl, 'qr-code.png');
            } catch (e) { alert('PNG download failed: ' + e.message); }
        },
        async downloadSvg() {
            try {
                const { result, preload } = await this._renderForExport();
                if (!preload.ok) alert('Some logos could not be embedded for download (CORS): ' + Object.keys(preload.errors).join(', '));
                window.QrStudio.downloadSvg(result.svg, 'qr-code.svg');
            } catch (e) { alert('SVG download failed: ' + e.message); }
        },
    };
}
</script>
@endsection
