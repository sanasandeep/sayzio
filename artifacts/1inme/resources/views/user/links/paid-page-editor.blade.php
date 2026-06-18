@extends('user.layouts.app')
@section('title', 'Bizs Profile Editor')

@section('content')
@php
    // Resolve bundled theme media to absolute URLs for the live preview;
    // custom (owner-supplied) media is already absolute and handled below.
    $previewTemplates = collect($templates)->map(function ($t) {
        $t['bg_image'] = !empty($t['bg_image']) ? asset($t['bg_image']) : null;
        $t['bg_video'] = !empty($t['bg_video']) ? asset($t['bg_video']) : null;
        return $t;
    })->all();
@endphp
<div class="max-w-5xl mx-auto"
     x-data="{
        tpl: '{{ $templateId }}',
        isPublic: {{ $isPublic ? 'true' : 'false' }},
        customImage: {{ Illuminate\Support\Js::from($bgImageUrl) }},
        customVideo: {{ Illuminate\Support\Js::from($bgVideoUrl) }},
        templates: {{ Illuminate\Support\Js::from($previewTemplates) }},
        get current() { return this.templates[this.tpl] || Object.values(this.templates)[0]; },
        get previewImage() { return (this.customImage || '').trim() || this.current.bg_image || null; },
        get previewVideo() { return (this.customVideo || '').trim() || this.current.bg_video || null; },
     }">

    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.index') }}" class="text-white/30 hover:text-white/50" title="Back to links"><i class="fas fa-arrow-left"></i></a>
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-white truncate">{{ $link->title ?: 'Bizs Profile' }}</h1>
            <p class="text-xs text-white/40 mt-0.5">
                <a href="{{ $publicUrl }}" target="_blank" class="text-violet-400 hover:underline">{{ $publicUrl }} <i class="fas fa-arrow-up-right-from-square ml-0.5 text-[10px]"></i></a>
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-sm">
            <i class="fas fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('user.links.paid-page.update', ['link' => $link->id]) }}">
        @csrf
        <input type="hidden" name="template" :value="tpl">
        <input type="hidden" name="is_public" :value="isPublic ? 1 : 0">
        <input type="hidden" name="bg_image_url" :value="customImage">
        <input type="hidden" name="bg_video_url" :value="customVideo">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- ── Left: controls ──────────────────────────────── --}}
            <div class="space-y-5">
                {{-- Template picker --}}
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-sm font-semibold text-white mb-1">Design template</h2>
                    <p class="text-xs text-white/40 mb-4">Pick a vibe — the preview updates live. {{ count($templates) }} themes across {{ count($categories) }} styles.</p>
                    <div class="space-y-5 max-h-[520px] overflow-y-auto pr-1 -mr-1">
                        @foreach($categories as $group)
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    @if(!empty($group['icon']))<i class="fas {{ $group['icon'] }} text-[10px] text-violet-300/90"></i>@endif
                                    <span class="text-[11px] font-bold uppercase tracking-wide text-violet-300/90">{{ $group['label'] }}</span>
                                    <span class="text-[10px] text-white/30">{{ count($group['templates']) }}</span>
                                    <span class="flex-1 h-px bg-white/10"></span>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    @foreach($group['templates'] as $t)
                                        <button type="button" @click="tpl = '{{ $t['id'] }}'"
                                                :class="tpl === '{{ $t['id'] }}' ? 'ring-2 ring-violet-400 border-violet-400' : 'border-white/10 hover:border-white/30'"
                                                class="text-left rounded-xl border overflow-hidden transition focus:outline-none">
                                            <div class="h-16 relative" style="background: {{ $t['hero_bg'] }};">
                                                @if(!empty($t['bg_image']))
                                                    <div class="absolute inset-0 bg-cover bg-center opacity-60" style="background-image:url('{{ asset($t['bg_image']) }}');"></div>
                                                @endif
                                                @if(!empty($t['bg_video']))
                                                    <span class="absolute top-1 left-1 text-[8px] font-bold text-white/90 bg-black/40 rounded px-1"><i class="fas fa-film"></i></span>
                                                @endif
                                                <span class="absolute bottom-1 left-2 right-2 truncate text-[10px] font-bold text-white drop-shadow">{{ $t['name'] }}</span>
                                                <span x-show="tpl === '{{ $t['id'] }}'" class="absolute top-1 right-1 w-4 h-4 rounded-full bg-violet-500 text-white text-[9px] flex items-center justify-center"><i class="fas fa-check"></i></span>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Custom background media --}}
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-sm font-semibold text-white mb-1">Your own background <span class="text-white/30 font-normal">(optional)</span></h2>
                    <p class="text-xs text-white/40 mb-4">Paste a full image or video URL to override the theme's background. Leave blank to use the theme's built-in look.</p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-white/70 mb-1"><i class="fas fa-image mr-1 text-violet-300"></i> Background image URL</label>
                            <div class="flex gap-2">
                                <input type="url" x-model="customImage" maxlength="2048"
                                       class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:border-violet-400"
                                       placeholder="https://example.com/background.jpg">
                                <button type="button" x-show="customImage" @click="customImage = ''" class="px-3 rounded-xl text-white/40 hover:text-white border border-white/10" title="Clear"><i class="fas fa-xmark"></i></button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-white/70 mb-1"><i class="fas fa-film mr-1 text-violet-300"></i> Background video URL (mp4)</label>
                            <div class="flex gap-2">
                                <input type="url" x-model="customVideo" maxlength="2048"
                                       class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:border-violet-400"
                                       placeholder="https://example.com/loop.mp4">
                                <button type="button" x-show="customVideo" @click="customVideo = ''" class="px-3 rounded-xl text-white/40 hover:text-white border border-white/10" title="Clear"><i class="fas fa-xmark"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Public / gated toggle --}}
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-sm font-semibold text-white mb-3">Who can view this page?</h2>
                    <div class="space-y-2">
                        <button type="button" @click="isPublic = true"
                                :class="isPublic ? 'border-violet-400 bg-violet-500/10 ring-1 ring-violet-500/30' : 'border-white/10 hover:border-white/30'"
                                class="w-full text-left rounded-xl border px-4 py-3 transition flex items-start gap-3">
                            <i class="fas fa-globe mt-0.5 text-violet-300"></i>
                            <div>
                                <div class="text-sm font-semibold text-white">Public</div>
                                <div class="text-xs text-white/50">Anyone with the link can view the page. Individual posts still respect their own paywall &amp; visibility.</div>
                            </div>
                        </button>
                        <button type="button" @click="isPublic = false"
                                :class="!isPublic ? 'border-violet-400 bg-violet-500/10 ring-1 ring-violet-500/30' : 'border-white/10 hover:border-white/30'"
                                class="w-full text-left rounded-xl border px-4 py-3 transition flex items-start gap-3">
                            <i class="fas fa-lock mt-0.5 text-violet-300"></i>
                            <div>
                                <div class="text-sm font-semibold text-white">Gated</div>
                                <div class="text-xs text-white/50">Visitors must sign in before they can see the page at all.</div>
                            </div>
                        </button>
                    </div>
                </div>

                {{-- Content management --}}
                <div class="glass rounded-2xl p-6"
                     x-data="paidPageContent({
                        postCount: {{ (int) $postCount }},
                        tierCount: {{ (int) $tierCount }},
                        postStoreUrl: '{{ route('user.posts.store') }}',
                        tierStoreUrl: '{{ route('user.monetization.tiers.store') }}',
                     })">
                    <h2 class="text-sm font-semibold text-white mb-1">Your posts &amp; tiers</h2>
                    <p class="text-xs text-white/40 mb-4">Everything you publish shows here automatically — there's no "add to page" step. Posts &amp; tiers are shared across all your pages.</p>

                    {{-- Empty-state banner (refreshes in place via Alpine) --}}
                    <div x-show="postCount === 0 || tierCount === 0" x-cloak
                         class="mb-4 rounded-xl border border-violet-500/30 bg-violet-500/10 px-4 py-3 text-xs text-white/70 leading-relaxed">
                        <i class="fas fa-wand-magic-sparkles mr-1 text-violet-300"></i>
                        This page fills itself in. The moment you publish a post or add a paid tier, it appears here for fans —
                        <span x-show="postCount === 0 && tierCount === 0">you don't have any posts or tiers yet.</span>
                        <span x-show="postCount === 0 && tierCount > 0">you don't have any posts yet.</span>
                        <span x-show="postCount > 0 && tierCount === 0">you don't have any paid tiers yet.</span>
                        Create them right here — no need to leave this page.
                    </div>

                    {{-- Success toast --}}
                    <div x-show="toast" x-cloak x-transition.opacity
                         class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/15 px-4 py-3 text-xs text-emerald-300">
                        <i class="fas fa-circle-check mr-1"></i> <span x-text="toast"></span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="rounded-xl border border-white/10 px-4 py-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-white"><i class="fas fa-feather mr-2 text-violet-300"></i> Posts</span>
                                <span class="text-xs text-white/40" x-text="postCount.toLocaleString()">{{ number_format($postCount) }}</span>
                            </div>
                            <button type="button" @click="openPost()" class="inline-flex items-center gap-1.5 text-xs font-semibold text-violet-300 hover:text-violet-200">
                                <i class="fas fa-plus text-[10px]"></i> Create a post
                            </button>
                        </div>
                        <div class="rounded-xl border border-white/10 px-4 py-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-white"><i class="fas fa-gem mr-2 text-violet-300"></i> Tiers</span>
                                <span class="text-xs text-white/40" x-text="tierCount.toLocaleString()">{{ number_format($tierCount) }}</span>
                            </div>
                            <button type="button" @click="openTier()" class="inline-flex items-center gap-1.5 text-xs font-semibold text-violet-300 hover:text-violet-200">
                                <i class="fas fa-plus text-[10px]"></i> Create a tier
                            </button>
                        </div>
                    </div>

                    {{-- ── Inline post composer (drawer) ───────────────── --}}
                    <template x-teleport="body">
                    <div x-show="postOpen" x-cloak class="fixed inset-0 z-[60] flex" @keydown.escape.window="postOpen = false">
                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="postOpen = false"></div>
                        <div class="relative ml-auto h-full w-full max-w-md bg-[#15121f] border-l border-white/10 shadow-2xl overflow-y-auto"
                             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
                            <form @submit.prevent="submitPost()" class="p-6 space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-base font-bold text-white"><i class="fas fa-feather mr-2 text-violet-300"></i> Write a post</h3>
                                    <button type="button" @click="postOpen = false" class="text-white/40 hover:text-white"><i class="fas fa-xmark"></i></button>
                                </div>
                                <p class="text-xs text-white/40">It appears on this page automatically once published.</p>

                                <div x-show="post.error" x-cloak class="rounded-lg bg-rose-500/15 border border-rose-500/30 px-3 py-2 text-xs text-rose-300" x-text="post.error"></div>

                                <div>
                                    <label class="block text-xs font-semibold text-white/70 mb-1">Title <span class="text-white/30">(optional)</span></label>
                                    <input type="text" x-model="post.title" maxlength="200"
                                           class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:border-violet-400"
                                           placeholder="What's this post about?">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-white/70 mb-1">Body</label>
                                    <textarea x-model="post.body" rows="6" maxlength="5000" required
                                              class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:border-violet-400"
                                              placeholder="Share an update with your fans…"></textarea>
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" @click="postOpen = false" class="px-4 py-2 rounded-xl text-sm text-white/60 hover:text-white">Cancel</button>
                                    <button type="submit" :disabled="post.submitting"
                                            class="px-4 py-2 rounded-xl text-sm font-semibold bg-violet-600 hover:bg-violet-500 text-white disabled:opacity-50">
                                        <span x-show="!post.submitting">Publish post</span>
                                        <span x-show="post.submitting" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Publishing…</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    </template>

                    {{-- ── Inline tier composer (drawer) ───────────────── --}}
                    <template x-teleport="body">
                    <div x-show="tierOpen" x-cloak class="fixed inset-0 z-[60] flex" @keydown.escape.window="tierOpen = false">
                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="tierOpen = false"></div>
                        <div class="relative ml-auto h-full w-full max-w-md bg-[#15121f] border-l border-white/10 shadow-2xl overflow-y-auto"
                             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
                            <form @submit.prevent="submitTier()" class="p-6 space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-base font-bold text-white"><i class="fas fa-gem mr-2 text-violet-300"></i> Add a tier</h3>
                                    <button type="button" @click="tierOpen = false" class="text-white/40 hover:text-white"><i class="fas fa-xmark"></i></button>
                                </div>
                                <p class="text-xs text-white/40">A paid membership level fans can subscribe to.</p>

                                <div x-show="tier.error" x-cloak class="rounded-lg bg-rose-500/15 border border-rose-500/30 px-3 py-2 text-xs text-rose-300" x-text="tier.error"></div>

                                <div>
                                    <label class="block text-xs font-semibold text-white/70 mb-1">Tier name</label>
                                    <input type="text" x-model="tier.name" maxlength="80" required
                                           class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:border-violet-400"
                                           placeholder="e.g. Supporter">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-white/70 mb-1">Monthly price</label>
                                    <input type="number" x-model="tier.price_monthly" min="1" max="1000" step="0.01" required
                                           class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:border-violet-400"
                                           placeholder="5.00">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-white/70 mb-1">Perks <span class="text-white/30">(one per line, optional)</span></label>
                                    <textarea x-model="tier.perks" rows="4" maxlength="2000"
                                              class="w-full rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white placeholder-white/30 focus:outline-none focus:border-violet-400"
                                              placeholder="Exclusive posts&#10;Early access&#10;Discord role"></textarea>
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" @click="tierOpen = false" class="px-4 py-2 rounded-xl text-sm text-white/60 hover:text-white">Cancel</button>
                                    <button type="submit" :disabled="tier.submitting"
                                            class="px-4 py-2 rounded-xl text-sm font-semibold bg-violet-600 hover:bg-violet-500 text-white disabled:opacity-50">
                                        <span x-show="!tier.submitting">Add tier</span>
                                        <span x-show="tier.submitting" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Adding…</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    </template>
                </div>
            </div>

            {{-- ── Right: live preview ─────────────────────────── --}}
            <div class="lg:sticky lg:top-6 self-start">
                <div class="rounded-2xl overflow-hidden border border-white/10 shadow-2xl">
                    <div class="px-4 py-2 bg-black/40 flex items-center gap-2 text-[11px] text-white/40">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-400/60"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400/60"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400/60"></span>
                        <span class="ml-2">Live preview</span>
                    </div>
                    <div class="p-5 min-h-[420px] transition-all duration-300 relative overflow-hidden"
                         :style="`background: ${current.page_bg}; font-family: '${current.font}', sans-serif;`">
                        {{-- background media layers --}}
                        <div x-show="previewImage" class="absolute inset-0 bg-cover bg-center" :style="`background-image: url('${previewImage}');`"></div>
                        <template x-if="previewVideo">
                            <video class="absolute inset-0 w-full h-full object-cover" autoplay muted loop playsinline :src="previewVideo"></video>
                        </template>
                        <div x-show="previewImage || previewVideo" class="absolute inset-0" style="background: linear-gradient(180deg, rgba(4,4,10,0.5) 0%, rgba(4,4,10,0.82) 100%);"></div>
                        <div class="relative">
                        <div class="rounded-2xl overflow-hidden relative" :style="`background: ${current.hero_bg}; border-radius: ${current.radius};`">
                            <div class="px-5 pt-8 pb-6">
                                <div class="flex items-end gap-3">
                                    @if($link->user->avatar ?? false)
                                        <img src="{{ $link->user->avatar }}" class="w-16 h-16 rounded-2xl object-cover border-4 border-white/70 shadow-lg" alt="">
                                    @else
                                        <div class="w-16 h-16 rounded-2xl border-4 border-white/70 bg-white/20 text-white flex items-center justify-center font-extrabold backdrop-blur">{{ $link->user->getInitials() ?? '?' }}</div>
                                    @endif
                                    <div>
                                        <div class="text-xl font-extrabold text-white drop-shadow">{{ $link->title ?: ($link->user->name ?? 'Your name') }}</div>
                                        <div class="text-white/80 text-xs">@{{ $link->user->handle ?? 'handle' }}</div>
                                    </div>
                                </div>
                                <div class="mt-4 flex gap-2">
                                    <span class="px-3 py-1.5 rounded-full text-[11px] font-bold text-white" :style="`background: ${current.accent};`"><i class="fas fa-gem mr-1"></i> Subscribe</span>
                                    <span class="px-3 py-1.5 rounded-full text-[11px] font-bold bg-white/90 text-rose-600"><i class="fas fa-heart mr-1"></i> Tip</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 p-4 shadow-lg" :style="`background: ${current.card_bg}; color: ${current.card_text}; border-radius: ${current.radius};`">
                            <div class="text-sm font-semibold">Latest post</div>
                            <div class="text-xs opacity-70 mt-1">Your monetized posts, reactions and comments appear here — styled to match.</div>
                            <div class="mt-3 flex gap-2">
                                <span class="px-2 py-1 rounded-lg text-[11px] border" :style="`border-color: ${current.accent}; color: ${current.accent};`">❤️ 12</span>
                                <span class="px-2 py-1 rounded-lg text-[11px] border border-black/10">🔥 5</span>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
                <p class="text-[11px] text-white/30 mt-2 text-center">Animations respect the visitor's reduced-motion preference.</p>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <a href="{{ $publicUrl }}" target="_blank" class="px-5 py-2.5 rounded-xl text-sm text-white/60 hover:text-white">Open public page</a>
            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-violet-600 hover:bg-violet-500 text-white">Save design</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('paidPageContent', (config) => ({
        postCount: config.postCount,
        tierCount: config.tierCount,
        postStoreUrl: config.postStoreUrl,
        tierStoreUrl: config.tierStoreUrl,
        postOpen: false,
        tierOpen: false,
        toast: '',
        post: { title: '', body: '', submitting: false, error: '' },
        tier: { name: '', price_monthly: '', perks: '', submitting: false, error: '' },

        openPost() { this.post.error = ''; this.postOpen = true; },
        openTier() { this.tier.error = ''; this.tierOpen = true; },

        flash(msg) {
            this.toast = msg;
            setTimeout(() => { this.toast = ''; }, 5000);
        },

        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },

        firstError(payload, fallback) {
            if (payload && payload.error) {
                if (payload.error.details) {
                    const keys = Object.keys(payload.error.details);
                    if (keys.length) {
                        const v = payload.error.details[keys[0]];
                        return Array.isArray(v) ? v[0] : v;
                    }
                }
                if (payload.error.message) return payload.error.message;
            }
            if (payload && payload.errors) {
                const keys = Object.keys(payload.errors);
                if (keys.length) {
                    const v = payload.errors[keys[0]];
                    return Array.isArray(v) ? v[0] : v;
                }
            }
            if (payload && payload.message) return payload.message;
            return fallback;
        },

        async submitPost() {
            if (this.post.submitting) return;
            this.post.error = '';
            this.post.submitting = true;
            try {
                const form = new FormData();
                form.append('title', this.post.title || '');
                form.append('body', this.post.body || '');
                const res = await fetch(this.postStoreUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: form,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.post.error = this.firstError(json, 'Could not publish your post. Please try again.');
                    return;
                }
                if (json.data && typeof json.data.post_count === 'number') {
                    this.postCount = json.data.post_count;
                }
                this.post.title = '';
                this.post.body = '';
                this.postOpen = false;
                this.flash((json.data && json.data.message) || 'Post published.');
            } catch (e) {
                this.post.error = 'Network error. Please try again.';
            } finally {
                this.post.submitting = false;
            }
        },

        async submitTier() {
            if (this.tier.submitting) return;
            this.tier.error = '';
            this.tier.submitting = true;
            try {
                const form = new FormData();
                form.append('name', this.tier.name || '');
                form.append('price_monthly', this.tier.price_monthly || '');
                form.append('perks', this.tier.perks || '');
                const res = await fetch(this.tierStoreUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: form,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.tier.error = this.firstError(json, 'Could not add your tier. Please try again.');
                    return;
                }
                if (json.data && typeof json.data.tier_count === 'number') {
                    this.tierCount = json.data.tier_count;
                }
                this.tier.name = '';
                this.tier.price_monthly = '';
                this.tier.perks = '';
                this.tierOpen = false;
                this.flash((json.data && json.data.message) || 'Tier added.');
            } catch (e) {
                this.tier.error = 'Network error. Please try again.';
            } finally {
                this.tier.submitting = false;
            }
        },
    }));
});
</script>
@endpush
@endsection
