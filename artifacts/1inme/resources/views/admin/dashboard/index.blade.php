@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="glass rounded-2xl p-6 border border-white/10 ">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white/40">Total Users</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['total_users']) }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-blue-400 text-lg"></i>
            </div>
        </div>
        <p class="text-xs text-emerald-400 mt-3"><i class="fas fa-arrow-up mr-1"></i>{{ $stats['users_today'] }} today</p>
    </div>

    <div class="glass rounded-2xl p-6 border border-white/10 ">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white/40">Active Users</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['active_users']) }}</p>
            </div>
            <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-user-check text-emerald-400 text-lg"></i>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl p-6 border border-white/10 ">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white/40">Staff Members</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['total_staff']) }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-user-shield text-blue-400 text-lg"></i>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl p-6 border border-white/10 ">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white/40">This Month</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['users_this_month']) }}</p>
            </div>
            <div class="w-12 h-12 bg-amber-500/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-calendar text-amber-400 text-lg"></i>
            </div>
        </div>
    </div>
</div>

<div class="glass rounded-2xl border border-white/10 ">
    <div class="p-6 border-b border-white/10">
        <h2 class="text-lg font-semibold text-white">Recent Users</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-white/5">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($stats['recent_users'] as $user)
                <tr class="hover:bg-white/5">
                    <td class="px-6 py-4 text-sm text-white">{{ $user->name }}</td>
                    <td class="px-6 py-4 text-sm text-white/40">{{ $user->email }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $user->status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                            {{ ucfirst($user->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-white/40">{{ $user->created_at->diffForHumans() }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-white/30">No users yet</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
