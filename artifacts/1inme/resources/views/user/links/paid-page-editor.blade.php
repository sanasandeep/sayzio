@extends('user.layouts.app')
@section('title', 'Paid Page Editor')

@section('content')
<div class="max-w-5xl mx-auto"
     x-data="{
        tpl: '{{ $templateId }}',
        isPublic: {{ $isPublic ? 'true' : 'false' }},
        templates: {{ Illuminate\Support\Js::from($templates) }},
        get current() { return this.templates[this.tpl] || Object.values(this.templates)[0]; }
     }">

    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.index') }}" class="text-white/30 hover:text-white/50" title="Back to links"><i class="fas fa-arrow-left"></i></a>
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-white truncate">{{ $link->title ?: 'Paid Page' }}</h1>
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- ── Left: controls ──────────────────────────────── --}}
            <div class="space-y-5">
                {{-- Template picker --}}
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-sm font-semibold text-white mb-1">Design template</h2>
                    <p class="text-xs text-white/40 mb-4">Pick a vibe — the preview updates live.</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($templates as $id => $t)
                            <button type="button" @click="tpl = '{{ $id }}'"
                                    :class="tpl === '{{ $id }}' ? 'ring-2 ring-violet-400 border-violet-400' : 'border-white/10 hover:border-white/30'"
                                    class="text-left rounded-xl border overflow-hidden transition focus:outline-none">
                                <div class="h-16 relative" style="background: {{ $t['hero_bg'] }};">
                                    <span class="absolute bottom-1 left-2 text-[10px] font-bold text-white drop-shadow">{{ $t['name'] }}</span>
                                    <span x-show="tpl === '{{ $id }}'" class="absolute top-1 right-1 w-4 h-4 rounded-full bg-violet-500 text-white text-[9px] flex items-center justify-center"><i class="fas fa-check"></i></span>
                                </div>
                            </button>
                        @endforeach
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

                {{-- Content management shortcuts --}}
                <div class="glass rounded-2xl p-6">
                    <h2 class="text-sm font-semibold text-white mb-1">Manage your content</h2>
                    <p class="text-xs text-white/40 mb-4">Posts &amp; tiers are shared across all your pages.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <a href="{{ route('user.posts.index') }}" class="rounded-xl border border-white/10 hover:border-white/30 px-4 py-3 flex items-center justify-between transition">
                            <span class="text-sm text-white"><i class="fas fa-feather mr-2 text-violet-300"></i> Posts</span>
                            <span class="text-xs text-white/40">{{ number_format($postCount) }}</span>
                        </a>
                        <a href="{{ route('user.monetization.earnings') }}" class="rounded-xl border border-white/10 hover:border-white/30 px-4 py-3 flex items-center justify-between transition">
                            <span class="text-sm text-white"><i class="fas fa-gem mr-2 text-violet-300"></i> Tiers</span>
                            <span class="text-xs text-white/40">{{ number_format($tierCount) }}</span>
                        </a>
                    </div>
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
                    <div class="p-5 min-h-[420px] transition-all duration-300"
                         :style="`background: ${current.page_bg}; font-family: '${current.font}', sans-serif;`">
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
                <p class="text-[11px] text-white/30 mt-2 text-center">Animations respect the visitor's reduced-motion preference.</p>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <a href="{{ $publicUrl }}" target="_blank" class="px-5 py-2.5 rounded-xl text-sm text-white/60 hover:text-white">Open public page</a>
            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-violet-600 hover:bg-violet-500 text-white">Save design</button>
        </div>
    </form>
</div>
@endsection
