@extends('admin.layouts.app')
@section('title', 'Block Defaults: ' . $type)
@section('page-title', 'Block Defaults: ' . $type)

@section('content')
<div class="max-w-3xl"
     x-data="{
        styleTab: 'style',
        styleData: @js($adminOverride['style'] ?? []),
        contentJson: @js(!empty($adminOverride['content']) ? json_encode($adminOverride['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : ''),
        systemContent: @js($systemContent),
        systemStyle: @js($systemStyle),
        resetJson() {
            this.contentJson = JSON.stringify(this.systemContent, null, 2);
        },
        getStyle(field) { return this.styleData[field] ?? ''; },
        setStyle(field, val) {
            if (val === '') {
                delete this.styleData[field];
            } else {
                this.styleData[field] = val;
            }
        },
     }">

    {{-- Back link --}}
    <div class="mb-4">
        <a href="{{ route('admin.block-defaults.index') }}" class="text-sm" style="color: var(--text-dimmed);">
            <i class="fas fa-arrow-left mr-1"></i> All block types
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 text-sm">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-3 rounded-xl border border-red-500/30 bg-red-500/10 text-red-300 text-sm">
            @foreach($errors->all() as $err)
                <div><i class="fas fa-exclamation-circle mr-1"></i> {{ $err }}</div>
            @endforeach
        </div>
    @endif

    {{-- Header card --}}
    <div class="glass rounded-2xl border border-white/10 p-5 mb-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-white/90 font-mono">{{ $type }}</h2>
                <p class="text-sm text-white/50 mt-1">
                    Overrides below are merged on top of system defaults for every
                    <strong>new</strong> block of this type. Existing blocks are not affected.
                </p>
            </div>
            @if($hasOverride)
                <form method="POST" action="{{ route('admin.block-defaults.reset', $type) }}"
                      onsubmit="return confirm('Reset « {{ $type }} » to system defaults?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all"
                            style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5;">
                        <i class="fas fa-rotate-left mr-1"></i> Reset to system defaults
                    </button>
                </form>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.block-defaults.update', $type) }}">
        @csrf
        @method('PUT')

        {{-- Style overrides --}}
        <div class="glass rounded-2xl border border-white/10 p-5 mb-4">
            <h3 class="text-sm font-semibold mb-1" style="color: var(--text-primary);">
                <i class="fas fa-paintbrush mr-1.5" style="color: var(--accent-light);"></i>
                Style overrides
            </h3>
            <p class="text-xs mb-4" style="color: var(--text-faint);">
                Only filled-in fields are saved as overrides. Leave a field blank to use the system default.
            </p>

            {{-- Structural --}}
            <div class="bd-section-title">Layout &amp; Display</div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                <label class="bd-label">
                    Display mode
                    <select name="style[display_mode]" class="bd-select"
                            x-model="styleData.display_mode"
                            @change="setStyle('display_mode', $event.target.value)">
                        <option value="">— system default ({{ $systemStyle['display_mode'] ?? 'card' }}) —</option>
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
                           placeholder="{{ $systemStyle['grid_span'] ?? '' }}">
                </label>
                <label class="bd-label">
                    Padding (px)
                    <input type="text" name="style[padding]" class="bd-input"
                           :value="getStyle('padding')"
                           @input="setStyle('padding', $event.target.value)"
                           placeholder="{{ $systemStyle['padding'] ?? '' }}">
                </label>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                @foreach(['padding_top','padding_bottom','padding_left','padding_right'] as $p)
                    <label class="bd-label">
                        {{ Str::title(str_replace('_', ' ', $p)) }}
                        <input type="text" name="style[{{ $p }}]" class="bd-input"
                               :value="getStyle('{{ $p }}')"
                               @input="setStyle('{{ $p }}', $event.target.value)"
                               placeholder="{{ $systemStyle[$p] ?? '' }}">
                    </label>
                @endforeach
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                @foreach(['margin_top','margin_bottom','margin_left','margin_right'] as $m)
                    <label class="bd-label">
                        {{ Str::title(str_replace('_', ' ', $m)) }}
                        <input type="text" name="style[{{ $m }}]" class="bd-input"
                               :value="getStyle('{{ $m }}')"
                               @input="setStyle('{{ $m }}', $event.target.value)"
                               placeholder="{{ $systemStyle[$m] ?? '' }}">
                    </label>
                @endforeach
            </div>

            {{-- Typography --}}
            <div class="bd-section-title">Typography</div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                <label class="bd-label">
                    Font family
                    <input type="text" name="style[font_family]" class="bd-input"
                           :value="getStyle('font_family')"
                           @input="setStyle('font_family', $event.target.value)"
                           placeholder="e.g. Space Grotesk">
                </label>
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
                        <option value="">— system default —</option>
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
                        <option value="">— system default —</option>
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

            {{-- Background --}}
            <div class="bd-section-title">Background</div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
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
                <label class="bd-label" style="grid-column: span 2;">
                    Background image URL
                    <input type="url" name="style[bg_image]" class="bd-input"
                           :value="getStyle('bg_image')"
                           @input="setStyle('bg_image', $event.target.value)"
                           placeholder="https://…">
                </label>
                <label class="bd-label">
                    Background opacity (0–100)
                    <input type="number" name="style[bg_opacity]" min="0" max="100" class="bd-input"
                           :value="getStyle('bg_opacity')"
                           @input="setStyle('bg_opacity', $event.target.value)"
                           placeholder="{{ $systemStyle['bg_opacity'] ?? '100' }}">
                </label>
            </div>

            {{-- Border --}}
            <div class="bd-section-title">Border</div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <label class="bd-label">
                    Border style
                    <select name="style[border_style]" class="bd-select"
                            x-model="styleData.border_style"
                            @change="setStyle('border_style', $event.target.value)">
                        <option value="">— system default —</option>
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

            {{-- Shadow & Effect --}}
            <div class="bd-section-title">Shadow &amp; Effect</div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <label class="bd-label">
                    Shadow preset
                    <select name="style[shadow_preset]" class="bd-select"
                            x-model="styleData.shadow_preset"
                            @change="setStyle('shadow_preset', $event.target.value)">
                        <option value="">— system default ({{ $systemStyle['shadow_preset'] ?? 'none' }}) —</option>
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
                        <option value="">— system default ({{ $systemStyle['glass_preset'] ?? 'off' }}) —</option>
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
                        <option value="">— system default —</option>
                        @foreach(['none','blur','glow','gradient','shimmer'] as $ef)
                            <option value="{{ $ef }}">{{ $ef }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>

        {{-- Content overrides --}}
        <div class="glass rounded-2xl border border-white/10 p-5 mb-4">
            <div class="flex items-start justify-between gap-4 mb-3">
                <div>
                    <h3 class="text-sm font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-file-lines mr-1.5" style="color: var(--accent-light);"></i>
                        Content overrides (JSON)
                    </h3>
                    <p class="text-xs mt-1" style="color: var(--text-faint);">
                        Override sample text, placeholder media URLs, button labels, etc.
                        Fields not listed here fall back to the system default.
                        <strong>Do not include <code>_style</code> or <code>_placeholder</code> keys.</strong>
                    </p>
                </div>
                <button type="button" @click="resetJson()"
                        class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                        style="background: rgba(255,255,255,0.06); border: 1px solid var(--border-glass); color: var(--text-dimmed);"
                        title="Load system defaults into the editor">
                    <i class="fas fa-arrow-rotate-left mr-1"></i> Load system defaults
                </button>
            </div>

            {{-- System defaults preview --}}
            <details class="mb-3">
                <summary class="cursor-pointer text-xs font-semibold" style="color: var(--text-faint);">
                    <i class="fas fa-eye mr-1"></i> View system content defaults
                </summary>
                <pre class="mt-2 p-3 rounded-xl text-xs overflow-auto"
                     style="background: var(--bg-glass); border: 1px solid var(--border-glass); color: var(--text-dimmed); max-height: 200px;">{{ json_encode($systemContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </details>

            <textarea name="content_json"
                      x-model="contentJson"
                      rows="14"
                      placeholder='Leave blank to use the system default, or paste a JSON object with only the fields you want to override, e.g.:&#10;{&#10;  "title": "My company",&#10;  "url": "https://example.com"&#10;}'
                      class="w-full rounded-xl px-3 py-3 font-mono text-xs resize-y"
                      spellcheck="false"
                      style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary); min-height: 180px;"></textarea>
            <p class="text-[11px] mt-1.5" style="color: var(--text-faint);">
                Enter a JSON object with the keys you want to override, or leave blank to use system defaults.
            </p>
        </div>

        {{-- Actions --}}
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
    </form>
</div>

<style>
    .bd-section-title {
        font-size: 10px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.1em; color: var(--text-faint);
        margin-bottom: 8px; margin-top: 4px;
    }
    .bd-label {
        display: flex; flex-direction: column; gap: 4px;
        font-size: 10px; font-weight: 600; text-transform: uppercase;
        letter-spacing: 0.05em; color: var(--text-faint);
    }
    .bd-input {
        padding: 6px 9px; border-radius: 7px;
        border: 1px solid var(--border-glass);
        background: var(--bg-glass-input); color: var(--text-primary);
        font-size: 13px; font-weight: 500; text-transform: none;
        letter-spacing: 0; font-family: inherit;
    }
    .bd-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-glow); }
    .bd-select {
        padding: 6px 9px; border-radius: 7px;
        border: 1px solid var(--border-glass);
        background: var(--bg-glass-input); color: var(--text-primary);
        font-size: 13px; text-transform: none; letter-spacing: 0;
    }
    .bd-select:focus { outline: none; border-color: var(--accent); }
    .bd-color {
        width: 36px; height: 34px; border-radius: 7px; cursor: pointer;
        border: 1px solid var(--border-glass); background: var(--bg-glass-input); padding: 2px;
    }
</style>
@endsection
