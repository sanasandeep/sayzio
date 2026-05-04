@php
    $st = $block->settings['_style'] ?? [];
    $templates = \App\Modules\User\Models\BiolinkBlock::BLOCK_TEMPLATES;
    $variants = \App\Modules\User\Support\BlockVariantCatalog::forType($block->type);
    $variantTags = \App\Modules\User\Support\BlockVariantCatalog::TAGS;
    $variantShapes = \App\Modules\User\Support\BlockVariantCatalog::SHAPES;
    $variantVersion = \App\Modules\User\Support\BlockVariantCatalog::VERSION;
    $currentVariant = $st['_variant'] ?? '';
    // Pre-variant style snapshot, captured server-side the first time a
    // creator picks a curated variant. When present we surface a "Custom
    // (your tweaks)" entry at the top of the gallery so they can return.
    $customSnapshot = $block->settings['_style_custom_snapshot'] ?? null;
    $fonts = ['', 'Space Grotesk', 'Inter', 'Poppins', 'Roboto', 'Playfair Display', 'Montserrat', 'DM Sans', 'Outfit', 'Clash Display'];
    $weights = ['' => 'Default', '300' => 'Light', '400' => 'Regular', '500' => 'Medium', '600' => 'Semi Bold', '700' => 'Bold', '800' => 'Extra Bold', '900' => 'Black'];
    $borderStyles = ['none' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'];
    $shadowTypes = ['none' => 'None', 'soft' => 'Soft', 'hard' => 'Hard', 'neon' => 'Neon Glow', 'glow' => 'Subtle Glow', 'neumorphic' => 'Neumorphic', 'inset' => 'Inner Shadow'];
    $effects = ['none' => 'None', 'glass' => 'Glassmorphism', 'gradient_border' => 'Gradient Border'];

    // Blocks where the entire Block Styling section is hidden — the
    // visible result is either decided entirely by the embedded provider
    // (iframe_embed, custom_html) or has nothing for typography/colours
    // to bite into (spacer, divider).
    $noStyleBlocks = ['spacer', 'divider', 'iframe_embed', 'custom_html'];
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
<div class="mt-4 pt-4" style="border-top: 1px solid var(--border-subtle);" x-data="{ showStyle: false, activeStyleTab: 'designs' }">
    <button type="button" @click="showStyle = !showStyle"
            class="w-full flex items-center justify-between text-sm font-medium py-1" style="color: var(--text-muted);">
        <span><i class="fas fa-wand-magic-sparkles mr-2 text-pink-400"></i>Block Styling</span>
        <i :class="showStyle ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-xs"></i>
    </button>

    <div x-show="showStyle" x-cloak x-transition class="mt-3">

        <div class="flex gap-1 mb-4 p-0.5 rounded-lg" style="background: var(--bg-glass-input);">
            <button type="button" @click="activeStyleTab = 'designs'"
                    :class="activeStyleTab === 'designs' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'designs' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-shapes mr-1"></i>Designs
                <span class="ml-1 inline-block px-1 rounded-full text-[8px]" style="background: rgba(124,58,237,0.18); color: #a78bfa;">{{ count($variants) }}</span>
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

        {{-- DESIGNS TAB --}}
        @php
            // Build the tag set actually present in this type's variants so
            // we don't show empty filters. "All" is always first; "Favorites"
            // (client-side, localStorage) is always second.
            $variantTagsPresent = [];
            foreach ($variants as $v) {
                foreach (($v['tags'] ?? []) as $t) $variantTagsPresent[$t] = true;
            }
            $variantTagsPresent = array_intersect_key($variantTags, $variantTagsPresent);
        @endphp
        <div x-show="activeStyleTab === 'designs'" class="space-y-3"
             x-data="blockDesignsGallery({
                 blockId: {{ (int) ($block->id ?? 0) }},
                 blockType: '{{ $block->type }}',
                 currentVariant: @js($currentVariant),
                 customSnapshot: @js($customSnapshot)
             })"
             x-init="$nextTick(() => loadLivePreviews())">
            {{-- Plain-language explainer so first-time users understand
                 what they're picking and that it's safe to experiment. --}}
            <div class="p-2.5 rounded-lg flex items-start gap-2" style="background: rgba(124,58,237,0.08); border: 1px solid rgba(124,58,237,0.2);">
                <i class="fas fa-shapes text-[12px] mt-0.5" style="color: #a78bfa;"></i>
                <div class="flex-1">
                    <div class="text-[11px] font-bold leading-tight" style="color: var(--text-primary);">One-click skins for this block</div>
                    <div class="text-[10px] mt-0.5" style="color: var(--text-dimmed);">Pick a shape and theme — your text, link and image stay the same. Use <b>Reset</b> to undo or <b>Surprise me</b> to spin a random look.</div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-1">
                <button type="button" @click="resetStyle(false)"
                        class="text-[10px] font-bold py-1 px-2 rounded-md transition-all"
                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);"
                        title="Reset this block's styling to the default">
                    <i class="fas fa-rotate-left mr-1"></i>Reset
                </button>
                <button type="button" @click="surpriseMe()"
                        class="text-[10px] font-bold py-1 px-2 rounded-md transition-all"
                        style="background: linear-gradient(135deg, #ec4899, #8b5cf6); color: white;">
                    <i class="fas fa-dice mr-1"></i>Surprise me
                </button>
            </div>

            {{-- Build the set of shapes actually represented in this
                 type's variants. Only render the Shape row when there
                 are at least two distinct shapes — a single-shape catalog
                 (e.g. paragraph) would just be visual noise. --}}
            @php
                $variantShapesPresent = [];
                foreach ($variants as $v) {
                    if (!empty($v['shape'])) $variantShapesPresent[$v['shape']] = true;
                }
                $variantShapesPresent = array_intersect_key($variantShapes, $variantShapesPresent);
            @endphp
            @if(count($variantShapesPresent) >= 2)
            <div>
                <div class="text-[9px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-dimmed);">Shape</div>
                <div class="flex flex-wrap gap-1">
                    <button type="button" @click="activeShape = 'all'"
                            :class="activeShape === 'all' ? 'ring-1 ring-cyan-400/60' : ''"
                            class="text-[9px] font-bold px-2 py-1 rounded-full transition-all"
                            :style="activeShape === 'all' ? 'background: rgba(34,211,238,0.18); color: #67e8f9;' : 'background: var(--bg-glass-input); color: var(--text-faint);'">
                        All shapes
                    </button>
                    @foreach($variantShapesPresent as $shapeKey => $shapeLabel)
                    <button type="button" @click="activeShape = '{{ $shapeKey }}'"
                            :class="activeShape === '{{ $shapeKey }}' ? 'ring-1 ring-cyan-400/60' : ''"
                            class="text-[9px] font-bold px-2 py-1 rounded-full transition-all"
                            :style="activeShape === '{{ $shapeKey }}' ? 'background: rgba(34,211,238,0.18); color: #67e8f9;' : 'background: var(--bg-glass-input); color: var(--text-faint);'">
                        {{ $shapeLabel }}
                    </button>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Theme filter chips (colour / vibe). --}}
            <div>
                <div class="text-[9px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-dimmed);">Theme</div>
                <div class="flex flex-wrap gap-1">
                    <button type="button" @click="activeFilter = 'all'"
                            :class="activeFilter === 'all' ? 'ring-1 ring-violet-400/60' : ''"
                            class="text-[9px] font-bold px-2 py-1 rounded-full transition-all"
                            :style="activeFilter === 'all' ? 'background: rgba(124,58,237,0.18); color: #a78bfa;' : 'background: var(--bg-glass-input); color: var(--text-faint);'">
                        All
                    </button>
                    <button type="button" @click="activeFilter = 'favorites'"
                            :class="activeFilter === 'favorites' ? 'ring-1 ring-pink-400/60' : ''"
                            class="text-[9px] font-bold px-2 py-1 rounded-full transition-all"
                            :style="activeFilter === 'favorites' ? 'background: rgba(236,72,153,0.18); color: #f472b6;' : 'background: var(--bg-glass-input); color: var(--text-faint);'">
                        <i class="fas fa-star text-[8px] mr-0.5"></i>Favorites
                    </button>
                    @foreach($variantTagsPresent as $tagKey => $tagLabel)
                    <button type="button" @click="activeFilter = '{{ $tagKey }}'"
                            :class="activeFilter === '{{ $tagKey }}' ? 'ring-1 ring-violet-400/60' : ''"
                            class="text-[9px] font-bold px-2 py-1 rounded-full transition-all"
                            :style="activeFilter === '{{ $tagKey }}' ? 'background: rgba(124,58,237,0.18); color: #a78bfa;' : 'background: var(--bg-glass-input); color: var(--text-faint);'">
                        {{ $tagLabel }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Variant + version are no longer carried via hidden form
                 inputs. The dedicated apply-variant endpoint persists
                 them in `_style` directly with full-replace semantics so
                 the standard form save can never re-merge stale variant
                 keys back into the block. --}}

            {{-- Custom (current style) chip — visible when block has _style
                 overrides but no variant. Clicking is a no-op; it just
                 explains why no thumbnail is highlighted. --}}
            <template x-if="hasCustomStyle && currentVariant === ''">
                <div class="p-2 rounded-lg flex items-center gap-2" style="background: rgba(236,72,153,0.08); border: 1px dashed rgba(236,72,153,0.3);">
                    <div class="w-7 h-7 rounded-md flex items-center justify-center" style="background: rgba(236,72,153,0.18); color: #f472b6;">
                        <i class="fas fa-paint-brush text-[10px]"></i>
                    </div>
                    <div class="flex-1">
                        <div class="text-[11px] font-bold" style="color: var(--text-primary);">Custom</div>
                        <div class="text-[9px]" style="color: var(--text-dimmed);">Your tweaked styling — pick a design below to swap.</div>
                    </div>
                </div>
            </template>

            {{-- "Custom (your tweaks)" restore card — only visible when a
                 snapshot exists from the first time this block was skinned
                 with a curated variant. Clicking restores those handcrafted
                 styles in place of the current variant, so creators never
                 lose work by exploring designs. --}}
            @if(!empty($customSnapshot))
            <button type="button" @click="restoreCustom()"
                    class="w-full p-2 rounded-xl text-left transition-all flex items-center gap-2 hover:scale-[1.01]"
                    style="background: rgba(236,72,153,0.08); border: 1px dashed rgba(236,72,153,0.4);">
                <div class="w-9 h-9 rounded-md flex items-center justify-center" style="background: rgba(236,72,153,0.18); color: #f472b6;">
                    <i class="fas fa-paint-brush text-[12px]"></i>
                </div>
                <div class="flex-1">
                    <div class="text-[11px] font-bold" style="color: var(--text-primary);">Custom (your tweaks)</div>
                    <div class="text-[9px]" style="color: var(--text-dimmed);">Restore your handcrafted styling.</div>
                </div>
                <i class="fas fa-undo text-[10px]" style="color: #f472b6;"></i>
            </button>
            @endif

            {{-- Variant grid --}}
            <div class="grid grid-cols-2 gap-2">
                @foreach($variants as $v)
                @php
                    $pv = $v['preview'] ?? [];
                    $thumbBg = $pv['bg'] ?? '#1a1a2e';
                    $thumbText = $pv['text'] ?? '#ffffff';
                    $thumbRadius = (int) ($pv['radius'] ?? 12);
                    $thumbBorder = $pv['border'] ?? '';
                    $thumbShadow = $pv['shadow'] ?? '';
                    $isDashed = !empty($pv['dashed']);
                    $isSerif = !empty($pv['serif']);
                @endphp
                <button type="button"
                        data-variant-key="{{ $v['key'] }}"
                        x-show="matchesFilter(@js($v['tags'] ?? []), '{{ $v['key'] }}', @js($v['shape'] ?? ''))"
                        @click="applyVariant('{{ $v['key'] }}', $el)"
                        class="group p-2 rounded-xl text-left transition-all hover:scale-[1.03] relative"
                        :style="currentVariant === '{{ $v['key'] }}' ? 'background: rgba(124,58,237,0.12); border: 2px solid rgba(124,58,237,0.6); box-shadow: 0 0 12px rgba(124,58,237,0.18);' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass);'">
                    {{-- Selected check --}}
                    <div class="absolute top-1.5 left-1.5 w-5 h-5 rounded-full flex items-center justify-center transition-all"
                         :style="currentVariant === '{{ $v['key'] }}' ? 'background: #8b5cf6; opacity: 1;' : 'opacity: 0;'">
                        <i class="fas fa-check text-white text-[8px]"></i>
                    </div>
                    {{-- Favorite star --}}
                    <button type="button" @click.stop="toggleFavorite('{{ $v['key'] }}')"
                            class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full flex items-center justify-center transition-all opacity-60 hover:opacity-100"
                            :style="isFavorite('{{ $v['key'] }}') ? 'background: rgba(236,72,153,0.2); color: #f472b6; opacity: 1;' : 'background: rgba(255,255,255,0.06); color: var(--text-faint);'">
                        <i :class="isFavorite('{{ $v['key'] }}') ? 'fas' : 'far'" class="fa-star text-[8px]"></i>
                    </button>

                    {{-- Thumbnail rendered from preview hints with a small
                         block-shape sketch so creators can see how the
                         variant frames their actual block type, not just an
                         abstract color swatch. We pick the sketch by the
                         block's category (button/image/text/badge) so the
                         shape matches what they'll see in the live preview. --}}
                    @php
                        // Variant-declared shape wins over the per-block-
                        // type fallback when present — that way a
                        // 'plain_text' or 'image_full' link variant gets
                        // the right sketch even though the block type
                        // itself is just 'link'.
                        $variantShape = $v['shape'] ?? '';
                        $shapeKind = match (true) {
                            $variantShape === 'plain_text' => 'plain_link',
                            $variantShape === 'image_full' => 'image_btn',
                            $variantShape === 'outline'    => 'button_outline',
                            in_array($block->type, ['avatar']) => 'avatar',
                            in_array($block->type, ['image', 'photo', 'banner', 'header_image']) => 'image',
                            in_array($block->type, ['link', 'link_big', 'button', 'cta', 'cta_button', 'social', 'url']) => 'button',
                            in_array($block->type, ['heading', 'title', 'heading_logo']) => 'heading',
                            in_array($block->type, ['divider', 'spacer']) => 'divider',
                            default => 'text',
                        };
                    @endphp
                    {{-- Thumbnail container: server-rendered live preview
                         is fetched on tab open and injected into
                         data-variant-preview="{{ $v['key'] }}". Until then
                         we render the static shape-sketch fallback so the
                         gallery is never blank. Bumped to h-20 so shape
                         differences (pill vs square vs full-image) are
                         visible at a glance instead of squinting. --}}
                    <div data-variant-preview="{{ $v['key'] }}"
                         class="h-20 rounded-lg flex items-center justify-center mt-3 mb-2 overflow-hidden p-2"
                         style="background: {{ $thumbBg }};
                                border-radius: {{ min($thumbRadius, 24) }}px;
                                {{ $thumbBorder ? 'border:' . ($isDashed ? '2px dashed ' : '1px solid ') . $thumbBorder . ';' : '' }}
                                {{ $thumbShadow ? 'box-shadow:' . $thumbShadow . ';' : '' }}">
                        @if($shapeKind === 'button')
                            <div class="px-3 py-1.5 text-[9px] font-bold"
                                 style="background: {{ $thumbText }}; color: {{ $thumbBg === 'transparent' ? '#000' : $thumbBg }}; border-radius: {{ min($thumbRadius, 999) }}px;">
                                Click me
                            </div>
                        @elseif($shapeKind === 'button_outline')
                            <div class="px-3 py-1.5 text-[9px] font-bold"
                                 style="background: transparent; color: {{ $thumbText }}; border: 1.5px solid {{ $thumbText }}; border-radius: {{ min($thumbRadius, 999) }}px;">
                                Click me
                            </div>
                        @elseif($shapeKind === 'plain_link')
                            <span class="text-[10px] font-medium underline decoration-1 underline-offset-2"
                                  style="color: {{ $thumbText }};">Click me →</span>
                        @elseif($shapeKind === 'image_btn')
                            <div class="w-full h-full rounded flex items-end p-1.5"
                                 style="background: linear-gradient(135deg,#7c3aed,#ec4899); border-radius: {{ min($thumbRadius, 16) }}px;">
                                <span class="text-[9px] font-bold text-white drop-shadow">Click me</span>
                            </div>
                        @elseif($shapeKind === 'avatar')
                            <div class="rounded-full" style="width: 28px; height: 28px; background: {{ $thumbText }}; opacity: 0.85;"></div>
                        @elseif($shapeKind === 'image')
                            <div class="flex items-center justify-center w-full h-full rounded" style="background: {{ $thumbText }}30; color: {{ $thumbText }};">
                                <i class="fas fa-image text-[14px]"></i>
                            </div>
                        @elseif($shapeKind === 'heading')
                            <div class="text-[12px] font-bold" style="color: {{ $thumbText }}; {{ $isSerif ? "font-family: 'Playfair Display', serif;" : '' }}">Heading</div>
                        @elseif($shapeKind === 'divider')
                            <div style="width: 80%; height: 2px; background: {{ $thumbText }}; opacity: 0.6;"></div>
                        @else
                            <div class="w-full" style="color: {{ $thumbText }}; {{ $isSerif ? "font-family: 'Playfair Display', serif;" : '' }}">
                                <div class="text-[8px] font-bold leading-tight">Aa Bb Cc</div>
                                <div style="height: 2px; background: {{ $thumbText }}; opacity: 0.4; margin-top: 3px; width: 80%;"></div>
                                <div style="height: 2px; background: {{ $thumbText }}; opacity: 0.4; margin-top: 2px; width: 60%;"></div>
                            </div>
                        @endif
                    </div>
                    <div class="text-[10px] font-semibold truncate" style="color: var(--text-primary);">{{ $v['name'] }}</div>
                    <div class="flex flex-wrap gap-0.5 mt-0.5">
                        @foreach(($v['tags'] ?? []) as $tagKey)
                        @if(isset($variantTags[$tagKey]))
                        <span class="text-[8px] px-1 rounded" style="background: rgba(124,58,237,0.1); color: #a78bfa;">{{ $variantTags[$tagKey] }}</span>
                        @endif
                        @endforeach
                    </div>
                </button>
                @endforeach
            </div>

            {{-- Empty state when filter shows nothing --}}
            <div x-show="visibleCount() === 0" class="text-center py-4 text-[10px]" style="color: var(--text-dimmed);">
                <i class="fas fa-search-minus mr-1"></i>No designs match this filter yet.
            </div>

            {{-- Apply to all --}}
            <button type="button" @click="applyToAll()"
                    x-show="currentVariant !== ''"
                    class="w-full text-[10px] font-bold py-2 rounded-lg transition-all flex items-center justify-center gap-1"
                    style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass); color: var(--text-muted);">
                <i class="fas fa-clone text-[9px]"></i>
                Apply this design to all <span x-text="blockTypeLabel"></span> blocks
            </button>

            {{-- Reset to default for ALL blocks of this type. Separate from
                 the per-block Reset button at the top so creators can do
                 either: zero out just this block, or zero out every block
                 of this type on the page. --}}
            <button type="button" @click="resetStyle(true)"
                    class="w-full text-[10px] font-bold py-2 rounded-lg transition-all flex items-center justify-center gap-1"
                    style="background: rgba(244,63,94,0.06); border: 1px dashed rgba(244,63,94,0.35); color: #fb7185;">
                <i class="fas fa-rotate-left text-[9px]"></i>
                Reset all <span x-text="blockTypeLabel"></span> blocks to default
            </button>
        </div>

        @if($showText)
        {{-- TEXT TAB --}}
        <div x-show="activeStyleTab === 'typography'" class="space-y-3">
            <div>
                <label class="{{ $labelClass }}">Font Family</label>
                @include('user.links.partials.font-picker', [
                    'name' => 'style[font_family]',
                    'value' => $st['font_family'] ?? '',
                    'pickerId' => 'blockFont_' . ($block->id ?? uniqid()),
                    'allowInherit' => true,
                ])
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
                    <label class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-all text-xs font-medium" :style="mode === 'card' ? 'background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.3); color: #8b5cf6;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);'">
                        <input type="radio" name="style[display_mode]" value="card" x-model="mode" class="hidden">
                        <i class="fas fa-square text-xs"></i> Card
                    </label>
                    <label class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-all text-xs font-medium" :style="mode === 'content' ? 'background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.3); color: #8b5cf6;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);'">
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

            {{-- Glass preset (simplified). Advanced glass blur/opacity sliders
                 still appear under "More options" for back-compat. --}}
            <div>
                <label class="{{ $labelClass }}">Glassmorphism</label>
                <div class="grid grid-cols-3 gap-2">
                    @php $gp = $st['glass_preset'] ?? ''; @endphp
                    @foreach(['off' => 'Off', 'light' => 'Light', 'heavy' => 'Heavy'] as $gpVal => $gpLabel)
                        <label class="flex items-center justify-center p-2 rounded-lg cursor-pointer transition-all text-[10px] font-bold"
                               style="{{ $gp === $gpVal ? 'background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.3); color: #8b5cf6;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);' }}">
                            <input type="radio" name="style[glass_preset]" value="{{ $gpVal }}" {{ $gp === $gpVal ? 'checked' : '' }} class="hidden"> {{ $gpLabel }}
                        </label>
                    @endforeach
                </div>
                <p class="text-[9px] mt-1" style="color: var(--text-dimmed);">Use the gradient border in advanced options if needed.</p>
            </div>

            {{-- Shadow preset (simplified). Granular shadow_x/y/blur/spread
                 remain editable under "More options" for back-compat. --}}
            <div>
                <label class="{{ $labelClass }}">Shadow</label>
                <div class="grid grid-cols-4 gap-2">
                    @php $sp = $st['shadow_preset'] ?? ''; @endphp
                    @foreach(['none' => 'None', 'soft' => 'Soft', 'medium' => 'Medium', 'strong' => 'Strong'] as $spVal => $spLabel)
                        <label class="flex items-center justify-center p-2 rounded-lg cursor-pointer transition-all text-[10px] font-bold"
                               style="{{ $sp === $spVal ? 'background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.3); color: #8b5cf6;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);' }}">
                            <input type="radio" name="style[shadow_preset]" value="{{ $spVal }}" {{ $sp === $spVal ? 'checked' : '' }} class="hidden"> {{ $spLabel }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Round-trip the underlying values that the presets map onto so
                 the form always posts a complete style payload. The advanced
                 panel below exposes friendly inputs that override these. --}}
            <input type="hidden" name="style[effect]" value="{{ $st['effect'] ?? 'none' }}">
            <input type="hidden" name="style[shadow_type]" value="{{ $st['shadow_type'] ?? 'none' }}">
            <input type="hidden" name="style[glass_blur]" value="{{ $st['glass_blur'] ?? 20 }}">
            <input type="hidden" name="style[glass_opacity]" value="{{ $st['glass_opacity'] ?? 15 }}">

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
                              :style="gridSpan == '{{ $gv }}' ? 'background: rgba(124,58,237,0.15); border-color: rgba(124,58,237,0.3); color: #a78bfa;' : 'background: transparent; border-color: transparent; color: var(--text-faint);'">{{ $gl }}</span>
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
// Per-type variant catalog snapshot for the currently-edited block. Stored
// on window so multiple open editor panes share a single deserialized copy
// (cheaper than re-decoding for every Alpine init).
window.__blockVariants = window.__blockVariants || {};
window.__blockVariants['{{ $block->type }}'] = @json($variants);

window.blockDesignsGallery = function(opts) {
    return {
        blockId: opts.blockId,
        blockType: opts.blockType,
        currentVariant: opts.currentVariant || '',
        customSnapshot: opts.customSnapshot || null,
        activeFilter: 'all',
        // Independent shape filter (Pill / Square / Outline / Text Link /
        // Image / Card). Orthogonal to activeFilter — a variant must
        // satisfy BOTH the active theme and the active shape to be shown.
        activeShape: 'all',
        favorites: [],
        // Block has _style overrides but no _variant key — show "Custom"
        // chip so they know their tweaks aren't being silently lost.
        hasCustomStyle: @js(!empty($st) && empty($currentVariant)),
        // Friendly label of the block type for "Apply to all <type>" copy.
        blockTypeLabel: @js(\App\Modules\User\Models\BiolinkBlock::TYPES[$block->type]['label'] ?? $block->type),

        init() {
            try {
                var raw = localStorage.getItem('biolink:variantFavorites:' + this.blockType);
                this.favorites = raw ? JSON.parse(raw) : [];
            } catch (e) { this.favorites = []; }
        },

        catalog() {
            return window.__blockVariants[this.blockType] || [];
        },

        // Lazy-load server-rendered live previews for every thumbnail in
        // the gallery. The server returns inline-style strings derived
        // from the same `BiolinkBlock::buildInlineStyle()` the public
        // renderer uses, so what creators see in the gallery is exactly
        // what their block will render with. We only fetch once per
        // editor open — the result is cached on `this`.
        loadLivePreviews() {
            if (this._previewsLoaded || this._previewsLoading) return;
            this._previewsLoading = true;
            var url = '{{ route('user.links.blocks.variantPreviews', [$link, $block]) }}';
            var self = this;
            var blockLabel = '{{ $block->settings['label'] ?? $block->settings['text'] ?? '' }}';
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !Array.isArray(data.previews)) return;
                    self._previewsLoaded = true;
                    data.previews.forEach(function(p) {
                        var slot = self.$el.querySelector('[data-variant-preview="' + p.key + '"]');
                        if (!slot) return;
                        // Replace the shape-sketch placeholder with a
                        // live-styled mini block. We use inline style
                        // straight from the server so any future change
                        // to buildInlineStyle() flows through here.
                        var content = blockLabel ? blockLabel.substring(0, 16) : p.name;
                        slot.setAttribute('style', 'min-height:80px;display:flex;align-items:center;justify-content:center;overflow:hidden;margin:12px 0 8px;padding:8px;' + p.inline_style);
                        slot.innerHTML = '<span style="font-size:12px;font-weight:600;color:' + (p.text_color || 'inherit') + ';text-align:center;line-height:1.2;padding:0 4px;">' + (content.replace(/[<>&]/g, '') || 'Preview') + '</span>';
                    });
                })
                .catch(function() {})
                .finally(function() { self._previewsLoading = false; });
        },

        matchesFilter(tags, key, shape) {
            // Shape gate first — if the user has narrowed by shape, drop
            // anything that doesn't match before checking the theme tag.
            if (this.activeShape !== 'all' && (shape || '') !== this.activeShape) return false;
            if (this.activeFilter === 'all') return true;
            if (this.activeFilter === 'favorites') return this.favorites.indexOf(key) !== -1;
            return (tags || []).indexOf(this.activeFilter) !== -1;
        },

        visibleCount() {
            var self = this;
            return this.catalog().filter(function(v) {
                return self.matchesFilter(v.tags || [], v.key, v.shape || '');
            }).length;
        },

        isFavorite(key) { return this.favorites.indexOf(key) !== -1; },

        toggleFavorite(key) {
            var i = this.favorites.indexOf(key);
            if (i === -1) this.favorites.push(key); else this.favorites.splice(i, 1);
            try { localStorage.setItem('biolink:variantFavorites:' + this.blockType, JSON.stringify(this.favorites)); } catch (e) {}
        },

        surpriseMe() {
            // Pick a variant tagged with the page's overall vibe when we
            // can — fallback to a uniform random pick. We also avoid
            // re-picking the variant that's already applied.
            var pool = this.catalog().filter(function(v) { return v.key !== this.currentVariant; }, this);
            if (pool.length === 0) pool = this.catalog();
            if (pool.length === 0) return;
            var pick = pool[Math.floor(Math.random() * pool.length)];
            // Find the matching button so we get the click animation.
            var btn = this.$el.querySelector('[data-variant-key="' + pick.key + '"]') || this.$el;
            this.applyVariant(pick.key, btn);
        },

        applyVariant(key, btn) {
            var v = this.catalog().find(function(x) { return x.key === key; });
            if (!v) return;
            // Optimistic UI: swap selection immediately so the gallery
            // feels instant even on slow networks.
            this.currentVariant = key;
            this.hasCustomStyle = false;
            if (btn && btn.style) {
                btn.style.transform = 'scale(0.95)';
                setTimeout(function() { btn.style.transform = ''; }, 150);
            }

            // Hit the dedicated apply-variant endpoint, which performs a
            // FULL `_style` replace server-side (STYLE_DEFAULTS + variant
            // overrides). This guarantees that switching from variant A
            // to variant B never leaves residual A keys behind, which
            // was the merge-bug in the previous form-pipeline approach.
            var url = '{{ route('user.links.blocks.applyVariant', [$link, $block]) }}';
            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
            var fd = new FormData();
            fd.append('variant', key);
            if (token) fd.append('_token', token);
            var self = this;
            fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        if (typeof showToast === 'function') showToast('Design applied', 'success');
                        // Refresh the form once so any granular controls
                        // (Look/Layout/Text tabs) reflect the new style
                        // payload that the server just wrote.
                        if (typeof refreshBlockEditor === 'function') refreshBlockEditor();
                        if (typeof refreshPreview === 'function') refreshPreview();
                        // Capture the snapshot the server may have just
                        // taken so the "Custom" restore card appears
                        // without a full editor reload.
                        if (data.block && data.block.settings && data.block.settings._style_custom_snapshot) {
                            self.customSnapshot = data.block.settings._style_custom_snapshot;
                        }
                    } else {
                        if (typeof showToast === 'function') showToast((data && data.error) || 'Failed to apply design', 'error');
                    }
                })
                .catch(function() {
                    if (typeof showToast === 'function') showToast('Failed to apply design', 'error');
                });
        },

        restoreCustom() {
            // Hit the dedicated restore-custom-style endpoint, which
            // does a full `_style` REPLACE from the server-side snapshot
            // (STYLE_DEFAULTS + snapshot, with the variant key cleared).
            // The snapshot itself stays on the block so the user can
            // explore variants again and come back later.
            if (!this.customSnapshot) return;
            this.currentVariant = '';
            this.hasCustomStyle = true;
            var url = '{{ route('user.links.blocks.restoreCustomStyle', [$link, $block]) }}';
            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
            var fd = new FormData();
            if (token) fd.append('_token', token);
            fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        if (typeof showToast === 'function') showToast('Custom styling restored', 'success');
                        if (typeof refreshBlockEditor === 'function') refreshBlockEditor();
                        if (typeof refreshPreview === 'function') refreshPreview();
                    } else {
                        if (typeof showToast === 'function') showToast((data && data.error) || 'Failed to restore', 'error');
                    }
                })
                .catch(function() {
                    if (typeof showToast === 'function') showToast('Failed to restore', 'error');
                });
        },

        resetStyle(applyToAll) {
            // Wipes _style back to STYLE_DEFAULTS server-side. When
            // applyToAll is true, every block of the same type on this
            // page is reset; otherwise just this one block is.
            var label = applyToAll
                ? ('Reset every ' + this.blockTypeLabel + ' block to the default styling? This will clear any custom tweaks.')
                : 'Reset this block to the default styling? This will clear any custom tweaks.';
            if (typeof confirm === 'function' && !confirm(label)) return;
            var url = '{{ route('user.links.blocks.resetStyle', [$link, $block]) }}';
            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
            var fd = new FormData();
            if (applyToAll) fd.append('apply_to_all', '1');
            if (token) fd.append('_token', token);
            var self = this;
            fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        self.currentVariant = '';
                        self.hasCustomStyle = false;
                        self.customSnapshot = null;
                        if (typeof showToast === 'function') {
                            showToast(applyToAll ? ('Reset ' + (data.updated || 0) + ' block(s) to default') : 'Block reset to default', 'success');
                        }
                        if (typeof refreshBlockEditor === 'function') refreshBlockEditor();
                        if (typeof refreshPreview === 'function') refreshPreview();
                    } else {
                        if (typeof showToast === 'function') showToast((data && data.error) || 'Failed to reset', 'error');
                    }
                })
                .catch(function() {
                    if (typeof showToast === 'function') showToast('Failed to reset', 'error');
                });
        },

        applyToAll() {
            if (!this.currentVariant) return;
            if (typeof confirm === 'function' && !confirm('Apply this design to every ' + this.blockTypeLabel + ' block on this page?')) return;
            var url = '{{ route('user.links.blocks.applyVariantToAll', [$link, $block]) }}';
            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
            var fd = new FormData();
            fd.append('variant', this.currentVariant);
            if (token) fd.append('_token', token);
            fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        if (typeof showToast === 'function') showToast('Applied to ' + data.updated + ' block(s)', 'success');
                        if (typeof refreshPreview === 'function') refreshPreview();
                    } else {
                        if (typeof showToast === 'function') showToast((data && data.error) || 'Failed', 'error');
                    }
                })
                .catch(function() {
                    if (typeof showToast === 'function') showToast('Failed to apply to all', 'error');
                });
        },
    };
};

{{-- Presets feature was removed by user request; the Designs / Text / Look / Layout
     tabs cover all styling needs without the rigid preset grid. --}}
</script>
