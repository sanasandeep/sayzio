@extends('admin.layouts.app')
@section('title', 'Block Defaults: ' . $type)
@section('page-title', 'Block Defaults: ' . $type)

@section('content')
@php
    $layoutFields   = ['display_mode', 'grid_span'];
    $spacingFields  = ['padding', 'padding_top', 'padding_bottom', 'padding_left', 'padding_right', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right'];
    $typographyFields = ['font_family', 'font_size', 'font_weight', 'font_style', 'text_color'];
    $bgFields       = ['bg_color', 'bg_image', 'bg_opacity'];
    $borderFields   = ['border_style', 'border_width', 'border_radius', 'border_color'];
    $shadowFields   = ['shadow_preset', 'glass_preset', 'effect'];

    $hasLayout     = !empty(array_intersect_key($adminOverride['style'] ?? [], array_flip($layoutFields)));
    $hasSpacing    = !empty(array_intersect_key($adminOverride['style'] ?? [], array_flip($spacingFields)));
    $hasTypography = !empty(array_intersect_key($adminOverride['style'] ?? [], array_flip($typographyFields)));
    $hasBg         = !empty(array_intersect_key($adminOverride['style'] ?? [], array_flip($bgFields)));
    $hasBorder     = !empty(array_intersect_key($adminOverride['style'] ?? [], array_flip($borderFields)));
    $hasShadow     = !empty(array_intersect_key($adminOverride['style'] ?? [], array_flip($shadowFields)));
@endphp

<div x-data="{
    styleData: @js($adminOverride['style'] ?? []),
    contentJson: @js(!empty($adminOverride['content']) ? json_encode($adminOverride['content'], JSON_PRETTY_PRINT) : ''),
    systemContent: @js($systemContent),
    systemStyle: @js($systemStyle),
    startBlank: {{ $startBlank ? 'true' : 'false' }},
    structuralKeys: @js(\App\Modules\User\Support\BlockDefaults::structuralContentKeys()),
    scalarKeys: @js($scalarContentKeys),
    arrayMeta: @js($arrayContentKeys),
    contentData: @js((object) ($adminOverride['content'] ?? [])),
    syncing: false,
    open: {
        layout:     {{ $hasLayout     ? 'true' : 'true' }},
        spacing:    {{ $hasSpacing    ? 'true' : 'false' }},
        typography: {{ $hasTypography ? 'true' : 'false' }},
        bg:         {{ $hasBg         ? 'true' : 'false' }},
        border:     {{ $hasBorder     ? 'true' : 'false' }},
        shadow:     {{ $hasShadow     ? 'true' : 'false' }},
        contentFields: {{ (!empty($adminOverride['content']) || $startBlank) ? 'true' : 'true' }},
        content:    false,
    },
    resetJson() {
        this.contentJson = JSON.stringify(this.systemContent, null, 2);
    },
    /* ── Friendly content fields ↔ JSON sync ─────────────────────────
       The JSON textarea is the single submitted source of truth; the
       friendly inputs read/write keys inside it. An explicit '' written
       by a field is a real blank override; deleting the key falls back
       to the system default. */
    baseValue(key) {
        const sys = this.systemContent[key];
        if (this.startBlank && !this.structuralKeys.includes(key)) {
            if (typeof sys === 'string') return '';
            if (Array.isArray(sys)) return [];
        }
        return sys;
    },
    fieldValue(key) {
        return Object.prototype.hasOwnProperty.call(this.contentData, key)
            ? this.contentData[key]
            : this.baseValue(key);
    },
    fieldOverridden(key) {
        return Object.prototype.hasOwnProperty.call(this.contentData, key);
    },
    setField(key, val) {
        const sys = this.systemContent[key];
        if (typeof sys === 'number' && val !== '' && !isNaN(Number(val))) {
            this.contentData[key] = Number(val);
        } else if (typeof sys === 'boolean') {
            if (val === '') { delete this.contentData[key]; }
            else { this.contentData[key] = (val === 'true' || val === '1'); }
        } else {
            this.contentData[key] = val;
        }
        this.writeJsonFromData();
    },
    resetField(key) {
        delete this.contentData[key];
        this.writeJsonFromData();
    },
    /* ── Repeatable list editors (array-of-strings / array-of-objects) ── */
    listValue(key) {
        const v = this.fieldValue(key);
        return Array.isArray(v) ? v : [];
    },
    ensureListOverride(key) {
        if (!this.fieldOverridden(key) || !Array.isArray(this.contentData[key])) {
            /* Reassign the whole object (not just add the key): Alpine's
               reactivity does not track hasOwnProperty/key-addition, so a
               plain `contentData[key] = ...` on a brand-new key would leave
               the x-for rows rendering the stale system default. */
            const copy = JSON.parse(JSON.stringify(this.listValue(key)));
            this.contentData = { ...this.contentData, [key]: copy };
        }
        return this.contentData[key];
    },
    listSetString(key, idx, val) {
        const arr = this.ensureListOverride(key);
        arr[idx] = val;
        this.writeJsonFromData();
    },
    listSetField(key, idx, field, val) {
        const arr = this.ensureListOverride(key);
        if (!arr[idx] || typeof arr[idx] !== 'object') arr[idx] = {};
        const ftype = (this.arrayMeta[key].fields || {})[field] || 'string';
        if (ftype === 'number') {
            arr[idx][field] = (val === '' || isNaN(Number(val))) ? val : Number(val);
        } else if (ftype === 'boolean') {
            arr[idx][field] = !!val;
        } else {
            arr[idx][field] = val;
        }
        this.writeJsonFromData();
    },
    listAdd(key) {
        const arr = this.ensureListOverride(key);
        const meta = this.arrayMeta[key];
        if (meta.kind === 'strings') {
            arr.push('');
        } else {
            const row = {};
            Object.entries(meta.fields || {}).forEach(([f, t]) => {
                row[f] = t === 'boolean' ? false : (t === 'number' ? 0 : '');
            });
            arr.push(row);
        }
        this.writeJsonFromData();
    },
    listRemove(key, idx) {
        const arr = this.ensureListOverride(key);
        arr.splice(idx, 1);
        this.writeJsonFromData();
    },
    listMove(key, idx, dir) {
        const arr = this.ensureListOverride(key);
        const to = idx + dir;
        if (to < 0 || to >= arr.length) return;
        const [row] = arr.splice(idx, 1);
        arr.splice(to, 0, row);
        this.writeJsonFromData();
    },
    /* ── Drag-and-drop reordering (rows within one list) ── */
    listDrag: { key: null, from: null },
    listDragStart(key, idx, e) {
        this.listDrag = { key, from: idx };
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', String(idx)); } catch (err) { /* IE/edge cases */ }
        }
    },
    listDragOver(key, e) {
        if (this.listDrag.key !== key) return;
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
    },
    listDrop(key, idx) {
        if (this.listDrag.key !== key || this.listDrag.from === null) return;
        const from = this.listDrag.from;
        this.listDragEnd();
        if (from === idx) return;
        const arr = this.ensureListOverride(key);
        if (from < 0 || from >= arr.length || idx < 0 || idx >= arr.length) return;
        const [row] = arr.splice(from, 1);
        arr.splice(idx, 0, row);
        this.writeJsonFromData();
    },
    listDragEnd() {
        this.listDrag = { key: null, from: null };
    },
    writeJsonFromData() {
        this.syncing = true;
        this.contentJson = Object.keys(this.contentData).length
            ? JSON.stringify(this.contentData, null, 2)
            : '';
        this.$nextTick(() => { this.syncing = false; });
    },
    readDataFromJson() {
        if (this.syncing) return;
        const raw = this.contentJson.trim();
        if (raw === '') { this.contentData = {}; return; }
        try {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                this.contentData = parsed;
            }
        } catch (e) { /* invalid JSON: keep last good contentData */ }
    },
    getStyle(field) { return this.styleData[field] ?? ''; },
    setStyle(field, val) {
        if (val === '') { delete this.styleData[field]; } else { this.styleData[field] = val; }
    },
    hasOverrideIn(fields) {
        return fields.some(f => this.styleData[f] !== undefined && this.styleData[f] !== '');
    },
    clearSection(fields) {
        fields.forEach(f => { delete this.styleData[f]; });
        if (fields.includes('font_family')) {
            window.dispatchEvent(new CustomEvent('font-picker-set', { detail: { pickerId: 'bdFontFamily', value: '' } }));
        }
    },
    get effective() {
        return Object.assign({}, this.systemStyle, this.styleData);
    },
    previewTimer: null,
    previewLoading: false,
    jsonInvalid: false,
    schedulePreview() {
        clearTimeout(this.previewTimer);
        this.previewTimer = setTimeout(() => this.fetchPreview(), 400);
    },
    async fetchPreview() {
        const content = this.contentJson.trim();
        this.jsonInvalid = false;
        if (content !== '') {
            try { JSON.parse(content); } catch (e) { this.jsonInvalid = true; return; }
        }
        this.previewLoading = true;
        try {
            const body = new URLSearchParams();
            Object.entries(this.styleData).forEach(([k, v]) => {
                if (v !== undefined && v !== null && v !== '') body.append('style[' + k + ']', v);
            });
            body.append('content_json', content);
            if (this.startBlank) body.append('start_blank', '1');
            const res = await fetch(@js(route('admin.block-defaults.preview', $type)), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'text/html' },
                body,
            });
            if (res.ok) {
                this.$refs.previewFrame.srcdoc = await res.text();
            } else if (res.status === 422) {
                this.jsonInvalid = true;
            }
        } catch (e) {
        } finally {
            this.previewLoading = false;
        }
    },
}"
x-init="fetchPreview(); $watch('styleData', () => schedulePreview()); $watch('contentJson', () => { readDataFromJson(); schedulePreview(); }); $watch('startBlank', () => schedulePreview())">

    {{-- Back link --}}
    <div class="mb-4">
        <a href="{{ route('admin.block-defaults.index') }}" class="text-sm" style="color: var(--text-dimmed);">
            <i class="fas fa-arrow-left mr-1"></i> All block types
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 text-sm ak-green">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-3 rounded-xl border border-red-500/30 bg-red-500/10 text-red-300 text-sm ak-red">
            @foreach($errors->all() as $err)
                <div><i class="fas fa-exclamation-circle mr-1"></i> {{ $err }}</div>
            @endforeach
        </div>
    @endif

    {{-- Header --}}
    <div class="glass rounded-2xl border border-white/10 p-5 mb-5">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90 font-mono ak-strong">{{ $type }}</h2>
                <p class="text-sm text-white/50 mt-1 ak-muted">
                    Overrides are merged on top of system defaults for every <strong>new</strong> block of this type.
                    Existing blocks are not affected.
                </p>
            </div>
            @if($hasOverride)
                <form method="POST" action="{{ route('admin.block-defaults.reset', $type) }}"
                      onsubmit="return confirm('Reset \u00ab {{ $type }} \u00bb to system defaults?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all"
                            style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5;">
                        <i class="fas fa-rotate-left mr-1"></i> Reset all to system defaults
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Two-pane layout --}}
    <form method="POST" action="{{ route('admin.block-defaults.update', $type) }}">
        @csrf
        @method('PUT')

        <div class="bd-two-pane">

            {{-- ═══════════════════════════ LEFT: settings ═══════════════════════════ --}}
            <div class="bd-form-col">

                {{-- ── Layout & Display ── --}}
                <div class="bd-card mb-3">
                    <button type="button" class="bd-section-hd" @click="open.layout = !open.layout">
                        <div class="bd-section-hd-left">
                            <i class="fas fa-table-columns bd-section-icon"></i>
                            <span class="bd-section-title">Layout &amp; Display</span>
                            <span x-show="hasOverrideIn(@js($layoutFields))" x-cloak class="bd-badge">overrides</span>
                        </div>
                        <div class="bd-section-hd-right">
                            <span role="button" tabindex="0"
                                    x-show="hasOverrideIn(@js($layoutFields))" x-cloak
                                    @click.stop="clearSection(@js($layoutFields))"
                                    @keydown.enter.prevent.stop="clearSection(@js($layoutFields))"
                                    class="bd-clear-btn" title="Clear section overrides">
                                <i class="fas fa-xmark"></i> clear
                                    </span>
                            <i class="fas fa-chevron-down bd-chevron" :class="open.layout && 'rotate-180'"></i>
                        </div>
                    </button>
                    <div x-show="open.layout" x-collapse>
                        <div class="bd-body">
                            <div class="grid grid-cols-2 gap-3">
                                <label class="bd-label">
                                    Display mode
                                    <select name="style[display_mode]" class="bd-select"
                                            x-model="styleData.display_mode"
                                            @change="setStyle('display_mode', $event.target.value)">
                                        <option value="">system ({{ $systemStyle['display_mode'] ?? 'card' }})</option>
                                        <option value="card">card</option>
                                        <option value="content">content</option>
                                        <option value="overlay">overlay</option>
                                        <option value="flat">flat</option>
                                    </select>
                                </label>
                                <label class="bd-label">
                                    Grid span (1–12)
                                    <input type="number" name="style[grid_span]" min="1" max="12"
                                           class="bd-input"
                                           :value="getStyle('grid_span')"
                                           @input="setStyle('grid_span', $event.target.value)"
                                           placeholder="{{ $systemStyle['grid_span'] ?? 'auto' }}">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Spacing ── --}}
                <div class="bd-card mb-3">
                    <button type="button" class="bd-section-hd" @click="open.spacing = !open.spacing">
                        <div class="bd-section-hd-left">
                            <i class="fas fa-expand bd-section-icon"></i>
                            <span class="bd-section-title">Spacing</span>
                            <span x-show="hasOverrideIn(@js($spacingFields))" x-cloak class="bd-badge">overrides</span>
                        </div>
                        <div class="bd-section-hd-right">
                            <span role="button" tabindex="0"
                                    x-show="hasOverrideIn(@js($spacingFields))" x-cloak
                                    @click.stop="clearSection(@js($spacingFields))"
                                    @keydown.enter.prevent.stop="clearSection(@js($spacingFields))"
                                    class="bd-clear-btn" title="Clear section overrides">
                                <i class="fas fa-xmark"></i> clear
                                    </span>
                            <i class="fas fa-chevron-down bd-chevron" :class="open.spacing && 'rotate-180'"></i>
                        </div>
                    </button>
                    <div x-show="open.spacing" x-collapse>
                        <div class="bd-body">
                            <p class="bd-hint mb-3">All values in px. Leave blank to use system default.</p>
                            <div class="mb-3">
                                <label class="bd-label">
                                    Padding, all sides shorthand
                                    <input type="text" name="style[padding]" class="bd-input"
                                           :value="getStyle('padding')"
                                           @input="setStyle('padding', $event.target.value)"
                                           placeholder="{{ $systemStyle['padding'] ?? 'e.g. 12' }}">
                                </label>
                            </div>
                            <div class="bd-spacer-grid mb-1">
                                <div class="bd-spacer-label">Padding</div>
                                @foreach(['padding_top' => 'Top', 'padding_bottom' => 'Bot', 'padding_left' => 'Left', 'padding_right' => 'Right'] as $pf => $pl)
                                    <label class="bd-label">
                                        {{ $pl }}
                                        <input type="text" name="style[{{ $pf }}]" class="bd-input bd-input-sm"
                                               :value="getStyle('{{ $pf }}')"
                                               @input="setStyle('{{ $pf }}', $event.target.value)"
                                               placeholder="{{ $systemStyle[$pf] ?? '' }}">
                                    </label>
                                @endforeach
                            </div>
                            <div class="bd-spacer-grid">
                                <div class="bd-spacer-label">Margin</div>
                                @foreach(['margin_top' => 'Top', 'margin_bottom' => 'Bot', 'margin_left' => 'Left', 'margin_right' => 'Right'] as $mf => $ml)
                                    <label class="bd-label">
                                        {{ $ml }}
                                        <input type="text" name="style[{{ $mf }}]" class="bd-input bd-input-sm"
                                               :value="getStyle('{{ $mf }}')"
                                               @input="setStyle('{{ $mf }}', $event.target.value)"
                                               placeholder="{{ $systemStyle[$mf] ?? '' }}">
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Typography ── --}}
                <div class="bd-card mb-3">
                    <button type="button" class="bd-section-hd" @click="open.typography = !open.typography">
                        <div class="bd-section-hd-left">
                            <i class="fas fa-font bd-section-icon"></i>
                            <span class="bd-section-title">Typography</span>
                            <span x-show="hasOverrideIn(@js($typographyFields))" x-cloak class="bd-badge">overrides</span>
                        </div>
                        <div class="bd-section-hd-right">
                            <span role="button" tabindex="0"
                                    x-show="hasOverrideIn(@js($typographyFields))" x-cloak
                                    @click.stop="clearSection(@js($typographyFields))"
                                    @keydown.enter.prevent.stop="clearSection(@js($typographyFields))"
                                    class="bd-clear-btn" title="Clear section overrides">
                                <i class="fas fa-xmark"></i> clear
                                    </span>
                            <i class="fas fa-chevron-down bd-chevron" :class="open.typography && 'rotate-180'"></i>
                        </div>
                    </button>
                    <div x-show="open.typography" x-collapse>
                        <div class="bd-body">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bd-label" style="grid-column: span 2;">
                                    Font family
                                    {{-- Shared searchable picker; the hidden input keeps the
                                         style[font_family] form name. Its change event bubbles
                                         up here to sync the Alpine style state. --}}
                                    <div class="mt-1" @change="if ($event.target.name === 'style[font_family]') setStyle('font_family', $event.target.value)">
                                        @include('user.links.partials.font-picker', [
                                            'name' => 'style[font_family]',
                                            'value' => $adminOverride['style']['font_family'] ?? '',
                                            'pickerId' => 'bdFontFamily',
                                            'allowInherit' => true,
                                            'hideCustomFonts' => true,
                                        ])
                                    </div>
                                </div>
                                <label class="bd-label">
                                    Font size (px)
                                    <input type="text" name="style[font_size]" class="bd-input"
                                           :value="getStyle('font_size')"
                                           @input="setStyle('font_size', $event.target.value)"
                                           placeholder="{{ $systemStyle['font_size'] ?? '' }}">
                                </label>
                                <label class="bd-label">
                                    Font weight
                                    <select name="style[font_weight]" class="bd-select"
                                            x-model="styleData.font_weight"
                                            @change="setStyle('font_weight', $event.target.value)">
                                        <option value="">system default</option>
                                        @foreach(['400','500','600','700','800','900'] as $w)
                                            <option value="{{ $w }}">{{ $w }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="bd-label">
                                    Font style
                                    <select name="style[font_style]" class="bd-select"
                                            x-model="styleData.font_style"
                                            @change="setStyle('font_style', $event.target.value)">
                                        <option value="">system default</option>
                                        <option value="normal">normal</option>
                                        <option value="italic">italic</option>
                                    </select>
                                </label>
                                <label class="bd-label">
                                    Text colour
                                    <div class="flex gap-2 items-center">
                                        <input type="color" class="bd-color"
                                               :value="getStyle('text_color') || '#ffffff'"
                                               @input="setStyle('text_color', $event.target.value)">
                                        <input type="text" name="style[text_color]" class="bd-input flex-1"
                                               :value="getStyle('text_color')"
                                               @input="setStyle('text_color', $event.target.value)"
                                               placeholder="{{ $systemStyle['text_color'] ?? 'inherit' }}">
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Background ── --}}
                <div class="bd-card mb-3">
                    <button type="button" class="bd-section-hd" @click="open.bg = !open.bg">
                        <div class="bd-section-hd-left">
                            <i class="fas fa-fill-drip bd-section-icon"></i>
                            <span class="bd-section-title">Background</span>
                            <span x-show="hasOverrideIn(@js($bgFields))" x-cloak class="bd-badge">overrides</span>
                        </div>
                        <div class="bd-section-hd-right">
                            <span role="button" tabindex="0"
                                    x-show="hasOverrideIn(@js($bgFields))" x-cloak
                                    @click.stop="clearSection(@js($bgFields))"
                                    @keydown.enter.prevent.stop="clearSection(@js($bgFields))"
                                    class="bd-clear-btn" title="Clear section overrides">
                                <i class="fas fa-xmark"></i> clear
                                    </span>
                            <i class="fas fa-chevron-down bd-chevron" :class="open.bg && 'rotate-180'"></i>
                        </div>
                    </button>
                    <div x-show="open.bg" x-collapse>
                        <div class="bd-body">
                            <div class="grid grid-cols-2 gap-3">
                                <label class="bd-label">
                                    Background colour
                                    <div class="flex gap-2 items-center">
                                        <input type="color" class="bd-color"
                                               :value="getStyle('bg_color') || '#1e1e2e'"
                                               @input="setStyle('bg_color', $event.target.value)">
                                        <input type="text" name="style[bg_color]" class="bd-input flex-1"
                                               :value="getStyle('bg_color')"
                                               @input="setStyle('bg_color', $event.target.value)"
                                               placeholder="{{ $systemStyle['bg_color'] ?? 'inherit' }}">
                                    </div>
                                </label>
                                <label class="bd-label">
                                    Background opacity (0–100)
                                    <input type="number" name="style[bg_opacity]" min="0" max="100" class="bd-input"
                                           :value="getStyle('bg_opacity')"
                                           @input="setStyle('bg_opacity', $event.target.value)"
                                           placeholder="{{ $systemStyle['bg_opacity'] ?? '100' }}">
                                </label>
                                <label class="bd-label" style="grid-column: span 2;">
                                    Background image URL
                                    <input type="url" name="style[bg_image]" class="bd-input"
                                           :value="getStyle('bg_image')"
                                           @input="setStyle('bg_image', $event.target.value)"
                                           placeholder="https://…">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Border ── --}}
                <div class="bd-card mb-3">
                    <button type="button" class="bd-section-hd" @click="open.border = !open.border">
                        <div class="bd-section-hd-left">
                            <i class="fas fa-square bd-section-icon"></i>
                            <span class="bd-section-title">Border</span>
                            <span x-show="hasOverrideIn(@js($borderFields))" x-cloak class="bd-badge">overrides</span>
                        </div>
                        <div class="bd-section-hd-right">
                            <span role="button" tabindex="0"
                                    x-show="hasOverrideIn(@js($borderFields))" x-cloak
                                    @click.stop="clearSection(@js($borderFields))"
                                    @keydown.enter.prevent.stop="clearSection(@js($borderFields))"
                                    class="bd-clear-btn" title="Clear section overrides">
                                <i class="fas fa-xmark"></i> clear
                                    </span>
                            <i class="fas fa-chevron-down bd-chevron" :class="open.border && 'rotate-180'"></i>
                        </div>
                    </button>
                    <div x-show="open.border" x-collapse>
                        <div class="bd-body">
                            <div class="grid grid-cols-2 gap-3">
                                <label class="bd-label">
                                    Border style
                                    <select name="style[border_style]" class="bd-select"
                                            x-model="styleData.border_style"
                                            @change="setStyle('border_style', $event.target.value)">
                                        <option value="">system default</option>
                                        @foreach(['none','solid','dashed','dotted','double'] as $bs)
                                            <option value="{{ $bs }}">{{ $bs }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="bd-label">
                                    Border width (px)
                                    <input type="text" name="style[border_width]" class="bd-input"
                                           :value="getStyle('border_width')"
                                           @input="setStyle('border_width', $event.target.value)"
                                           placeholder="{{ $systemStyle['border_width'] ?? '' }}">
                                </label>
                                <label class="bd-label">
                                    Border radius (px)
                                    <input type="text" name="style[border_radius]" class="bd-input"
                                           :value="getStyle('border_radius')"
                                           @input="setStyle('border_radius', $event.target.value)"
                                           placeholder="{{ $systemStyle['border_radius'] ?? '' }}">
                                </label>
                                <label class="bd-label">
                                    Border colour
                                    <div class="flex gap-2 items-center">
                                        <input type="color" class="bd-color"
                                               :value="getStyle('border_color') || '#ffffff'"
                                               @input="setStyle('border_color', $event.target.value)">
                                        <input type="text" name="style[border_color]" class="bd-input flex-1"
                                               :value="getStyle('border_color')"
                                               @input="setStyle('border_color', $event.target.value)"
                                               placeholder="{{ $systemStyle['border_color'] ?? '' }}">
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Shadow & Effects ── --}}
                <div class="bd-card mb-3">
                    <button type="button" class="bd-section-hd" @click="open.shadow = !open.shadow">
                        <div class="bd-section-hd-left">
                            <i class="fas fa-wand-magic-sparkles bd-section-icon"></i>
                            <span class="bd-section-title">Shadow &amp; Effects</span>
                            <span x-show="hasOverrideIn(@js($shadowFields))" x-cloak class="bd-badge">overrides</span>
                        </div>
                        <div class="bd-section-hd-right">
                            <span role="button" tabindex="0"
                                    x-show="hasOverrideIn(@js($shadowFields))" x-cloak
                                    @click.stop="clearSection(@js($shadowFields))"
                                    @keydown.enter.prevent.stop="clearSection(@js($shadowFields))"
                                    class="bd-clear-btn" title="Clear section overrides">
                                <i class="fas fa-xmark"></i> clear
                                    </span>
                            <i class="fas fa-chevron-down bd-chevron" :class="open.shadow && 'rotate-180'"></i>
                        </div>
                    </button>
                    <div x-show="open.shadow" x-collapse>
                        <div class="bd-body">
                            <div class="grid grid-cols-3 gap-3">
                                <label class="bd-label">
                                    Shadow preset
                                    <select name="style[shadow_preset]" class="bd-select"
                                            x-model="styleData.shadow_preset"
                                            @change="setStyle('shadow_preset', $event.target.value)">
                                        <option value="">system ({{ $systemStyle['shadow_preset'] ?? 'none' }})</option>
                                        @foreach(['none','soft','medium','strong'] as $sp)
                                            <option value="{{ $sp }}">{{ $sp }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="bd-label">
                                    Glass preset
                                    <select name="style[glass_preset]" class="bd-select"
                                            x-model="styleData.glass_preset"
                                            @change="setStyle('glass_preset', $event.target.value)">
                                        <option value="">system ({{ $systemStyle['glass_preset'] ?? 'off' }})</option>
                                        @foreach(['off','light','heavy'] as $gp)
                                            <option value="{{ $gp }}">{{ $gp }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="bd-label">
                                    Effect
                                    <select name="style[effect]" class="bd-select"
                                            x-model="styleData.effect"
                                            @change="setStyle('effect', $event.target.value)">
                                        <option value="">system default</option>
                                        @foreach(['none','blur','glow','gradient','shimmer'] as $ef)
                                            <option value="{{ $ef }}">{{ $ef }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Content (friendly fields + start blank) ── --}}
                <div class="bd-card mb-3">
                    <button type="button" class="bd-section-hd" @click="open.contentFields = !open.contentFields">
                        <div class="bd-section-hd-left">
                            <i class="fas fa-pen-to-square bd-section-icon"></i>
                            <span class="bd-section-title">Content</span>
                            <span x-show="startBlank || Object.keys(contentData).length" x-cloak class="bd-badge">overrides</span>
                        </div>
                        <i class="fas fa-chevron-down bd-chevron" :class="open.contentFields && 'rotate-180'"></i>
                    </button>
                    <div x-show="open.contentFields" x-collapse>
                        <div class="bd-body">

                            {{-- Start blank toggle --}}
                            <input type="hidden" name="start_blank" value="0">
                            <label class="flex items-start gap-3 mb-4 cursor-pointer p-3 rounded-xl"
                                   style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                                <input type="checkbox" name="start_blank" value="1" x-model="startBlank"
                                       class="mt-0.5" data-testid="checkbox-start-blank">
                                <span>
                                    <span class="block text-sm font-semibold" style="color: var(--text-primary);">Start blank (no sample content)</span>
                                    <span class="block bd-hint mt-0.5">
                                        New blocks of this type start with all seeded sample text, media and list
                                        items blanked out. Layout, colours and toggles are kept. Any content
                                        overrides below still apply on top.
                                    </span>
                                </span>
                            </label>

                            @if(!empty($scalarContentKeys) || !empty($arrayContentKeys))
                                <p class="bd-hint mb-3">
                                    Edit the default content for new blocks. Clearing a field saves an explicit
                                    blank (new blocks start empty for that field); "system" restores the
                                    system default. Values sync with the JSON editor below.
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach($scalarContentKeys as $key)
                                        @php $sysVal = $systemContent[$key]; @endphp
                                        <label class="bd-label" @if(is_string($sysVal) && mb_strlen($sysVal) > 60) style="grid-column: 1 / -1;" @endif>
                                            <span class="flex items-center justify-between gap-2">
                                                <span>{{ str_replace('_', ' ', $key) }}</span>
                                                <span role="button" tabindex="0" x-show="fieldOverridden(@js($key))" x-cloak
                                                      @click.prevent="resetField(@js($key))"
                                                      @keydown.enter.prevent="resetField(@js($key))"
                                                      class="bd-clear-btn" title="Remove override, use system default">
                                                    <i class="fas fa-xmark"></i> system
                                                </span>
                                            </span>
                                            @if(is_bool($sysVal))
                                                <select class="bd-select" data-testid="content-field-{{ $key }}"
                                                        :value="fieldOverridden(@js($key)) ? String(contentData[@js($key)]) : ''"
                                                        @change="setField(@js($key), $event.target.value)">
                                                    <option value="">system ({{ $sysVal ? 'true' : 'false' }})</option>
                                                    <option value="true">true</option>
                                                    <option value="false">false</option>
                                                </select>
                                            @elseif(is_int($sysVal) || is_float($sysVal))
                                                <input type="number" class="bd-input" data-testid="content-field-{{ $key }}"
                                                       :value="fieldValue(@js($key))"
                                                       @input="setField(@js($key), $event.target.value)"
                                                       placeholder="system: {{ $sysVal }}">
                                            @elseif(is_string($sysVal) && mb_strlen($sysVal) > 60)
                                                <textarea class="bd-input" rows="2" data-testid="content-field-{{ $key }}"
                                                          :value="fieldValue(@js($key))"
                                                          @input="setField(@js($key), $event.target.value)"></textarea>
                                            @else
                                                <input type="text" class="bd-input" data-testid="content-field-{{ $key }}"
                                                       :value="fieldValue(@js($key))"
                                                       @input="setField(@js($key), $event.target.value)">
                                            @endif
                                        </label>
                                    @endforeach
                                </div>

                                @foreach($arrayContentKeys as $key => $meta)
                                    <div class="mt-4" data-testid="content-list-{{ $key }}">
                                        <div class="flex items-center justify-between gap-2 mb-2">
                                            <span class="bd-label" style="margin:0;">{{ str_replace('_', ' ', $key) }}</span>
                                            <span class="flex items-center gap-2">
                                                <span role="button" tabindex="0" x-show="fieldOverridden(@js($key))" x-cloak
                                                      @click.prevent="resetField(@js($key))"
                                                      @keydown.enter.prevent="resetField(@js($key))"
                                                      class="bd-clear-btn" title="Remove override, use system default">
                                                    <i class="fas fa-xmark"></i> system
                                                </span>
                                                <button type="button" class="bd-clear-btn" data-testid="list-add-{{ $key }}"
                                                        @click="listAdd(@js($key))" title="Add item">
                                                    <i class="fas fa-plus"></i> add
                                                </button>
                                            </span>
                                        </div>
                                        <p x-show="listValue(@js($key)).length === 0" x-cloak class="bd-hint mb-2">
                                            No items — saving keeps this list explicitly empty for new blocks.
                                        </p>
                                        <div class="space-y-2">
                                            <template x-for="(item, idx) in listValue(@js($key))" :key="idx">
                                                <div class="flex items-start gap-2 p-2 rounded-xl transition-opacity"
                                                     style="background: var(--bg-glass); border: 1px solid var(--border-glass);"
                                                     :class="listDrag.key === @js($key) && listDrag.from === idx ? 'opacity-50' : ''"
                                                     @dragover.prevent="listDragOver(@js($key), $event)"
                                                     @drop.prevent="listDrop(@js($key), idx)">
                                                    <span draggable="true" data-testid="list-drag-{{ $key }}"
                                                          @dragstart="listDragStart(@js($key), idx, $event)"
                                                          @dragend="listDragEnd()"
                                                          class="cursor-grab active:cursor-grabbing select-none pt-2 px-1"
                                                          style="color: var(--text-faint);"
                                                          title="Drag to reorder">
                                                        <i class="fas fa-grip-vertical"></i>
                                                    </span>
                                                    <div class="flex flex-col gap-1 pt-1">
                                                        <button type="button" class="bd-clear-btn" title="Move up"
                                                                :disabled="idx === 0" :style="idx === 0 && 'opacity:0.3'"
                                                                @click="listMove(@js($key), idx, -1)">
                                                            <i class="fas fa-chevron-up"></i>
                                                        </button>
                                                        <button type="button" class="bd-clear-btn" title="Move down"
                                                                :disabled="idx === listValue(@js($key)).length - 1"
                                                                :style="idx === listValue(@js($key)).length - 1 && 'opacity:0.3'"
                                                                @click="listMove(@js($key), idx, 1)">
                                                            <i class="fas fa-chevron-down"></i>
                                                        </button>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        @if(($meta['kind'] ?? '') === 'strings')
                                                            <input type="text" class="bd-input w-full"
                                                                   :value="item"
                                                                   @input="listSetString(@js($key), idx, $event.target.value)">
                                                        @else
                                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                                @foreach(($meta['fields'] ?? []) as $field => $ftype)
                                                                    <label class="bd-label" style="margin:0;">
                                                                        <span class="text-xs" style="color: var(--text-faint);">{{ str_replace('_', ' ', $field) }}</span>
                                                                        @if($ftype === 'boolean')
                                                                            <span class="flex items-center gap-2 mt-1">
                                                                                <input type="checkbox"
                                                                                       :checked="!!(item && item[@js($field)])"
                                                                                       @change="listSetField(@js($key), idx, @js($field), $event.target.checked)">
                                                                            </span>
                                                                        @elseif($ftype === 'number')
                                                                            <input type="number" class="bd-input w-full"
                                                                                   :value="item ? item[@js($field)] : ''"
                                                                                   @input="listSetField(@js($key), idx, @js($field), $event.target.value)">
                                                                        @else
                                                                            <input type="text" class="bd-input w-full"
                                                                                   :value="item ? (item[@js($field)] ?? '') : ''"
                                                                                   @input="listSetField(@js($key), idx, @js($field), $event.target.value)">
                                                                        @endif
                                                                    </label>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <button type="button" class="bd-clear-btn mt-1" title="Remove item"
                                                            @click="listRemove(@js($key), idx)">
                                                        <i class="fas fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <p class="bd-hint">
                                    This block type has no simple text fields — edit its default content
                                    (lists, cards, items) via the JSON editor below.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ── Content overrides (collapsed by default) ── --}}
                <div class="bd-card mb-5">
                    <button type="button" class="bd-section-hd" @click="open.content = !open.content">
                        <div class="bd-section-hd-left">
                            <i class="fas fa-file-lines bd-section-icon"></i>
                            <span class="bd-section-title">Content overrides (JSON)</span>
                            @if(!empty($adminOverride['content']))
                                <span class="bd-badge">overrides</span>
                            @endif
                        </div>
                        <i class="fas fa-chevron-down bd-chevron" :class="open.content && 'rotate-180'"></i>
                    </button>
                    <div x-show="open.content" x-collapse>
                        <div class="bd-body">
                            <p class="bd-hint mb-3">
                                Override sample text, placeholder media URLs, button labels, etc.
                                Leave blank to use system defaults.
                                <strong>Do not include <code>_style</code> or <code>_placeholder</code> keys.</strong>
                            </p>

                            <details class="mb-3">
                                <summary class="cursor-pointer text-xs font-semibold" style="color: var(--text-faint);">
                                    <i class="fas fa-eye mr-1"></i> View system content defaults
                                </summary>
                                <pre class="mt-2 p-3 rounded-xl text-xs overflow-auto"
                                     style="background: var(--bg-glass); border: 1px solid var(--border-glass); color: var(--text-dimmed); max-height: 200px;">{{ json_encode($systemContent, JSON_PRETTY_PRINT) }}</pre>
                            </details>

                            <div class="flex justify-end mb-2">
                                <button type="button" @click="resetJson()"
                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                                        style="background: rgba(255,255,255,0.06); border: 1px solid var(--border-glass); color: var(--text-dimmed);"
                                        title="Load system defaults into the editor">
                                    <i class="fas fa-arrow-rotate-left mr-1"></i> Load system defaults
                                </button>
                            </div>

                            <textarea name="content_json"
                                      x-model="contentJson"
                                      rows="12"
                                      placeholder='Leave blank to use system defaults, or paste a JSON object, e.g.:&#10;{&#10;  "title": "My company",&#10;  "url": "https://example.com"&#10;}'
                                      class="w-full rounded-xl px-3 py-3 font-mono text-xs resize-y"
                                      spellcheck="false"
                                      style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary); min-height: 160px;"></textarea>
                        </div>
                    </div>
                </div>

                {{-- ── Actions ── --}}
                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="px-6 py-2.5 rounded-xl font-medium text-sm transition-all"
                            style="background: var(--c-primary); color: #fff;">
                        <i class="fas fa-check mr-1.5"></i> Save overrides
                    </button>
                    <a href="{{ route('admin.block-defaults.index') }}"
                       class="px-6 py-2.5 rounded-xl font-medium text-sm transition-all"
                       style="background: rgba(255,255,255,0.07); border: 1px solid var(--border-glass); color: var(--text-dimmed);">
                        Cancel
                    </a>
                </div>
            </div>

            {{-- ═══════════════════════════ RIGHT: live preview ═══════════════════════════ --}}
            <div class="bd-preview-col">
                <div class="bd-preview-sticky">
                    <div class="glass rounded-2xl border border-white/10 p-5">

                        {{-- Preview heading --}}
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-sm font-semibold" style="color: var(--text-primary);">
                                <i class="fas fa-eye mr-1.5" style="color: var(--accent-light);"></i>
                                Live preview
                            </div>
                            <span class="text-xs" style="color: var(--text-faint);">
                                <span x-show="previewLoading" x-cloak><i class="fas fa-circle-notch fa-spin mr-1"></i></span>
                                Updates as you edit
                            </span>
                        </div>

                        {{-- Preview canvas — real server-rendered block via the
                             shared public renderer, in a sandboxed iframe. --}}
                        <div class="bd-canvas bd-canvas-frame">
                            <iframe x-ref="previewFrame" class="bd-iframe" sandbox="allow-scripts" title="Block preview"></iframe>
                        </div>
                        <p x-show="jsonInvalid" x-cloak class="bd-json-warn">
                            <i class="fas fa-triangle-exclamation mr-1"></i>
                            Content JSON is invalid, preview paused until it parses.
                        </p>

                        {{-- Effective style tokens --}}
                        <div class="mt-4 pt-4 bd-tokens-section">
                            <div class="bd-tokens-heading">Effective style tokens</div>
                            <div class="bd-chips">
                                <template x-for="(val, key) in effective" :key="key">
                                    <div class="bd-chip" :class="styleData[key] !== undefined ? 'bd-chip-on' : ''">
                                        <span class="bd-chip-key" x-text="key.replace(/_/g, ' ')"></span>
                                        <span class="bd-chip-val" x-text="val"></span>
                                    </div>
                                </template>
                            </div>
                            <p class="bd-tokens-note">
                                <span class="bd-chip bd-chip-on" style="display:inline-flex;">override</span>
                                = your override &nbsp;|&nbsp;
                                <span class="bd-chip" style="display:inline-flex;">system</span>
                                = system default
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
/* ── Two-pane grid ─────────────────────────── */
.bd-two-pane {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 20px;
    align-items: start;
}
@media (max-width: 1100px) {
    .bd-two-pane { grid-template-columns: 1fr; }
    .bd-preview-col { order: -1; }
}
.bd-preview-sticky { position: sticky; top: 76px; }

/* ── Section cards ─────────────────────────── */
.bd-card {
    border-radius: 16px;
    overflow: hidden;
    background: var(--bg-glass);
    border: 1px solid var(--border-glass);
}
html.light-mode .bd-card {
    background: rgba(0,0,0,0.02);
    border-color: rgba(0,0,0,0.09);
}

.bd-section-hd {
    display: flex; align-items: center; justify-content: space-between;
    width: 100%; padding: 13px 16px; cursor: pointer;
    background: none; border: none; text-align: left;
    transition: background 0.15s;
}
.bd-section-hd:hover { background: rgba(255,255,255,0.03); }
html.light-mode .bd-section-hd:hover { background: rgba(0,0,0,0.025); }

.bd-section-hd-left  { display: flex; align-items: center; gap: 8px; }
.bd-section-hd-right { display: flex; align-items: center; gap: 8px; }

.bd-section-icon { font-size: 11px; width: 14px; color: var(--accent-light); }
.bd-section-title { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.bd-chevron { font-size: 10px; color: var(--text-faint); transition: transform 0.2s; }

.bd-body { padding: 4px 16px 16px; }

/* ── Override badge ────────────────────────── */
.bd-badge {
    display: inline-block;
    font-size: 9px; font-weight: 700; padding: 1px 7px; border-radius: 99px;
    text-transform: uppercase; letter-spacing: 0.04em;
    background: rgba(59,130,246,0.14); color: #93c5fd;
    border: 1px solid rgba(59,130,246,0.28);
}
html.light-mode .bd-badge {
    background: rgba(59,130,246,0.1); color: #1d4ed8;
    border-color: rgba(59,130,246,0.3);
}

/* ── Clear-section button ──────────────────── */
.bd-clear-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 600;
    cursor: pointer; line-height: 1.6;
    background: rgba(239,68,68,0.09); color: #fca5a5;
    border: 1px solid rgba(239,68,68,0.22); transition: background 0.15s;
}
html.light-mode .bd-clear-btn {
    background: rgba(239,68,68,0.07); color: #dc2626;
    border-color: rgba(239,68,68,0.2);
}
.bd-clear-btn:hover { background: rgba(239,68,68,0.18); }
html.light-mode .bd-clear-btn:hover { background: rgba(239,68,68,0.14); }

/* ── Form fields ───────────────────────────── */
.bd-label {
    display: flex; flex-direction: column; gap: 4px;
    font-size: 10px; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.05em; color: var(--text-faint);
}
.bd-input {
    padding: 7px 10px; border-radius: 8px;
    border: 1px solid var(--border-glass);
    background-color: var(--bg-glass-input); color: var(--text-primary);
    font-size: 13px; font-weight: 500;
    text-transform: none; letter-spacing: 0; font-family: inherit;
    transition: border-color 0.15s;
}
html.light-mode .bd-input { background-color: #fff; border-color: rgba(0,0,0,0.14); color: #111; }
.bd-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-glow); }
.bd-input-sm { font-size: 12px; padding: 5px 8px; }

/* background-color (NOT the `background` shorthand): the admin layout
   injects the select chevron via a `[data-app-layout] select`
   background-image; a shorthand here resets background-repeat/position
   to their initials while the higher-specificity image survives,
   tiling the chevron across the whole control. */
.bd-select {
    padding: 7px 10px; border-radius: 8px;
    border: 1px solid var(--border-glass);
    background-color: var(--bg-glass-input); color: var(--text-primary);
    font-size: 13px; text-transform: none; letter-spacing: 0;
    transition: border-color 0.15s;
}
html.light-mode .bd-select { background-color: #fff; border-color: rgba(0,0,0,0.14); color: #111; }
.bd-select:focus { outline: none; border-color: var(--accent); }

.bd-color {
    width: 36px; height: 34px; border-radius: 7px; cursor: pointer; flex-shrink: 0;
    border: 1px solid var(--border-glass); background: var(--bg-glass-input); padding: 2px;
}

.bd-hint {
    font-size: 11px; color: var(--text-faint);
}

/* ── Spacing grid ──────────────────────────── */
.bd-spacer-grid {
    display: grid;
    grid-template-columns: auto repeat(4, 1fr);
    gap: 6px;
    align-items: end;
}
.bd-spacer-label {
    font-size: 9px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.06em; color: var(--text-faint);
    writing-mode: vertical-rl; transform: rotate(180deg);
    align-self: center; padding-bottom: 2px;
}
@media (max-width: 640px) {
    .bd-spacer-grid {
        grid-template-columns: auto repeat(2, 1fr);
    }
}

/* ── Preview panel ─────────────────────────── */
.bd-canvas {
    border-radius: 12px; padding: 20px; min-height: 140px;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.01) 100%);
    border: 1px solid rgba(255,255,255,0.06);
}
html.light-mode .bd-canvas {
    background: linear-gradient(135deg, rgba(0,0,0,0.04) 0%, rgba(0,0,0,0.01) 100%);
    border-color: rgba(0,0,0,0.08);
}

.bd-canvas-frame {
    padding: 0; display: block; overflow: hidden;
}
.bd-iframe {
    display: block; width: 100%; height: 380px;
    border: 0; border-radius: 12px; background: transparent;
}

.bd-json-warn {
    margin-top: 10px; font-size: 11px; font-weight: 600;
    color: #fbbf24;
}
html.light-mode .bd-json-warn { color: #b45309; }

/* ── Style token chips ─────────────────────── */
.bd-tokens-section { border-top: 1px solid var(--border-glass); padding-top: 14px; }
html.light-mode .bd-tokens-section { border-color: rgba(0,0,0,0.09); }

.bd-tokens-heading {
    font-size: 9px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.08em; color: var(--text-faint); margin-bottom: 8px;
}
.bd-tokens-note {
    font-size: 10px; margin-top: 8px; color: var(--text-faint);
    display: flex; align-items: center; gap: 4px; flex-wrap: wrap;
}

.bd-chips { display: flex; flex-wrap: wrap; gap: 4px; }

.bd-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 2px 7px; border-radius: 6px; font-size: 10px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
}
html.light-mode .bd-chip {
    background: rgba(0,0,0,0.04);
    border-color: rgba(0,0,0,0.09);
}
.bd-chip-on {
    background: rgba(59,130,246,0.12);
    border-color: rgba(59,130,246,0.28);
}
html.light-mode .bd-chip-on {
    background: rgba(59,130,246,0.1);
    border-color: rgba(59,130,246,0.28);
}
.bd-chip-key { color: var(--text-faint); }
.bd-chip-val { color: var(--text-primary); font-weight: 600; }

[x-cloak] { display: none !important; }
</style>
@endsection
