@extends('admin.layouts.app')
@section('title', 'Create Addon')
@section('page-title', 'Create Addon')

@section('content')
<div class="max-w-3xl">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <form method="POST" action="{{ route('admin.addons.store') }}">
            @csrf
            @php
                $addon = new \App\Modules\Admin\Models\Addon([
                    'type' => 'recurring',
                    'monthly_price' => 0,
                    'annual_price' => 0,
                    'status' => 'active',
                    'sort_order' => 0,
                ]);
                $checkedPlanIds = [];
                $submitLabel = 'Create Addon';
            @endphp
            @include('admin.addons._form', compact('addon', 'plans', 'checkedPlanIds', 'submitLabel'))
        </form>
    </div>
</div>
@endsection
