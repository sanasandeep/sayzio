@extends('user.layouts.app')
@section('title', 'Edit credential')
@section('content')
@include('user.vault._tabs')
<div class="max-w-3xl">
    <h2 class="text-lg font-semibold mb-4">Edit credential</h2>
    <form method="post" action="{{ route('user.vault.credentials.update', $item) }}">
        @csrf @method('PUT')
        @include('user.vault.credentials._form', ['item' => $item])
    </form>
</div>
@endsection
