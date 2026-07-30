@extends('admin.layouts.app')
@section('title', 'Marketing SEO')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <a href="{{ route('admin.site-pages.index') }}" class="text-xs text-blue-400 hover:underline ak-blue">
        <i class="fas fa-arrow-left mr-1"></i>Back to all pages
    </a>

    <div>
        <h1 class="text-2xl font-bold text-white ak-strong">Marketing SEO</h1>
        <p class="mt-1 text-sm text-white/50 ak-muted">
            Manage the meta title, description and keywords for every public marketing page.
            Open Graph and Twitter cards reuse the title and description automatically.
            Leave a field blank to fall back to the seeded default.
        </p>
    </div>

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm ak-green">
            {{ session('success') }}
        </div>
    @endif

    {{-- Code-driven pages: edited inline, persisted to the central registry. --}}
    <form method="POST" action="{{ route('admin.marketing-seo.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        @foreach($codeGroups as $group => $pages)
            <section class="bg-white/5 border border-white/10 rounded-2xl p-5 space-y-4">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-white/60 ak-muted">{{ $group }}</h2>

                @foreach($pages as $p)
                    @php $__customised = $p['override']['title'] !== '' || $p['override']['description'] !== '' || $p['override']['keywords'] !== '' || $p['override']['share_image'] !== ''; @endphp
                    <div x-data="{ open: {{ $__customised ? 'true' : 'false' }} }"
                         class="bg-black/20 border border-white/10 rounded-xl">
                        {{-- div role=button (not <button>): the header row holds a real
                             <a> to the live page, and interactive content inside a
                             button is invalid HTML (parser can eject later markup). --}}
                        <div role="button" tabindex="0" @click="open = !open"
                             @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open"
                             class="w-full flex items-center justify-between px-4 py-3 text-left cursor-pointer select-none">
                            <span class="flex items-center gap-2 min-w-0">
                                <span class="text-sm font-semibold text-white truncate ak-strong">{{ $p['label'] }}</span>
                                <a href="{{ $p['url'] }}" target="_blank" rel="noopener"
                                   @click.stop
                                   class="text-[11px] text-blue-400 hover:underline shrink-0 ak-blue">{{ $p['url'] }}</a>
                            </span>
                            <span class="flex items-center gap-2 shrink-0">
                                @if($__customised)
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-200 border border-blue-400/30 ak-blue">Customised</span>
                                @else
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-white/5 text-white/40 border border-white/10 ak-note">Default</span>
                                @endif
                                <i class="fas fa-chevron-down text-white/40 text-xs transition-transform ak-note" :class="open && 'rotate-180'"></i>
                            </span>
                        </div>

                        <div x-show="open" x-cloak class="px-4 pb-4 space-y-3 border-t border-white/10 pt-3">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1 ak-muted">Meta title</label>
                                <input type="text" name="seo[{{ $p['key'] }}][title]"
                                       value="{{ old('seo.'.$p['key'].'.title', $p['override']['title']) }}"
                                       placeholder="{{ $p['default']['title'] }}"
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1 ak-muted">Meta description</label>
                                <textarea name="seo[{{ $p['key'] }}][description]" rows="2"
                                          placeholder="{{ $p['default']['description'] }}"
                                          class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">{{ old('seo.'.$p['key'].'.description', $p['override']['description']) }}</textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1 ak-muted">Meta keywords</label>
                                <input type="text" name="seo[{{ $p['key'] }}][keywords]"
                                       value="{{ old('seo.'.$p['key'].'.keywords', $p['override']['keywords']) }}"
                                       placeholder="{{ $p['default']['keywords'] }}"
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
                                <p class="mt-1 text-[11px] text-white/30 ak-note">Comma-separated. Placeholder shows the seeded default.</p>
                            </div>
                            <div x-data="{ url: @js(old('seo.'.$p['key'].'.share_image', $p['override']['share_image'])) }">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1 ak-muted">Share preview image <span class="normal-case tracking-normal text-white/40 font-normal ak-note">(og:image, ideally 1200×630)</span></label>
                                <div x-data="aboutPhotoUploader({ get: () => url, set: (v) => url = v, aspect: 1200/630, outputSize: 1200, isCircle: false })" class="space-y-2">
                                    <div class="flex items-start gap-3">
                                        <div class="shrink-0 text-center">
                                            <template x-if="url">
                                                <img :src="url" alt="" class="w-40 object-cover rounded-md border border-white/10 bg-white/5" style="height:84px" x-on:error="$el.style.display='none'">
                                            </template>
                                            <template x-if="!url">
                                                <div class="w-40 rounded-md border-2 border-dashed border-white/15 bg-white/5 flex items-center justify-center text-[10px] text-white/40 text-center px-2 ak-note" style="height:84px">Falls back to the global default share image</div>
                                            </template>
                                            <div class="text-[10px] text-white/40 mt-1 ak-note">Live preview</div>
                                        </div>
                                        <div class="flex-1 space-y-2">
                                            <input type="text" name="seo[{{ $p['key'] }}][share_image]" x-model="url"
                                                   placeholder="https://… or /storage/… (or upload)"
                                                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white ak-strong ak-input">
                                            @error('seo.'.$p['key'].'.share_image')<p class="mt-1 text-xs text-red-400 ak-red">{{ $message }}</p>@enderror
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <button type="button" @click="pickFile()" :disabled="uploading" class="text-xs px-3 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1">
                                                    <i class="fas fa-upload"></i>
                                                    <span x-text="uploading ? ('Uploading… ' + progress + '%') : 'Upload image'"></span>
                                                </button>
                                                <button type="button" x-show="url" @click="clear()" class="text-xs px-2 py-1.5 text-white/60 hover:text-white ak-muted"><i class="fas fa-times mr-1"></i>Remove</button>
                                            </div>
                                            <p x-show="error" x-text="error" class="text-xs text-red-400 ak-red"></p>
                                            <p class="text-[11px] text-white/30 ak-note">Leave blank to use the global default share image from Marketing Settings.</p>
                                        </div>
                                    </div>
                                    <input type="file" x-ref="fileInput" @change="handleFile($event)" accept="image/*" class="hidden">
                                    @include('admin.site-pages.partials.about-crop-modal')
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endforeach

        <div class="sticky bottom-4 flex justify-end">
            <button type="submit"
                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-xl shadow-lg shadow-blue-900/40">
                <i class="fas fa-save mr-1.5"></i>Save SEO
            </button>
        </div>
    </form>

    @include('admin.site-pages.partials.photo-uploader')

    {{-- Content pages backed by a site_pages row: deep-link to their editor. --}}
    @foreach($siteGroups as $group => $pages)
        <section class="bg-white/5 border border-white/10 rounded-2xl p-5 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-white/60 ak-muted">{{ $group }} <span class="text-white/30 normal-case font-normal ak-note">· content pages</span></h2>
            </div>
            <div class="divide-y divide-white/5">
                @foreach($pages as $p)
                    <a href="{{ $p['edit_url'] }}" class="flex items-center justify-between gap-3 py-2.5 group">
                        <span class="min-w-0">
                            <span class="block text-sm text-white group-hover:text-blue-300 truncate ak-strong">{{ $p['label'] }}</span>
                            <span class="block text-[11px] text-white/40 truncate ak-note">{{ $p['title'] ?: $p['slug'] }}</span>
                        </span>
                        <span class="flex items-center gap-2 shrink-0 text-white/40 ak-note">
                            @unless($p['exists'])
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-200 border border-amber-400/30 ak-amber">Not seeded</span>
                            @endunless
                            <span class="text-[11px] text-blue-400 group-hover:underline ak-blue">Edit<i class="fas fa-arrow-right ml-1"></i></span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
@endsection
