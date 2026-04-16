@extends('admin.layouts.app')
@section('title', 'All Links')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-white">All Links</h1>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="glass rounded-2xl p-4">
        <div class="text-2xl font-bold text-white">{{ number_format($stats['total']) }}</div>
        <div class="text-sm text-white/40">Total Links</div>
    </div>
    <div class="glass rounded-2xl p-4">
        <div class="text-2xl font-bold text-emerald-400">{{ number_format($stats['active']) }}</div>
        <div class="text-sm text-white/40">Active</div>
    </div>
    <div class="glass rounded-2xl p-4">
        <div class="text-2xl font-bold text-purple-400">{{ number_format($stats['total_clicks']) }}</div>
        <div class="text-sm text-white/40">Total Clicks</div>
    </div>
    <div class="glass rounded-2xl p-4">
        <div class="text-xs text-white/40 mb-1">By Type</div>
        <div class="flex flex-wrap gap-1">
            @foreach($stats['types'] as $type => $count)
                <span class="text-xs bg-white/10 text-white/60 px-2 py-0.5 rounded">{{ ucfirst($type) }}: {{ $count }}</span>
            @endforeach
        </div>
    </div>
</div>

<div class="glass rounded-2xl p-4 mb-6">
    <form method="GET" action="{{ route('admin.links.index') }}" class="flex flex-wrap gap-3">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search links, users..." class="border border-white/10 rounded-xl px-3 py-2 text-sm w-64 focus:ring-2 focus:ring-purple-500/40">
        <select name="type" class="border border-white/10 rounded-xl px-3 py-2 text-sm">
            <option value="">All Types</option>
            @foreach(['url', 'biolink', 'file', 'ics', 'vcf'] as $t)
                <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
            @endforeach
        </select>
        <select name="status" class="border border-white/10 rounded-xl px-3 py-2 text-sm">
            <option value="">All Status</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
        <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-purple-700">Filter</button>
        @if(request()->hasAny(['search', 'type', 'status']))
            <a href="{{ route('admin.links.index') }}" class="text-white/40 hover:text-white/60 px-3 py-2 text-sm">Clear</a>
        @endif
    </form>
</div>

<form id="bulkForm" method="POST" action="{{ route('admin.links.bulk') }}">
    @csrf
    <div id="bulkBar" class="hidden bg-gray-800 text-white rounded-xl p-3 mb-4 flex items-center justify-between">
        <span><strong id="selectedCount">0</strong> link(s) selected</span>
        <div class="flex items-center gap-2">
            <button type="submit" name="action" value="enable" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-xl text-sm font-medium">Enable</button>
            <button type="submit" name="action" value="disable" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1.5 rounded-xl text-sm font-medium">Disable</button>
            <button type="submit" name="action" value="delete" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-xl text-sm font-medium" onclick="return confirm('Delete selected links?')">Delete</button>
        </div>
    </div>

    <div class="glass rounded-2xl overflow-hidden p-3">
        <table class="enhanced-table min-w-full divide-y divide-white/5">
            <thead class="bg-white/5">
                <tr>
                    <th class="px-4 py-3 text-left" data-no-sort>
                        <input type="checkbox" id="selectAll" class="rounded border-white/10 text-purple-400 focus:ring-purple-500/40">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-white/40 uppercase">Link</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-white/40 uppercase">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-white/40 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-white/40 uppercase">Clicks</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-white/40 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-white/40 uppercase">Created</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-white/40 uppercase" data-no-sort>Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($links as $link)
                <tr class="hover:bg-white/5">
                    <td class="px-4 py-3">
                        <input type="checkbox" name="link_ids[]" value="{{ $link->id }}" class="link-checkbox rounded border-white/10 text-purple-400 focus:ring-purple-500/40">
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-medium text-white">{{ Str::limit($link->title ?: $link->alias, 35) }}</div>
                        <div class="text-xs text-white/40 font-mono">{{ $link->getShortUrl() }}</div>
                        @if($link->long_url)
                            <div class="text-xs text-purple-500 truncate max-w-xs">{{ Str::limit($link->long_url, 50) }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm text-white">{{ $link->user->name }}</div>
                        <div class="text-xs text-white/40">{{ $link->user->email }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            {{ $link->type === 'url' ? 'bg-purple-500/10 text-purple-400' : '' }}
                            {{ $link->type === 'biolink' ? 'bg-purple-500/10 text-purple-400' : '' }}
                            {{ $link->type === 'file' ? 'bg-emerald-500/10 text-emerald-400' : '' }}
                            {{ $link->type === 'ics' ? 'bg-amber-500/10 text-amber-400' : '' }}
                            {{ $link->type === 'vcf' ? 'bg-pink-50 text-pink-700' : '' }}
                        ">{{ ucfirst($link->type) }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-white/60">{{ number_format($link->total_clicks) }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded text-xs {{ $link->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                            {{ $link->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-white/40">{{ $link->created_at->format('M d, Y') }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.links.show', $link) }}" class="text-purple-400 hover:text-purple-300 text-sm">View</a>
                            <button type="button" class="text-yellow-600 hover:text-yellow-700 text-sm" onclick="document.getElementById('toggle-form-{{ $link->id }}').submit()">{{ $link->is_active ? 'Disable' : 'Enable' }}</button>
                            <button type="button" class="text-red-400 hover:text-red-400 text-sm" onclick="if(confirm('Delete this link?')) document.getElementById('delete-form-{{ $link->id }}').submit()">Delete</button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-white/40">No links found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</form>

@foreach($links as $link)
<form id="toggle-form-{{ $link->id }}" method="POST" action="{{ route('admin.links.toggle', $link) }}" class="hidden">@csrf</form>
<form id="delete-form-{{ $link->id }}" method="POST" action="{{ route('admin.links.destroy', $link) }}" class="hidden">@csrf @method('DELETE')</form>
@endforeach

<div class="mt-4">{{ $links->links() }}</div>

@include('common.partials.enhanced-table')

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.link-checkbox');
    const bulkBar = document.getElementById('bulkBar');
    const selectedCount = document.getElementById('selectedCount');

    function updateBulkBar() {
        const checked = document.querySelectorAll('.link-checkbox:checked').length;
        selectedCount.textContent = checked;
        bulkBar.classList.toggle('hidden', checked === 0);
    }

    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkBar();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            selectAll.checked = document.querySelectorAll('.link-checkbox:checked').length === checkboxes.length;
            updateBulkBar();
        });
    });
});
</script>
@endsection
