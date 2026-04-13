@extends('admin.layouts.app')
@section('title', 'Plans')
@section('page-title', 'Subscription Plans')

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-dark-500">Manage subscription plans and pricing</p>
    <a href="{{ route('admin.plans.create') }}" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition">
        <i class="fas fa-plus mr-2"></i>Add Plan
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($plans as $plan)
    <div class="bg-white rounded-xl border border-dark-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-semibold text-dark-800">{{ $plan->name }}</h3>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                {{ $plan->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-dark-100 text-dark-600' }}">
                {{ ucfirst($plan->status) }}
            </span>
        </div>
        <p class="text-sm text-dark-500 mb-4">{{ $plan->description ?? 'No description' }}</p>

        <div class="space-y-2 mb-4">
            <div class="flex justify-between text-sm">
                <span class="text-dark-500">Monthly</span>
                <span class="font-semibold text-dark-800">${{ number_format($plan->monthly_price, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-dark-500">Annual</span>
                <span class="font-semibold text-dark-800">${{ number_format($plan->annual_price, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-dark-500">Trial Days</span>
                <span class="text-dark-800">{{ $plan->trial_days }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-dark-500">Users</span>
                <span class="text-dark-800">{{ $plan->users_count }}</span>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-4 border-t border-dark-100">
            <a href="{{ route('admin.plans.edit', $plan) }}" class="text-dark-400 hover:text-primary-600"><i class="fas fa-edit"></i></a>
            @if($plan->users_count === 0)
            <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-dark-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
            </form>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endsection
