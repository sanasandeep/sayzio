@extends('user.layouts.app')
@php
    $linkType = $linkType ?? 'biolink';
    // Label comes from the shared link-type catalog so it never drifts from
    // the rest of the app; only the title placeholder copy is view-specific.
    $typeLabel   = \App\Modules\User\Models\Link::typeLabel($linkType);
    $placeholder = [
        'biolink'        => 'My Link in Bio',
        'conversational' => 'My Conversational Page',
        'slides'         => 'My Slides Page',
        'ai_chat'        => 'My AI Chatbot',
    ][$linkType] ?? 'My ' . $typeLabel;
@endphp
@section('title', 'Create ' . $typeLabel)

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-white/30 hover:text-white transition-colors" title="Choose a different type"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">{{ $typeLabel }}</h1>
            <p class="text-xs text-white/40 mt-0.5">Step 2 of 2 &middot; <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-blue-400 hover:underline">change type</a></p>
        </div>
    </div>

    <form method="POST" action="{{ route('user.links.store') }}">
        @csrf
        <input type="hidden" name="type" value="{{ $linkType }}">

        <div class="glass rounded-2xl p-6 mb-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1.5">Title</label>
                <input type="text" name="title" value="{{ old('title', $prefillTitle ?? '') }}" placeholder="{{ $placeholder }}"
                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none transition-all">
                <p class="text-xs text-white/30 mt-1">Shown in your dashboard. Visitors won't see this directly.</p>
                @error('title') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @include('user.links.partials.alias-checker')
                <div x-data="aliasChecker('{{ route('user.links.check-alias') }}')" x-init="init()">
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Custom Alias</label>
                    @php
                        $defaultHost = \App\Modules\Common\Support\PlatformHosts::currentRequestHost()
                            ?: \App\Modules\Common\Support\PlatformHosts::primary();
                    @endphp
                    <div class="flex items-center bg-white/5 border rounded-xl overflow-hidden transition-colors"
                         :class="state === 'available' ? 'border-emerald-500/40 focus-within:ring-2 focus-within:ring-emerald-500/40'
                             : (isError ? 'border-red-500/40 focus-within:ring-2 focus-within:ring-red-500/40'
                             : 'border-white/10 focus-within:ring-2 focus-within:ring-blue-500/40')">
                        @if(($domains ?? collect())->isNotEmpty())
                            @php $selectedDomainId = old('domain_id', $defaultDomainId ?? ''); @endphp
                            <select name="domain_id" class="bg-white/5 px-2 py-2.5 text-xs text-white/70 border-r border-white/10 outline-none max-w-[180px]">
                                <option value="" {{ (string) $selectedDomainId === '' ? 'selected' : '' }} class="bg-[#0d0818]">{{ $defaultHost }}/</option>
                                @foreach($domains as $d)
                                    <option value="{{ $d->id }}" {{ (string) $selectedDomainId === (string) $d->id ? 'selected' : '' }} class="bg-[#0d0818]">{{ $d->domain }}/{{ (isset($defaultDomainId) && (string) $defaultDomainId === (string) $d->id) ? ' (default)' : '' }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="bg-white/5 px-3 py-2.5 text-sm text-white/30 border-r border-white/10">{{ $defaultHost }}/</span>
                        @endif
                        <input type="text" name="alias" value="{{ old('alias', $prefillAlias ?? '') }}" placeholder="auto-generated"
                               minlength="{{ ($aliasLimits ?? ['min'=>3])['min'] }}"
                               maxlength="{{ ($aliasLimits ?? ['max'=>50])['max'] }}"
                               pattern="[A-Za-z0-9_\-]+"
                               autocomplete="off" spellcheck="false"
                               @input.debounce.400ms="check($event.target.value)"
                               class="flex-1 px-3 py-2.5 text-sm bg-transparent text-white placeholder-white/20 border-0 focus:ring-0 outline-none">
                        <span class="flex items-center px-3" x-show="state && state !== 'empty'" x-cloak>
                            <i x-show="state === 'checking'" class="fas fa-spinner fa-spin text-white/40 text-sm"></i>
                            <i x-show="state === 'available'" class="fas fa-circle-check text-emerald-400 text-sm"></i>
                            <i x-show="isError" class="fas fa-circle-xmark text-red-400 text-sm"></i>
                        </span>
                    </div>
                    <p aria-live="polite" x-show="message && state && state !== 'empty'" x-cloak
                       class="text-sm mt-1.5"
                       :class="state === 'available' ? 'text-emerald-400' : (isError ? 'text-red-400' : 'text-white/40')"
                       x-text="message"></p>
                    <p class="text-xs text-white/40 mt-1.5">
                        <i class="fas fa-info-circle mr-1"></i>{{ ($aliasLimits ?? ['min'=>3])['min'] }}–{{ ($aliasLimits ?? ['max'=>50])['max'] }} characters · letters, numbers, hyphens &amp; underscores
                    </p>
                    @error('alias') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
                    @error('domain_id') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Folder</label>
                    <select name="project_id" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                        <option value="" class="bg-[#0d0818]">No folder</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }} class="bg-[#0d0818]">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="text-xs text-white/40 bg-blue-500/5 border border-blue-500/10 rounded-xl px-4 py-3">
                <i class="fas fa-info-circle text-blue-400 mr-1.5"></i>
                After creating, you'll pick a starting template (or skip) and land in the Link in Bio editor where you can add blocks, customize the look, and more.
            </div>
        </div>

        @php($aiBuilderEnabled = $linkType === 'biolink' && \App\Services\AI\AiEngineSettings::isEnabled())

        @if($aiBuilderEnabled)
        <div class="glass rounded-2xl p-5 mb-6 border border-blue-500/20 bg-gradient-to-br from-blue-500/10 to-fuchsia-500/5">
            <div class="flex items-start gap-3">
                <div class="shrink-0 w-10 h-10 rounded-xl bg-blue-600/30 flex items-center justify-center">
                    <i class="fas fa-wand-magic-sparkles text-blue-300"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-white">Build with AI <span class="ml-1.5 text-[10px] uppercase tracking-wide text-blue-300 bg-blue-500/20 px-1.5 py-0.5 rounded">New</span></h3>
                    <p class="text-xs text-white/50 mt-1">Skip the blank page, describe your page, paste your links, and add photos. AI assembles a complete Link in Bio for you to refine in the editor. Uses coins.</p>
                </div>
            </div>
        </div>
        @endif

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">Back</a>
            @if($aiBuilderEnabled)
            <button type="submit" name="start_mode" value="ai" class="bg-white/5 hover:bg-white/10 border border-blue-500/30 text-blue-200 px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-blue-500/10">
                <i class="fas fa-wand-magic-sparkles mr-1.5 text-xs"></i> Build with AI
            </button>
            @endif
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-blue-500/20">
                {{ $aiBuilderEnabled ? 'Start blank' : 'Create Link in Bio' }} <i class="fas fa-arrow-right ml-1.5 text-xs"></i>
            </button>
        </div>
    </form>
</div>
@endsection
