@extends('admin.layouts.app')
@section('title', 'Edit page — ' . $page->title)
@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <a href="{{ route('admin.site-pages.index') }}" class="text-xs text-violet-400 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to all pages</a>

    <form method="POST" action="{{ route('admin.site-pages.update', $page->slug) }}"
          x-data="{ sections: {{ json_encode(array_values($page->sections ?? [])) }} }"
          class="glass rounded-2xl p-6 space-y-5">
        @csrf
        <div>
            <h2 class="text-lg font-semibold text-white">{{ $page->title }} <span class="text-xs text-white/40 ml-2">/{{ $page->slug === 'home' ? '' : $page->slug }}</span></h2>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Page title</label>
            <input type="text" name="title" required value="{{ old('title', $page->title) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            @error('title')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Meta description</label>
            <textarea name="meta_description" rows="2" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old('meta_description', $page->meta_description) }}</textarea>
        </div>

        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="text-xs font-semibold uppercase tracking-wider text-white/60">Content sections</label>
                <button type="button" @click="sections.push({heading:'',body:''})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white">
                    <i class="fas fa-plus mr-1"></i> Add section
                </button>
            </div>
            <template x-for="(s, i) in sections" :key="i">
                <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] uppercase tracking-wider text-white/40">Section <span x-text="i+1"></span></span>
                        <button type="button" @click="sections.splice(i,1)" class="text-xs text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
                    </div>
                    <input type="text" :name="'sections['+i+'][heading]'" x-model="s.heading" placeholder="Section heading"
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <textarea :name="'sections['+i+'][body]'" x-model="s.body" rows="6" placeholder="Body — line breaks are preserved. Basic HTML is allowed."
                              class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono"></textarea>
                </div>
            </template>
            <div x-show="sections.length===0" class="text-xs text-white/40 text-center py-4">No sections yet — click "Add section".</div>
        </div>

        @if(in_array($page->slug, ['error-403', 'error-404']))
            <div class="grid sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Call-to-action label</label>
                    <input type="text" name="cta_label" value="{{ old('cta_label', $page->cta_label) }}" placeholder="Back to home" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('cta_label')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Call-to-action URL</label>
                    <input type="text" name="cta_url" value="{{ old('cta_url', $page->cta_url) }}" placeholder="/" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    @error('cta_url')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        @endif

        @if($page->slug === 'error-404')
            <div class="pt-2">
                <label class="inline-flex items-start gap-2 text-sm text-white">
                    <input type="hidden" name="error_404_suggestions_enabled" value="0">
                    <input type="checkbox" name="error_404_suggestions_enabled" value="1" {{ old('error_404_suggestions_enabled', $settings['error_404_suggestions_enabled']) ? 'checked' : '' }} class="mt-0.5 rounded border-white/20 bg-white/5">
                    <span>
                        Show "Did you mean…?" suggestions
                        <span class="block text-xs text-white/50 mt-0.5">When a visitor hits a 404, show up to 3 close matches from your biolinks, short links and site pages. Nothing is shown when no match is close enough.</span>
                    </span>
                </label>
            </div>
        @endif

        <div class="pt-4 border-t border-white/10 flex items-center justify-between">
            @if(in_array($page->slug, ['error-403', 'error-404']))
                <span class="text-xs text-white/40">Shown automatically when visitors hit a {{ $page->slug === 'error-403' ? '403 (no access)' : '404 (not found)' }} response.</span>
            @else
                <a href="/{{ $page->slug === 'home' ? '' : $page->slug }}" target="_blank" class="text-xs text-violet-400 hover:underline">View live page <i class="fas fa-external-link-alt ml-1 text-[10px]"></i></a>
            @endif
            <button type="submit" class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-xl font-medium">Save changes</button>
        </div>
    </form>

    @if($page->slug === 'discovery')
        <div class="glass rounded-2xl p-6">
            <h3 class="text-sm font-semibold text-white mb-1">Discovery settings</h3>
            <p class="text-xs text-white/50 mb-4">Controls how the public /discovery page renders biolinks.</p>
            <form method="POST" action="{{ route('admin.site-pages.discovery-settings') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Biolinks per page</label>
                    <input type="number" min="4" max="60" name="discovery_per_page" value="{{ old('discovery_per_page', $settings['discovery_per_page']) }}" class="w-32 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-white">
                    <input type="checkbox" name="discovery_show_search" value="1" {{ $settings['discovery_show_search'] ? 'checked' : '' }} class="rounded border-white/20 bg-white/5">
                    Show search bar
                </label>
                <div class="pt-2">
                    <button type="submit" class="px-5 py-2 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm font-medium text-white">Save settings</button>
                </div>
            </form>
        </div>
    @endif

    @if($page->slug === 'creators-feed')
        <div class="glass rounded-2xl p-6">
            <h3 class="text-sm font-semibold text-white mb-1">Creators feed settings</h3>
            <p class="text-xs text-white/50 mb-4">Controls how the public /creators-feed page renders posts.</p>
            <form method="POST" action="{{ route('admin.site-pages.creators-feed-settings') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-white/60 mb-1.5">Posts per page</label>
                    <input type="number" min="4" max="60" name="creators_feed_per_page" value="{{ old('creators_feed_per_page', $settings['creators_feed_per_page']) }}" class="w-32 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-white">
                    <input type="checkbox" name="creators_feed_show_pinned" value="1" {{ $settings['creators_feed_show_pinned'] ? 'checked' : '' }} class="rounded border-white/20 bg-white/5">
                    Show pinned posts at the top
                </label>
                <div class="pt-2">
                    <button type="submit" class="px-5 py-2 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm font-medium text-white">Save settings</button>
                </div>
            </form>
        </div>
    @endif

    @if($page->slug === 'faqs')
        <div class="glass rounded-2xl p-6">
            <h3 class="text-sm font-semibold text-white mb-3">FAQ items</h3>
            <form method="POST" action="{{ route('admin.site-pages.faqs.store') }}" class="space-y-2 mb-5 pb-5 border-b border-white/10">
                @csrf
                <input type="text" name="question" required placeholder="Question" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                <textarea name="answer" required rows="3" placeholder="Answer" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
                <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 rounded-lg text-xs font-medium text-white"><i class="fas fa-plus mr-1"></i> Add FAQ</button>
            </form>

            <div class="space-y-3">
                @foreach($faqs as $f)
                    <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-2">
                        <form method="POST" action="{{ route('admin.site-pages.faqs.update', $f) }}" class="space-y-2">
                            @csrf @method('PUT')
                            <div class="flex items-center gap-2">
                                <input type="number" name="sort_order" value="{{ $f->sort_order }}" class="w-20 px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-white">
                                <input type="text" name="question" value="{{ $f->question }}" required class="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            </div>
                            <textarea name="answer" required rows="3" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ $f->answer }}</textarea>
                            <button type="submit" class="px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-xs text-white">Save</button>
                        </form>
                        <form method="POST" action="{{ route('admin.site-pages.faqs.destroy', $f) }}" onsubmit="return confirm('Delete this FAQ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 bg-red-500/20 hover:bg-red-500/30 text-red-300 rounded-lg text-xs"><i class="fas fa-trash mr-1"></i>Delete</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
