@php $s = $block->settings ?? []; @endphp
@php
$inputClass = 'theme-input w-full';
$selectClass = $inputClass;
$labelClass = 'block text-xs mb-1';
@endphp
<style>.block-settings-form label { color: var(--text-faint); } .block-settings-form .glass { background: var(--bg-glass); border: 1px solid var(--border-glass); }
.block-settings-form .placeholder-banner { display:flex; align-items:flex-start; gap:10px; padding:10px 12px; margin-bottom:14px; border-radius:12px; background: linear-gradient(135deg, rgba(124,58,237,0.18), rgba(236,72,153,0.18)); border: 1px solid rgba(167,139,250,0.35); color: #f3e8ff; font-size: 12.5px; line-height:1.4; }
.block-settings-form .placeholder-banner i { color:#fbbf24; font-size:14px; margin-top:2px; }

/* Italic+dimmed inputs in placeholder mode; ::after pill on file
   uploads. Restored on focus so editing feels normal. */
.block-settings-form.placeholder-mode input[type="text"],
.block-settings-form.placeholder-mode input[type="url"],
.block-settings-form.placeholder-mode input[type="email"],
.block-settings-form.placeholder-mode input[type="tel"],
.block-settings-form.placeholder-mode textarea {
    font-style: italic;
    opacity: 0.78;
    transition: opacity 0.15s ease, font-style 0.15s ease;
}
.block-settings-form.placeholder-mode input[type="text"]:focus,
.block-settings-form.placeholder-mode input[type="url"]:focus,
.block-settings-form.placeholder-mode input[type="email"]:focus,
.block-settings-form.placeholder-mode input[type="tel"]:focus,
.block-settings-form.placeholder-mode textarea:focus {
    font-style: normal;
    opacity: 1;
}
.block-settings-form.placeholder-mode .file-upload-field { position: relative; }
.block-settings-form.placeholder-mode .file-upload-field::after {
    content: "Sample — replace";
    position: absolute;
    top: 0;
    right: 0;
    background: rgba(251,191,36,0.18);
    color: #fbbf24;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 2px 7px;
    border-radius: 999px;
    pointer-events: none;
    border: 1px solid rgba(251,191,36,0.35);
}
</style>
<div class="block-settings-form @if(!empty($s['_placeholder'])) placeholder-mode @endif">

{{-- First-paint placeholder banner; cleared by update() once the
     creator edits any seeded field. --}}
@if(!empty($s['_placeholder']))
<div class="placeholder-banner" role="note">
    <i class="fas fa-lightbulb"></i>
    <div>
        <strong>We dropped in placeholder content</strong> so this block looks great right away.
        Italic fields and media tagged with the amber <em>Sample</em> pill below are placeholders — overwrite any of them and this notice will disappear on your next save.
    </div>
</div>
@endif

@if($block->type === 'link')
<div class="space-y-3" x-data="{ featured: {{ !empty($s['is_featured']) ? 'true' : 'false' }} }">
    <div><label class="{{ $labelClass }}">Link Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://" class="{{ $inputClass }}"></div>
    @include('user.links.partials.icon-picker', ['fieldName' => 'settings[icon]', 'currentValue' => $s['icon'] ?? '', 'labelText' => 'Icon', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[thumbnail]', 'currentValue' => $s['thumbnail'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Thumbnail', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <label class="flex items-center gap-2 text-xs text-white/60">
        <input type="hidden" name="settings[is_featured]" value="0">
        <input type="checkbox" name="settings[is_featured]" value="1" x-model="featured" class="rounded text-violet-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
        <i class="fas fa-thumbtack text-amber-400"></i> Mark as featured (pinned style)
    </label>
    <div x-show="featured" x-cloak class="space-y-2 pl-2 border-l-2 border-amber-400/30">
        <div><label class="{{ $labelClass }}">Featured Description (optional)</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Accent Color</label><input type="color" name="settings[accent_color]" value="{{ $s['accent_color'] ?? '#f59e0b' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
    </div>
</div>

@elseif($block->type === 'link_big')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Link Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://" class="{{ $inputClass }}"></div>
    @include('user.links.partials.icon-picker', ['fieldName' => 'settings[icon]', 'currentValue' => $s['icon'] ?? '', 'labelText' => 'Icon', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[thumbnail]', 'currentValue' => $s['thumbnail'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Thumbnail', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Background Color</label><input type="color" name="settings[bg_color]" value="{{ $s['bg_color'] ?? '#7c3aed' }}" class="w-full h-10 rounded-xl cursor-pointer" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
</div>

@elseif($block->type === 'heading')
@php
    $headingStyle = $s['style'] ?? 'plain';
@endphp
<div class="space-y-3" x-data="{ headingStyle: @js($headingStyle) }">
    <div><label class="{{ $labelClass }}">Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div>
        <label class="{{ $labelClass }}">Style</label>
        <select name="settings[style]" x-model="headingStyle" class="{{ $selectClass }}">
            <option value="plain" style="background: var(--bg-body); color: var(--text-primary);">Plain</option>
            <option value="gradient" style="background: var(--bg-body); color: var(--text-primary);">Gradient</option>
            <option value="animated" style="background: var(--bg-body); color: var(--text-primary);">Animated</option>
        </select>
    </div>
    <div class="grid grid-cols-2 gap-3" x-show="headingStyle === 'gradient'" x-cloak>
        <div><label class="{{ $labelClass }}">From Color</label><input type="color" name="settings[from_color]" value="{{ $s['from_color'] ?? '#7c3aed' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
        <div><label class="{{ $labelClass }}">To Color</label><input type="color" name="settings[to_color]" value="{{ $s['to_color'] ?? '#ec4899' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Size</label><select name="settings[size]" class="{{ $selectClass }}"><option value="h1" {{ ($s['size'] ?? '') === 'h1' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H1</option><option value="h2" {{ ($s['size'] ?? '') === 'h2' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H2</option><option value="h3" {{ ($s['size'] ?? '') === 'h3' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H3</option></select></div>
        <div><label class="{{ $labelClass }}">Align</label><select name="settings[align]" class="{{ $selectClass }}"><option value="left" {{ ($s['align'] ?? '') === 'left' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Left</option><option value="center" {{ ($s['align'] ?? '') === 'center' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Center</option><option value="right" {{ ($s['align'] ?? '') === 'right' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Right</option></select></div>
    </div>
</div>

@elseif($block->type === 'heading_logo')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[logo_url]', 'currentValue' => $s['logo_url'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Logo', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Size</label><select name="settings[size]" class="{{ $selectClass }}"><option value="h1" {{ ($s['size'] ?? '') === 'h1' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H1</option><option value="h2" {{ ($s['size'] ?? '') === 'h2' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H2</option><option value="h3" {{ ($s['size'] ?? '') === 'h3' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">H3</option></select></div>
        <div><label class="{{ $labelClass }}">Align</label><select name="settings[align]" class="{{ $selectClass }}"><option value="center" {{ ($s['align'] ?? '') === 'center' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Center</option><option value="left" {{ ($s['align'] ?? '') === 'left' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Left</option></select></div>
    </div>
</div>

@elseif($block->type === 'paragraph')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><textarea name="settings[text]" rows="3" class="{{ $inputClass }}">{{ $s['text'] ?? '' }}</textarea></div>
    <div><label class="{{ $labelClass }}">Align</label><select name="settings[align]" class="{{ $selectClass }}"><option value="left" {{ ($s['align'] ?? '') === 'left' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Left</option><option value="center" {{ ($s['align'] ?? '') === 'center' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Center</option><option value="right" {{ ($s['align'] ?? '') === 'right' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Right</option></select></div>
</div>

@elseif($block->type === 'paragraph_rich')
<div><label class="{{ $labelClass }}">Rich Text HTML</label><textarea name="settings[html]" rows="5" class="{{ $inputClass }}">{{ $s['html'] ?? '' }}</textarea></div>

@elseif($block->type === 'divider')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Style</label><select name="settings[style]" class="{{ $selectClass }}"><option value="solid" {{ ($s['style'] ?? '') === 'solid' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Solid</option><option value="dashed" {{ ($s['style'] ?? '') === 'dashed' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Dashed</option><option value="dotted" {{ ($s['style'] ?? '') === 'dotted' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Dotted</option></select></div>
    <div><label class="{{ $labelClass }}">Color</label><input type="text" name="settings[color]" value="{{ $s['color'] ?? 'rgba(255,255,255,0.1)' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'spacer')
<div><label class="{{ $labelClass }}">Height (px)</label><input type="number" name="settings[height]" value="{{ $s['height'] ?? 20 }}" min="4" max="200" class="{{ $inputClass }}"></div>

@elseif(in_array($block->type, ['list', 'list_numbered']))
@php
    // Normalize items to always be {text, icon} objects so the editor can
    // safely bind a per-item icon field even on legacy string-only blocks.
    $rawItems = $s['items'] ?? [];
    $normItems = array_map(function ($i) {
        if (is_array($i)) {
            return ['text' => (string)($i['text'] ?? ''), 'icon' => (string)($i['icon'] ?? '')];
        }
        return ['text' => (string)$i, 'icon' => ''];
    }, $rawItems);
    if (empty($normItems)) $normItems = [['text' => '', 'icon' => '']];

    if ($block->type === 'list') {
        $listStyles = [
            'clean'     => ['label' => 'Clean',      'icon' => 'fa-list-ul'],
            'boxed'     => ['label' => 'Boxed',      'icon' => 'fa-square'],
            'divided'   => ['label' => 'Divided',    'icon' => 'fa-grip-lines'],
            'checklist' => ['label' => 'Checklist',  'icon' => 'fa-check-square'],
            'timeline'  => ['label' => 'Timeline',   'icon' => 'fa-stream'],
        ];
        $defaultStyle = 'clean';
    } else {
        $listStyles = [
            'clean'        => ['label' => 'Plain',         'icon' => 'fa-list-ol'],
            'boxed'        => ['label' => 'Boxed',         'icon' => 'fa-square'],
            'divided'      => ['label' => 'Divided',       'icon' => 'fa-grip-lines'],
            'pill'         => ['label' => 'Pill Badge',    'icon' => 'fa-circle'],
            'badge_square' => ['label' => 'Square Badge',  'icon' => 'fa-stop'],
            'outlined'     => ['label' => 'Outlined Big',  'icon' => 'fa-1'],
        ];
        $defaultStyle = 'clean';
    }
    $curListStyle = $s['style'] ?? $defaultStyle;
@endphp
<div x-data='{ items: @json($normItems), style: @json($curListStyle) }' class="space-y-3">
    <div>
        <label class="{{ $labelClass }}">Style</label>
        <div class="grid grid-cols-3 gap-2">
            @foreach($listStyles as $key => $meta)
                <label class="cursor-pointer">
                    <input type="radio" name="settings[style]" value="{{ $key }}" x-model="style" class="sr-only peer">
                    <div class="rounded-lg p-2 text-center text-[11px] flex flex-col items-center gap-1 transition-all peer-checked:ring-2 peer-checked:ring-violet-500 peer-checked:bg-violet-500/15"
                         style="background: var(--bg-glass); border: 1px solid var(--border-glass); color: var(--text-muted);">
                        <i class="fas {{ $meta['icon'] }} text-sm"></i>
                        <span>{{ $meta['label'] }}</span>
                    </div>
                </label>
            @endforeach
        </div>
    </div>

    @if($block->type === 'list')
        <div>@include('user.links.partials.icon-picker', ['fieldName' => 'settings[icon]', 'currentValue' => $s['icon'] ?? 'fa-check', 'labelText' => 'Default Bullet Icon (used when an item has no icon)', 'inputClass' => $inputClass, 'labelClass' => $labelClass])</div>
    @endif

    <div>
        <label class="{{ $labelClass }}">Items</label>
        <template x-for="(item, i) in items" :key="i">
            <div class="mb-2 rounded-lg p-2" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                <div class="flex gap-2">
                    <input type="text" x-model="items[i].text" :name="'settings[items]['+i+'][text]'" placeholder="Item text" class="{{ $inputClass }} flex-1">
                    @if($block->type === 'list')
                    <select x-model="items[i].icon" :name="'settings[items]['+i+'][icon]'" class="{{ $selectClass }}" style="width: 130px;">
                        <option value="" style="background: var(--bg-body); color: var(--text-primary);">Default</option>
                        <option value="fa-check" style="background: var(--bg-body); color: var(--text-primary);">✓ Check</option>
                        <option value="fa-circle" style="background: var(--bg-body); color: var(--text-primary);">• Dot</option>
                        <option value="fa-star" style="background: var(--bg-body); color: var(--text-primary);">★ Star</option>
                        <option value="fa-arrow-right" style="background: var(--bg-body); color: var(--text-primary);">→ Arrow</option>
                        <option value="fa-heart" style="background: var(--bg-body); color: var(--text-primary);">♥ Heart</option>
                        <option value="fa-bolt" style="background: var(--bg-body); color: var(--text-primary);">⚡ Bolt</option>
                        <option value="fa-fire" style="background: var(--bg-body); color: var(--text-primary);">🔥 Fire</option>
                        <option value="fa-gem" style="background: var(--bg-body); color: var(--text-primary);">💎 Gem</option>
                        <option value="fa-times" style="background: var(--bg-body); color: var(--text-primary);">✗ Times</option>
                        <option :value="items[i].icon" x-show="items[i].icon && !['','fa-check','fa-circle','fa-star','fa-arrow-right','fa-heart','fa-bolt','fa-fire','fa-gem','fa-times'].includes(items[i].icon)" style="background: var(--bg-body); color: var(--text-primary);" x-text="items[i].icon"></option>
                    </select>
                    @endif
                    <button type="button" @click="items.splice(i,1)" class="text-red-400/60 hover:text-red-400 px-2"><i class="fas fa-times text-xs"></i></button>
                </div>
                @if($block->type === 'list')
                <div class="mt-1.5 flex items-center gap-2">
                    <span class="text-[10px]" style="color: var(--text-faint);">Or custom Font Awesome class:</span>
                    <input type="text" x-model="items[i].icon" placeholder="fa-rocket" class="{{ $inputClass }} flex-1" style="font-size: 11px; padding: 4px 8px;">
                </div>
                @endif
            </div>
        </template>
        <button type="button" @click="items.push({{ $block->type === 'list' ? '{text:\'\',icon:\'\'}' : '{text:\'\'}' }})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add Item</button>
    </div>
</div>

@elseif($block->type === 'list_pricing')
@php
    $pricingStyles = [
        'classic'    => ['label' => 'Classic List', 'icon' => 'fa-list',         'desc' => 'Name + price with leader dots'],
        'menu'       => ['label' => 'Menu',         'icon' => 'fa-utensils',     'desc' => 'Name, description, price'],
        'cards'      => ['label' => 'Card Grid',    'icon' => 'fa-th-large',     'desc' => 'Stacked pricing cards'],
        'comparison' => ['label' => 'Comparison',   'icon' => 'fa-table',        'desc' => 'Included / not included'],
        'featured'   => ['label' => 'Featured',     'icon' => 'fa-star',         'desc' => 'Highlight one plan'],
    ];
    $rawP = $s['items'] ?? [];
    $pItems = array_map(fn($i) => [
        'name'        => (string)($i['name'] ?? ''),
        'description' => (string)($i['description'] ?? ''),
        'price'       => (string)($i['price'] ?? ''),
        'period'      => (string)($i['period'] ?? ''),
        'included'    => (bool)($i['included'] ?? true),
        'featured'    => (bool)($i['featured'] ?? false),
        'thumbnail'   => (string)($i['thumbnail'] ?? ''),
        'icon'        => (string)($i['icon'] ?? ''),
    ], $rawP);
    if (empty($pItems)) {
        $pItems = [['name' => '', 'description' => '', 'price' => '', 'period' => '', 'included' => true, 'featured' => false, 'thumbnail' => '', 'icon' => '']];
    }
    $curPStyle = $s['style'] ?? 'classic';
@endphp
<div x-data='{ items: @json($pItems), style: @json($curPStyle) }' class="space-y-3">
    <div>
        <label class="{{ $labelClass }}">Style</label>
        <div class="grid grid-cols-2 gap-2">
            @foreach($pricingStyles as $key => $meta)
                <label class="cursor-pointer">
                    <input type="radio" name="settings[style]" value="{{ $key }}" x-model="style" class="sr-only peer">
                    <div class="rounded-lg p-2.5 text-left flex items-start gap-2 transition-all peer-checked:ring-2 peer-checked:ring-violet-500 peer-checked:bg-violet-500/15"
                         style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                        <i class="fas {{ $meta['icon'] }} text-sm mt-0.5" style="color: var(--text-muted);"></i>
                        <div>
                            <div class="text-xs font-semibold" style="color: var(--text-primary);">{{ $meta['label'] }}</div>
                            <div class="text-[10px]" style="color: var(--text-faint);">{{ $meta['desc'] }}</div>
                        </div>
                    </div>
                </label>
            @endforeach
        </div>
    </div>

    <div>
        <label class="{{ $labelClass }}">Items</label>
        <template x-for="(item, i) in items" :key="i">
            <div class="rounded-lg p-3 mb-2" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                <div class="grid grid-cols-2 gap-2 mb-2">
                    <input type="text" x-model="items[i].name" :name="'settings[items]['+i+'][name]'" placeholder="Plan / Item name" class="{{ $inputClass }}">
                    <div class="flex gap-1">
                        <input type="text" x-model="items[i].price" :name="'settings[items]['+i+'][price]'" placeholder="$29" class="{{ $inputClass }} flex-1">
                        <input type="text" x-model="items[i].period" :name="'settings[items]['+i+'][period]'" placeholder="/mo" class="{{ $inputClass }}" style="width: 70px;">
                    </div>
                </div>
                <textarea x-model="items[i].description" :name="'settings[items]['+i+'][description]'" placeholder="Short description (used by Menu, Cards, Featured styles)" rows="2" class="{{ $inputClass }} mb-2"></textarea>
                <div class="grid grid-cols-2 gap-2 mb-2">
                    <input type="text" x-model="items[i].thumbnail" :name="'settings[items]['+i+'][thumbnail]'" placeholder="Thumbnail URL (optional)" class="{{ $inputClass }}" style="font-size: 11px;">
                    <input type="text" x-model="items[i].icon" :name="'settings[items]['+i+'][icon]'" placeholder="Icon e.g. fa-coffee" class="{{ $inputClass }}" style="font-size: 11px;">
                </div>
                <div class="flex items-center gap-4 flex-wrap">
                    <label class="flex items-center gap-1.5 text-xs" style="color: var(--text-muted);">
                        <input type="hidden" :name="'settings[items]['+i+'][included]'" value="0">
                        <input type="checkbox" x-model="items[i].included" :name="'settings[items]['+i+'][included]'" :value="'1'" class="rounded text-violet-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                        <i class="fas fa-check text-green-400 text-[10px]"></i> Included
                    </label>
                    <label class="flex items-center gap-1.5 text-xs" style="color: var(--text-muted);">
                        <input type="hidden" :name="'settings[items]['+i+'][featured]'" value="0">
                        <input type="checkbox" x-model="items[i].featured" :name="'settings[items]['+i+'][featured]'" :value="'1'" class="rounded text-amber-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                        <i class="fas fa-star text-amber-400 text-[10px]"></i> Featured
                    </label>
                    <button type="button" @click="items.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400 ml-auto"><i class="fas fa-times mr-1"></i>Remove</button>
                </div>
            </div>
        </template>
        <button type="button" @click="items.push({name:'',description:'',price:'',period:'',included:true,featured:false,thumbnail:'',icon:''})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add Item</button>
    </div>
</div>

@elseif($block->type === 'alert')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Type</label><select name="settings[type]" class="{{ $selectClass }}"><option value="info" {{ ($s['type'] ?? '') === 'info' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Info</option><option value="success" {{ ($s['type'] ?? '') === 'success' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Success</option><option value="warning" {{ ($s['type'] ?? '') === 'warning' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Warning</option><option value="error" {{ ($s['type'] ?? '') === 'error' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Error</option></select></div>
</div>

@elseif($block->type === 'badge')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Color</label><input type="color" name="settings[color]" value="{{ $s['color'] ?? '#7c3aed' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
        <div><label class="{{ $labelClass }}">Text Color</label><input type="color" name="settings[text_color]" value="{{ $s['text_color'] ?? '#ffffff' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
    </div>
</div>

@elseif($block->type === 'image')
@php $imgStyle = $s['_image_style'] ?? []; $imgLink = $s['_link'] ?? []; @endphp
<div class="space-y-3">
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[url]', 'currentValue' => $s['url'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Image', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Alt Text</label><input type="text" name="settings[alt]" value="{{ $s['alt'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>
@include('user.links.partials.image-style-settings', ['imgStyle' => $imgStyle, 'inputClass' => $inputClass, 'labelClass' => $labelClass, 'selectClass' => $selectClass])
@include('user.links.partials.block-link-settings', ['imgLink' => $imgLink, 'inputClass' => $inputClass, 'labelClass' => $labelClass])

@elseif(in_array($block->type, ['image_grid', 'image_slider', 'image_slider_v2']))
@php
    $imgStyle = $s['_image_style'] ?? [];
    $imgLink = $s['_link'] ?? [];
    $gridImgId = 'gridimg_' . $block->id;
@endphp
<div x-data="imageListUploader_{{ $gridImgId }}()">
    <label class="{{ $labelClass }}">Images</label>
    <template x-for="(img, i) in images" :key="i">
        <div class="flex gap-2 mb-2 items-center">
            <template x-if="isImageUrl(img)">
                <img :src="img" class="w-8 h-8 rounded object-cover flex-shrink-0" alt="">
            </template>
            <input type="url" x-model="images[i]" :name="'settings[images][' + i + ']'" placeholder="https://..." class="{{ $inputClass }} flex-1">
            <button type="button" @click="images.splice(i,1)" class="text-red-400/60 hover:text-red-400 px-1.5 flex-shrink-0"><i class="fas fa-times text-xs"></i></button>
        </div>
    </template>
    <div class="flex items-center gap-2 mt-1 flex-wrap">
        <button type="button" @click="images.push('')" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add URL</button>
        <span class="text-white/10">|</span>
        <button type="button" @click="$refs.gridFileInput.click()" class="text-xs text-emerald-400 hover:text-emerald-300"><i class="fas fa-cloud-upload-alt mr-1"></i>Upload</button>
        <span class="text-white/10">|</span>
        <button type="button" @click="toggleVault()" class="text-xs text-cyan-400 hover:text-cyan-300"><i class="fas fa-folder-open mr-1"></i><span x-text="showVault ? 'Close My Files' : 'From My Files'"></span></button>
    </div>
    <input type="file" x-ref="gridFileInput" accept=".jpg,.jpeg,.png,.gif,.webp,.svg" multiple class="hidden" @change="uploadMultiple($event)">
    <template x-if="uploading">
        <div class="mt-2 rounded-lg p-2" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
            <div class="w-full rounded-full h-1.5 mb-1" style="background: var(--bg-glass-input);">
                <div class="h-1.5 rounded-full bg-gradient-to-r from-violet-500 to-pink-500 transition-all" :style="'width:' + uploadProgress + '%'"></div>
            </div>
            <p class="text-[10px] text-violet-300"><i class="fas fa-spinner fa-spin mr-1"></i>Uploading...</p>
        </div>
    </template>
    <template x-if="showVault">
        <div class="mt-2 rounded-lg overflow-hidden" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
            <div class="p-2 flex items-center gap-2" style="border-bottom: 1px solid var(--border-subtle, rgba(255,255,255,0.06));">
                <input type="text" x-model="vaultSearch" placeholder="Search My Files…" class="flex-1 text-xs px-2.5 py-1.5 rounded-lg outline-none" style="background: var(--bg-glass-input); color: var(--text-primary); border: 1px solid var(--border-glass);">
                <button type="button" @click="loadVault()" class="text-[10px] text-violet-400 hover:text-violet-300 px-2"><i class="fas fa-sync-alt"></i></button>
            </div>
            <div class="max-h-48 overflow-y-auto p-2">
                <template x-if="vaultLoading"><div class="py-6 text-center"><i class="fas fa-spinner fa-spin text-violet-400/60"></i></div></template>
                <template x-if="!vaultLoading && vaultFiles.length === 0"><div class="py-6 text-center text-xs text-white/30">No images in your vault yet</div></template>
                <div class="grid grid-cols-4 gap-1.5">
                    <template x-for="f in filteredVault" :key="f.id">
                        <button type="button" @click="addFromVault(f)" class="rounded-lg overflow-hidden text-left transition-all hover:ring-2 hover:ring-violet-500/50" style="background: var(--bg-glass-input);">
                            <img :src="f.url" class="w-full aspect-square object-cover" :alt="f.original_name">
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </template>
    @if($block->type === 'image_grid')<div class="mt-3"><label class="{{ $labelClass }}">Columns</label><select name="settings[columns]" class="{{ $selectClass }}"><option value="2" {{ ($s['columns'] ?? 3) == 2 ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">2</option><option value="3" {{ ($s['columns'] ?? 3) == 3 ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">3</option><option value="4" {{ ($s['columns'] ?? 3) == 4 ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">4</option></select></div>@endif
</div>
<script>
function imageListUploader_{{ $gridImgId }}() {
    return {
        images: {!! json_encode($s['images'] ?? []) !!},
        uploading: false,
        uploadProgress: 0,
        showVault: false,
        vaultFiles: [],
        vaultLoading: false,
        vaultSearch: '',
        get filteredVault() {
            if (!this.vaultSearch) return this.vaultFiles;
            const s = this.vaultSearch.toLowerCase();
            return this.vaultFiles.filter((f) => (f.original_name || '').toLowerCase().includes(s));
        },
        toggleVault() {
            this.showVault = !this.showVault;
            if (this.showVault && this.vaultFiles.length === 0) this.loadVault();
        },
        async loadVault() {
            this.vaultLoading = true;
            try {
                const r = await fetch('{{ route("user.files.index") }}?type=image&page=1', { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                this.vaultFiles = data.files || [];
            } catch (e) { this.vaultFiles = []; }
            this.vaultLoading = false;
        },
        addFromVault(f) { if (f && f.url) this.images.push(f.url); },
        isImageUrl(u) { return u && (u.startsWith('http') || u.startsWith('/')); },
        async uploadMultiple(e) {
            var files = Array.from(e.target.files);
            if (!files.length) return;
            this.uploading = true;
            this.uploadProgress = 0;
            var done = 0;
            for (var f of files) {
                var fd = new FormData();
                fd.append('file', f);
                try {
                    var r = await fetch('{{ route("user.files.upload") }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                        body: fd
                    });
                    var data = await r.json();
                    if (data.success && data.file) this.images.push(data.file.url);
                } catch(err) {}
                done++;
                this.uploadProgress = Math.round((done / files.length) * 100);
            }
            this.uploading = false;
            e.target.value = '';
        }
    }
}
</script>
@include('user.links.partials.image-style-settings', ['imgStyle' => $imgStyle, 'inputClass' => $inputClass, 'labelClass' => $labelClass, 'selectClass' => $selectClass])
@include('user.links.partials.block-link-settings', ['imgLink' => $imgLink, 'inputClass' => $inputClass, 'labelClass' => $labelClass])

@elseif(in_array($block->type, ['video', 'header_video']))
<div class="space-y-3">
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[url]', 'currentValue' => $s['url'] ?? '', 'acceptTypes' => 'video', 'labelText' => 'Video', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    @if($block->type === 'header_video')
    <div class="flex gap-4">
        <label class="flex items-center gap-2 text-xs text-white/40"><input type="checkbox" name="settings[autoplay]" value="1" {{ ($s['autoplay'] ?? false) ? 'checked' : '' }} class="rounded text-violet-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">Autoplay</label>
        <label class="flex items-center gap-2 text-xs text-white/40"><input type="checkbox" name="settings[muted]" value="1" {{ ($s['muted'] ?? false) ? 'checked' : '' }} class="rounded text-violet-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">Muted</label>
        <label class="flex items-center gap-2 text-xs text-white/40"><input type="checkbox" name="settings[loop]" value="1" {{ ($s['loop'] ?? false) ? 'checked' : '' }} class="rounded text-violet-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">Loop</label>
    </div>
    @endif
</div>

@elseif($block->type === 'audio')
<div class="space-y-3">
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[url]', 'currentValue' => $s['url'] ?? '', 'acceptTypes' => 'audio', 'labelText' => 'Audio File', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif(in_array($block->type, ['pdf_document', 'powerpoint', 'excel']))
<div class="space-y-3">
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[url]', 'currentValue' => $s['url'] ?? '', 'acceptTypes' => 'document', 'labelText' => 'File', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'socials')
@include('user.links.partials.socials-form', ['s' => $s])

@elseif(in_array($block->type, ['socials_multi', 'socials_custom']))
@include('user.links.partials.socials-form', ['s' => $s])
@if($block->type === 'socials_custom')
<div class="mt-3 grid grid-cols-2 gap-3">
    <div><label class="{{ $labelClass }}">Style</label><select name="settings[style]" class="{{ $selectClass }}"><option value="rounded" {{ ($s['style'] ?? '') === 'rounded' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Rounded</option><option value="square" {{ ($s['style'] ?? '') === 'square' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Square</option><option value="circle" {{ ($s['style'] ?? '') === 'circle' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Circle</option></select></div>
    <div><label class="{{ $labelClass }}">Size</label><select name="settings[size]" class="{{ $selectClass }}"><option value="sm" {{ ($s['size'] ?? '') === 'sm' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Small</option><option value="md" {{ ($s['size'] ?? '') === 'md' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Medium</option><option value="lg" {{ ($s['size'] ?? '') === 'lg' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Large</option></select></div>
</div>
@endif

@elseif(in_array($block->type, ['instagram_media', 'tiktok_video', 'twitter_tweet', 'twitter_video', 'facebook_post', 'reddit_post', 'telegram_post', 'rumble_video', 'vk_video', 'soundcloud', 'tidal', 'mixcloud', 'anchor_fm', 'apple_music', 'typeform', 'calendly']))
<div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://..." class="{{ $inputClass }}"></div>

@elseif(in_array($block->type, ['tiktok_profile', 'twitter_profile', 'pinterest_profile', 'snapchat']))
<div><label class="{{ $labelClass }}">Username</label><input type="text" name="settings[username]" value="{{ $s['username'] ?? '' }}" placeholder="@username" class="{{ $inputClass }}"></div>

@elseif($block->type === 'rss_feed')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">RSS Feed URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Number of items</label><input type="number" name="settings[count]" value="{{ $s['count'] ?? 5 }}" min="1" max="20" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'spotify')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Spotify URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Type</label><select name="settings[type]" class="{{ $selectClass }}"><option value="track" {{ ($s['type'] ?? '') === 'track' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Track</option><option value="album" {{ ($s['type'] ?? '') === 'album' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Album</option><option value="playlist" {{ ($s['type'] ?? '') === 'playlist' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Playlist</option></select></div>
</div>

@elseif($block->type === 'youtube')
<div><label class="{{ $labelClass }}">YouTube Video ID or URL</label><input type="text" name="settings[video_id]" value="{{ $s['video_id'] ?? '' }}" class="{{ $inputClass }}"></div>

@elseif($block->type === 'youtube_feed')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Channel ID</label><input type="text" name="settings[channel_id]" value="{{ $s['channel_id'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Videos to show</label><input type="number" name="settings[count]" value="{{ $s['count'] ?? 3 }}" min="1" max="10" class="{{ $inputClass }}"></div>
</div>

@elseif(in_array($block->type, ['vimeo']))
<div><label class="{{ $labelClass }}">Vimeo Video ID</label><input type="text" name="settings[video_id]" value="{{ $s['video_id'] ?? '' }}" class="{{ $inputClass }}"></div>

@elseif(in_array($block->type, ['twitch', 'kick']))
<div><label class="{{ $labelClass }}">Channel Name</label><input type="text" name="settings[channel]" value="{{ $s['channel'] ?? '' }}" class="{{ $inputClass }}"></div>

@elseif($block->type === 'discord_server')
<div><label class="{{ $labelClass }}">Server ID</label><input type="text" name="settings[server_id]" value="{{ $s['server_id'] ?? '' }}" class="{{ $inputClass }}"></div>

@elseif($block->type === 'email_collector')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Placeholder</label><input type="text" name="settings[placeholder]" value="{{ $s['placeholder'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'phone_collector')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Placeholder</label><input type="text" name="settings[placeholder]" value="{{ $s['placeholder'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'contact_form')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif(in_array($block->type, ['whatsapp_widget', 'whatsapp_item']))
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Phone (with country code)</label><input type="text" name="settings[phone]" value="{{ $s['phone'] ?? '' }}" placeholder="+1234567890" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Default Message</label><input type="text" name="settings[message]" value="{{ $s['message'] ?? '' }}" class="{{ $inputClass }}"></div>
    @if($block->type === 'whatsapp_widget')<div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>@endif
    @if($block->type === 'whatsapp_item')<div><label class="{{ $labelClass }}">Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>@endif
</div>

@elseif($block->type === 'verified_heading')
<div class="space-y-3">
    <div class="p-3 rounded-xl text-xs" style="background: rgba(29,155,240,0.1); color: #1d9bf0; border: 1px solid rgba(29,155,240,0.2);">
        <i class="fas fa-check-circle mr-1"></i> Verified block — text is locked and cannot be changed.
    </div>
    <div><label class="{{ $labelClass }}">Verified Name</label><input type="text" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }} opacity-50" disabled></div>
    <div><label class="{{ $labelClass }}">Font Size (px)</label><input type="number" name="settings[font_size]" value="{{ $s['font_size'] ?? '24' }}" min="12" max="72" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Alignment</label>
        <select name="settings[alignment]" class="{{ $inputClass }}">
            <option value="left" {{ ($s['alignment'] ?? 'center') === 'left' ? 'selected' : '' }}>Left</option>
            <option value="center" {{ ($s['alignment'] ?? 'center') === 'center' ? 'selected' : '' }}>Center</option>
            <option value="right" {{ ($s['alignment'] ?? 'center') === 'right' ? 'selected' : '' }}>Right</option>
        </select>
    </div>
    <input type="hidden" name="settings[text]" value="{{ $s['text'] ?? '' }}">
    <input type="hidden" name="settings[verified]" value="1">
    <input type="hidden" name="settings[locked_text]" value="1">
</div>

@elseif($block->type === 'verified_avatar')
<div class="space-y-3">
    <div class="p-3 rounded-xl text-xs" style="background: rgba(29,155,240,0.1); color: #1d9bf0; border: 1px solid rgba(29,155,240,0.2);">
        <i class="fas fa-check-circle mr-1"></i> Verified block — image is locked and cannot be changed.
    </div>
    @if(!empty($s['image_url']))
    <div class="flex justify-center"><img src="{{ $s['image_url'] }}" class="w-20 h-20 rounded-full object-cover" style="border: 2px solid rgba(29,155,240,0.3);"></div>
    @endif
    <div><label class="{{ $labelClass }}">Size (px)</label><input type="number" name="settings[size]" value="{{ $s['size'] ?? '100' }}" min="48" max="200" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Shape</label>
        <select name="settings[shape]" class="{{ $inputClass }}">
            <option value="circle" {{ ($s['shape'] ?? 'circle') === 'circle' ? 'selected' : '' }}>Circle</option>
            <option value="rounded" {{ ($s['shape'] ?? 'circle') === 'rounded' ? 'selected' : '' }}>Rounded Square</option>
        </select>
    </div>
    <input type="hidden" name="settings[image_url]" value="{{ $s['image_url'] ?? '' }}">
    <input type="hidden" name="settings[verified]" value="1">
    <input type="hidden" name="settings[locked_image]" value="1">
</div>

@elseif($block->type === 'email_subscribe')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Placeholder</label><input type="text" name="settings[placeholder]" value="{{ $s['placeholder'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Success Message</label><input type="text" name="settings[success_message]" value="{{ $s['success_message'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="flex items-center gap-2">
        <input type="hidden" name="settings[name_field]" value="0">
        <input type="checkbox" name="settings[name_field]" value="1" {{ ($s['name_field'] ?? false) ? 'checked' : '' }} class="rounded">
        <label class="{{ $labelClass }}">Show Name Field</label>
    </div>
</div>

@elseif($block->type === 'whatsapp_channel_subscribe')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Channel URL</label><input type="url" name="settings[channel_url]" value="{{ $s['channel_url'] ?? '' }}" placeholder="https://whatsapp.com/channel/..." class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'whatsapp_number_subscribe')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Your WhatsApp Number</label><input type="text" name="settings[phone]" value="{{ $s['phone'] ?? '' }}" placeholder="+1234567890" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Default Subscription Message</label><input type="text" name="settings[default_message]" value="{{ $s['default_message'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="flex items-center gap-2">
        <input type="hidden" name="settings[collect_phone]" value="0">
        <input type="checkbox" name="settings[collect_phone]" value="1" {{ ($s['collect_phone'] ?? false) ? 'checked' : '' }} class="rounded">
        <label class="{{ $labelClass }}">Collect visitor's phone number</label>
    </div>
</div>

@elseif(in_array($block->type, ['faq', 'faq_v2']))
<div x-data="{ items: {{ json_encode($s['items'] ?? [['question' => '', 'answer' => '']]) }} }">
    <label class="{{ $labelClass }}">FAQ Items</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2">
            <input type="text" x-model="items[i].question" :name="'settings[items]['+i+'][question]'" placeholder="Question" class="{{ $inputClass }} mb-2">
            <textarea x-model="items[i].answer" :name="'settings[items]['+i+'][answer]'" placeholder="Answer" rows="2" class="{{ $inputClass }}"></textarea>
            <button type="button" @click="items.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400 mt-1"><i class="fas fa-times mr-1"></i>Remove</button>
        </div>
    </template>
    <button type="button" @click="items.push({question:'',answer:''})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add Item</button>
</div>

@elseif($block->type === 'poll')
<div x-data="{ options: {{ json_encode($s['options'] ?? ['Option A', 'Option B']) }} }">
    <div class="mb-3"><label class="{{ $labelClass }}">Question</label><input type="text" name="settings[question]" value="{{ $s['question'] ?? '' }}" class="{{ $inputClass }}"></div>
    <label class="{{ $labelClass }}">Options</label>
    <template x-for="(opt, i) in options" :key="i">
        <div class="flex gap-2 mb-2"><input type="text" x-model="options[i]" :name="'settings[options]['+i+']'" class="{{ $inputClass }}"><button type="button" @click="options.splice(i,1)" class="text-red-400/60 hover:text-red-400 px-2"><i class="fas fa-times text-xs"></i></button></div>
    </template>
    <button type="button" @click="options.push('')" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add Option</button>
    {{-- Server hides /poll-results tallies until this viewer has voted. --}}
    <label class="flex items-center gap-2 mt-3 text-xs text-white/70 cursor-pointer">
        <input type="hidden" name="settings[hide_results_until_voted]" value="0">
        <input type="checkbox" name="settings[hide_results_until_voted]" value="1" @checked(!empty($s['hide_results_until_voted'])) class="rounded border-white/20 bg-white/5">
        <span>Hide results until the viewer has voted</span>
    </label>
    {{-- Stricter mode: hide tallies from EVERYONE (including voters) until
         the configured timestamp passes. Takes precedence over the
         per-viewer toggle above. Empty = disabled. --}}
    <div class="mt-3">
        <label class="{{ $labelClass }}">Reveal results at (optional)</label>
        @php
            $revealVal = '';
            if (!empty($s['reveal_results_at'])) {
                try { $revealVal = \Carbon\Carbon::parse($s['reveal_results_at'])->format('Y-m-d\TH:i'); }
                catch (\Throwable $e) { $revealVal = ''; }
            }
        @endphp
        <input type="datetime-local" name="settings[reveal_results_at]" value="{{ $revealVal }}" class="{{ $inputClass }}">
        <p class="text-[11px] text-white/40 mt-1">Until this date/time, no one — including you — sees the tallies, even after they vote.</p>
    </div>
    @if($block->exists)
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <a href="{{ route('user.links.poll-votes.index', [$block->link_id, $block->id]) }}"
               class="inline-flex items-center gap-1.5 text-xs text-violet-300 hover:text-violet-200">
                <i class="fas fa-list-ol"></i> View &amp; export votes
            </a>
            {{-- Reset votes: clears all PollVote rows for this block while
                 keeping the block id, settings, and analytics history intact. --}}
            <button type="button"
                    class="inline-flex items-center gap-1.5 text-xs text-red-300 hover:text-red-200"
                    data-reset-poll-url="{{ route('user.links.poll-votes.reset', [$block->link_id, $block->id]) }}"
                    data-reset-poll-count="{{ $block->pollVotes()->count() }}"
                    onclick="resetPollVotes(event, this)">
                <i class="fas fa-rotate-left"></i> Reset votes
            </button>
        </div>
    @endif
</div>
<script>
if (typeof window.resetPollVotesToast !== 'function') {
    window.resetPollVotesToast = function (msg, type) {
        if (typeof window.showToast === 'function') { window.showToast(msg, type); return; }
        var colors = { success: 'linear-gradient(135deg, #10b981, #059669)', error: 'linear-gradient(135deg, #ef4444, #dc2626)', info: 'linear-gradient(135deg, #8b5cf6, #7c3aed)' };
        var icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
        var t = document.createElement('div');
        t.className = 'fixed bottom-4 right-4 z-[10001] px-4 py-2.5 rounded-xl text-xs font-medium text-white shadow-lg transition-all';
        t.style.cssText = 'background:' + (colors[type] || colors.info) + ';';
        t.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + ' mr-1.5"></i>' + msg;
        document.body.appendChild(t);
        setTimeout(function () { t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 300); }, 2500);
    };
}
if (typeof window.resetPollVotesConfirm !== 'function') {
    window.resetPollVotesConfirm = function (count, onConfirm) {
        var countText = count === 1 ? '1 vote' : (count + ' votes');
        var escapeHtml = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); };
        var run = function () {
            if (typeof window.themedConfirm === 'function') {
                window.themedConfirm({
                    title: 'Reset poll votes?',
                    messageHtml: 'This will permanently delete <strong>' + escapeHtml(countText) + '</strong> for this poll. The block and its settings stay, but the tally cannot be recovered.',
                    confirmText: 'Reset votes',
                    confirmIcon: 'fa-rotate-left',
                    iconClass: 'fa-rotate-left',
                    onConfirm: onConfirm
                });
            } else {
                // themed-confirm helper is loaded via the user/admin layouts; if it
                // is somehow missing, fail safe by skipping the destructive action
                // rather than falling back to a native browser confirm.
                if (window.console && console.warn) console.warn('themedConfirm unavailable; skipping poll reset.');
            }
        };
        run();
    };
}
if (typeof window.resetPollVotes !== 'function') {
    window.resetPollVotes = function (e, btn) {
        e.preventDefault();
        var count = parseInt(btn.getAttribute('data-reset-poll-count') || '0', 10) || 0;
        window.resetPollVotesConfirm(count, function () {
            var url = btn.getAttribute('data-reset-poll-url');
            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
            btn.disabled = true;
            var original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting…';
            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                btn.disabled = false;
                btn.innerHTML = original;
                if (res.ok && res.body && res.body.success) {
                    btn.setAttribute('data-reset-poll-count', '0');
                    window.resetPollVotesToast('Cleared ' + (res.body.deleted || 0) + ' poll vote(s).', 'success');
                } else {
                    window.resetPollVotesToast('Could not reset poll votes. Please try again.', 'error');
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = original;
                window.resetPollVotesToast('Could not reset poll votes. Please try again.', 'error');
            });
        });
    };
}
</script>

@elseif($block->type === 'testimonials')
<div x-data="{ items: {{ json_encode($s['items'] ?? [['name'=>'','text'=>'','rating'=>5]]) }} }">
    <label class="{{ $labelClass }}">Testimonials</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2 space-y-2">
            <input type="text" x-model="items[i].name" :name="'settings[items]['+i+'][name]'" placeholder="Name" class="{{ $inputClass }}">
            <textarea x-model="items[i].text" :name="'settings[items]['+i+'][text]'" placeholder="Testimonial" rows="2" class="{{ $inputClass }}"></textarea>
            <input type="number" x-model="items[i].rating" :name="'settings[items]['+i+'][rating]'" min="1" max="5" placeholder="Rating 1-5" class="{{ $inputClass }}">
            <button type="button" @click="items.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400"><i class="fas fa-times mr-1"></i>Remove</button>
        </div>
    </template>
    <button type="button" @click="items.push({name:'',text:'',rating:5})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add</button>
</div>

@elseif($block->type === 'review')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Review Text</label><textarea name="settings[text]" rows="2" class="{{ $inputClass }}">{{ $s['text'] ?? '' }}</textarea></div>
    <div><label class="{{ $labelClass }}">Rating (1-5)</label><input type="number" name="settings[rating]" value="{{ $s['rating'] ?? 5 }}" min="1" max="5" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'reviews_wall')
<div class="space-y-3">
    <p class="text-xs text-white/40">A live wall of reviews collected on this page. Visitors can submit ratings, written reviews, photos, audio and video — moderate them from the standalone Reviews page editor.</p>
    <div><label class="{{ $labelClass }}">Heading</label><input type="text" name="settings[heading]" value="{{ $s['heading'] ?? 'What people are saying' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-2">
        <div><label class="{{ $labelClass }}">Source</label><select name="settings[source]" class="{{ $selectClass }}">@foreach(['both'=>'Native + Imported','native'=>'Native only','external'=>'Imported only'] as $v=>$l)<option value="{{ $v }}" @selected(($s['source'] ?? 'both')===$v) style="background: var(--bg-body); color: var(--text-primary);">{{ $l }}</option>@endforeach</select></div>
        <div><label class="{{ $labelClass }}">Sort</label><select name="settings[sort]" class="{{ $selectClass }}">@foreach(['recent'=>'Most recent','rating'=>'Highest rated'] as $v=>$l)<option value="{{ $v }}" @selected(($s['sort'] ?? 'recent')===$v) style="background: var(--bg-body); color: var(--text-primary);">{{ $l }}</option>@endforeach</select></div>
        <div><label class="{{ $labelClass }}">Layout</label><select name="settings[layout]" class="{{ $selectClass }}">@foreach(['grid'=>'Grid','list'=>'List'] as $v=>$l)<option value="{{ $v }}" @selected(($s['layout'] ?? 'grid')===$v) style="background: var(--bg-body); color: var(--text-primary);">{{ $l }}</option>@endforeach</select></div>
        <div><label class="{{ $labelClass }}">Max shown</label><input type="number" name="settings[limit]" value="{{ $s['limit'] ?? 6 }}" min="1" max="50" class="{{ $inputClass }}"></div>
    </div>
    @php $rwSelProviders = is_array($s['providers'] ?? null) ? $s['providers'] : []; @endphp
    <div>
        <label class="{{ $labelClass }}">Imported providers to show</label>
        <p class="text-[11px] text-white/40 mb-1.5">Leave all unchecked to show every connected provider.</p>
        <div class="flex flex-wrap gap-2">
            @foreach(\App\Services\ReviewProviders\ReviewProviderRegistry::all() as $rwSlug => $rwP)
            <label class="flex items-center gap-1.5 text-sm text-white/70 bg-white/5 rounded-lg px-2.5 py-1.5 cursor-pointer">
                <input type="checkbox" name="settings[providers][]" value="{{ $rwSlug }}" @checked(in_array($rwSlug, $rwSelProviders, true)) class="rounded">
                <i class="{{ $rwP['icon'] }}" style="color: {{ $rwP['tint'] }}"></i>{{ $rwP['name'] }}
            </label>
            @endforeach
        </div>
    </div>
    <label class="flex items-center gap-2 text-sm text-white/70"><input type="hidden" name="settings[show_summary]" value="0"><input type="checkbox" name="settings[show_summary]" value="1" @checked($s['show_summary'] ?? true) class="rounded">Show rating summary</label>
    <label class="flex items-center gap-2 text-sm text-white/70"><input type="hidden" name="settings[allow_submissions]" value="0"><input type="checkbox" name="settings[allow_submissions]" value="1" @checked($s['allow_submissions'] ?? true) class="rounded">Allow visitors to submit reviews</label>
    <label class="flex items-center gap-2 text-sm text-white/70"><input type="hidden" name="settings[collect_media]" value="0"><input type="checkbox" name="settings[collect_media]" value="1" @checked($s['collect_media'] ?? true) class="rounded">Allow photo / audio / video</label>
    <label class="flex items-center gap-2 text-sm text-white/70"><input type="hidden" name="settings[collect_email]" value="0"><input type="checkbox" name="settings[collect_email]" value="1" @checked($s['collect_email'] ?? true) class="rounded">Collect reviewer email (private)</label>
</div>

@elseif(in_array($block->type, ['timeline', 'timeline_staged']))
<div x-data="{ items: {{ json_encode($s['items'] ?? [['title'=>'','description'=>'']]) }} }">
    <label class="{{ $labelClass }}">Timeline Items</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2 space-y-2">
            <input type="text" x-model="items[i].title" :name="'settings[items]['+i+'][title]'" placeholder="Title" class="{{ $inputClass }}">
            <input type="text" x-model="items[i].description" :name="'settings[items]['+i+'][description]'" placeholder="Description" class="{{ $inputClass }}">
            @if($block->type === 'timeline')<input type="text" x-model="items[i].date" :name="'settings[items]['+i+'][date]'" placeholder="Date" class="{{ $inputClass }}">@endif
            @if($block->type === 'timeline_staged')<select x-model="items[i].status" :name="'settings[items]['+i+'][status]'" class="{{ $selectClass }}"><option value="completed" style="background: var(--bg-body); color: var(--text-primary);">Completed</option><option value="active" style="background: var(--bg-body); color: var(--text-primary);">Active</option><option value="upcoming" style="background: var(--bg-body); color: var(--text-primary);">Upcoming</option></select>@endif
            <button type="button" @click="items.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400"><i class="fas fa-times mr-1"></i>Remove</button>
        </div>
    </template>
    @php $extra = $block->type === 'timeline' ? "date:''" : "status:'upcoming'"; @endphp
    <button type="button" @click="items.push({title:'',description:'',{!! $extra !!}})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add Item</button>
</div>

@elseif($block->type === 'product')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Product Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><textarea name="settings[description]" rows="2" class="{{ $inputClass }}">{{ $s['description'] ?? '' }}</textarea></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Price</label><input type="text" name="settings[price]" value="{{ $s['price'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Badge</label><input type="text" name="settings[badge]" value="{{ $s['badge'] ?? '' }}" placeholder="Sale, New" class="{{ $inputClass }}"></div>
    </div>
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[image]', 'currentValue' => $s['image'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Product Image', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Buy URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'service')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Service Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><textarea name="settings[description]" rows="2" class="{{ $inputClass }}">{{ $s['description'] ?? '' }}</textarea></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Price</label><input type="text" name="settings[price]" value="{{ $s['price'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div>@include('user.links.partials.icon-picker', ['fieldName' => 'settings[icon]', 'currentValue' => $s['icon'] ?? '', 'labelText' => 'Icon', 'inputClass' => $inputClass, 'labelClass' => $labelClass])</div>
    </div>
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'donation')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><textarea name="settings[description]" rows="2" class="{{ $inputClass }}">{{ $s['description'] ?? '' }}</textarea></div>
    <div><label class="{{ $labelClass }}">Donation URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'coupon')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Coupon Code</label><input type="text" name="settings[code]" value="{{ $s['code'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Expires</label><input type="datetime-local" name="settings[expires]" value="{{ $s['expires'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'price')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Plan Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Amount</label><input type="text" name="settings[amount]" value="{{ $s['amount'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Period</label><input type="text" name="settings[period]" value="{{ $s['period'] ?? '' }}" placeholder="/month" class="{{ $inputClass }}"></div>
    </div>
    <div><label class="{{ $labelClass }}">Sign Up URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'paypal')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">PayPal Email</label><input type="email" name="settings[email]" value="{{ $s['email'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Amount</label><input type="text" name="settings[amount]" value="{{ $s['amount'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Currency</label><input type="text" name="settings[currency]" value="{{ $s['currency'] ?? 'USD' }}" class="{{ $inputClass }}"></div>
    </div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? 'Pay Now' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'countdown')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Target Date</label><input type="datetime-local" name="settings[target_date]" value="{{ $s['target_date'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'progress')
<div x-data="{ items: {{ json_encode($s['items'] ?? [['label'=>'Progress','value'=>75,'color'=>'#7c3aed']]) }} }">
    <label class="{{ $labelClass }}">Progress Bars</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2 grid grid-cols-3 gap-2">
            <input type="text" x-model="items[i].label" :name="'settings[items]['+i+'][label]'" placeholder="Label" class="{{ $inputClass }}">
            <input type="number" x-model="items[i].value" :name="'settings[items]['+i+'][value]'" min="0" max="100" placeholder="%" class="{{ $inputClass }}">
            <input type="color" x-model="items[i].color" :name="'settings[items]['+i+'][color]'" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
        </div>
    </template>
    <button type="button" @click="items.push({label:'',value:50,color:'#7c3aed'})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add</button>
</div>

@elseif($block->type === 'cta_button')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Button Color</label><input type="color" name="settings[color]" value="{{ $s['color'] ?? '#7c3aed' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
        <div><label class="{{ $labelClass }}">Text Color</label><input type="color" name="settings[text_color]" value="{{ $s['text_color'] ?? '#ffffff' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
    </div>
    <div><label class="{{ $labelClass }}">Size</label><select name="settings[size]" class="{{ $selectClass }}"><option value="sm" {{ ($s['size'] ?? '') === 'sm' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Small</option><option value="md" {{ ($s['size'] ?? '') === 'md' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Medium</option><option value="lg" {{ ($s['size'] ?? '') === 'lg' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Large</option></select></div>
</div>

@elseif($block->type === 'notification')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Type</label><select name="settings[type]" class="{{ $selectClass }}"><option value="info" {{ ($s['type'] ?? '') === 'info' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Info</option><option value="success" {{ ($s['type'] ?? '') === 'success' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Success</option><option value="warning" {{ ($s['type'] ?? '') === 'warning' ? 'selected' : '' }} style="background: var(--bg-body); color: var(--text-primary);">Warning</option></select></div>
</div>

@elseif($block->type === 'ticker')
<div x-data="{ items: {{ json_encode($s['items'] ?? ['Text 1']) }} }">
    <label class="{{ $labelClass }}">Ticker Items</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="flex gap-2 mb-2"><input type="text" x-model="items[i]" :name="'settings[items]['+i+']'" class="{{ $inputClass }}"><button type="button" @click="items.splice(i,1)" class="text-red-400/60 px-2"><i class="fas fa-times text-xs"></i></button></div>
    </template>
    <button type="button" @click="items.push('')" class="text-xs text-violet-400"><i class="fas fa-plus mr-1"></i>Add</button>
</div>

@elseif($block->type === 'iframe_embed')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Embed URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Height (px)</label><input type="number" name="settings[height]" value="{{ $s['height'] ?? 400 }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'form')
@php $userForms = auth()->user()->forms()->orderBy('title')->get(['id','title','is_active']); @endphp
<div class="space-y-3">
    <div>
        <label class="{{ $labelClass }}">Form</label>
        @if($userForms->isEmpty())
            <div class="text-xs px-3 py-2 rounded-lg" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: #fbbf24;">
                You haven't built any forms yet. <a href="{{ route('user.forms.create') }}" class="underline font-semibold">Create one →</a>
            </div>
        @else
            <select name="settings[form_id]" class="{{ $inputClass }}">
                <option value="">— Choose a form —</option>
                @foreach($userForms as $f)
                    <option value="{{ $f->id }}" @selected(($s['form_id'] ?? null) == $f->id)>{{ $f->title }} {{ $f->is_active ? '' : '(disabled)' }}</option>
                @endforeach
            </select>
            <p class="text-[10px] mt-1" style="color: var(--text-faint);">The form auto-resizes — height below is the initial frame height.</p>
        @endif
    </div>
    <div><label class="{{ $labelClass }}">Initial height (px)</label><input type="number" name="settings[height]" value="{{ $s['height'] ?? 600 }}" min="200" max="2000" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'custom_html')
<div><label class="{{ $labelClass }}">HTML Code</label><textarea name="settings[html]" rows="6" class="{{ $inputClass }} font-mono">{{ $s['html'] ?? '' }}</textarea></div>

@elseif($block->type === 'file')
<div class="space-y-3"
     x-data="{ fileName: {{ json_encode($s['name'] ?? '') }}, fileSize: {{ json_encode($s['size'] ?? '') }} }"
     @file-meta="if ($event.detail.name) fileName = $event.detail.name; if ($event.detail.size_human) fileSize = $event.detail.size_human;">
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[url]', 'currentValue' => $s['url'] ?? '', 'acceptTypes' => 'all', 'labelText' => 'File', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">File Name</label><input type="text" name="settings[name]" x-model="fileName" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">File Size</label><input type="text" name="settings[size]" x-model="fileSize" placeholder="e.g. 2.5 MB" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'external_item')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[image]', 'currentValue' => $s['image'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Image', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
</div>

@elseif($block->type === 'markdown')
<div><label class="{{ $labelClass }}">Markdown Content</label><textarea name="settings[content]" rows="6" class="{{ $inputClass }} font-mono">{{ $s['content'] ?? '' }}</textarea></div>

@elseif(in_array($block->type, ['map', 'yandex_maps']))
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Address</label><input type="text" name="settings[address]" value="{{ $s['address'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Zoom</label><input type="number" name="settings[zoom]" value="{{ $s['zoom'] ?? 14 }}" min="1" max="20" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'vcard')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Full Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Email</label><input type="email" name="settings[email]" value="{{ $s['email'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Phone</label><input type="text" name="settings[phone]" value="{{ $s['phone'] ?? '' }}" class="{{ $inputClass }}"></div>
    </div>
    <div><label class="{{ $labelClass }}">Company</label><input type="text" name="settings[company]" value="{{ $s['company'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Website</label><input type="url" name="settings[website]" value="{{ $s['website'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'avatar')
<div class="space-y-3">
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[url]', 'currentValue' => $s['url'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Avatar Image', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Size (px)</label><input type="number" name="settings[size]" value="{{ $s['size'] ?? 96 }}" min="32" max="256" class="{{ $inputClass }}"></div>
        <div class="flex items-end pb-1"><label class="flex items-center gap-2 text-xs text-white/40"><input type="hidden" name="settings[rounded]" value="0"><input type="checkbox" name="settings[rounded]" value="1" {{ ($s['rounded'] ?? true) ? 'checked' : '' }} class="rounded text-violet-500" style="background: var(--bg-glass-input); border-color: var(--border-glass);">Rounded</label></div>
    </div>
</div>

@elseif(in_array($block->type, ['profile_card_v1', 'profile_card_v2', 'profile_card_v3', 'profile_card_v4']))
@php
    // Normalise existing socials into {name,url} for the Alpine repeater
    // (accepts the legacy {platform} shape too).
    $pcSocials = [];
    foreach ((is_array($s['socials'] ?? null) ? $s['socials'] : []) as $soc) {
        if (!is_array($soc)) continue;
        $pcSocials[] = ['name' => $soc['name'] ?? $soc['platform'] ?? '', 'url' => $soc['url'] ?? ''];
    }
    $pcSocialOptions = ['instagram','twitter','facebook','tiktok','youtube','linkedin','github','discord','telegram','whatsapp','snapchat','pinterest','twitch','dribbble','spotify','soundcloud','apple','reddit','medium','behance','website','email'];
@endphp
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Name</label><input type="text" name="settings[name]" value="{{ $s['name'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[avatar]', 'currentValue' => $s['avatar'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Avatar', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[cover]', 'currentValue' => $s['cover'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Cover Image', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Bio</label><textarea name="settings[bio]" rows="2" class="{{ $inputClass }}">{{ $s['bio'] ?? '' }}</textarea></div>

    {{-- Fields below are only shown by some identity designs (verified
         designs, the Social Profile layout, the Founder CTA pill). They're
         saved regardless and simply not rendered by layouts that ignore
         them, so you can switch designs without re-entering them. --}}
    <label class="flex items-center gap-2 cursor-pointer select-none">
        <input type="hidden" name="settings[verified]" value="0">
        <input type="checkbox" name="settings[verified]" value="1" @checked(!empty($s['verified'])) class="rounded border-white/20 bg-white/5 text-violet-500 focus:ring-violet-500/40">
        <span class="text-sm text-white/80">Show verified badge</span>
    </label>

    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Location</label><input type="text" name="settings[location]" value="{{ $s['location'] ?? '' }}" placeholder="City, Country" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Website</label><input type="url" name="settings[website]" value="{{ $s['website'] ?? '' }}" placeholder="https://…" class="{{ $inputClass }}"></div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">CTA Label</label><input type="text" name="settings[cta_label]" value="{{ $s['cta_label'] ?? '' }}" placeholder="e.g. Get in touch" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">CTA Link</label><input type="url" name="settings[cta_url]" value="{{ $s['cta_url'] ?? '' }}" placeholder="https://…" class="{{ $inputClass }}"></div>
    </div>

    {{-- Social links — shown by the social / glass / gradient / minimal
         designs as an icon row. --}}
    <div x-data="{ socials: {{ json_encode(array_values($pcSocials)) }} }">
        <label class="{{ $labelClass }}">Social Links</label>
        <template x-for="(soc, i) in socials" :key="i">
            <div class="flex items-center gap-2 mb-2">
                <select x-model="socials[i].name" :name="'settings[socials]['+i+'][name]'" class="{{ $inputClass }}">
                    <option value="" class="bg-[#0d0818]">Select…</option>
                    @foreach($pcSocialOptions as $opt)
                        <option value="{{ $opt }}" class="bg-[#0d0818]">{{ ucfirst($opt) }}</option>
                    @endforeach
                </select>
                <input type="url" x-model="socials[i].url" :name="'settings[socials]['+i+'][url]'" placeholder="https://…" class="{{ $inputClass }}">
                <button type="button" @click="socials.splice(i,1)" class="text-red-400/60 hover:text-red-400 shrink-0"><i class="fas fa-times"></i></button>
            </div>
        </template>
        <button type="button" @click="socials.push({name:'',url:''})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add social link</button>
    </div>
</div>

@elseif($block->type === 'qr_code')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">URL to encode</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Size (px)</label><input type="number" name="settings[size]" value="{{ $s['size'] ?? 200 }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'share')
<div><label class="{{ $labelClass }}">Share Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="{{ $inputClass }}"></div>

@elseif($block->type === 'one_time_offer')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><textarea name="settings[description]" rows="2" class="{{ $inputClass }}">{{ $s['description'] ?? '' }}</textarea></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Price</label><input type="text" name="settings[price]" value="{{ $s['price'] ?? '' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Original Price</label><input type="text" name="settings[original_price]" value="{{ $s['original_price'] ?? '' }}" class="{{ $inputClass }}"></div>
    </div>
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'card')
<div class="space-y-4" x-data="{ bgType: '{{ $s['bg_type'] ?? 'glass' }}' }">
    <div><label class="{{ $labelClass }}">Card Title (optional)</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}" placeholder="Optional section title"></div>

    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Columns</label>
            <select name="settings[columns]" class="{{ $selectClass }}">
                @foreach([1=>'1 Column',2=>'2 Columns',3=>'3 Columns',4=>'4 Columns',6=>'6 Columns',8=>'8 Columns',9=>'9 Columns',12=>'12 Columns'] as $v=>$l)
                <option value="{{ $v }}" {{ ($s['columns'] ?? 2) == $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="{{ $labelClass }}">Gap (px)</label><input type="number" name="settings[gap]" value="{{ $s['gap'] ?? 12 }}" min="0" max="48" class="{{ $inputClass }}"></div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Padding (px)</label><input type="number" name="settings[padding]" value="{{ $s['padding'] ?? 16 }}" min="0" max="64" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Border Radius (px)</label><input type="number" name="settings[border_radius]" value="{{ $s['border_radius'] ?? 16 }}" min="0" max="48" class="{{ $inputClass }}"></div>
    </div>

    <div><label class="{{ $labelClass }}">Background Type</label>
        <select name="settings[bg_type]" x-model="bgType" class="{{ $selectClass }}">
            <option value="glass">Glassmorphism</option>
            <option value="color">Solid Color</option>
            <option value="gradient">Gradient</option>
            <option value="image">Background Image</option>
            <option value="transparent">Transparent</option>
        </select>
    </div>

    <div x-show="bgType === 'color'" x-cloak>
        <label class="{{ $labelClass }}">Background Color</label>
        <input type="text" name="settings[bg_color]" value="{{ $s['bg_color'] ?? 'rgba(255,255,255,0.06)' }}" class="{{ $inputClass }}" placeholder="e.g. #1a1a2e or rgba(...)">
    </div>

    <div x-show="bgType === 'gradient'" x-cloak>
        <label class="{{ $labelClass }}">CSS Gradient</label>
        <input type="text" name="settings[bg_gradient]" value="{{ $s['bg_gradient'] ?? '' }}" class="{{ $inputClass }}" placeholder="linear-gradient(135deg, #7c3aed, #ec4899)">
    </div>

    <div x-show="bgType === 'image'" x-cloak>
        <label class="{{ $labelClass }}">Image URL</label>
        <input type="url" name="settings[bg_image]" value="{{ $s['bg_image'] ?? '' }}" class="{{ $inputClass }}" placeholder="https://...">
    </div>

    <div x-show="bgType === 'glass'" x-cloak class="space-y-3">
        <div><label class="{{ $labelClass }}">Glass Blur (px)</label><input type="range" name="settings[glass_blur]" value="{{ $s['glass_blur'] ?? 12 }}" min="0" max="40" class="w-full accent-purple-500"></div>
        <div><label class="{{ $labelClass }}">Glass Opacity (%)</label><input type="range" name="settings[glass_opacity]" value="{{ $s['glass_opacity'] ?? 6 }}" min="0" max="30" class="w-full accent-purple-500"></div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Border Color</label><input type="text" name="settings[border_color]" value="{{ $s['border_color'] ?? 'rgba(255,255,255,0.08)' }}" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Border Width (px)</label><input type="number" name="settings[border_width]" value="{{ $s['border_width'] ?? 1 }}" min="0" max="8" class="{{ $inputClass }}"></div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Shadow</label>
            <select name="settings[shadow]" class="{{ $selectClass }}">
                @foreach(['none'=>'None','sm'=>'Small','md'=>'Medium','lg'=>'Large','xl'=>'Extra Large'] as $sv=>$sl)
                <option value="{{ $sv }}" {{ ($s['shadow'] ?? 'none') === $sv ? 'selected' : '' }}>{{ $sl }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="{{ $labelClass }}">Shadow Color</label><input type="text" name="settings[shadow_color]" value="{{ $s['shadow_color'] ?? '#00000040' }}" class="{{ $inputClass }}"></div>
    </div>
</div>

@elseif($block->type === 'grid')
<div class="space-y-4">
    <p class="text-xs text-white/30">A plain column grid with no background — just columns, gap and padding. Drop blocks inside to lay them out side by side.</p>
    <div><label class="{{ $labelClass }}">Section Title (optional)</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}" placeholder="Optional section title"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Columns</label>
            <select name="settings[columns]" class="{{ $selectClass }}">
                @foreach([1=>'1 Column',2=>'2 Columns',3=>'3 Columns',4=>'4 Columns',6=>'6 Columns',8=>'8 Columns',9=>'9 Columns',12=>'12 Columns'] as $v=>$l)
                <option value="{{ $v }}" {{ ($s['columns'] ?? 2) == $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="{{ $labelClass }}">Gap (px)</label><input type="number" name="settings[gap]" value="{{ $s['gap'] ?? 12 }}" min="0" max="48" class="{{ $inputClass }}"></div>
    </div>
    <div><label class="{{ $labelClass }}">Padding (px)</label><input type="number" name="settings[padding]" value="{{ $s['padding'] ?? 0 }}" min="0" max="64" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'grid_auto')
<div class="space-y-4">
    <p class="text-xs text-white/30">A responsive auto-fit grid. Columns are created automatically based on the minimum item width — items wrap to new rows as space allows.</p>
    <div><label class="{{ $labelClass }}">Section Title (optional)</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}" placeholder="Optional section title"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="{{ $labelClass }}">Min Item Width (px)</label><input type="number" name="settings[min_width]" value="{{ $s['min_width'] ?? 140 }}" min="60" max="600" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Gap (px)</label><input type="number" name="settings[gap]" value="{{ $s['gap'] ?? 12 }}" min="0" max="48" class="{{ $inputClass }}"></div>
    </div>
    <div><label class="{{ $labelClass }}">Padding (px)</label><input type="number" name="settings[padding]" value="{{ $s['padding'] ?? 0 }}" min="0" max="64" class="{{ $inputClass }}"></div>
</div>

@elseif(in_array($block->type, ['catalog', 'market', 'card_slider', 'scroll_cards', 'nav_menu']))
<div x-data="{ items: {{ json_encode($s['items'] ?? $s['cards'] ?? [['name'=>'','title'=>'','url'=>'']]) }} }">
    <label class="{{ $labelClass }}">Items</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2 space-y-2">
            <input type="text" x-model="items[i].name || items[i].title || items[i].text" :name="'settings[items]['+i+'][name]'" placeholder="Name/Title" class="{{ $inputClass }}">
            <input type="url" x-model="items[i].url" :name="'settings[items]['+i+'][url]'" placeholder="URL" class="{{ $inputClass }}">
            <button type="button" @click="items.splice(i,1)" class="text-xs text-red-400/60 hover:text-red-400"><i class="fas fa-times mr-1"></i>Remove</button>
        </div>
    </template>
    <button type="button" @click="items.push({name:'',url:''})" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Add Item</button>
</div>

@elseif($block->type === 'quiz')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Quiz Title</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <p class="text-xs text-white/20">Quiz questions are managed through the settings JSON.</p>
</div>

@elseif($block->type === 'chart_pie')
<div x-data="{ items: {{ json_encode($s['items'] ?? [['label'=>'Segment','value'=>50,'color'=>'#7c3aed']]) }} }">
    <label class="{{ $labelClass }}">Chart Segments</label>
    <template x-for="(item, i) in items" :key="i">
        <div class="glass rounded-lg p-3 mb-2 grid grid-cols-3 gap-2">
            <input type="text" x-model="items[i].label" :name="'settings[items]['+i+'][label]'" placeholder="Label" class="{{ $inputClass }}">
            <input type="number" x-model="items[i].value" :name="'settings[items]['+i+'][value]'" placeholder="Value" class="{{ $inputClass }}">
            <input type="color" x-model="items[i].color" :name="'settings[items]['+i+'][color]'" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
        </div>
    </template>
    <button type="button" @click="items.push({label:'',value:25,color:'#ec4899'})" class="text-xs text-violet-400"><i class="fas fa-plus mr-1"></i>Add</button>
</div>

@elseif($block->type === 'ai_companion')
    @php
        $userCmps = \App\Modules\User\Models\AiCompanion::where('user_id', auth()->id())
            ->where('placement', 'biolink')
            ->orderByDesc('id')
            ->get(['id', 'name', 'is_disabled']);
    @endphp
    <label class="{{ $labelClass }}">Pick an AI Companion</label>
    @if($userCmps->isEmpty())
        <p class="text-xs text-white/40 mb-2">You haven't created any biolink AI Companions yet.</p>
        <a href="{{ route('user.ai-companions.create') }}?placement=biolink" target="_blank" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Create one</a>
    @else
        <select name="settings[companion_id]" class="{{ $inputClass }}">
            <option value="">— Choose a Companion —</option>
            @foreach($userCmps as $c)
                <option value="{{ $c->id }}" {{ (string)($s['companion_id'] ?? '') === (string)$c->id ? 'selected' : '' }}>
                    {{ $c->name }}{{ $c->is_disabled ? ' — disabled' : '' }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-white/40 mt-2"><i class="fas fa-info-circle mr-1"></i> Renders as a floating chat launcher (or inline chat) on this biolink page.</p>
    @endif

@elseif($block->type === 'social_proof')
    @php $userSps = \App\Modules\User\Models\SocialProof::where('user_id', auth()->id())->orderByDesc('id')->get(); @endphp
    <label class="{{ $labelClass }}">Pick a Buzz campaign</label>
    @if($userSps->isEmpty())
        <p class="text-xs text-white/40 mb-2">You haven't created any campaigns yet.</p>
        <a href="{{ route('user.social-proofs.create') }}" target="_blank" class="text-xs text-violet-400 hover:text-violet-300"><i class="fas fa-plus mr-1"></i>Create one</a>
    @else
        <select name="settings[social_proof_id]" class="{{ $inputClass }}">
            <option value="">— Choose a campaign —</option>
            @foreach($userSps as $sp)
                <option value="{{ $sp->id }}" {{ (string)($s['social_proof_id'] ?? '') === (string)$sp->id ? 'selected' : '' }}>
                    {{ $sp->name }} ({{ $sp->typeLabel() }}){{ $sp->is_active ? '' : ' — paused' }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-white/40 mt-2"><i class="fas fa-info-circle mr-1"></i> The notification will appear as a floating widget on the biolink page.</p>
    @endif

@elseif(in_array($block->type, ['buy_me_coffee', 'patreon', 'ko_fi'], true))
@php
    $tipLabel = match($block->type) { 'buy_me_coffee' => 'Buy Me a Coffee username', 'patreon' => 'Patreon username', default => 'Ko-fi username' };
@endphp
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">{{ $tipLabel }}</label><input type="text" name="settings[username]" value="{{ $s['username'] ?? '' }}" placeholder="yourname" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Button Text</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? 'Support me' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description (optional)</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    @if($block->type === 'patreon')
        <div>
            <label class="{{ $labelClass }}">Featured tier name (optional)</label>
            <input type="text" name="settings[tier_name]" value="{{ $s['tier_name'] ?? '' }}" placeholder="e.g. Gold supporter" class="{{ $inputClass }}">
            <p class="text-[11px] text-white/40 mt-1">Shown as a chip under the Patreon button.</p>
        </div>
    @else
        @php $amts = is_array($s['amounts'] ?? null) ? implode(',', array_map('intval', $s['amounts'])) : ''; @endphp
        <div>
            <label class="{{ $labelClass }}">Quick tip amounts (USD, comma-separated)</label>
            <input type="text" name="settings[amounts_csv]" value="{{ $amts }}" placeholder="1, 3, 5" class="{{ $inputClass }}">
            <p class="text-[11px] text-white/40 mt-1">Renders inline tip-jar buttons. Leave blank for sensible defaults.</p>
        </div>
    @endif
</div>

@elseif($block->type === 'latest_youtube')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">YouTube channel handle</label><input type="text" name="settings[channel]" value="{{ $s['channel'] ?? '' }}" placeholder="@yourchannel" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Pinned video ID (optional)</label><input type="text" name="settings[video_id]" value="{{ $s['video_id'] ?? '' }}" placeholder="dQw4w9WgXcQ" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Override title (optional)</label><input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="{{ $inputClass }}"></div>
    <p class="text-xs text-white/40">The latest video is auto-fetched from the channel's public RSS feed and refreshed every few hours — no API key needed. Pin a specific video ID to override.</p>
</div>

@elseif($block->type === 'latest_instagram')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Instagram handle</label><input type="text" name="settings[handle]" value="{{ $s['handle'] ?? '' }}" placeholder="@yourhandle" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Pinned post URL (optional)</label><input type="url" name="settings[post_url]" value="{{ $s['post_url'] ?? '' }}" placeholder="https://instagram.com/p/..." class="{{ $inputClass }}"></div>
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[thumbnail]', 'currentValue' => $s['thumbnail'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Thumbnail', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div><label class="{{ $labelClass }}">Caption (optional)</label><input type="text" name="settings[caption]" value="{{ $s['caption'] ?? '' }}" class="{{ $inputClass }}"></div>
</div>

@elseif($block->type === 'featured_pin')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Title</label><input type="text" name="settings[text]" value="{{ $s['text'] ?? 'Featured' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Description</label><input type="text" name="settings[description]" value="{{ $s['description'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" class="{{ $inputClass }}"></div>
    @include('user.links.partials.file-upload-field', ['fieldName' => 'settings[thumbnail]', 'currentValue' => $s['thumbnail'] ?? '', 'acceptTypes' => 'image', 'labelText' => 'Thumbnail (optional)', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
    <div class="grid grid-cols-2 gap-3">
        @include('user.links.partials.icon-picker', ['fieldName' => 'settings[icon]', 'currentValue' => $s['icon'] ?? 'fa-thumbtack', 'labelText' => 'Icon', 'inputClass' => $inputClass, 'labelClass' => $labelClass])
        <div><label class="{{ $labelClass }}">Accent Color</label><input type="color" name="settings[accent_color]" value="{{ $s['accent_color'] ?? '#f59e0b' }}" class="w-full h-10 rounded-xl" style="border: 1px solid var(--border-glass); background: var(--bg-glass-input);"></div>
    </div>
</div>

@elseif($block->type === 'calendly_embed')
<div class="space-y-3">
    <div><label class="{{ $labelClass }}">Calendly URL</label><input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://calendly.com/you/30min" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Embed Height (px)</label><input type="number" name="settings[height]" value="{{ $s['height'] ?? 700 }}" min="400" max="1400" class="{{ $inputClass }}"></div>
    <label class="flex items-center gap-2 text-xs text-white/60"><input type="hidden" name="settings[hide_event_details]" value="0"><input type="checkbox" name="settings[hide_event_details]" value="1" {{ !empty($s['hide_event_details']) ? 'checked' : '' }} class="rounded text-violet-500">Hide event details</label>
    <label class="flex items-center gap-2 text-xs text-white/60"><input type="hidden" name="settings[hide_cookie_banner]" value="0"><input type="checkbox" name="settings[hide_cookie_banner]" value="1" {{ ($s['hide_cookie_banner'] ?? true) ? 'checked' : '' }} class="rounded text-violet-500">Hide cookie banner</label>
</div>

@elseif($block->type === 'map_location')
<div class="space-y-3" x-data="mapPinPicker({
        address: {{ Illuminate\Support\Js::from($s['address'] ?? '') }},
        lat: {{ Illuminate\Support\Js::from((string)($s['lat'] ?? '')) }},
        lng: {{ Illuminate\Support\Js::from((string)($s['lng'] ?? '')) }},
     })">
    <div><label class="{{ $labelClass }}">Address</label><input type="text" name="settings[address]" x-model="address" placeholder="123 Main St, City" class="{{ $inputClass }}"></div>
    <div>
        <div class="flex items-center justify-between mb-1">
            <span class="{{ $labelClass }}" style="margin-bottom:0;">Pin location</span>
            <button type="button" @click="toggleMap()" class="text-[11px] font-medium" style="color:#a78bfa;">
                <i class="fas fa-map-location-dot mr-1"></i> <span x-text="showMap ? 'Hide map' : 'Pick on map'"></span>
            </button>
        </div>
        <div x-show="showMap" x-cloak class="mb-1">
            <div class="flex gap-2 mb-2">
                <input x-model="searchQuery" @keydown.enter.prevent="searchAddress()" type="text" placeholder="Search a place or address…" class="{{ $inputClass }}">
                <button type="button" @click="searchAddress()" class="px-3 rounded-lg text-xs font-medium flex-shrink-0" style="background:rgba(124,58,237,.12);color:#a78bfa;border:1px solid rgba(124,58,237,.20)">
                    <i class="fas fa-magnifying-glass"></i>
                </button>
            </div>
            <div x-ref="map" class="mpp-map" style="height:240px;border-radius:12px;overflow:hidden;border:1px solid var(--border-glass);background:#1e2330;"></div>
            <p class="text-[11px] mt-1.5 text-white/40">
                <i class="fas fa-circle-info mr-1"></i> Tap the map or drag the pin — we'll fill in the address and coordinates for you.
            </p>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div><label class="{{ $labelClass }}">Latitude (optional)</label><input type="text" name="settings[lat]" x-model="lat" @input="syncMapFromInputs()" placeholder="37.7749" class="{{ $inputClass }}"></div>
        <div><label class="{{ $labelClass }}">Longitude (optional)</label><input type="text" name="settings[lng]" x-model="lng" @input="syncMapFromInputs()" placeholder="-122.4194" class="{{ $inputClass }}"></div>
    </div>
    <p class="text-[11px] text-white/40 -mt-1">If both lat/lng are set they take precedence over the address (useful for pin-precise pinning).</p>
    <div><label class="{{ $labelClass }}">Display Label (optional)</label><input type="text" name="settings[label]" value="{{ $s['label'] ?? '' }}" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Zoom</label><input type="number" name="settings[zoom]" value="{{ $s['zoom'] ?? 15 }}" min="1" max="20" class="{{ $inputClass }}"></div>
    <label class="flex items-center gap-2 text-xs text-white/60"><input type="hidden" name="settings[show_directions]" value="0"><input type="checkbox" name="settings[show_directions]" value="1" {{ ($s['show_directions'] ?? true) ? 'checked' : '' }} class="rounded text-violet-500">Show "Directions" button</label>
</div>

@else
<p class="text-xs text-white/20">Configure this block's settings below.</p>
@foreach($s as $key => $val)
    @if(is_string($val) || is_numeric($val))
    <div class="mt-2"><label class="{{ $labelClass }}">{{ ucwords(str_replace('_', ' ', $key)) }}</label><input type="text" name="settings[{{ $key }}]" value="{{ $val }}" class="{{ $inputClass }}"></div>
    @endif
@endforeach
@endif

@include('user.links.partials.block-style-settings', ['block' => $block, 'inputClass' => $inputClass, 'labelClass' => $labelClass])

@include('user.links.partials.block-display-settings', ['block' => $block, 'inputClass' => $inputClass, 'labelClass' => $labelClass])
</div>
