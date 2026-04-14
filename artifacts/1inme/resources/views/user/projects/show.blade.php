@extends('user.layouts.app')
@section('title', $project->name)

@section('content')
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-4">
        <a href="{{ route('user.projects.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: {{ $project->color }}20">
                <i class="fas fa-folder" style="color: {{ $project->color }}"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $project->name }}</h1>
                @if($project->description)
                    <p class="text-gray-500 text-sm">{{ $project->description }}</p>
                @endif
            </div>
        </div>
    </div>
    <a href="{{ route('user.links.create') }}?project_id={{ $project->id }}" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
        <i class="fas fa-plus"></i> Add Link
    </a>
</div>

@if($links->isEmpty())
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
    <p class="text-gray-500">No links in this project yet.</p>
</div>
@else
<div class="space-y-3">
    @foreach($links as $link)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center bg-purple-50 text-purple-600">
                    <i class="fas fa-link text-sm"></i>
                </div>
                <div class="min-w-0">
                    <a href="{{ route('user.links.show', $link) }}" class="font-medium text-gray-900 hover:text-primary-600 truncate block">{{ $link->title ?: $link->alias }}</a>
                    <div class="text-sm text-primary-600 truncate">{{ $link->getShortUrl() }}</div>
                </div>
            </div>
            <div class="flex items-center gap-4 ml-4">
                <span class="text-sm text-gray-500">{{ number_format($link->total_clicks) }} clicks</span>
                <a href="{{ route('user.links.edit', $link) }}" class="text-gray-400 hover:text-primary-600"><i class="fas fa-edit"></i></a>
            </div>
        </div>
    </div>
    @endforeach
</div>
<div class="mt-6">{{ $links->links() }}</div>
@endif
@endsection
