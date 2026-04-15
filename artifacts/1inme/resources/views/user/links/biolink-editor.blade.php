@extends('user.layouts.app')
@section('title', 'Biolink Settings - ' . ($link->title ?: $link->alias))

@section('content')
<div x-data="biolinkEditor()" class="max-w-7xl mx-auto">
    <div class="flex items-center gap-2 text-sm mb-2" style="color: var(--text-faint);">
        <a href="{{ route('user.links.index') }}" class="hover:text-purple-400 transition-colors">Links</a>
        <i class="fas fa-chevron-right text-[8px]"></i>
        <span style="color: var(--text-muted);">Biolink Settings</span>
    </div>

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
            <form action="{{ route('user.links.toggle-active', $link) }}" method="POST">
                @csrf
                <button class="btn-ghost text-xs py-2" title="{{ $link->is_active ? 'Deactivate' : 'Activate' }}">
                    <i class="fas {{ $link->is_active ? 'fa-toggle-on text-emerald-400' : 'fa-toggle-off' }}"></i>
                </button>
            </form>
            <a href="{{ url('/' . $link->alias) }}" target="_blank" class="btn-ghost text-xs py-2" title="Open in new tab">
                <i class="fas fa-external-link-alt text-[10px]"></i>
            </a>
            <a href="{{ route('user.links.qrcode', $link) }}" class="btn-ghost text-xs py-2" title="QR Code">
                <i class="fas fa-qrcode text-[10px]"></i>
            </a>
            <a href="{{ route('user.links.show', $link) }}" class="btn-ghost text-xs py-2" title="Analytics">
                <i class="fas fa-chart-bar text-[10px]"></i>
            </a>
        </div>
    </div>

    <div class="flex items-center gap-3 mt-5 mb-6">
        <button @click="activeTab = 'settings'" :class="activeTab === 'settings' ? 'btn-primary shadow-lg' : 'btn-ghost'" class="px-5 py-2.5 rounded-xl text-sm font-medium transition-all flex items-center gap-2">
            <i class="fas fa-cog text-xs"></i> Settings
        </button>
        <button @click="activeTab = 'blocks'" :class="activeTab === 'blocks' ? 'btn-primary shadow-lg' : 'btn-ghost'" class="px-5 py-2.5 rounded-xl text-sm font-medium transition-all flex items-center gap-2">
            <i class="fas fa-th-large text-xs"></i> Blocks
        </button>
        <button @click="showAddBlock = true" class="btn-primary px-5 py-2.5 text-sm ml-1" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fas fa-plus text-xs"></i> Add block
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7 xl:col-span-7">
            <div x-show="activeTab === 'settings'" x-cloak>
                <div class="space-y-2">
                    @php $bs = $link->settings['biolink'] ?? []; @endphp

                    <div class="card-premium overflow-hidden" x-data="{ open: true, editing: false, alias: '{{ $link->alias }}' }">
                        <button @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                            <span class="flex items-center gap-3 text-sm font-semibold" style="color: var(--text-primary);">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.15);"><i class="fas fa-link text-purple-400 text-[10px]"></i></div>
                                Short URL
                            </span>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-300" style="color: var(--text-faint);" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-cloak class="px-5 pb-5 pt-4" style="border-top: 1px solid var(--border-subtle);">
                            <form method="POST" action="{{ route('user.links.update-alias', $link) }}" x-ref="aliasForm">
                                @csrf
                                @method('PUT')
                                <div class="flex items-center rounded-xl overflow-hidden" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                    <span class="px-3 py-2.5 text-sm flex-shrink-0" style="color: var(--text-faint); background: var(--bg-glass-input); border-right: 1px solid var(--border-glass);">{{ request()->getHost() }}/</span>
                                    <template x-if="!editing">
                                        <span class="flex-1 px-3 py-2.5 text-sm font-medium cursor-pointer flex items-center justify-between gap-2 group" style="color: var(--text-primary);" @click="editing = true; $nextTick(() => $refs.aliasInput.focus())">
                                            <span x-text="alias"></span>
                                            <i class="fas fa-pen text-[10px] opacity-0 group-hover:opacity-60 transition-opacity" style="color: var(--text-faint);"></i>
                                        </span>
                                    </template>
                                    <template x-if="editing">
                                        <input x-ref="aliasInput" type="text" name="alias" x-model="alias" class="flex-1 px-3 py-2.5 text-sm font-medium bg-transparent outline-none" style="color: var(--text-primary);" @keydown.enter.prevent="$refs.aliasForm.submit()" @keydown.escape="editing = false">
                                    </template>
                                </div>
                                <div class="flex items-center justify-between mt-2">
                                    <p class="text-xs" style="color: var(--text-faint);">Click to edit your URL alias.</p>
                                    <div x-show="editing" x-cloak class="flex items-center gap-2">
                                        <button type="button" @click="editing = false; alias = '{{ $link->alias }}'" class="text-xs px-3 py-1 rounded-lg transition-colors" style="color: var(--text-faint);" onmouseover="this.style.background='var(--bg-glass-hover)'" onmouseout="this.style.background='transparent'">Cancel</button>
                                        <button type="submit" class="text-xs px-3 py-1 rounded-lg bg-purple-600 text-white hover:bg-purple-500 transition-colors">Save</button>
                                    </div>
                                </div>
                            </form>
                            @error('alias')
                                <p class="text-xs text-red-400 mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <form method="POST" action="{{ route('user.links.page-settings', $link) }}" enctype="multipart/form-data">
                        @csrf

                        <div class="card-premium overflow-hidden" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                                <span class="flex items-center gap-3 text-sm font-semibold" style="color: var(--text-primary);">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: rgba(236,72,153,0.1); border: 1px solid rgba(236,72,153,0.15);"><i class="fas fa-palette text-pink-400 text-[10px]"></i></div>
                                    Customizations
                                </span>
                                <i class="fas fa-chevron-down text-xs transition-transform duration-300" style="color: var(--text-faint);" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open" x-cloak class="px-5 pb-5 pt-4 space-y-4" style="border-top: 1px solid var(--border-subtle);">
                                <div>
                                    <label class="block text-xs mb-1.5" style="color: var(--text-faint);">Page Title</label>
                                    <input type="text" name="biolink_title" value="{{ $bs['biolink_title'] ?? $link->title }}" class="theme-input w-full">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1.5" style="color: var(--text-faint);">Description</label>
                                    <textarea name="biolink_description" rows="2" class="theme-input w-full">{{ $bs['biolink_description'] ?? '' }}</textarea>
                                </div>
                                <div>
                                    <label class="block text-xs mb-1.5" style="color: var(--text-faint);">Background Type</label>
                                    <select name="background_type" class="theme-input w-full">
                                        <option value="color" {{ ($bs['background_type'] ?? '') === 'color' ? 'selected' : '' }}>Solid Color</option>
                                        <option value="gradient" {{ ($bs['background_type'] ?? '') === 'gradient' ? 'selected' : '' }}>Gradient</option>
                                        <option value="image" {{ ($bs['background_type'] ?? '') === 'image' ? 'selected' : '' }}>Image</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs mb-1.5" style="color: var(--text-faint);">Background Color</label>
                                        <input type="color" name="background_color" value="{{ $bs['background_color'] ?? '#0a0612' }}" class="w-full h-10 rounded-xl cursor-pointer" style="border: 1px solid var(--border-subtle); background: var(--input-bg);">
                                    </div>
                                    <div>
                                        <label class="block text-xs mb-1.5" style="color: var(--text-faint);">Font Color</label>
                                        <input type="color" name="font_color" value="{{ $bs['font_color'] ?? '#ffffff' }}" class="w-full h-10 rounded-xl cursor-pointer" style="border: 1px solid var(--border-subtle); background: var(--input-bg);">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs mb-1.5" style="color: var(--text-faint);">Gradient CSS</label>
                                    <input type="text" name="background_gradient" value="{{ $bs['background_gradient'] ?? 'linear-gradient(135deg, #0a0612 0%, #1a0533 50%, #0a0612 100%)' }}" class="theme-input w-full">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1.5" style="color: var(--text-faint);">Background Image</label>
                                    <input type="file" name="background_image" accept="image/*" class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm" style="color: var(--text-faint); --file-bg: var(--input-bg);">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1.5" style="color: var(--text-faint);">Font Family</label>
                                    <select name="font_family" class="theme-input w-full">
                                        @foreach(['Space Grotesk', 'Inter', 'Poppins', 'Roboto', 'Playfair Display', 'Montserrat'] as $font)
                                        <option value="{{ $font }}" {{ ($bs['font_family'] ?? '') === $font ? 'selected' : '' }}>{{ $font }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs mb-1.5" style="color: var(--text-faint);">Button Style</label>
                                    <select name="button_style" class="theme-input w-full">
                                        @foreach(['rounded' => 'Rounded', 'pill' => 'Pill', 'square' => 'Square', 'outline' => 'Outline', 'shadow' => 'Shadow'] as $val => $label)
                                        <option value="{{ $val }}" {{ ($bs['button_style'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs mb-1.5" style="color: var(--text-faint);">Button Color</label>
                                        <input type="color" name="button_color" value="{{ $bs['button_color'] ?? '#7c3aed' }}" class="w-full h-10 rounded-xl cursor-pointer" style="border: 1px solid var(--border-subtle); background: var(--input-bg);">
                                    </div>
                                    <div>
                                        <label class="block text-xs mb-1.5" style="color: var(--text-faint);">Button Text Color</label>
                                        <input type="color" name="button_text_color" value="{{ $bs['button_text_color'] ?? '#ffffff' }}" class="w-full h-10 rounded-xl cursor-pointer" style="border: 1px solid var(--border-subtle); background: var(--input-bg);">
                                    </div>
                                </div>
                                <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm mt-2">Save Customizations</button>
                            </div>
                        </div>

                        <div class="card-premium overflow-hidden" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                                <span class="flex items-center gap-3 text-sm font-semibold" style="color: var(--text-primary);">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.15);"><i class="fas fa-check-circle text-emerald-400 text-[10px]"></i></div>
                                    Verified badge
                                </span>
                                <i class="fas fa-chevron-down text-xs transition-transform duration-300" style="color: var(--text-faint);" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open" x-cloak class="px-5 pb-5 pt-4" style="border-top: 1px solid var(--border-subtle);">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="hidden" name="verified_badge" value="0">
                                    <input type="checkbox" name="verified_badge" value="1" {{ ($bs['verified_badge'] ?? false) ? 'checked' : '' }} class="rounded text-purple-500 focus:ring-purple-500/40 w-5 h-5" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                                    <span class="text-sm" style="color: var(--text-muted);">Show verified badge on your biolink page</span>
                                </label>
                                <p class="text-xs mt-2" style="color: var(--text-faint);">Display a verified checkmark next to your name.</p>
                            </div>
                        </div>

                        <div class="card-premium overflow-hidden" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                                <span class="flex items-center gap-3 text-sm font-semibold" style="color: var(--text-primary);">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.15);"><i class="fas fa-star text-amber-400 text-[10px]"></i></div>
                                    Branding
                                </span>
                                <i class="fas fa-chevron-down text-xs transition-transform duration-300" style="color: var(--text-faint);" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open" x-cloak class="px-5 pb-5 pt-4" style="border-top: 1px solid var(--border-subtle);">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="hidden" name="branding_hidden" value="0">
                                    <input type="checkbox" name="branding_hidden" value="1" {{ ($bs['branding_hidden'] ?? false) ? 'checked' : '' }} class="rounded text-purple-500 focus:ring-purple-500/40 w-5 h-5" style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                                    <span class="text-sm" style="color: var(--text-muted);">Hide "Powered by 1INME" branding</span>
                                </label>
                            </div>
                        </div>
                    </form>

                    <div class="card-premium overflow-hidden" x-data="{ open: false }">
                        <button @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                            <span class="flex items-center gap-3 text-sm font-semibold" style="color: var(--text-primary);">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: rgba(6,182,212,0.1); border: 1px solid rgba(6,182,212,0.15);"><i class="fas fa-bullseye text-cyan-400 text-[10px]"></i></div>
                                Pixels
                            </span>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-300" style="color: var(--text-faint);" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-cloak class="px-5 pb-5 pt-4" style="border-top: 1px solid var(--border-subtle);">
                            @if($link->pixels->count())
                                <div class="flex flex-wrap gap-2">
                                    @foreach($link->pixels as $pixel)
                                    <span class="badge" style="background: rgba(139,92,246,0.08); color: var(--accent-light); border: 1px solid rgba(139,92,246,0.12);">{{ $pixel->name }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs" style="color: var(--text-faint);">No tracking pixels attached. <a href="{{ route('user.links.edit', $link) }}" class="text-purple-400 hover:underline">Add via link settings</a></p>
                            @endif
                        </div>
                    </div>

                    <div class="card-premium overflow-hidden" x-data="{ open: false }">
                        <button @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                            <span class="flex items-center gap-3 text-sm font-semibold" style="color: var(--text-primary);">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.15);"><i class="fas fa-tags text-violet-400 text-[10px]"></i></div>
                                UTM Parameters
                            </span>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-300" style="color: var(--text-faint);" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-cloak class="px-5 pb-5 pt-4" style="border-top: 1px solid var(--border-subtle);">
                            @if($link->utm_source || $link->utm_medium || $link->utm_campaign)
                                <div class="space-y-2 text-sm">
                                    @if($link->utm_source)<div class="flex justify-between"><span style="color: var(--text-faint);">Source</span><span style="color: var(--text-muted);">{{ $link->utm_source }}</span></div>@endif
                                    @if($link->utm_medium)<div class="flex justify-between"><span style="color: var(--text-faint);">Medium</span><span style="color: var(--text-muted);">{{ $link->utm_medium }}</span></div>@endif
                                    @if($link->utm_campaign)<div class="flex justify-between"><span style="color: var(--text-faint);">Campaign</span><span style="color: var(--text-muted);">{{ $link->utm_campaign }}</span></div>@endif
                                </div>
                            @else
                                <p class="text-xs" style="color: var(--text-faint);">No UTM parameters set. <a href="{{ route('user.links.edit', $link) }}" class="text-purple-400 hover:underline">Configure via link settings</a></p>
                            @endif
                        </div>
                    </div>

                    <div class="card-premium overflow-hidden" x-data="{ open: false }">
                        <button @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                            <span class="flex items-center gap-3 text-sm font-semibold" style="color: var(--text-primary);">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.15);"><i class="fas fa-search text-blue-400 text-[10px]"></i></div>
                                SEO
                            </span>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-300" style="color: var(--text-faint);" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-cloak class="px-5 pb-5 pt-4" style="border-top: 1px solid var(--border-subtle);">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between"><span style="color: var(--text-faint);">SEO Title</span><span style="color: var(--text-muted);">{{ $link->seo_title ?: 'Not set' }}</span></div>
                                <div class="flex justify-between"><span style="color: var(--text-faint);">Description</span><span class="truncate max-w-[200px]" style="color: var(--text-muted);">{{ $link->seo_description ?: 'Not set' }}</span></div>
                            </div>
                            <a href="{{ route('user.links.edit', $link) }}" class="inline-block text-xs text-purple-400 hover:underline mt-3">Edit SEO settings</a>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="activeTab === 'blocks'">
                <div class="space-y-2">
                    @forelse($blocks as $block)
                    <div class="card-premium overflow-hidden group"
                         data-block-id="{{ $block->id }}"
                         x-data="{ editing: false }">
                        <div class="px-5 py-3.5 flex items-center gap-3 hover:bg-white/[0.02] transition-colors">
                            <div class="cursor-move handle" style="color: var(--text-faint);">
                                <i class="fas fa-grip-vertical"></i>
                            </div>
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.15);">
                                <i class="fas {{ $blockTypes[$block->type]['icon'] ?? 'fa-cube' }} text-purple-400 text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ $blockTypes[$block->type]['label'] ?? ucfirst($block->type) }}</span>
                                    @if(!$block->is_active)
                                    <span class="badge" style="background: rgba(239,68,68,0.08); color: #f87171; border: 1px solid rgba(239,68,68,0.12);">hidden</span>
                                    @endif
                                </div>
                                <p class="text-xs truncate mt-0.5" style="color: var(--text-faint);">
                                    @if($block->type === 'link'){{ $block->settings['text'] ?? $block->settings['url'] ?? '' }}
                                    @elseif($block->type === 'heading'){{ $block->settings['text'] ?? '' }}
                                    @elseif($block->type === 'paragraph'){{ \Illuminate\Support\Str::limit($block->settings['text'] ?? '', 50) }}
                                    @elseif($block->type === 'socials'){{ count($block->settings['platforms'] ?? []) }} platforms
                                    @elseif($block->type === 'faq'){{ count($block->settings['items'] ?? []) }} items
                                    @elseif($block->type === 'cta_button'){{ $block->settings['text'] ?? '' }}
                                    @else{{ ucfirst($block->type) }} block
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="editing = !editing" class="p-2 rounded-lg hover:text-purple-400 hover:bg-purple-500/10 transition-all" style="color: var(--text-faint);" title="Edit">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                                <form method="POST" action="{{ route('user.links.blocks.toggle', [$link, $block]) }}">
                                    @csrf
                                    <button class="p-2 rounded-lg hover:text-amber-400 hover:bg-amber-500/10 transition-all" style="color: var(--text-faint);" title="{{ $block->is_active ? 'Hide' : 'Show' }}">
                                        <i class="fas {{ $block->is_active ? 'fa-eye' : 'fa-eye-slash' }} text-xs"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('user.links.blocks.destroy', [$link, $block]) }}" onsubmit="return confirm('Delete this block?')">
                                    @csrf @method('DELETE')
                                    <button class="p-2 rounded-lg hover:text-red-400 hover:bg-red-500/10 transition-all" style="color: var(--text-faint);" title="Delete">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div x-show="editing" x-cloak class="px-5 pb-5 pt-4" style="border-top: 1px solid var(--border-subtle);">
                            <form method="POST" action="{{ route('user.links.blocks.update', [$link, $block]) }}">
                                @csrf @method('PUT')
                                @include('user.links.partials.block-settings-form', ['block' => $block])
                                <div class="flex items-center gap-2 mt-4">
                                    <button type="submit" class="btn-primary text-sm py-2 px-5">Save</button>
                                    <button type="button" @click="editing = false" class="btn-ghost text-sm py-2 px-5">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @empty
                    <div class="card-premium p-12 text-center">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4 animate-pulse-glow" style="background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.12);">
                            <i class="fas fa-th-large text-xl text-purple-400"></i>
                        </div>
                        <p class="text-sm mb-4" style="color: var(--text-muted);">No blocks yet. Add your first block to start building your biolink page.</p>
                        <button @click="showAddBlock = true" class="btn-primary text-sm py-2.5 px-5" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class="fas fa-plus text-xs"></i> Add block
                        </button>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="lg:col-span-5 xl:col-span-5">
            <div class="sticky top-6 flex justify-center">
                <div class="relative" style="width: 320px;">
                    <div class="absolute -inset-2 rounded-[3.5rem] animate-pulse-glow" style="background: linear-gradient(135deg, rgba(139,92,246,0.1), rgba(168,85,247,0.05)); filter: blur(20px);"></div>
                    <div class="absolute -inset-1 rounded-[3rem]" style="background: linear-gradient(180deg, rgba(139,92,246,0.15), rgba(255,255,255,0.05), rgba(139,92,246,0.1)); filter: blur(1px);"></div>
                    <div class="relative bg-black rounded-[2.5rem] p-2 shadow-2xl" style="border: 3px solid rgba(255,255,255,0.08); box-shadow: 0 24px 80px rgba(0,0,0,0.6), 0 0 40px rgba(139,92,246,0.08);">
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-28 h-6 bg-black rounded-b-2xl z-10 flex items-center justify-center">
                            <div class="w-16 h-3.5 rounded-full" style="background: rgba(255,255,255,0.05);"></div>
                        </div>

                        <div class="rounded-[2rem] overflow-hidden" style="height: 580px; background: var(--bg-body);">
                            <iframe id="previewFrame" src="{{ url('/' . $link->alias) }}" class="w-full h-full border-0 rounded-[2rem]" style="transform-origin: top left;"></iframe>
                        </div>

                        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-28 h-1 rounded-full" style="background: rgba(255,255,255,0.08);"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showAddBlock" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-md" style="background: var(--overlay-bg);" @click.self="showAddBlock = false">
        <div x-transition class="w-full max-w-lg mx-4 rounded-2xl max-h-[80vh] overflow-hidden flex flex-col card-premium" style="background: var(--bg-sidebar); backdrop-filter: blur(40px) saturate(1.4);">
            <div class="p-5 flex items-center justify-between flex-shrink-0" style="border-bottom: 1px solid var(--border-subtle);">
                <h3 class="text-lg font-bold gradient-text">Add Block</h3>
                <button @click="showAddBlock = false" class="p-1.5 rounded-lg hover:bg-white/[0.05] transition-all" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-5 overflow-y-auto flex-1">
                @foreach($blockCategories as $catKey => $catLabel)
                <div class="mb-6 last:mb-0">
                    <h4 class="text-[10px] font-bold uppercase tracking-[0.15em] mb-3" style="color: var(--text-faint);">{{ $catLabel }}</h4>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($blockTypes as $typeKey => $typeInfo)
                            @if($typeInfo['category'] === $catKey)
                            <form method="POST" action="{{ route('user.links.blocks.store', $link) }}">
                                @csrf
                                <input type="hidden" name="type" value="{{ $typeKey }}">
                                <button type="submit" class="w-full flex items-center gap-3 p-3 rounded-xl transition-all text-left group hover:translate-x-1" style="border: 1px solid var(--border-subtle); background: transparent;" onmouseover="this.style.borderColor='rgba(139,92,246,0.3)';this.style.background='rgba(139,92,246,0.04)'" onmouseout="this.style.borderColor='var(--border-subtle)';this.style.background='transparent'">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-all duration-300" style="background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.15);">
                                        <i class="fas {{ $typeInfo['icon'] }} text-purple-400 text-sm"></i>
                                    </div>
                                    <span class="text-sm group-hover:text-purple-400 transition-colors" style="color: var(--text-muted);">{{ $typeInfo['label'] }}</span>
                                </button>
                            </form>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script>
function biolinkEditor() {
    return {
        activeTab: 'settings',
        showAddBlock: false,
    }
}
</script>
@endsection
