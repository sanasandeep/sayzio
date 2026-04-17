@extends('user.layouts.app')
@section('title', 'Splash Page · ' . ($link->title ?: $link->alias))

@section('content')
@php
    $favSrc = $link->favicon
        ?? ($link->settings['biolink']['favicons']['icon_512'] ?? null)
        ?? ($link->settings['biolink']['favicons']['apple_touch_icon'] ?? null);
    $enabled = !empty($splash['enabled']);
    $auto    = !empty($splash['auto_redirect']);
    $cd      = isset($splash['countdown']) ? (int) $splash['countdown'] : 5;
@endphp

<div class="max-w-4xl mx-auto" x-data="{
        enabled: {{ $enabled ? 'true' : 'false' }},
        auto:    {{ $auto    ? 'true' : 'false' }},
        cd:      {{ $cd }},
        showHelp: false,
        previewLogo:    @json($splash['logo']     ?? null),
        previewFavicon: @json($splash['favicon']  ?? null),
        previewOg:      @json($splash['og_image'] ?? null),
        title:       @json($splash['title']       ?? ''),
        description: @json($splash['description'] ?? ''),
        ctaLabel:    @json(($splash['cta_label'] ?? '') ?: 'Continue'),
        readPreview(input, target){
            var f = input.files && input.files[0];
            if(!f) return;
            var r = new FileReader();
            r.onload = e => this[target] = e.target.result;
            r.readAsDataURL(f);
        }
     }">

    @include('user.partials.page-hero', [
        'title'    => 'Splash Page',
        'subtitle' => $link->title ?: $link->alias,
        'icon'     => 'fa-rocket',
        'favicon'  => $favSrc,
        'back'     => route('user.links.show', $link),
        'chips'    => [
            ['icon' => 'fa-circle ' . ($enabled ? 'text-emerald-400' : 'text-gray-400'), 'text' => $enabled ? 'Enabled' : 'Disabled'],
            ['icon' => 'fa-' . ($link->type === 'biolink' ? 'th-large' : 'link'), 'text' => ucfirst($link->type ?? 'link')],
        ],
    ])

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="mb-6 px-4 py-3 rounded-xl text-sm" style="background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.25); color: #f87171;">
        <i class="fas fa-exclamation-triangle mr-1.5"></i>
        <ul class="list-disc pl-5 mt-1">
            @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- ===== INTRO BANNER ===== --}}
    <div class="card-premium p-6 mb-8">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: linear-gradient(135deg, rgba(139,92,246,0.18), rgba(236,72,153,0.18));">
                <i class="fas fa-rocket text-violet-400 text-lg"></i>
            </div>
            <div class="flex-1">
                <h3 class="text-base font-bold mb-1" style="color: var(--text-primary);">What is a splash page?</h3>
                <p class="text-sm leading-relaxed" style="color: var(--text-muted);">
                    A splash page is an intermediate screen visitors see <strong>before</strong> they land on your destination link.
                    Use it for announcements, promotions, disclaimers, branding, or even ad placements — a smart transition layer
                    that improves engagement, branding, and control over how users reach your final content.
                </p>
                <button type="button" @click="showHelp = !showHelp" class="mt-2 text-xs font-medium" style="color: var(--accent-light);">
                    <i class="fas fa-chevron-down text-[9px] mr-1" :class="showHelp ? 'rotate-180' : ''" style="transition: transform 0.2s;"></i>
                    <span x-text="showHelp ? 'Hide tips' : 'Show common use cases & tips'"></span>
                </button>
                <div x-show="showHelp" x-cloak x-transition class="mt-3 p-3 rounded-lg text-[12px] leading-relaxed" style="background: rgba(139,92,246,0.06); border: 1px solid rgba(139,92,246,0.18); color: var(--text-muted);">
                    <ul class="list-disc pl-4 space-y-1">
                        <li><strong>Sponsorships / ads</strong> — show a sponsor message before passing the visitor on.</li>
                        <li><strong>Disclaimer or age gate</strong> — make sure people read terms before continuing.</li>
                        <li><strong>Brand intro</strong> — reinforce your logo and identity on every link, especially when forwarding to a third-party site.</li>
                        <li><strong>Announcements</strong> — push a sale, an event, or news on top of every link click.</li>
                        <li><strong>Engagement boost</strong> — adds a controlled pause that builds anticipation.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('user.links.splash.update', $link) }}" enctype="multipart/form-data" class="space-y-8">
        @csrf

        {{-- ===== ENABLE SWITCH ===== --}}
        <div class="card-premium p-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background: rgba(16,185,129,0.12);">
                        <i class="fas fa-power-off text-emerald-400 text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Splash Page</h3>
                        <p class="text-[11px]" style="color: var(--text-faint);">Show the splash page when someone opens this link.</p>
                    </div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer select-none">
                    <input type="hidden" name="splash_enabled" value="0">
                    <input type="checkbox" name="splash_enabled" value="1" class="sr-only peer" x-model="enabled">
                    <div class="w-12 h-6 rounded-full peer-checked:bg-emerald-500" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); transition: background 0.2s;"></div>
                    <div class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-6"></div>
                </label>
            </div>
        </div>

        <div :class="!enabled && 'opacity-50 pointer-events-none'" class="space-y-8" style="transition: opacity 0.25s;">

            {{-- ===== CONTENT ===== --}}
            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(139,92,246,0.12);"><i class="fas fa-pen-nib text-violet-400 text-xs"></i></div>
                    <div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Content</h3>
                        <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">The headline, message and call-to-action your visitors will see.</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Logo <span class="text-[10px]" style="color: var(--text-faint);">— shown at the top of the splash card</span></label>
                        <div class="flex items-center gap-4">
                            <template x-if="previewLogo">
                                <div class="w-16 h-16 rounded-xl flex items-center justify-center p-2" style="background: rgba(255,255,255,0.04); border: 1px solid var(--border-subtle);">
                                    <img :src="previewLogo" alt="Logo preview" class="max-w-full max-h-full object-contain">
                                </div>
                            </template>
                            <template x-if="!previewLogo">
                                <div class="w-16 h-16 rounded-xl flex items-center justify-center" style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass);">
                                    <i class="fas fa-image text-xl" style="color: var(--text-faint);"></i>
                                </div>
                            </template>
                            <div class="flex-1 min-w-0">
                                <input type="file" name="splash_logo" accept="image/*" @change="readPreview($event.target, 'previewLogo')" class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-violet-500/10 file:text-violet-400 file:font-medium" style="color: var(--text-faint);">
                                <p class="text-[10px] mt-1" style="color: var(--text-faint);">PNG/SVG with transparent background works best. Max 2MB.</p>
                                <template x-if="previewLogo">
                                    <label class="text-[10px] inline-flex items-center gap-1.5 mt-2 cursor-pointer" style="color: #f87171;">
                                        <input type="checkbox" name="splash_remove_logo" value="1" class="rounded" @change="if($event.target.checked) previewLogo = null">
                                        <i class="fas fa-trash text-[9px]"></i> Remove current logo
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Title <span class="text-[10px]" style="color: var(--text-faint);">— the big headline (also used as page title in the browser tab)</span></label>
                        <input type="text" name="splash_title" maxlength="160" x-model="title" placeholder="e.g. You're being redirected" class="theme-input w-full">
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Description <span class="text-[10px]" style="color: var(--text-faint);">— a short message under the title</span></label>
                        <textarea name="splash_description" maxlength="1000" rows="3" x-model="description" placeholder="Tell visitors what's about to happen, who you are, or why they're here." class="theme-input w-full"></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button label</label>
                            <input type="text" name="splash_cta_label" maxlength="60" x-model="ctaLabel" placeholder="Continue" class="theme-input w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Button URL <span class="text-[10px]" style="color: var(--text-faint);">— leave empty to continue to the link's destination</span></label>
                            <input type="url" name="splash_cta_url" value="{{ old('splash_cta_url', $splash['cta_url'] ?? '') }}" placeholder="https://" pattern="https?://.+" class="theme-input w-full">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== AUTO-REDIRECT ===== --}}
            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(245,158,11,0.12);"><i class="fas fa-clock text-amber-400 text-xs"></i></div>
                    <div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Auto-redirect &amp; countdown</h3>
                        <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Automatically send visitors to the destination after a delay. They can still click the button to skip the wait.</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="hidden" name="splash_auto_redirect" value="0">
                        <input type="checkbox" name="splash_auto_redirect" value="1" x-model="auto" class="rounded text-violet-500">
                        <span class="text-sm font-medium" style="color: var(--text-primary);">Automatically continue after a countdown</span>
                    </label>
                    <div x-show="auto" x-transition x-cloak class="ml-7">
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Wait time</label>
                        <div class="flex items-center gap-3">
                            <input type="range" name="splash_countdown" min="0" max="60" x-model.number="cd" class="flex-1 max-w-xs accent-violet-500">
                            <div class="w-20 px-3 py-1.5 rounded-lg text-center font-mono text-sm font-semibold" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                                <span x-text="cd"></span>s
                            </div>
                        </div>
                        <p class="text-[10px] mt-2" style="color: var(--text-faint);">0–60 seconds. <strong>5–10s</strong> is a common sweet spot — long enough to read your message, short enough to not annoy.</p>
                    </div>
                </div>
            </div>

            {{-- ===== BRANDING / META ===== --}}
            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(99,102,241,0.12);"><i class="fas fa-share-nodes text-indigo-400 text-xs"></i></div>
                    <div>
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Branding &amp; share preview</h3>
                        <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Favicon for the browser tab and a preview image used by Google, WhatsApp, Twitter and friends when this splash URL is shared.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Favicon</label>
                        <div class="flex items-center gap-3">
                            <template x-if="previewFavicon">
                                <img :src="previewFavicon" class="w-10 h-10 rounded-lg" style="border: 1px solid var(--border-subtle);">
                            </template>
                            <template x-if="!previewFavicon">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass);">
                                    <i class="fas fa-bookmark text-xs" style="color: var(--text-faint);"></i>
                                </div>
                            </template>
                            <input type="file" name="splash_favicon" accept="image/*" @change="readPreview($event.target, 'previewFavicon')" class="flex-1 min-w-0 text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-violet-500/10 file:text-violet-400 file:font-medium" style="color: var(--text-faint);">
                        </div>
                        <template x-if="previewFavicon">
                            <label class="text-[10px] inline-flex items-center gap-1.5 mt-2 cursor-pointer" style="color: #f87171;">
                                <input type="checkbox" name="splash_remove_favicon" value="1" class="rounded" @change="if($event.target.checked) previewFavicon = null">
                                <i class="fas fa-trash text-[9px]"></i> Remove favicon
                            </label>
                        </template>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Open Graph / share image</label>
                        @if(!empty($splash['og_image']))
                            <img :src="previewOg" alt="Share preview" x-show="previewOg" class="h-20 rounded-lg mb-2" style="border: 1px solid var(--border-subtle);">
                        @endif
                        <input type="file" name="splash_og_image" accept="image/*" @change="readPreview($event.target, 'previewOg')" class="w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-violet-500/10 file:text-violet-400 file:font-medium" style="color: var(--text-faint);">
                        <p class="text-[10px] mt-1" style="color: var(--text-faint);">Recommended size 1200×630 px.</p>
                        <template x-if="previewOg">
                            <label class="text-[10px] inline-flex items-center gap-1.5 mt-2 cursor-pointer" style="color: #f87171;">
                                <input type="checkbox" name="splash_remove_og" value="1" class="rounded" @change="if($event.target.checked) previewOg = null">
                                <i class="fas fa-trash text-[9px]"></i> Remove image
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ===== ADVANCED ===== --}}
            <div class="card-premium p-6" x-data="{ open: {{ (!empty($splash['custom_css']) || !empty($splash['custom_js'])) ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="flex items-center gap-3 w-full text-left">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(6,182,212,0.12);"><i class="fas fa-code text-cyan-400 text-xs"></i></div>
                    <div class="flex-1">
                        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Advanced — custom CSS &amp; JavaScript</h3>
                        <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">For developers. Inject your own styles or scripts onto the splash page. Skip this section if you're not sure.</p>
                    </div>
                    <i class="fas fa-chevron-down text-xs" :class="open ? 'rotate-180' : ''" style="color: var(--text-faint); transition: transform 0.2s;"></i>
                </button>
                <div x-show="open" x-transition x-cloak class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Custom CSS</label>
                        <textarea name="splash_custom_css" rows="6" placeholder=".glass-card { background: …; }" class="theme-input w-full font-mono text-[12px]">{{ old('splash_custom_css', $splash['custom_css'] ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Custom JavaScript</label>
                        <textarea name="splash_custom_js" rows="6" placeholder="// Runs at the bottom of the splash page" class="theme-input w-full font-mono text-[12px]">{{ old('splash_custom_js', $splash['custom_js'] ?? '') }}</textarea>
                        <p class="text-[10px] mt-1" style="color: var(--text-faint);">Wrapped in try/catch — errors are logged to the browser console.</p>
                    </div>
                </div>
            </div>

            {{-- ===== LIVE PREVIEW ===== --}}
            <div class="card-premium p-6">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(236,72,153,0.12);"><i class="fas fa-eye text-pink-400 text-xs"></i></div>
                        <div>
                            <h3 class="text-sm font-bold" style="color: var(--text-primary);">Preview</h3>
                            <p class="text-[11px] mt-0.5" style="color: var(--text-faint);">Live preview of what visitors will see. Save first to test the real page.</p>
                        </div>
                    </div>
                    @if($enabled)
                    <a href="{{ rtrim(config('app.url'), '/') . '/' . $link->alias }}?_continue=0&_preview=1" target="_blank"
                       class="text-[11px] px-3 py-1.5 rounded-lg font-medium" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                        <i class="fas fa-external-link-alt text-[9px] mr-1"></i> Open live splash
                    </a>
                    @endif
                </div>
                <div class="rounded-2xl overflow-hidden" style="background: linear-gradient(160deg, #0a0b10, #1a1230); border: 1px solid var(--border-glass);">
                    <div class="p-8 sm:p-10 text-center text-white" style="font-family: 'Plus Jakarta Sans', sans-serif;">
                        <template x-if="previewLogo">
                            <div class="w-16 h-16 rounded-xl mx-auto mb-5 flex items-center justify-center p-2" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                                <img :src="previewLogo" class="max-w-full max-h-full object-contain">
                            </div>
                        </template>
                        <template x-if="!previewLogo">
                            <div class="w-14 h-14 rounded-xl mx-auto mb-5 flex items-center justify-center" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                                <i class="fas fa-bolt text-white text-xl"></i>
                            </div>
                        </template>
                        <h2 class="text-2xl font-extrabold mb-2" x-text="title || 'Your title here'" style="letter-spacing: -0.02em;"></h2>
                        <p class="text-sm mb-6" style="color: rgba(255,255,255,0.6); max-width: 24rem; margin-left: auto; margin-right: auto;" x-text="description || 'A short message describing what visitors are about to see.'"></p>
                        <div class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-sm text-white" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); box-shadow: 0 8px 24px -8px rgba(139,92,246,0.4);">
                            <span x-text="ctaLabel || 'Continue'"></span>
                            <i class="fas fa-arrow-right text-[10px]"></i>
                        </div>
                        <div x-show="auto && cd > 0" class="mt-4 text-xs" style="color: rgba(255,255,255,0.45);">
                            Auto-redirecting in <span x-text="cd"></span>s
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 mt-8 py-4 flex items-center gap-3" style="background: var(--bg-body); z-index: 10;">
            <button type="submit" class="btn-primary px-8 py-3 text-sm font-semibold inline-flex items-center gap-2 shadow-lg">
                <i class="fas fa-save text-xs"></i> Save Splash Page
            </button>
            <a href="{{ route('user.links.show', $link) }}" class="text-xs px-4 py-2 rounded-lg" style="color: var(--text-faint);">Cancel</a>
        </div>
    </form>
</div>
@endsection
