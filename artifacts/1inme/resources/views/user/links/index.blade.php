@extends('user.layouts.app')
@section('title', 'My Links')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">My Links</h1>
        <p class="text-white/40 text-sm mt-1">Manage all your shortened links</p>
    </div>
    <a href="{{ route('user.links.create') }}" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 transition-all hover:shadow-lg hover:shadow-purple-500/20">
        <i class="fas fa-plus text-xs"></i> Create Link
    </a>
</div>

<div class="glass rounded-2xl mb-6">
    <form method="GET" class="p-4 flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[11px] text-white/30 uppercase tracking-wider mb-1.5">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search links..."
                   class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-purple-500/40 focus:border-purple-500/40 outline-none transition-all">
        </div>
        <div>
            <label class="block text-[11px] text-white/30 uppercase tracking-wider mb-1.5">Type</label>
            <select name="type" class="bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none appearance-none pr-8">
                <option value="" class="bg-[#1a1025]">All Types</option>
                <option value="url" {{ request('type') === 'url' ? 'selected' : '' }} class="bg-[#1a1025]">URL Shortener</option>
                <option value="biolink" {{ request('type') === 'biolink' ? 'selected' : '' }} class="bg-[#1a1025]">Bio Link</option>
                <option value="file" {{ request('type') === 'file' ? 'selected' : '' }} class="bg-[#1a1025]">File Link</option>
                <option value="ics" {{ request('type') === 'ics' ? 'selected' : '' }} class="bg-[#1a1025]">ICS</option>
                <option value="vcf" {{ request('type') === 'vcf' ? 'selected' : '' }} class="bg-[#1a1025]">VCF</option>
            </select>
        </div>
        <div>
            <label class="block text-[11px] text-white/30 uppercase tracking-wider mb-1.5">Project</label>
            <select name="project_id" class="bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none appearance-none pr-8">
                <option value="" class="bg-[#1a1025]">All Projects</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }} class="bg-[#1a1025]">{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] text-white/30 uppercase tracking-wider mb-1.5">Status</label>
            <select name="status" class="bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-purple-500/40 outline-none appearance-none pr-8">
                <option value="" class="bg-[#1a1025]">All</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }} class="bg-[#1a1025]">Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }} class="bg-[#1a1025]">Inactive</option>
            </select>
        </div>
        <button type="submit" class="bg-white/10 hover:bg-white/15 text-white/70 px-5 py-2.5 rounded-xl text-sm font-medium transition-all border border-white/10">
            <i class="fas fa-search text-xs mr-1.5"></i> Filter
        </button>
    </form>
</div>

@if($links->isEmpty())
<div class="glass rounded-2xl p-14 text-center">
    <div class="w-16 h-16 rounded-2xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center mx-auto mb-5">
        <i class="fas fa-link text-purple-400 text-2xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-white mb-2">No links yet</h3>
    <p class="text-white/40 mb-5 text-sm">Create your first link to start tracking clicks.</p>
    <a href="{{ route('user.links.create') }}" class="inline-flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-purple-500/20">
        <i class="fas fa-plus text-xs"></i> Create Link
    </a>
</div>
@else
<div class="space-y-3">
    @foreach($links as $link)
    <div class="glass rounded-2xl p-4 hover:bg-white/[0.06] transition-all group">
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-4 flex-1 min-w-0">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center text-sm
                    {{ $link->type === 'url' ? 'bg-purple-500/10 text-purple-400 border border-purple-500/20' : '' }}
                    {{ $link->type === 'biolink' ? 'bg-pink-500/10 text-pink-400 border border-pink-500/20' : '' }}
                    {{ $link->type === 'file' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                    {{ $link->type === 'ics' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : '' }}
                    {{ $link->type === 'vcf' ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20' : '' }}">
                    <i class="fas {{ $link->type === 'url' ? 'fa-link' : ($link->type === 'biolink' ? 'fa-id-card' : ($link->type === 'file' ? 'fa-file' : ($link->type === 'ics' ? 'fa-calendar' : 'fa-address-card'))) }}"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <a href="{{ route('user.links.show', $link) }}" class="font-semibold text-white hover:text-purple-400 truncate transition-colors">
                            {{ $link->title ?: $link->alias }}
                        </a>
                        @if(!$link->is_active)
                            <span class="text-[10px] bg-red-500/10 text-red-400 px-2 py-0.5 rounded-md border border-red-500/20 font-medium">Inactive</span>
                        @endif
                        @if($link->is_password_protected)
                            <i class="fas fa-lock text-white/20 text-[10px]" title="Password protected"></i>
                        @endif
                        @if($link->expires_at)
                            <i class="fas fa-clock text-white/20 text-[10px]" title="Expires {{ $link->expires_at->format('M d, Y') }}"></i>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 text-sm text-purple-400/70 mb-1" x-data="{ copied: false }">
                        <span class="truncate">{{ $link->getShortUrl() }}</span>
                        <button @click="navigator.clipboard.writeText('{{ $link->getShortUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="text-white/20 hover:text-purple-400 flex-shrink-0 transition-colors">
                            <i x-show="!copied" class="fas fa-copy text-xs"></i>
                            <i x-show="copied" x-cloak class="fas fa-check text-emerald-400 text-xs"></i>
                        </button>
                    </div>
                    @if($link->long_url)
                    <p class="text-xs text-white/20 truncate">{{ $link->long_url }}</p>
                    @endif
                    <div class="flex items-center gap-3 mt-2 text-[11px] text-white/25">
                        @if($link->project)
                            <span class="flex items-center gap-1.5">
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
                    <div class="text-lg font-bold text-white">{{ number_format($link->total_clicks) }}</div>
                    <div class="text-[11px] text-white/25">clicks</div>
                </div>
                <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    <a href="{{ route('user.links.show', $link) }}" class="p-2 text-white/30 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg transition-all" title="View">
                        <i class="fas fa-chart-bar text-sm"></i>
                    </a>
                    <a href="{{ route('user.links.edit', $link) }}" class="p-2 text-white/30 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg transition-all" title="Edit">
                        <i class="fas fa-edit text-sm"></i>
                    </a>
                    <form action="{{ route('user.links.destroy', $link) }}" method="POST" onsubmit="return confirm('Delete this link?')">
                        @csrf @method('DELETE')
                        <button class="p-2 text-white/30 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-all" title="Delete">
                            <i class="fas fa-trash text-sm"></i>
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
