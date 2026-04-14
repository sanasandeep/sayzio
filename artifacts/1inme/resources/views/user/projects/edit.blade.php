@extends('user.layouts.app')
@section('title', 'Edit Project')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.projects.index') }}" class="text-white/30 hover:text-white/50"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-white">Edit Project</h1>
    </div>

    <form method="POST" action="{{ route('user.projects.update', $project) }}">
        @csrf @method('PUT')
        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $project->name) }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500/40 focus:border-purple-500/40" required>
                @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Color</label>
                <input type="color" name="color" value="{{ old('color', $project->color) }}" class="h-10 w-20 border border-white/10 rounded-xl cursor-pointer">
            </div>
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500/40 focus:border-purple-500/40">{{ old('description', $project->description) }}</textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('user.projects.index') }}" class="px-4 py-2.5 text-sm text-white/60 hover:bg-white/10 rounded-xl">Cancel</a>
            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Save Changes</button>
        </div>
    </form>
</div>
@endsection
