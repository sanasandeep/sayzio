@php
    $st = $block->settings['_style'] ?? [];
    $templates = \App\Modules\User\Models\BiolinkBlock::BLOCK_TEMPLATES;
    $fonts = ['', 'Space Grotesk', 'Inter', 'Poppins', 'Roboto', 'Playfair Display', 'Montserrat', 'DM Sans', 'Outfit', 'Clash Display'];
    $weights = ['' => 'Default', '300' => 'Light', '400' => 'Regular', '500' => 'Medium', '600' => 'Semi Bold', '700' => 'Bold', '800' => 'Extra Bold', '900' => 'Black'];
    $borderStyles = ['none' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'];
    $shadowTypes = ['none' => 'None', 'soft' => 'Soft', 'hard' => 'Hard', 'neon' => 'Neon Glow', 'glow' => 'Subtle Glow', 'neumorphic' => 'Neumorphic', 'inset' => 'Inner Shadow'];
    $effects = ['none' => 'None', 'glass' => 'Glassmorphism', 'gradient_border' => 'Gradient Border'];

    $noStyleBlocks = ['spacer', 'divider'];
    $noTextBlocks = [
        'avatar', 'image', 'image_grid', 'image_slider', 'image_slider_v2',
        'video', 'header_video', 'audio', 'spacer', 'divider',
        'map', 'yandex_maps', 'iframe_embed', 'custom_html',
        'spotify', 'apple_music', 'soundcloud', 'tidal', 'mixcloud', 'anchor_fm',
        'youtube', 'youtube_feed', 'vimeo', 'twitch', 'kick', 'rumble_video', 'vk_video',
        'instagram_media', 'tiktok_video', 'tiktok_profile', 'twitter_video',
        'facebook_post', 'reddit_post', 'telegram_post', 'discord_server',
        'pdf_document', 'powerpoint', 'excel', 'qr_code',
    ];
    $showText = !in_array($block->type, $noTextBlocks);
    $showStyle = !in_array($block->type, $noStyleBlocks);
@endphp

@if($showStyle)
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
                <i class="fas fa-magic mr-1"></i>Presets
            </button>
            @if($showText)
            <button type="button" @click="activeStyleTab = 'typography'"
                    :class="activeStyleTab === 'typography' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'typography' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-font mr-1"></i>Text
            </button>
            @endif
            <button type="button" @click="activeStyleTab = 'appearance'"
                    :class="activeStyleTab === 'appearance' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'appearance' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-palette mr-1"></i>Look
            </button>
            <button type="button" @click="activeStyleTab = 'spacing'"
                    :class="activeStyleTab === 'spacing' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'spacing' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-arrows-alt mr-1"></i>Layout
            </button>
        </div>

        {{-- PRESETS TAB --}}
        <div x-show="activeStyleTab === 'templates'" class="space-y-2" x-data="{ selectedTemplate: '{{ $st['_template'] ?? '' }}' }">
            <input type="hidden" name="style[_template]" :value="selectedTemplate">
            <p class="text-[10px] mb-2" style="color: var(--text-dimmed);"><i class="fas fa-info-circle mr-1"></i>Click a preset to apply its style instantly</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach($templates as $tKey => $tpl)
                <button type="button" class="p-3 rounded-xl text-left transition-all hover:scale-[1.03] relative"
                        :style="selectedTemplate === '{{ $tKey }}' ? 'background: rgba(139,92,246,0.12); border: 2px solid rgba(139,92,246,0.6); box-shadow: 0 0 12px rgba(139,92,246,0.15);' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass);'"
                        @click="selectedTemplate = '{{ $tKey }}'; applyBlockTemplate('{{ $tKey }}', $el)">
                    <div class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full flex items-center justify-center transition-all"
                         :style="selectedTemplate === '{{ $tKey }}' ? 'background: #8b5cf6; opacity: 1;' : 'opacity: 0;'">
                        <i class="fas fa-check text-white text-[8px]"></i>
                    </div>
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

        @if($showText)
        {{-- TEXT TAB --}}
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
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="{{ $labelClass }}">Size (px)</label>
                    <input type="number" name="style[font_size]" value="{{ $st['font_size'] ?? '' }}" placeholder="Auto" min="8" max="72" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Weight</label>
                    <select name="style[font_weight]" class="{{ $inputClass }}">
                        @foreach($weights as $wVal => $wLabel)
                        <option value="{{ $wVal }}" {{ ($st['font_weight'] ?? '') == $wVal ? 'selected' : '' }}>{{ $wLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Style</label>
                    <select name="style[font_style]" class="{{ $inputClass }}">
                        <option value="normal" {{ ($st['font_style'] ?? 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                        <option value="italic" {{ ($st['font_style'] ?? '') === 'italic' ? 'selected' : '' }}>Italic</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="{{ $labelClass }}">Text Color</label>
                <div class="flex gap-2">
                    <input type="color" name="style[text_color]" value="{{ $st['text_color'] ?? '#ffffff' }}" class="w-10 h-9 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                    <input type="text" value="{{ $st['text_color'] ?? '' }}" placeholder="Inherit" class="{{ $inputClass }} flex-1" oninput="this.previousElementSibling.value = this.value" onchange="this.previousElementSibling.value = this.value">
                </div>
            </div>
        </div>
        @endif

        {{-- LOOK TAB (merged Fill + Border + FX) --}}
        <div x-show="activeStyleTab === 'appearance'" class="space-y-4" x-data="{ showAdvanced: false }">

            {{-- Display Mode --}}
            <div>
                <label class="{{ $labelClass }}">Display Mode</label>
                <div class="grid grid-cols-2 gap-2" x-data="{ mode: '{{ $st['display_mode'] ?? 'card' }}' }">
                    <label class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-all text-xs font-medium" :style="mode === 'card' ? 'background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.3); color: #8b5cf6;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);'">
                        <input type="radio" name="style[display_mode]" value="card" x-model="mode" class="hidden">
                        <i class="fas fa-square text-xs"></i> Card
                    </label>
                    <label class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-all text-xs font-medium" :style="mode === 'content' ? 'background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.3); color: #8b5cf6;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);'">
                        <input type="radio" name="style[display_mode]" value="content" x-model="mode" class="hidden">
                        <i class="fas fa-align-left text-xs"></i> Content Only
                    </label>
                </div>
            </div>

            {{-- Background Color --}}
            <div>
                <label class="{{ $labelClass }}">Background Color</label>
                <div class="flex gap-2">
                    <input type="color" name="style[bg_color]" value="{{ $st['bg_color'] ?? '#ffffff0d' }}" class="w-10 h-9 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                    <input type="text" value="{{ $st['bg_color'] ?? '' }}" placeholder="Transparent" class="{{ $inputClass }} flex-1" oninput="this.previousElementSibling.value = this.value" onchange="this.previousElementSibling.value = this.value">
                </div>
            </div>
            <input type="hidden" name="style[bg_opacity]" value="{{ $st['bg_opacity'] ?? 100 }}">

            {{-- Effect --}}
            <div x-data="{ effect: '{{ $st['effect'] ?? 'none' }}' }">
                <label class="{{ $labelClass }}">Effect</label>
                <div class="grid grid-cols-3 gap-2">
                    <label class="flex items-center justify-center gap-1.5 p-2 rounded-lg cursor-pointer transition-all text-[10px] font-bold" :style="effect === 'none' ? 'background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.3); color: #8b5cf6;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);'">
                        <input type="radio" name="style[effect]" value="none" x-model="effect" class="hidden"> None
                    </label>
                    <label class="flex items-center justify-center gap-1.5 p-2 rounded-lg cursor-pointer transition-all text-[10px] font-bold" :style="effect === 'glass' ? 'background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.3); color: #8b5cf6;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);'">
                        <input type="radio" name="style[effect]" value="glass" x-model="effect" class="hidden"> <i class="fas fa-gem text-[8px]"></i> Glass
                    </label>
                    <label class="flex items-center justify-center gap-1.5 p-2 rounded-lg cursor-pointer transition-all text-[10px] font-bold" :style="effect === 'gradient_border' ? 'background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.3); color: #8b5cf6;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);'">
                        <input type="radio" name="style[effect]" value="gradient_border" x-model="effect" class="hidden"> <i class="fas fa-circle-notch text-[8px]"></i> Gradient
                    </label>
                </div>
                <div x-show="effect === 'glass'" x-cloak class="grid grid-cols-2 gap-2 mt-2">
                    <div>
                        <label class="{{ $labelClass }}">Blur (px)</label>
                        <input type="number" name="style[glass_blur]" value="{{ $st['glass_blur'] ?? 20 }}" min="0" max="100" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Glass Opacity (%)</label>
                        <input type="number" name="style[glass_opacity]" value="{{ $st['glass_opacity'] ?? 15 }}" min="0" max="100" class="{{ $inputClass }}">
                    </div>
                </div>
            </div>

            {{-- Shadow --}}
            <div>
                <label class="{{ $labelClass }}">Shadow</label>
                <select name="style[shadow_type]" class="{{ $inputClass }}">
                    @foreach($shadowTypes as $shVal => $shLabel)
                    <option value="{{ $shVal }}" {{ ($st['shadow_type'] ?? 'none') === $shVal ? 'selected' : '' }}>{{ $shLabel }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="style[shadow_color]" value="{{ $st['shadow_color'] ?? '#000000' }}">
                <input type="hidden" name="style[shadow_blur]" value="{{ $st['shadow_blur'] ?? 12 }}">
                <input type="hidden" name="style[shadow_x]" value="{{ $st['shadow_x'] ?? 0 }}">
                <input type="hidden" name="style[shadow_y]" value="{{ $st['shadow_y'] ?? 4 }}">
                <input type="hidden" name="style[shadow_spread]" value="{{ $st['shadow_spread'] ?? 0 }}">
            </div>

            {{-- Border Radius --}}
            <div>
                <label class="{{ $labelClass }}">Corner Radius (px)</label>
                <input type="number" name="style[border_radius]" value="{{ $st['border_radius'] ?? '' }}" placeholder="12" min="0" max="999" class="{{ $inputClass }}">
            </div>

            {{-- Advanced toggle --}}
            <button type="button" @click="showAdvanced = !showAdvanced" class="w-full flex items-center justify-center gap-1 text-[10px] font-semibold py-1.5 rounded-lg transition-all" style="color: var(--text-faint); background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                <i class="fas" :class="showAdvanced ? 'fa-chevron-up' : 'fa-chevron-down'" style="font-size: 7px;"></i>
                <span x-text="showAdvanced ? 'Hide advanced' : 'More options'"></span>
            </button>

            <div x-show="showAdvanced" x-cloak x-transition class="space-y-3 pt-1">
                {{-- Background Image --}}
                <div>
                    <label class="{{ $labelClass }}">Background Image URL</label>
                    <input type="url" name="style[bg_image]" value="{{ $st['bg_image'] ?? '' }}" placeholder="https://..." class="{{ $inputClass }}">
                </div>
                {{-- Border --}}
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="{{ $labelClass }}">Border</label>
                        <select name="style[border_style]" class="{{ $inputClass }}">
                            @foreach($borderStyles as $bsVal => $bsLabel)
                            <option value="{{ $bsVal }}" {{ ($st['border_style'] ?? 'none') === $bsVal ? 'selected' : '' }}>{{ $bsLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Width (px)</label>
                        <input type="number" name="style[border_width]" value="{{ $st['border_width'] ?? '' }}" placeholder="1" min="0" max="10" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Color</label>
                        <input type="color" name="style[border_color]" value="{{ $st['border_color'] ?? '#ffffff15' }}" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                    </div>
                </div>
                {{-- Shadow fine-tuning --}}
                <div>
                    <label class="{{ $labelClass }}">Shadow Fine-Tune</label>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-semibold flex-shrink-0" style="color: var(--text-dimmed);">Color</span>
                            <input type="color" name="style[shadow_color]" value="{{ $st['shadow_color'] ?? '#000000' }}" class="w-full h-8 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-semibold flex-shrink-0" style="color: var(--text-dimmed);">Blur</span>
                            <input type="number" name="style[shadow_blur]" value="{{ $st['shadow_blur'] ?? 12 }}" min="0" max="100" class="{{ $inputClass }}">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mt-1">
                        <div class="flex items-center gap-1"><span class="text-[9px] font-semibold" style="color: var(--text-dimmed);">X</span><input type="number" name="style[shadow_x]" value="{{ $st['shadow_x'] ?? 0 }}" min="-50" max="50" class="{{ $inputClass }}"></div>
                        <div class="flex items-center gap-1"><span class="text-[9px] font-semibold" style="color: var(--text-dimmed);">Y</span><input type="number" name="style[shadow_y]" value="{{ $st['shadow_y'] ?? 4 }}" min="-50" max="50" class="{{ $inputClass }}"></div>
                        <div class="flex items-center gap-1"><span class="text-[9px] font-semibold" style="color: var(--text-dimmed);">Spread</span><input type="number" name="style[shadow_spread]" value="{{ $st['shadow_spread'] ?? 0 }}" min="-20" max="50" class="{{ $inputClass }}"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- LAYOUT TAB (Spacing + Grid) --}}
        <div x-show="activeStyleTab === 'spacing'" class="space-y-4">
            {{-- Grid Width --}}
            <div>
                <label class="{{ $labelClass }}">Block Width</label>
                <div class="grid grid-cols-6 gap-1 p-2 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);" x-data="{ gridSpan: '{{ $st['grid_span'] ?? 12 }}' }">
                    @foreach([3 => '¼', 4 => '⅓', 6 => '½', 8 => '⅔', 9 => '¾', 12 => 'Full'] as $gv => $gl)
                    <label class="flex flex-col items-center cursor-pointer" @click="gridSpan = '{{ $gv }}'">
                        <input type="radio" name="style[grid_span]" value="{{ $gv }}" {{ ($st['grid_span'] ?? 12) == $gv ? 'checked' : '' }} class="hidden">
                        <span class="w-full text-center text-[10px] font-bold py-1.5 rounded-lg border transition-all"
                              :style="gridSpan == '{{ $gv }}' ? 'background: rgba(139,92,246,0.15); border-color: rgba(139,92,246,0.3); color: #a78bfa;' : 'background: transparent; border-color: transparent; color: var(--text-faint);'">{{ $gl }}</span>
                    </label>
                    @endforeach
                </div>
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Place blocks side-by-side in a row</p>
            </div>

            {{-- Padding --}}
            <div x-data="{ showPadding: {{ ($st['padding'] ?? '') !== '' || ($st['padding_top'] ?? '') !== '' ? 'true' : 'false' }} }">
                <button type="button" @click="showPadding = !showPadding" class="flex items-center gap-2 text-[11px] font-semibold w-full py-1" style="color: var(--text-muted);">
                    <i class="fas fa-expand text-[8px]" style="color: #22d3ee;"></i> Padding
                    <i class="fas text-[7px] ml-auto" :class="showPadding ? 'fa-chevron-up' : 'fa-chevron-down'" style="color: var(--text-faint);"></i>
                </button>
                <div x-show="showPadding" x-cloak x-transition class="mt-1">
                    <div class="p-2 rounded-xl" style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass);">
                        <div class="grid grid-cols-5 gap-1">
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">All</label><input type="number" name="style[padding]" value="{{ $st['padding'] ?? '' }}" placeholder="—" min="0" max="60" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Top</label><input type="number" name="style[padding_top]" value="{{ $st['padding_top'] ?? '' }}" placeholder="—" min="0" max="200" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Bot</label><input type="number" name="style[padding_bottom]" value="{{ $st['padding_bottom'] ?? '' }}" placeholder="—" min="0" max="200" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Left</label><input type="number" name="style[padding_left]" value="{{ $st['padding_left'] ?? '' }}" placeholder="—" min="0" max="200" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Right</label><input type="number" name="style[padding_right]" value="{{ $st['padding_right'] ?? '' }}" placeholder="—" min="0" max="200" class="{{ $inputClass }} text-[11px]"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Margin --}}
            <div x-data="{ showMargin: {{ ($st['margin_top'] ?? '') !== '' || ($st['margin_bottom'] ?? '') !== '' ? 'true' : 'false' }} }">
                <button type="button" @click="showMargin = !showMargin" class="flex items-center gap-2 text-[11px] font-semibold w-full py-1" style="color: var(--text-muted);">
                    <i class="fas fa-arrows-alt-v text-[8px]" style="color: #fb923c;"></i> Margin
                    <i class="fas text-[7px] ml-auto" :class="showMargin ? 'fa-chevron-up' : 'fa-chevron-down'" style="color: var(--text-faint);"></i>
                </button>
                <div x-show="showMargin" x-cloak x-transition class="mt-1">
                    <div class="p-2 rounded-xl" style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass);">
                        <div class="grid grid-cols-4 gap-1">
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Top</label><input type="number" name="style[margin_top]" value="{{ $st['margin_top'] ?? '' }}" placeholder="—" min="-100" max="200" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Bot</label><input type="number" name="style[margin_bottom]" value="{{ $st['margin_bottom'] ?? '' }}" placeholder="—" min="-100" max="200" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Left</label><input type="number" name="style[margin_left]" value="{{ $st['margin_left'] ?? '' }}" placeholder="—" min="-100" max="200" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Right</label><input type="number" name="style[margin_right]" value="{{ $st['margin_right'] ?? '' }}" placeholder="—" min="-100" max="200" class="{{ $inputClass }} text-[11px]"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@endif

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
    var tplInput = form.querySelector('[name="style[_template]"]');
    if (tplInput) {
        tplInput.value = key;
        tplInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
    btn.style.transform = 'scale(0.95)';
    setTimeout(function() { btn.style.transform = ''; }, 150);
    setTimeout(function() {
        var fd = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: fd
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) {
                if (typeof showToast === 'function') showToast('Preset applied', 'success');
                if (typeof refreshPreview === 'function') refreshPreview();
            } else {
                if (typeof showToast === 'function') showToast(data.error || 'Failed to apply', 'error');
            }
        }).catch(function() {
            if (typeof showToast === 'function') showToast('Failed to apply preset', 'error');
        });
    }, 100);
}
</script>
