@php
    $st = $block->settings['_style'] ?? [];
    $templates = \App\Modules\User\Models\BiolinkBlock::blockTemplates();
    $variants = \App\Modules\User\Support\BlockVariantCatalog::forType($block->type);
    $variantTags = \App\Modules\User\Support\BlockVariantCatalog::TAGS;
    $variantShapes = \App\Modules\User\Support\BlockVariantCatalog::SHAPES;
    $variantVersion = \App\Modules\User\Support\BlockVariantCatalog::version();
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
    $noStyleBlocks = ['spacer', 'iframe_embed', 'custom_html'];
    // Divider (Task #6581): shows ONLY the Designs gallery — its curated
    // line looks. Typography/Look/Layout still make no sense on a bare
    // line, so those tabs stay hidden and the block stays out of the
    // unified _style chrome panel.
    $designsOnly = $block->type === 'divider';
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
    // Design-locked pages: per-block styling (Designs/Text/Look/Layout) is
    // owned by the template — hide the whole section and show a lock note.
    $designLocked = method_exists($link, 'isDesignLocked') && $link->isDesignLocked();

    // Effective page backdrop for the contrast warning (Task #6052): when the
    // block background is empty (transparent/inherit), text sits directly on
    // the page background. Resolve it to a solid hex when possible so the
    // warning still fires; gradients / images / presets / videos stay null so
    // the warning is silent (no false positives).
    $pageBs = $link->settings['biolink'] ?? [];
    $effPageBg = null;
    $__hex6 = fn($v) => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : null;
    if (!array_key_exists('background_type', $pageBs)) {
        // No theme ever saved: the page renders the default dark gradient over
        // bg_fallback_color (#0a0612). Mirror the public-page contrast
        // safeguard and treat the fallback as the effective backdrop.
        $effPageBg = $__hex6($pageBs['bg_fallback_color'] ?? null) ?? '#0a0612';
    } elseif (($pageBs['background_type'] ?? null) === 'color') {
        $effPageBg = $__hex6($pageBs['background_color'] ?? null);
    }
@endphp

@if($designLocked && $showStyle)
<div class="mt-4 pt-4" style="border-top: 1px solid var(--border-subtle);">
    <div class="flex items-center gap-2 text-xs" style="color: var(--text-faint);">
        <i class="fas fa-lock text-amber-400"></i>
        <span>Block styling follows the template design. Detach from the template in Settings to customize.</span>
    </div>
</div>
@endif

@if($showStyle && !$designLocked)
<div class="mt-4 pt-4" style="border-top: 1px solid var(--border-subtle);" data-style-root x-data="{
    showStyle: false, activeStyleTab: 'designs',
    {{-- WCAG contrast warning (Task #6046): mirrors the Default Colors tab helper.
         Non-blocking — warns only, never prevents saving. --}}
    cText: @js((string) ($st['text_color'] ?? '')),
    cBg: @js((string) ($st['bg_color'] ?? '')),
    {{-- Resolved page backdrop used when the block bg is empty (Task #6052).
         null when the page background is a gradient/image/preset (silent). --}}
    cPageBg: @js($effPageBg),
    cEffBg() { return String(this.cBg || '').trim() !== '' ? this.cBg : this.cPageBg; },
    cUsingPage() { return String(this.cBg || '').trim() === '' && this.cPageBg !== null; },
    cLum(hex) {
        const m = /^#?([0-9a-f]{6})$/i.exec(String(hex || '').trim());
        if (!m) return null;
        const n = parseInt(m[1], 16);
        const chan = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
        return 0.2126 * chan((n >> 16) & 255) + 0.7152 * chan((n >> 8) & 255) + 0.0722 * chan(n & 255);
    },
    cRatio() {
        const a = this.cLum(this.cText), b = this.cLum(this.cEffBg());
        if (a === null || b === null) return null;
        const hi = Math.max(a, b), lo = Math.min(a, b);
        return (hi + 0.05) / (lo + 0.05);
    },
    cLow() { const r = this.cRatio(); return r !== null && r < 4.5; },
    cFmt() { const r = this.cRatio(); return r === null ? '' : (Math.round(r * 10) / 10) + ':1'; }
}">
    <button type="button" @click="showStyle = !showStyle"
            class="w-full flex items-center justify-between text-sm font-medium py-1.5 group" style="color: var(--text-muted);">
        <span class="flex items-center gap-2">
            <span class="inline-flex w-7 h-7 rounded-lg items-center justify-center" style="background: linear-gradient(135deg, rgba(236,72,153,0.18), rgba(92,131,255,0.12)); border: 1px solid rgba(236,72,153,0.25);">
                <i class="fas fa-wand-magic-sparkles text-pink-400 text-xs"></i>
            </span>
            <span>Block Styling</span>
            <span class="edit-section-pill edit-section-pill--pink">Themes · Fonts · Colors</span>
        </span>
        <i :class="showStyle ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-[10px] edit-section-chevron"></i>
    </button>

    <div x-show="showStyle" x-cloak x-transition class="mt-3">

        <div class="flex gap-1 mb-4 p-0.5 rounded-lg" style="background: var(--bg-glass-input);">
            <button type="button" @click="activeStyleTab = 'designs'"
                    :class="activeStyleTab === 'designs' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'designs' ? 'background: linear-gradient(135deg, #5c83ff, #3d6bff);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-shapes mr-1"></i>Designs
                <span class="ml-1 inline-block px-1 rounded-full text-[8px]" style="background: rgba(61,107,255,0.18); color: #90acff;">{{ count($variants) }}</span>
            </button>
            @if($showText)
            <button type="button" @click="activeStyleTab = 'typography'"
                    :class="activeStyleTab === 'typography' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'typography' ? 'background: linear-gradient(135deg, #5c83ff, #3d6bff);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-font mr-1"></i>Text
            </button>
            @endif
            @if(!$designsOnly)
            <button type="button" @click="activeStyleTab = 'appearance'"
                    :class="activeStyleTab === 'appearance' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'appearance' ? 'background: linear-gradient(135deg, #5c83ff, #3d6bff);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-palette mr-1"></i>Look
            </button>
            <button type="button" @click="activeStyleTab = 'spacing'"
                    :class="activeStyleTab === 'spacing' ? 'text-white shadow-sm' : ''"
                    :style="activeStyleTab === 'spacing' ? 'background: linear-gradient(135deg, #5c83ff, #3d6bff);' : 'color: var(--text-faint);'"
                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">
                <i class="fas fa-arrows-alt mr-1"></i>Layout
            </button>
            @endif
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
            <div class="p-2.5 rounded-lg flex items-start gap-2" style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.2);">
                <i class="fas fa-shapes text-[12px] mt-0.5" style="color: #90acff;"></i>
                <div class="flex-1">
                    <div class="text-[11px] font-bold leading-tight" style="color: var(--text-primary);">One-click skins for this block</div>
                    <div class="text-[10px] mt-0.5" style="color: var(--text-dimmed);">Pick a shape and theme, your text, link and image stay the same. Use <b>Reset</b> to undo or <b>Surprise me</b> to spin a random look.</div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-1">
                {{-- "Saving design…" pill — visible whenever any of the
                     gallery's apply / restore / reset / apply-to-all
                     requests is in flight. Closes the loop between the
                     optimistic selection swap and the eventual toast so
                     creators on slow connections don't think the click
                     was ignored. Cleared automatically when _busy flips
                     back to false in each action's `finally`. --}}
                <span x-show="_busy" x-cloak
                      class="mr-auto inline-flex items-center gap-1 text-[10px] font-bold py-1 px-2 rounded-md"
                      style="background: rgba(61,107,255,0.14); border: 1px solid rgba(61,107,255,0.35); color: #bccfff;">
                    <i class="fas fa-circle-notch fa-spin text-[10px]"></i>
                    <span>Saving design…</span>
                </span>
                {{-- Inline error chip — shown when an apply / restore /
                     reset / apply-to-all request fails. Sits in the same
                     spot as the "Saving design…" pill so the feedback
                     loop closes in the same place creators were just
                     watching. The retry button re-runs the failed
                     action; the chip auto-clears after ~6s or on the
                     next successful action. Hidden while _busy so the
                     spinner pill takes precedence during a retry. --}}
                <span x-show="_error && !_busy" x-cloak
                      class="mr-auto inline-flex items-center gap-1 text-[10px] font-bold py-1 px-2 rounded-md"
                      style="background: rgba(239,68,68,0.14); border: 1px solid rgba(239,68,68,0.4); color: #fca5a5;">
                    <i class="fas fa-triangle-exclamation text-[10px]"></i>
                    <span x-text="_error"></span>
                    <button type="button" x-show="_retry" @click="retryLastAction()"
                            class="ml-1 underline decoration-dotted text-[10px] font-bold"
                            style="color: #fecaca;"
                            title="Retry the last action">Retry</button>
                    <button type="button" @click="clearError()"
                            class="ml-0.5 text-[10px]"
                            style="color: #fca5a5;"
                            title="Dismiss">
                        <i class="fas fa-xmark"></i>
                    </button>
                </span>
                <button type="button" @click="resetStyle(false)"
                        :disabled="_busy"
                        :style="_busy ? 'opacity:0.5;cursor:not-allowed;background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);'"
                        class="text-[10px] font-bold py-1 px-2 rounded-md transition-all"
                        title="Reset this block's styling to the default">
                    <i class="fas fa-rotate-left mr-1"></i>Reset
                </button>
                <button type="button" @click="surpriseMe()"
                        :disabled="_busy"
                        :style="_busy ? 'opacity:0.6;cursor:not-allowed;background: linear-gradient(135deg, #ec4899, #5c83ff); color: white;' : 'background: linear-gradient(135deg, #ec4899, #5c83ff); color: white;'"
                        class="text-[10px] font-bold py-1 px-2 rounded-md transition-all">
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
                            :class="activeFilter === 'all' ? 'ring-1 ring-blue-400/60' : ''"
                            class="text-[9px] font-bold px-2 py-1 rounded-full transition-all"
                            :style="activeFilter === 'all' ? 'background: rgba(61,107,255,0.18); color: #90acff;' : 'background: var(--bg-glass-input); color: var(--text-faint);'">
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
                            :class="activeFilter === '{{ $tagKey }}' ? 'ring-1 ring-blue-400/60' : ''"
                            class="text-[9px] font-bold px-2 py-1 rounded-full transition-all"
                            :style="activeFilter === '{{ $tagKey }}' ? 'background: rgba(61,107,255,0.18); color: #90acff;' : 'background: var(--bg-glass-input); color: var(--text-faint);'">
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
                        <div class="text-[9px]" style="color: var(--text-dimmed);">Your tweaked styling, pick a design below to swap.</div>
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
                    :disabled="_busy"
                    class="w-full p-2 rounded-xl text-left transition-all flex items-center gap-2 hover:scale-[1.01] relative"
                    :style="_busy ? 'opacity:0.6;cursor:not-allowed;background: rgba(236,72,153,0.08); border: 1px dashed rgba(236,72,153,0.4);' : 'background: rgba(236,72,153,0.08); border: 1px dashed rgba(236,72,153,0.4);'">
                <div class="w-9 h-9 rounded-md flex items-center justify-center" style="background: rgba(236,72,153,0.18); color: #f472b6;">
                    <i class="fas fa-paint-brush text-[12px]"></i>
                </div>
                <div class="flex-1">
                    <div class="text-[11px] font-bold" style="color: var(--text-primary);">Custom (your tweaks)</div>
                    <div class="text-[9px]" style="color: var(--text-dimmed);">Restore your handcrafted styling.</div>
                </div>
                <i x-show="_busyKey !== '__custom'" class="fas fa-undo text-[10px]" style="color: #f472b6;"></i>
                <i x-show="_busyKey === '__custom'" x-cloak class="fas fa-circle-notch fa-spin text-[10px]" style="color: #f472b6;"></i>
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
                    // Browser-window variants (Task #6568) get a mini title
                    // bar with three control dots in the thumbnail so the
                    // gallery card actually looks like the retro window.
                    $isWindow = !empty($pv['window']);
                @endphp
                <button type="button"
                        data-variant-key="{{ $v['key'] }}"
                        x-show="matchesFilter(@js($v['tags'] ?? []), '{{ $v['key'] }}', @js($v['shape'] ?? ''))"
                        @click="applyVariant('{{ $v['key'] }}', $el)"
                        :disabled="_busy"
                        class="group p-2 rounded-xl text-left transition-all hover:scale-[1.03] relative"
                        :style="(currentVariant === '{{ $v['key'] }}' ? 'background: rgba(61,107,255,0.12); border: 2px solid rgba(61,107,255,0.6); box-shadow: 0 0 12px rgba(61,107,255,0.18);' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass);') + (_busy && _busyKey !== '{{ $v['key'] }}' ? ' opacity:0.5;cursor:not-allowed;' : '') + (_busy && _busyKey === '{{ $v['key'] }}' ? ' cursor:wait;' : '')">
                    {{-- Per-card "Saving…" overlay — shown only on the
                         exact variant card that was just clicked, so the
                         creator can see which design is being applied
                         while the request is in flight. --}}
                    <div x-show="_busyKey === '{{ $v['key'] }}'" x-cloak
                         class="absolute inset-0 rounded-xl flex flex-col items-center justify-center gap-1 z-10"
                         style="background: rgba(15,15,25,0.72); backdrop-filter: blur(2px);">
                        <i class="fas fa-circle-notch fa-spin text-[16px]" style="color: #bccfff;"></i>
                        <span class="text-[9px] font-bold" style="color: #bccfff;">Saving…</span>
                    </div>
                    {{-- Selected check --}}
                    <div class="absolute top-1.5 left-1.5 w-5 h-5 rounded-full flex items-center justify-center transition-all"
                         :style="currentVariant === '{{ $v['key'] }}' ? 'background: #5c83ff; opacity: 1;' : 'opacity: 0;'">
                        <i class="fas fa-check text-white text-[8px]"></i>
                    </div>
                    {{-- Favorite star --}}
                    {{-- span role=button (not <button>): this sits inside the
                         variant card <button>; a button-in-button force-closes
                         the outer one and ejects later markup from the layout. --}}
                    <span role="button" tabindex="0" @click.stop="toggleFavorite('{{ $v['key'] }}')"
                          @keydown.enter.stop.prevent="toggleFavorite('{{ $v['key'] }}')"
                          class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full flex items-center justify-center transition-all opacity-60 hover:opacity-100 cursor-pointer"
                          :style="isFavorite('{{ $v['key'] }}') ? 'background: rgba(236,72,153,0.2); color: #f472b6; opacity: 1;' : 'background: rgba(255,255,255,0.06); color: var(--text-faint);'">
                        <i :class="isFavorite('{{ $v['key'] }}') ? 'fas' : 'far'" class="fa-star text-[8px]"></i>
                    </span>

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
                        // itself is just 'link'. Helper is shared with
                        // the Controller so the live preview that swaps
                        // in later picks the same kind.
                        $shapeKind = \App\Modules\User\Support\BlockVariantCatalog::shapeKindFor($block->type, $v['shape'] ?? null);
                    @endphp
                    {{-- Thumbnail container: server-rendered live preview
                         is fetched on tab open and injected into
                         data-variant-preview="{{ $v['key'] }}". Until then
                         we render the static shape-sketch fallback so the
                         gallery is never blank. Bumped to h-20 so shape
                         differences (pill vs square vs full-image) are
                         visible at a glance instead of squinting. --}}
                    <div data-variant-preview="{{ $v['key'] }}"
                         class="h-20 rounded-lg mt-3 mb-2 overflow-hidden {{ $isWindow ? 'flex flex-col' : 'flex items-center justify-center p-2' }}"
                         style="background: {{ $thumbBg }};
                                border-radius: {{ min($thumbRadius, 24) }}px;
                                {{ $thumbBorder ? 'border:' . ($isDashed ? '2px dashed ' : ($isWindow ? '2px solid ' : '1px solid ')) . $thumbBorder . ';' : '' }}
                                {{ $thumbShadow ? 'box-shadow:' . $thumbShadow . ';' : '' }}">
                        @if($isWindow)
                            <div class="flex items-center gap-1 shrink-0" aria-hidden="true"
                                 style="padding: 3px 6px; border-bottom: 2px solid {{ $thumbBorder ?: '#111111' }};">
                                @foreach(['×', '+', '−'] as $wcDot)
                                    <span style="width: 9px; height: 9px; border: 1px solid {{ $thumbBorder ?: '#111111' }}; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: 6px; line-height: 1; color: {{ $thumbBorder ?: '#111111' }};">{{ $wcDot }}</span>
                                @endforeach
                            </div>
                            <div class="flex-1 flex items-center justify-center p-1">
                        @endif
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
                                 style="background: linear-gradient(135deg,#3d6bff,#ec4899); border-radius: {{ min($thumbRadius, 16) }}px;">
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
                            @php $dvHint = $pv['divider'] ?? 'solid'; @endphp
                            @if($dvHint === 'gradient')
                                <div style="width: 85%; height: 3px; background: linear-gradient(90deg, transparent, {{ $thumbText }}, transparent); opacity: 0.7;"></div>
                            @elseif($dvHint === 'dots')
                                <div style="width: 70%; height: 6px; background-image: radial-gradient(circle, {{ $thumbText }} 2px, transparent 3px); background-size: 14px 6px; background-position: center; background-repeat: repeat-x; opacity: 0.7;"></div>
                            @elseif($dvHint === 'zigzag')
                                <div style="width: 75%; height: 8px; background: linear-gradient(135deg, {{ $thumbText }} 25%, transparent 25%) 0 0/8px 8px repeat-x, linear-gradient(225deg, {{ $thumbText }} 25%, transparent 25%) 0 0/8px 8px repeat-x; opacity: 0.6;"></div>
                            @elseif($dvHint === 'wave')
                                <svg style="width: 75%; opacity: 0.7;" height="8"><path d="M0 4 Q4 0 8 4 T16 4 T24 4 T32 4 T40 4 T48 4 T56 4 T64 4 T72 4 T80 4 T88 4 T96 4" fill="none" stroke="{{ $thumbText }}" stroke-width="2"/></svg>
                            @elseif($dvHint === 'double')
                                <div style="width: 85%; height: 0; border-top: 3px double {{ $thumbText }}; opacity: 0.7;"></div>
                            @elseif($dvHint === 'ornament')
                                <div style="width: 80%; display: flex; align-items: center; gap: 5px; opacity: 0.75;">
                                    <div style="flex: 1 1 0%; height: 1px; background: {{ $thumbText }};"></div>
                                    <span style="color: {{ $thumbText }}; font-size: 10px; line-height: 1;">✦</span>
                                    <div style="flex: 1 1 0%; height: 1px; background: {{ $thumbText }};"></div>
                                </div>
                            @else
                                <div style="width: 80%; height: 2px; background: {{ $thumbText }}; opacity: 0.6;"></div>
                            @endif
                        @else
                            <div class="w-full" style="color: {{ $thumbText }}; {{ $isSerif ? "font-family: 'Playfair Display', serif;" : '' }}">
                                <div class="text-[8px] font-bold leading-tight">Aa Bb Cc</div>
                                <div style="height: 2px; background: {{ $thumbText }}; opacity: 0.4; margin-top: 3px; width: 80%;"></div>
                                <div style="height: 2px; background: {{ $thumbText }}; opacity: 0.4; margin-top: 2px; width: 60%;"></div>
                            </div>
                        @endif
                        @if($isWindow)
                            </div>
                        @endif
                    </div>
                    <div class="text-[10px] font-semibold truncate" style="color: var(--text-primary);">{{ $v['name'] }}</div>
                    <div class="flex flex-wrap gap-0.5 mt-0.5">
                        @foreach(($v['tags'] ?? []) as $tagKey)
                        @if(isset($variantTags[$tagKey]))
                        <span class="text-[8px] px-1 rounded" style="background: rgba(61,107,255,0.1); color: #90acff;">{{ $variantTags[$tagKey] }}</span>
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
                    :disabled="_busy"
                    class="w-full text-[10px] font-bold py-2 rounded-lg transition-all flex items-center justify-center gap-1"
                    :style="(_busy ? 'opacity:0.6;cursor:not-allowed;' : '') + 'background: var(--bg-glass-input); border: 1px dashed var(--border-glass); color: var(--text-muted);'">
                <i x-show="_busyKey !== '__all'" class="fas fa-clone text-[9px]"></i>
                <i x-show="_busyKey === '__all'" x-cloak class="fas fa-circle-notch fa-spin text-[9px]"></i>
                <span x-show="_busyKey !== '__all'">Apply this design to all <span x-text="blockTypeLabel"></span> blocks</span>
                <span x-show="_busyKey === '__all'" x-cloak>Saving design…</span>
            </button>

            {{-- Reset to default for ALL blocks of this type. Separate from
                 the per-block Reset button at the top so creators can do
                 either: zero out just this block, or zero out every block
                 of this type on the page. --}}
            <button type="button" @click="resetStyle(true)"
                    :disabled="_busy"
                    class="w-full text-[10px] font-bold py-2 rounded-lg transition-all flex items-center justify-center gap-1"
                    :style="(_busy ? 'opacity:0.6;cursor:not-allowed;' : '') + 'background: rgba(244,63,94,0.06); border: 1px dashed rgba(244,63,94,0.35); color: #fb7185;'">
                <i x-show="_busyKey !== '__reset'" class="fas fa-rotate-left text-[9px]"></i>
                <i x-show="_busyKey === '__reset'" x-cloak class="fas fa-circle-notch fa-spin text-[9px]"></i>
                <span x-show="_busyKey !== '__reset'">Reset all <span x-text="blockTypeLabel"></span> blocks to default</span>
                <span x-show="_busyKey === '__reset'" x-cloak>Saving design…</span>
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
                    @php
                        $tcVal = $st['text_color'] ?? '';
                        $tcPicker = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $tcVal) ? $tcVal : '#ffffff';
                    @endphp
                    {{-- Picker is intentionally UNNAMED: input[type=color] always holds a
                         browser-normalized solid hex, so submitting it directly would stamp
                         a color on every save even when the user never picked one. The text
                         input is the source of truth (supports empty = inherit). --}}
                    <input type="color" value="{{ $tcPicker }}" class="w-10 h-9 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);" oninput="this.nextElementSibling.value = this.value" @input="cText = $event.target.value">
                    <input type="text" name="style[text_color]" value="{{ $tcVal }}" placeholder="Inherit" class="{{ $inputClass }} flex-1" oninput="if (/^#[0-9a-fA-F]{6}$/.test(this.value)) this.previousElementSibling.value = this.value" @input="cText = $event.target.value">
                </div>
                {{-- Non-blocking WCAG contrast warning vs the block's background color (Look tab). --}}
                <template x-if="cLow()">
                    <div class="flex items-center gap-2 rounded-lg px-3 py-2 mt-2 text-[11px] font-medium"
                         style="background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.35); color: #f59e0b;"
                         data-testid="block-contrast-warning-text">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span>Low contrast (<span x-text="cFmt()"></span>) against the <span x-text="cUsingPage() ? 'page background' : 'background color'"></span>: text may be hard to read. Aim for at least 4.5:1.</span>
                    </div>
                </template>
            </div>
            @if(in_array($block->type, ['heading', 'paragraph'], true))
            {{-- Tilt (Task #5954): rotate the whole text block up to ±30°
                 for poster / scrapbook looks. 0 = level (nothing stored). --}}
            <div x-data="{ tilt: {{ (float) ($st['_tilt'] ?? 0) }} }">
                <label class="{{ $labelClass }}">Tilt <span class="opacity-60" x-text="(tilt > 0 ? '+' : '') + tilt + '°'"></span></label>
                <div class="flex items-center gap-2">
                    <input type="range" min="-30" max="30" step="1" name="style[_tilt]"
                           x-model.number="tilt" class="flex-1 accent-blue-500">
                    <button type="button" class="text-[10px] px-2 py-1 rounded-lg" style="border: 1px solid var(--border-glass); color: var(--text-muted);"
                            @click="tilt = 0; $nextTick(() => { const r = $el.parentElement.querySelector('input[type=range]'); r.dispatchEvent(new Event('input', { bubbles: true })); })">Level</button>
                </div>
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Tilts the whole text block. Great with display fonts for a hand-placed poster feel.</p>
            </div>
            @endif
        </div>
        @endif

        {{-- LOOK TAB (merged Fill + Border + FX) --}}
        <div x-show="activeStyleTab === 'appearance'" class="space-y-4" x-data="{ showAdvanced: false }">

            {{-- Display Mode --}}
            <div>
                <label class="{{ $labelClass }}">Display Mode</label>
                <div class="grid grid-cols-2 gap-2" x-data="{ mode: '{{ $st['display_mode'] ?? 'card' }}' }">
                    <label class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-all text-xs font-medium" :style="mode === 'card' ? 'background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.3); color: #5c83ff;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);'">
                        <input type="radio" name="style[display_mode]" value="card" x-model="mode" class="hidden">
                        <i class="fas fa-square text-xs"></i> Card
                    </label>
                    <label class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-all text-xs font-medium" :style="mode === 'content' ? 'background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.3); color: #5c83ff;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);'">
                        <input type="radio" name="style[display_mode]" value="content" x-model="mode" class="hidden">
                        <i class="fas fa-align-left text-xs"></i> Content Only
                    </label>
                </div>
            </div>

            {{-- Background (Task #6044): unified mode picker — None /
                 Color / Gradient builder / Preset / Image. All modes write
                 into the SAME underlying _style keys (bg_color carries both
                 solids and gradient strings, bg_preset_key the catalog pick,
                 bg_image an http(s) or /f/ vault URL) so old saves render
                 unchanged. Torn presets stay excluded at block level. --}}
            <input type="hidden" name="style[bg_opacity]" value="{{ $st['bg_opacity'] ?? 100 }}">
            @php
                $bgVal = $st['bg_color'] ?? '';
                $bgPicker = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $bgVal) ? $bgVal : '#ffffff';
                $bgIsGradient = (bool) preg_match('/^(linear|radial|conic)-gradient\(/i', (string) $bgVal);
                $bgImageVal = $st['bg_image'] ?? '';
                $blkPresets = collect(\App\Modules\User\Support\BgPresetCatalog::all())
                    ->filter(fn($p) => ($p['group'] ?? '') !== 'torn');
                $blkPresetGroups = array_filter(
                    \App\Modules\User\Support\BgPresetCatalog::GROUPS,
                    fn($k) => $k !== 'torn',
                    ARRAY_FILTER_USE_KEY
                );
                $bgInitialMode = !empty($st['bg_preset_key']) ? 'preset'
                    : ($bgImageVal !== '' ? 'image'
                    : ($bgIsGradient ? 'gradient'
                    : ($bgVal !== '' && $bgVal !== 'transparent' ? 'color' : 'none')));
            @endphp
            <div x-data="{
                bgMode: @js($bgInitialMode),
                bpGroup: 'gradients', bpSearch: '',
                bpKey: @js($st['bg_preset_key'] ?? ''),
                bpOpen: {{ !empty($st['bg_preset_key']) ? 'true' : 'false' }},
                bpOpacity: {{ max(0, min(100, (int) (is_numeric($st['bg_preset_opacity'] ?? null) ? $st['bg_preset_opacity'] : 100))) }},
                gradType: 'linear', gradAngle: 135,
                gradStops: [{ color: '#3d6bff', pos: 0 }, { color: '#ec4899', pos: 100 }],
                init() {
                    /* Best-effort parse of an existing gradient back into the
                       builder controls; unparseable strings keep the defaults
                       (the raw value stays untouched until the user edits). */
                    var v = @js($bgIsGradient ? $bgVal : '');
                    if (v) {
                        var m = v.match(/^(linear|radial|conic)-gradient\((.*)\)$/i);
                        if (m) {
                            this.gradType = m[1].toLowerCase();
                            var body = m[2];
                            var am = body.match(/(?:from\s+)?(-?\d+(?:\.\d+)?)deg/i);
                            if (am) this.gradAngle = parseInt(am[1], 10) || 0;
                            var stops = [];
                            var re = /(#[0-9a-fA-F]{3,8}|rgba?\([^)]*\))\s*(\d{1,3})?%?/g, sm;
                            while ((sm = re.exec(body)) !== null) {
                                stops.push({ color: sm[1], pos: sm[2] !== undefined ? parseInt(sm[2], 10) : null });
                            }
                            if (stops.length >= 2) {
                                stops.forEach(function(s, i) { if (s.pos === null || isNaN(s.pos)) s.pos = Math.round(i / (stops.length - 1) * 100); });
                                this.gradStops = stops;
                            }
                        }
                    }
                },
                writeInput(el, val) {
                    if (!el || el.value === val) return;
                    el.value = val;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                },
                writeBg(v) { this.writeInput(this.$refs.bgcInput, v); },
                gradCss() {
                    var stops = this.gradStops.slice().sort(function(a, b) { return a.pos - b.pos; });
                    var s = stops.map(function(x) { return x.color + ' ' + x.pos + '%'; }).join(', ');
                    if (this.gradType === 'radial') return 'radial-gradient(circle, ' + s + ')';
                    if (this.gradType === 'conic') return 'conic-gradient(from ' + this.gradAngle + 'deg, ' + s + ')';
                    return 'linear-gradient(' + this.gradAngle + 'deg, ' + s + ')';
                },
                applyGrad() { this.writeBg(this.gradCss()); },
                addStop() {
                    if (this.gradStops.length >= 8) return;
                    var last = this.gradStops[this.gradStops.length - 1];
                    this.gradStops.push({ color: '#5c83ff', pos: Math.min(100, (last ? last.pos : 50) + 10) });
                    this.applyGrad();
                },
                removeStop(i) {
                    if (this.gradStops.length <= 2) return;
                    this.gradStops.splice(i, 1);
                    this.applyGrad();
                },
                clearPreset() {
                    if (!this.bpKey) return;
                    this.bpKey = '';
                    var self = this;
                    this.$nextTick(function() { self.$refs.bpInput.dispatchEvent(new Event('change', { bubbles: true })); });
                },
                clearImage() {
                    var wrap = this.$refs.bgImgWrap;
                    var h = wrap ? wrap.querySelector('input[type=hidden]') : null;
                    if (h && h.value !== '') this.writeInput(h, '');
                },
                setMode(m) {
                    this.bgMode = m;
                    if (m === 'none') { this.writeBg(''); this.clearPreset(); this.clearImage(); }
                    if (m === 'color' || m === 'gradient') { this.clearPreset(); this.clearImage(); if (m === 'gradient') this.applyGrad(); else if (/gradient\(/.test(this.$refs.bgcInput.value)) this.writeBg(''); }
                    if (m === 'preset') { this.writeBg(''); this.clearImage(); this.bpOpen = true; }
                    if (m === 'image') { this.writeBg(''); this.clearPreset(); }
                }
            }" class="space-y-2">
                <label class="{{ $labelClass }}">Background</label>
                <div class="grid grid-cols-5 gap-1">
                    @foreach(['none' => 'None', 'color' => 'Color', 'gradient' => 'Gradient', 'preset' => 'Preset', 'image' => 'Image'] as $bmVal => $bmLabel)
                    <button type="button" @click="setMode('{{ $bmVal }}')"
                            class="text-[10px] font-semibold px-1 py-1.5 rounded-lg transition-all"
                            :style="bgMode === '{{ $bmVal }}' ? 'background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.3); color: #5c83ff;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);'">
                        {{ $bmLabel }}
                    </button>
                    @endforeach
                </div>

                {{-- Color mode. Picker is intentionally UNNAMED (see text_color
                     note above / Task #4025). The text input is the single
                     source for style[bg_color] across the Color AND Gradient
                     modes (a gradient is just a CSS string in the same key). --}}
                <div x-show="bgMode === 'color'" x-cloak class="space-y-2">
                    <div class="flex gap-2">
                        <input type="color" value="{{ $bgPicker }}" class="w-10 h-9 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);" oninput="this.nextElementSibling.value = this.value; this.nextElementSibling.dispatchEvent(new Event('input', { bubbles: true })); this.nextElementSibling.dispatchEvent(new Event('change', { bubbles: true }))">
                        <input type="text" name="style[bg_color]" x-ref="bgcInput" value="{{ $bgVal }}" placeholder="Transparent" class="{{ $inputClass }} flex-1" oninput="if (/^#[0-9a-fA-F]{6}$/.test(this.value)) this.previousElementSibling.value = this.value" @input="cBg = $event.target.value">
                    </div>
                    {{-- Non-blocking WCAG contrast warning vs the block's text color (Text tab). --}}
                    <template x-if="cLow()">
                        <div class="flex items-center gap-2 rounded-lg px-3 py-2 text-[11px] font-medium"
                             style="background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.35); color: #f59e0b;"
                             data-testid="block-contrast-warning-bg">
                            <i class="fas fa-triangle-exclamation"></i>
                            <span>Low contrast (<span x-text="cFmt()"></span>) against the text color: text may be hard to read. Aim for at least 4.5:1.</span>
                        </div>
                    </template>
                </div>

                {{-- Gradient builder mode (writes a gradient string into the
                     same style[bg_color] input above). --}}
                <div x-show="bgMode === 'gradient'" x-cloak class="space-y-2 p-2 rounded-xl" style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass);">
                    <div class="rounded-lg h-8" :style="'background:' + gradCss() + '; border: 1px solid var(--border-glass);'"></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[9px] font-bold" style="color: var(--text-dimmed);">Type</label>
                            <select x-model="gradType" @change="applyGrad()" class="{{ $inputClass }} text-[11px]">
                                <option value="linear">Linear</option>
                                <option value="radial">Radial</option>
                                <option value="conic">Conic</option>
                            </select>
                        </div>
                        <div x-show="gradType !== 'radial'">
                            <label class="text-[9px] font-bold" style="color: var(--text-dimmed);">Angle <span x-text="gradAngle + '°'"></span></label>
                            <input type="range" min="0" max="360" step="5" x-model.number="gradAngle" @input="applyGrad()" class="w-full accent-indigo-500 mt-2">
                        </div>
                    </div>
                    <template x-for="(stop, idx) in gradStops" :key="idx">
                        <div class="flex items-center gap-1.5">
                            <input type="color" :value="/^#[0-9a-fA-F]{6}$/.test(stop.color) ? stop.color : '#5c83ff'" @input="stop.color = $event.target.value; applyGrad()" class="w-8 h-7 rounded-md cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                            <input type="range" min="0" max="100" :value="stop.pos" @input="stop.pos = parseInt($event.target.value, 10); applyGrad()" class="flex-1 accent-indigo-500">
                            <span class="text-[9px] w-7 text-right font-mono" style="color: var(--text-faint);" x-text="stop.pos + '%'"></span>
                            <button type="button" @click="removeStop(idx)" x-show="gradStops.length > 2" class="text-[10px] px-1" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
                        </div>
                    </template>
                    <button type="button" @click="addStop()" x-show="gradStops.length < 8" class="text-[10px] font-semibold px-2 py-1 rounded-lg" style="color: var(--text-faint); background: var(--bg-glass); border: 1px solid var(--border-glass);"><i class="fas fa-plus mr-1" style="font-size:8px;"></i>Add color stop</button>
                </div>

                {{-- Image mode: http(s) URL, upload, or vault pick — vault
                     picks persist as root-relative /f/… paths. --}}
                <div x-show="bgMode === 'image'" x-cloak x-ref="bgImgWrap">
                    @include('user.links.partials.file-upload-field', ['fieldName' => 'style[bg_image]', 'currentValue' => $bgImageVal, 'acceptTypes' => 'image', 'labelText' => 'Background Image', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
                </div>

                {{-- Preset mode (Task #5970): catalog preset painted on a
                     layer behind the block content. --}}
                <div x-show="bgMode === 'preset'" x-cloak>
                <div class="flex items-center justify-between gap-2">
                    <label class="{{ $labelClass }}">Preset Background</label>
                    <button type="button" @click="bpOpen = !bpOpen" class="text-[10px] font-semibold px-2 py-1 rounded-lg transition-all" style="color: var(--text-faint); background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        <span x-text="bpKey ? 'Change' : (bpOpen ? 'Hide' : 'Browse')"></span>
                    </button>
                </div>
                {{-- Hidden input carries the selection; :value emits no events so
                     the swatch click fires a synthetic change FROM THIS INPUT
                     (the drawer autosave binds its listeners on the inputs
                     themselves, so a bubbled $dispatch from the swatch button
                     would never reach them — Task #5990). --}}
                <input type="hidden" name="style[bg_preset_key]" x-ref="bpInput" :value="bpKey">
                <div x-show="bpOpen" x-cloak class="space-y-2 mt-2">
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 -mx-1 px-1">
                        @foreach($blkPresetGroups as $bpgKey => $bpgLabel)
                        <button type="button" @click="bpGroup = '{{ $bpgKey }}'"
                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap transition-all"
                                :style="bpGroup === '{{ $bpgKey }}' ? 'background: rgba(61,107,255,0.25); color:#bccfff; border:1px solid rgba(61,107,255,0.5)' : 'background: var(--bg-glass-input); color: var(--text-muted); border:1px solid var(--border-glass)'">
                            {{ $bpgLabel }}
                        </button>
                        @endforeach
                        <input type="text" x-model="bpSearch" placeholder="Search…"
                               class="text-[10px] px-2 py-0.5 rounded-md flex-1 min-w-[70px]"
                               style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    </div>
                    <div class="grid grid-cols-6 gap-1 max-h-[220px] overflow-y-auto pr-1">
                        @foreach($blkPresets as $bpId => $bp)
                        <button type="button"
                                x-show="(bpGroup === '{{ $bp['group'] }}') && (!bpSearch || '{{ strtolower($bp['label']) }}'.includes(bpSearch.toLowerCase()))"
                                @click="bpKey = bpKey === '{{ $bpId }}' ? '' : '{{ $bpId }}'; $nextTick(() => $refs.bpInput.dispatchEvent(new Event('change', { bubbles: true }))); window.scrollLivePreviewIntoView && window.scrollLivePreviewIntoView()"
                                :class="bpKey === '{{ $bpId }}' ? 'ring-2 ring-blue-400' : ''"
                                class="rounded-md overflow-hidden relative transition-all hover:scale-[1.08] hover:z-10"
                                style="{{ $bp['css'] }}; width:100%; aspect-ratio:1/1; border:1px solid var(--border-glass); background-size: cover; background-position: center;"
                                title="{{ $bp['label'] }}">
                            <div x-show="bpKey === '{{ $bpId }}'"
                                 class="absolute top-0.5 right-0.5 w-3 h-3 rounded-full flex items-center justify-center"
                                 style="background: rgba(61,107,255,0.95); color:#fff;">
                                <i class="fas fa-check" style="font-size:5px;"></i>
                            </div>
                        </button>
                        @endforeach
                    </div>
                    <div x-show="bpKey">
                        <label class="{{ $labelClass }}">Preset Transparency <span class="opacity-60" x-text="bpOpacity + '%'"></span></label>
                        <input type="range" name="style[bg_preset_opacity]" min="0" max="100" step="5" x-model="bpOpacity" class="w-full">
                    </div>
                    <p class="text-[9px]" style="color: var(--text-dimmed);">Click a swatch to select, click again to remove. The preset paints behind the block's content.</p>
                </div>
                </div>
            </div>

            {{-- Glass preset (simplified). Advanced glass blur/opacity sliders
                 still appear under "More options" for back-compat. --}}
            <div>
                <label class="{{ $labelClass }}">Glassmorphism</label>
                <div class="grid grid-cols-3 gap-2">
                    @php $gp = $st['glass_preset'] ?? ''; @endphp
                    @foreach(['off' => 'Off', 'light' => 'Light', 'heavy' => 'Heavy'] as $gpVal => $gpLabel)
                        <label class="flex items-center justify-center p-2 rounded-lg cursor-pointer transition-all text-[10px] font-bold"
                               style="{{ $gp === $gpVal ? 'background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.3); color: #5c83ff;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);' }}">
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
                               style="{{ $sp === $spVal ? 'background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.3); color: #5c83ff;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-faint);' }}">
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
            @php
                $advCorners = ($st['border_radius_tl'] ?? '') !== '' || ($st['border_radius_tr'] ?? '') !== ''
                    || ($st['border_radius_bl'] ?? '') !== '' || ($st['border_radius_br'] ?? '') !== '';
            @endphp
            <div x-data="{ showCorners: {{ $advCorners ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between gap-2">
                    <label class="{{ $labelClass }}">Corner Radius (px)</label>
                    <button type="button" @click="showCorners = !showCorners" class="text-[10px] font-semibold px-2 py-1 rounded-lg transition-all" style="color: var(--text-faint); background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        <i class="fas text-[7px] mr-1" :class="showCorners ? 'fa-chevron-up' : 'fa-chevron-down'"></i>Advanced
                    </button>
                </div>
                <input type="number" name="style[border_radius]" value="{{ $st['border_radius'] ?? '' }}" placeholder="12" min="0" max="999" class="{{ $inputClass }}">
                <div x-show="showCorners" x-cloak x-transition class="mt-1 p-2 rounded-xl" style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass);">
                    <div class="grid grid-cols-4 gap-1">
                        @foreach(['tl' => 'T-L', 'tr' => 'T-R', 'bl' => 'B-L', 'br' => 'B-R'] as $ck => $cl)
                        <div>
                            <label class="text-[8px] font-bold" style="color: var(--text-dimmed);">{{ $cl }}</label>
                            <input type="number" name="style[border_radius_{{ $ck }}]" value="{{ $st['border_radius_' . $ck] ?? '' }}" placeholder="-" min="0" max="999" class="{{ $inputClass }} text-[11px]">
                        </div>
                        @endforeach
                    </div>
                    <p class="text-[9px] mt-1" style="color: var(--text-dimmed);">Blank corners use the radius above.</p>
                </div>
            </div>

            {{-- Advanced toggle --}}
            <button type="button" @click="showAdvanced = !showAdvanced" class="w-full flex items-center justify-center gap-1 text-[10px] font-semibold py-1.5 rounded-lg transition-all" style="color: var(--text-faint); background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                <i class="fas" :class="showAdvanced ? 'fa-chevron-up' : 'fa-chevron-down'" style="font-size: 7px;"></i>
                <span x-text="showAdvanced ? 'Hide advanced' : 'More options'"></span>
            </button>

            <div x-show="showAdvanced" x-cloak x-transition class="space-y-3 pt-1">
                {{-- Border. The color cell is wrapped in a borderColorField()
                     component so committed custom colors are remembered as
                     quick-pick swatches (localStorage, mirrors the mobile
                     editor's recent-border-colors behavior, Task #6094). --}}
                <div x-data="borderColorField()" @recent-border-colors-changed.document="recents = $event.detail">
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
                            @php
                                $bcVal = $st['border_color'] ?? '';
                                $bcPicker = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $bcVal) ? $bcVal : '#ffffff';
                            @endphp
                            {{-- Hidden input carries the submitted value; the picker is
                                 unnamed so its browser-normalized default never gets
                                 stamped into _style on unrelated saves (Task #4025). --}}
                            <input type="hidden" name="style[border_color]" value="{{ $bcVal }}" x-ref="val">
                            <input type="color" value="{{ $bcPicker }}" x-ref="picker" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);" oninput="this.previousElementSibling.value = this.value" @change="commit($event.target.value)">
                        </div>
                    </div>
                    {{-- Quick-pick swatches: fixed presets + recent custom colors. --}}
                    <div class="flex flex-wrap items-center gap-1.5 mt-1.5" data-border-color-swatches>
                        <template x-for="sw in presets.concat(recents)" :key="sw">
                            <button type="button" class="w-5 h-5 rounded-full cursor-pointer transition-transform hover:scale-110" :style="'background: ' + sw + '; border: 1px solid var(--border-glass);'" :title="'Use ' + sw" :aria-label="'Use border color ' + sw" @click="pick(sw)"></button>
                        </template>
                    </div>
                    <p x-show="recents.length > 0" x-cloak class="text-[9px] mt-1" style="color: var(--text-dimmed);">Your recent custom colors appear at the end of the row.</p>
                </div>
                {{-- Per-side borders (Task #6038): each side's style/width/color
                     overrides the shorthand above field-by-field; blank = use
                     the shorthand value. --}}
                @php
                    $advSides = false;
                    foreach (['top', 'right', 'bottom', 'left'] as $side) {
                        if (($st["border_{$side}_style"] ?? '') !== '' || ($st["border_{$side}_width"] ?? '') !== '' || ($st["border_{$side}_color"] ?? '') !== '') {
                            $advSides = true;
                            break;
                        }
                    }
                @endphp
                <div x-data="{ showSides: {{ $advSides ? 'true' : 'false' }} }">
                    <button type="button" @click="showSides = !showSides" class="flex items-center gap-2 text-[11px] font-semibold w-full py-1" style="color: var(--text-muted);">
                        <i class="fas fa-border-style text-[8px]" style="color: #90acff;"></i> Per-side borders
                        <i class="fas text-[7px] ml-auto" :class="showSides ? 'fa-chevron-up' : 'fa-chevron-down'" style="color: var(--text-faint);"></i>
                    </button>
                    <div x-show="showSides" x-cloak x-transition class="mt-1 p-2 rounded-xl space-y-1.5" style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass);">
                        @foreach(['top' => 'Top', 'right' => 'Right', 'bottom' => 'Bottom', 'left' => 'Left'] as $side => $sideLabel)
                        @php
                            $ssVal = $st["border_{$side}_style"] ?? '';
                            $swVal = $st["border_{$side}_width"] ?? '';
                            $scv   = $st["border_{$side}_color"] ?? '';
                            $scp   = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $scv) ? $scv : '#ffffff';
                        @endphp
                        <div x-data="borderColorField()" @recent-border-colors-changed.document="recents = $event.detail">
                            <div class="grid grid-cols-[36px_1fr_56px_36px] gap-1 items-center">
                                <span class="text-[9px] font-bold" style="color: var(--text-dimmed);">{{ $sideLabel }}</span>
                                <select name="style[border_{{ $side }}_style]" class="{{ $inputClass }} text-[11px]">
                                    <option value="" {{ $ssVal === '' ? 'selected' : '' }}>Default</option>
                                    @foreach($borderStyles as $bsVal => $bsLabel)
                                    <option value="{{ $bsVal }}" {{ $ssVal === $bsVal ? 'selected' : '' }}>{{ $bsLabel }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="style[border_{{ $side }}_width]" value="{{ $swVal }}" placeholder="-" min="0" max="10" class="{{ $inputClass }} text-[11px]">
                                <div>
                                    <input type="hidden" name="style[border_{{ $side }}_color]" value="{{ $scv }}" x-ref="val">
                                    <input type="color" value="{{ $scp }}" x-ref="picker" class="w-full h-8 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);" oninput="this.previousElementSibling.value = this.value" @change="commit($event.target.value)">
                                </div>
                            </div>
                            {{-- Quick-pick swatches for this side (presets + recents). --}}
                            <div class="flex flex-wrap items-center gap-1 mt-1 pl-9" data-border-color-swatches>
                                <template x-for="sw in presets.concat(recents)" :key="sw">
                                    <button type="button" class="w-3.5 h-3.5 rounded-full cursor-pointer transition-transform hover:scale-110" :style="'background: ' + sw + '; border: 1px solid var(--border-glass);'" :title="'Use ' + sw" :aria-label="'Use {{ strtolower($sideLabel) }} border color ' + sw" @click="pick(sw)"></button>
                                </template>
                            </div>
                        </div>
                        @endforeach
                        <p class="text-[9px]" style="color: var(--text-dimmed);">Blank fields use the border settings above. Pick "None" to remove one side.</p>
                    </div>
                </div>
                {{-- Shadow fine-tuning --}}
                <div>
                    <label class="{{ $labelClass }}">Shadow Fine-Tune</label>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-semibold flex-shrink-0" style="color: var(--text-dimmed);">Color</span>
                            @php
                                $scVal = $st['shadow_color'] ?? '';
                                $scPicker = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $scVal) ? $scVal : '#000000';
                            @endphp
                            {{-- Hidden input carries the submitted value; picker is
                                 unnamed (see border_color note, Task #4025). --}}
                            <input type="hidden" name="style[shadow_color]" value="{{ $scVal }}">
                            <input type="color" value="{{ $scPicker }}" class="w-full h-8 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);" oninput="this.previousElementSibling.value = this.value">
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
            {{-- Grid Width — per-device (Task #6119). Mobile drives the base
                 `grid_span`; Desktop drives the `grid_span_md` override that
                 only applies at/above the 768px breakpoint on the public page.
                 "Same as mobile" submits an empty value, which the controller
                 treats as "clear this key" (Task #4025 semantics). --}}
            <div x-data="{ widthDevice: 'mobile', gridSpan: '{{ $st['grid_span'] ?? 12 }}', gridSpanMd: '{{ $st['grid_span_md'] ?? '' }}' }">
                <div class="flex items-center justify-between">
                    <label class="{{ $labelClass }}">Block Width</label>
                    <div class="inline-flex rounded-lg p-0.5" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);" data-width-device-toggle>
                        <button type="button" class="px-2 py-0.5 rounded-md text-[9px] font-bold transition-all" @click="widthDevice = 'mobile'"
                                :style="widthDevice === 'mobile' ? 'background: rgba(61,107,255,0.2); color: #90acff;' : 'background: transparent; color: var(--text-faint);'">
                            <i class="fas fa-mobile-alt mr-1"></i>Mobile
                        </button>
                        <button type="button" class="px-2 py-0.5 rounded-md text-[9px] font-bold transition-all" @click="widthDevice = 'desktop'"
                                :style="widthDevice === 'desktop' ? 'background: rgba(61,107,255,0.2); color: #90acff;' : 'background: transparent; color: var(--text-faint);'">
                            <i class="fas fa-desktop mr-1"></i>Desktop
                        </button>
                    </div>
                </div>
                <div x-show="widthDevice === 'mobile'" class="grid grid-cols-6 gap-1 p-2 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                    @foreach([3 => '¼', 4 => '⅓', 6 => '½', 8 => '⅔', 9 => '¾', 12 => 'Full'] as $gv => $gl)
                    <label class="flex flex-col items-center cursor-pointer" @click="gridSpan = '{{ $gv }}'">
                        <input type="radio" name="style[grid_span]" value="{{ $gv }}" {{ ($st['grid_span'] ?? 12) == $gv ? 'checked' : '' }} class="hidden">
                        <span class="w-full text-center text-[10px] font-bold py-1.5 rounded-lg border transition-all"
                              :style="gridSpan == '{{ $gv }}' ? 'background: rgba(61,107,255,0.15); border-color: rgba(61,107,255,0.3); color: #90acff;' : 'background: transparent; border-color: transparent; color: var(--text-faint);'">{{ $gl }}</span>
                    </label>
                    @endforeach
                </div>
                <div x-show="widthDevice === 'desktop'" x-cloak class="grid grid-cols-7 gap-1 p-2 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                    @foreach(['' => 'Same', 3 => '¼', 4 => '⅓', 6 => '½', 8 => '⅔', 9 => '¾', 12 => 'Full'] as $gv => $gl)
                    <label class="flex flex-col items-center cursor-pointer" @click="gridSpanMd = '{{ $gv }}'">
                        <input type="radio" name="style[grid_span_md]" value="{{ $gv }}" {{ (string) ($st['grid_span_md'] ?? '') === (string) $gv ? 'checked' : '' }} class="hidden">
                        <span class="w-full text-center text-[10px] font-bold py-1.5 rounded-lg border transition-all" @if($gv === '') title="Same as mobile" @endif
                              :style="gridSpanMd == '{{ $gv }}' ? 'background: rgba(61,107,255,0.15); border-color: rgba(61,107,255,0.3); color: #90acff;' : 'background: transparent; border-color: transparent; color: var(--text-faint);'">{{ $gl }}</span>
                    </label>
                    @endforeach
                </div>
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);" x-text="widthDevice === 'mobile' ? 'Width on phones (and everywhere unless Desktop overrides it)' : 'Width on large screens, \'Same\' keeps the mobile width'"></p>
            </div>

            {{-- Grid Height (row span) — per-device (Task #6123). Mirrors the
                 Block Width control above: Mobile drives the base
                 `grid_row_span` ("Auto" = empty, no stretching); Desktop
                 drives the `grid_row_span_md` override applied at/above the
                 768px breakpoint ("Same" = empty = follow mobile). --}}
            <div x-data="{ heightDevice: 'mobile', rowSpan: '{{ $st['grid_row_span'] ?? '' }}', rowSpanMd: '{{ $st['grid_row_span_md'] ?? '' }}' }">
                <div class="flex items-center justify-between">
                    <label class="{{ $labelClass }}">Block Height (Rows)</label>
                    <div class="inline-flex rounded-lg p-0.5" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);" data-height-device-toggle>
                        <button type="button" class="px-2 py-0.5 rounded-md text-[9px] font-bold transition-all" @click="heightDevice = 'mobile'"
                                :style="heightDevice === 'mobile' ? 'background: rgba(61,107,255,0.2); color: #90acff;' : 'background: transparent; color: var(--text-faint);'">
                            <i class="fas fa-mobile-alt mr-1"></i>Mobile
                        </button>
                        <button type="button" class="px-2 py-0.5 rounded-md text-[9px] font-bold transition-all" @click="heightDevice = 'desktop'"
                                :style="heightDevice === 'desktop' ? 'background: rgba(61,107,255,0.2); color: #90acff;' : 'background: transparent; color: var(--text-faint);'">
                            <i class="fas fa-desktop mr-1"></i>Desktop
                        </button>
                    </div>
                </div>
                <div x-show="heightDevice === 'mobile'" class="grid grid-cols-7 gap-1 p-2 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                    @foreach(['' => 'Auto', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6'] as $rv => $rl)
                    <label class="flex flex-col items-center cursor-pointer" @click="rowSpan = '{{ $rv }}'">
                        <input type="radio" name="style[grid_row_span]" value="{{ $rv }}" {{ (string) ($st['grid_row_span'] ?? '') === (string) $rv ? 'checked' : '' }} class="hidden">
                        <span class="w-full text-center text-[10px] font-bold py-1.5 rounded-lg border transition-all" @if($rv === '') title="Automatic height" @endif
                              :style="rowSpan == '{{ $rv }}' ? 'background: rgba(61,107,255,0.15); border-color: rgba(61,107,255,0.3); color: #90acff;' : 'background: transparent; border-color: transparent; color: var(--text-faint);'">{{ $rl }}</span>
                    </label>
                    @endforeach
                </div>
                <div x-show="heightDevice === 'desktop'" x-cloak class="grid grid-cols-7 gap-1 p-2 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                    @foreach(['' => 'Same', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6'] as $rv => $rl)
                    <label class="flex flex-col items-center cursor-pointer" @click="rowSpanMd = '{{ $rv }}'">
                        <input type="radio" name="style[grid_row_span_md]" value="{{ $rv }}" {{ (string) ($st['grid_row_span_md'] ?? '') === (string) $rv ? 'checked' : '' }} class="hidden">
                        <span class="w-full text-center text-[10px] font-bold py-1.5 rounded-lg border transition-all" @if($rv === '') title="Same as mobile" @endif
                              :style="rowSpanMd == '{{ $rv }}' ? 'background: rgba(61,107,255,0.15); border-color: rgba(61,107,255,0.3); color: #90acff;' : 'background: transparent; border-color: transparent; color: var(--text-faint);'">{{ $rl }}</span>
                    </label>
                    @endforeach
                </div>
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);" x-text="heightDevice === 'mobile' ? 'Rows the block stretches across next to side-by-side blocks, \'Auto\' keeps natural height' : 'Rows on large screens, \'Same\' keeps the mobile setting'"></p>
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
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">All</label><input type="number" name="style[padding]" value="{{ $st['padding'] ?? '' }}" placeholder="-" min="0" max="60" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Top</label><input type="number" name="style[padding_top]" value="{{ $st['padding_top'] ?? '' }}" placeholder="-" min="0" max="200" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Bot</label><input type="number" name="style[padding_bottom]" value="{{ $st['padding_bottom'] ?? '' }}" placeholder="-" min="0" max="200" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Left</label><input type="number" name="style[padding_left]" value="{{ $st['padding_left'] ?? '' }}" placeholder="-" min="0" max="200" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Right</label><input type="number" name="style[padding_right]" value="{{ $st['padding_right'] ?? '' }}" placeholder="-" min="0" max="200" class="{{ $inputClass }} text-[11px]"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Margin --}}
            <div x-data="{ showMargin: {{ ($st['margin_top'] ?? '') !== '' || ($st['margin_bottom'] ?? '') !== '' || ($st['margin_left'] ?? '') !== '' || ($st['margin_right'] ?? '') !== '' ? 'true' : 'false' }} }">
                <button type="button" @click="showMargin = !showMargin" class="flex items-center gap-2 text-[11px] font-semibold w-full py-1" style="color: var(--text-muted);">
                    <i class="fas fa-arrows-alt-v text-[8px]" style="color: #fb923c;"></i> Margin
                    <i class="fas text-[7px] ml-auto" :class="showMargin ? 'fa-chevron-up' : 'fa-chevron-down'" style="color: var(--text-faint);"></i>
                </button>
                <div x-show="showMargin" x-cloak x-transition class="mt-1">
                    <div class="p-2 rounded-xl" style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass);">
                        <div class="grid grid-cols-4 gap-1">
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Top</label><input type="number" name="style[margin_top]" value="{{ $st['margin_top'] ?? '' }}" placeholder="-" min="-100" max="200" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Bot</label><input type="number" name="style[margin_bottom]" value="{{ $st['margin_bottom'] ?? '' }}" placeholder="-" min="-100" max="200" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Left</label><input type="number" name="style[margin_left]" value="{{ $st['margin_left'] ?? '' }}" placeholder="-" min="-100" max="200" class="{{ $inputClass }} text-[11px]"></div>
                            <div><label class="text-[8px] font-bold" style="color: var(--text-dimmed);">Right</label><input type="number" name="style[margin_right]" value="{{ $st['margin_right'] ?? '' }}" placeholder="-" min="-100" max="200" class="{{ $inputClass }} text-[11px]"></div>
                        </div>
                        {{-- Task #6114: the page has no side padding — a block's
                             Left/Right margin of 0 makes it truly full width.
                             Quick affordance sets both sides to 0 (or clears
                             them back to the page default). --}}
                        <div class="mt-1.5 flex items-center justify-between gap-2">
                            <button type="button"
                                    class="text-[9px] font-bold px-2 py-1 rounded-md transition-colors"
                                    style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);"
                                    @click="
                                        const inputs = ['margin_left','margin_right'].map(k => $root.querySelector(`[name='style[${k}]']`)).filter(Boolean);
                                        const isFull = inputs.length && inputs.every(i => i.value === '0');
                                        inputs.forEach(i => {
                                            i.value = isFull ? '' : '0';
                                            i.dispatchEvent(new Event('input', { bubbles: true }));
                                            i.dispatchEvent(new Event('change', { bubbles: true }));
                                        });
                                    ">
                                <i class="fas fa-left-right text-[8px] mr-1" style="color: #fb923c;"></i>Full width
                            </button>
                            <span class="text-[8px]" style="color: var(--text-faint);">Left/Right 0 = edge-to-edge</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@endif

<script>
// Recent border colors store (Task #6102). Mirrors the mobile editor's
// behavior (Task #6094): custom hex colors committed in any border color
// field are remembered on-device (localStorage, most recent first, capped
// at 5) and rendered as quick-pick swatches after the fixed presets.
// Preset duplicates are never re-added to the recents list. Uses the same
// storage key name as mobile for conceptual parity.
window.__recentBorderColors = window.__recentBorderColors || {
    KEY: 'biolink.editor.recentBorderColors',
    PRESETS: ['#ffffff', '#0f172a', '#7d9bff', '#f59e0b', '#ef4444', '#10b981', '#ec4899', '#8b5cf6'],
    MAX: 5,
    // Normalizes a color to a lowercase #rgb/#rrggbb/#rrggbbaa hex string,
    // or null when it isn't a plain hex color.
    normalize: function (raw) {
        var v = String(raw == null ? '' : raw).trim().toLowerCase();
        return /^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/.test(v) ? v : null;
    },
    load: function () {
        var out = [];
        try {
            var arr = JSON.parse(localStorage.getItem(this.KEY) || '[]');
            if (!Array.isArray(arr)) return out;
            for (var i = 0; i < arr.length && out.length < this.MAX; i++) {
                var hex = this.normalize(arr[i]);
                if (hex && this.PRESETS.indexOf(hex) === -1 && out.indexOf(hex) === -1) out.push(hex);
            }
        } catch (e) { /* corrupted storage → start fresh */ }
        return out;
    },
    remember: function (raw) {
        var hex = this.normalize(raw);
        if (!hex || this.PRESETS.indexOf(hex) !== -1) return this.load();
        var next = [hex].concat(this.load().filter(function (c) { return c !== hex; })).slice(0, this.MAX);
        try { localStorage.setItem(this.KEY, JSON.stringify(next)); } catch (e) { /* storage full/blocked */ }
        // Keep every open border color field's swatch row in sync.
        document.dispatchEvent(new CustomEvent('recent-border-colors-changed', { detail: next }));
        return next;
    }
};

// Alpine component for one border color field (hidden value input +
// unnamed color picker + swatch row). x-refs: `val` (hidden submitted
// input) and `picker` (the <input type="color">).
window.borderColorField = window.borderColorField || function () {
    var store = window.__recentBorderColors;
    return {
        presets: store.PRESETS,
        recents: store.load(),
        // Remember a committed color (picker close or swatch tap).
        commit: function (v) {
            this.recents = store.remember(v);
        },
        // Fill this field from a tapped swatch and notify autosave/live
        // preview listeners via bubbled input/change events.
        pick: function (sw) {
            var hidden = this.$refs.val;
            var picker = this.$refs.picker;
            if (!hidden) return;
            hidden.value = sw;
            if (picker && /^#[0-9a-f]{6}$/.test(sw)) picker.value = sw;
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
            this.commit(sw);
        }
    };
};

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
        // Reactive busy flags so the gallery can render a "Saving design…"
        // pill and a spinner overlay on the just-clicked card while an
        // apply / restore / reset / apply-to-all request is in flight.
        // _busyKey is the variant key being applied (or one of the
        // sentinel values '__custom' / '__reset' / '__all') so we can
        // pinpoint exactly which control to overlay the spinner on.
        _busy: false,
        _busyKey: '',
        // Inline-error state for the red "Couldn't save — try again" chip
        // that closes the loop when an apply / restore / reset /
        // apply-to-all request fails. _retry holds a closure that
        // re-runs the failed action with its original args; _errorTimer
        // is the auto-clear handle (~6s). showError / clearError /
        // retryLastAction below own all transitions.
        _error: '',
        _retry: null,
        _errorTimer: null,
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
            // Bind once so add/remove pair to the same function reference.
            // While _error is set the gallery installs a beforeunload guard
            // so creators don't silently lose their last failed design click
            // by closing the tab or navigating away. The handler is removed
            // again the moment _error clears (success, retry, dismiss, or
            // the 6s auto-clear timer).
            var self = this;
            this._beforeUnloadHandler = function(e) {
                if (!self._error) return;
                e.preventDefault();
                e.returnValue = '';
                return '';
            };
        },

        destroy() {
            this._uninstallUnloadGuard();
            if (this._errorTimer) { clearTimeout(this._errorTimer); this._errorTimer = null; }
        },

        _installUnloadGuard() {
            if (this._unloadGuardInstalled) return;
            window.addEventListener('beforeunload', this._beforeUnloadHandler);
            this._unloadGuardInstalled = true;
        },
        _uninstallUnloadGuard() {
            if (!this._unloadGuardInstalled) return;
            window.removeEventListener('beforeunload', this._beforeUnloadHandler);
            this._unloadGuardInstalled = false;
        },

        catalog() {
            return window.__blockVariants[this.blockType] || [];
        },

        // --- Inline error chip helpers --------------------------------
        // showError surfaces the red chip with a friendly message and an
        // optional retry closure. clearError tears it down (called both
        // manually via the X button and automatically on the next
        // successful action / after the 6s timer). retryLastAction is
        // bound to the chip's Retry button — it grabs the current
        // closure and clears the chip before invoking it so a second
        // failure replaces the chip cleanly instead of stacking.
        showError(msg, retryFn) {
            var self = this;
            this._error = msg || "Couldn't save, try again";
            this._retry = retryFn || null;
            if (this._errorTimer) clearTimeout(this._errorTimer);
            this._errorTimer = setTimeout(function() { self.clearError(); }, 6000);
            this._installUnloadGuard();
        },
        clearError() {
            this._error = '';
            this._retry = null;
            if (this._errorTimer) { clearTimeout(this._errorTimer); this._errorTimer = null; }
            this._uninstallUnloadGuard();
        },
        retryLastAction() {
            var fn = this._retry;
            this.clearError();
            if (typeof fn === 'function') fn();
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
            var rawLabel = @js((string) ($block->settings['label'] ?? $block->settings['text'] ?? ''));
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !Array.isArray(data.previews)) return;
                    self._previewsLoaded = true;
                    data.previews.forEach(function(p) {
                        var slot = self.$el.querySelector('[data-variant-preview="' + p.key + '"]');
                        if (!slot) return;
                        // The chrome wrapper keeps a subtle dark-modal-
                        // safe frame so every variant — including
                        // transparent / outline / plain-text ones — has
                        // a visible silhouette. The inner element gets
                        // the variant's real inline_style (bg, border,
                        // radius, shadow, colour) so the preview looks
                        // like the live block.
                        slot.setAttribute('style', 'height:80px;display:flex;align-items:center;justify-content:center;overflow:hidden;margin:12px 0 8px;padding:8px;background:repeating-linear-gradient(45deg,rgba(255,255,255,0.04) 0 6px,rgba(255,255,255,0.015) 6px 12px);border:1px solid rgba(255,255,255,0.08);border-radius:8px;');
                        slot.innerHTML = self.buildLivePreviewInner(p, rawLabel);
                    });
                })
                .catch(function() {})
                .finally(function() { self._previewsLoading = false; });
        },

        // Render the inner sketch for one variant's live preview, picked
        // by the server-supplied `shape_kind`. Buttons get a button,
        // headings get bold heading text, images / avatars get their own
        // silhouettes, dividers get a horizontal rule, plain links get
        // an underlined snippet, and everything else gets a tiny text
        // sample. This is the fix for non-button blocks — previously
        // every shape collapsed to "tiny text chip" which made the
        // Designs gallery look broken on image / avatar / heading /
        // divider blocks.
        buildLivePreviewInner(p, rawLabel) {
            var inline = p.inline_style || '';
            var color = p.text_color || '#ffffff';
            var safe = String(rawLabel || '').replace(/[<>&"]/g, '').slice(0, 18);
            var label = safe || p.name || 'Preview';
            // Retro browser-window variants (Task #6568): wrap the sketch
            // in a mini window frame — title bar with three control dots,
            // thick border, hard offset shadow — matching the public
            // renderer's chrome so the preview is honest.
            if (p.window_chrome) {
                var dot = function(g) { return '<span style="width:10px;height:10px;border:1px solid #111;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-size:7px;line-height:1;color:#111;">' + g + '</span>'; };
                return '<div style="background:#f6f4ef;border:2px solid #111;box-shadow:4px 4px 0 #111;max-width:92%;color:' + color + ';">'
                    + '<div style="display:flex;align-items:center;gap:3px;padding:3px 7px;border-bottom:2px solid #111;">' + dot('×') + dot('+') + dot('−') + '</div>'
                    + '<div style="padding:7px 10px;font-size:10px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + label + '</div>'
                    + '</div>';
            }
            switch (p.shape_kind) {
                case 'button':
                case 'button_outline':
                    return '<div style="' + inline + 'display:inline-flex;align-items:center;justify-content:center;padding:6px 14px;font-size:11px;font-weight:600;color:' + color + ';white-space:nowrap;max-width:96%;overflow:hidden;text-overflow:ellipsis;">' + label + '</div>';
                case 'plain_link':
                    return '<span style="font-size:11px;font-weight:500;text-decoration:underline;text-underline-offset:2px;color:' + color + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:96%;">' + label + ' →</span>';
                case 'image':
                    return '<div style="' + inline + 'width:78%;height:88%;display:flex;align-items:center;justify-content:center;color:' + color + ';opacity:0.9;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="1.6" fill="currentColor" stroke="none"/><path d="M21 15l-5-5L5 21"/></svg></div>';
                case 'image_btn':
                    return '<div style="' + inline + 'width:88%;height:88%;display:flex;align-items:flex-end;padding:6px;color:#fff;background-image:linear-gradient(135deg,#3d6bff,#ec4899);"><span style="font-size:9px;font-weight:700;text-shadow:0 1px 2px rgba(0,0,0,0.4);max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + label + '</span></div>';
                case 'avatar':
                    return '<div style="' + inline + 'width:48px;height:48px;border-radius:999px;display:flex;align-items:center;justify-content:center;color:' + color + ';font-weight:700;font-size:14px;">' + (label.charAt(0).toUpperCase() || 'A') + '</div>';
                case 'heading':
                    return '<div style="' + inline + 'padding:6px 10px;font-size:13px;font-weight:700;color:' + color + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:96%;line-height:1.2;">' + (label || 'Heading') + '</div>';
                case 'divider': {
                    // Sketch the actual divider preset (Task #6581) from
                    // the variant's content-settings payload.
                    var ds = p.divider_settings || {};
                    var dc = ds.color || 'rgba(255,255,255,0.55)';
                    var dt = Math.max(1, Math.min(6, parseInt(ds.thickness, 10) || 1));
                    var dw = Math.max(30, Math.min(100, parseInt(ds.width, 10) || 100));
                    var seg;
                    switch (ds.style) {
                        case 'gradient':
                            seg = '<div style="flex:1 1 0%;height:' + dt + 'px;background:linear-gradient(90deg,transparent,' + dc + ',transparent);"></div>'; break;
                        case 'dots':
                            seg = '<div style="flex:1 1 0%;height:' + (dt * 3) + 'px;background-image:radial-gradient(circle,' + dc + ' ' + dt + 'px,transparent ' + (dt + 1) + 'px);background-size:' + (dt * 9) + 'px ' + (dt * 3) + 'px;background-position:center;background-repeat:repeat-x;"></div>'; break;
                        case 'zigzag':
                            seg = '<div style="flex:1 1 0%;height:8px;background:linear-gradient(135deg,' + dc + ' 25%,transparent 25%) 0 0/8px 8px repeat-x,linear-gradient(225deg,' + dc + ' 25%,transparent 25%) 0 0/8px 8px repeat-x;"></div>'; break;
                        case 'wave':
                            seg = '<svg style="flex:1 1 0%;display:block;" height="8" width="100%"><path d="M0 4 Q4 0 8 4 T16 4 T24 4 T32 4 T40 4 T48 4 T56 4 T64 4 T72 4 T80 4 T88 4 T96 4 T104 4 T112 4 T120 4" fill="none" stroke="' + dc + '" stroke-width="' + dt + '"/></svg>'; break;
                        case 'double':
                            seg = '<div style="flex:1 1 0%;height:0;border-top:3px double ' + dc + ';"></div>'; break;
                        case 'dashed':
                        case 'dotted':
                            seg = '<div style="flex:1 1 0%;height:0;border-top:' + dt + 'px ' + ds.style + ' ' + dc + ';"></div>'; break;
                        default:
                            seg = '<div style="flex:1 1 0%;height:' + dt + 'px;background:' + dc + ';"></div>';
                    }
                    var orn = '';
                    if (ds.ornament_icon) orn = '<span style="flex:0 0 auto;color:' + dc + ';font-size:11px;">★</span>';
                    else if (ds.ornament_text) orn = '<span style="flex:0 0 auto;color:' + dc + ';font-size:11px;">' + String(ds.ornament_text).slice(0, 4) + '</span>';
                    return '<div style="width:' + dw + '%;display:flex;align-items:center;gap:6px;">' + (orn ? seg + orn + seg : seg) + '</div>';
                }
                case 'text':
                default:
                    return '<div style="' + inline + 'padding:6px 10px;color:' + color + ';font-size:10px;line-height:1.3;max-width:96%;overflow:hidden;"><div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + label + '</div><div style="opacity:0.6;font-size:9px;margin-top:2px;">Lorem ipsum dolor sit amet</div></div>';
            }
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
            // Ignore re-clicks while a previous apply is still in flight —
            // the dev logs showed creators frantically clicking variants
            // when the preview/controls didn't refresh, queueing up
            // duplicate /apply-variant POSTs. With the refresh wired up
            // a single click is now visibly responsive, so dedupe here.
            if (this._busy) return;
            var v = this.catalog().find(function(x) { return x.key === key; });
            if (!v) return;
            this._busy = true;
            this._busyKey = key;
            // Snapshot the previously-applied selection BEFORE the
            // optimistic swap so the failure branch can roll back to
            // exactly what the creator had selected before the click.
            // Without this, a rejected request would leave the gallery
            // showing the new variant as "selected" even though the
            // server kept the old one.
            var prevVariant = this.currentVariant;
            var prevHasCustom = this.hasCustomStyle;
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
            var rollback = function() {
                self.currentVariant = prevVariant;
                self.hasCustomStyle = prevHasCustom;
            };
            var retry = function() { self.applyVariant(key, btn); };
            fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        self.clearError();
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
                        rollback();
                        self.showError("Couldn't save, try again", retry);
                    }
                })
                .catch(function() {
                    rollback();
                    self.showError("Couldn't save, try again", retry);
                })
                .finally(function() { self._busy = false; self._busyKey = ''; });
        },

        restoreCustom() {
            if (this._busy) return;
            // Hit the dedicated restore-custom-style endpoint, which
            // does a full `_style` REPLACE from the server-side snapshot
            // (STYLE_DEFAULTS + snapshot, with the variant key cleared).
            // The snapshot itself stays on the block so the user can
            // explore variants again and come back later.
            if (!this.customSnapshot) return;
            this._busy = true;
            this._busyKey = '__custom';
            // Snapshot prior selection so a rejected restore rolls the
            // gallery back to whichever variant was actually applied.
            var prevVariant = this.currentVariant;
            var prevHasCustom = this.hasCustomStyle;
            this.currentVariant = '';
            this.hasCustomStyle = true;
            var url = '{{ route('user.links.blocks.restoreCustomStyle', [$link, $block]) }}';
            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
            var fd = new FormData();
            if (token) fd.append('_token', token);
            var self = this;
            var rollback = function() {
                self.currentVariant = prevVariant;
                self.hasCustomStyle = prevHasCustom;
            };
            var retry = function() { self.restoreCustom(); };
            fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        self.clearError();
                        if (typeof showToast === 'function') showToast('Custom styling restored', 'success');
                        if (typeof refreshBlockEditor === 'function') refreshBlockEditor();
                        if (typeof refreshPreview === 'function') refreshPreview();
                    } else {
                        rollback();
                        self.showError("Couldn't save, try again", retry);
                    }
                })
                .catch(function() {
                    rollback();
                    self.showError("Couldn't save, try again", retry);
                })
                .finally(function() { self._busy = false; self._busyKey = ''; });
        },

        resetStyle(applyToAll) {
            if (this._busy) return;
            // Wipes _style back to STYLE_DEFAULTS server-side. When
            // applyToAll is true, every block of the same type on this
            // page is reset; otherwise just this one block is.
            var label = applyToAll
                ? ('Reset every ' + this.blockTypeLabel + ' block to the default styling? This will clear any custom tweaks.')
                : 'Reset this block to the default styling? This will clear any custom tweaks.';
            if (typeof confirm === 'function' && !confirm(label)) return;
            this._busy = true;
            this._busyKey = applyToAll ? '__reset' : '__reset_one';
            var url = '{{ route('user.links.blocks.resetStyle', [$link, $block]) }}';
            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
            var fd = new FormData();
            if (applyToAll) fd.append('apply_to_all', '1');
            if (token) fd.append('_token', token);
            var self = this;
            // Reset has no optimistic UI to roll back (state only flips
            // on success), but we still surface the friendly chip + a
            // retry that re-runs the same scope. The retry skips confirm
            // because the user already confirmed when they clicked once.
            var retry = function() {
                self._busy = true;
                self._busyKey = applyToAll ? '__reset' : '__reset_one';
                fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                    .then(handle).catch(fail)
                    .finally(function() { self._busy = false; self._busyKey = ''; });
            };
            var handle = function(r) {
                return r.json().then(function(data) {
                    if (data && data.success) {
                        self.clearError();
                        self.currentVariant = '';
                        self.hasCustomStyle = false;
                        self.customSnapshot = null;
                        if (typeof showToast === 'function') {
                            showToast(applyToAll ? ('Reset ' + (data.updated || 0) + ' block(s) to default') : 'Block reset to default', 'success');
                        }
                        if (typeof refreshBlockEditor === 'function') refreshBlockEditor();
                        if (typeof refreshPreview === 'function') refreshPreview();
                    } else {
                        self.showError("Couldn't save, try again", retry);
                    }
                });
            };
            var fail = function() { self.showError("Couldn't save, try again", retry); };
            fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                .then(handle).catch(fail)
                .finally(function() { self._busy = false; self._busyKey = ''; });
        },

        applyToAll() {
            if (this._busy) return;
            if (!this.currentVariant) return;
            if (typeof confirm === 'function' && !confirm('Apply this design to every ' + this.blockTypeLabel + ' block on this page?')) return;
            this._busy = true;
            this._busyKey = '__all';
            var self = this;
            var url = '{{ route('user.links.blocks.applyVariantToAll', [$link, $block]) }}';
            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
            var fd = new FormData();
            fd.append('variant', this.currentVariant);
            if (token) fd.append('_token', token);
            // Apply-to-all has no optimistic state on this block (it
            // fans out to siblings server-side), so the chip just needs
            // a friendly message + a retry that re-runs the same fetch
            // without re-prompting the confirm dialog.
            var retry = function() {
                self._busy = true;
                self._busyKey = '__all';
                fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                    .then(handle).catch(fail)
                    .finally(function() { self._busy = false; self._busyKey = ''; });
            };
            var handle = function(r) {
                return r.json().then(function(data) {
                    if (data && data.success) {
                        self.clearError();
                        if (typeof showToast === 'function') showToast('Applied to ' + data.updated + ' block(s)', 'success');
                        if (typeof refreshPreview === 'function') refreshPreview();
                    } else {
                        self.showError("Couldn't save, try again", retry);
                    }
                });
            };
            var fail = function() { self.showError("Couldn't save, try again", retry); };
            fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                .then(handle).catch(fail)
                .finally(function() { self._busy = false; self._busyKey = ''; });
        },
    };
};

{{-- Presets feature was removed by user request; the Designs / Text / Look / Layout
     tabs cover all styling needs without the rigid preset grid. --}}
</script>
