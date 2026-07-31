@extends('user.layouts.app')
@section('title', 'Edit Folder')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.projects.index') }}" class="text-white/30 hover:text-white/50"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-white">Edit Folder</h1>
    </div>

    <form method="POST" action="{{ route('user.projects.update', $project) }}">
        @csrf @method('PUT')
        @include('user.projects._form', ['project' => $project])

        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('user.projects.index') }}" class="px-4 py-2.5 text-sm text-white/60 hover:bg-white/10 rounded-xl">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Save Changes</button>
        </div>
    </form>
</div>
@endsection
