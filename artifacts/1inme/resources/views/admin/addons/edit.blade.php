@extends('admin.layouts.app')
@section('title', 'Edit Addon')
@section('page-title', 'Edit Addon: ' . $addon->name)

@section('content')
<div class="max-w-3xl">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <form method="POST" action="{{ route('admin.addons.update', $addon) }}">
            @csrf @method('PUT')
            @php
                $checkedPlanIds = $attachedPlanIds;
                $submitLabel = 'Update Addon';
            @endphp
            @include('admin.addons._form', compact('addon', 'plans', 'checkedPlanIds', 'submitLabel'))
        </form>
    </div>
</div>
@endsection
