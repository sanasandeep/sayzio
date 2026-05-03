@extends('admin.layouts.app')
@section('title', 'Edit Plan')
@section('page-title', 'Edit Plan: ' . $plan->name)

@section('content')
<div class="max-w-5xl">
    <form method="POST" action="{{ route('admin.plans.update', $plan) }}">
        @csrf @method('PUT')
        @include('admin.plans._form')
    </form>
</div>
@endsection
