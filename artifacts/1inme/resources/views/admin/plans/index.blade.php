@extends('admin.layouts.app')
@section('title', 'Plans')
@section('page-title', 'Subscription Plans')

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-white/40">Manage subscription plans and pricing</p>
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.plans.export') }}" class="px-4 py-2 bg-white/10 text-white/80 rounded-xl text-sm font-medium hover:bg-white/20 transition">
            <i class="fas fa-download mr-2"></i>Export CSV
        </a>
        <a href="{{ route('admin.plans.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>Add Plan
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($plans as $plan)
        @include('admin.plans.partials._card', ['plan' => $plan])
    @endforeach
</div>

@if(isset($archivedPlans) && $archivedPlans->count() > 0)
<div x-data="{ open: false }" class="mt-10">
    <button type="button" @click="open = !open" class="flex items-center gap-2 text-sm text-white/50 hover:text-white/80 transition">
        <i class="fas" :class="open ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
        Archived plans ({{ $archivedPlans->count() }})
        <span class="text-white/30">— legacy plans kept for existing subscribers</span>
    </button>
    <div x-show="open" x-cloak class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
        @foreach($archivedPlans as $plan)
            @include('admin.plans.partials._card', ['plan' => $plan])
        @endforeach
    </div>
</div>
@endif
@endsection
