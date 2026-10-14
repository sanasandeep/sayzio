@extends('user.layouts.app')
@section('title', 'New client')
@section('content')
@include('user.planner.styles')
@include('user.partials.page-hero',['title'=>'New client','subtitle'=>'Save one client record, then add projects and connected work.','emblemIcon'=>'fa-user-plus','favicon'=>null,'url'=>null,'back'=>route('user.vault.clients.index'),'chips'=>[],'actions'=>[]])
@include('user.partials.client-create-styles')
<div class="client-create-page">

    <form class="client-main-form" method="post" action="{{ route('user.vault.clients.store') }}">
        @csrf
        @include('user.vault.clients._form', ['item' => null])
    </form>
</div>
@endsection
