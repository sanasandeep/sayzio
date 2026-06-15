@extends('user.layouts.app')
@section('title', 'Create Link')

@section('content')
@php
    $aliasLimits = $aliasLimits ?? ['min' => 3, 'max' => 50];
    $domainHost  = $domainHost ?? request()->getHost();
@endphp
<div class="max-w-2xl mx-auto">
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

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                @foreach([
                    ['value' => 'url',            'icon' => 'fa-link',         'color' => 'text-violet-400',  'label' => 'Short Link'],
                    ['value' => 'biolink',        'icon' => 'fa-id-card',      'color' => 'text-pink-400',    'label' => 'Link in Bio'],
                    ['value' => 'conversational', 'icon' => 'fa-comments',     'color' => 'text-sky-400',     'label' => 'Conversational'],
                    ['value' => 'slides',         'icon' => 'fa-clone',        'color' => 'text-fuchsia-400', 'label' => 'Slides'],
                    ['value' => 'ai_chat',        'icon' => 'fa-robot',        'color' => 'text-teal-400',    'label' => 'AI Chatbot'],
                    ['value' => 'restaurant_menu','icon' => 'fa-utensils',     'color' => 'text-orange-400',  'label' => 'Restaurant Menu'],
                    ['value' => 'file',           'icon' => 'fa-file',         'color' => 'text-emerald-400', 'label' => 'File Share'],
                    ['value' => 'ics',            'icon' => 'fa-calendar',     'color' => 'text-amber-400',   'label' => 'Event'],
                    ['value' => 'vcf',            'icon' => 'fa-address-card', 'color' => 'text-cyan-400',    'label' => 'Contact Card'],
                ] as $opt)
                    <label class="relative cursor-pointer block">
                        <input type="radio" name="type" value="{{ $opt['value'] }}" x-model="type" class="sr-only">
                        <div class="border rounded-xl p-4 text-center transition-all"
                             :class="type === '{{ $opt['value'] }}'
                                ? 'border-violet-500 bg-violet-500/10 ring-2 ring-violet-500/30'
                                : 'border-white/10 hover:bg-white/[0.04]'">
                            <i class="fas {{ $opt['icon'] }} {{ $opt['color'] }} text-xl mb-2"></i>
                            <div class="text-sm font-medium text-white/80">{{ $opt['label'] }}</div>
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
