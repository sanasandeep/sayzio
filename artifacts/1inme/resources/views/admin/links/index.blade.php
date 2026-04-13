@extends('admin.layouts.app')
@section('title', 'All Links')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">All Links</h1>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</div>
        <div class="text-sm text-gray-500">Total Links</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="text-2xl font-bold text-green-600">{{ number_format($stats['active']) }}</div>
        <div class="text-sm text-gray-500">Active</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="text-2xl font-bold text-blue-600">{{ number_format($stats['total_clicks']) }}</div>
        <div class="text-sm text-gray-500">Total Clicks</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="text-xs text-gray-500 mb-1">By Type</div>
        <div class="flex flex-wrap gap-1">
            @foreach($stats['types'] as $type => $count)
                <span class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">{{ ucfirst($type) }}: {{ $count }}</span>
            @endforeach
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
    <form method="GET" action="{{ route('admin.links.index') }}" class="flex flex-wrap gap-3">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search links, users..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64 focus:ring-2 focus:ring-primary-500">
        <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">All Types</option>
            @foreach(['url', 'biolink', 'file', 'ics', 'vcf'] as $t)
                <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
            @endforeach
        </select>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">All Status</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
        <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700">Filter</button>
        @if(request()->hasAny(['search', 'type', 'status']))
            <a href="{{ route('admin.links.index') }}" class="text-gray-500 hover:text-gray-700 px-3 py-2 text-sm">Clear</a>
        @endif
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Link</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clicks</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($links as $link)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="text-sm font-medium text-gray-900">{{ Str::limit($link->title ?: $link->alias, 35) }}</div>
                    <div class="text-xs text-gray-500 font-mono">{{ $link->getShortUrl() }}</div>
                    @if($link->long_url)
                        <div class="text-xs text-blue-500 truncate max-w-xs">{{ Str::limit($link->long_url, 50) }}</div>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="text-sm text-gray-900">{{ $link->user->name }}</div>
                    <div class="text-xs text-gray-500">{{ $link->user->email }}</div>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                        {{ $link->type === 'url' ? 'bg-blue-50 text-blue-700' : '' }}
                        {{ $link->type === 'biolink' ? 'bg-purple-50 text-purple-700' : '' }}
                        {{ $link->type === 'file' ? 'bg-green-50 text-green-700' : '' }}
                        {{ $link->type === 'ics' ? 'bg-orange-50 text-orange-700' : '' }}
                        {{ $link->type === 'vcf' ? 'bg-pink-50 text-pink-700' : '' }}
                    ">{{ ucfirst($link->type) }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ number_format($link->total_clicks) }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded text-xs {{ $link->is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                        {{ $link->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500">{{ $link->created_at->format('M d, Y') }}</td>
                <td class="px-4 py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('admin.links.show', $link) }}" class="text-primary-600 hover:text-primary-700 text-sm">View</a>
                        <form method="POST" action="{{ route('admin.links.toggle', $link) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-yellow-600 hover:text-yellow-700 text-sm">{{ $link->is_active ? 'Disable' : 'Enable' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.links.destroy', $link) }}" class="inline" onsubmit="return confirm('Delete this link?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-700 text-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No links found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $links->links() }}</div>
@endsection
