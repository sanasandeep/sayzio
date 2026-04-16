@extends('admin.layouts.app')
@section('title', 'Users')
@section('page-title', 'User Management')

@section('content')
<div class="flex items-center justify-between mb-6">
    <form method="GET" class="flex items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search users..."
               class="px-4 py-2 border border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/40 outline-none">
        <select name="status" class="px-3 py-2 border border-white/10 rounded-xl text-sm">
            <option value="">All Status</option>
            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            <option value="banned" {{ request('status') == 'banned' ? 'selected' : '' }}>Banned</option>
        </select>
        <select name="plan" class="px-3 py-2 border border-white/10 rounded-xl text-sm">
            <option value="">All Plans</option>
            @foreach($plans as $plan)
                <option value="{{ $plan->id }}" {{ request('plan') == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 bg-white/10 text-white/80 rounded-xl text-sm hover:bg-white/[0.06]">Filter</button>
    </form>
</div>

<div class="glass rounded-2xl border border-white/10 overflow-hidden p-3">
    <table class="enhanced-table w-full">
        <thead class="bg-white/5">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Plan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Joined</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-white/40 uppercase" data-no-sort>Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse($users as $user)
            <tr class="hover:bg-white/5">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-500/10 text-blue-300 flex items-center justify-center text-sm font-medium">
                            {{ substr($user->name, 0, 1) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-white">{{ $user->name }}</p>
                            <p class="text-xs text-white/30">{{ $user->email }}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-white/60">{{ $user->plan->name ?? 'Free' }}</td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $user->status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : ($user->status === 'banned' ? 'bg-red-500/10 text-red-400' : 'bg-yellow-500/10 text-yellow-400') }}">
                        {{ ucfirst($user->status) }}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-white/40">{{ $user->created_at->format('M d, Y') }}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('admin.users.show', $user) }}" class="text-white/30 hover:text-blue-400" title="View"><i class="fas fa-eye"></i></a>
                        <form action="{{ route('admin.users.impersonate', $user) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-white/30 hover:text-amber-400" title="Login as user"><i class="fas fa-user-secret"></i></button>
                        </form>
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-white/30 hover:text-red-400" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-6 py-8 text-center text-white/30">No users found</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($users->hasPages())
    <div class="px-6 py-4 border-t border-white/10">{{ $users->links() }}</div>
    @endif
</div>
@include('common.partials.enhanced-table')
@endsection
