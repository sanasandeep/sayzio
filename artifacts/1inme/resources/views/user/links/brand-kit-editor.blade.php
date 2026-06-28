@extends('user.layouts.app')
@section('title', 'Brand / Press Kit editor')

@section('content')
@php($p = $config['palette'] ?? [])
@php($f = $config['fonts'] ?? [])
@php($v = $config['voice'] ?? [])
<div class="max-w-3xl mx-auto"
     x-data="brandKitEditor({
        taglines: @js(array_values($config['taglines'] ?? [])),
        descriptors: @js(array_values($v['descriptors'] ?? [])),
        neutrals: @js(array_values($p['neutrals'] ?? [])),
        logos: @js(array_values($config['logos'] ?? [])),
        socials: @js(array_values($config['socials'] ?? [])),
     })">

    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.index') }}" class="text-white/30 hover:text-white/50" title="Back to links"><i class="fas fa-arrow-left"></i></a>
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-white">Brand / Press Kit</h1>
            <p class="text-xs text-white/40 mt-0.5">
                Built from your saved Brand Kit.
                <a href="{{ $publicUrl }}" target="_blank" class="text-blue-400 hover:underline">View public page <i class="fas fa-external-link-alt text-[10px]"></i></a>
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-red-400/20 bg-red-400/10 px-4 py-3 text-sm text-red-200">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('user.links.brand-kit.update', $link) }}" class="space-y-5">
        @csrf

        {{-- Theme + visibility --}}
        <div class="glass rounded-2xl p-6">
            <label class="block text-sm font-medium text-white/60 mb-3">Page theme</label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" x-data="{ tpl: '{{ $config['template'] ?? 'studio' }}' }">
                @foreach($templates as $id => $t)
                    <label class="cursor-pointer">
                        <input type="radio" name="template" value="{{ $id }}" class="sr-only" x-model="tpl" @checked(($config['template'] ?? 'studio') === $id)>
                        <div :class="tpl === '{{ $id }}' ? 'ring-2 ring-blue-400 border-blue-400' : 'border-white/10 hover:border-white/30'"
                             class="rounded-xl border overflow-hidden transition">
                            <div class="h-16" style="background: {{ $t['page_bg'] }};">
                                <span class="block text-[10px] font-bold px-2 pt-2" style="color: {{ $t['text'] }};">{{ $t['name'] }}</span>
                            </div>
                            <div class="px-2 py-1.5 bg-black/30"><p class="text-[10px] text-white/50 leading-tight">{{ $t['tagline'] }}</p></div>
                        </div>
                    </label>
                @endforeach
            </div>

            <label class="flex items-center gap-3 mt-5 cursor-pointer">
                <input type="checkbox" name="is_public" value="1" @checked($isPublic) class="rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500/40">
                <span class="text-sm text-white/70">Public — anyone with the link can view. <span class="text-white/40">Uncheck to require visitors to be signed in.</span></span>
            </label>
        </div>

        {{-- Identity --}}
        <div class="glass rounded-2xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-white/80">Brand identity</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Brand name</label>
                    <input type="text" name="brand_name" value="{{ $config['brand_name'] ?? '' }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Tagline</label>
                    <input type="text" name="tagline" value="{{ $config['tagline'] ?? '' }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">About</label>
                <textarea name="about" rows="3" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">{{ $config['about'] ?? '' }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Press boilerplate <span class="text-white/35">— the short paragraph press can copy verbatim</span></label>
                <textarea name="boilerplate" rows="4" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">{{ $config['boilerplate'] ?? '' }}</textarea>
            </div>
        </div>

        {{-- Colours --}}
        <div class="glass rounded-2xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-white/80">Colours</h2>
            <div class="grid grid-cols-3 gap-4">
                @foreach(['primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent'] as $key => $label)
                <div>
                    <label class="block text-xs font-medium text-white/50 mb-1">{{ $label }}</label>
                    <input type="text" name="palette[{{ $key }}]" value="{{ $p[$key] ?? '' }}" placeholder="#rrggbb" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/40">
                </div>
                @endforeach
            </div>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-medium text-white/50">Neutral swatches</label>
                    <button type="button" @click="neutrals.push('#888888')" class="text-xs text-blue-400 hover:underline"><i class="fas fa-plus"></i> Add</button>
                </div>
                <div class="space-y-2">
                    <template x-for="(n, i) in neutrals" :key="'n'+i">
                        <div class="flex items-center gap-2">
                            <input type="text" :name="'palette[neutrals][]'" x-model="neutrals[i]" placeholder="#rrggbb" class="flex-1 border border-white/10 rounded-xl px-3 py-2 text-sm">
                            <button type="button" @click="neutrals.splice(i,1)" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Fonts --}}
        <div class="glass rounded-2xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-white/80">Font pairing</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-white/50 mb-1">Heading font</label>
                    <input type="text" name="fonts[heading]" value="{{ $f['heading'] ?? 'Inter' }}" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-white/50 mb-1">Body font</label>
                    <input type="text" name="fonts[body]" value="{{ $f['body'] ?? 'Inter' }}" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        {{-- Voice --}}
        <div class="glass rounded-2xl p-6 space-y-4">
            <h2 class="text-sm font-semibold text-white/80">Brand voice</h2>
            <div>
                <label class="block text-xs font-medium text-white/50 mb-1">Tone</label>
                <input type="text" name="voice[tone]" value="{{ $v['tone'] ?? '' }}" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm">
            </div>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-medium text-white/50">Descriptors</label>
                    <button type="button" @click="descriptors.push('')" class="text-xs text-blue-400 hover:underline"><i class="fas fa-plus"></i> Add</button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <template x-for="(d, i) in descriptors" :key="'d'+i">
                        <div class="flex items-center gap-1 bg-white/5 rounded-lg px-2 py-1">
                            <input type="text" name="voice[descriptors][]" x-model="descriptors[i]" placeholder="e.g. bold" class="bg-transparent text-sm w-24 focus:outline-none">
                            <button type="button" @click="descriptors.splice(i,1)" class="text-white/30 hover:text-red-400"><i class="fas fa-times text-xs"></i></button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Taglines --}}
        <div class="glass rounded-2xl p-6 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-white/80">Taglines</h2>
                <button type="button" @click="taglines.push('')" class="text-xs text-blue-400 hover:underline"><i class="fas fa-plus"></i> Add</button>
            </div>
            <template x-for="(t, i) in taglines" :key="'t'+i">
                <div class="flex items-center gap-2">
                    <input type="text" name="taglines[]" x-model="taglines[i]" class="flex-1 border border-white/10 rounded-xl px-3 py-2 text-sm">
                    <button type="button" @click="taglines.splice(i,1)" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button>
                </div>
            </template>
        </div>

        {{-- Logos --}}
        <div class="glass rounded-2xl p-6 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-white/80">Logo downloads <span class="text-white/35 font-normal">— link each to a downloadable asset</span></h2>
                <button type="button" @click="logos.push({label:'',url:''})" class="text-xs text-blue-400 hover:underline"><i class="fas fa-plus"></i> Add</button>
            </div>
            <template x-for="(l, i) in logos" :key="'l'+i">
                <div class="grid grid-cols-12 gap-2 items-center">
                    <input type="text" :name="'logos['+i+'][label]'" x-model="l.label" placeholder="Label (e.g. PNG, dark)" class="col-span-4 border border-white/10 rounded-xl px-3 py-2 text-sm">
                    <input type="url" :name="'logos['+i+'][url]'" x-model="l.url" placeholder="https://…" class="col-span-7 border border-white/10 rounded-xl px-3 py-2 text-sm">
                    <button type="button" @click="logos.splice(i,1)" class="col-span-1 text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button>
                </div>
            </template>
            <p class="text-[11px] text-white/35" x-show="logos.length === 0">No logos yet — add a hosted file URL for each download.</p>
        </div>

        {{-- Socials + contact --}}
        <div class="glass rounded-2xl p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-white/80">Social links</h2>
                <button type="button" @click="socials.push({label:'',url:''})" class="text-xs text-blue-400 hover:underline"><i class="fas fa-plus"></i> Add</button>
            </div>
            <template x-for="(s, i) in socials" :key="'s'+i">
                <div class="grid grid-cols-12 gap-2 items-center">
                    <input type="text" :name="'socials['+i+'][label]'" x-model="s.label" placeholder="Label (e.g. Instagram)" class="col-span-4 border border-white/10 rounded-xl px-3 py-2 text-sm">
                    <input type="url" :name="'socials['+i+'][url]'" x-model="s.url" placeholder="https://…" class="col-span-7 border border-white/10 rounded-xl px-3 py-2 text-sm">
                    <button type="button" @click="socials.splice(i,1)" class="col-span-1 text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button>
                </div>
            </template>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-white/5">
                <div>
                    <label class="block text-xs font-medium text-white/50 mb-1">Contact email</label>
                    <input type="email" name="contact_email" value="{{ $config['contact_email'] ?? '' }}" placeholder="press@brand.com" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-white/50 mb-1">Contact / website link</label>
                    <input type="url" name="contact_url" value="{{ $config['contact_url'] ?? '' }}" placeholder="https://brand.com/press" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        {{-- Sections visibility --}}
        <div class="glass rounded-2xl p-6">
            <h2 class="text-sm font-semibold text-white/80 mb-3">Show on the page</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @php($secLabels = ['logos'=>'Logos','colors'=>'Colours','fonts'=>'Fonts','voice'=>'Voice','about'=>'About','socials'=>'Socials','contact'=>'Contact'])
                @foreach($secLabels as $key => $label)
                <label class="flex items-center gap-2 text-sm text-white/70 cursor-pointer">
                    <input type="checkbox" name="sections[{{ $key }}]" value="1" @checked(($config['sections'][$key] ?? true)) class="rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500/40">
                    {{ $label }}
                </label>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ $publicUrl }}" target="_blank" class="px-5 py-2.5 rounded-xl text-sm text-white/60 hover:text-white">Preview</a>
            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">Save changes</button>
        </div>
    </form>
</div>

<script>
function brandKitEditor(initial) {
    return {
        taglines: initial.taglines || [],
        descriptors: initial.descriptors || [],
        neutrals: initial.neutrals || [],
        logos: initial.logos || [],
        socials: initial.socials || [],
    };
}
</script>
@endsection
