@php
    $st = $block->settings['_style'] ?? [];
    $templates = \App\Modules\User\Models\BiolinkBlock::BLOCK_TEMPLATES;
    $fonts = ['', 'Space Grotesk', 'Inter', 'Poppins', 'Roboto', 'Playfair Display', 'Montserrat', 'DM Sans', 'Outfit', 'Clash Display'];
    $weights = ['' => 'Default', '300' => 'Light', '400' => 'Regular', '500' => 'Medium', '600' => 'Semi Bold', '700' => 'Bold', '800' => 'Extra Bold', '900' => 'Black'];
    $borderStyles = ['none' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double', 'groove' => 'Groove', 'ridge' => 'Ridge'];
    $shadowTypes = ['none' => 'None', 'soft' => 'Soft', 'hard' => 'Hard', 'neon' => 'Neon Glow', 'glow' => 'Subtle Glow', 'neumorphic' => 'Neumorphic', 'inset' => 'Inner Shadow'];
    $effects = ['none' => 'None', 'glass' => 'Glassmorphism', 'gradient_border' => 'Gradient Border'];
@endphp

<div class="mt-4 pt-4" style="border-top: 1px solid var(--border-subtle);" x-data="{ showStyle: false, activeStyleTab: 'templates' }">
    <button type="button" @click="showStyle = !showStyle"
            class="w-full flex items-center justify-between text-sm font-medium py-1" style="color: var(--text-muted);">
        <span><i class="fas fa-wand-magic-sparkles mr-2 text-pink-400"></i>Block Styling</span>
        <i :class="showStyle ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-xs"></i>
    </button>

    <div x-show="showStyle" x-cloak x-transition class="mt-3">

        <div class="flex gap-1 mb-4 p-0.5 rounded-lg" style="background: var(--bg-glass-input);">
            <button type="button" @click="activeStyleTab = 'templates'"
                    :class="activeStyleTab === 'templates' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'templates' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-magic mr-1"></i>Templates
            </button>
            <button type="button" @click="activeStyleTab = 'typography'"
                    :class="activeStyleTab === 'typography' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'typography' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-font mr-1"></i>Text
            </button>
            <button type="button" @click="activeStyleTab = 'background'"
                    :class="activeStyleTab === 'background' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'background' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-fill-drip mr-1"></i>Fill
            </button>
            <button type="button" @click="activeStyleTab = 'border'"
                    :class="activeStyleTab === 'border' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'border' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-border-all mr-1"></i>Border
            </button>
            <button type="button" @click="activeStyleTab = 'effects'"
                    :class="activeStyleTab === 'effects' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'effects' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-sparkles mr-1"></i>FX
            </button>
        </div>

        {{-- TEMPLATES TAB --}}
        <div x-show="activeStyleTab === 'templates'" class="space-y-2">
            <p class="text-[10px] mb-2" style="color: var(--text-dimmed);"><i class="fas fa-info-circle mr-1"></i>Click a template to apply its style instantly</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach($templates as $tKey => $tpl)
                <button type="button" class="p-3 rounded-xl text-left transition-all hover:scale-[1.03]"
                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"
                        onclick="applyBlockTemplate('{{ $tKey }}', this)">
                    <div class="flex items-center gap-2 mb-1.5">
                        <div class="w-6 h-6 rounded-md flex items-center justify-center" style="background: {{ $tpl['preview_bg'] }}; border: 1px solid {{ $tpl['preview_bg'] }}30;">
                            <i class="fas {{ $tpl['icon'] }} text-[9px]" style="color: {{ $tpl['preview_text'] }};"></i>
                        </div>
                        <span class="text-[11px] font-semibold" style="color: var(--text-primary);">{{ $tpl['label'] }}</span>
                    </div>
                    <div class="h-5 rounded-md" style="background: {{ $tpl['preview_bg'] }}; border: 1px solid {{ $tpl['preview_text'] }}20; opacity: 0.6;"></div>
                </button>
                @endforeach
            </div>
        </div>

        {{-- TYPOGRAPHY TAB --}}
        <div x-show="activeStyleTab === 'typography'" class="space-y-3">
            <div>
                <label class="{{ $labelClass }}">Font Family</label>
                <select name="style[font_family]" class="{{ $inputClass }}">
                    <option value="">Inherit from page</option>
                    @foreach($fonts as $f)
                    @if($f) <option value="{{ $f }}" {{ ($st['font_family'] ?? '') === $f ? 'selected' : '' }}>{{ $f }}</option> @endif
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $labelClass }}">Font Size (px)</label>
                    <input type="number" name="style[font_size]" value="{{ $st['font_size'] ?? '' }}" placeholder="Auto" min="8" max="72" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Font Weight</label>
                    <select name="style[font_weight]" class="{{ $inputClass }}">
                        @foreach($weights as $wVal => $wLabel)
                        <option value="{{ $wVal }}" {{ ($st['font_weight'] ?? '') == $wVal ? 'selected' : '' }}>{{ $wLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $labelClass }}">Font Style</label>
                    <select name="style[font_style]" class="{{ $inputClass }}">
                        <option value="normal" {{ ($st['font_style'] ?? 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                        <option value="italic" {{ ($st['font_style'] ?? '') === 'italic' ? 'selected' : '' }}>Italic</option>
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Text Color</label>
                    <div class="flex gap-2">
                        <input type="color" name="style[text_color]" value="{{ $st['text_color'] ?? '#ffffff' }}" class="w-10 h-9 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                        <input type="text" value="{{ $st['text_color'] ?? '' }}" placeholder="Inherit" class="{{ $inputClass }} flex-1" oninput="this.previousElementSibling.value = this.value" onchange="this.previousElementSibling.value = this.value">
                    </div>
                </div>
            </div>
        </div>

        {{-- BACKGROUND TAB --}}
        <div x-show="activeStyleTab === 'background'" class="space-y-3">
            <div>
                <label class="{{ $labelClass }}">Display Mode</label>
                <div class="grid grid-cols-2 gap-2" x-data="{ mode: '{{ $st['display_mode'] ?? 'card' }}' }">
                    <label class="flex items-center gap-2 p-2.5 rounded-lg cursor-pointer transition-all" :style="mode === 'card' ? 'background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.3);' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass);'">
                        <input type="radio" name="style[display_mode]" value="card" x-model="mode" class="hidden">
                        <i class="fas fa-square text-xs" :style="mode === 'card' ? 'color: #8b5cf6;' : 'color: var(--text-faint);'"></i>
                        <span class="text-xs font-medium" :style="mode === 'card' ? 'color: #8b5cf6;' : 'color: var(--text-muted);'">Card</span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-lg cursor-pointer transition-all" :style="mode === 'content' ? 'background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.3);' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass);'">
                        <input type="radio" name="style[display_mode]" value="content" x-model="mode" class="hidden">
                        <i class="fas fa-align-left text-xs" :style="mode === 'content' ? 'color: #8b5cf6;' : 'color: var(--text-faint);'"></i>
                        <span class="text-xs font-medium" :style="mode === 'content' ? 'color: #8b5cf6;' : 'color: var(--text-muted);'">Content Only</span>
                    </label>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $labelClass }}">Background Color</label>
                    <input type="color" name="style[bg_color]" value="{{ $st['bg_color'] ?? '#ffffff0d' }}" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Opacity (%)</label>
                    <input type="number" name="style[bg_opacity]" value="{{ $st['bg_opacity'] ?? 100 }}" min="0" max="100" class="{{ $inputClass }}">
                </div>
            </div>
            <div>
                <label class="{{ $labelClass }}">Background Image URL</label>
                <input type="url" name="style[bg_image]" value="{{ $st['bg_image'] ?? '' }}" placeholder="https://..." class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Padding (px)</label>
                <input type="number" name="style[padding]" value="{{ $st['padding'] ?? '' }}" placeholder="Auto" min="0" max="60" class="{{ $inputClass }}">
            </div>
        </div>

        {{-- BORDER TAB --}}
        <div x-show="activeStyleTab === 'border'" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $labelClass }}">Border Style</label>
                    <select name="style[border_style]" class="{{ $inputClass }}">
                        @foreach($borderStyles as $bsVal => $bsLabel)
                        <option value="{{ $bsVal }}" {{ ($st['border_style'] ?? 'none') === $bsVal ? 'selected' : '' }}>{{ $bsLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Border Width (px)</label>
                    <input type="number" name="style[border_width]" value="{{ $st['border_width'] ?? '' }}" placeholder="1" min="0" max="10" class="{{ $inputClass }}">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $labelClass }}">Border Color</label>
                    <input type="color" name="style[border_color]" value="{{ $st['border_color'] ?? '#ffffff15' }}" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Border Radius (px)</label>
                    <input type="number" name="style[border_radius]" value="{{ $st['border_radius'] ?? '' }}" placeholder="12" min="0" max="999" class="{{ $inputClass }}">
                </div>
            </div>

            <div class="pt-2" style="border-top: 1px solid var(--border-subtle);">
                <label class="{{ $labelClass }}">Shadow Type</label>
                <select name="style[shadow_type]" class="{{ $inputClass }}">
                    @foreach($shadowTypes as $shVal => $shLabel)
                    <option value="{{ $shVal }}" {{ ($st['shadow_type'] ?? 'none') === $shVal ? 'selected' : '' }}>{{ $shLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $labelClass }}">Shadow Color</label>
                    <input type="color" name="style[shadow_color]" value="{{ $st['shadow_color'] ?? '#000000' }}" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Shadow Blur (px)</label>
                    <input type="number" name="style[shadow_blur]" value="{{ $st['shadow_blur'] ?? 12 }}" min="0" max="100" class="{{ $inputClass }}">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="{{ $labelClass }}">X Offset</label>
                    <input type="number" name="style[shadow_x]" value="{{ $st['shadow_x'] ?? 0 }}" min="-50" max="50" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Y Offset</label>
                    <input type="number" name="style[shadow_y]" value="{{ $st['shadow_y'] ?? 4 }}" min="-50" max="50" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Spread</label>
                    <input type="number" name="style[shadow_spread]" value="{{ $st['shadow_spread'] ?? 0 }}" min="-20" max="50" class="{{ $inputClass }}">
                </div>
            </div>
        </div>

        {{-- EFFECTS TAB --}}
        <div x-show="activeStyleTab === 'effects'" class="space-y-3">
            <div>
                <label class="{{ $labelClass }}">Effect</label>
                <select name="style[effect]" class="{{ $inputClass }}" x-data="{ effect: '{{ $st['effect'] ?? 'none' }}' }" x-model="effect" x-ref="effectSelect">
                    @foreach($effects as $eVal => $eLabel)
                    <option value="{{ $eVal }}" {{ ($st['effect'] ?? 'none') === $eVal ? 'selected' : '' }}>{{ $eLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $labelClass }}">Glass Blur (px)</label>
                <input type="number" name="style[glass_blur]" value="{{ $st['glass_blur'] ?? 20 }}" min="0" max="100" class="{{ $inputClass }}">
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Only applies with Glassmorphism effect</p>
            </div>
            <div>
                <label class="{{ $labelClass }}">Glass Opacity (%)</label>
                <input type="number" name="style[glass_opacity]" value="{{ $st['glass_opacity'] ?? 15 }}" min="0" max="100" class="{{ $inputClass }}">
            </div>
        </div>

    </div>
</div>

<script>
var blockTemplates = @json($templates);
function applyBlockTemplate(key, btn) {
    var tpl = blockTemplates[key];
    if (!tpl) return;
    var form = btn.closest('form');
    if (!form) return;
    var style = tpl.style;
    for (var prop in style) {
        var input = form.querySelector('[name="style[' + prop + ']"]');
        if (input) {
            input.value = style[prop];
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    btn.style.transform = 'scale(0.95)';
    setTimeout(function() { btn.style.transform = ''; }, 150);
    var label = btn.querySelector('span');
    if (label) {
        var orig = label.textContent;
        label.textContent = 'Applied!';
        label.style.color = '#10b981';
        setTimeout(function() { label.textContent = orig; label.style.color = ''; }, 1000);
    }
}
</script>
