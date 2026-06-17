@extends('user.layouts.app')
@section('title', 'Create Link')

@section('content')
@php
    $aliasLimits = $aliasLimits ?? ['min' => 3, 'max' => 50];
    $domainHost  = $domainHost ?? request()->getHost();
@endphp
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.index') }}" class="text-white/30 hover:text-white transition-colors"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-white">Create Link</h1>
    </div>

    <form method="POST" action="{{ route('user.links.choose-type') }}" x-data="{ type: '{{ old('type', $lastType ?? '') }}' }">
        @csrf

        <div class="glass rounded-2xl p-6 mb-6">
            <label class="block text-sm font-medium text-white/60 mb-1.5">
                Custom URL <span class="text-white/30 text-xs">(optional)</span>
            </label>
            <div class="flex items-stretch rounded-xl bg-white/5 border border-white/10 focus-within:ring-2 focus-within:ring-violet-500/40 overflow-hidden">
                <span class="flex items-center px-3 text-sm text-white/40 bg-white/[0.03] border-r border-white/10 select-none">
                    {{ $domainHost }}/
                </span>
                <input type="text" name="alias"
                       value="{{ old('alias', $prefillAlias ?? '') }}"
                       placeholder="leave blank to auto-generate"
                       minlength="{{ $aliasLimits['min'] }}"
                       maxlength="{{ $aliasLimits['max'] }}"
                       pattern="[A-Za-z0-9_\-]+"
                       autocomplete="off" spellcheck="false"
                       class="flex-1 bg-transparent px-3 py-2.5 text-sm text-white placeholder-white/20 outline-none">
            </div>
            @error('alias') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            <p class="text-xs text-white/30 mt-1.5">
                Leave blank and we'll generate one for you. Letters, numbers, dashes &amp; underscores only.
                Length: {{ $aliasLimits['min'] }}–{{ $aliasLimits['max'] }} characters
                @if(!empty($aliasUpgradeHint))
                    · <a href="{{ route('user.plans.index') }}" class="text-violet-400 hover:underline">upgrade for more</a>
                @endif.
            </p>
        </div>

        <a href="{{ route('user.links.url.bulk') }}"
           class="block glass rounded-2xl p-5 mb-4 border border-emerald-500/20 bg-gradient-to-r from-emerald-500/10 to-teal-500/5 hover:from-emerald-500/15 hover:to-teal-500/10 transition-all group">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-emerald-500/20 text-emerald-300 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-layer-group text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-white font-medium">Bulk create short links</div>
                    <div class="text-xs text-white/50 mt-0.5">Paste a list or upload a CSV — share settings across many links in one go.</div>
                </div>
                <i class="fas fa-arrow-right text-white/30 group-hover:text-emerald-300 transition-colors"></i>
            </div>
        </a>

        <a href="{{ route('user.links.wizard') }}"
           class="block glass rounded-2xl p-5 mb-4 border border-violet-500/20 bg-gradient-to-r from-violet-500/10 to-fuchsia-500/5 hover:from-violet-500/15 hover:to-fuchsia-500/10 transition-all group">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-violet-500/20 text-violet-300 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-magic text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-white font-medium">Build a Link in Bio with the guided wizard</div>
                    <div class="text-xs text-white/50 mt-0.5">Answer a few questions and we'll generate your page — blocks, layout and all.</div>
                </div>
                <i class="fas fa-arrow-right text-white/30 group-hover:text-violet-300 transition-colors"></i>
            </div>
        </a>

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-base font-semibold text-white mb-1">…or pick a link type manually</h2>
            <p class="text-xs text-white/40 mb-4">Pick one to continue — we'll only ask for the fields that matter for that type.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach([
                    ['value' => 'url',            'icon' => 'fa-link',         'badge' => 'bg-violet-500/15 text-violet-300',  'label' => 'Short Link',      'desc' => 'Shorten any URL with a custom alias and click tracking.'],
                    ['value' => 'biolink',        'icon' => 'fa-id-card',      'badge' => 'bg-pink-500/15 text-pink-300',      'label' => 'Link in Bio',     'desc' => 'A mini-site of your links, blocks and media on one page.'],
                    ['value' => 'conversational', 'icon' => 'fa-comments',     'badge' => 'bg-sky-500/15 text-sky-300',        'label' => 'Conversational',  'desc' => 'A guided, chat-style page that responds as visitors tap.'],
                    ['value' => 'slides',         'icon' => 'fa-clone',        'badge' => 'bg-fuchsia-500/15 text-fuchsia-300','label' => 'Slides',          'desc' => 'Present a swipeable deck of slides from a single link.'],
                    ['value' => 'ai_chat',        'icon' => 'fa-robot',        'badge' => 'bg-teal-500/15 text-teal-300',      'label' => 'AI Chatbot',      'desc' => 'An AI assistant that answers your visitors for you.'],
                    ['value' => 'restaurant_menu','icon' => 'fa-utensils',     'badge' => 'bg-orange-500/15 text-orange-300',  'label' => 'Restaurant Menu', 'desc' => 'A digital menu with sections, items and prices.'],
                    ['value' => 'file',           'icon' => 'fa-file',         'badge' => 'bg-emerald-500/15 text-emerald-300','label' => 'File Share',      'desc' => 'Share a downloadable file behind a short link.'],
                    ['value' => 'ics',            'icon' => 'fa-calendar',     'badge' => 'bg-amber-500/15 text-amber-300',    'label' => 'Event',           'desc' => 'A calendar event visitors can add in a single tap.'],
                    ['value' => 'vcf',            'icon' => 'fa-address-card', 'badge' => 'bg-cyan-500/15 text-cyan-300',      'label' => 'Contact Card',    'desc' => 'A digital business card visitors can save instantly.'],
                    ['value' => 'reviews',        'icon' => 'fa-star',         'badge' => 'bg-yellow-500/15 text-yellow-300',  'label' => 'Reviews',         'desc' => 'Collect and showcase reviews from your audience.'],
                    ['value' => 'resume',         'icon' => 'fa-file-lines',   'badge' => 'bg-indigo-500/15 text-indigo-300',  'label' => 'Resume / Portfolio', 'desc' => 'A shareable resume / portfolio page with PDF download.'],
                    ['value' => 'paid_page',      'icon' => 'fa-crown',        'badge' => 'bg-rose-500/15 text-rose-300',      'label' => 'Bizs Profile',    'desc' => 'A themeable home that automatically shows all your posts, tiers & tips — no linking needed.'],
                ] as $opt)
                    <label class="relative cursor-pointer block group h-full">
                        <input type="radio" name="type" value="{{ $opt['value'] }}" x-model="type" class="sr-only peer">
                        <div class="h-full border rounded-2xl p-5 flex flex-col gap-3 transition-all duration-200 motion-safe:group-hover:-translate-y-0.5"
                             :class="type === '{{ $opt['value'] }}'
                                ? 'border-violet-500 bg-violet-500/10 ring-2 ring-violet-500/30 shadow-lg shadow-violet-500/10'
                                : 'border-white/10 hover:border-white/20 hover:bg-white/[0.04] hover:shadow-lg hover:shadow-black/20'">
                            <div class="flex items-center justify-between">
                                <div class="w-11 h-11 rounded-xl flex items-center justify-center {{ $opt['badge'] }}">
                                    <i class="fas {{ $opt['icon'] }} text-lg"></i>
                                </div>
                                <span class="w-5 h-5 rounded-full border flex items-center justify-center transition-all"
                                      :class="type === '{{ $opt['value'] }}'
                                        ? 'border-violet-400 bg-violet-500'
                                        : 'border-white/20'">
                                    <i class="fas fa-check text-[10px] text-white transition-opacity"
                                       :class="type === '{{ $opt['value'] }}' ? 'opacity-100' : 'opacity-0'"></i>
                                </span>
                            </div>
                            <div>
                                <div class="text-base font-semibold text-white">{{ $opt['label'] }}</div>
                                <div class="text-xs text-white/50 mt-1 leading-relaxed">{{ $opt['desc'] }}</div>
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
            @error('type') <p class="text-red-400 text-sm mt-2">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('user.links.index') }}" class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">Cancel</a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-violet-500/20">
                Continue <i class="fas fa-arrow-right ml-1.5 text-xs"></i>
            </button>
        </div>
    </form>
</div>
@endsection
