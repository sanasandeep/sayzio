{{--
    Reusable "Page background" card extracted from settings/appearance.blade.php
    so the slides + conversational editors can expose the same color / gradient
    / image / slideshow / video / template controls without re-implementing the
    Alpine state machine. Renders ONLY the inner card markup — the parent view
    wraps it in whatever <form> + submit handler is appropriate for that page.

    Required vars: $link  -- OR $bs, for a surface that has a background but
                  no Link behind it (a resume is reachable at @handle/slug
                  with no link in scope at all). Passing $bs is what keeps
                  this the only background picker in the app; the alternative
                  was a second one for resumes, and six of those is how this
                  work started.
    Optional:      $bgTemplates (Collection of \App\Modules\Admin\Models\BgTemplate)
                   — auto-loaded from the database if not passed in.
--}}
@php
    $bs = $bs ?? ($link->settings['biolink'] ?? []);
    // $link is still used for the image-gallery route and the design lock;
    // a Link-less surface passes neither and gets the picker without them.
    $link = $link ?? null;
    $bgType            = $bs['background_type']     ?? 'color';
    $bgColor           = $bs['background_color']    ?? '#0a0612';
    $bgGradient        = $bs['background_gradient'] ?? 'linear-gradient(135deg, #0a0612 0%, #1a0533 50%, #0a0612 100%)';
    $gradientColors    = $bs['gradient_colors']     ?? [['color'=>'#0a0612','pos'=>0],['color'=>'#1a0533','pos'=>50],['color'=>'#0a0612','pos'=>100]];
    $gradientAngle     = $bs['gradient_angle']      ?? 135;
    $gradientTypeVal   = $bs['gradient_type']       ?? 'linear';
    $slideshowImages   = $bs['slideshow_images']    ?? [];
    $slideshowInterval = $bs['slideshow_interval']  ?? 5;
    $videoUrl          = $bs['video_url']           ?? '';
    $videoFile         = $bs['video_file']          ?? '';
    $bgTemplateId      = $bs['bg_template_id']      ?? null;
    $bgAttachment      = $bs['bg_attachment']       ?? 'fixed';
    $bgFallbackColor   = $bs['bg_fallback_color']   ?? '#0a0612';
    $bgFallbackImage   = $bs['bg_fallback_image']   ?? '';
    $bgBlur            = $bs['bg_blur']             ?? 0;
    $bgOverlayColor    = $bs['bg_overlay_color']    ?? '#000000';
    $bgOverlayOpacity  = $bs['bg_overlay_opacity']  ?? 0;
    $bgPresetKey       = $bs['bg_preset_key']       ?? '';
    $tornPaperColor    = $bs['torn_paper_color']    ?? '#cfe0e6';
    $tornStyleVal      = $bs['torn_style']          ?? \App\Modules\User\Support\TornStyleCatalog::DEFAULT;
    // Color inputs always submit a value, so seed pleasant defaults
    // (the legacy dusty-blue backdrop) instead of browser-default black.
    $tornBdColor       = ($bs['torn_backdrop_color']  ?? '') ?: '#8aa6b4';
    $tornBdColor2      = ($bs['torn_backdrop_color2'] ?? '') ?: '#46626f';
    $tilesPaletteVal   = $bs['tiles_palette']       ?? '';
    $tilesLayoutVal    = $bs['tiles_layout']        ?? 'uniform';
    $tilesAnimateVal   = (string) ($bs['tiles_animate'] ?? '0');
    $meshPresetVal     = $bs['mesh_preset']         ?? '';
    $patternPresetVal  = $bs['pattern_preset']      ?? '';
    $gradientPresetIdVal = $bs['gradient_preset_id'] ?? '';

    // Lazy-load bg templates if the parent didn't pass them in.
    $bgTemplates = $bgTemplates ?? \App\Modules\Admin\Models\BgTemplate::active()->get();

    // Task #6231: one library over every ready-made look, replacing the six
    // pickers. Categories describe what a look IS, so "Mesh" is one chip over
    // both sources instead of a picker AND a chip inside Template.
    $library         = \App\Modules\User\Support\BackgroundLibrary::items($bgTemplates);
    $libraryCounts   = \App\Modules\User\Support\BackgroundLibrary::counts($library);
    $libraryLabels   = \App\Modules\User\Support\BackgroundLibrary::CATEGORIES;
    $librarySelected = \App\Modules\User\Support\BackgroundLibrary::selectedValue($bs);
@endphp

<div class="card-premium p-6" x-data="bgSettings()" x-init="init()">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(99,102,241,0.1);"><i class="fas fa-fill-drip text-indigo-400 text-xs"></i></div>
        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Page background</h3>
    </div>

    <div class="space-y-5">
        <div>
            {{-- Three groups as a segmented control, then only that group's
                 types. Switching a group shows different options; it never
                 changes what is saved. Only clicking a type does that. --}}
            {{-- Each tab carries what it is FOR under its name. Colour and
                 Style both used to open on a chip row over a swatch grid,
                 which is what made them read as one feature done twice; the
                 sub-line is the first thing that tells them apart. --}}
            <div class="bg-group-switch mb-3" role="group" aria-label="Background kind">
                <template x-for="g in groups" :key="g.key">
                    <button type="button" @click="activeGroup = g.key"
                            :aria-current="activeGroup === g.key ? 'true' : 'false'"
                            :class="activeGroup === g.key ? 'is-on' : ''"
                            class="bg-group-btn">
                        <span class="bg-group-name" x-text="g.label"></span>
                        <span class="bg-group-sub" x-text="g.sub"></span>
                        <span class="bg-group-dot" x-show="typesIn(g.key).some(t => t.key === bgType)" aria-hidden="true"></span>
                    </button>
                </template>
            </div>
            {{-- Colour and Media still choose between a handful of unlike
                 things, so they keep their tiles -- now wide, named rows,
                 because two or three of them never needed a six-up grid of
                 icons. Style does not: every one of its options was "pick a
                 ready-made look", which is why the same category names kept
                 turning up at two levels. It gets the library below instead. --}}
            <template x-for="g in groups" :key="g.key">
            <div x-show="activeGroup === g.key && g.key !== 'style'">
            <div class="bg-type-row">
                <template x-for="t in typesIn(g.key)" :key="t.key">
                    <button type="button" @click="bgType = t.key"
                        :class="bgType === t.key ? 'is-on' : ''"
                        class="bg-type-card">
                        <span class="bg-type-chip" :style="'background:' + t.preview">
                            <i :class="'fas ' + t.icon" class="text-[9px] text-white/80"></i>
                        </span>
                        <span class="bg-type-label" x-text="t.label"></span>
                    </button>
                </template>
            </div>
            </div>
            </template>

            {{-- THE STYLE LIBRARY (Task #6231) --------------------------------
                 One grid over every ready-made look. The chips filter that one
                 set, so a category name exists exactly once. Picking a look
                 still writes the same background_type and key field its old
                 picker wrote -- see BackgroundLibrary -- so nothing stored
                 changes and the renderer is untouched. --}}
            <div x-show="activeGroup === 'style'" x-data="{ libCat: 'all', libSearch: '', picked: @js($librarySelected) }"
                 @bg-browse.window="libCat = $event.detail; libSearch = ''">
                <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                    <label class="block text-xs font-medium" style="color: var(--text-muted);">
                        Choose a look <span class="opacity-60">{{ count($library) }}</span>
                    </label>
                    <input type="text" x-model="libSearch" placeholder="Search all {{ count($library) }}…"
                           class="text-[11px] px-2 py-1 rounded-md flex-1 max-w-[190px]"
                           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                </div>

                <div class="bg-lib-chips mb-2">
                    <button type="button" @click="libCat = 'all'"
                            class="bg-lib-chip" :class="libCat === 'all' ? 'is-on' : ''">
                        All <span class="bg-lib-n">{{ count($library) }}</span>
                    </button>
                    @foreach($libraryCounts as $catKey => $catCount)
                    <button type="button" @click="libCat = '{{ $catKey }}'"
                            class="bg-lib-chip" :class="libCat === '{{ $catKey }}' ? 'is-on' : ''">
                        {{ $libraryLabels[$catKey] }} <span class="bg-lib-n">{{ $catCount }}</span>
                    </button>
                    @endforeach
                </div>

                <div class="bg-swatch-grid max-h-[420px] overflow-y-auto pr-1">
                    @foreach($library as $item)
                    @php
                        $detail = ['type' => $item['type'], 'value' => $item['value']] + ($item['torn'] ?? []);
                    @endphp
                    <button type="button"
                            title="{{ $item['label'] }}"
                            x-show="(libCat === 'all' || libCat === '{{ $item['category'] }}')
                                    && (!libSearch || {{ Illuminate\Support\Js::from($item['search']) }}.includes(libSearch.toLowerCase()))"
                            @click="picked = {{ Illuminate\Support\Js::from($item['value']) }};
                                    bgType = {{ Illuminate\Support\Js::from($item['type']) }};
                                    @if($item['type'] === 'gradient')
                                    {{-- A gradient is the one look that stays editable: picking
                                         it loads the Colour builder rather than freezing a
                                         background, which the two separate surfaces never did. --}}
                                    gradientStops    = {{ Illuminate\Support\Js::from($item['gradient']['stops']) }};
                                    gradientType     = {{ Illuminate\Support\Js::from($item['gradient']['type']) }};
                                    gradientAngle    = {{ (int) $item['gradient']['angle'] }};
                                    gradientPresetId = {{ Illuminate\Support\Js::from($item['value']) }};
                                    @else
                                    window.dispatchEvent(new CustomEvent('bg-pick', {{ Illuminate\Support\Js::from(['detail' => $detail]) }}));
                                    @endif
                                    $nextTick(() => $dispatch('change'))"
                            :class="picked === {{ Illuminate\Support\Js::from($item['value']) }} && bgType === {{ Illuminate\Support\Js::from($item['type']) }} ? 'is-picked' : ''"
                            class="bg-lib-swatch">
                        @switch($item['thumb']['kind'])
                            @case('class')
                                <span class="bg-lib-fill" style="background: {{ $item['thumb']['ground'] }};">
                                    <span class="{{ $item['thumb']['class'] }}" style="position:absolute;inset:0;"></span>
                                </span>
                                @break
                            @case('tiles')
                                <span class="bg-lib-fill bg-lib-tiles">
                                    @foreach($item['thumb']['tiles'] as $tileCss)
                                    <i style="background: {{ $tileCss }};"></i>
                                    @endforeach
                                </span>
                                @break
                            @case('torn')
                                <span class="bg-lib-fill" style="background: linear-gradient(140deg, {{ $item['thumb']['backdrop'][0] }}, {{ $item['thumb']['backdrop'][1] }});">
                                    @foreach($item['thumb']['sheets'] as $sheet)
                                    <i style="position:absolute;inset:0;clip-path:{{ $sheet['clip'] }};background: {{ \App\Modules\User\Support\TornStyleCatalog::shadeHex($item['thumb']['paper'], $sheet['shade']) }};"></i>
                                    @endforeach
                                </span>
                                @break
                            @default
                                <span class="bg-lib-fill" style="{{ $item['thumb']['css'] }}; background-size: cover; background-position: center;"></span>
                        @endswitch
                        <span class="bg-lib-tick" aria-hidden="true"><i class="fas fa-check"></i></span>
                    </button>
                    @endforeach
                </div>
                <p class="text-[10px] mt-1.5" style="color: var(--text-dimmed);">
                    Search covers every look at once, moods too, so &ldquo;pastel&rdquo; or &ldquo;warm&rdquo;
                    finds gradients. Options for the one you pick appear below.
                </p>
            </div>

            {{-- A tab whose options are not on screen says why, rather than
                 ending in blank space. The dot on the other tab is where the
                 page's background actually lives. --}}
            <p class="text-[10px] mt-2" style="color: var(--text-dimmed);"
               x-show="activeGroup !== 'style' && !typesIn(activeGroup).some(t => t.key === bgType)">
                Your page is using <span class="font-semibold" x-text="currentLabel()"></span>,
                over in <span class="font-semibold" x-text="currentGroupLabel()"></span>.
                Pick one above to change it.
            </p>

            <input type="hidden" name="background_type" :value="bgType">
            {{-- The chosen gradient preset lives at the top of the card, not
                 inside the Colour panel: the Style library sets it too now,
                 and one field written from two places needs one input. --}}
            <input type="hidden" name="gradient_preset_id" :value="gradientPresetId">
        </div>

        {{-- The generated thumbnail classes for the library's template
             entries. Lifted out of the old Template panel so the swatches
             paint wherever the library is shown. --}}
        <style>
        @foreach($bgTemplates as $tpl)
        {!! str_replace(['.bg-template-', 'position:fixed', 'position: fixed', 'z-index:-1', 'z-index: -1'], ['.bg-thumb-', 'position:absolute', 'position:absolute', 'z-index:0', 'z-index:0'], $tpl->css) !!}
        @endforeach
        </style>

        {{-- SOLID COLOR --}}
        <div x-show="panelFor('color')" x-transition class="space-y-3">
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Color</label>
                <div class="flex items-center gap-2">
                    <input type="color" name="background_color" value="{{ $bgColor }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bgColor }}</span>
                </div>
            </div>
        </div>

        {{-- GRADIENT -- a BUILDER, not a second library.
             The 166 presets that used to sit under here behind a chip row of
             their own now live in the Style library with every other
             ready-made look. What stays is the thing this tab is for:
             building one. The result comes first and the stops are the
             biggest control on it; the presets are one scrolling row of
             starting points at the bottom. --}}
        <div x-show="panelFor('gradient')" x-transition class="space-y-3">
            <div class="bg-grad-preview" :style="'background:' + buildGradientCSS()"></div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Type</label>
                    <select name="gradient_type" x-model="gradientType" @change="gradientPresetId = ''" class="theme-input w-full">
                        <option value="linear">Linear</option>
                        <option value="radial">Radial</option>
                        <option value="conic">Conic</option>
                    </select>
                </div>
                <div x-show="gradientType === 'linear' || gradientType === 'conic'">
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Angle <span class="opacity-60"><span x-text="gradientAngle"></span>&deg;</span></label>
                    <input type="range" name="gradient_angle" x-model="gradientAngle" @input="gradientPresetId = ''" min="0" max="360" class="w-full accent-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium mb-2" style="color: var(--text-muted);">Colour stops</label>
                <div class="space-y-2">
                    <template x-for="(stop, idx) in gradientStops" :key="idx">
                        <div class="flex items-center gap-2 p-2 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <input type="color" :value="stop.color" @input="stop.color = $event.target.value; gradientPresetId = ''" class="w-8 h-8 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                            <div class="flex-1">
                                <input type="range" :value="stop.pos" @input="stop.pos = parseInt($event.target.value); gradientPresetId = ''" min="0" max="100" class="w-full accent-indigo-500">
                            </div>
                            <span class="text-[10px] font-mono w-8 text-center" style="color: var(--text-faint);" x-text="stop.pos + '%'"></span>
                            <button type="button" @click="removeStop(idx)" x-show="gradientStops.length > 2" class="w-6 h-6 rounded flex items-center justify-center hover:bg-red-500/10 transition-colors" style="color: var(--text-faint);">
                                <i class="fas fa-times text-[9px]"></i>
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="addStop()" class="mt-2 text-[11px] font-semibold px-3 py-1.5 rounded-lg transition-all hover:bg-blue-500/10" style="color: #90acff; border: 1px dashed rgba(61,107,255,0.3);">
                    <i class="fas fa-plus text-[9px] mr-1"></i> Add stop
                </button>
                <input type="hidden" name="gradient_colors" :value="JSON.stringify(gradientStops)">
                <input type="hidden" name="background_gradient" :value="buildGradientCSS()">
            </div>

            @include('user.links.partials.gradient-catalog-picker')
        </div>

        {{-- IMAGE --}}
        <div x-show="panelFor('image')" x-transition class="space-y-3">
            @include('user.partials.dropzone-input', [
                'name'        => 'background_image',
                'label'       => 'Background Image',
                'policy'      => \App\Services\UploadPolicy::for('link.background_image', auth()->user()),
                'currentUrl'  => $bs['background_image'] ?? null,
                'currentName' => !empty($bs['background_image']) ? 'Saved background image' : null,
                'compact'     => true,
                'browseType'  => 'image',
                // The merged gallery below covers the same S3 folders, in the
                // right shape and by reference rather than by copy.
                'allowStock'  => false,
            ])

            {{-- THE IMAGE GALLERY (Task #6232) ------------------------------
                 One grid over all three curated S3 folders, replacing the
                 Stock tab above it and the separate "Or choose from our
                 gallery" accordion below. Two image pickers on one panel was
                 the same duplication the Style library removed -- and they
                 disagreed on shape too: Stock drew 152px squares while the
                 gallery drew 9/14 portraits, on the same screen.

                 9/14 wins, because that is the shape of the page the image
                 will fill. A square crop of a background is a preview of
                 something the user never gets.

                 Picking stores the S3 KEY, so the server resolves the public
                 CDN URL and nothing is copied into the user's vault -- the
                 gallery path already worked this way, and the Stock tab's
                 blob-copy did not. Now all three folders take the better one. --}}
            <div x-data="{
                    galShow: false,
                    galFolder: 'all',
                    galLoading: false,
                    galFailed: false,
                    galAssets: [],
                    galSearch: '',
                    galLimit: 48,
                    galSelected: '',
                    folders: @js(\App\Modules\User\Support\BackgroundImageGallery::FOLDERS),
                    async galLoad() {
                        this.galLoading = true; this.galFailed = false;
                        try {
                            const all = await Promise.all(Object.keys(this.folders).map(async (folder) => {
                                const r = await fetch('{{ route('user.platform-assets.index', '__F__') }}'.replace('__F__', folder), { headers: { 'Accept': 'application/json' } });
                                const j = await r.json();
                                if (!r.ok || !j || !j.success || !Array.isArray(j.assets)) return [];
                                return j.assets.map(a => ({ ...a, folder }));
                            }));
                            this.galAssets = all.flat();
                        } catch (e) { this.galFailed = true; }
                        this.galLoading = false;
                    },
                    galMatching() {
                        const q = this.galSearch.trim().toLowerCase();
                        return this.galAssets.filter(a =>
                            (this.galFolder === 'all' || a.folder === this.galFolder) &&
                            (!q || a.label.toLowerCase().includes(q))
                        );
                    },
                    galVisible() { return this.galMatching().slice(0, this.galLimit); },
                    galCountIn(folder) {
                        return folder === 'all'
                            ? this.galAssets.length
                            : this.galAssets.filter(a => a.folder === folder).length;
                    }
                }" class="space-y-2 pt-3" style="border-top: 1px solid var(--border-subtle);">
                <input type="hidden" name="background_image_asset" :value="galSelected">

                {{-- This tab is called "use your own", so the file goes
                     first and ours sits behind one line. It is still ONE
                     picker -- the duplicate Stock tab is gone -- and the
                     440 images are only fetched once someone asks for them,
                     which the always-open grid could not do. --}}
                <button type="button" class="bg-disclosure"
                        :aria-expanded="galShow ? 'true' : 'false'"
                        @click="galShow = !galShow; if (galShow && !galAssets.length && !galLoading) galLoad()">
                    <i class="fas fa-chevron-right text-[8px] bg-disclosure-caret" :class="galShow ? 'is-open' : ''"></i>
                    Or choose one of ours
                    <span class="opacity-60" x-text="galAssets.length ? galAssets.length + ' images' : ''"></span>
                </button>

                <div x-show="galShow" x-transition class="space-y-2">
                <div class="flex items-center justify-end gap-2 flex-wrap">
                    <input type="text" x-model="galSearch" placeholder="Search images…"
                           class="text-[11px] px-2 py-1 rounded-md flex-1 max-w-[190px]"
                           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                </div>

                <div class="bg-lib-chips" x-show="galAssets.length > 0">
                    <button type="button" @click="galFolder = 'all'; galLimit = 48"
                            class="bg-lib-chip" :class="galFolder === 'all' ? 'is-on' : ''">
                        All <span class="bg-lib-n" x-text="galCountIn('all')"></span>
                    </button>
                    <template x-for="(label, key) in folders" :key="key">
                        <button type="button" @click="galFolder = key; galLimit = 48"
                                x-show="galCountIn(key) > 0"
                                class="bg-lib-chip" :class="galFolder === key ? 'is-on' : ''">
                            <span x-text="label"></span> <span class="bg-lib-n" x-text="galCountIn(key)"></span>
                        </button>
                    </template>
                </div>

                <template x-if="galLoading">
                    <p class="text-[11px] text-center py-3" style="color: var(--text-dimmed);">Loading images…</p>
                </template>
                <template x-if="!galLoading && galFailed">
                    <p class="text-[11px] text-center py-3" style="color: var(--text-dimmed);">Couldn't load the gallery right now. Try again in a minute.</p>
                </template>
                <template x-if="!galLoading && !galFailed && galAssets.length === 0">
                    <p class="text-[11px] text-center py-3" style="color: var(--text-dimmed);">No gallery images available yet.</p>
                </template>

                <div class="bg-swatch-grid max-h-[380px] overflow-y-auto pr-1" x-show="galAssets.length > 0">
                    <template x-for="a in galVisible()" :key="a.key">
                        <button type="button"
                                @click="galSelected = galSelected === a.key ? '' : a.key; $nextTick(() => $dispatch('change'))"
                                :class="galSelected === a.key ? 'is-picked' : ''"
                                class="bg-lib-swatch"
                                :title="a.label">
                            <img :src="a.url" :alt="a.label" loading="lazy" class="bg-lib-fill" style="width:100%;height:100%;object-fit:cover;">
                            <span class="bg-lib-tick" aria-hidden="true"><i class="fas fa-check"></i></span>
                        </button>
                    </template>
                </div>

                <div class="flex items-center justify-between" x-show="galAssets.length > 0">
                    <p class="text-[10px]" style="color: var(--text-dimmed);">Click to select, click again to deselect. Save to apply.</p>
                    <button type="button" x-show="galMatching().length > galLimit" @click="galLimit += 48"
                            class="text-[10px] font-semibold px-2 py-1 rounded-md" style="color:#90acff; border: 1px dashed rgba(61,107,255,0.3);">
                        Show more
                    </button>
                </div>
                </div>
            </div>
        </div>

        {{-- SLIDESHOW --}}
        <div x-show="panelFor('slideshow')" x-transition class="space-y-3">
            <div>
                @include('user.partials.dropzone-input', [
                    'name'     => 'slideshow_images',
                    'label'    => 'Slideshow Images (up to 10)',
                    'policy'   => \App\Services\UploadPolicy::for('link.slideshow_image', auth()->user()),
                    'hint'     => 'Drop multiple images',
                    'compact'  => true,
                ])
                @if(!empty($slideshowImages))
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach($slideshowImages as $si => $sImg)
                    <div class="relative group">
                        <img src="{{ $sImg }}" class="w-14 h-14 rounded-lg object-cover" alt="Slide {{ $si+1 }}">
                        <label class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-red-500 flex items-center justify-center cursor-pointer opacity-0 group-hover:opacity-100 transition-opacity">
                            <input type="checkbox" name="remove_slideshow_images[]" value="{{ $si }}" class="hidden">
                            <i class="fas fa-times text-white text-[8px]"></i>
                        </label>
                    </div>
                    @endforeach
                </div>
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Click <i class="fas fa-times text-red-400"></i> on images to remove them on save.</p>
                @endif
            </div>
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Slide Interval (<span class="font-mono">{{ $slideshowInterval }}s</span>)</label>
                <input type="range" name="slideshow_interval" value="{{ $slideshowInterval }}" min="1" max="30" class="w-full accent-indigo-500" oninput="this.previousElementSibling.querySelector('span').textContent = this.value + 's'">
                <div class="flex justify-between text-[9px]" style="color: var(--text-dimmed);"><span>1s</span><span>30s</span></div>
            </div>
        </div>

        {{-- VIDEO --}}
        <div x-show="panelFor('video')" x-transition class="space-y-3">
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Video URL</label>
                <input type="url" name="video_url" value="{{ $videoUrl }}" class="theme-input w-full" placeholder="https://example.com/video.mp4">
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Direct link to MP4 or WebM file. YouTube/Vimeo links are not supported.</p>
            </div>
            @include('user.partials.dropzone-input', [
                'name'        => 'video_file',
                'label'       => 'Or Upload Video',
                'policy'      => \App\Services\UploadPolicy::for('link.video_file', auth()->user()),
                'currentUrl'  => null,
                'currentName' => $videoFile ? 'Saved video file' : null,
                'hint'        => 'Auto-plays muted on loop',
                'previewKind' => 'file',
                'compact'     => true,
            ])
        </div>

        {{-- TORN PAPER --}}
        <div x-show="panelFor('torn')" x-transition class="space-y-3">
            @include('user.partials.dropzone-input', [
                'name'        => 'torn_image',
                'label'       => 'Backdrop Photo',
                'policy'      => \App\Services\UploadPolicy::for('link.background_image', auth()->user()),
                'currentUrl'  => $bs['torn_image'] ?? null,
                'currentName' => !empty($bs['torn_image']) ? 'Saved backdrop photo' : null,
                'hint'        => 'Peeks out beyond the torn edge of the paper',
                'compact'     => true,
            ])
            {{-- The tear shape and the colourway are chosen together in the
                 library, because a shape with no colourway is not something
                 anyone picks. Both still store exactly what they always did:
                 the style KEY, whose clip paths resolve server-side, and the
                 three colours. The colours stay editable here afterwards. --}}
            <div x-data="{ tornStyle: @js($tornStyleVal) }"
                 @torn-style-combo.window="tornStyle = $event.detail"
                 @bg-pick.window="if ($event.detail.type === 'torn') tornStyle = $event.detail.style">
                <input type="hidden" name="torn_style" :value="tornStyle">
            </div>
            <div x-data="{ tornPaper: @js($tornPaperColor), tornBd: @js($tornBdColor), tornBd2: @js($tornBdColor2) }" class="space-y-3"
                 @bg-pick.window="if ($event.detail.type === 'torn') {
                    tornPaper = $event.detail.paper;
                    tornBd    = $event.detail.backdrop;
                    tornBd2   = $event.detail.backdrop2;
                 }">
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Paper Color</label>
                        <input type="color" name="torn_paper_color" x-model="tornPaper" class="w-10 h-10 rounded-lg cursor-pointer" style="border: 1px solid var(--border-subtle);">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Backdrop Color 1</label>
                        <input type="color" name="torn_backdrop_color" x-model="tornBd" class="w-10 h-10 rounded-lg cursor-pointer" style="border: 1px solid var(--border-subtle);">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Backdrop Color 2</label>
                        <input type="color" name="torn_backdrop_color2" x-model="tornBd2" class="w-10 h-10 rounded-lg cursor-pointer" style="border: 1px solid var(--border-subtle);">
                    </div>
                </div>
                <p class="text-[10px]" style="color: var(--text-dimmed);">The paper sheet tears away to reveal the backdrop. An uploaded photo wins over the backdrop colors; with neither, the fallback color shows beyond the tear.</p>
            </div>
        </div>

        {{-- TILES (Task #6204) --}}
        <div x-show="panelFor('tiles')" x-transition class="space-y-3"
             x-data="{ tilesPalette: @js($tilesPaletteVal), tilesLayout: @js($tilesLayoutVal), tilesAnimate: @js($tilesAnimateVal) }"
             @bg-pick.window="if ($event.detail.type === 'tiles') tilesPalette = $event.detail.value">
            <input type="hidden" name="tiles_palette" :value="tilesPalette">
            {{-- The palette is picked in the library; layout and animation are
                 settings on top of it, so they stay here. --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Layout</label>
                    {{-- No @change re-dispatch here: a native change event on a select already
     bubbles to the form's draft-preview listener, and re-dispatching a
     bubbling 'change' from inside a @change handler fires the same handler
     on the target again (.stop only blocks propagation), looping forever. --}}
                    <select name="tiles_layout" x-model="tilesLayout" class="theme-input w-full">
                        @foreach(\App\Modules\User\Support\TilesBgCatalog::LAYOUTS as $layoutKey => $layoutLabel)
                        <option value="{{ $layoutKey }}">{{ $layoutLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Animation</label>
                    <select name="tiles_animate" x-model="tilesAnimate" class="theme-input w-full">
                        <option value="0">Off</option>
                        <option value="1">Gentle pulse</option>
                    </select>
                </div>
            </div>
            <p class="text-[10px]" style="color: var(--text-dimmed);">A full-page grid of gradient tiles. The pulse animation is automatically disabled for visitors who prefer reduced motion.</p>
        </div>

        {{-- MESH -- the swatches moved into the library; the field it writes
             did not change, so pages saved before the merge still resolve. --}}
        <div x-show="panelFor('mesh')" x-transition class="space-y-3"
             x-data="{ meshPreset: @js($meshPresetVal) }"
             @bg-pick.window="if ($event.detail.type === 'mesh') meshPreset = $event.detail.value">
            <input type="hidden" name="mesh_preset" :value="meshPreset">
            <p class="text-[10px]" style="color: var(--text-dimmed);">Soft multi-point color blends.</p>
        </div>

        {{-- PATTERN --}}
        <div x-show="panelFor('pattern')" x-transition class="space-y-3"
             x-data="{ patternPreset: @js($patternPresetVal) }"
             @bg-pick.window="if ($event.detail.type === 'pattern') patternPreset = $event.detail.value">
            <input type="hidden" name="pattern_preset" :value="patternPreset">
            <p class="text-[10px]" style="color: var(--text-dimmed);">Subtle geometric textures.</p>
        </div>

        {{-- PRESET -- the swatches and the group chips moved into the
             library, where "Patterns" is one chip rather than a preset group
             that also had its own picker. Transparency is a setting on top of
             the chosen preset, so it stays. --}}
        <div x-show="panelFor('preset')" x-transition class="space-y-3"
             x-data="{ selectedKey: @js($bgPresetKey) }"
             @bg-pick.window="if ($event.detail.type === 'preset') selectedKey = $event.detail.value">
            {{-- The library dispatches a synthetic change event after picking so
                 the live draft-preview push fires: this hidden input is updated
                 via :value, which emits no input/change of its own. --}}
            <input type="hidden" name="bg_preset_key" :value="selectedKey">
            {{-- Preset transparency (Task #5970): fades the preset layer itself
                 (0 = invisible, 100 = fully opaque); page content is unaffected. --}}
            <div x-data="{ presetOpacity: {{ max(0, min(100, (int) ($bs['bg_preset_opacity'] ?? 100))) }} }">
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">
                    Preset Transparency <span class="opacity-60" x-text="presetOpacity + '%'"></span>
                </label>
                <input type="range" name="bg_preset_opacity" min="0" max="100" step="5" x-model="presetOpacity" class="w-full">
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Lower values fade the preset toward the fallback color behind it.</p>
            </div>
        </div>

        {{-- TEMPLATE -- there is no "Template" tab any more. These were
             never templates, they were a background library, and their six
             category chips were the other half of the duplication: Mesh and
             Patterns appeared here AND as pickers of their own. They are now
             ordinary entries in the one library. The stored field is
             unchanged, so every saved page still resolves. --}}
        <div x-show="panelFor('template')" x-transition
             x-data="{ selectedTpl: {{ $bgTemplateId ? (int) $bgTemplateId : "''" }} }"
             @bg-pick.window="if ($event.detail.type === 'template') selectedTpl = $event.detail.value">
            <input type="hidden" name="bg_template_id" :value="selectedTpl">
        </div>

        {{-- SHARED EFFECTS --}}
        <div class="pt-4" style="border-top: 1px solid var(--border-subtle);">
            <div class="flex items-center gap-2 mb-3">
                <i class="fas fa-sliders-h text-[10px] text-blue-400"></i>
                <span class="text-xs font-semibold" style="color: var(--text-primary);">Finish</span>
            </div>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Scrolling</label>
                        <div class="flex gap-2" x-data="{ attach: '{{ $bgAttachment }}' }">
                            <button type="button" @click="attach = 'fixed'" :class="attach === 'fixed' ? 'ring-2 ring-blue-500' : ''" class="flex-1 py-2 text-[10px] font-semibold rounded-lg transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
                                <i class="fas fa-thumbtack text-[9px] mr-1"></i> Fixed
                            </button>
                            <button type="button" @click="attach = 'scroll'" :class="attach === 'scroll' ? 'ring-2 ring-blue-500' : ''" class="flex-1 py-2 text-[10px] font-semibold rounded-lg transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
                                <i class="fas fa-arrows-alt-v text-[9px] mr-1"></i> Scroll
                            </button>
                            <input type="hidden" name="bg_attachment" :value="attach">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Colour behind it</label>
                        <div class="flex items-center gap-2">
                            <input type="color" name="bg_fallback_color" value="{{ $bgFallbackColor }}" class="w-8 h-8 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                            <span class="text-[10px] font-mono" style="color: var(--text-faint);">{{ $bgFallbackColor }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Blur Effect (<span class="font-mono">{{ $bgBlur }}px</span>)</label>
                    <input type="range" name="bg_blur" value="{{ $bgBlur }}" min="0" max="100" class="w-full accent-indigo-500" oninput="this.previousElementSibling.querySelector('span').textContent = this.value + 'px'">
                    <div class="flex justify-between text-[9px]" style="color: var(--text-dimmed);"><span>None</span><span>Heavy blur</span></div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Dim colour</label>
                        <div class="flex items-center gap-2">
                            <input type="color" name="bg_overlay_color" value="{{ $bgOverlayColor }}" class="w-8 h-8 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                            <span class="text-[10px] font-mono" style="color: var(--text-faint);">{{ $bgOverlayColor }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Dim (<span class="font-mono">{{ $bgOverlayOpacity }}%</span>)</label>
                        <input type="range" name="bg_overlay_opacity" value="{{ $bgOverlayOpacity }}" min="0" max="100" class="w-full accent-indigo-500" oninput="this.previousElementSibling.querySelector('span').textContent = this.value + '%'">
                    </div>
                </div>

                <div x-show="bgType === 'image' || bgType === 'slideshow' || bgType === 'video'" x-transition>
                    @include('user.partials.dropzone-input', [
                        'name'        => 'bg_fallback_image',
                        'label'       => 'Fallback Image',
                        'policy'      => \App\Services\UploadPolicy::for('link.bg_fallback_image', auth()->user()),
                        'currentUrl'  => $bgFallbackImage ?: null,
                        'currentName' => $bgFallbackImage ? 'Saved fallback' : null,
                        'hint'        => 'Shown while media loads or if it fails',
                        'compact'     => true,
                    ])
                </div>
            </div>
        </div>
    </div>
</div>

@once
<style>
    /* One rule for every swatch grid in this card.
     *
     * All five pickers already draw a 9/14 swatch, so the aspect was never
     * the problem -- the column counts were, and they had drifted apart as
     * each picker was added: Pattern at 6 columns, Tiles at 7, Mesh at 10,
     * Presets and Templates at 12. Same markup, same aspect, and a Pattern
     * swatch rendering at 102px next to a Template swatch at 50px.
     *
     * auto-fill with a min track size means one rule fits every picker and
     * every width: the swatch keeps a usable size and the column count falls
     * out of the space available, so a new picker cannot drift again.
     */
    /* Three-way switch over the background kinds. */
    .bg-group-switch {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 3px;
        padding: 3px;
        border-radius: 12px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
    }
    .bg-group-btn {
        position: relative;
        display: block;
        width: 100%;
        padding: 7px 6px 8px;
        border: 0;
        border-radius: 9px;
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
        transition: background .15s ease, color .15s ease;
    }
    .bg-group-btn:hover { color: var(--text-dimmed); }
    .bg-group-btn.is-on {
        background: var(--bg-glass);
        color: var(--text-primary);
        box-shadow: inset 0 0 0 1px var(--border-glass);
    }
    .bg-group-name { display: block; font-size: 12px; font-weight: 600; line-height: 1.25; }
    .bg-group-btn.is-on .bg-group-name { font-weight: 700; }
    /* What the tab is FOR. Colour and Style looked identical without it. */
    .bg-group-sub {
        display: block;
        font-size: 9.5px;
        font-weight: 500;
        line-height: 1.3;
        margin-top: 1px;
        opacity: .62;
    }

    /* ---- Type row --------------------------------------------------- */
    /* Two or three unlike things, named. The old six-up grid of icon
       tiles was sized for six; Colour only ever had two in it, and a
       9px caption under a 28px icon is not what a choice looks like. */
    .bg-type-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(118px, 1fr));
        gap: 8px;
    }
    .bg-type-card {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 9px 11px;
        border-radius: 12px;
        text-align: left;
        cursor: pointer;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        transition: border-color .15s ease, background .15s ease;
    }
    .bg-type-card:hover { border-color: rgba(61,107,255,.35); }
    .bg-type-card:focus-visible { outline: 2px solid #5c83ff; outline-offset: 2px; }
    .bg-type-card.is-on {
        border-color: rgba(61,107,255,.55);
        background: rgba(61,107,255,.09);
    }
    .bg-type-chip {
        flex: 0 0 auto;
        width: 26px;
        height: 26px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .bg-type-label { font-size: 12px; font-weight: 600; color: var(--text-muted); }
    .bg-type-card.is-on .bg-type-label { color: #90acff; font-weight: 700; }
    html.light-mode .bg-type-card.is-on .bg-type-label { color: #2544b8; }

    /* ---- Gradient builder ------------------------------------------- */
    /* The result, before the controls that make it. */
    .bg-grad-preview {
        height: 64px;
        border-radius: 14px;
        border: 1px solid var(--border-glass);
    }
    /* Starting points, one row. A grid here is what made Colour look like
       a second copy of the Style library. */
    .bg-quick-strip {
        display: flex;
        gap: 6px;
        overflow-x: auto;
        /* overflow-x also clips vertically, so leave room for the selected
           swatch's ring and the hover lift. */
        padding: 3px 0 7px;
    }
    .bg-quick-strip .bg-lib-swatch {
        flex: 0 0 auto;
        width: 46px;
    }
    .bg-quick-strip .bg-lib-swatch:hover { transform: scale(1.06); }

    /* ---- Disclosure -------------------------------------------------- */
    .bg-disclosure {
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 0;
        border: 0;
        background: transparent;
        font-size: 11.5px;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
    }
    .bg-disclosure:hover { color: var(--text-primary); }
    .bg-disclosure-caret { transition: transform .15s ease; }
    .bg-disclosure-caret.is-open { transform: rotate(90deg); }
    /* Marks the group the saved background belongs to, so switching tabs
       never loses track of which one is actually in use. */
    .bg-group-dot {
        position: absolute;
        top: 6px;
        right: 8px;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #5c83ff;
    }
    .bg-swatch-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(58px, 1fr));
        gap: 6px;
    }
    @media (min-width: 640px) {
        .bg-swatch-grid { grid-template-columns: repeat(auto-fill, minmax(66px, 1fr)); }
    }

    /* ---- Style library (Task #6231) ---------------------------------- */
    /* Chips scroll on a phone and wrap once there is room, so all nine
       categories are visible at once rather than hiding the last few off
       the right edge -- which is how "Tiles" and "Torn paper" went unseen. */
    .bg-lib-chips { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 6px; }
    @media (min-width: 640px) {
        .bg-lib-chips { flex-wrap: wrap; overflow: visible; }
    }
    .bg-lib-chip {
        font-size: 11px;
        font-weight: 600;
        padding: 5px 11px;
        border-radius: 999px;
        white-space: nowrap;
        cursor: pointer;
        transition: background .15s ease, color .15s ease, border-color .15s ease;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-muted);
    }
    .bg-lib-chip:hover { color: var(--text-primary); }
    .bg-lib-chip.is-on {
        background: rgba(61, 107, 255, 0.25);
        border-color: rgba(61, 107, 255, 0.5);
        color: #bccfff;
    }
    html.light-mode .bg-lib-chip.is-on {
        background: rgba(61, 107, 255, 0.12);
        color: #2544b8;
    }
    .bg-lib-n { opacity: .6; font-weight: 500; margin-left: 2px; }

    .bg-lib-swatch {
        position: relative;
        width: 100%;
        aspect-ratio: 9 / 14;
        padding: 0;
        border: 1px solid var(--border-glass);
        border-radius: 6px;
        overflow: hidden;
        cursor: pointer;
        background: var(--bg-glass-input);
        transition: transform .12s ease, box-shadow .12s ease;
    }
    .bg-lib-swatch:hover { transform: scale(1.08); z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,.4); }
    .bg-lib-swatch:focus-visible { outline: 2px solid #5c83ff; outline-offset: 2px; }
    .bg-lib-swatch.is-picked { box-shadow: 0 0 0 2px rgba(144,172,255,.95), 0 4px 12px rgba(0,0,0,.4); }
    .bg-lib-fill { position: absolute; inset: 0; display: block; }
    .bg-lib-tiles { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; padding: 2px; }
    .bg-lib-tiles i { display: block; border-radius: 2px; }
    .bg-lib-tick {
        position: absolute;
        top: 2px;
        right: 2px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(61,107,255,.95);
        color: #fff;
        font-size: 6px;
    }
    .bg-lib-swatch.is-picked .bg-lib-tick { display: flex; }
</style>
<script>
function bgSettings() {
    return {
        bgType: @json($bgType),
        bgPresetKey: @json($bgPresetKey),
        gradientType: @json($gradientTypeVal),
        gradientAngle: @json((int) $gradientAngle),
        gradientStops: @json($gradientColors),
        // Hoisted out of the gradient picker: the Style library writes this
        // too now, and a field written from two places needs one home.
        gradientPresetId: @json($gradientPresetIdVal),
        // The sub-line is what stops Colour and Style reading as the same
        // feature: one BUILDS a background, the other PICKS a finished one.
        groups: [
            { key: 'colour', label: 'Colour', sub: 'build one' },
            { key: 'style',  label: 'Style',  sub: 'pick one' },
            { key: 'media',  label: 'Media',  sub: 'use your own' },
        ],
        // Style's six entries no longer render as tiles -- the library
        // replaced them -- but they stay in this list because it is what maps
        // a saved background_type back to its group, so opening the panel on
        // a saved template still lands on Style rather than a default tab.
        types: [
            { key: 'color',     group: 'colour', label: 'Solid colour', icon: 'fa-fill',   preview: 'linear-gradient(135deg, #2139a1, #3b0764)' },
            { key: 'gradient',  group: 'colour', label: 'Gradient',    icon: 'fa-rainbow', preview: 'linear-gradient(135deg, #ec4899, #5c83ff, #06b6d4)' },

            { key: 'template',  group: 'style',  label: 'Template',    icon: 'fa-magic',   preview: 'linear-gradient(135deg, #0f0c29, #302b63)' },
            { key: 'preset',    group: 'style',  label: 'Presets',     icon: 'fa-th-large', preview: 'linear-gradient(135deg, #f97316, #ec4899, #06b6d4)' },
            { key: 'mesh',      group: 'style',  label: 'Mesh',        icon: 'fa-braille', preview: 'radial-gradient(circle at 30% 30%, #22d3ee, transparent 60%), radial-gradient(circle at 75% 70%, #a78bfa, transparent 60%), #0b1026' },
            { key: 'pattern',   group: 'style',  label: 'Pattern',     icon: 'fa-th',      preview: 'repeating-linear-gradient(45deg, #4338ca 0 4px, #1e1b4b 4px 10px)' },
            { key: 'tiles',     group: 'style',  label: 'Tiles',       icon: 'fa-border-all', preview: 'conic-gradient(from 45deg, #1d4ed8 25%, #0ea5e9 25% 50%, #312e81 50% 75%, #334155 75%)' },
            { key: 'torn',      group: 'style',  label: 'Torn Paper',  icon: 'fa-scroll',  preview: 'linear-gradient(115deg, #cfe0e6 0%, #cfe0e6 60%, #5d7d8e 60%)' },

            { key: 'image',     group: 'media',  label: 'Image',       icon: 'fa-image',   preview: 'rgba(99,102,241,0.15)' },
            { key: 'slideshow', group: 'media',  label: 'Slideshow',   icon: 'fa-images',  preview: 'rgba(236,72,153,0.15)' },
            { key: 'video',     group: 'media',  label: 'Video',       icon: 'fa-film',    preview: 'rgba(61,107,255,0.15)' }
        ],
        typesIn(group) { return this.types.filter(t => t.group === group); },
        /**
         * Whether a type's options pane belongs on screen.
         *
         * The panes used to key on the saved type ALONE, so opening Media
         * while the page was on a gradient drew Media's three tiles with the
         * whole Colour builder underneath them -- two tabs' worth of
         * controls in one tab, which is the confusion this card has been
         * working its way out of. A tab shows its own options or none.
         *
         * Switching tabs still changes nothing that is saved: the hidden
         * inputs are inside these panes and x-show only hides them.
         */
        panelFor(type) {
            const t = this.types.find(x => x.key === type);
            return this.bgType === type && (!t || t.group === this.activeGroup);
        },
        /** What the page is using, for the note shown on an empty tab. */
        currentLabel() {
            return (this.types.find(t => t.key === this.bgType) || {}).label || '';
        },
        currentGroupLabel() {
            const t = this.types.find(x => x.key === this.bgType);
            return t ? (this.groups.find(g => g.key === t.group) || {}).label : '';
        },
        // Opens on the group holding the saved background, so the panel
        // always shows what is actually in use rather than a default tab.
        activeGroup: (function () { return 'style'; })(),
        syncActiveGroup() {
            const t = this.types.find(t => t.key === this.bgType);
            if (t) this.activeGroup = t.group;
        },
        init() {
            this.syncActiveGroup();
            if (!this.gradientStops || this.gradientStops.length < 2) {
                this.gradientStops = [
                    { color: '#0a0612', pos: 0 },
                    { color: '#1a0533', pos: 50 },
                    { color: '#0a0612', pos: 100 }
                ];
            }
        },
        addStop() {
            if (this.gradientStops.length >= 10) return;
            var last = this.gradientStops[this.gradientStops.length - 1];
            this.gradientStops.push({ color: '#5c83ff', pos: Math.min(100, (last ? last.pos : 50) + 10) });
        },
        removeStop(idx) {
            if (this.gradientStops.length <= 2) return;
            this.gradientStops.splice(idx, 1);
        },
        buildGradientCSS() {
            var stops = this.gradientStops.slice().sort(function(a, b) { return a.pos - b.pos; });
            var stopsStr = stops.map(function(s) { return s.color + ' ' + s.pos + '%'; }).join(', ');
            if (this.gradientType === 'radial') return 'radial-gradient(circle, ' + stopsStr + ')';
            if (this.gradientType === 'conic')  return 'conic-gradient(from ' + this.gradientAngle + 'deg, ' + stopsStr + ')';
            return 'linear-gradient(' + this.gradientAngle + 'deg, ' + stopsStr + ')';
        }
    };
}
</script>
@endonce
