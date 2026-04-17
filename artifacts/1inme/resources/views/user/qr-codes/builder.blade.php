@extends('user.layouts.app')
@section('title', $qrCode && $qrCode->exists ? 'Edit · ' . $qrCode->name : 'New QR Code')

@php
    $isEdit       = $qrCode && $qrCode->exists;
    $action       = $isEdit ? route('user.qr-codes.update', $qrCode) : route('user.qr-codes.store');
    $design       = ($isEdit ? ($qrCode->design ?? []) : []) + $defaultDesign;
    $design['frame'] = ($isEdit ? ($qrCode->design['frame'] ?? []) : []) + $defaultDesign['frame'];
    $payload      = $isEdit ? ($qrCode->payload ?? []) : [];
    $currentType  = $isEdit ? $qrCode->type : 'url';
    $linkId       = $isEdit ? $qrCode->link_id : null;
@endphp

@section('content')
<style>
    [x-cloak] { display: none !important; }
    .qr-type-tab { transition: all .15s; }
    .qr-type-tab.active { background: var(--c-primary-soft); color: var(--c-primary); border-color: var(--accent); }
    .qr-frame-card { cursor: pointer; transition: all .15s; }
    .qr-frame-card.active { border-color: var(--accent); background: var(--c-primary-soft); }
    .qr-style-card { cursor: pointer; transition: all .15s; padding: 8px; border-radius: 8px; border: 1px solid var(--border-glass); }
    .qr-style-card.active { border-color: var(--accent); background: var(--c-primary-soft); }
    .qr-color-swatch { width: 28px; height: 28px; border-radius: 6px; cursor: pointer; border: 1px solid var(--border-glass); }
    .qr-color-swatch.active { border: 2px solid var(--accent); }
    .frame-arrow { position: relative; padding: 14px 10px 28px; }
    .frame-arrow::after {
        content:''; position:absolute; bottom:14px; left:50%; transform:translateX(-50%);
        border-left:8px solid transparent; border-right:8px solid transparent; border-top:10px solid var(--frame-bg, #071437);
    }
</style>

<div class="max-w-[1400px] mx-auto" x-data="qrBuilder()" x-init="init()" x-cloak>
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
        <template x-for="(v, k) in payload" :key="k">
            <input type="hidden" :name="`payload[${k}]`" :value="v ?? ''">
        </template>
        <template x-for="(v, k) in flatDesign()" :key="k">
            <input type="hidden" :name="k" :value="v ?? ''">
        </template>

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
                    <div class="flex items-center justify-center rounded-lg p-4 min-h-[400px]" style="background: var(--bg-glass-hover); border: 1px solid var(--border-glass);">
                        <div :class="frame.template !== 'none' ? 'qr-frame-wrap qr-frame-' + frame.template : ''"
                             :style="frame.template !== 'none' ? frameStyles() : ''">
                            <div x-show="frame.template !== 'none' && framePosition() === 'top'" x-cloak class="text-center pb-2 px-3 font-bold" :style="`color: ${frame.text_color}; font-family: '${frame.font}', sans-serif;`" x-text="frame.text"></div>
                            <div id="qrTarget" class="bg-white rounded" :style="`background: ${design.transparent_bg ? 'transparent' : design.bg_color}; padding: 8px; border-radius: 8px;`"></div>
                            <div x-show="frame.template !== 'none' && framePosition() === 'bottom'" x-cloak class="text-center pt-2 px-3 font-bold" :style="`color: ${frame.text_color}; font-family: '${frame.font}', sans-serif;`" x-text="frame.text"></div>
                        </div>
                    </div>
                    <div class="mt-3 text-center text-[11px]" style="color: var(--text-muted);">
                        <span x-text="encodedPreview"></span>
                    </div>
                </div>
            </div>

            {{-- RIGHT: design panel --}}
            <div class="lg:col-span-4 space-y-4">
                <div class="card-premium p-4">
                    <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);"><i class="fas fa-palette mr-1.5"></i> Style</h3>
                    <div class="text-[11px] font-semibold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Dot style</div>
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        @foreach(['square','rounded','dots','classy','classy-rounded','extra-rounded'] as $s)
                            <button type="button" @click="design.dot_style = '{{ $s }}'; render()"
                                    class="qr-style-card text-[10px] capitalize text-center"
                                    :class="design.dot_style === '{{ $s }}' ? 'active' : ''">{{ str_replace('-',' ',$s) }}</button>
                        @endforeach
                    </div>
                    <div class="text-[11px] font-semibold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Outer eye</div>
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        @foreach(['dot','square','extra-rounded'] as $s)
                            <button type="button" @click="design.corner_square_style = '{{ $s }}'; render()"
                                    class="qr-style-card text-[10px] capitalize text-center"
                                    :class="design.corner_square_style === '{{ $s }}' ? 'active' : ''">{{ str_replace('-',' ',$s) }}</button>
                        @endforeach
                    </div>
                    <div class="text-[11px] font-semibold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Inner eye</div>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['dot','square'] as $s)
                            <button type="button" @click="design.corner_dot_style = '{{ $s }}'; render()"
                                    class="qr-style-card text-[10px] capitalize text-center"
                                    :class="design.corner_dot_style === '{{ $s }}' ? 'active' : ''">{{ $s }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="card-premium p-4">
                    <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);"><i class="fas fa-fill-drip mr-1.5"></i> Colors</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-[11px] font-semibold mb-1.5" style="color: var(--text-secondary);">Foreground</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="design.fg_color" @input="syncFg(); render()" class="w-10 h-10 rounded cursor-pointer">
                                <input type="text" x-model="design.fg_color" @input="render()" class="flex-1 px-2 py-1.5 text-xs font-mono rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1.5" style="color: var(--text-secondary);">Background</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="design.bg_color" @input="render()" class="w-10 h-10 rounded cursor-pointer" :disabled="design.transparent_bg">
                                <input type="text" x-model="design.bg_color" @input="render()" class="flex-1 px-2 py-1.5 text-xs font-mono rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);" :disabled="design.transparent_bg">
                            </div>
                            <label class="inline-flex items-center gap-1.5 text-[11px] mt-1.5 cursor-pointer" style="color: var(--text-muted);">
                                <input type="checkbox" x-model="design.transparent_bg" @change="render()"> Transparent background (best for Link in Bio pages)
                            </label>
                        </div>
                        <details>
                            <summary class="text-[11px] cursor-pointer font-semibold" style="color: var(--text-muted);">Advanced eye colors</summary>
                            <div class="mt-2 space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] flex-1" style="color: var(--text-secondary);">Outer eye</span>
                                    <input type="color" x-model="design.corner_square_color" @input="render()" class="w-8 h-8 rounded cursor-pointer">
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] flex-1" style="color: var(--text-secondary);">Inner eye</span>
                                    <input type="color" x-model="design.corner_dot_color" @input="render()" class="w-8 h-8 rounded cursor-pointer">
                                </div>
                            </div>
                        </details>
                    </div>
                </div>

                <div class="card-premium p-4">
                    <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);"><i class="fas fa-image mr-1.5"></i> Logo</h3>
                    <input type="url" x-model="design.logo_url" @input="render()" placeholder="https://… (paste image URL)"
                           class="w-full px-2 py-1.5 text-xs rounded outline-none mb-2" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    <div x-show="design.logo_url" class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-[11px]" style="color: var(--text-secondary);">Size</span>
                        <input type="range" min="0.05" max="0.5" step="0.01" x-model.number="design.logo_size" @input="render()" class="flex-1">
                        <span class="text-[11px] w-12 text-right" x-text="Math.round(design.logo_size * 100) + '%'" style="color: var(--text-muted);"></span>
                    </div>
                    <button type="button" x-show="design.logo_url" @click="design.logo_url = null; render()" class="text-[11px]" style="color: var(--c-danger);"><i class="fas fa-times"></i> Remove</button>
                </div>

                <div class="card-premium p-4">
                    <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);"><i class="fas fa-square-full mr-1.5"></i> Frame</h3>
                    <div class="grid grid-cols-4 gap-2 mb-3">
                        @foreach(['none'=>'None','scan-me'=>'Scan Me','classic'=>'Classic','rounded'=>'Rounded','ribbon'=>'Ribbon','bubble'=>'Bubble','minimal'=>'Minimal','arrow'=>'Arrow'] as $key => $label)
                            <button type="button" @click="frame.template = '{{ $key }}'"
                                    class="qr-frame-card p-2 rounded-lg text-[10px] text-center"
                                    :class="frame.template === '{{ $key }}' ? 'active' : ''"
                                    style="border: 1px solid var(--border-glass); color: var(--text-secondary);">{{ $label }}</button>
                        @endforeach
                    </div>
                    <div x-show="frame.template !== 'none'" x-cloak class="space-y-2">
                        <input type="text" x-model="frame.text" maxlength="60" placeholder="Frame text"
                               class="w-full px-2 py-1.5 text-xs rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                        <select x-model="frame.font" class="w-full px-2 py-1.5 text-xs rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                            @foreach(['Inter','Roboto','Poppins','Montserrat','Playfair Display','Bebas Neue','Pacifico'] as $f)
                                <option value="{{ $f }}">{{ $f }}</option>
                            @endforeach
                        </select>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] flex-1" style="color: var(--text-secondary);">Frame fill</span>
                            <input type="color" x-model="frame.bg_color" class="w-8 h-8 rounded cursor-pointer">
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] flex-1" style="color: var(--text-secondary);">Text color</span>
                            <input type="color" x-model="frame.text_color" class="w-8 h-8 rounded cursor-pointer">
                        </div>
                    </div>
                </div>

                <div class="card-premium p-4">
                    <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);"><i class="fas fa-sliders mr-1.5"></i> Advanced</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">Error correction</label>
                            <select x-model="design.error_correction" @change="render()" class="w-full px-2 py-1.5 text-xs rounded outline-none" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                                <option value="L">L — Low (~7%)</option>
                                <option value="M">M — Medium (~15%)</option>
                                <option value="Q">Q — Quartile (~25%)</option>
                                <option value="H">H — High (~30%, recommended for logos)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">Size (px) <span x-text="design.size" class="font-mono"></span></label>
                            <input type="range" min="200" max="1200" step="50" x-model.number="design.size" @input="render()" class="w-full">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1" style="color: var(--text-secondary);">Margin <span x-text="design.margin" class="font-mono"></span></label>
                            <input type="range" min="0" max="40" step="1" x-model.number="design.margin" @input="render()" class="w-full">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/qr-code-styling@1.6.0-rc.1/lib/qr-code-styling.js"></script>
<script>
function qrBuilder() {
    return {
        type: @js($currentType),
        useExistingLink: @js((bool) $linkId),
        linkId: @js($linkId),
        payload: @js($payload),
        design: @js(array_diff_key($design, ['frame' => 0])),
        frame: @js($design['frame']),
        encoded: '',
        encodedPreview: '',
        qr: null,
        renderTimer: null,
        resolveTimer: null,

        init() {
            this.qr = new QRCodeStyling(this.options('preview'));
            this.qr.append(document.getElementById('qrTarget'));
            this.$watch('payload', () => this.scheduleResolve(), { deep: true });
            this.$watch('type', () => this.scheduleResolve());
            this.$watch('linkId', () => this.useExistingLink && this.resolveLinkPayload());
            this.$watch('useExistingLink', v => {
                if (!v) { this.linkId = null; this.scheduleResolve(); }
            });
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
            this.renderTimer = setTimeout(() => {
                if (!this.qr) return;
                this.qr.update(this.options(this.encoded || 'preview'));
            }, 60);
        },

        options(data) {
            const o = {
                width: 320, height: 320, type: 'svg', data: data || 'preview',
                margin: this.design.margin,
                qrOptions: { errorCorrectionLevel: this.design.error_correction },
                backgroundOptions: { color: this.design.transparent_bg ? 'transparent' : this.design.bg_color },
                dotsOptions: { type: this.design.dot_style, color: this.design.fg_color },
                cornersSquareOptions: { type: this.design.corner_square_style, color: this.design.corner_square_color },
                cornersDotOptions: { type: this.design.corner_dot_style, color: this.design.corner_dot_color },
            };
            if (this.design.logo_url) {
                o.image = this.design.logo_url;
                o.imageOptions = { hideBackgroundDots: this.design.hide_dots_behind_logo, imageSize: this.design.logo_size, margin: this.design.logo_margin, crossOrigin: 'anonymous' };
            }
            return o;
        },

        syncFg() {
            if (this.design.corner_square_color === '#071437' || !this.design.corner_square_color) this.design.corner_square_color = this.design.fg_color;
            if (this.design.corner_dot_color === '#071437' || !this.design.corner_dot_color) this.design.corner_dot_color = this.design.fg_color;
        },

        framePosition() {
            return ['classic','minimal','arrow','rounded','bubble'].includes(this.frame.template) ? 'bottom' : 'top';
        },

        frameStyles() {
            const map = {
                'scan-me':  `--frame-bg:${this.frame.bg_color}; background:${this.frame.bg_color}; border-radius:14px; padding:14px; padding-bottom:6px;`,
                'classic':  `--frame-bg:${this.frame.bg_color}; background:${this.frame.bg_color}; padding:14px; padding-top:6px;`,
                'rounded':  `--frame-bg:${this.frame.bg_color}; background:${this.frame.bg_color}; border-radius:24px; padding:18px;`,
                'ribbon':   `--frame-bg:${this.frame.bg_color}; background:${this.frame.bg_color}; border-radius:8px 8px 0 0; padding:14px; padding-bottom:6px; clip-path: polygon(0 0,100% 0,100% 90%,90% 100%,10% 100%,0 90%);`,
                'bubble':   `--frame-bg:${this.frame.bg_color}; background:${this.frame.bg_color}; border-radius:50%; padding:36px;`,
                'minimal':  `--frame-bg:${this.frame.bg_color}; border:3px solid ${this.frame.bg_color}; border-radius:8px; padding:10px;`,
                'arrow':    `--frame-bg:${this.frame.bg_color}; background:${this.frame.bg_color}; border-radius:14px; padding:14px;`,
            };
            return map[this.frame.template] || '';
        },

        flatDesign() {
            const out = {};
            Object.entries(this.design).forEach(([k, v]) => { out[`design[${k}]`] = v; });
            Object.entries(this.frame).forEach(([k, v]) => { out[`design[frame][${k}]`] = v; });
            return out;
        },

        downloadPng() { if (this.qr) this.qr.download({ name: 'qr-code', extension: 'png' }); },
        downloadSvg() { if (this.qr) this.qr.download({ name: 'qr-code', extension: 'svg' }); },
    };
}
</script>
@endsection
