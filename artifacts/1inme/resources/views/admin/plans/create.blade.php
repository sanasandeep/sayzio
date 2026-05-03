@extends('admin.layouts.app')
@section('title', 'Create Plan')
@section('page-title', 'Create Plan')

@section('content')
<div class="max-w-5xl">
    <form method="POST" action="{{ route('admin.plans.store') }}">
        @csrf
        @include('admin.plans._form')
    </form>
</div>
@endsection
