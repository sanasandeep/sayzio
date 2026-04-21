@extends('admin.layouts.app')
@section('title', 'Edit Coin Package')
@section('page-title', 'Edit Coin Package')

@section('content')
<form method="POST" action="{{ route('admin.coin-packages.update', $package) }}" class="max-w-3xl">
    @csrf @method('PUT')
    @include('admin.coin-packages._form', ['package' => $package, 'submitLabel' => 'Update Package'])
</form>
@endsection
