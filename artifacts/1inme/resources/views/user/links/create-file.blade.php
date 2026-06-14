@extends('user.layouts.app')
@section('title', 'Create File Share')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-white/30 hover:text-white/50" title="Choose a different type"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">Create File Share</h1>
            <p class="text-xs text-white/40 mt-0.5">Step 2 of 2 &middot; <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-violet-400 hover:underline">change type</a></p>
        </div>
    </div>

    <form method="POST" action="{{ route('user.links.file.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                @include('user.partials.dropzone-input', [
                    'name'     => 'file',
                    'label'    => 'File',
                    'required' => true,
                    'policy'   => \App\Services\UploadPolicy::for('link.file_share', auth()->user()),
                    'hint'     => 'Drop here or click to browse',
                ])
                @error('file') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Title</label>
                <input type="text" name="title" value="{{ old('title', $prefillTitle ?? '') }}" placeholder="Optional title (defaults to file name)" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Custom Alias</label>
                    <input type="text" name="alias" value="{{ old('alias', $prefillAlias ?? '') }}" placeholder="auto-generated" minlength="{{ ($aliasLimits ?? ['min'=>3])['min'] }}" maxlength="{{ ($aliasLimits ?? ['max'=>50])['max'] }}" pattern="[A-Za-z0-9_\-]+" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                    @error('alias') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Project</label>
                    <select name="project_id" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
                        <option value="">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Expiration</label>
                <input type="datetime-local" name="expires_at" class="border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40">
            </div>

            <div class="flex items-center gap-3">
                <input type="hidden" name="show_download_page" value="0">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="show_download_page" value="1" checked class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-violet-600 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
                <div>
                    <span class="text-sm font-medium text-white/60">Show Download Page</span>
                    <p class="text-xs text-white/30">When enabled, visitors see a branded download page. When disabled, the file downloads directly.</p>
                </div>
            </div>

            @if(workspace_owner()->userCanUseLinkSetting('deep_link'))
            <div class="flex items-center gap-3">
                <input type="hidden" name="open_in_app" value="0">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="open_in_app" value="1" {{ old('open_in_app') ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-violet-600 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
                <div>
                    <span class="text-sm font-medium text-white/60">Open in app on mobile</span>
                    <p class="text-xs text-white/30">If the file's link points at a supported app, mobile visitors get a deep-link interstitial before downloading.</p>
                </div>
            </div>
            @endif
        </div>

        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('user.links.index') }}" class="px-4 py-2.5 text-sm text-white/60 hover:bg-white/10 rounded-xl">Cancel</a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Create File Share</button>
        </div>
    </form>
</div>
@endsection
