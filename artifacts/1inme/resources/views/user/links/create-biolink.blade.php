@extends('user.layouts.app')
@section('title', 'Create Link in Bio')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-white/30 hover:text-white transition-colors" title="Choose a different type"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">Link in Bio</h1>
            <p class="text-xs text-white/40 mt-0.5">Step 2 of 2 &middot; <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-violet-400 hover:underline">change type</a></p>
        </div>
    </div>

    <form method="POST" action="{{ route('user.links.store') }}">
        @csrf
        <input type="hidden" name="type" value="biolink">

        <div class="glass rounded-2xl p-6 mb-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1.5">Title</label>
                <input type="text" name="title" value="{{ old('title', $prefillTitle ?? '') }}" placeholder="My bio page"
                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none transition-all">
                <p class="text-xs text-white/30 mt-1">Shown in your dashboard. Visitors won't see this directly.</p>
                @error('title') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Custom Alias</label>
                    <div class="flex items-center bg-white/5 border border-white/10 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-violet-500/40">
                        <span class="bg-white/5 px-3 py-2.5 text-sm text-white/30 border-r border-white/10">{{ request()->getHost() }}/</span>
                        <input type="text" name="alias" value="{{ old('alias', $prefillAlias ?? '') }}" placeholder="auto-generated"
                               minlength="{{ ($aliasLimits ?? ['min'=>3])['min'] }}"
                               maxlength="{{ ($aliasLimits ?? ['max'=>50])['max'] }}"
                               pattern="[A-Za-z0-9_\-]+"
                               class="flex-1 px-3 py-2.5 text-sm bg-transparent text-white placeholder-white/20 border-0 focus:ring-0 outline-none">
                    </div>
                    @error('alias') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1.5">Project</label>
                    <select name="project_id" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        <option value="" class="bg-[#0d0818]">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }} class="bg-[#0d0818]">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="text-xs text-white/40 bg-violet-500/5 border border-violet-500/10 rounded-xl px-4 py-3">
                <i class="fas fa-info-circle text-violet-400 mr-1.5"></i>
                After creating, you'll pick a starting template (or skip) and land in the Link in Bio editor where you can add blocks, customize the look, and more.
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">Back</a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-violet-500/20">
                Create Link in Bio <i class="fas fa-arrow-right ml-1.5 text-xs"></i>
            </button>
        </div>
    </form>
</div>
@endsection
