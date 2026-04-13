@extends('admin.layouts.app')
@section('title', 'Users')
@section('page-title', 'User Management')

@section('content')
<div class="flex items-center justify-between mb-6">
    <form method="GET" class="flex items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search users..."
               class="px-4 py-2 border border-dark-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
        <select name="status" class="px-3 py-2 border border-dark-300 rounded-lg text-sm">
            <option value="">All Status</option>
            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            <option value="banned" {{ request('status') == 'banned' ? 'selected' : '' }}>Banned</option>
        </select>
        <select name="plan" class="px-3 py-2 border border-dark-300 rounded-lg text-sm">
            <option value="">All Plans</option>
            @foreach($plans as $plan)
                <option value="{{ $plan->id }}" {{ request('plan') == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 bg-dark-100 text-dark-700 rounded-lg text-sm hover:bg-dark-200">Filter</button>
    </form>
</div>

<div class="bg-white rounded-xl border border-dark-200 shadow-sm overflow-hidden">
    <table class="w-full">
        <thead class="bg-dark-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">Plan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">Joined</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-dark-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-dark-100">
            @forelse($users as $user)
            <tr class="hover:bg-dark-50">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-medium">
                            {{ substr($user->name, 0, 1) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-dark-800">{{ $user->name }}</p>
                            <p class="text-xs text-dark-400">{{ $user->email }}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-dark-600">{{ $user->plan->name ?? 'Free' }}</td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $user->status === 'active' ? 'bg-green-100 text-green-800' : ($user->status === 'banned' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                        {{ ucfirst($user->status) }}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-dark-500">{{ $user->created_at->format('M d, Y') }}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('admin.users.show', $user) }}" class="text-dark-400 hover:text-primary-600" title="View"><i class="fas fa-eye"></i></a>
                        <form action="{{ route('admin.users.impersonate', $user) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-dark-400 hover:text-orange-600" title="Login as user"><i class="fas fa-user-secret"></i></button>
                        </form>
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-dark-400 hover:text-red-600" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-6 py-8 text-center text-dark-400">No users found</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($users->hasPages())
    <div class="px-6 py-4 border-t border-dark-200">{{ $users->links() }}</div>
    @endif
</div>
@endsection
