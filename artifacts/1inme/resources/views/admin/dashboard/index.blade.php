@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl p-6 border border-dark-200 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-dark-500">Total Users</p>
                <p class="text-2xl font-bold text-dark-800 mt-1">{{ number_format($stats['total_users']) }}</p>
            </div>
            <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-purple-600 text-lg"></i>
            </div>
        </div>
        <p class="text-xs text-green-600 mt-3"><i class="fas fa-arrow-up mr-1"></i>{{ $stats['users_today'] }} today</p>
    </div>

    <div class="bg-white rounded-xl p-6 border border-dark-200 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-dark-500">Active Users</p>
                <p class="text-2xl font-bold text-dark-800 mt-1">{{ number_format($stats['active_users']) }}</p>
            </div>
            <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                <i class="fas fa-user-check text-green-600 text-lg"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 border border-dark-200 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-dark-500">Staff Members</p>
                <p class="text-2xl font-bold text-dark-800 mt-1">{{ number_format($stats['total_staff']) }}</p>
            </div>
            <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center">
                <i class="fas fa-user-shield text-purple-600 text-lg"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 border border-dark-200 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-dark-500">This Month</p>
                <p class="text-2xl font-bold text-dark-800 mt-1">{{ number_format($stats['users_this_month']) }}</p>
            </div>
            <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center">
                <i class="fas fa-calendar text-orange-600 text-lg"></i>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-dark-200 shadow-sm">
    <div class="p-6 border-b border-dark-200">
        <h2 class="text-lg font-semibold text-dark-800">Recent Users</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-dark-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-dark-500 uppercase">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-100">
                @forelse($stats['recent_users'] as $user)
                <tr class="hover:bg-dark-50">
                    <td class="px-6 py-4 text-sm text-dark-800">{{ $user->name }}</td>
                    <td class="px-6 py-4 text-sm text-dark-500">{{ $user->email }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $user->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ ucfirst($user->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-dark-500">{{ $user->created_at->diffForHumans() }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-dark-400">No users yet</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
