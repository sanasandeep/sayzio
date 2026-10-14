@extends('user.layouts.app')
@section('title', 'New client profile')
@section('content')
@include('user.vault._tabs')
@include('user.partials.client-create-styles')
<div class="client-create-page">
    <div class="client-create-intro"><strong>New client profile</strong><p>Save contact details and protected notes. Link this profile to a client workspace when you are ready to track work and billing.</p><a href="{{ route('user.delivery-projects.create') }}">Already have a profile? Create a client workspace →</a></div>
    <form method="post" action="{{ route('user.vault.clients.store') }}">
        @csrf
        @include('user.vault.clients._form', ['item' => null])
    </form>
</div>
@endsection
