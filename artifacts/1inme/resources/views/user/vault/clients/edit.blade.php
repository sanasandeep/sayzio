@extends('user.layouts.app')
@section('title', 'Edit client profile')
@section('content')
@include('user.vault._tabs')
<div class="max-w-3xl">
    <h2 class="text-lg font-semibold mb-4">Edit client profile</h2>
    <form method="post" action="{{ route('user.vault.clients.update', $item) }}">
        @csrf @method('PUT')
        @include('user.vault.clients._form', ['item' => $item])
    </form>
</div>
@endsection
