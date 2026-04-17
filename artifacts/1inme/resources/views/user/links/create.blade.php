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

        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-base font-semibold text-white mb-1">What kind of link?</h2>
            <p class="text-xs text-white/40 mb-4">Pick one to continue — we'll only ask for the fields that matter for that type.</p>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                @foreach([
                    ['value' => 'url',     'icon' => 'fa-link',         'color' => 'text-violet-400',  'label' => 'Short Link'],
                    ['value' => 'biolink', 'icon' => 'fa-id-card',      'color' => 'text-pink-400',    'label' => 'Link in Bio'],
                    ['value' => 'file',    'icon' => 'fa-file',         'color' => 'text-emerald-400', 'label' => 'File Share'],
                    ['value' => 'ics',     'icon' => 'fa-calendar',     'color' => 'text-amber-400',   'label' => 'Event Invite'],
                    ['value' => 'vcf',     'icon' => 'fa-address-card', 'color' => 'text-cyan-400',    'label' => 'Digital Card'],
                ] as $opt)
                    <label class="relative cursor-pointer">
                        <input type="radio" name="type" value="{{ $opt['value'] }}" x-model="type" class="peer sr-only">
                        <div class="peer-checked:border-violet-500 peer-checked:bg-violet-500/10 border border-white/10 rounded-xl p-4 text-center transition-all hover:bg-white/[0.04]">
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
