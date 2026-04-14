@extends('user.layouts.app')
@section('title', 'Projects')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Projects</h1>
        <p class="text-white/40 text-sm mt-1">Organize your links into projects</p>
    </div>
    <a href="{{ route('user.projects.create') }}" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2">
        <i class="fas fa-plus"></i> New Project
    </a>
</div>

@if($projects->isEmpty())
<div class="glass rounded-2xl p-12 text-center">
    <div class="w-16 h-16 bg-purple-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-folder text-purple-400 text-2xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-white mb-2">No projects yet</h3>
    <p class="text-white/40 mb-4">Create a project to organize your links.</p>
    <a href="{{ route('user.projects.create') }}" class="inline-flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-xl text-sm font-medium">
        <i class="fas fa-plus"></i> Create Project
    </a>
</div>
@else
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($projects as $project)
    <div class="glass rounded-2xl p-5 hover:border-primary-200 transition-colors">
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background-color: {{ $project->color }}20">
                    <i class="fas fa-folder" style="color: {{ $project->color }}"></i>
                </div>
                <div>
                    <a href="{{ route('user.projects.show', $project) }}" class="font-semibold text-white hover:text-purple-400">{{ $project->name }}</a>
                    <div class="text-xs text-white/40 mt-0.5">{{ $project->links_count }} links</div>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <a href="{{ route('user.projects.edit', $project) }}" class="p-2 text-white/30 hover:text-purple-400 rounded-xl"><i class="fas fa-edit text-xs"></i></a>
                <form action="{{ route('user.projects.destroy', $project) }}" method="POST" onsubmit="return confirm('Delete this project? Links will be kept but unassigned.')">
                    @csrf @method('DELETE')
                    <button class="p-2 text-white/30 hover:text-red-400 rounded-xl"><i class="fas fa-trash text-xs"></i></button>
                </form>
            </div>
        </div>
        @if($project->description)
        <p class="text-sm text-white/40 line-clamp-2">{{ $project->description }}</p>
        @endif
    </div>
    @endforeach
</div>

<div class="mt-6">{{ $projects->links() }}</div>
@endif
@endsection
