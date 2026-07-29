{{--
    Reusable "Page background" card extracted from settings/appearance.blade.php
    so the slides + conversational editors can expose the same color / gradient
    / image / slideshow / video / template controls without re-implementing the
    Alpine state machine. Renders ONLY the inner card markup — the parent view
    wraps it in whatever <form> + submit handler is appropriate for that page.

    Required vars: $link
    Optional:      $bgTemplates (Collection of \App\Modules\Admin\Models\BgTemplate)
                   — auto-loaded from the database if not passed in.
--}}
@php
    use App\Modules\User\Support\BgPresetCatalog;
    $bs = $link->settings['biolink'] ?? [];
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
    $bgPresets         = BgPresetCatalog::all();
    $bgPresetGroups    = BgPresetCatalog::GROUPS;

    // Lazy-load bg templates if the parent didn't pass them in.
    $bgTemplates = $bgTemplates ?? \App\Modules\Admin\Models\BgTemplate::active()->get();

    $tplCategories = $bgTemplates->groupBy(fn ($t) => $t->category ?: 'pattern')->map->count();
    $tplCategoryLabels = [
        'animated' => 'Animated',
        'gradient' => 'Gradients',
        'mesh'     => 'Mesh',
        'pattern'  => 'Patterns',
        'svg'      => 'SVG',
        'neon'     => 'Neon',
    ];
@endphp

<div class="card-premium p-6" x-data="bgSettings()" x-init="init()">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(99,102,241,0.1);"><i class="fas fa-fill-drip text-indigo-400 text-xs"></i></div>
        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Page background</h3>
    </div>

    <div class="space-y-5">
        <div>
            <label class="block text-xs font-medium mb-2" style="color: var(--text-muted);">Background Type</label>
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                <template x-for="t in types" :key="t.key">
                    <button type="button" @click="bgType = t.key"
                        :class="bgType === t.key ? 'ring-2 ring-blue-500' : ''"
                        class="flex flex-col items-center gap-1 p-2.5 rounded-xl transition-all text-center"
                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"
                        :style="bgType === t.key ? 'border-color: rgba(61,107,255,0.5); background: rgba(61,107,255,0.08);' : ''">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" :style="'background:' + t.preview">
                            <i :class="'fas ' + t.icon" class="text-[9px] text-white/80"></i>
                        </div>
                        <span class="text-[9px] font-semibold leading-tight" style="color: var(--text-muted);" x-text="t.label"></span>
                    </button>
                </template>
            </div>
            <input type="hidden" name="background_type" :value="bgType">
        </div>

        {{-- SOLID COLOR --}}
        <div x-show="bgType === 'color'" x-transition class="space-y-3">
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Color</label>
                <div class="flex items-center gap-2">
                    <input type="color" name="background_color" value="{{ $bgColor }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $bgColor }}</span>
                </div>
            </div>
        </div>

        {{-- GRADIENT --}}
        <div x-show="bgType === 'gradient'" x-transition class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Gradient Type</label>
                    <select name="gradient_type" x-model="gradientType" class="theme-input w-full">
                        <option value="linear">Linear</option>
                        <option value="radial">Radial</option>
                        <option value="conic">Conic</option>
                    </select>
                </div>
                <div x-show="gradientType === 'linear' || gradientType === 'conic'">
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Angle (<span x-text="gradientAngle"></span>&deg;)</label>
                    <input type="range" name="gradient_angle" x-model="gradientAngle" min="0" max="360" class="w-full accent-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium mb-2" style="color: var(--text-muted);">Color Stops</label>
                <div class="h-8 rounded-xl mb-3" :style="'background:' + buildGradientCSS()"></div>
                <div class="space-y-2">
                    <template x-for="(stop, idx) in gradientStops" :key="idx">
                        <div class="flex items-center gap-2 p-2 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <input type="color" :value="stop.color" @input="stop.color = $event.target.value" class="w-8 h-8 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                            <div class="flex-1">
                                <input type="range" :value="stop.pos" @input="stop.pos = parseInt($event.target.value)" min="0" max="100" class="w-full accent-indigo-500">
                            </div>
                            <span class="text-[10px] font-mono w-8 text-center" style="color: var(--text-faint);" x-text="stop.pos + '%'"></span>
                            <button type="button" @click="removeStop(idx)" x-show="gradientStops.length > 2" class="w-6 h-6 rounded flex items-center justify-center hover:bg-red-500/10 transition-colors" style="color: var(--text-faint);">
                                <i class="fas fa-times text-[9px]"></i>
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="addStop()" class="mt-2 text-[11px] font-semibold px-3 py-1.5 rounded-lg transition-all hover:bg-blue-500/10" style="color: #90acff; border: 1px dashed rgba(61,107,255,0.3);">
                    <i class="fas fa-plus text-[9px] mr-1"></i> Add Color Stop
                </button>
                <input type="hidden" name="gradient_colors" :value="JSON.stringify(gradientStops)">
                <input type="hidden" name="background_gradient" :value="buildGradientCSS()">
            </div>

            <div class="mt-4">
                @include('user.links.partials.gradient-catalog-picker')
            </div>
        </div>

        {{-- IMAGE --}}
        <div x-show="bgType === 'image'" x-transition class="space-y-3">
            @include('user.partials.dropzone-input', [
                'name'        => 'background_image',
                'label'       => 'Background Image',
                'policy'      => \App\Services\UploadPolicy::for('link.background_image', auth()->user()),
                'currentUrl'  => $bs['background_image'] ?? null,
                'currentName' => !empty($bs['background_image']) ? 'Saved background image' : null,
                'compact'     => true,
                'browseType'  => 'image',
            ])

            {{-- Curated background gallery (Task #6015) — platform-provided
                 photos listed live from S3, available on every plan. Picking
                 one submits its S3 key via the hidden input; the server
                 resolves + stores the public CDN URL (an uploaded file, if
                 any, still wins server-side). --}}
            <div x-data="{
                    galOpen: false,
                    galLoading: false,
                    galFailed: false,
                    galAssets: [],
                    galSearch: '',
                    galLimit: 36,
                    galSelected: '',
                    async galLoad() {
                        this.galOpen = !this.galOpen;
                        if (!this.galOpen || this.galAssets.length || this.galLoading) return;
                        this.galLoading = true; this.galFailed = false;
                        try {
                            const r = await fetch('{{ route('user.platform-assets.index', 'biolink-backgrounds') }}', { headers: { 'Accept': 'application/json' } });
                            const j = await r.json();
                            this.galAssets = (j && j.success && Array.isArray(j.assets)) ? j.assets : [];
                            this.galFailed = !r.ok;
                        } catch (e) { this.galFailed = true; }
                        this.galLoading = false;
                    },
                    galVisible() {
                        const q = this.galSearch.trim().toLowerCase();
                        const all = q ? this.galAssets.filter(a => a.label.toLowerCase().includes(q)) : this.galAssets;
                        return all.slice(0, this.galLimit);
                    },
                    galCount() {
                        const q = this.galSearch.trim().toLowerCase();
                        return q ? this.galAssets.filter(a => a.label.toLowerCase().includes(q)).length : this.galAssets.length;
                    }
                }" class="space-y-2">
                <input type="hidden" name="background_image_asset" :value="galSelected">
                <button type="button" @click="galLoad()"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl transition-all"
                        style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                    <span class="text-xs font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-images text-blue-400 text-[10px] mr-1.5"></i> Or choose from our gallery
                    </span>
                    <i class="fas text-[10px]" :class="galOpen ? 'fa-chevron-up' : 'fa-chevron-down'" style="color: var(--text-faint);"></i>
                </button>
                <div x-show="galOpen" x-transition class="space-y-2" style="display: none;">
                    <template x-if="galLoading"><p class="text-[11px] text-center py-3" style="color: var(--text-dimmed);">Loading gallery…</p></template>
                    <template x-if="!galLoading && galFailed"><p class="text-[11px] text-center py-3" style="color: var(--text-dimmed);">Couldn't load the gallery right now. Try again in a minute.</p></template>
                    <template x-if="!galLoading && !galFailed && galAssets.length === 0"><p class="text-[11px] text-center py-3" style="color: var(--text-dimmed);">No gallery backgrounds available yet.</p></template>
                    <template x-if="galAssets.length > 0">
                        <div class="space-y-2">
                            <input type="text" x-model="galSearch" placeholder="Search backgrounds…"
                                   class="text-[11px] px-2.5 py-1.5 rounded-md w-full"
                                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                            <div class="grid grid-cols-4 sm:grid-cols-6 gap-1.5 max-h-[380px] overflow-y-auto pr-1">
                                <template x-for="a in galVisible()" :key="a.key">
                                    <button type="button"
                                            @click="galSelected = galSelected === a.key ? '' : a.key; $nextTick(() => $dispatch('change'))"
                                            :class="galSelected === a.key ? 'ring-2 ring-blue-400' : ''"
                                            class="rounded-md overflow-hidden relative transition-all hover:scale-[1.05] hover:z-10"
                                            style="aspect-ratio: 9/14; border: 1px solid var(--border-glass);"
                                            :title="a.label">
                                        <img :src="a.url" :alt="a.label" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
                                        <div x-show="galSelected === a.key"
                                             class="absolute top-0.5 right-0.5 w-3.5 h-3.5 rounded-full flex items-center justify-center"
                                             style="background: rgba(61,107,255,0.95); color:#fff;">
                                            <i class="fas fa-check" style="font-size:6px;"></i>
                                        </div>
                                    </button>
                                </template>
                            </div>
                            <div class="flex items-center justify-between">
                                <p class="text-[10px]" style="color: var(--text-dimmed);">Click to select, click again to deselect. Save to apply.</p>
                                <button type="button" x-show="galCount() > galLimit" @click="galLimit += 36"
                                        class="text-[10px] font-semibold px-2 py-1 rounded-md" style="color:#90acff; border: 1px dashed rgba(61,107,255,0.3);">
                                    Show more
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- SLIDESHOW --}}
        <div x-show="bgType === 'slideshow'" x-transition class="space-y-3">
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
        <div x-show="bgType === 'video'" x-transition class="space-y-3">
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
        <div x-show="bgType === 'torn'" x-transition class="space-y-3">
            @include('user.partials.dropzone-input', [
                'name'        => 'torn_image',
                'label'       => 'Backdrop Photo',
                'policy'      => \App\Services\UploadPolicy::for('link.background_image', auth()->user()),
                'currentUrl'  => $bs['torn_image'] ?? null,
                'currentName' => !empty($bs['torn_image']) ? 'Saved backdrop photo' : null,
                'hint'        => 'Peeks out beyond the torn edge of the paper',
                'compact'     => true,
            ])
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Paper Color</label>
                <div class="flex items-center gap-2">
                    <input type="color" name="torn_paper_color" value="{{ $tornPaperColor }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $tornPaperColor }}</span>
                </div>
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">A solid paper sheet with a jagged torn right edge sits over the backdrop photo. If no photo is uploaded, the fallback color shows beyond the tear.</p>
            </div>
        </div>

        {{-- PRESET --}}
        <div x-show="bgType === 'preset'" x-transition class="space-y-3"
             x-data="{ presetGroup: 'gradients', presetSearch: '', selectedKey: @js($bgPresetKey) }">
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <label class="block text-xs font-medium" style="color: var(--text-muted);">Choose a Preset <span class="opacity-60">({{ count($bgPresets) }})</span></label>
                <input type="text" x-model="presetSearch" placeholder="Search…"
                       class="text-[11px] px-2 py-1 rounded-md flex-1 max-w-[160px]"
                       style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
            </div>
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 -mx-1 px-1">
                @foreach($bgPresetGroups as $groupKey => $groupLabel)
                <button type="button" @click="presetGroup = '{{ $groupKey }}'"
                        class="text-[11px] font-semibold px-2.5 py-1 rounded-full whitespace-nowrap transition-all"
                        :style="presetGroup === '{{ $groupKey }}' ? 'background: rgba(61,107,255,0.25); color:#bccfff; border:1px solid rgba(61,107,255,0.5)' : 'background: var(--bg-glass-input); color: var(--text-muted); border:1px solid var(--border-glass)'">
                    {{ $groupLabel }}
                    <span class="opacity-60">{{ collect($bgPresets)->where('group', $groupKey)->count() }}</span>
                </button>
                @endforeach
            </div>
            <input type="hidden" name="bg_preset_key" :value="selectedKey">
            <div class="grid grid-cols-6 xs:grid-cols-7 sm:grid-cols-9 md:grid-cols-10 lg:grid-cols-12 gap-1 max-h-[480px] overflow-y-auto pr-1">
                @foreach($bgPresets as $presetId => $preset)
                <button type="button"
                        x-show="(presetGroup === '{{ $preset['group'] }}') && (!presetSearch || '{{ strtolower($preset['label']) }}'.includes(presetSearch.toLowerCase()))"
                        {{-- $dispatch bubbles a synthetic change event up to the form so the
                             live draft-preview push fires (the hidden bg_preset_key input is
                             updated via :value binding, which emits no input/change events). --}}
                        @click="selectedKey = selectedKey === '{{ $presetId }}' ? '' : '{{ $presetId }}'; $nextTick(() => $dispatch('change'))"
                        :class="selectedKey === '{{ $presetId }}' ? 'ring-2 ring-blue-400 ring-offset-1 ring-offset-transparent' : ''"
                        class="rounded-md overflow-hidden relative transition-all hover:scale-[1.08] hover:z-10 hover:shadow-lg"
                        style="{{ $preset['css'] }}; width:100%; aspect-ratio:9/14; border:1px solid var(--border-glass); background-size: cover; background-position: center;"
                        title="{{ $preset['label'] }}">
                    <div x-show="selectedKey === '{{ $presetId }}'"
                         class="absolute top-0.5 right-0.5 w-3.5 h-3.5 rounded-full flex items-center justify-center"
                         style="background: rgba(61,107,255,0.95); color:#fff; font-size:7px;">
                        <i class="fas fa-check" style="font-size:6px;"></i>
                    </div>
                </button>
                @endforeach
            </div>
            <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">
                Click a swatch to select it. Click again to deselect.
            </p>
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

        {{-- TEMPLATE --}}
        <div x-show="bgType === 'template'" x-transition class="space-y-3"
             x-data="{ tplCat: 'all', tplSearch: '', selectedTpl: {{ $bgTemplateId ?? 'null' }} }">
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <label class="block text-xs font-medium" style="color: var(--text-muted);">Choose a Template <span class="opacity-60">({{ $bgTemplates->count() }})</span></label>
                <input type="text" x-model="tplSearch" placeholder="Search…"
                       class="text-[11px] px-2 py-1 rounded-md flex-1 max-w-[160px]"
                       style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
            </div>
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 -mx-1 px-1">
                <button type="button" @click="tplCat = 'all'"
                        class="text-[11px] font-semibold px-2.5 py-1 rounded-full whitespace-nowrap transition-all"
                        :style="tplCat === 'all' ? 'background: rgba(61,107,255,0.25); color:#bccfff; border:1px solid rgba(61,107,255,0.5)' : 'background: var(--bg-glass-input); color: var(--text-muted); border:1px solid var(--border-glass)'">
                    All <span class="opacity-60">{{ $bgTemplates->count() }}</span>
                </button>
                @foreach($tplCategoryLabels as $catKey => $catLabel)
                    @if(($tplCategories[$catKey] ?? 0) > 0)
                    <button type="button" @click="tplCat = '{{ $catKey }}'"
                            class="text-[11px] font-semibold px-2.5 py-1 rounded-full whitespace-nowrap transition-all"
                            :style="tplCat === '{{ $catKey }}' ? 'background: rgba(61,107,255,0.25); color:#bccfff; border:1px solid rgba(61,107,255,0.5)' : 'background: var(--bg-glass-input); color: var(--text-muted); border:1px solid var(--border-glass)'">
                        {{ $catLabel }} <span class="opacity-60">{{ $tplCategories[$catKey] }}</span>
                    </button>
                    @endif
                @endforeach
            </div>
            <style>
            @foreach($bgTemplates as $tpl)
            {!! str_replace(['.bg-template-', 'position:fixed', 'position: fixed', 'z-index:-1', 'z-index: -1'], ['.bg-thumb-', 'position:absolute', 'position:absolute', 'z-index:0', 'z-index:0'], $tpl->css) !!}
            @endforeach
            </style>
            <div class="grid grid-cols-6 xs:grid-cols-7 sm:grid-cols-9 md:grid-cols-10 lg:grid-cols-12 gap-1 max-h-[560px] overflow-y-auto pr-1">
                @foreach($bgTemplates as $tpl)
                @php
                    $tplCat = $tpl->category ?: 'pattern';
                    $previewIsDecl = str_contains($tpl->preview_color, ':');
                    $previewBg = $previewIsDecl ? '#0f172a' : $tpl->preview_color;
                @endphp
                <label class="cursor-pointer group block"
                       title="{{ $tpl->name }}"
                       x-show="(tplCat === 'all' || tplCat === '{{ $tplCat }}') && (!tplSearch || '{{ strtolower(addslashes($tpl->name)) }}'.includes(tplSearch.toLowerCase()))">
                    <input type="radio" name="bg_template_id" value="{{ $tpl->id }}" {{ $bgTemplateId == $tpl->id ? 'checked' : '' }} class="hidden peer" @click="selectedTpl = {{ $tpl->id }}">
                    <div class="rounded-md overflow-hidden relative transition-all hover:scale-[1.08] hover:z-10 hover:shadow-lg peer-checked:ring-2 peer-checked:ring-blue-400 peer-checked:ring-offset-1 peer-checked:ring-offset-transparent"
                         style="width:100%;aspect-ratio:9/14;background:{{ $previewBg }};border:1px solid var(--border-glass);"
                         :style="{ boxShadow: selectedTpl === {{ $tpl->id }} ? '0 0 0 2px rgba(144,172,255,0.9), 0 4px 12px rgba(0,0,0,.4)' : '' }">
                        <div class="bg-thumb-{{ $tpl->slug }}" style="position:absolute;inset:0;"></div>
                        <div class="absolute top-0.5 right-0.5 w-3.5 h-3.5 rounded-full items-center justify-center hidden peer-checked:flex"
                             style="background: rgba(61,107,255,0.95); color:#fff; font-size:7px;"
                             :class="selectedTpl === {{ $tpl->id }} ? '!flex' : ''">
                            <i class="fas fa-check" style="font-size:6px;"></i>
                        </div>
                    </div>
                </label>
                @endforeach
            </div>
            @if($bgTemplates->isEmpty())
            <p class="text-[11px] p-3 rounded-lg text-center" style="color: var(--text-dimmed); background: var(--bg-glass);">No templates available yet.</p>
            @endif
        </div>

        {{-- SHARED EFFECTS --}}
        <div class="pt-4" style="border-top: 1px solid var(--border-subtle);">
            <div class="flex items-center gap-2 mb-3">
                <i class="fas fa-sliders-h text-[10px] text-blue-400"></i>
                <span class="text-xs font-semibold" style="color: var(--text-primary);">Background Effects</span>
            </div>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Position</label>
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
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Fallback Color</label>
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
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Overlay Color</label>
                        <div class="flex items-center gap-2">
                            <input type="color" name="bg_overlay_color" value="{{ $bgOverlayColor }}" class="w-8 h-8 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                            <span class="text-[10px] font-mono" style="color: var(--text-faint);">{{ $bgOverlayColor }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Overlay Opacity (<span class="font-mono">{{ $bgOverlayOpacity }}%</span>)</label>
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
<script>
function bgSettings() {
    return {
        bgType: @json($bgType),
        bgPresetKey: @json($bgPresetKey),
        gradientType: @json($gradientTypeVal),
        gradientAngle: @json((int) $gradientAngle),
        gradientStops: @json($gradientColors),
        types: [
            { key: 'color',     label: 'Solid Color', icon: 'fa-fill',    preview: 'linear-gradient(135deg, #2139a1, #3b0764)' },
            { key: 'gradient',  label: 'Gradient',    icon: 'fa-rainbow', preview: 'linear-gradient(135deg, #ec4899, #5c83ff, #06b6d4)' },
            { key: 'preset',    label: 'Presets',     icon: 'fa-th-large', preview: 'linear-gradient(135deg, #f97316, #ec4899, #06b6d4)' },
            { key: 'image',     label: 'Image',       icon: 'fa-image',   preview: 'rgba(99,102,241,0.15)' },
            { key: 'slideshow', label: 'Slideshow',   icon: 'fa-images',  preview: 'rgba(236,72,153,0.15)' },
            { key: 'video',     label: 'Video',       icon: 'fa-film',    preview: 'rgba(61,107,255,0.15)' },
            { key: 'template',  label: 'Template',    icon: 'fa-magic',   preview: 'linear-gradient(135deg, #0f0c29, #302b63)' },
            { key: 'torn',      label: 'Torn Paper',  icon: 'fa-scroll',  preview: 'linear-gradient(115deg, #cfe0e6 0%, #cfe0e6 60%, #5d7d8e 60%)' }
        ],
        init() {
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
