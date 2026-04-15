@php
    $ist = $imgStyle ?? [];
    $maskShapes = [
        'none' => 'None (Default)',
        'rounded' => 'Rounded',
        'circle' => 'Circle',
        'square' => 'Square',
        'diamond' => 'Diamond',
        'hexagon' => 'Hexagon',
        'octagon' => 'Octagon',
        'star' => 'Star',
        'blob' => 'Blob',
        'arch' => 'Arch',
    ];
    $imgBorderStyles = ['none' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'];
    $imgShadowTypes = [
        'none' => 'None',
        'soft' => 'Soft Shadow',
        'hard' => 'Hard Shadow',
        'glow' => 'Glow',
        'neon' => 'Neon Glow',
        'drop' => 'Drop Shadow (CSS filter)',
    ];
@endphp

<div class="mt-4 pt-4" style="border-top: 1px solid var(--border-subtle);" x-data="{ showImgStyle: false }">
    <button type="button" @click="showImgStyle = !showImgStyle"
            class="w-full flex items-center justify-between text-sm font-medium py-1" style="color: var(--text-muted);">
        <span><i class="fas fa-crop-simple mr-2 text-cyan-400"></i>Image Styling</span>
        <i :class="showImgStyle ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-xs"></i>
    </button>

    <div x-show="showImgStyle" x-cloak x-transition class="mt-3 space-y-4">

        <div>
            <label class="{{ $labelClass }}">Mask / Crop Shape</label>
            <select name="settings[_image_style][mask_shape]" class="{{ $selectClass }}">
                @foreach($maskShapes as $mVal => $mLabel)
                <option value="{{ $mVal }}" {{ ($ist['mask_shape'] ?? 'none') === $mVal ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">{{ $mLabel }}</option>
                @endforeach
            </select>
            <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Clips the image into a specific shape</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="{{ $labelClass }}">Border Radius (px)</label>
                <input type="number" name="settings[_image_style][border_radius]" value="{{ $ist['border_radius'] ?? '' }}" placeholder="12" min="0" max="999" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Object Fit</label>
                <select name="settings[_image_style][object_fit]" class="{{ $selectClass }}">
                    <option value="cover" {{ ($ist['object_fit'] ?? 'cover') === 'cover' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Cover</option>
                    <option value="contain" {{ ($ist['object_fit'] ?? '') === 'contain' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Contain</option>
                    <option value="fill" {{ ($ist['object_fit'] ?? '') === 'fill' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Fill</option>
                    <option value="none" {{ ($ist['object_fit'] ?? '') === 'none' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">None</option>
                </select>
            </div>
        </div>

        <div class="pt-3" style="border-top: 1px solid var(--border-subtle);">
            <p class="text-xs font-semibold mb-2" style="color: var(--text-muted);"><i class="fas fa-border-all mr-1 text-purple-400"></i>Border</p>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $labelClass }}">Style</label>
                    <select name="settings[_image_style][border_style]" class="{{ $selectClass }}">
                        @foreach($imgBorderStyles as $bsVal => $bsLabel)
                        <option value="{{ $bsVal }}" {{ ($ist['border_style'] ?? 'none') === $bsVal ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">{{ $bsLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Width (px)</label>
                    <input type="number" name="settings[_image_style][border_width]" value="{{ $ist['border_width'] ?? '' }}" placeholder="1" min="0" max="10" class="{{ $inputClass }}">
                </div>
            </div>
            <div class="mt-2">
                <label class="{{ $labelClass }}">Border Color</label>
                <input type="color" name="settings[_image_style][border_color]" value="{{ $ist['border_color'] ?? '#ffffff20' }}" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
            </div>
        </div>

        <div class="pt-3" style="border-top: 1px solid var(--border-subtle);">
            <p class="text-xs font-semibold mb-2" style="color: var(--text-muted);"><i class="fas fa-cloud mr-1 text-blue-400"></i>Shadow</p>
            <div>
                <label class="{{ $labelClass }}">Shadow Type</label>
                <select name="settings[_image_style][shadow_type]" class="{{ $selectClass }}">
                    @foreach($imgShadowTypes as $shVal => $shLabel)
                    <option value="{{ $shVal }}" {{ ($ist['shadow_type'] ?? 'none') === $shVal ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">{{ $shLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-2">
                <div>
                    <label class="{{ $labelClass }}">Shadow Color</label>
                    <input type="color" name="settings[_image_style][shadow_color]" value="{{ $ist['shadow_color'] ?? '#00000040' }}" class="w-full h-9 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Shadow Blur (px)</label>
                    <input type="number" name="settings[_image_style][shadow_blur]" value="{{ $ist['shadow_blur'] ?? 12 }}" min="0" max="80" class="{{ $inputClass }}">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-2">
                <div>
                    <label class="{{ $labelClass }}">X Offset</label>
                    <input type="number" name="settings[_image_style][shadow_x]" value="{{ $ist['shadow_x'] ?? 0 }}" min="-40" max="40" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Y Offset</label>
                    <input type="number" name="settings[_image_style][shadow_y]" value="{{ $ist['shadow_y'] ?? 4 }}" min="-40" max="40" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Spread</label>
                    <input type="number" name="settings[_image_style][shadow_spread]" value="{{ $ist['shadow_spread'] ?? 0 }}" min="-20" max="40" class="{{ $inputClass }}">
                </div>
            </div>
        </div>

    </div>
</div>
