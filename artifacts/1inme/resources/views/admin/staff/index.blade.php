@extends('admin.layouts.app')
@section('title', 'Staff Management')
@section('page-title', 'Staff Management')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-4">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search staff..."
                   class="px-4 py-2 border border-dark-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
            <select name="status" class="px-3 py-2 border border-dark-300 rounded-lg text-sm">
                <option value="">All Status</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-dark-100 text-dark-700 rounded-lg text-sm hover:bg-dark-200">Filter</button>
        </form>
    </div>
    <a href="{{ route('admin.staff.create') }}" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Staff
    </a>
</div>

<div class="bg-white rounded-xl border border-dark-200 shadow-sm overflow-hidden">
    <table class="w-full">
        <thead class="bg-dark-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">Role</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">Last Login</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-dark-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-dark-100">
            @forelse($staff as $member)
            <tr class="hover:bg-dark-50">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-medium">
                            {{ substr($member->name, 0, 1) }}
                        </div>
                        <span class="text-sm font-medium text-dark-800">{{ $member->name }}</span>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-dark-500">{{ $member->email }}</td>
                <td class="px-6 py-4 text-sm text-dark-600">{{ $member->role->name ?? 'N/A' }}</td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $member->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ ucfirst($member->status) }}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-dark-500">{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('admin.staff.edit', $member) }}" class="text-dark-400 hover:text-primary-600"><i class="fas fa-edit"></i></a>
                        @if($member->id !== auth()->guard('admin')->id())
                        <form action="{{ route('admin.staff.destroy', $member) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-dark-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-6 py-8 text-center text-dark-400">No staff members found</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($staff->hasPages())
    <div class="px-6 py-4 border-t border-dark-200">{{ $staff->links() }}</div>
    @endif
</div>
@endsection
