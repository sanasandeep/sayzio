@extends('user.layouts.app')
@section('title', 'New credential')
@section('content')
@include('user.partials._plan_lock', ['feature' => 'vaults', 'kind' => 'flag', 'label' => 'Credential vault'])
@include('user.vault._tabs')
<div class="max-w-3xl">
    <h2 class="text-lg font-semibold mb-4">New credential</h2>
    <form method="post" action="{{ route('user.vault.credentials.store') }}">
        @csrf
        @include('user.vault.credentials._form', ['item' => null])
    </form>
</div>
@endsection
