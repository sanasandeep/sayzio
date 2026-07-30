{{--
    Friendly visual style editor for the admin Block Designs forms
    (Task #6054). Replaces the raw style JSON textarea with the same
    kind of controls users get in the biolink editor's Style tab
    (color pickers, sliders, dropdowns), while keeping `style_json`
    as the single submitted field so the controller contract is
    unchanged. Exotic keys (per-side borders, _photo_* decorations,
    stickers…) survive untouched via the Advanced JSON panel — the
    controls only edit the keys they own and re-serialize the whole
    object.

    Expects:
      $styleJson      — the JSON string (old-input aware) to seed from
      $sampleLabel    — text shown in the live preview chip
      $showLinkLayout — offer the link_layout dropdown (default true)
--}}
@php
    use App\Modules\User\Support\BlockStyleSanitizer;
    $showLinkLayout = $showLinkLayout ?? true;
    $sampleLabel = $sampleLabel ?? 'Sample block';
    $bdDecoded = json_decode($styleJson, true);
    $bdInvalid = !is_array($bdDecoded);
    $bdInitial = $bdInvalid ? (object) [] : (object) $bdDecoded;
    $bdFontValue = (!$bdInvalid && !empty($bdDecoded['font_family'])) ? (string) $bdDecoded['font_family'] : '';
@endphp

<div x-data="bdStyleEditor(@js($bdInitial), @js($styleJson), {{ $bdInvalid ? 'true' : 'false' }})" class="space-y-5">

    {{-- ── Text ── --}}
    <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
        <div class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--text-faint);">
            <i class="fas fa-font mr-1 opacity-60"></i> Text
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2">
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Font family</label>
                <div @change="if ($event.target.matches('input[type=hidden]')) set('font_family', $event.target.value)">
                    @include('user.links.partials.font-picker', [
                        'name' => 'ui_font_family',
                        'value' => $bdFontValue,
                        'pickerId' => 'bdsFontFamily',
                        'allowInherit' => true,
                        'inheritLabel' => 'No override (inherit)',
                        'hideCustomFonts' => true,
                    ])
                </div>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">
                    Font size <span class="text-white/40" x-text="get('font_size') !== '' ? get('font_size') + 'px' : 'default'"></span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="range" min="8" max="72" step="1" class="flex-1"
                           :value="get('font_size') === '' ? 16 : get('font_size')"
                           @input="set('font_size', $event.target.value)">
                    <button type="button" class="text-[11px] text-white/40 hover:text-white/70 ak-muted"
                            x-show="get('font_size') !== ''" x-cloak @click="set('font_size', '')">reset</button>
                </div>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Font weight</label>
                <select class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/80"
                        :value="get('font_weight')" @change="set('font_weight', $event.target.value)">
                    <option value="">Default</option>
                    @foreach(['300' => 'Light (300)', '400' => 'Regular (400)', '500' => 'Medium (500)', '600' => 'Semibold (600)', '700' => 'Bold (700)', '800' => 'Extrabold (800)', '900' => 'Black (900)'] as $w => $wl)
                        <option value="{{ $w }}">{{ $wl }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Font style</label>
                <select class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/80"
                        :value="get('font_style')" @change="set('font_style', $event.target.value)">
                    <option value="">Default</option>
                    <option value="normal">Normal</option>
                    <option value="italic">Italic</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Text color</label>
                <div class="flex items-center gap-2">
                    <input type="color" class="h-9 w-10 rounded-lg border border-white/10 bg-transparent cursor-pointer"
                           :value="hexOr(get('text_color'), '#ffffff')"
                           @input="set('text_color', $event.target.value)">
                    <input type="text" placeholder="none" class="flex-1 text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/90"
                           :value="get('text_color')" @input="set('text_color', $event.target.value)">
                </div>
            </div>
        </div>
    </div>

    {{-- ── Background ── --}}
    <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
        <div class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--text-faint);">
            <i class="fas fa-fill-drip mr-1 opacity-60"></i> Background
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Background color <span class="text-white/35">(hex, rgb or CSS gradient)</span></label>
                <div class="flex items-center gap-2">
                    <input type="color" class="h-9 w-10 rounded-lg border border-white/10 bg-transparent cursor-pointer"
                           :value="hexOr(get('bg_color'), '#1a1a2e')"
                           @input="set('bg_color', $event.target.value)">
                    <input type="text" placeholder="none" class="flex-1 text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/90"
                           :value="get('bg_color')" @input="set('bg_color', $event.target.value)">
                </div>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">
                    Background opacity <span class="text-white/40" x-text="get('bg_opacity') !== '' ? get('bg_opacity') + '%' : '100%'"></span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="range" min="0" max="100" step="1" class="flex-1"
                           :value="get('bg_opacity') === '' ? 100 : get('bg_opacity')"
                           @input="set('bg_opacity', $event.target.value)">
                    <button type="button" class="text-[11px] text-white/40 hover:text-white/70 ak-muted"
                            x-show="get('bg_opacity') !== ''" x-cloak @click="set('bg_opacity', '')">reset</button>
                </div>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Background image URL</label>
                <input type="url" placeholder="https://…" class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/90"
                       :value="get('bg_image')" @input="set('bg_image', $event.target.value)">
            </div>
        </div>
    </div>

    {{-- ── Border ── --}}
    <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
        <div class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--text-faint);">
            <i class="far fa-square mr-1 opacity-60"></i> Border &amp; corners
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Border style</label>
                <select class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/80"
                        :value="get('border_style')" @change="set('border_style', $event.target.value)">
                    <option value="">Default</option>
                    @foreach(['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge'] as $bs)
                        <option value="{{ $bs }}">{{ ucfirst($bs) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Border color</label>
                <div class="flex items-center gap-2">
                    <input type="color" class="h-9 w-10 rounded-lg border border-white/10 bg-transparent cursor-pointer"
                           :value="hexOr(get('border_color'), '#ffffff')"
                           @input="set('border_color', $event.target.value)">
                    <input type="text" placeholder="none" class="flex-1 text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/90"
                           :value="get('border_color')" @input="set('border_color', $event.target.value)">
                </div>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">
                    Border width <span class="text-white/40" x-text="get('border_width') !== '' ? get('border_width') + 'px' : 'default'"></span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="range" min="0" max="10" step="1" class="flex-1"
                           :value="get('border_width') === '' ? 1 : get('border_width')"
                           @input="set('border_width', $event.target.value)">
                    <button type="button" class="text-[11px] text-white/40 hover:text-white/70 ak-muted"
                            x-show="get('border_width') !== ''" x-cloak @click="set('border_width', '')">reset</button>
                </div>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">
                    Corner radius <span class="text-white/40" x-text="get('border_radius') !== '' ? get('border_radius') + 'px' : 'default'"></span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="range" min="0" max="60" step="1" class="flex-1"
                           :value="get('border_radius') === '' ? 14 : Math.min(60, get('border_radius'))"
                           @input="set('border_radius', $event.target.value)">
                    <input type="number" min="0" max="999" class="w-16 text-xs rounded-lg px-2 py-1.5 border border-white/10 bg-white/5 text-white/80"
                           :value="get('border_radius')" @input="set('border_radius', $event.target.value)">
                </div>
            </div>
        </div>
    </div>

    {{-- ── Shadow ── --}}
    <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
        <div class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--text-faint);">
            <i class="fas fa-clone mr-1 opacity-60"></i> Shadow
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Shadow type</label>
                <select class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/80"
                        :value="get('shadow_type')" @change="set('shadow_type', $event.target.value)">
                    <option value="">Default</option>
                    @foreach(['none', 'soft', 'hard', 'neon', 'glow', 'neumorphic', 'inset'] as $st)
                        <option value="{{ $st }}">{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Shadow color</label>
                <div class="flex items-center gap-2">
                    <input type="color" class="h-9 w-10 rounded-lg border border-white/10 bg-transparent cursor-pointer"
                           :value="hexOr(get('shadow_color'), '#000000')"
                           @input="set('shadow_color', $event.target.value)">
                    <input type="text" placeholder="none" class="flex-1 min-w-0 text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/90"
                           :value="get('shadow_color')" @input="set('shadow_color', $event.target.value)">
                </div>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">
                    Blur <span class="text-white/40" x-text="get('shadow_blur') !== '' ? get('shadow_blur') + 'px' : 'default'"></span>
                </label>
                <input type="range" min="0" max="100" step="1" class="w-full mt-2"
                       :value="get('shadow_blur') === '' ? 12 : get('shadow_blur')"
                       @input="set('shadow_blur', $event.target.value)">
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">
                    Offset X <span class="text-white/40" x-text="get('shadow_x') !== '' ? get('shadow_x') + 'px' : '0'"></span>
                </label>
                <input type="range" min="-50" max="50" step="1" class="w-full mt-2"
                       :value="get('shadow_x') === '' ? 0 : get('shadow_x')"
                       @input="set('shadow_x', $event.target.value)">
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">
                    Offset Y <span class="text-white/40" x-text="get('shadow_y') !== '' ? get('shadow_y') + 'px' : '0'"></span>
                </label>
                <input type="range" min="-50" max="50" step="1" class="w-full mt-2"
                       :value="get('shadow_y') === '' ? 0 : get('shadow_y')"
                       @input="set('shadow_y', $event.target.value)">
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">
                    Spread <span class="text-white/40" x-text="get('shadow_spread') !== '' ? get('shadow_spread') + 'px' : '0'"></span>
                </label>
                <input type="range" min="-20" max="50" step="1" class="w-full mt-2"
                       :value="get('shadow_spread') === '' ? 0 : get('shadow_spread')"
                       @input="set('shadow_spread', $event.target.value)">
            </div>
        </div>
    </div>

    {{-- ── Effects & layout ── --}}
    <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
        <div class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--text-faint);">
            <i class="fas fa-wand-magic-sparkles mr-1 opacity-60"></i> Effects &amp; layout
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Glass effect</label>
                <select class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/80"
                        :value="get('glass_preset')" @change="set('glass_preset', $event.target.value)">
                    <option value="">Default</option>
                    <option value="off">Off</option>
                    <option value="light">Light</option>
                    <option value="heavy">Heavy</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Shadow preset</label>
                <select class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/80"
                        :value="get('shadow_preset')" @change="set('shadow_preset', $event.target.value)">
                    <option value="">Default</option>
                    @foreach(['none', 'soft', 'medium', 'strong'] as $sp)
                        <option value="{{ $sp }}">{{ ucfirst($sp) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Special effect</label>
                <select class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/80"
                        :value="get('effect')" @change="set('effect', $event.target.value)">
                    <option value="">Default</option>
                    <option value="none">None</option>
                    <option value="glass">Glass</option>
                    <option value="gradient_border">Gradient border</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Display mode</label>
                <select class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/80"
                        :value="get('display_mode')" @change="set('display_mode', $event.target.value)">
                    <option value="">Default</option>
                    <option value="card">Card</option>
                    <option value="content">Content only</option>
                </select>
            </div>
            @if($showLinkLayout)
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">Link layout</label>
                <select class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/80"
                        :value="get('link_layout')" @change="set('link_layout', $event.target.value)">
                    <option value="">Default button</option>
                    @foreach(BlockStyleSanitizer::LINK_LAYOUTS as $ll)
                        <option value="{{ $ll }}">{{ ucfirst(str_replace('_', ' ', $ll)) }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="block text-[11px] text-white/60 mb-1 ak-muted">
                    Padding <span class="text-white/40" x-text="get('padding') !== '' ? get('padding') + 'px' : 'default'"></span>
                </label>
                <input type="range" min="0" max="60" step="1" class="w-full mt-2"
                       :value="get('padding') === '' ? 14 : get('padding')"
                       @input="set('padding', $event.target.value)">
            </div>
        </div>
    </div>

    {{-- ── Live preview ── --}}
    <div>
        <label class="block text-xs font-bold uppercase tracking-widest mb-1.5" style="color: var(--text-faint);">Live preview</label>
        <div class="rounded-xl border border-white/10 p-6 flex items-center justify-center" style="background: #101223;">
            <div class="px-6 py-3 text-sm font-semibold"
                 style="min-width:200px;text-align:center;"
                 :style="previewCss()">
                {{ $sampleLabel }}
            </div>
        </div>
    </div>

    {{-- ── Advanced: raw JSON (still the submitted field) ── --}}
    <div x-data="{ showJson: {{ $bdInvalid ? 'true' : 'false' }} }">
        <button type="button" class="inline-flex items-center gap-2 text-xs text-white/50 hover:text-white/80 ak-muted"
                @click="showJson = !showJson">
            <i class="fas" :class="showJson ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
            Advanced: edit raw style JSON
        </button>
        <div x-show="showJson" x-collapse x-cloak class="mt-2">
            <p class="text-[11px] text-white/35 mb-2 ak-muted">
                Full payload, including properties the controls above don't cover
                (per-side borders, per-corner radius, photo decorations&hellip;).
                Unknown or invalid properties are dropped on save.
            </p>
            <textarea name="style_json" rows="12" required spellcheck="false"
                      class="w-full text-xs font-mono rounded-xl px-3 py-2 border bg-black/30 text-white/90"
                      :class="jsonError ? 'border-red-500/50' : 'border-white/10'"
                      x-model="jsonText" @input="fromJson()"></textarea>
            <p class="text-[11px] text-red-300 mt-1" x-show="jsonError" x-cloak x-text="jsonError"></p>
        </div>
        {{-- Keep the textarea submitted even while collapsed: x-show only hides it. --}}
    </div>
</div>

@once
<script>
function bdStyleEditor(initial, rawJson, invalid) {
    return {
        s: initial || {},
        jsonText: invalid ? rawJson : JSON.stringify(initial || {}, null, 2),
        jsonError: invalid ? 'Not valid JSON: fix it here or use the controls above (they will overwrite it).' : '',
        get(key) {
            const v = this.s[key];
            return (v === undefined || v === null) ? '' : v;
        },
        set(key, val) {
            if (val === '' || val === null || val === undefined) {
                delete this.s[key];
                this.s = { ...this.s };
            } else {
                const numeric = ['font_size', 'bg_opacity', 'border_width', 'border_radius',
                                 'shadow_x', 'shadow_y', 'shadow_blur', 'shadow_spread', 'padding'];
                this.s = { ...this.s, [key]: numeric.includes(key) && !isNaN(Number(val)) ? Number(val) : val };
            }
            this.jsonText = JSON.stringify(this.s, null, 2);
            this.jsonError = '';
        },
        fromJson() {
            try {
                const parsed = JSON.parse(this.jsonText);
                if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                    this.jsonError = 'Style must be a JSON object.';
                    return;
                }
                this.s = parsed;
                this.jsonError = '';
                // Keep the font picker trigger in sync when JSON changes the font.
                window.dispatchEvent(new CustomEvent('font-picker-set', {
                    detail: { pickerId: 'bdsFontFamily', value: parsed.font_family || '' },
                }));
            } catch (e) {
                this.jsonError = 'Not valid JSON: ' + e.message;
            }
        },
        hexOr(val, fallback) {
            return /^#[0-9a-fA-F]{6}$/.test(String(val)) ? val : fallback;
        },
        previewCss() {
            const s = this.s;
            const css = {};
            css.background = s.bg_color || '#1a1a2e';
            css.color = s.text_color || '#fff';
            css.borderRadius = (s.border_radius != null ? s.border_radius : 14) + 'px';
            if (s.border_color && s.border_style && s.border_style !== 'none') {
                css.border = (s.border_width || 1) + 'px ' + s.border_style + ' ' + s.border_color;
            } else {
                css.border = 'none';
            }
            if (s.shadow_type && s.shadow_type !== 'none' && s.shadow_color) {
                css.boxShadow = (s.shadow_type === 'inset' ? 'inset ' : '')
                    + (s.shadow_x || 0) + 'px ' + (s.shadow_y || 4) + 'px '
                    + (s.shadow_blur != null ? s.shadow_blur : 12) + 'px '
                    + (s.shadow_spread || 0) + 'px ' + s.shadow_color;
            } else {
                css.boxShadow = 'none';
            }
            if (s.font_family) {
                const fam = String(s.font_family).replace(/^custom:/, '');
                css.fontFamily = "'" + fam + "', sans-serif";
            }
            if (s.font_size) css.fontSize = s.font_size + 'px';
            if (s.font_weight) css.fontWeight = s.font_weight;
            if (s.font_style) css.fontStyle = s.font_style;
            if (s.padding != null) css.padding = s.padding + 'px';
            if (s.bg_opacity != null) css.opacity = Math.max(0.15, Math.min(1, s.bg_opacity / 100));
            return css;
        },
    };
}
</script>
@endonce
