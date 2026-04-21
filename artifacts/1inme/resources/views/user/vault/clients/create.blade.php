@extends('user.layouts.app')
@section('title', 'New client')
@section('content')
@include('user.vault._tabs')
<div class="max-w-3xl">
    <h2 class="text-lg font-semibold mb-4">New client</h2>
    <form method="post" action="{{ route('user.vault.clients.store') }}">
        @csrf
        @include('user.vault.clients._form', ['item' => null])
    </form>
</div>
@endsection
