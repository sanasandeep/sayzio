@extends('user.layouts.app')
@section('title', 'Create File Link')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-gray-900">Create File Link</h1>
    </div>

    <form method="POST" action="{{ route('user.links.file.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">File <span class="text-red-500">*</span></label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-primary-400 transition-colors" x-data="{ fileName: '' }">
                    <input type="file" name="file" @change="fileName = $event.target.files[0]?.name || ''" class="hidden" id="file-input" required>
                    <label for="file-input" class="cursor-pointer">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-3 block"></i>
                        <p class="text-sm text-gray-600" x-show="!fileName">Click to upload a file (max {{ $maxFileSizeMb }}MB)</p>
                        <p class="text-sm text-primary-600 font-medium" x-show="fileName" x-text="fileName" x-cloak></p>
                    </label>
                </div>
                @error('file') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input type="text" name="title" value="{{ old('title') }}" placeholder="Optional title (defaults to file name)" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Custom Alias</label>
                    <input type="text" name="alias" value="{{ old('alias') }}" placeholder="auto-generated" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
                    @error('alias') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                    <select name="project_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
                        <option value="">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Expiration</label>
                <input type="datetime-local" name="expires_at" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
            </div>

            <div class="flex items-center gap-3">
                <input type="hidden" name="show_download_page" value="0">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="show_download_page" value="1" checked class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
                <div>
                    <span class="text-sm font-medium text-gray-700">Show Download Page</span>
                    <p class="text-xs text-gray-400">When enabled, visitors see a branded download page. When disabled, the file downloads directly.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('user.links.index') }}" class="px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</a>
            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium">Create File Link</button>
        </div>
    </form>
</div>
@endsection
