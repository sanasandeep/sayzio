@extends('user.layouts.app')
@section('title', 'Edit Project')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.projects.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-gray-900">Edit Project</h1>
    </div>

    <form method="POST" action="{{ route('user.projects.update', $project) }}">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $project->name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                <input type="color" name="color" value="{{ old('color', $project->color) }}" class="h-10 w-20 border border-gray-300 rounded-lg cursor-pointer">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">{{ old('description', $project->description) }}</textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('user.projects.index') }}" class="px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</a>
            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium">Save Changes</button>
        </div>
    </form>
</div>
@endsection
