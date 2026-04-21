@extends('admin.layouts.app')
@section('title', 'New Coin Package')
@section('page-title', 'New Coin Package')

@section('content')
<form method="POST" action="{{ route('admin.coin-packages.store') }}" class="max-w-3xl">
    @csrf
    @include('admin.coin-packages._form', ['package' => $package, 'submitLabel' => 'Create Package'])
</form>
@endsection
