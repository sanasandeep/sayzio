@extends('user.layouts.app')
@section('title', 'Appearance - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php
    $bs = $link->settings['biolink'] ?? [];
    $activeSettingsTab = 'appearance';
    $bgType = $bs['background_type'] ?? 'color';
    $bgColor = $bs['background_color'] ?? '#0a0612';
    $fontColor = $bs['font_color'] ?? '#ffffff';
    $bgGradient = $bs['background_gradient'] ?? 'linear-gradient(135deg, #0a0612 0%, #1a0533 50%, #0a0612 100%)';
    $gradientColors = $bs['gradient_colors'] ?? [['color'=>'#0a0612','pos'=>0],['color'=>'#1a0533','pos'=>50],['color'=>'#0a0612','pos'=>100]];
    $gradientAngle = $bs['gradient_angle'] ?? 135;
    $gradientTypeVal = $bs['gradient_type'] ?? 'linear';
    $slideshowImages = $bs['slideshow_images'] ?? [];
    $slideshowInterval = $bs['slideshow_interval'] ?? 5;
    $videoUrl = $bs['video_url'] ?? '';
    $videoFile = $bs['video_file'] ?? '';
    $bgTemplateId = $bs['bg_template_id'] ?? null;
    $bgAttachment = $bs['bg_attachment'] ?? 'fixed';
    $bgFallbackColor = $bs['bg_fallback_color'] ?? '#0a0612';
    $bgFallbackImage = $bs['bg_fallback_image'] ?? '';
    $bgBlur = $bs['bg_blur'] ?? 0;
    $bgOverlayColor = $bs['bg_overlay_color'] ?? '#000000';
    $bgOverlayOpacity = $bs['bg_overlay_opacity'] ?? 0;
    $fontFamily = $bs['font_family'] ?? 'Space Grotesk';
@endphp

<div class="w-full max-w-7xl mx-auto">
    @include('user.links.partials.editor-header', ['link' => $link, 'activeMainTab' => 'settings'])
    @include('user.links.partials.settings-header', ['link' => $link, 'activeSettingsTab' => $activeSettingsTab])

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-7">
            <form method="POST" action="{{ route('user.links.page-settings', $link) }}" enctype="multipart/form-data">
                @csrf

                <div class="space-y-6">

                    <div class="card-premium p-6" x-data="{ editing: false, alias: '{{ $link->alias }}' }">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.1);"><i class="fas fa-link text-purple-400 text-xs"></i></div>
                            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Short URL</h3>
                        </div>
                        <div class="flex items-center rounded-xl overflow-hidden" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <span class="px-3 py-2.5 text-sm flex-shrink-0" style="color: var(--text-faint); border-right: 1px solid var(--border-glass);">{{ request()->getHost() }}/</span>
                            <template x-if="!editing">
                                <span class="flex-1 px-3 py-2.5 text-sm font-medium cursor-pointer flex items-center justify-between gap-2 group" style="color: var(--text-primary);" @click="editing = true; $nextTick(() => $refs.aliasInput.focus())">
                                    <span x-text="alias"></span>
                                    <i class="fas fa-pen text-[10px] opacity-0 group-hover:opacity-60 transition-opacity" style="color: var(--text-faint);"></i>
                                </span>
                            </template>
                            <template x-if="editing">
                                <div class="flex-1 flex items-center">
                                    <input x-ref="aliasInput" type="text" x-model="alias" class="flex-1 px-3 py-2.5 text-sm font-medium bg-transparent outline-none" style="color: var(--text-primary);" @keydown.escape="editing = false">
                                    <div class="flex items-center gap-1 pr-2">
                                        <button type="button" @click="editing = false; alias = '{{ $link->alias }}'" class="text-[10px] px-2 py-1 rounded" style="color: var(--text-faint);">Cancel</button>
                                        <button type="button" class="text-[10px] px-2 py-1 rounded bg-purple-600 text-white"
                                           @click="fetch('{{ route('user.links.update-alias', $link) }}', { method:'PUT', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'}, body:JSON.stringify({alias:alias})}).then(r=>r.json()).then(d=>{if(d.success||!d.errors){editing=false;location.reload()}else{alert(d.errors?.alias?.[0]||'Error')}}).catch(()=>alert('Error'))">Save</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="card-premium p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(236,72,153,0.1);"><i class="fas fa-palette text-pink-400 text-xs"></i></div>
                            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Page Design</h3>
                        </div>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Page Title</label>
                                    <input type="text" name="biolink_title" value="{{ $bs['biolink_title'] ?? $link->title }}" class="theme-input w-full" placeholder="My Bio Link">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Family</label>
                                    <select name="font_family" class="theme-input w-full">
                                        @foreach(['Space Grotesk','Inter','Poppins','Roboto','Playfair Display','Montserrat','DM Sans','Outfit'] as $font)
                                        <option value="{{ $font }}" {{ $fontFamily === $font ? 'selected' : '' }}>{{ $font }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Description</label>
                                <textarea name="biolink_description" rows="2" class="theme-input w-full" placeholder="A short description for your page">{{ $bs['biolink_description'] ?? '' }}</textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Font Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="font_color" value="{{ $fontColor }}" class="w-10 h-10 rounded-lg cursor-pointer flex-shrink-0" style="border: 1px solid var(--border-subtle);">
                                    <span class="text-xs font-mono" style="color: var(--text-faint);">{{ $fontColor }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-premium p-6" x-data="bgSettings()" x-init="init()">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(99,102,241,0.1);"><i class="fas fa-fill-drip text-indigo-400 text-xs"></i></div>
                            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Colors & Background</h3>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <label class="block text-xs font-medium mb-2" style="color: var(--text-muted);">Background Type</label>
                                <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                                    <template x-for="t in types" :key="t.key">
                                        <button type="button" @click="bgType = t.key"
                                            :class="bgType === t.key ? 'ring-2 ring-purple-500' : ''"
                                            class="flex flex-col items-center gap-1 p-2.5 rounded-xl transition-all text-center"
                                            style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"
                                            :style="bgType === t.key ? 'border-color: rgba(139,92,246,0.5); background: rgba(139,92,246,0.08);' : ''">
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

                            {{-- MULTI-COLOR GRADIENT --}}
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
                                        <input type="range" name="gradient_angle" x-model="gradientAngle" min="0" max="360" class="w-full accent-purple-500">
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
                                                    <input type="range" :value="stop.pos" @input="stop.pos = parseInt($event.target.value)" min="0" max="100" class="w-full accent-purple-500">
                                                </div>
                                                <span class="text-[10px] font-mono w-8 text-center" style="color: var(--text-faint);" x-text="stop.pos + '%'"></span>
                                                <button type="button" @click="removeStop(idx)" x-show="gradientStops.length > 2" class="w-6 h-6 rounded flex items-center justify-center hover:bg-red-500/10 transition-colors" style="color: var(--text-faint);">
                                                    <i class="fas fa-times text-[9px]"></i>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <button type="button" @click="addStop()" class="mt-2 text-[11px] font-semibold px-3 py-1.5 rounded-lg transition-all hover:bg-purple-500/10" style="color: #a78bfa; border: 1px dashed rgba(139,92,246,0.3);">
                                        <i class="fas fa-plus text-[9px] mr-1"></i> Add Color Stop
                                    </button>
                                    <input type="hidden" name="gradient_colors" :value="JSON.stringify(gradientStops)">
                                    <input type="hidden" name="background_gradient" :value="buildGradientCSS()">
                                </div>
                            </div>

                            {{-- STATIC IMAGE --}}
                            <div x-show="bgType === 'image'" x-transition class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Background Image</label>
                                    <input type="file" name="background_image" accept="image/*" class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-purple-500/10 file:text-purple-400 file:font-medium" style="color: var(--text-faint);">
                                    @if(!empty($bs['background_image']))
                                    <div class="flex items-center gap-2 mt-2 p-2 rounded-lg" style="background: var(--bg-glass);">
                                        <img src="{{ $bs['background_image'] }}" class="w-12 h-12 rounded-lg object-cover" alt="Current bg">
                                        <span class="text-[10px]" style="color: var(--text-faint);">Current background image</span>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            {{-- SLIDESHOW --}}
                            <div x-show="bgType === 'slideshow'" x-transition class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Slideshow Images (up to 10)</label>
                                    <input type="file" name="slideshow_images[]" accept="image/*" multiple class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-purple-500/10 file:text-purple-400 file:font-medium" style="color: var(--text-faint);">
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
                                    <input type="range" name="slideshow_interval" value="{{ $slideshowInterval }}" min="1" max="30" class="w-full accent-purple-500" oninput="this.previousElementSibling.querySelector('span').textContent = this.value + 's'">
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
                                <div>
                                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Or Upload Video</label>
                                    <input type="file" name="video_file" accept="video/mp4,video/webm" class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-purple-500/10 file:text-purple-400 file:font-medium" style="color: var(--text-faint);">
                                    <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">MP4 or WebM. Max 50MB. Auto-plays muted with loop.</p>
                                    @if($videoFile)
                                    <div class="flex items-center gap-2 mt-2 p-2 rounded-lg" style="background: var(--bg-glass);">
                                        <i class="fas fa-film text-purple-400"></i>
                                        <span class="text-[10px]" style="color: var(--text-faint);">Video uploaded</span>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            {{-- CSS/JS TEMPLATES --}}
                            <div x-show="bgType === 'template'" x-transition class="space-y-3">
                                <label class="block text-xs font-medium mb-1" style="color: var(--text-muted);">Choose a Template</label>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2" x-data="{ selectedTpl: {{ $bgTemplateId ?? 'null' }} }">
                                    @foreach($bgTemplates as $tpl)
                                    <label class="cursor-pointer group">
                                        <input type="radio" name="bg_template_id" value="{{ $tpl->id }}" {{ $bgTemplateId == $tpl->id ? 'checked' : '' }} class="hidden peer" @click="selectedTpl = {{ $tpl->id }}">
                                        <div class="p-2 rounded-xl transition-all peer-checked:ring-2 peer-checked:ring-purple-500 hover:scale-[1.02]"
                                             style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"
                                             :style="selectedTpl === {{ $tpl->id }} ? 'border-color: rgba(139,92,246,0.5); background: rgba(139,92,246,0.08);' : ''">
                                            <div class="h-16 rounded-lg mb-2 overflow-hidden" style="background: {{ $tpl->preview_color }};"></div>
                                            <p class="text-[10px] font-semibold text-center" style="color: var(--text-muted);">{{ $tpl->name }}</p>
                                        </div>
                                    </label>
                                    @endforeach
                                </div>
                                @if($bgTemplates->isEmpty())
                                <p class="text-[11px] p-3 rounded-lg text-center" style="color: var(--text-dimmed); background: var(--bg-glass);">No templates available yet.</p>
                                @endif
                            </div>

                            {{-- SHARED CONFIGURATIONS --}}
                            <div class="pt-4" style="border-top: 1px solid var(--border-subtle);">
                                <div class="flex items-center gap-2 mb-3">
                                    <i class="fas fa-sliders-h text-[10px] text-purple-400"></i>
                                    <span class="text-xs font-semibold" style="color: var(--text-primary);">Background Effects</span>
                                </div>
                                <div class="space-y-4">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Position</label>
                                            <div class="flex gap-2" x-data="{ attach: '{{ $bgAttachment }}' }">
                                                <button type="button" @click="attach = 'fixed'" :class="attach === 'fixed' ? 'ring-2 ring-purple-500' : ''" class="flex-1 py-2 text-[10px] font-semibold rounded-lg transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
                                                    <i class="fas fa-thumbtack text-[9px] mr-1"></i> Fixed
                                                </button>
                                                <button type="button" @click="attach = 'scroll'" :class="attach === 'scroll' ? 'ring-2 ring-purple-500' : ''" class="flex-1 py-2 text-[10px] font-semibold rounded-lg transition-all" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
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
                                        <input type="range" name="bg_blur" value="{{ $bgBlur }}" min="0" max="100" class="w-full accent-purple-500" oninput="this.previousElementSibling.querySelector('span').textContent = this.value + 'px'">
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
                                            <input type="range" name="bg_overlay_opacity" value="{{ $bgOverlayOpacity }}" min="0" max="100" class="w-full accent-purple-500" oninput="this.previousElementSibling.querySelector('span').textContent = this.value + '%'">
                                        </div>
                                    </div>

                                    <div x-show="bgType === 'image' || bgType === 'slideshow' || bgType === 'video'" x-transition>
                                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Fallback Image</label>
                                        <input type="file" name="bg_fallback_image" accept="image/*" class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-purple-500/10 file:text-purple-400 file:font-medium" style="color: var(--text-faint);">
                                        <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Shown while media loads or if it fails.</p>
                                        @if($bgFallbackImage)
                                        <div class="flex items-center gap-2 mt-1 p-1.5 rounded-lg" style="background: var(--bg-glass);">
                                            <img src="{{ $bgFallbackImage }}" class="w-8 h-8 rounded object-cover">
                                            <span class="text-[10px]" style="color: var(--text-faint);">Current fallback</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                @include('user.links.partials.settings-footer', ['link' => $link])
            </form>
        </div>

        <div class="lg:col-span-5 hidden lg:block">
            <div class="sticky top-0 self-start">
                @include('user.links.partials.device-preview', ['link' => $link])
            </div>
        </div>
    </div>
</div>

<script>
function bgSettings() {
    return {
        bgType: '{{ $bgType }}',
        gradientType: '{{ $gradientTypeVal }}',
        gradientAngle: {{ $gradientAngle }},
        gradientStops: @json($gradientColors),
        types: [
            { key: 'color', label: 'Solid Color', icon: 'fa-fill', preview: 'linear-gradient(135deg, #6b21a8, #3b0764)' },
            { key: 'gradient', label: 'Gradient', icon: 'fa-rainbow', preview: 'linear-gradient(135deg, #ec4899, #8b5cf6, #06b6d4)' },
            { key: 'image', label: 'Image', icon: 'fa-image', preview: 'rgba(99,102,241,0.15)' },
            { key: 'slideshow', label: 'Slideshow', icon: 'fa-images', preview: 'rgba(236,72,153,0.15)' },
            { key: 'video', label: 'Video', icon: 'fa-film', preview: 'rgba(139,92,246,0.15)' },
            { key: 'template', label: 'Template', icon: 'fa-magic', preview: 'linear-gradient(135deg, #0f0c29, #302b63)' }
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
            this.gradientStops.push({ color: '#8b5cf6', pos: Math.min(100, (last ? last.pos : 50) + 10) });
        },
        removeStop(idx) {
            if (this.gradientStops.length <= 2) return;
            this.gradientStops.splice(idx, 1);
        },
        buildGradientCSS() {
            var stops = this.gradientStops.slice().sort(function(a, b) { return a.pos - b.pos; });
            var stopsStr = stops.map(function(s) { return s.color + ' ' + s.pos + '%'; }).join(', ');
            if (this.gradientType === 'radial') return 'radial-gradient(circle, ' + stopsStr + ')';
            if (this.gradientType === 'conic') return 'conic-gradient(from ' + this.gradientAngle + 'deg, ' + stopsStr + ')';
            return 'linear-gradient(' + this.gradientAngle + 'deg, ' + stopsStr + ')';
        }
    };
}
</script>
@endsection
