@extends('admin.layouts.app')
@section('title', 'Staff Management')
@section('page-title', 'Staff Management')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-4">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search staff..."
                   class="px-4 py-2 border border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
            <select name="status" class="px-3 py-2 border border-white/10 rounded-xl text-sm">
                <option value="">All Status</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-white/10 text-white/80 rounded-xl text-sm hover:bg-white/[0.06]">Filter</button>
        </form>
    </div>
    <a href="{{ route('admin.staff.create') }}" class="px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Staff
    </a>
</div>

<div class="glass rounded-2xl border border-white/10 overflow-hidden p-3">
    <table class="enhanced-table w-full">
        <thead class="bg-white/5">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Role</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Last Login</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-white/40 uppercase" data-no-sort>Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse($staff as $member)
            <tr class="hover:bg-white/5">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-violet-500/10 text-violet-300 flex items-center justify-center text-sm font-medium">
                            {{ substr($member->name, 0, 1) }}
                        </div>
                        <span class="text-sm font-medium text-white">{{ $member->name }}</span>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-white/40">{{ $member->email }}</td>
                <td class="px-6 py-4 text-sm text-white/60">{{ $member->role->name ?? 'N/A' }}</td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $member->status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                        {{ ucfirst($member->status) }}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-white/40">{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('admin.staff.edit', $member) }}" class="text-white/30 hover:text-violet-400"><i class="fas fa-edit"></i></a>
                        @if($member->id !== auth()->guard('admin')->id())
                        <form action="{{ route('admin.staff.destroy', $member) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-6 py-8 text-center text-white/30">No staff members found</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($staff->hasPages())
    <div class="px-6 py-4 border-t border-white/10">{{ $staff->links() }}</div>
    @endif
</div>
@include('common.partials.enhanced-table')
@endsection
