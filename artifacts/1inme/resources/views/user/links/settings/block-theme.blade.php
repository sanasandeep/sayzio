@extends('user.layouts.app')
@section('title', 'Block Theme - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php
    $bs = $link->settings['biolink'] ?? [];
    $bt = $bs['block_theme'] ?? [];
    $activeSettingsTab = 'block-theme';
    $gtFonts = ['', 'Space Grotesk', 'Inter', 'Poppins', 'Roboto', 'Playfair Display', 'Montserrat', 'DM Sans', 'Outfit'];
    $gtWeights = ['' => 'Default', '300' => 'Light', '400' => 'Regular', '500' => 'Medium', '600' => 'Semi Bold', '700' => 'Bold', '800' => 'Extra Bold'];
    $gtBorderStyles = ['none' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'];
    $gtShadowTypes = ['none' => 'None', 'soft' => 'Soft', 'hard' => 'Hard', 'neon' => 'Neon Glow', 'glow' => 'Subtle Glow', 'neumorphic' => 'Neumorphic', 'inset' => 'Inner Shadow'];
    $gtEffects = ['none' => 'None', 'glass' => 'Glassmorphism', 'gradient_border' => 'Gradient Border'];
    $gtTemplates = \App\Modules\User\Models\BiolinkBlock::BLOCK_TEMPLATES;
@endphp

<div class="w-full">
    @include('user.links.partials.settings-header', ['link' => $link, 'activeSettingsTab' => $activeSettingsTab])

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7">
            <form method="POST" action="{{ route('user.links.page-settings', $link) }}" enctype="multipart/form-data">
                @csrf

                <div class="card-premium p-6" x-data="{ gtTab: 'templates' }">
                    <div class="flex items-center justify-between mb-5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.1);"><i class="fas fa-wand-magic-sparkles text-purple-400 text-xs"></i></div>
                            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Global Block Theme</h3>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer px-3 py-1.5 rounded-lg transition-all" style="background: rgba(139,92,246,0.06); border: 1px solid rgba(139,92,246,0.12);">
                            <input type="hidden" name="block_theme[apply_to_all]" value="0">
                            <input type="checkbox" name="block_theme[apply_to_all]" value="1" {{ ($bt['apply_to_all'] ?? false) ? 'checked' : '' }} class="rounded text-purple-500 focus:ring-purple-500/40 w-4 h-4" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                            <span class="text-[11px] font-semibold" style="color: var(--text-muted);">Apply to all</span>
                        </label>
                    </div>

                    <div class="flex gap-1 p-0.5 rounded-lg mb-5" style="background: var(--bg-glass-input);">
                        @foreach(['templates' => 'Templates', 'text' => 'Text', 'fill' => 'Fill & Spacing', 'border' => 'Border & Shadow', 'fx' => 'Effects'] as $tabKey => $tabLabel)
                        <button type="button" @click="gtTab = '{{ $tabKey }}'"
                                :class="gtTab === '{{ $tabKey }}' ? 'text-white shadow-sm' : ''"
                                :style="gtTab === '{{ $tabKey }}' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);'"
                                class="flex-1 text-[10px] font-bold py-2 rounded-md transition-all">{{ $tabLabel }}</button>
                        @endforeach
                    </div>

                    <div x-show="gtTab === 'templates'" class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach($gtTemplates as $tKey => $tpl)
                        <button type="button" class="p-3 rounded-xl text-left transition-all hover:scale-[1.02]" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"
                                onclick="applyGlobalTemplate('{{ $tKey }}', this)">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded flex items-center justify-center" style="background: {{ $tpl['preview_bg'] }};"><i class="fas {{ $tpl['icon'] }} text-[9px]" style="color: {{ $tpl['preview_text'] }};"></i></div>
                                <span class="text-[11px] font-semibold" style="color: var(--text-primary);">{{ $tpl['label'] }}</span>
                            </div>
                        </button>
                        @endforeach
                    </div>

                    <div x-show="gtTab === 'text'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Family</label>
                                <select name="block_theme[font_family]" class="theme-input w-full">
                                    <option value="">Inherit from page</option>
                                    @foreach($gtFonts as $f)
                                        @if($f)<option value="{{ $f }}" {{ ($bt['font_family'] ?? '') === $f ? 'selected' : '' }}>{{ $f }}</option>@endif
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Weight</label>
                                <select name="block_theme[font_weight]" class="theme-input w-full">
                                    @foreach($gtWeights as $wVal => $wLabel)
                                    <option value="{{ $wVal }}" {{ ($bt['font_weight'] ?? '') == $wVal ? 'selected' : '' }}>{{ $wLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Size (px)</label>
                                <input type="number" name="block_theme[font_size]" value="{{ $bt['font_size'] ?? 14 }}" min="8" max="72" class="theme-input w-full">
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="block_theme[text_color]" value="{{ $bt['text_color'] ?? '#ffffff' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-glass);">
                                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bt['text_color'] ?? '#ffffff' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Text Alignment</label>
                                <select name="block_theme[text_align]" class="theme-input w-full">
                                    @foreach(['left' => 'Left', 'center' => 'Center', 'right' => 'Right'] as $aVal => $aLabel)
                                    <option value="{{ $aVal }}" {{ ($bt['text_align'] ?? 'center') === $aVal ? 'selected' : '' }}>{{ $aLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Letter Spacing (px)</label>
                                <input type="number" name="block_theme[letter_spacing]" value="{{ $bt['letter_spacing'] ?? 0 }}" min="-5" max="20" step="0.5" class="theme-input w-full">
                            </div>
                        </div>
                    </div>

                    <div x-show="gtTab === 'fill'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="block_theme[bg_color]" value="{{ $bt['bg_color'] ?? '#1a1a2e' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-glass);">
                                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bt['bg_color'] ?? '#1a1a2e' }}</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Opacity (%)</label>
                                <input type="number" name="block_theme[bg_opacity]" value="{{ $bt['bg_opacity'] ?? 100 }}" min="0" max="100" class="theme-input w-full">
                            </div>
                        </div>
                        <div style="border-top: 1px solid var(--border-subtle);" class="pt-4">
                            <p class="text-xs font-semibold mb-3" style="color: var(--text-muted);">Padding</p>
                            <div class="grid grid-cols-5 gap-2">
                                <div><label class="block text-[10px] mb-1" style="color: var(--text-faint);">All</label><input type="number" name="block_theme[padding]" value="{{ $bt['padding'] ?? 16 }}" min="0" max="60" class="theme-input w-full"></div>
                                <div><label class="block text-[10px] mb-1" style="color: var(--text-faint);">Top</label><input type="number" name="block_theme[padding_top]" value="{{ $bt['padding_top'] ?? '' }}" placeholder="—" min="0" max="200" class="theme-input w-full"></div>
                                <div><label class="block text-[10px] mb-1" style="color: var(--text-faint);">Bottom</label><input type="number" name="block_theme[padding_bottom]" value="{{ $bt['padding_bottom'] ?? '' }}" placeholder="—" min="0" max="200" class="theme-input w-full"></div>
                                <div><label class="block text-[10px] mb-1" style="color: var(--text-faint);">Left</label><input type="number" name="block_theme[padding_left]" value="{{ $bt['padding_left'] ?? '' }}" placeholder="—" min="0" max="200" class="theme-input w-full"></div>
                                <div><label class="block text-[10px] mb-1" style="color: var(--text-faint);">Right</label><input type="number" name="block_theme[padding_right]" value="{{ $bt['padding_right'] ?? '' }}" placeholder="—" min="0" max="200" class="theme-input w-full"></div>
                            </div>
                            <p class="text-[10px] mt-2" style="color: var(--text-dimmed);">Set "All" for uniform padding, or override individual sides.</p>
                        </div>
                    </div>

                    <div x-show="gtTab === 'border'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Border Style</label>
                                <select name="block_theme[border_style]" class="theme-input w-full">
                                    @foreach($gtBorderStyles as $bsVal => $bsLabel)
                                    <option value="{{ $bsVal }}" {{ ($bt['border_style'] ?? 'none') === $bsVal ? 'selected' : '' }}>{{ $bsLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Border Width (px)</label>
                                <input type="number" name="block_theme[border_width]" value="{{ $bt['border_width'] ?? 1 }}" min="0" max="10" class="theme-input w-full">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Border Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="block_theme[border_color]" value="{{ $bt['border_color'] ?? '#2d2d3d' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-glass);">
                                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bt['border_color'] ?? '#2d2d3d' }}</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Border Radius (px)</label>
                                <input type="number" name="block_theme[border_radius]" value="{{ $bt['border_radius'] ?? 12 }}" min="0" max="999" class="theme-input w-full">
                            </div>
                        </div>
                        <div style="border-top: 1px solid var(--border-subtle);" class="pt-4">
                            <p class="text-xs font-semibold mb-3" style="color: var(--text-muted);">Shadow</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Shadow Type</label>
                                    <select name="block_theme[shadow_type]" class="theme-input w-full">
                                        @foreach($gtShadowTypes as $shVal => $shLabel)
                                        <option value="{{ $shVal }}" {{ ($bt['shadow_type'] ?? 'none') === $shVal ? 'selected' : '' }}>{{ $shLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Shadow Blur (px)</label>
                                    <input type="number" name="block_theme[shadow_blur]" value="{{ $bt['shadow_blur'] ?? 12 }}" min="0" max="100" class="theme-input w-full">
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Shadow Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="block_theme[shadow_color]" value="{{ $bt['shadow_color'] ?? '#000000' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-glass);">
                                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bt['shadow_color'] ?? '#000000' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="gtTab === 'fx'" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-2" style="color: var(--text-muted);">Effect</label>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach($gtEffects as $eVal => $eLabel)
                                <label class="flex items-center gap-2 p-3 rounded-xl cursor-pointer transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                    <input type="radio" name="block_theme[effect]" value="{{ $eVal }}" {{ ($bt['effect'] ?? 'none') === $eVal ? 'checked' : '' }} class="text-purple-500 focus:ring-purple-500/40" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                                    <span class="text-[11px] font-semibold" style="color: var(--text-muted);">{{ $eLabel }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Glass Blur (px)</label>
                                <input type="range" name="block_theme[glass_blur]" value="{{ $bt['glass_blur'] ?? 20 }}" min="0" max="100" class="w-full accent-purple-500" oninput="this.nextElementSibling.textContent = this.value + 'px'">
                                <span class="text-[10px] font-mono" style="color: var(--text-faint);">{{ $bt['glass_blur'] ?? 20 }}px</span>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Glass Opacity (%)</label>
                                <input type="range" name="block_theme[glass_opacity]" value="{{ $bt['glass_opacity'] ?? 15 }}" min="0" max="100" class="w-full accent-purple-500" oninput="this.nextElementSibling.textContent = this.value + '%'">
                                <span class="text-[10px] font-mono" style="color: var(--text-faint);">{{ $bt['glass_opacity'] ?? 15 }}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                @include('user.links.partials.settings-footer', ['link' => $link])
            </form>
        </div>

        <div class="lg:col-span-5 hidden lg:block">
            <div class="sticky top-6">
                @include('user.links.partials.settings-device-preview', ['link' => $link])
            </div>
        </div>
    </div>
</div>

<script>
var globalTemplates = @json($gtTemplates);
function applyGlobalTemplate(key, btn) {
    var tpl = globalTemplates[key];
    if (!tpl) return;
    var form = btn.closest('form');
    if (!form) return;
    var style = tpl.style;
    for (var prop in style) {
        var input = form.querySelector('[name="block_theme[' + prop + ']"]');
        if (input) {
            input.value = style[prop];
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    btn.style.transform = 'scale(0.95)';
    setTimeout(function() { btn.style.transform = ''; }, 150);
}
</script>
@endsection
