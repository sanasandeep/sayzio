@extends('admin.layouts.app')
@section('title', $plan->name)

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('admin.plans.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-900">{{ $plan->name }}</h1>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Plan Details</h2>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd><span class="px-2 py-1 rounded text-xs {{ $plan->status === 'active' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ ucfirst($plan->status) }}</span></dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Monthly Price</dt><dd class="font-medium">${{ number_format($plan->monthly_price, 2) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Annual Price</dt><dd class="font-medium">${{ number_format($plan->annual_price, 2) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Trial Days</dt><dd>{{ $plan->trial_days }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Users</dt><dd>{{ $plan->users_count }}</dd></div>
        </dl>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Features</h2>
        @if($plan->features)
        <dl class="space-y-2 text-sm">
            @foreach($plan->features as $key => $value)
            <div class="flex justify-between">
                <dt class="text-gray-500">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                <dd class="font-medium">
                    @if(is_bool($value))
                        <span class="{{ $value ? 'text-green-600' : 'text-red-500' }}">{{ $value ? 'Yes' : 'No' }}</span>
                    @elseif($value === -1)
                        <span class="text-green-600">Unlimited</span>
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
    <a href="{{ route('admin.plans.edit', $plan) }}" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Edit Plan</a>
    <a href="{{ route('admin.plans.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2 text-sm">Back to list</a>
</div>
@endsection
