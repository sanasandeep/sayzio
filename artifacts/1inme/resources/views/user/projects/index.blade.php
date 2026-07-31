@extends('user.layouts.app')
@section('title', 'Folders')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Folders</h1>
        <p class="text-white/40 text-sm mt-1">Organize your links into folders — drop any link inside, click a folder to open it</p>
    </div>
    <div class="flex items-center gap-2">
        @php
            $sortOptions = [
                'name' => 'Name',
                'links' => 'Number of links',
                'active' => 'Active links',
                'clicks' => 'Clicks',
                'modified' => 'Last modified',
                'created' => 'Date created',
            ];
        @endphp
        <form method="GET" class="flex items-center gap-2">
            <select name="sort" onchange="this.form.submit()" class="theme-input appearance-none pr-8 text-sm">
                @foreach($sortOptions as $key => $label)
                    <option value="{{ $key }}" {{ $sort === $key ? 'selected' : '' }} class="bg-[#0a0612]">Sort: {{ $label }}</option>
                @endforeach
            </select>
            <input type="hidden" name="dir" value="{{ $dir }}">
            <button type="submit" name="dir" value="{{ $dir === 'asc' ? 'desc' : 'asc' }}"
                    class="p-2.5 rounded-xl border border-white/10 text-white/50 hover:text-white hover:bg-white/10"
                    title="{{ $dir === 'asc' ? 'Ascending — click for descending' : 'Descending — click for ascending' }}">
                <i class="fas {{ $dir === 'asc' ? 'fa-arrow-up-short-wide' : 'fa-arrow-down-wide-short' }} text-sm"></i>
            </button>
        </form>
        <a href="{{ route('user.projects.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2">
            <i class="fas fa-folder-plus"></i> New Folder
        </a>
    </div>
</div>

@if($projects->isEmpty())
<div class="glass rounded-2xl p-12 text-center">
    <div class="w-16 h-16 bg-blue-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-folder text-blue-400 text-2xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-white mb-2">No folders yet</h3>
    <p class="text-white/40 mb-4">Create a folder to organize your links — just like files on your computer.</p>
    <a href="{{ route('user.projects.create') }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium">
        <i class="fas fa-folder-plus"></i> Create Folder
    </a>
</div>
@else
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
    @foreach($projects as $project)
    @php $c = $project->color ?: '#3b82f6'; @endphp
    <div class="group relative rounded-2xl p-4 text-center hover:bg-white/5 transition-colors border border-transparent hover:border-white/10">
        <a href="{{ route('user.links.index', ['project_id' => $project->id]) }}" class="block" title="Open {{ $project->name }}">
            {{-- Finder-style folder icon --}}
            <svg viewBox="0 0 96 72" class="w-20 h-16 mx-auto drop-shadow-lg" aria-hidden="true">
                <path d="M6 14 a6 6 0 0 1 6-6 h22 l8 8 h42 a6 6 0 0 1 6 6 v4 H6 Z" fill="{{ $c }}" opacity="0.75"/>
                <rect x="6" y="22" width="84" height="44" rx="6" fill="{{ $c }}"/>
                <rect x="6" y="22" width="84" height="10" rx="5" fill="#ffffff" opacity="0.18"/>
            </svg>
            <div class="mt-2 font-medium text-white text-sm truncate" title="{{ $project->name }}">{{ $project->name }}</div>
            <div class="text-[11px] text-white/40 mt-0.5">
                {{ number_format($project->links_count) }} {{ Str::plural('link', $project->links_count) }}
                @if($project->total_clicks_sum)
                    · {{ number_format($project->total_clicks_sum) }} clicks
                @endif
            </div>
            <div class="text-[10px] text-white/25">
                {{ number_format($project->active_links_count) }} active · {{ $project->updated_at->diffForHumans(short: true) }}
            </div>
        </a>
        <div class="absolute top-2 right-2 flex items-center gap-0.5 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
            <a href="{{ route('user.projects.edit', $project) }}" class="p-1.5 text-white/40 hover:text-blue-400 rounded-lg bg-black/30" title="Rename / recolor"><i class="fas fa-pen text-[10px]"></i></a>
            <form action="{{ route('user.projects.destroy', $project) }}" method="POST" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this folder?', message: 'Links inside will be kept but become unfiled.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                @csrf @method('DELETE')
                <button class="p-1.5 text-white/40 hover:text-red-400 rounded-lg bg-black/30" title="Delete folder"><i class="fas fa-trash text-[10px]"></i></button>
            </form>
        </div>
    </div>
    @endforeach

    <a href="{{ route('user.projects.create') }}" class="rounded-2xl p-4 text-center border border-dashed border-white/15 hover:border-blue-400/50 hover:bg-white/5 transition-colors flex flex-col items-center justify-center min-h-[132px]">
        <div class="w-12 h-12 rounded-2xl bg-white/5 flex items-center justify-center mb-2">
            <i class="fas fa-plus text-white/40"></i>
        </div>
        <span class="text-sm text-white/40">New Folder</span>
    </a>
</div>

<div class="mt-6">{{ $projects->links() }}</div>
@endif
@endsection
