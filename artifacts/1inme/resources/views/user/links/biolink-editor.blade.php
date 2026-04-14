@extends('user.layouts.app')
@section('title', 'Edit Biolink - ' . ($link->title ?: $link->alias))

@section('content')
<div x-data="biolinkEditor()" class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('user.links.show', $link) }}" class="text-white/30 hover:text-white transition-colors"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1 class="text-2xl font-bold text-white">Biolink Editor</h1>
                <p class="text-white/40 text-sm mt-0.5">{{ $link->getShortUrl() }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ url('/r/' . $link->alias) }}" target="_blank" class="px-4 py-2 text-sm text-white/50 hover:text-white border border-white/10 rounded-xl hover:bg-white/5 transition-all">
                <i class="fas fa-external-link-alt text-xs mr-1.5"></i> Preview
            </a>
            <button @click="showSettings = true" class="px-4 py-2 text-sm text-white/50 hover:text-white border border-white/10 rounded-xl hover:bg-white/5 transition-all">
                <i class="fas fa-cog text-xs mr-1.5"></i> Page Settings
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3">
            <div class="glass rounded-2xl p-5 mb-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-white">Blocks</h2>
                    <button @click="showAddBlock = true" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-xl transition-all hover:shadow-lg hover:shadow-purple-500/20">
                        <i class="fas fa-plus text-xs mr-1.5"></i> Add Block
                    </button>
                </div>

                <div class="space-y-2" x-ref="blockList">
                    @forelse($blocks as $block)
                    <div class="glass rounded-xl p-4 group cursor-move hover:bg-white/[0.06] transition-all"
                         data-block-id="{{ $block->id }}"
                         x-data="{ editing: false, blockSettings: {{ json_encode($block->settings) }} }">
                        <div class="flex items-center gap-3">
                            <div class="text-white/20 cursor-move handle">
                                <i class="fas fa-grip-vertical"></i>
                            </div>
                            <div class="w-9 h-9 rounded-lg bg-purple-500/10 border border-purple-500/20 flex items-center justify-center flex-shrink-0">
                                <i class="fas {{ $blockTypes[$block->type]['icon'] ?? 'fa-cube' }} text-purple-400 text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-white">{{ $blockTypes[$block->type]['label'] ?? ucfirst($block->type) }}</span>
                                    @if(!$block->is_active)
                                    <span class="text-[10px] bg-white/10 text-white/30 px-1.5 py-0.5 rounded">hidden</span>
                                    @endif
                                </div>
                                <p class="text-xs text-white/30 truncate">
                                    @if($block->type === 'link'){{ $block->settings['text'] ?? $block->settings['url'] ?? '' }}
                                    @elseif($block->type === 'heading'){{ $block->settings['text'] ?? '' }}
                                    @elseif($block->type === 'paragraph'){{ \Illuminate\Support\Str::limit($block->settings['text'] ?? '', 60) }}
                                    @elseif($block->type === 'image'){{ $block->settings['alt'] ?? 'Image' }}
                                    @elseif($block->type === 'socials'){{ count($block->settings['platforms'] ?? []) }} platforms
                                    @elseif($block->type === 'faq'){{ count($block->settings['items'] ?? []) }} items
                                    @else{{ ucfirst($block->type) }} block
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="editing = !editing" class="p-2 text-white/30 hover:text-purple-400 rounded-lg hover:bg-purple-500/10 transition-all" title="Edit">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                                <form method="POST" action="{{ route('user.links.blocks.toggle', [$link, $block]) }}">
                                    @csrf
                                    <button class="p-2 text-white/30 hover:text-amber-400 rounded-lg hover:bg-amber-500/10 transition-all" title="{{ $block->is_active ? 'Hide' : 'Show' }}">
                                        <i class="fas {{ $block->is_active ? 'fa-eye' : 'fa-eye-slash' }} text-xs"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('user.links.blocks.destroy', [$link, $block]) }}" onsubmit="return confirm('Delete this block?')">
                                    @csrf @method('DELETE')
                                    <button class="p-2 text-white/30 hover:text-red-400 rounded-lg hover:bg-red-500/10 transition-all" title="Delete">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div x-show="editing" x-cloak class="mt-4 pt-4 border-t border-white/5">
                            <form method="POST" action="{{ route('user.links.blocks.update', [$link, $block]) }}">
                                @csrf @method('PUT')
                                @include('user.links.partials.block-settings-form', ['block' => $block])
                                <div class="flex items-center gap-2 mt-4">
                                    <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm rounded-xl transition-all">Save</button>
                                    <button type="button" @click="editing = false" class="px-4 py-2 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-12">
                        <div class="w-14 h-14 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-cube text-white/20 text-xl"></i>
                        </div>
                        <p class="text-white/40 text-sm mb-4">No blocks yet. Add your first block to get started.</p>
                        <button @click="showAddBlock = true" class="inline-flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all">
                            <i class="fas fa-plus text-xs"></i> Add Block
                        </button>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="sticky top-6">
                <div class="glass rounded-2xl overflow-hidden" style="height: 600px;">
                    <div class="bg-white/5 border-b border-white/5 px-4 py-2 flex items-center gap-2">
                        <div class="flex gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-full bg-red-400/40"></div>
                            <div class="w-2.5 h-2.5 rounded-full bg-amber-400/40"></div>
                            <div class="w-2.5 h-2.5 rounded-full bg-emerald-400/40"></div>
                        </div>
                        <span class="text-[10px] text-white/20 ml-2">{{ $link->getShortUrl() }}</span>
                    </div>
                    <iframe src="{{ url('/r/' . $link->alias) }}" class="w-full" style="height: calc(100% - 36px); border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showAddBlock" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="showAddBlock = false">
        <div class="w-full max-w-lg mx-4 rounded-2xl max-h-[80vh] overflow-hidden flex flex-col" style="background: rgba(15,10,26,0.98); backdrop-filter: blur(30px); border: 1px solid rgba(255,255,255,0.08);">
            <div class="p-5 border-b border-white/5 flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold text-white">Add Block</h3>
                <button @click="showAddBlock = false" class="text-white/30 hover:text-white"><i class="fas fa-times"></i></button>
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
                                    <span class="text-sm text-white/60 group-hover:text-white transition-colors">{{ $typeInfo['label'] }}</span>
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

    <div x-show="showSettings" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="showSettings = false">
        <div class="w-full max-w-lg mx-4 rounded-2xl max-h-[80vh] overflow-hidden flex flex-col" style="background: rgba(15,10,26,0.98); backdrop-filter: blur(30px); border: 1px solid rgba(255,255,255,0.08);">
            <div class="p-5 border-b border-white/5 flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-semibold text-white">Page Settings</h3>
                <button @click="showSettings = false" class="text-white/30 hover:text-white"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="{{ route('user.links.page-settings', $link) }}" enctype="multipart/form-data" class="overflow-y-auto flex-1">
                @csrf
                <div class="p-5 space-y-4">
                    @php $bs = $link->settings['biolink'] ?? []; @endphp
                    <div>
                        <label class="block text-sm text-white/60 mb-1.5">Page Title</label>
                        <input type="text" name="biolink_title" value="{{ $bs['biolink_title'] ?? $link->title }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-purple-500/40 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm text-white/60 mb-1.5">Description</label>
                        <textarea name="biolink_description" rows="2" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-purple-500/40 outline-none">{{ $bs['biolink_description'] ?? '' }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm text-white/60 mb-1.5">Background Type</label>
                        <select name="background_type" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
                            <option value="color" {{ ($bs['background_type'] ?? '') === 'color' ? 'selected' : '' }} class="bg-[#1a1025]">Solid Color</option>
                            <option value="gradient" {{ ($bs['background_type'] ?? '') === 'gradient' ? 'selected' : '' }} class="bg-[#1a1025]">Gradient</option>
                            <option value="image" {{ ($bs['background_type'] ?? '') === 'image' ? 'selected' : '' }} class="bg-[#1a1025]">Image</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-white/60 mb-1.5">Background Color</label>
                            <input type="color" name="background_color" value="{{ $bs['background_color'] ?? '#0f0a1a' }}" class="w-full h-10 rounded-xl border border-white/10 bg-white/5 cursor-pointer">
                        </div>
                        <div>
                            <label class="block text-sm text-white/60 mb-1.5">Font Color</label>
                            <input type="color" name="font_color" value="{{ $bs['font_color'] ?? '#ffffff' }}" class="w-full h-10 rounded-xl border border-white/10 bg-white/5 cursor-pointer">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm text-white/60 mb-1.5">Gradient (CSS)</label>
                        <input type="text" name="background_gradient" value="{{ $bs['background_gradient'] ?? 'linear-gradient(135deg, #0f0a1a 0%, #1a0533 50%, #0f0a1a 100%)' }}" placeholder="linear-gradient(...)" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-purple-500/40 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm text-white/60 mb-1.5">Background Image</label>
                        <input type="file" name="background_image" accept="image/*" class="w-full text-sm text-white/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:bg-white/10 file:text-white/60 hover:file:bg-white/15">
                    </div>
                    <div>
                        <label class="block text-sm text-white/60 mb-1.5">Font</label>
                        <select name="font_family" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
                            <option value="Space Grotesk" {{ ($bs['font_family'] ?? '') === 'Space Grotesk' ? 'selected' : '' }} class="bg-[#1a1025]">Space Grotesk</option>
                            <option value="Inter" {{ ($bs['font_family'] ?? '') === 'Inter' ? 'selected' : '' }} class="bg-[#1a1025]">Inter</option>
                            <option value="Poppins" {{ ($bs['font_family'] ?? '') === 'Poppins' ? 'selected' : '' }} class="bg-[#1a1025]">Poppins</option>
                            <option value="Roboto" {{ ($bs['font_family'] ?? '') === 'Roboto' ? 'selected' : '' }} class="bg-[#1a1025]">Roboto</option>
                            <option value="Playfair Display" {{ ($bs['font_family'] ?? '') === 'Playfair Display' ? 'selected' : '' }} class="bg-[#1a1025]">Playfair Display</option>
                            <option value="Montserrat" {{ ($bs['font_family'] ?? '') === 'Montserrat' ? 'selected' : '' }} class="bg-[#1a1025]">Montserrat</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-white/60 mb-1.5">Button Color</label>
                            <input type="color" name="button_color" value="{{ $bs['button_color'] ?? '#7c3aed' }}" class="w-full h-10 rounded-xl border border-white/10 bg-white/5 cursor-pointer">
                        </div>
                        <div>
                            <label class="block text-sm text-white/60 mb-1.5">Button Text</label>
                            <input type="color" name="button_text_color" value="{{ $bs['button_text_color'] ?? '#ffffff' }}" class="w-full h-10 rounded-xl border border-white/10 bg-white/5 cursor-pointer">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm text-white/60 mb-1.5">Button Style</label>
                        <select name="button_style" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
                            <option value="rounded" {{ ($bs['button_style'] ?? '') === 'rounded' ? 'selected' : '' }} class="bg-[#1a1025]">Rounded</option>
                            <option value="pill" {{ ($bs['button_style'] ?? '') === 'pill' ? 'selected' : '' }} class="bg-[#1a1025]">Pill</option>
                            <option value="square" {{ ($bs['button_style'] ?? '') === 'square' ? 'selected' : '' }} class="bg-[#1a1025]">Square</option>
                            <option value="outline" {{ ($bs['button_style'] ?? '') === 'outline' ? 'selected' : '' }} class="bg-[#1a1025]">Outline</option>
                            <option value="shadow" {{ ($bs['button_style'] ?? '') === 'shadow' ? 'selected' : '' }} class="bg-[#1a1025]">Shadow</option>
                        </select>
                    </div>
                </div>
                <div class="p-5 border-t border-white/5">
                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-purple-500/20">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function biolinkEditor() {
    return {
        showAddBlock: false,
        showSettings: false,
    }
}
</script>
@endsection
