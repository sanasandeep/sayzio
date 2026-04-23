@extends('admin.layouts.app')
@section('title', 'Plans')
@section('page-title', 'Subscription Plans')

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-white/40">Manage subscription plans and pricing</p>
    <a href="{{ route('admin.plans.create') }}" class="px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Plan
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($plans as $plan)
    <div class="glass rounded-2xl border border-white/10  p-6 {{ $plan->is_archived ? 'opacity-60' : '' }}">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-semibold text-white flex items-center gap-2">
                {{ $plan->name }}
                @if($plan->is_popular)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-violet-500/15 text-violet-300" title="Shown as Most Popular on the homepage">
                        <i class="fas fa-star mr-1"></i>Most Popular
                    </span>
                @endif
            </h3>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                {{ $plan->status === 'active' && !$plan->is_archived ? 'bg-emerald-500/10 text-emerald-400' : 'bg-white/10 text-white/60' }}">
                {{ $plan->is_archived ? 'Archived' : ucfirst($plan->status) }}
            </span>
        </div>
        <p class="text-sm text-white/40 mb-4">{{ $plan->description ?? 'No description' }}</p>

        <div class="space-y-2 mb-4">
            <div class="flex justify-between text-sm">
                <span class="text-white/40">Monthly</span>
                <span class="font-semibold text-white">${{ number_format($plan->monthly_price, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-white/40">Annual</span>
                <span class="font-semibold text-white">${{ number_format($plan->annual_price, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-white/40">Trial Days</span>
                <span class="text-white">{{ $plan->trial_days }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-white/40">Users</span>
                <span class="text-white">{{ $plan->users_count }}</span>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-4 border-t border-white/5">
            <a href="{{ route('admin.plans.edit', $plan) }}" class="text-white/30 hover:text-violet-400" title="Edit"><i class="fas fa-edit"></i></a>
            <form action="{{ route('admin.plans.archive', $plan) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="text-white/30 hover:text-amber-400" title="{{ $plan->is_archived ? 'Restore' : 'Archive (existing subscribers keep their plan)' }}">
                    <i class="fas {{ $plan->is_archived ? 'fa-box-open' : 'fa-box-archive' }}"></i>
                </button>
            </form>
            @if($plan->users_count === 0)
            <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this plan?', message: 'This cannot be undone.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                @csrf @method('DELETE')
                <button type="submit" class="text-white/30 hover:text-red-400" title="Delete"><i class="fas fa-trash"></i></button>
            </form>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endsection
