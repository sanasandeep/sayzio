@php $s = $block->settings ?? []; @endphp

@if($block->type === 'link')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Link Text</label>
        <input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">URL</label>
        <input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Icon (FontAwesome class)</label>
        <input type="text" name="settings[icon]" value="{{ $s['icon'] ?? '' }}" placeholder="fas fa-globe" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Thumbnail URL</label>
        <input type="text" name="settings[thumbnail]" value="{{ $s['thumbnail'] ?? '' }}" placeholder="https://..." class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
</div>

@elseif($block->type === 'heading')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Text</label>
        <input type="text" name="settings[text]" value="{{ $s['text'] ?? '' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-xs text-white/40 mb-1">Size</label>
            <select name="settings[size]" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
                <option value="h1" {{ ($s['size'] ?? '') === 'h1' ? 'selected' : '' }} class="bg-[#1a1025]">H1 - Large</option>
                <option value="h2" {{ ($s['size'] ?? '') === 'h2' ? 'selected' : '' }} class="bg-[#1a1025]">H2 - Medium</option>
                <option value="h3" {{ ($s['size'] ?? '') === 'h3' ? 'selected' : '' }} class="bg-[#1a1025]">H3 - Small</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-white/40 mb-1">Align</label>
            <select name="settings[align]" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
                <option value="left" {{ ($s['align'] ?? '') === 'left' ? 'selected' : '' }} class="bg-[#1a1025]">Left</option>
                <option value="center" {{ ($s['align'] ?? '') === 'center' ? 'selected' : '' }} class="bg-[#1a1025]">Center</option>
                <option value="right" {{ ($s['align'] ?? '') === 'right' ? 'selected' : '' }} class="bg-[#1a1025]">Right</option>
            </select>
        </div>
    </div>
</div>

@elseif($block->type === 'paragraph')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Text</label>
        <textarea name="settings[text]" rows="3" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">{{ $s['text'] ?? '' }}</textarea>
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Alignment</label>
        <select name="settings[align]" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
            <option value="left" {{ ($s['align'] ?? '') === 'left' ? 'selected' : '' }} class="bg-[#1a1025]">Left</option>
            <option value="center" {{ ($s['align'] ?? '') === 'center' ? 'selected' : '' }} class="bg-[#1a1025]">Center</option>
            <option value="right" {{ ($s['align'] ?? '') === 'right' ? 'selected' : '' }} class="bg-[#1a1025]">Right</option>
        </select>
    </div>
</div>

@elseif($block->type === 'image')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Image URL</label>
        <input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://..." class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Alt Text</label>
        <input type="text" name="settings[alt]" value="{{ $s['alt'] ?? '' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Link To (optional)</label>
        <input type="url" name="settings[link]" value="{{ $s['link'] ?? '' }}" placeholder="https://..." class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
</div>

@elseif($block->type === 'video')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Video URL (MP4, WebM)</label>
        <input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://..." class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
</div>

@elseif($block->type === 'audio')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Audio URL</label>
        <input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://..." class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Title</label>
        <input type="text" name="settings[title]" value="{{ $s['title'] ?? '' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
</div>

@elseif($block->type === 'divider')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Style</label>
        <select name="settings[style]" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
            <option value="solid" {{ ($s['style'] ?? '') === 'solid' ? 'selected' : '' }} class="bg-[#1a1025]">Solid</option>
            <option value="dashed" {{ ($s['style'] ?? '') === 'dashed' ? 'selected' : '' }} class="bg-[#1a1025]">Dashed</option>
            <option value="dotted" {{ ($s['style'] ?? '') === 'dotted' ? 'selected' : '' }} class="bg-[#1a1025]">Dotted</option>
        </select>
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Color</label>
        <input type="text" name="settings[color]" value="{{ $s['color'] ?? 'rgba(255,255,255,0.1)' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
</div>

@elseif($block->type === 'spacer')
<div>
    <label class="block text-xs text-white/40 mb-1">Height (px)</label>
    <input type="number" name="settings[height]" value="{{ $s['height'] ?? 20 }}" min="5" max="200" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
</div>

@elseif($block->type === 'avatar')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Image URL</label>
        <input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://..." class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-xs text-white/40 mb-1">Size (px)</label>
            <input type="number" name="settings[size]" value="{{ $s['size'] ?? 96 }}" min="32" max="256" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
        </div>
        <div class="flex items-end pb-1">
            <label class="flex items-center gap-2 text-sm text-white/60 cursor-pointer">
                <input type="hidden" name="settings[rounded]" value="0">
                <input type="checkbox" name="settings[rounded]" value="1" {{ ($s['rounded'] ?? true) ? 'checked' : '' }} class="rounded bg-white/5 border-white/20 text-purple-500 focus:ring-purple-500/40">
                Rounded
            </label>
        </div>
    </div>
</div>

@elseif($block->type === 'socials')
<div x-data="{ platforms: {{ json_encode($s['platforms'] ?? []) }} }">
    <label class="block text-xs text-white/40 mb-2">Social Platforms</label>
    <template x-for="(platform, index) in platforms" :key="index">
        <div class="flex items-center gap-2 mb-2">
            <select x-model="platforms[index].name" :name="'settings[platforms][' + index + '][name]'" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none w-32">
                <option value="instagram" class="bg-[#1a1025]">Instagram</option>
                <option value="twitter" class="bg-[#1a1025]">Twitter/X</option>
                <option value="facebook" class="bg-[#1a1025]">Facebook</option>
                <option value="tiktok" class="bg-[#1a1025]">TikTok</option>
                <option value="youtube" class="bg-[#1a1025]">YouTube</option>
                <option value="linkedin" class="bg-[#1a1025]">LinkedIn</option>
                <option value="github" class="bg-[#1a1025]">GitHub</option>
                <option value="discord" class="bg-[#1a1025]">Discord</option>
                <option value="telegram" class="bg-[#1a1025]">Telegram</option>
                <option value="whatsapp" class="bg-[#1a1025]">WhatsApp</option>
                <option value="snapchat" class="bg-[#1a1025]">Snapchat</option>
                <option value="pinterest" class="bg-[#1a1025]">Pinterest</option>
                <option value="twitch" class="bg-[#1a1025]">Twitch</option>
                <option value="dribbble" class="bg-[#1a1025]">Dribbble</option>
                <option value="website" class="bg-[#1a1025]">Website</option>
                <option value="email" class="bg-[#1a1025]">Email</option>
            </select>
            <input type="url" x-model="platforms[index].url" :name="'settings[platforms][' + index + '][url]'" placeholder="URL" class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
            <button type="button" @click="platforms.splice(index, 1)" class="p-2 text-red-400/60 hover:text-red-400"><i class="fas fa-times text-xs"></i></button>
        </div>
    </template>
    <button type="button" @click="platforms.push({name: 'instagram', url: ''})" class="text-xs text-purple-400 hover:text-purple-300 mt-1">
        <i class="fas fa-plus mr-1"></i> Add Platform
    </button>
</div>

@elseif($block->type === 'faq')
<div x-data="{ items: {{ json_encode($s['items'] ?? [['question' => '', 'answer' => '']]) }} }">
    <label class="block text-xs text-white/40 mb-2">FAQ Items</label>
    <template x-for="(item, index) in items" :key="index">
        <div class="glass rounded-lg p-3 mb-2">
            <input type="text" x-model="items[index].question" :name="'settings[items][' + index + '][question]'" placeholder="Question" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white mb-2 focus:ring-2 focus:ring-purple-500/40 outline-none">
            <textarea x-model="items[index].answer" :name="'settings[items][' + index + '][answer]'" placeholder="Answer" rows="2" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none"></textarea>
            <button type="button" @click="items.splice(index, 1)" class="text-xs text-red-400/60 hover:text-red-400 mt-1"><i class="fas fa-times mr-1"></i> Remove</button>
        </div>
    </template>
    <button type="button" @click="items.push({question: '', answer: ''})" class="text-xs text-purple-400 hover:text-purple-300 mt-1">
        <i class="fas fa-plus mr-1"></i> Add FAQ Item
    </button>
</div>

@elseif($block->type === 'email_collector')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Title</label>
        <input type="text" name="settings[title]" value="{{ $s['title'] ?? 'Subscribe' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Placeholder</label>
        <input type="text" name="settings[placeholder]" value="{{ $s['placeholder'] ?? 'Your email' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Button Text</label>
        <input type="text" name="settings[button_text]" value="{{ $s['button_text'] ?? 'Subscribe' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
</div>

@elseif($block->type === 'map')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Address / Location</label>
        <input type="text" name="settings[address]" value="{{ $s['address'] ?? '' }}" placeholder="123 Main St, City, Country" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Zoom Level</label>
        <input type="number" name="settings[zoom]" value="{{ $s['zoom'] ?? 14 }}" min="1" max="20" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
</div>

@elseif($block->type === 'custom_html')
<div>
    <label class="block text-xs text-white/40 mb-1">HTML Code</label>
    <textarea name="settings[html]" rows="6" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white font-mono focus:ring-2 focus:ring-purple-500/40 outline-none">{{ $s['html'] ?? '' }}</textarea>
</div>

@elseif($block->type === 'youtube')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">YouTube Video ID or URL</label>
        <input type="text" name="settings[video_id]" value="{{ $s['video_id'] ?? '' }}" placeholder="dQw4w9WgXcQ or full URL" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
</div>

@elseif($block->type === 'spotify')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Spotify URL</label>
        <input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://open.spotify.com/track/..." class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Embed Type</label>
        <select name="settings[type]" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
            <option value="track" {{ ($s['type'] ?? '') === 'track' ? 'selected' : '' }} class="bg-[#1a1025]">Track</option>
            <option value="album" {{ ($s['type'] ?? '') === 'album' ? 'selected' : '' }} class="bg-[#1a1025]">Album</option>
            <option value="playlist" {{ ($s['type'] ?? '') === 'playlist' ? 'selected' : '' }} class="bg-[#1a1025]">Playlist</option>
        </select>
    </div>
</div>

@elseif($block->type === 'countdown')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Title</label>
        <input type="text" name="settings[title]" value="{{ $s['title'] ?? 'Coming Soon' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Target Date</label>
        <input type="datetime-local" name="settings[target_date]" value="{{ $s['target_date'] ?? '' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
</div>

@elseif($block->type === 'cta_button')
<div class="space-y-3">
    <div>
        <label class="block text-xs text-white/40 mb-1">Button Text</label>
        <input type="text" name="settings[text]" value="{{ $s['text'] ?? 'Click Here' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">URL</label>
        <input type="url" name="settings[url]" value="{{ $s['url'] ?? '' }}" placeholder="https://..." class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-xs text-white/40 mb-1">Button Color</label>
            <input type="color" name="settings[color]" value="{{ $s['color'] ?? '#7c3aed' }}" class="w-full h-10 rounded-xl border border-white/10 bg-white/5 cursor-pointer">
        </div>
        <div>
            <label class="block text-xs text-white/40 mb-1">Text Color</label>
            <input type="color" name="settings[text_color]" value="{{ $s['text_color'] ?? '#ffffff' }}" class="w-full h-10 rounded-xl border border-white/10 bg-white/5 cursor-pointer">
        </div>
    </div>
    <div>
        <label class="block text-xs text-white/40 mb-1">Size</label>
        <select name="settings[size]" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
            <option value="sm" {{ ($s['size'] ?? '') === 'sm' ? 'selected' : '' }} class="bg-[#1a1025]">Small</option>
            <option value="md" {{ ($s['size'] ?? '') === 'md' ? 'selected' : '' }} class="bg-[#1a1025]">Medium</option>
            <option value="lg" {{ ($s['size'] ?? '') === 'lg' ? 'selected' : '' }} class="bg-[#1a1025]">Large</option>
        </select>
    </div>
</div>
@endif

<div class="mt-3 pt-3 border-t border-white/5">
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-xs text-white/40 mb-1">Schedule Start</label>
            <input type="datetime-local" name="start_date" value="{{ $block->start_date?->format('Y-m-d\TH:i') }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
        </div>
        <div>
            <label class="block text-xs text-white/40 mb-1">Schedule End</label>
            <input type="datetime-local" name="end_date" value="{{ $block->end_date?->format('Y-m-d\TH:i') }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none">
        </div>
    </div>
</div>
