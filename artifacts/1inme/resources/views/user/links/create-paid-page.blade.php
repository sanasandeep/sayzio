@extends('user.layouts.app')
@section('title', 'Create Bizs Profile')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-white/30 hover:text-white/50" title="Choose a different type"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">Create Bizs Profile</h1>
            <p class="text-xs text-white/40 mt-0.5">Step 2 of 2 &middot; <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-blue-400 hover:underline">change type</a></p>
        </div>
    </div>

    <form method="POST" action="{{ route('user.links.store') }}">
        @csrf
        <input type="hidden" name="type" value="paid_page">
        <input type="hidden" name="paid_page_template" id="paid_page_template" value="{{ old('paid_page_template', \App\Modules\User\Support\PaidPageTemplates::DEFAULT_ID) }}">

        <div class="glass rounded-2xl p-6 space-y-4">
            <p class="text-sm text-white/50">
                A bold, shareable home for your paid content. Your posts, subscription tiers, pay-per-view unlocks and tips appear here <strong class="text-white/70">automatically</strong> — there's no linking step. Just pick a vibrant template below; anything you publish later shows up on its own.
            </p>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Page Title</label>
                <input type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Jane's VIP Lounge" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                @error('title') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    @include('user.links.partials.alias-field')
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Project</label>
                    <select name="project_id" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                        <option value="">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if(($domains ?? collect())->count() > 0)
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Domain</label>
                <select name="domain_id" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                    @foreach($domains as $domain)
                        <option value="{{ $domain->id }}" @selected(old('domain_id', $defaultDomainId ?? null) == $domain->id)>{{ $domain->host }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>

        {{-- ── Template picker ─────────────────────────────────── --}}
        <div class="glass rounded-2xl p-6 mt-5"
             x-data="{ tpl: '{{ old('paid_page_template', \App\Modules\User\Support\PaidPageTemplates::DEFAULT_ID) }}' }"
             x-init="$watch('tpl', v => document.getElementById('paid_page_template').value = v)">
            <label class="block text-sm font-medium text-white/60 mb-3">Choose a starting template</label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach($templates as $id => $t)
                    <button type="button" @click="tpl = '{{ $id }}'"
                            :class="tpl === '{{ $id }}' ? 'ring-2 ring-blue-400 border-blue-400' : 'border-white/10 hover:border-white/30'"
                            class="text-left rounded-xl border overflow-hidden transition focus:outline-none">
                        <div class="h-20 relative" style="background: {{ $t['hero_bg'] }};">
                            <div class="absolute inset-0" style="background: {{ $t['page_bg'] }}; opacity:0.25;"></div>
                            <span class="absolute bottom-1.5 left-2 text-[10px] font-bold text-white drop-shadow">{{ $t['name'] }}</span>
                            <span x-show="tpl === '{{ $id }}'" class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-blue-500 text-white text-[10px] flex items-center justify-center"><i class="fas fa-check"></i></span>
                        </div>
                        <div class="px-2 py-1.5 bg-black/30">
                            <p class="text-[10px] text-white/50 leading-tight">{{ $t['tagline'] }}</p>
                        </div>
                    </button>
                @endforeach
            </div>
            <p class="text-[11px] text-white/40 mt-3">You can change the template and toggle public / gated access anytime in the editor.</p>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <a href="{{ route('user.links.index') }}" class="px-5 py-2.5 rounded-xl text-sm text-white/60 hover:text-white">Cancel</a>
            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">Create Bizs Profile</button>
        </div>
    </form>
</div>
@endsection
