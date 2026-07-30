@extends('user.layouts.app')
@section('title', 'Default Colors - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php
    $bs = $link->settings['biolink'] ?? [];
    $tdc = is_array($bs['template_default_colors'] ?? null) ? $bs['template_default_colors'] : [];
    $activeSettingsTab = 'default-colors';
    $fields = [
        'text_color'        => ['label' => 'Text color',            'hint' => 'Body & heading text on new blocks'],
        'bg_color'          => ['label' => 'Background / fill',     'hint' => 'Card background of new blocks'],
        'border_color'      => ['label' => 'Border color',          'hint' => 'Applied when a block enables a border'],
        'accent_color'      => ['label' => 'Accent / button color', 'hint' => 'Background of button-style blocks (links, CTA)'],
        'accent_text_color' => ['label' => 'Text on accent',        'hint' => 'Text color on top of the accent background'],
    ];
    $initial = [];
    foreach ($fields as $k => $f) { $initial[$k] = (string) ($tdc[$k] ?? ''); }
@endphp

<div class="w-full max-w-7xl mx-auto">
    @include('user.links.partials.editor-header', ['link' => $link, 'activeMainTab' => 'settings'])
    @include('user.links.partials.settings-header', ['link' => $link, 'activeSettingsTab' => $activeSettingsTab])

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-7" id="settings-tab-content">
            <form method="POST" action="{{ route('user.links.page-settings', $link) }}">
                @csrf

                <div class="card-premium p-6" x-data="{
                    c: @js((object) $initial),
                    lum(hex) {
                        const m = /^#?([0-9a-f]{6})$/i.exec(String(hex || '').trim());
                        if (!m) return null;
                        const n = parseInt(m[1], 16);
                        const chan = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
                        return 0.2126 * chan((n >> 16) & 255) + 0.7152 * chan((n >> 8) & 255) + 0.0722 * chan(n & 255);
                    },
                    ratio(fg, bg) {
                        const a = this.lum(fg), b = this.lum(bg);
                        if (a === null || b === null) return null;
                        const hi = Math.max(a, b), lo = Math.min(a, b);
                        return (hi + 0.05) / (lo + 0.05);
                    },
                    textRatio() { return this.ratio(this.c.text_color, this.c.bg_color); },
                    accentRatio() {
                        if (!this.c.accent_color && !this.c.accent_text_color) return null;
                        return this.ratio(this.c.accent_text_color || '#ffffff', this.c.accent_color || '#3d6bff');
                    },
                    low(r) { return r !== null && r < 4.5; },
                    fmt(r) { return r === null ? '' : (Math.round(r * 10) / 10) + ':1'; }
                }">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(61,107,255,0.1);"><i class="fas fa-fill-drip text-blue-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Template Default Colors</h3>
                    </div>
                    <p class="text-xs mb-5" style="color: var(--text-muted);">
                        Baseline colors for every <strong>new</strong> block added to this template, and to pages created from it.
                        Each block stays individually recolorable afterward. Leave a field empty to inherit from the theme as today.
                        Existing blocks are never changed.
                    </p>

                    <div class="space-y-4">
                        @foreach($fields as $key => $f)
                        <div class="flex items-center gap-3">
                            <div class="flex-1">
                                <label class="block text-xs font-medium mb-0.5" style="color: var(--text-muted);">{{ $f['label'] }}</label>
                                <div class="text-[10px]" style="color: var(--text-faint);">{{ $f['hint'] }}</div>
                            </div>
                            {{-- The hidden input is what actually submits: '' = inherit. --}}
                            <input type="hidden" name="template_default_colors[{{ $key }}]" :value="c['{{ $key }}']">
                            <template x-if="c['{{ $key }}'] === ''">
                                <button type="button" class="px-3 py-1.5 rounded-lg text-[11px] font-semibold"
                                        style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass); color: var(--text-faint);"
                                        @click="c['{{ $key }}'] = '{{ $key === 'text_color' || $key === 'accent_text_color' ? '#ffffff' : '#3d6bff' }}'">
                                    Inherit: click to set
                                </button>
                            </template>
                            <template x-if="c['{{ $key }}'] !== ''">
                                <div class="flex items-center gap-2">
                                    <input type="color" class="w-9 h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"
                                           :value="c['{{ $key }}']" @input="c['{{ $key }}'] = $event.target.value">
                                    <span class="text-[11px] font-mono" style="color: var(--text-muted);" x-text="c['{{ $key }}']"></span>
                                    <button type="button" class="w-7 h-7 rounded-lg text-[11px]" title="Clear (inherit)"
                                            style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);"
                                            @click="c['{{ $key }}'] = ''">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                        @endforeach
                    </div>

                    {{-- Live contrast preview --}}
                    <div class="mt-6 pt-5" style="border-top: 1px solid var(--border-glass);">
                        <div class="text-xs font-semibold mb-3" style="color: var(--text-muted);"><i class="fas fa-eye mr-1.5"></i>Preview</div>
                        <div class="rounded-xl p-4 space-y-3" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <div class="rounded-lg px-4 py-3 text-sm font-medium"
                                 :style="'background:' + (c.bg_color || 'rgba(255,255,255,0.06)') + ';color:' + (c.text_color || 'var(--text-primary)') + ';border:1px solid ' + (c.border_color || 'var(--border-glass)')">
                                Text on background
                            </div>
                            <template x-if="low(textRatio())">
                                <div class="flex items-center gap-2 rounded-lg px-3 py-2 text-[11px] font-medium"
                                     style="background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.35); color: #f59e0b;"
                                     data-testid="contrast-warning-text">
                                    <i class="fas fa-triangle-exclamation"></i>
                                    <span>Low contrast (<span x-text="fmt(textRatio())"></span>): text may be hard to read. Aim for at least 4.5:1.</span>
                                </div>
                            </template>
                            <div class="rounded-lg px-4 py-3 text-sm font-semibold text-center"
                                 :style="'background:' + (c.accent_color || '#3d6bff') + ';color:' + (c.accent_text_color || '#ffffff')">
                                Button text on accent
                            </div>
                            <template x-if="low(accentRatio())">
                                <div class="flex items-center gap-2 rounded-lg px-3 py-2 text-[11px] font-medium"
                                     style="background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.35); color: #f59e0b;"
                                     data-testid="contrast-warning-accent">
                                    <i class="fas fa-triangle-exclamation"></i>
                                    <span>Low contrast (<span x-text="fmt(accentRatio())"></span>): button text may be hard to read. Aim for at least 4.5:1.</span>
                                </div>
                            </template>
                        </div>
                        <p class="text-[10px] mt-2" style="color: var(--text-faint);">Judge contrast here before saving: low-contrast pairs are hard to read on the public page.</p>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-semibold">
                            <i class="fas fa-save mr-1.5"></i> Save Default Colors
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="lg:col-span-5 lg:sticky lg:top-6">
            @include('user.links.partials.device-preview', ['link' => $link])
        </div>
    </div>
</div>
@endsection
