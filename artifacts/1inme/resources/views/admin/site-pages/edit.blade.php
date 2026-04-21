@extends('admin.layouts.app')
@section('title', 'Edit page — ' . $page->title)
@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <a href="{{ route('admin.site-pages.index') }}" class="text-xs text-violet-400 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to all pages</a>

    @php
        $isServices = $page->slug === 'services';
        $iconChoices = [
            'fa-bullhorn','fa-store','fa-shop','fa-cart-shopping','fa-bag-shopping','fa-tag','fa-tags','fa-gift','fa-percent',
            'fa-rocket','fa-bolt','fa-fire','fa-star','fa-heart','fa-thumbs-up','fa-trophy','fa-award','fa-medal','fa-crown','fa-gem',
            'fa-user','fa-users','fa-user-group','fa-user-plus','fa-user-tie','fa-people-group','fa-handshake','fa-id-badge','fa-id-card',
            'fa-link','fa-share-nodes','fa-paper-plane','fa-envelope','fa-comments','fa-comment-dots','fa-message','fa-phone','fa-headset',
            'fa-globe','fa-earth-americas','fa-map-location-dot','fa-location-dot','fa-compass','fa-sitemap','fa-network-wired',
            'fa-camera','fa-image','fa-images','fa-video','fa-film','fa-music','fa-microphone','fa-podcast','fa-headphones',
            'fa-palette','fa-paintbrush','fa-pen-nib','fa-pencil','fa-wand-magic-sparkles','fa-sparkles','fa-lightbulb','fa-flask',
            'fa-chart-line','fa-chart-bar','fa-chart-pie','fa-chart-column','fa-magnifying-glass-chart','fa-bullseye','fa-arrow-trend-up',
            'fa-briefcase','fa-building','fa-building-user','fa-suitcase','fa-clipboard-list','fa-list-check','fa-folder-open','fa-file-lines',
            'fa-calendar','fa-calendar-day','fa-calendar-check','fa-clock','fa-hourglass-half','fa-bell','fa-flag','fa-thumbtack',
            'fa-graduation-cap','fa-book','fa-book-open','fa-school','fa-chalkboard-user','fa-user-graduate','fa-pen-to-square',
            'fa-cog','fa-gear','fa-gears','fa-sliders','fa-screwdriver-wrench','fa-toolbox','fa-wrench','fa-hammer','fa-cube','fa-cubes',
            'fa-credit-card','fa-money-bill','fa-money-bill-wave','fa-coins','fa-piggy-bank','fa-wallet','fa-receipt','fa-cash-register',
            'fa-circle-check','fa-circle-info','fa-shield-halved','fa-lock','fa-key','fa-fingerprint',
            'fa-utensils','fa-mug-hot','fa-cake-candles','fa-pizza-slice','fa-leaf','fa-seedling','fa-tree','fa-paw','fa-dog','fa-cat',
            'fa-plane','fa-car','fa-truck','fa-bicycle','fa-ship','fa-train','fa-house','fa-bed','fa-couch',
            'fa-dumbbell','fa-heart-pulse','fa-spa','fa-stethoscope','fa-pills','fa-droplet','fa-sun','fa-moon','fa-cloud',
            'fa-circle-dot','fa-puzzle-piece','fa-cube','fa-layer-group','fa-shapes','fa-infinity','fa-recycle',
        ];
        $iconChoices = array_values(array_unique($iconChoices));
        $servicesSeed = [];
        if ($isServices) {
            foreach (array_values($page->sections ?? []) as $s) {
                $bullets = $s['bullets'] ?? [];
                if (is_array($bullets)) { $bullets = implode("\n", $bullets); }
                $servicesSeed[] = [
                    'heading'   => (string) ($s['heading'] ?? ''),
                    'tagline'   => (string) ($s['tagline'] ?? ''),
                    'body'      => (string) ($s['body'] ?? ''),
                    'icon'      => (string) ($s['icon'] ?? ''),
                    'tint'      => (string) ($s['tint'] ?? ''),
                    'bullets'   => (string) $bullets,
                    'cta_label' => (string) ($s['cta_label'] ?? ''),
                    'cta_url'   => (string) ($s['cta_url'] ?? ''),
                ];
            }
        }
    @endphp

    @if($page->slug === 'features')
        @include('admin.site-pages.partials.features-editor', ['page' => $page, 'categories' => $featuresCategories])
    @else
    <form method="POST" action="{{ route('admin.site-pages.update', $page->slug) }}"
          @if($isServices)
          x-data="{ sections: {{ json_encode($servicesSeed) }}, iconChoices: {{ json_encode($iconChoices) }}, pickerOpen: null, pickerQuery: '', filteredIcons() { const q = (this.pickerQuery || '').toLowerCase().trim(); return q ? this.iconChoices.filter(n => n.toLowerCase().includes(q)) : this.iconChoices; } }"
          @else
          x-data="{ sections: {{ json_encode(array_values($page->sections ?? [])) }} }"
          @endif
          class="glass rounded-2xl p-6 space-y-5">
        @csrf
        @method('PUT')
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
            @if($isServices)
                <p class="mt-1 text-[11px] text-white/40">Doubles as the hero subtitle on the public /services page.</p>
            @endif
        </div>

        @if($isServices)
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-semibold uppercase tracking-wider text-white/60">Use-case blocks</label>
                    <button type="button" @click="sections.push({heading:'',tagline:'',body:'',icon:'fa-circle-dot',tint:'',bullets:'',cta_label:'Get started',cta_url:'/register'})" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 rounded-lg text-white">
                        <i class="fas fa-plus mr-1"></i> Add use case
                    </button>
                </div>
                <template x-for="(s, i) in sections" :key="i">
                    <div class="bg-white/5 border border-white/10 rounded-xl p-4 mb-3 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] uppercase tracking-wider text-white/40">Use case <span x-text="i+1"></span></span>
                            <button type="button" @click="sections.splice(i,1)" class="text-xs text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Title</label>
                                <input type="text" :name="'sections['+i+'][heading]'" x-model="s.heading" placeholder="Marketing channel"
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Tagline</label>
                                <input type="text" :name="'sections['+i+'][tagline]'" x-model="s.tagline" placeholder="Run campaigns from a single, trackable hub."
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Description</label>
                            <textarea :name="'sections['+i+'][body]'" x-model="s.body" rows="3" placeholder="Short paragraph that pitches this use case."
                                      class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Bullets <span class="normal-case tracking-normal text-white/40">(one per line)</span></label>
                            <textarea :name="'sections['+i+'][bullets]'" x-model="s.bullets" rows="4" placeholder="Branded link-in-bio with UTM-friendly short links&#10;Per-link click analytics and traffic sources"
                                      class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono"></textarea>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div class="relative">
                                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Icon</label>
                                <input type="hidden" :name="'sections['+i+'][icon]'" x-model="s.icon">
                                <button type="button"
                                        @click="pickerOpen = (pickerOpen === i ? null : i); pickerQuery = ''"
                                        class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white flex items-center justify-between gap-3 hover:bg-white/10">
                                    <span class="flex items-center gap-3 min-w-0">
                                        <span class="w-8 h-8 rounded-md bg-white/10 border border-white/10 flex items-center justify-center text-violet-200">
                                            <i class="fas" :class="s.icon || 'fa-circle-dot'"></i>
                                        </span>
                                        <span class="font-mono text-xs text-white/70 truncate" x-text="s.icon || 'Choose an icon'"></span>
                                    </span>
                                    <i class="fas fa-chevron-down text-[10px] text-white/40"></i>
                                </button>
                                <div x-show="pickerOpen === i" x-cloak
                                     @click.outside="pickerOpen = null"
                                     @keydown.escape.window="pickerOpen = null"
                                     class="absolute z-30 mt-2 left-0 right-0 bg-zinc-900 border border-white/15 rounded-xl shadow-2xl p-3"
                                     style="display:none;">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="fas fa-magnifying-glass text-xs text-white/40"></i>
                                        <input type="text" x-model="pickerQuery" placeholder="Search icons (e.g. cart, chart, rocket)"
                                               class="flex-1 px-2 py-1.5 bg-white/5 border border-white/10 rounded-md text-xs text-white">
                                    </div>
                                    <div class="grid grid-cols-8 gap-1.5 max-h-64 overflow-y-auto p-0.5">
                                        <template x-for="name in filteredIcons()" :key="name">
                                            <button type="button"
                                                    @click="s.icon = name; pickerOpen = null"
                                                    :title="name"
                                                    :class="s.icon === name ? 'bg-violet-600/40 border-violet-400/60 text-white' : 'bg-white/5 border-white/10 text-white/70 hover:bg-white/10 hover:text-white'"
                                                    class="aspect-square flex items-center justify-center rounded-md border text-base">
                                                <i class="fas" :class="name"></i>
                                            </button>
                                        </template>
                                        <div x-show="filteredIcons().length === 0" class="col-span-8 text-center text-[11px] text-white/40 py-4">
                                            No icons match "<span x-text="pickerQuery"></span>".
                                        </div>
                                    </div>
                                    <div class="mt-2 pt-2 border-t border-white/10 flex items-center gap-2">
                                        <span class="text-[10px] uppercase tracking-wider text-white/40">Custom class</span>
                                        <input type="text" x-model="s.icon" placeholder="fa-bullhorn"
                                               class="flex-1 px-2 py-1 bg-white/5 border border-white/10 rounded text-xs text-white font-mono">
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Tint (Tailwind gradient classes)</label>
                                <input type="text" :name="'sections['+i+'][tint]'" x-model="s.tint" placeholder="from-violet-500/30 to-fuchsia-500/10"
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono">
                                <p class="mt-1 text-[11px] text-white/40">Leave empty to use a built-in default.</p>
                            </div>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">CTA label</label>
                                <input type="text" :name="'sections['+i+'][cta_label]'" x-model="s.cta_label" placeholder="Get started"
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">CTA URL</label>
                                <input type="text" :name="'sections['+i+'][cta_url]'" x-model="s.cta_url" placeholder="/register"
                                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="sections.length===0" class="text-xs text-white/40 text-center py-4">No use cases yet — click "Add use case".</div>
            </div>
        @else
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
                        <label class="block text-[10px] uppercase tracking-wider text-white/40">Body <span class="normal-case tracking-normal text-white/40">(Markdown or basic HTML)</span></label>
                        <textarea :name="'sections['+i+'][body]'" x-model="s.body" rows="6" placeholder="Body — line breaks are preserved."
                                  class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono"></textarea>
                        <p class="text-[11px] text-white/40 leading-relaxed">
                            Formatting: <code class="text-white/60">**bold**</code>,
                            <code class="text-white/60">*italic*</code>,
                            <code class="text-white/60">[text](https://url)</code>,
                            lines starting with <code class="text-white/60">-</code> become bullet lists,
                            <code class="text-white/60">1.</code> become numbered lists.
                            Safe HTML tags (<code class="text-white/60">a, strong, em, ul, ol, li, p, br, h3, h4, blockquote, code</code>) are allowed; anything else (including scripts, inline event handlers, and unsafe link protocols) is filtered out.
                        </p>
                    </div>
                </template>
                <div x-show="sections.length===0" class="text-xs text-white/40 text-center py-4">No sections yet — click "Add section".</div>
            </div>
        @endif

        @php
            $errorSlugs = ['error-403', 'error-404', 'error-500', 'error-503', 'error-419', 'error-429'];
            $errorLabels = [
                'error-403' => '403 (no access)',
                'error-404' => '404 (not found)',
                'error-500' => '500 (server error)',
                'error-503' => '503 (maintenance)',
                'error-419' => '419 (session expired)',
                'error-429' => '429 (too many requests)',
            ];
        @endphp
        @if(in_array($page->slug, $errorSlugs))
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
            @if(in_array($page->slug, $errorSlugs))
                <span class="text-xs text-white/40">Shown automatically when visitors hit a {{ $errorLabels[$page->slug] }} response.</span>
            @else
                <a href="/{{ $page->slug === 'home' ? '' : $page->slug }}" target="_blank" class="text-xs text-violet-400 hover:underline">View live page <i class="fas fa-external-link-alt ml-1 text-[10px]"></i></a>
            @endif
            <button type="submit" class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-xl font-medium">Save changes</button>
        </div>
    </form>
    @endif

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
