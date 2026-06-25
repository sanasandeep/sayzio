@extends('user.layouts.app')
@section('title', 'Buzz')

@section('content')
@include('user.partials._plan_lock', ['feature' => 'buzz_popups', 'kind' => 'flag', 'label' => 'Social-proof buzz popups'])
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Buzz</h1>
        <p class="text-white/40 text-sm mt-1">Build trust with notification widgets you can embed anywhere — including your Link in Bio pages.</p>
    </div>
    <a href="{{ route('user.social-proofs.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2">
        <i class="fas fa-plus"></i> New Notification
    </a>
</div>

@isset($buzzUsage)
<div class="glass rounded-2xl p-4 mb-5">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-sm">
            <span class="text-white/70"><i class="fas fa-eye text-blue-400 mr-1.5"></i> Buzz views this month</span>
            <span class="text-white font-semibold ml-2">{{ number_format($buzzUsage['used']) }}</span>
            <span class="text-white/40">/ {{ $buzzUsage['unlimited'] ? 'Unlimited' : number_format($buzzUsage['allowance']) }}</span>
        </div>
        @if($buzzUsage['paused'])
            <span class="px-2 py-1 rounded-md text-xs bg-rose-500/15 text-rose-300 border border-rose-500/20">
                <i class="fas fa-pause mr-1"></i> Limit reached — widgets paused until next month.
                <a href="{{ route('user.upgrade') }}" class="underline hover:text-rose-200">Upgrade</a>
            </span>
        @elseif(!$buzzUsage['unlimited'])
            <span class="text-xs text-white/40">{{ number_format($buzzUsage['remaining']) }} remaining</span>
        @endif
    </div>
    @unless($buzzUsage['unlimited'])
    <div class="mt-2 h-1.5 rounded-full bg-white/10 overflow-hidden">
        <div class="h-full rounded-full {{ $buzzUsage['percent_used'] >= 100 ? 'bg-rose-500' : ($buzzUsage['percent_used'] >= 80 ? 'bg-amber-400' : 'bg-blue-500') }}" style="width: {{ $buzzUsage['percent_used'] }}%"></div>
    </div>
    @endunless
</div>
@endisset

@if(session('success'))
<div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>
@endif

@if($proofs->isEmpty())
<div class="glass rounded-2xl p-12 text-center">
    <div class="w-16 h-16 bg-blue-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-bell text-blue-400 text-2xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-white mb-2">No notifications yet</h3>
    <p class="text-white/40 mb-4">Create a notification to display recent activity, visitor counts, countdowns, reviews and more.</p>
    <a href="{{ route('user.social-proofs.create') }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium">
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
                    <a href="{{ route('user.social-proofs.edit', $p) }}" class="font-medium hover:text-blue-300">{{ $p->name }}</a>
                </td>
                <td class="px-4 py-3 text-white/70">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs bg-blue-500/10 text-blue-300 border border-blue-500/20">
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
                    <a href="{{ route('user.social-proofs.edit', $p) }}" class="text-blue-300 hover:text-blue-200 px-2"><i class="fas fa-edit"></i></a>
                    <form action="{{ route('user.social-proofs.destroy', $p) }}" method="POST" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this notification campaign?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
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
