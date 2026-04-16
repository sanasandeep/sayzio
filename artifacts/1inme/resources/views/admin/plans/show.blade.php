@extends('admin.layouts.app')
@section('title', $plan->name)

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('admin.plans.index') }}" class="text-white/30 hover:text-white/50"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-white">{{ $plan->name }}</h1>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Plan Details</h2>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-white/40">Status</dt><dd><span class="px-2 py-1 rounded text-xs {{ $plan->status === 'active' ? 'bg-emerald-500/10 text-green-700' : 'bg-red-500/10 text-red-700' }}">{{ ucfirst($plan->status) }}</span></dd></div>
            <div class="flex justify-between"><dt class="text-white/40">Monthly Price</dt><dd class="font-medium">${{ number_format($plan->monthly_price, 2) }}</dd></div>
            <div class="flex justify-between"><dt class="text-white/40">Annual Price</dt><dd class="font-medium">${{ number_format($plan->annual_price, 2) }}</dd></div>
            <div class="flex justify-between"><dt class="text-white/40">Trial Days</dt><dd>{{ $plan->trial_days }}</dd></div>
            <div class="flex justify-between"><dt class="text-white/40">Users</dt><dd>{{ $plan->users_count }}</dd></div>
        </dl>
    </div>

    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Features</h2>
        @if($plan->features)
        <dl class="space-y-2 text-sm">
            @foreach($plan->features as $key => $value)
            <div class="flex justify-between">
                <dt class="text-white/40">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                <dd class="font-medium">
                    @if(is_bool($value))
                        <span class="{{ $value ? 'text-emerald-400' : 'text-red-500' }}">{{ $value ? 'Yes' : 'No' }}</span>
                    @elseif($value === -1)
                        <span class="text-emerald-400">Unlimited</span>
                    @else
                        {{ $value }}
                    @endif
                </dd>
            </div>
            @endforeach
        </dl>
        @endif
    </div>
</div>

<div class="flex gap-3">
    <a href="{{ route('admin.plans.edit', $plan) }}" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium">Edit Plan</a>
    <a href="{{ route('admin.plans.index') }}" class="text-white/50 hover:text-white px-4 py-2 text-sm">Back to list</a>
</div>
@endsection
