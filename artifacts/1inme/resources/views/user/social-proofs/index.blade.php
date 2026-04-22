@extends('user.layouts.app')
@section('title', 'Buzz')

@section('content')
@include('user.partials._plan_lock', ['feature' => 'buzz_popups', 'kind' => 'flag', 'label' => 'Social-proof buzz popups'])
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Buzz</h1>
        <p class="text-white/40 text-sm mt-1">Build trust with notification widgets you can embed anywhere — including your biolinks.</p>
    </div>
    <a href="{{ route('user.social-proofs.create') }}" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2">
        <i class="fas fa-plus"></i> New Notification
    </a>
</div>

@if(session('success'))
<div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>
@endif

@if($proofs->isEmpty())
<div class="glass rounded-2xl p-12 text-center">
    <div class="w-16 h-16 bg-violet-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-bell text-violet-400 text-2xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-white mb-2">No notifications yet</h3>
    <p class="text-white/40 mb-4">Create a notification to display recent activity, visitor counts, countdowns, reviews and more.</p>
    <a href="{{ route('user.social-proofs.create') }}" class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium">
        <i class="fas fa-plus"></i> Create your first
    </a>
</div>
@else
<div class="glass rounded-2xl overflow-hidden p-3">
    <table class="enhanced-table w-full text-sm">
        <thead class="bg-white/5 border-b border-white/10">
            <tr>
                <th class="text-left px-4 py-3 text-white/70 font-medium">Name</th>
                <th class="text-left px-4 py-3 text-white/70 font-medium">Notifications</th>
                <th class="text-left px-4 py-3 text-white/70 font-medium">Impressions</th>
                <th class="text-left px-4 py-3 text-white/70 font-medium">Clicks</th>
                <th class="text-left px-4 py-3 text-white/70 font-medium">CTR</th>
                <th class="text-left px-4 py-3 text-white/70 font-medium">Status</th>
                <th class="text-right px-4 py-3 text-white/70 font-medium">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($proofs as $p)
            <tr class="border-b border-white/5 hover:bg-white/5">
                <td class="px-4 py-3 text-white">
                    <a href="{{ route('user.social-proofs.edit', $p) }}" class="font-medium hover:text-violet-300">{{ $p->name }}</a>
                </td>
                <td class="px-4 py-3 text-white/70">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs bg-violet-500/10 text-violet-300 border border-violet-500/20">
                        <i class="fas fa-bell"></i> {{ $p->notificationCount() }}
                    </span>
                </td>
                <td class="px-4 py-3 text-white/70">{{ number_format($p->impressions) }}</td>
                <td class="px-4 py-3 text-white/70">{{ number_format($p->clicks) }}</td>
                <td class="px-4 py-3 text-white/70">{{ $p->ctr() }}%</td>
                <td class="px-4 py-3">
                    @if($p->is_active)
                        <span class="px-2 py-1 rounded-md text-xs bg-emerald-500/15 text-emerald-300 border border-emerald-500/20">Active</span>
                    @else
                        <span class="px-2 py-1 rounded-md text-xs bg-white/10 text-white/50 border border-white/10">Paused</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <form action="{{ route('user.social-proofs.toggle', $p) }}" method="POST" class="inline">@csrf
                        <button class="text-white/60 hover:text-white px-2" title="{{ $p->is_active ? 'Pause' : 'Activate' }}">
                            <i class="fas fa-{{ $p->is_active ? 'pause' : 'play' }}"></i>
                        </button>
                    </form>
                    <a href="{{ route('user.social-proofs.edit', $p) }}" class="text-violet-300 hover:text-violet-200 px-2"><i class="fas fa-edit"></i></a>
                    <form action="{{ route('user.social-proofs.destroy', $p) }}" method="POST" class="inline" onsubmit="return confirm('Delete this notification campaign?')">
                        @csrf @method('DELETE')
                        <button class="text-rose-400 hover:text-rose-300 px-2"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
