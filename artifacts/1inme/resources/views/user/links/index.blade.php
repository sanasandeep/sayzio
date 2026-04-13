@extends('user.layouts.app')
@section('title', 'My Links')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">My Links</h1>
        <p class="text-gray-500 text-sm mt-1">Manage all your shortened links</p>
    </div>
    <a href="{{ route('user.links.create') }}" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
        <i class="fas fa-plus"></i> Create Link
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
    <form method="GET" class="p-4 flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-gray-500 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search links..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Type</label>
            <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                <option value="">All Types</option>
                <option value="url" {{ request('type') === 'url' ? 'selected' : '' }}>URL Shortener</option>
                <option value="biolink" {{ request('type') === 'biolink' ? 'selected' : '' }}>Bio Link</option>
                <option value="file" {{ request('type') === 'file' ? 'selected' : '' }}>File Link</option>
                <option value="ics" {{ request('type') === 'ics' ? 'selected' : '' }}>ICS</option>
                <option value="vcf" {{ request('type') === 'vcf' ? 'selected' : '' }}>VCF</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Project</label>
            <select name="project_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                <option value="">All Projects</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Status</label>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                <option value="">All</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">Filter</button>
    </form>
</div>

@if($links->isEmpty())
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
    <div class="w-16 h-16 bg-primary-50 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-link text-primary-500 text-2xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">No links yet</h3>
    <p class="text-gray-500 mb-4">Create your first link to start tracking clicks.</p>
    <a href="{{ route('user.links.create') }}" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        <i class="fas fa-plus"></i> Create Link
    </a>
</div>
@else
<div class="space-y-3">
    @foreach($links as $link)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-primary-200 transition-colors">
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-4 flex-1 min-w-0">
                <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center
                    {{ $link->type === 'url' ? 'bg-blue-50 text-blue-600' : '' }}
                    {{ $link->type === 'biolink' ? 'bg-purple-50 text-purple-600' : '' }}
                    {{ $link->type === 'file' ? 'bg-green-50 text-green-600' : '' }}
                    {{ $link->type === 'ics' ? 'bg-orange-50 text-orange-600' : '' }}
                    {{ $link->type === 'vcf' ? 'bg-pink-50 text-pink-600' : '' }}">
                    <i class="fas {{ $link->type === 'url' ? 'fa-link' : ($link->type === 'biolink' ? 'fa-id-card' : ($link->type === 'file' ? 'fa-file' : ($link->type === 'ics' ? 'fa-calendar' : 'fa-address-card'))) }}"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <a href="{{ route('user.links.show', $link) }}" class="font-semibold text-gray-900 hover:text-primary-600 truncate">
                            {{ $link->title ?: $link->alias }}
                        </a>
                        @if(!$link->is_active)
                            <span class="text-xs bg-red-50 text-red-600 px-2 py-0.5 rounded-full">Inactive</span>
                        @endif
                        @if($link->is_password_protected)
                            <i class="fas fa-lock text-gray-400 text-xs" title="Password protected"></i>
                        @endif
                        @if($link->expires_at)
                            <i class="fas fa-clock text-gray-400 text-xs" title="Expires {{ $link->expires_at->format('M d, Y') }}"></i>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 text-sm text-primary-600 mb-1" x-data="{ copied: false }">
                        <span class="truncate">{{ $link->getShortUrl() }}</span>
                        <button @click="navigator.clipboard.writeText('{{ $link->getShortUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-gray-400 hover:text-primary-600 flex-shrink-0">
                            <i x-show="!copied" class="fas fa-copy"></i>
                            <i x-show="copied" x-cloak class="fas fa-check text-green-500"></i>
                        </button>
                    </div>
                    @if($link->long_url)
                    <p class="text-xs text-gray-400 truncate">{{ $link->long_url }}</p>
                    @endif
                    <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                        @if($link->project)
                            <span class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full" style="background-color: {{ $link->project->color }}"></span>
                                {{ $link->project->name }}
                            </span>
                        @endif
                        <span>{{ $link->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4 ml-4">
                <div class="text-center">
                    <div class="text-lg font-bold text-gray-900">{{ number_format($link->total_clicks) }}</div>
                    <div class="text-xs text-gray-500">Clicks</div>
                </div>
                <div class="flex items-center gap-1">
                    <a href="{{ route('user.links.show', $link) }}" class="p-2 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg" title="View">
                        <i class="fas fa-chart-bar"></i>
                    </a>
                    <a href="{{ route('user.links.edit', $link) }}" class="p-2 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form action="{{ route('user.links.destroy', $link) }}" method="POST" onsubmit="return confirm('Delete this link?')">
                        @csrf @method('DELETE')
                        <button class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="mt-6">{{ $links->links() }}</div>
@endif
@endsection
