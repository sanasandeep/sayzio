@extends('user.layouts.app')
@section('title', 'Biolink Settings - ' . ($link->title ?: $link->alias))

@section('content')
<div x-data="biolinkEditor()" class="max-w-7xl mx-auto">
    <div class="flex items-center gap-2 text-sm text-white/30 mb-2">
        <a href="{{ route('user.links.index') }}" class="hover:text-white/60 transition-colors">Links</a>
        <i class="fas fa-chevron-right text-[8px]"></i>
        <span class="text-white/50">Biolink Settings</span>
    </div>

    <div class="flex items-center justify-between mb-1">
        <div>
            <h1 class="text-2xl font-bold text-white">{{ $link->alias }}</h1>
            <div class="flex items-center gap-2 mt-1" x-data="{ copied: false }">
                <span class="inline-flex items-center gap-1.5 text-sm">
                    <span class="w-2 h-2 rounded-full {{ $link->is_active ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                    <span class="text-white/40">Your link is</span>
                    <span class="text-purple-400">{{ $link->getShortUrl() }}</span>
                </span>
                <button @click="navigator.clipboard.writeText('{{ $link->getShortUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-white/20 hover:text-purple-400 transition-colors">
                    <i x-show="!copied" class="fas fa-copy text-xs"></i>
                    <i x-show="copied" x-cloak class="fas fa-check text-emerald-400 text-xs"></i>
                </button>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('user.links.toggle-active', $link) }}" method="POST">
                @csrf
                <button class="p-2.5 rounded-xl border border-white/10 text-white/40 hover:bg-white/5 transition-all" title="{{ $link->is_active ? 'Deactivate' : 'Activate' }}">
                    <i class="fas {{ $link->is_active ? 'fa-toggle-on text-emerald-400' : 'fa-toggle-off' }}"></i>
                </button>
            </form>
            <a href="{{ url('/' . $link->alias) }}" target="_blank" class="p-2.5 rounded-xl border border-white/10 text-white/40 hover:bg-white/5 transition-all" title="Open in new tab">
                <i class="fas fa-external-link-alt text-sm"></i>
            </a>
            <a href="{{ route('user.links.qrcode', $link) }}" class="p-2.5 rounded-xl border border-white/10 text-white/40 hover:bg-white/5 transition-all" title="QR Code">
                <i class="fas fa-qrcode text-sm"></i>
            </a>
            <a href="{{ route('user.links.show', $link) }}" class="p-2.5 rounded-xl border border-white/10 text-white/40 hover:bg-white/5 transition-all" title="Analytics">
                <i class="fas fa-chart-bar text-sm"></i>
            </a>
        </div>
    </div>

    <div class="flex items-center gap-3 mt-5 mb-6">
        <button @click="activeTab = 'settings'" :class="activeTab === 'settings' ? 'bg-purple-600 text-white shadow-lg shadow-purple-500/20' : 'bg-white/5 text-white/50 hover:bg-white/10 border border-white/10'" class="px-5 py-2.5 rounded-xl text-sm font-medium transition-all flex items-center gap-2">
            <i class="fas fa-cog text-xs"></i> Settings
        </button>
        <button @click="activeTab = 'blocks'" :class="activeTab === 'blocks' ? 'bg-purple-600 text-white shadow-lg shadow-purple-500/20' : 'bg-white/5 text-white/50 hover:bg-white/10 border border-white/10'" class="px-5 py-2.5 rounded-xl text-sm font-medium transition-all flex items-center gap-2">
            <i class="fas fa-th-large text-xs"></i> Blocks
        </button>
        <button @click="showAddBlock = true" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl transition-all hover:shadow-lg hover:shadow-emerald-500/20 flex items-center gap-2 ml-1">
            <i class="fas fa-plus text-xs"></i> Add block
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7 xl:col-span-7">
            <div x-show="activeTab === 'settings'" x-cloak>
                <div class="space-y-2">
                    @php $bs = $link->settings['biolink'] ?? []; @endphp

                    <div class="glass rounded-2xl overflow-hidden" x-data="{ open: true }">
                        <button @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                            <span class="flex items-center gap-3 text-sm font-medium text-white">
                                <i class="fas fa-link text-purple-400 w-5 text-center"></i> Short URL
                            </span>
                            <i class="fas fa-chevron-down text-xs text-white/20 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-cloak class="px-5 pb-5 border-t border-white/5 pt-4">
                            <div class="flex items-center bg-white/5 border border-white/10 rounded-xl overflow-hidden">
                                <span class="bg-white/5 px-3 py-2.5 text-sm text-white/30 border-r border-white/10">{{ request()->getHost() }}/</span>
                                <span class="flex-1 px-3 py-2.5 text-sm text-white">{{ $link->alias }}</span>
                            </div>
                            <p class="text-xs text-white/20 mt-2">This is your unique biolink URL. Share it with your audience.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('user.links.page-settings', $link) }}" enctype="multipart/form-data">
                        @csrf

                        <div class="glass rounded-2xl overflow-hidden" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                                <span class="flex items-center gap-3 text-sm font-medium text-white">
                                    <i class="fas fa-palette text-pink-400 w-5 text-center"></i> Customizations
                                </span>
                                <i class="fas fa-chevron-down text-xs text-white/20 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open" x-cloak class="px-5 pb-5 border-t border-white/5 pt-4 space-y-4">
                                <div>
                                    <label class="block text-xs text-white/40 mb-1.5">Page Title</label>
                                    <input type="text" name="biolink_title" value="{{ $bs['biolink_title'] ?? $link->title }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-purple-500/40 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs text-white/40 mb-1.5">Description</label>
                                    <textarea name="biolink_description" rows="2" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-purple-500/40 outline-none">{{ $bs['biolink_description'] ?? '' }}</textarea>
                                </div>
                                <div>
                                    <label class="block text-xs text-white/40 mb-1.5">Background Type</label>
                                    <select name="background_type" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
                                        <option value="color" {{ ($bs['background_type'] ?? '') === 'color' ? 'selected' : '' }} class="bg-[#0d0818]">Solid Color</option>
                                        <option value="gradient" {{ ($bs['background_type'] ?? '') === 'gradient' ? 'selected' : '' }} class="bg-[#0d0818]">Gradient</option>
                                        <option value="image" {{ ($bs['background_type'] ?? '') === 'image' ? 'selected' : '' }} class="bg-[#0d0818]">Image</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-white/40 mb-1.5">Background Color</label>
                                        <input type="color" name="background_color" value="{{ $bs['background_color'] ?? '#0a0612' }}" class="w-full h-10 rounded-xl border border-white/10 bg-white/5 cursor-pointer">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-white/40 mb-1.5">Font Color</label>
                                        <input type="color" name="font_color" value="{{ $bs['font_color'] ?? '#ffffff' }}" class="w-full h-10 rounded-xl border border-white/10 bg-white/5 cursor-pointer">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-white/40 mb-1.5">Gradient CSS</label>
                                    <input type="text" name="background_gradient" value="{{ $bs['background_gradient'] ?? 'linear-gradient(135deg, #0a0612 0%, #1a0533 50%, #0a0612 100%)' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-purple-500/40 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs text-white/40 mb-1.5">Background Image</label>
                                    <input type="file" name="background_image" accept="image/*" class="w-full text-sm text-white/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:bg-white/10 file:text-white/60 hover:file:bg-white/15">
                                </div>
                                <div>
                                    <label class="block text-xs text-white/40 mb-1.5">Font Family</label>
                                    <select name="font_family" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
                                        @foreach(['Space Grotesk', 'Inter', 'Poppins', 'Roboto', 'Playfair Display', 'Montserrat'] as $font)
                                        <option value="{{ $font }}" {{ ($bs['font_family'] ?? '') === $font ? 'selected' : '' }} class="bg-[#0d0818]">{{ $font }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-white/40 mb-1.5">Button Style</label>
                                    <select name="button_style" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
                                        @foreach(['rounded' => 'Rounded', 'pill' => 'Pill', 'square' => 'Square', 'outline' => 'Outline', 'shadow' => 'Shadow'] as $val => $label)
                                        <option value="{{ $val }}" {{ ($bs['button_style'] ?? '') === $val ? 'selected' : '' }} class="bg-[#0d0818]">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-white/40 mb-1.5">Button Color</label>
                                        <input type="color" name="button_color" value="{{ $bs['button_color'] ?? '#7c3aed' }}" class="w-full h-10 rounded-xl border border-white/10 bg-white/5 cursor-pointer">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-white/40 mb-1.5">Button Text Color</label>
                                        <input type="color" name="button_text_color" value="{{ $bs['button_text_color'] ?? '#ffffff' }}" class="w-full h-10 rounded-xl border border-white/10 bg-white/5 cursor-pointer">
                                    </div>
                                </div>
                                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2.5 rounded-xl text-sm font-medium transition-all mt-2">Save Customizations</button>
                            </div>
                        </div>

                        <div class="glass rounded-2xl overflow-hidden" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                                <span class="flex items-center gap-3 text-sm font-medium text-white">
                                    <i class="fas fa-check-circle text-emerald-400 w-5 text-center"></i> Verified badge
                                </span>
                                <i class="fas fa-chevron-down text-xs text-white/20 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open" x-cloak class="px-5 pb-5 border-t border-white/5 pt-4">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="hidden" name="verified_badge" value="0">
                                    <input type="checkbox" name="verified_badge" value="1" {{ ($bs['verified_badge'] ?? false) ? 'checked' : '' }} class="rounded bg-white/5 border-white/20 text-purple-500 focus:ring-purple-500/40 w-5 h-5">
                                    <span class="text-sm text-white/60">Show verified badge on your biolink page</span>
                                </label>
                                <p class="text-xs text-white/20 mt-2">Display a verified checkmark next to your name.</p>
                            </div>
                        </div>

                        <div class="glass rounded-2xl overflow-hidden" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                                <span class="flex items-center gap-3 text-sm font-medium text-white">
                                    <i class="fas fa-star text-amber-400 w-5 text-center"></i> Branding
                                </span>
                                <i class="fas fa-chevron-down text-xs text-white/20 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open" x-cloak class="px-5 pb-5 border-t border-white/5 pt-4">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="hidden" name="branding_hidden" value="0">
                                    <input type="checkbox" name="branding_hidden" value="1" {{ ($bs['branding_hidden'] ?? false) ? 'checked' : '' }} class="rounded bg-white/5 border-white/20 text-purple-500 focus:ring-purple-500/40 w-5 h-5">
                                    <span class="text-sm text-white/60">Hide "Powered by 1INME" branding</span>
                                </label>
                            </div>
                        </div>
                    </form>

                    <div class="glass rounded-2xl overflow-hidden" x-data="{ open: false }">
                        <button @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                            <span class="flex items-center gap-3 text-sm font-medium text-white">
                                <i class="fas fa-bullseye text-cyan-400 w-5 text-center"></i> Pixels
                            </span>
                            <i class="fas fa-chevron-down text-xs text-white/20 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-cloak class="px-5 pb-5 border-t border-white/5 pt-4">
                            @if($link->pixels->count())
                                <div class="flex flex-wrap gap-2">
                                    @foreach($link->pixels as $pixel)
                                    <span class="bg-white/5 text-white/50 px-3 py-1.5 rounded-lg text-xs border border-white/10">{{ $pixel->name }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-white/20">No tracking pixels attached. <a href="{{ route('user.links.edit', $link) }}" class="text-purple-400 hover:underline">Add via link settings</a></p>
                            @endif
                        </div>
                    </div>

                    <div class="glass rounded-2xl overflow-hidden" x-data="{ open: false }">
                        <button @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                            <span class="flex items-center gap-3 text-sm font-medium text-white">
                                <i class="fas fa-tags text-violet-400 w-5 text-center"></i> UTM Parameters
                            </span>
                            <i class="fas fa-chevron-down text-xs text-white/20 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-cloak class="px-5 pb-5 border-t border-white/5 pt-4">
                            @if($link->utm_source || $link->utm_medium || $link->utm_campaign)
                                <div class="space-y-2 text-sm">
                                    @if($link->utm_source)<div class="flex justify-between"><span class="text-white/30">Source</span><span class="text-white/60">{{ $link->utm_source }}</span></div>@endif
                                    @if($link->utm_medium)<div class="flex justify-between"><span class="text-white/30">Medium</span><span class="text-white/60">{{ $link->utm_medium }}</span></div>@endif
                                    @if($link->utm_campaign)<div class="flex justify-between"><span class="text-white/30">Campaign</span><span class="text-white/60">{{ $link->utm_campaign }}</span></div>@endif
                                </div>
                            @else
                                <p class="text-xs text-white/20">No UTM parameters set. <a href="{{ route('user.links.edit', $link) }}" class="text-purple-400 hover:underline">Configure via link settings</a></p>
                            @endif
                        </div>
                    </div>

                    <div class="glass rounded-2xl overflow-hidden" x-data="{ open: false }">
                        <button @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-white/[0.02] transition-colors">
                            <span class="flex items-center gap-3 text-sm font-medium text-white">
                                <i class="fas fa-search text-blue-400 w-5 text-center"></i> SEO
                            </span>
                            <i class="fas fa-chevron-down text-xs text-white/20 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-cloak class="px-5 pb-5 border-t border-white/5 pt-4">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between"><span class="text-white/30">SEO Title</span><span class="text-white/60">{{ $link->seo_title ?: 'Not set' }}</span></div>
                                <div class="flex justify-between"><span class="text-white/30">Description</span><span class="text-white/60 truncate max-w-[200px]">{{ $link->seo_description ?: 'Not set' }}</span></div>
                            </div>
                            <a href="{{ route('user.links.edit', $link) }}" class="inline-block text-xs text-purple-400 hover:underline mt-3">Edit SEO settings</a>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="activeTab === 'blocks'">
                <div class="space-y-2">
                    @forelse($blocks as $block)
                    <div class="glass rounded-2xl overflow-hidden group"
                         data-block-id="{{ $block->id }}"
                         x-data="{ editing: false }">
                        <div class="px-5 py-3.5 flex items-center gap-3 hover:bg-white/[0.02] transition-colors">
                            <div class="text-white/15 cursor-move handle">
                                <i class="fas fa-grip-vertical"></i>
                            </div>
                            <div class="w-9 h-9 rounded-lg bg-purple-500/10 border border-purple-500/20 flex items-center justify-center flex-shrink-0">
                                <i class="fas {{ $blockTypes[$block->type]['icon'] ?? 'fa-cube' }} text-purple-400 text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-white">{{ $blockTypes[$block->type]['label'] ?? ucfirst($block->type) }}</span>
                                    @if(!$block->is_active)
                                    <span class="text-[10px] bg-red-500/10 text-red-400 px-1.5 py-0.5 rounded border border-red-500/20">hidden</span>
                                    @endif
                                </div>
                                <p class="text-xs text-white/25 truncate mt-0.5">
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
                                <button @click="editing = !editing" class="p-2 text-white/25 hover:text-purple-400 rounded-lg hover:bg-purple-500/10 transition-all" title="Edit">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                                <form method="POST" action="{{ route('user.links.blocks.toggle', [$link, $block]) }}">
                                    @csrf
                                    <button class="p-2 text-white/25 hover:text-amber-400 rounded-lg hover:bg-amber-500/10 transition-all" title="{{ $block->is_active ? 'Hide' : 'Show' }}">
                                        <i class="fas {{ $block->is_active ? 'fa-eye' : 'fa-eye-slash' }} text-xs"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('user.links.blocks.destroy', [$link, $block]) }}" onsubmit="return confirm('Delete this block?')">
                                    @csrf @method('DELETE')
                                    <button class="p-2 text-white/25 hover:text-red-400 rounded-lg hover:bg-red-500/10 transition-all" title="Delete">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div x-show="editing" x-cloak class="px-5 pb-5 border-t border-white/5 pt-4">
                            <form method="POST" action="{{ route('user.links.blocks.update', [$link, $block]) }}">
                                @csrf @method('PUT')
                                @include('user.links.partials.block-settings-form', ['block' => $block])
                                <div class="flex items-center gap-2 mt-4">
                                    <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm rounded-xl transition-all">Save</button>
                                    <button type="button" @click="editing = false" class="px-5 py-2 text-sm text-white/30 hover:text-white hover:bg-white/5 rounded-xl transition-all">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @empty
                    <div class="glass rounded-2xl p-12 text-center">
                        <div class="w-14 h-14 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-th-large text-white/15 text-xl"></i>
                        </div>
                        <p class="text-white/30 text-sm mb-4">No blocks yet. Add your first block to start building your biolink page.</p>
                        <button @click="showAddBlock = true" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all">
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
                    <div class="absolute -inset-1 bg-gradient-to-b from-white/10 via-white/5 to-white/10 rounded-[3rem] blur-sm"></div>
                    <div class="relative bg-black rounded-[2.5rem] p-2 shadow-2xl shadow-black/50" style="border: 3px solid rgba(255,255,255,0.1);">
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-28 h-6 bg-black rounded-b-2xl z-10 flex items-center justify-center">
                            <div class="w-16 h-3.5 bg-white/5 rounded-full"></div>
                        </div>

                        <div class="rounded-[2rem] overflow-hidden bg-[#0a0612]" style="height: 580px;">
                            <iframe id="previewFrame" src="{{ url('/' . $link->alias) }}" class="w-full h-full border-0 rounded-[2rem]" style="transform-origin: top left;"></iframe>
                        </div>

                        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-28 h-1 bg-white/10 rounded-full"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showAddBlock" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="showAddBlock = false">
        <div x-transition class="w-full max-w-lg mx-4 rounded-2xl max-h-[80vh] overflow-hidden flex flex-col" style="background: rgba(15,10,26,0.98); backdrop-filter: blur(30px); border: 1px solid rgba(255,255,255,0.08);">
            <div class="p-5 border-b border-white/5 flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold text-white">Add Block</h3>
                <button @click="showAddBlock = false" class="text-white/30 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-5 overflow-y-auto flex-1">
                @foreach($blockCategories as $catKey => $catLabel)
                <div class="mb-6 last:mb-0">
                    <h4 class="text-[11px] font-semibold uppercase text-white/20 tracking-wider mb-3">{{ $catLabel }}</h4>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($blockTypes as $typeKey => $typeInfo)
                            @if($typeInfo['category'] === $catKey)
                            <form method="POST" action="{{ route('user.links.blocks.store', $link) }}">
                                @csrf
                                <input type="hidden" name="type" value="{{ $typeKey }}">
                                <button type="submit" class="w-full flex items-center gap-3 p-3 rounded-xl border border-white/5 hover:border-purple-500/30 hover:bg-purple-500/5 transition-all text-left group">
                                    <div class="w-9 h-9 rounded-lg bg-purple-500/10 border border-purple-500/20 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
                                        <i class="fas {{ $typeInfo['icon'] }} text-purple-400 text-sm"></i>
                                    </div>
                                    <span class="text-sm text-white/50 group-hover:text-white transition-colors">{{ $typeInfo['label'] }}</span>
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
