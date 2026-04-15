@extends('user.layouts.app')
@section('title', 'Page Settings - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php
    $bs = $link->settings['biolink'] ?? [];
    $layout = $bs['layout'] ?? [];
    $bt = $bs['block_theme'] ?? [];
    $canBrand = auth()->user()->getPlanFeature('custom_branding', false);
    $canFavicon = auth()->user()->getPlanFeature('custom_favicon', false);
    $canCode = auth()->user()->getPlanFeature('custom_code', false);
    $gtFonts = ['', 'Space Grotesk', 'Inter', 'Poppins', 'Roboto', 'Playfair Display', 'Montserrat', 'DM Sans', 'Outfit'];
    $gtWeights = ['' => 'Default', '300' => 'Light', '400' => 'Regular', '500' => 'Medium', '600' => 'Semi Bold', '700' => 'Bold', '800' => 'Extra Bold'];
    $gtBorderStyles = ['none' => 'None', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'];
    $gtShadowTypes = ['none' => 'None', 'soft' => 'Soft', 'hard' => 'Hard', 'neon' => 'Neon Glow', 'glow' => 'Subtle Glow', 'neumorphic' => 'Neumorphic', 'inset' => 'Inner Shadow'];
    $gtEffects = ['none' => 'None', 'glass' => 'Glassmorphism', 'gradient_border' => 'Gradient Border'];
    $gtTemplates = \App\Modules\User\Models\BiolinkBlock::BLOCK_TEMPLATES;
@endphp

<div class="max-w-4xl mx-auto" x-data="{ settingsTab: 'appearance' }">

    <div class="flex items-center justify-between mb-1">
        <div>
            <h1 class="text-2xl font-bold gradient-text">{{ $link->alias }}</h1>
            <div class="flex items-center gap-2 mt-1" x-data="{ copied: false }">
                <span class="inline-flex items-center gap-1.5 text-sm">
                    <span class="w-2 h-2 rounded-full {{ $link->is_active ? 'bg-emerald-400' : 'bg-red-400' }}" style="{{ $link->is_active ? 'box-shadow: 0 0 8px rgba(16,185,129,0.5);' : '' }}"></span>
                    <span style="color: var(--text-dimmed);">Your link is</span>
                    <span class="text-purple-400">{{ $link->getShortUrl() }}</span>
                </span>
                <button @click="navigator.clipboard.writeText('{{ $link->getShortUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)" class="hover:text-purple-400 transition-colors" style="color: var(--text-faint);">
                    <i x-show="!copied" class="fas fa-copy text-xs"></i>
                    <i x-show="copied" x-cloak class="fas fa-check text-emerald-400 text-xs"></i>
                </button>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('user.links.blocks.editor', $link) }}" class="btn-primary px-4 py-2 text-xs inline-flex items-center gap-2">
                <i class="fas fa-th-large text-[10px]"></i> Blocks
            </a>
            <a href="{{ url('/' . $link->alias) }}" target="_blank" class="btn-ghost text-xs py-2" title="Preview">
                <i class="fas fa-external-link-alt text-[10px]"></i>
            </a>
        </div>
    </div>

    <div class="flex items-center gap-1.5 mt-5 mb-6 p-1 rounded-xl" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
        @foreach(['appearance' => ['icon' => 'fa-palette', 'label' => 'Appearance'], 'layout' => ['icon' => 'fa-ruler-combined', 'label' => 'Layout'], 'block_theme' => ['icon' => 'fa-wand-magic-sparkles', 'label' => 'Block Theme'], 'advanced' => ['icon' => 'fa-sliders-h', 'label' => 'Advanced']] as $tabKey => $tab)
        <button @click="settingsTab = '{{ $tabKey }}'" class="flex-1 flex items-center justify-center gap-1.5 text-xs font-semibold py-2.5 rounded-lg transition-all"
                :class="settingsTab === '{{ $tabKey }}' ? 'text-white shadow-sm' : ''"
                :style="settingsTab === '{{ $tabKey }}' ? 'background: linear-gradient(135deg, #8b5cf6, #7c3aed);' : 'color: var(--text-faint);'">
            <i class="fas {{ $tab['icon'] }} text-[10px]"></i>
            <span class="hidden sm:inline">{{ $tab['label'] }}</span>
        </button>
        @endforeach
    </div>

    <form method="POST" action="{{ route('user.links.page-settings', $link) }}" enctype="multipart/form-data">
        @csrf

        <div x-show="settingsTab === 'appearance'" x-cloak>
            <div class="space-y-6">

                <div class="card-premium p-6" x-data="{ editing: false, alias: '{{ $link->alias }}' }">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.1);"><i class="fas fa-link text-purple-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Short URL</h3>
                    </div>
                    <div class="flex items-center rounded-xl overflow-hidden" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        <span class="px-3 py-2.5 text-sm flex-shrink-0" style="color: var(--text-faint); border-right: 1px solid var(--border-glass);">{{ request()->getHost() }}/</span>
                        <template x-if="!editing">
                            <span class="flex-1 px-3 py-2.5 text-sm font-medium cursor-pointer flex items-center justify-between gap-2 group" style="color: var(--text-primary);" @click="editing = true; $nextTick(() => $refs.aliasInput.focus())">
                                <span x-text="alias"></span>
                                <i class="fas fa-pen text-[10px] opacity-0 group-hover:opacity-60 transition-opacity" style="color: var(--text-faint);"></i>
                            </span>
                        </template>
                        <template x-if="editing">
                            <div class="flex-1 flex items-center">
                                <input x-ref="aliasInput" type="text" x-model="alias" class="flex-1 px-3 py-2.5 text-sm font-medium bg-transparent outline-none" style="color: var(--text-primary);" @keydown.escape="editing = false">
                                <div class="flex items-center gap-1 pr-2">
                                    <button type="button" @click="editing = false; alias = '{{ $link->alias }}'" class="text-[10px] px-2 py-1 rounded" style="color: var(--text-faint);">Cancel</button>
                                    <a :href="'{{ route('user.links.update-alias', $link) }}'" class="text-[10px] px-2 py-1 rounded bg-purple-600 text-white"
                                       @click.prevent="fetch('{{ route('user.links.update-alias', $link) }}', { method:'PUT', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'}, body:JSON.stringify({alias:alias})}).then(r=>r.json()).then(d=>{if(d.success||!d.errors){editing=false;location.reload()}else{alert(d.errors?.alias?.[0]||'Error')}}).catch(()=>alert('Error'))">Save</a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="card-premium p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(236,72,153,0.1);"><i class="fas fa-palette text-pink-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Page Design</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Page Title</label>
                                <input type="text" name="biolink_title" value="{{ $bs['biolink_title'] ?? $link->title }}" class="theme-input w-full" placeholder="My Bio Link">
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Family</label>
                                <select name="font_family" class="theme-input w-full">
                                    @foreach(['Space Grotesk','Inter','Poppins','Roboto','Playfair Display','Montserrat'] as $font)
                                    <option value="{{ $font }}" {{ ($bs['font_family'] ?? '') === $font ? 'selected' : '' }}>{{ $font }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Description</label>
                            <textarea name="biolink_description" rows="2" class="theme-input w-full" placeholder="A short description for your page">{{ $bs['biolink_description'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card-premium p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(99,102,241,0.1);"><i class="fas fa-fill-drip text-indigo-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Colors & Background</h3>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Type</label>
                            <select name="background_type" class="theme-input w-full" x-data x-ref="bgType" @change="$dispatch('bg-type-change', { val: $refs.bgType.value })">
                                <option value="color" {{ ($bs['background_type'] ?? '') === 'color' ? 'selected' : '' }}>Solid Color</option>
                                <option value="gradient" {{ ($bs['background_type'] ?? '') === 'gradient' ? 'selected' : '' }}>Gradient</option>
                                <option value="image" {{ ($bs['background_type'] ?? '') === 'image' ? 'selected' : '' }}>Image</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="background_color" value="{{ $bs['background_color'] ?? '#0a0612' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bs['background_color'] ?? '#0a0612' }}</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="font_color" value="{{ $bs['font_color'] ?? '#ffffff' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bs['font_color'] ?? '#ffffff' }}</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Gradient CSS</label>
                            <input type="text" name="background_gradient" value="{{ $bs['background_gradient'] ?? 'linear-gradient(135deg, #0a0612 0%, #1a0533 50%, #0a0612 100%)' }}" class="theme-input w-full font-mono text-xs">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Image</label>
                            <input type="file" name="background_image" accept="image/*" class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-purple-500/10 file:text-purple-400 file:font-medium" style="color: var(--text-faint);">
                        </div>
                    </div>
                </div>

                <div class="card-premium p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.1);"><i class="fas fa-hand-pointer text-purple-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Button Style</h3>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Shape</label>
                            <select name="button_style" class="theme-input w-full">
                                @foreach(['rounded'=>'Rounded','pill'=>'Pill','square'=>'Square','outline'=>'Outline','shadow'=>'Shadow'] as $val => $label)
                                <option value="{{ $val }}" {{ ($bs['button_style'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="button_color" value="{{ $bs['button_color'] ?? '#7c3aed' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bs['button_color'] ?? '#7c3aed' }}</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button Text Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="button_text_color" value="{{ $bs['button_text_color'] ?? '#ffffff' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bs['button_text_color'] ?? '#ffffff' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="settingsTab === 'layout'" x-cloak>
            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(34,211,238,0.1);"><i class="fas fa-ruler-combined text-cyan-400 text-xs"></i></div>
                    <h3 class="text-sm font-bold" style="color: var(--text-primary);">Page Layout</h3>
                </div>
                <div class="space-y-6">
                    <div>
                        <p class="text-xs font-semibold mb-3" style="color: var(--text-muted);">Content Max Width (px)</p>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="flex items-center gap-2 text-[11px] font-medium mb-1" style="color: var(--text-faint);"><i class="fas fa-mobile-alt text-[9px] text-purple-400"></i> Phone</label>
                                <input type="number" name="layout[max_width_phone]" value="{{ $layout['max_width_phone'] ?? '' }}" placeholder="448" min="280" max="600" class="theme-input w-full">
                            </div>
                            <div>
                                <label class="flex items-center gap-2 text-[11px] font-medium mb-1" style="color: var(--text-faint);"><i class="fas fa-tablet-alt text-[9px] text-pink-400"></i> Tablet</label>
                                <input type="number" name="layout[max_width_tablet]" value="{{ $layout['max_width_tablet'] ?? '' }}" placeholder="540" min="320" max="900" class="theme-input w-full">
                            </div>
                            <div>
                                <label class="flex items-center gap-2 text-[11px] font-medium mb-1" style="color: var(--text-faint);"><i class="fas fa-desktop text-[9px] text-cyan-400"></i> Desktop</label>
                                <input type="number" name="layout[max_width_desktop]" value="{{ $layout['max_width_desktop'] ?? '' }}" placeholder="680" min="400" max="1200" class="theme-input w-full">
                            </div>
                        </div>
                    </div>

                    <div style="border-top: 1px solid var(--border-subtle);" class="pt-5">
                        <p class="text-xs font-semibold mb-3" style="color: var(--text-muted);">Page Padding (px)</p>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] mb-1" style="color: var(--text-faint);">Top</label>
                                <input type="number" name="layout[page_padding_top]" value="{{ $layout['page_padding_top'] ?? '' }}" placeholder="32" min="0" max="200" class="theme-input w-full">
                            </div>
                            <div>
                                <label class="block text-[11px] mb-1" style="color: var(--text-faint);">Bottom</label>
                                <input type="number" name="layout[page_padding_bottom]" value="{{ $layout['page_padding_bottom'] ?? '' }}" placeholder="64" min="0" max="200" class="theme-input w-full">
                            </div>
                            <div>
                                <label class="block text-[11px] mb-1" style="color: var(--text-faint);">Sides</label>
                                <input type="number" name="layout[page_padding_x]" value="{{ $layout['page_padding_x'] ?? '' }}" placeholder="16" min="0" max="100" class="theme-input w-full">
                            </div>
                        </div>
                    </div>

                    <div style="border-top: 1px solid var(--border-subtle);" class="pt-5">
                        <p class="text-xs font-semibold mb-3" style="color: var(--text-muted);">Block Spacing (px)</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] mb-1" style="color: var(--text-faint);">Gap between blocks</label>
                                <input type="number" name="layout[block_gap]" value="{{ $layout['block_gap'] ?? '' }}" placeholder="12" min="0" max="100" class="theme-input w-full">
                            </div>
                            <div>
                                <label class="block text-[11px] mb-1" style="color: var(--text-faint);">Block inner padding</label>
                                <input type="number" name="layout[block_padding]" value="{{ $layout['block_padding'] ?? '' }}" placeholder="Auto" min="0" max="60" class="theme-input w-full">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="settingsTab === 'block_theme'" x-cloak>
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
                            <select name="block_theme[font_family]" class="theme-input w-full"><option value="">Inherit</option>@foreach($gtFonts as $f)@if($f)<option value="{{ $f }}" {{ ($bt['font_family'] ?? '') === $f ? 'selected' : '' }}>{{ $f }}</option>@endif @endforeach</select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Weight</label>
                            <select name="block_theme[font_weight]" class="theme-input w-full">@foreach($gtWeights as $wVal => $wLabel)<option value="{{ $wVal }}" {{ ($bt['font_weight'] ?? '') == $wVal ? 'selected' : '' }}>{{ $wLabel }}</option>@endforeach</select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Size (px)</label>
                            <input type="number" name="block_theme[font_size]" value="{{ $bt['font_size'] ?? '' }}" placeholder="Auto" min="8" max="72" class="theme-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Text Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="block_theme[text_color]" value="{{ $bt['text_color'] ?? '#ffffff' }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-glass);">
                                <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bt['text_color'] ?? '#ffffff' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="gtTab === 'fill'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Color</label>
                            <input type="color" name="block_theme[bg_color]" value="{{ $bt['bg_color'] ?? '#ffffff0d' }}" class="w-full h-10 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass);">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Opacity (%)</label>
                            <input type="number" name="block_theme[bg_opacity]" value="{{ $bt['bg_opacity'] ?? 100 }}" min="0" max="100" class="theme-input w-full">
                        </div>
                    </div>
                    <div style="border-top: 1px solid var(--border-subtle);" class="pt-4">
                        <p class="text-xs font-semibold mb-3" style="color: var(--text-muted);">Padding</p>
                        <div class="grid grid-cols-5 gap-2">
                            <div><label class="block text-[10px] mb-1" style="color: var(--text-faint);">All</label><input type="number" name="block_theme[padding]" value="{{ $bt['padding'] ?? '' }}" placeholder="—" min="0" max="60" class="theme-input w-full"></div>
                            <div><label class="block text-[10px] mb-1" style="color: var(--text-faint);">Top</label><input type="number" name="block_theme[padding_top]" value="{{ $bt['padding_top'] ?? '' }}" placeholder="—" min="0" max="200" class="theme-input w-full"></div>
                            <div><label class="block text-[10px] mb-1" style="color: var(--text-faint);">Bottom</label><input type="number" name="block_theme[padding_bottom]" value="{{ $bt['padding_bottom'] ?? '' }}" placeholder="—" min="0" max="200" class="theme-input w-full"></div>
                            <div><label class="block text-[10px] mb-1" style="color: var(--text-faint);">Left</label><input type="number" name="block_theme[padding_left]" value="{{ $bt['padding_left'] ?? '' }}" placeholder="—" min="0" max="200" class="theme-input w-full"></div>
                            <div><label class="block text-[10px] mb-1" style="color: var(--text-faint);">Right</label><input type="number" name="block_theme[padding_right]" value="{{ $bt['padding_right'] ?? '' }}" placeholder="—" min="0" max="200" class="theme-input w-full"></div>
                        </div>
                    </div>
                </div>

                <div x-show="gtTab === 'border'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Border Style</label>
                            <select name="block_theme[border_style]" class="theme-input w-full">@foreach($gtBorderStyles as $bsVal => $bsLabel)<option value="{{ $bsVal }}" {{ ($bt['border_style'] ?? 'none') === $bsVal ? 'selected' : '' }}>{{ $bsLabel }}</option>@endforeach</select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Border Width</label>
                            <input type="number" name="block_theme[border_width]" value="{{ $bt['border_width'] ?? '' }}" placeholder="1" min="0" max="10" class="theme-input w-full">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Border Color</label>
                            <input type="color" name="block_theme[border_color]" value="{{ $bt['border_color'] ?? '#ffffff15' }}" class="w-full h-10 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass);">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Border Radius</label>
                            <input type="number" name="block_theme[border_radius]" value="{{ $bt['border_radius'] ?? '' }}" placeholder="12" min="0" max="999" class="theme-input w-full">
                        </div>
                    </div>
                    <div style="border-top: 1px solid var(--border-subtle);" class="pt-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Shadow Type</label>
                                <select name="block_theme[shadow_type]" class="theme-input w-full">@foreach($gtShadowTypes as $shVal => $shLabel)<option value="{{ $shVal }}" {{ ($bt['shadow_type'] ?? 'none') === $shVal ? 'selected' : '' }}>{{ $shLabel }}</option>@endforeach</select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Shadow Blur</label>
                                <input type="number" name="block_theme[shadow_blur]" value="{{ $bt['shadow_blur'] ?? 12 }}" min="0" max="100" class="theme-input w-full">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Shadow Color</label>
                            <input type="color" name="block_theme[shadow_color]" value="{{ $bt['shadow_color'] ?? '#000000' }}" class="w-full h-10 rounded-lg cursor-pointer" style="border: 1px solid var(--border-glass);">
                        </div>
                    </div>
                </div>

                <div x-show="gtTab === 'fx'" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Effect</label>
                        <select name="block_theme[effect]" class="theme-input w-full">@foreach($gtEffects as $eVal => $eLabel)<option value="{{ $eVal }}" {{ ($bt['effect'] ?? 'none') === $eVal ? 'selected' : '' }}>{{ $eLabel }}</option>@endforeach</select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Glass Blur (px)</label>
                            <input type="number" name="block_theme[glass_blur]" value="{{ $bt['glass_blur'] ?? 20 }}" min="0" max="100" class="theme-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Glass Opacity (%)</label>
                            <input type="number" name="block_theme[glass_opacity]" value="{{ $bt['glass_opacity'] ?? 15 }}" min="0" max="100" class="theme-input w-full">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="settingsTab === 'advanced'" x-cloak>
            <div class="space-y-6">

                <div class="card-premium p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(16,185,129,0.1);"><i class="fas fa-check-circle text-emerald-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Badges & Branding</h3>
                    </div>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl transition-all hover:bg-white/[0.02]" style="border: 1px solid var(--border-glass);">
                            <input type="hidden" name="verified_badge" value="0">
                            <input type="checkbox" name="verified_badge" value="1" {{ ($bs['verified_badge'] ?? false) ? 'checked' : '' }} class="rounded text-purple-500 focus:ring-purple-500/40 w-4 h-4" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                            <div>
                                <span class="text-xs font-semibold" style="color: var(--text-primary);">Verified badge</span>
                                <p class="text-[10px]" style="color: var(--text-dimmed);">Show a verified checkmark on your page</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl transition-all hover:bg-white/[0.02]" style="border: 1px solid var(--border-glass);">
                            <input type="hidden" name="branding_hidden" value="0">
                            <input type="checkbox" name="branding_hidden" value="1" {{ ($bs['branding_hidden'] ?? false) ? 'checked' : '' }} class="rounded text-purple-500 focus:ring-purple-500/40 w-4 h-4" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                            <div>
                                <span class="text-xs font-semibold" style="color: var(--text-primary);">Hide "Powered by 1INME"</span>
                                <p class="text-[10px]" style="color: var(--text-dimmed);">Remove the default branding footer</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="card-premium p-6">
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(251,146,60,0.1);"><i class="fas fa-copyright text-orange-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Custom Branding</h3>
                        @if(!$canBrand)<span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold" style="background: linear-gradient(135deg, rgba(251,146,60,0.15), rgba(245,158,11,0.1)); color: #fb923c;">PRO</span>@endif
                    </div>
                    @if($canBrand)
                    <p class="text-[11px] mb-4" style="color: var(--text-dimmed);">Replace the default footer with your own brand.</p>
                    <div class="space-y-3">
                        <div><label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Brand Name</label><input type="text" name="custom_branding_text" value="{{ $bs['custom_branding_text'] ?? '' }}" placeholder="Your Brand" class="theme-input w-full"></div>
                        <div><label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Brand URL</label><input type="url" name="custom_branding_url" value="{{ $bs['custom_branding_url'] ?? '' }}" placeholder="https://yourbrand.com" class="theme-input w-full"></div>
                        <div><label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Logo URL</label><input type="url" name="custom_branding_logo" value="{{ $bs['custom_branding_logo'] ?? '' }}" placeholder="https://yourbrand.com/logo.png" class="theme-input w-full"></div>
                    </div>
                    @else
                    <p class="text-xs mt-2 mb-3" style="color: var(--text-dimmed);">Replace "Powered by 1INME" with your own brand name, logo, and URL.</p>
                    <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg" style="background: linear-gradient(135deg, #fb923c, #f59e0b); color: #fff;">Upgrade Plan</a>
                    @endif
                </div>

                <div class="card-premium p-6">
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(34,211,238,0.1);"><i class="fas fa-image text-cyan-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Custom Favicon</h3>
                        @if(!$canFavicon)<span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold" style="background: linear-gradient(135deg, rgba(34,211,238,0.15), rgba(6,182,212,0.1)); color: #22d3ee;">PRO</span>@endif
                    </div>
                    @if($canFavicon)
                    <p class="text-[11px] mb-4" style="color: var(--text-dimmed);">Custom browser tab icon for this page.</p>
                    @if($link->favicon)
                    <div class="flex items-center gap-2 p-2 rounded-lg mb-3" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                        <img src="{{ $link->favicon }}" class="w-6 h-6 rounded object-contain" alt="Favicon"><span class="text-[10px]" style="color: var(--text-muted);">Current favicon</span>
                    </div>
                    @endif
                    <div class="space-y-3">
                        <div><label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Favicon URL</label><input type="url" name="favicon_url" value="{{ $bs['favicon_url'] ?? $link->favicon ?? '' }}" placeholder="https://example.com/favicon.png" class="theme-input w-full"></div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Or Upload</label>
                            <input type="file" name="favicon_upload" accept="image/png,image/x-icon,image/svg+xml,image/jpeg" class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-cyan-500/10 file:text-cyan-400 file:font-medium" style="color: var(--text-faint);">
                            <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">PNG, ICO, SVG or JPG. 32×32 or 64×64px recommended.</p>
                        </div>
                    </div>
                    @else
                    <p class="text-xs mt-2 mb-3" style="color: var(--text-dimmed);">Set a custom browser tab icon for your page.</p>
                    <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg" style="background: linear-gradient(135deg, #22d3ee, #06b6d4); color: #fff;">Upgrade Plan</a>
                    @endif
                </div>

                <div class="card-premium p-6">
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(168,85,247,0.1);"><i class="fas fa-code text-purple-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Custom CSS & JS</h3>
                        @if(!$canCode)<span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold" style="background: linear-gradient(135deg, rgba(168,85,247,0.15), rgba(139,92,246,0.1)); color: #a855f7;">PRO</span>@endif
                    </div>
                    @if($canCode)
                    <p class="text-[11px] mb-4" style="color: var(--text-dimmed);">Inject custom code into your biolink page.</p>
                    <div class="space-y-4" x-data="{ codeTab: 'css' }">
                        <div class="flex gap-1 p-0.5 rounded-lg" style="background: var(--bg-glass-input);">
                            @foreach(['css' => 'CSS', 'js_head' => 'JS (Head)', 'js_body' => 'JS (Body)'] as $ctKey => $ctLabel)
                            <button type="button" @click="codeTab = '{{ $ctKey }}'"
                                    :class="codeTab === '{{ $ctKey }}' ? 'text-white shadow-sm' : ''"
                                    :style="codeTab === '{{ $ctKey }}' ? 'background: linear-gradient(135deg, #a855f7, #7c3aed);' : 'color: var(--text-faint);'"
                                    class="flex-1 text-[10px] font-bold py-1.5 rounded-md transition-all">{{ $ctLabel }}</button>
                            @endforeach
                        </div>
                        <div x-show="codeTab === 'css'"><textarea name="custom_css" rows="6" class="theme-input w-full font-mono text-xs" placeholder="/* Custom styles */">{{ $bs['custom_css'] ?? '' }}</textarea></div>
                        <div x-show="codeTab === 'js_head'"><textarea name="custom_js_head" rows="6" class="theme-input w-full font-mono text-xs" placeholder="// Before page loads">{{ $bs['custom_js_head'] ?? '' }}</textarea></div>
                        <div x-show="codeTab === 'js_body'"><textarea name="custom_js_body" rows="6" class="theme-input w-full font-mono text-xs" placeholder="// After page loads">{{ $bs['custom_js_body'] ?? '' }}</textarea></div>
                    </div>
                    @else
                    <p class="text-xs mt-2 mb-3" style="color: var(--text-dimmed);">Add custom CSS and JavaScript to your page.</p>
                    <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg" style="background: linear-gradient(135deg, #a855f7, #7c3aed); color: #fff;">Upgrade Plan</a>
                    @endif
                </div>

                <div class="card-premium p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(59,130,246,0.1);"><i class="fas fa-search text-blue-400 text-xs"></i></div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">SEO & Tracking</h3>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center text-sm p-2 rounded-lg" style="background: var(--bg-glass);">
                            <span style="color: var(--text-faint);">SEO Title</span>
                            <span style="color: var(--text-muted);">{{ $link->seo_title ?: 'Not set' }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm p-2 rounded-lg" style="background: var(--bg-glass);">
                            <span style="color: var(--text-faint);">Description</span>
                            <span class="truncate max-w-[200px]" style="color: var(--text-muted);">{{ $link->seo_description ?: 'Not set' }}</span>
                        </div>
                        @if($link->pixels->count())
                        <div class="flex flex-wrap gap-1.5 p-2 rounded-lg" style="background: var(--bg-glass);">
                            <span class="text-[10px] font-semibold mr-1" style="color: var(--text-faint);">Pixels:</span>
                            @foreach($link->pixels as $pixel)
                            <span class="px-2 py-0.5 rounded text-[10px] font-medium" style="background: rgba(139,92,246,0.08); color: #a78bfa;">{{ $pixel->name }}</span>
                            @endforeach
                        </div>
                        @endif
                        <a href="{{ route('user.links.edit', $link) }}" class="inline-flex items-center gap-1.5 text-xs text-purple-400 hover:underline mt-1">
                            <i class="fas fa-external-link-alt text-[9px]"></i> Edit SEO, pixels & UTM in link settings
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 mt-6 py-4 flex items-center gap-3" style="background: var(--bg-body); z-index: 10;">
            <button type="submit" class="btn-primary px-8 py-3 text-sm font-semibold inline-flex items-center gap-2 shadow-lg">
                <i class="fas fa-save text-xs"></i> Save Settings
            </button>
            <a href="{{ route('user.links.blocks.editor', $link) }}" class="btn-ghost px-6 py-3 text-sm">Back to Blocks</a>
        </div>
    </form>
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
