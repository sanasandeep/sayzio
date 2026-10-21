@extends('user.layouts.app')

@section('title', 'Create Text Page')

@push('head')
<meta name="robots" content="noindex">
@endpush

@section('content')
<div class="py-8 px-4">

    @include('user.partials.create-step-header', [
        'title' => 'Create a Text Page',
        'icon' => 'fa-align-left',
        'subtitle' => 'Paste or type any text and get a short link. Visitors see a clean page with your text, and every visit is tracked.',
    ])


    @if(session('error'))
    <div class="mb-6 bg-red-500/10 border border-red-500/30 rounded-xl p-4 text-red-300 text-sm">
        {{ session('error') }}
    </div>
    @endif

    <form method="POST" action="{{ route('user.links.store') }}"
          class="space-y-6" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <input type="hidden" name="type" value="text">

        {{-- Text content --}}
        <div>
            <label for="text_content" class="block text-sm font-medium text-white/80 mb-1.5">Your text <span class="text-red-400">*</span></label>
            <textarea id="text_content" name="text_content" rows="10" required maxlength="20000"
                      class="glass-input w-full font-mono text-sm leading-relaxed"
                      placeholder="Paste or type the text you want to share…">{{ old('text_content') }}</textarea>
            <p class="text-xs text-white/40 mt-1">Plain text only, up to 20,000 characters. You can edit it later from the link's settings.</p>
            @error('text_content')
                <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Title --}}
        <div>
            <label for="title" class="block text-sm font-medium text-white/80 mb-1.5">Title <span class="text-white/40 font-normal">(optional)</span></label>
            <input type="text" id="title" name="title" value="{{ old('title') }}"
                   class="glass-input w-full" placeholder="e.g. Meeting notes, Snippet, Announcement" maxlength="255">
            <p class="text-xs text-white/40 mt-1">Shown as the page heading and in your links list.</p>
            @error('title')
                <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Custom alias --}}
        <div>
            <label for="alias" class="block text-sm font-medium text-white/80 mb-1.5">Custom URL <span class="text-white/40 font-normal">(optional)</span></label>
            <div class="flex items-center glass-input pr-0 overflow-hidden">
                <span class="pl-4 pr-2 text-white/40 text-sm shrink-0">{{ $domainHost }}/</span>
                <input type="text" id="alias" name="alias" value="{{ old('alias', $prefillAlias) }}"
                       class="flex-1 bg-transparent border-0 outline-none py-0 px-2 text-sm text-white placeholder-white/30"
                       placeholder="my-text"
                       minlength="{{ $aliasLimits['min'] }}"
                       maxlength="{{ $aliasLimits['max'] }}">
            </div>
            @error('alias')
                <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Domain --}}
        @if($domains->count() > 1)
        <div>
            <label for="domain_id" class="block text-sm font-medium text-white/80 mb-1.5">Domain</label>
            <select id="domain_id" name="domain_id" class="glass-input w-full">
                @foreach($domains as $domain)
                    <option value="{{ $domain->id }}" @selected(old('domain_id', $defaultDomainId) == $domain->id)>
                        {{ $domain->domain }}
                        @if(!$domain->is_verified) (unverified) @endif
                    </option>
                @endforeach
            </select>
        </div>
        @endif

        {{-- Project --}}
        @if($projects->count() > 0)
        <div>
            <label for="project_id" class="block text-sm font-medium text-white/80 mb-1.5">Folder <span class="text-white/40 font-normal">(optional)</span></label>
            <select id="project_id" name="project_id" class="glass-input w-full">
                <option value="">No folder</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        {{-- Submit --}}
        <div class="pt-2">
            <button type="submit" :disabled="submitting"
                    class="btn-primary w-full flex items-center justify-center gap-2 py-3">
                <span x-show="!submitting">Create Text Page</span>
                <span x-show="submitting" class="flex items-center gap-2">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Creating…
                </span>
            </button>
        </div>
    </form>
</div>
@endsection
