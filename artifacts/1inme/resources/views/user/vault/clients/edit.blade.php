@extends('user.layouts.app')
@section('title', 'Edit client')
@section('content')
@include('user.planner.styles')
@include('user.partials.client-create-styles')
@include('user.partials.page-hero',['title'=>'Edit client','subtitle'=>$item->name,'emblemIcon'=>'fa-user-pen','favicon'=>null,'url'=>null,'back'=>route('user.vault.clients.show',$item),'chips'=>[],'actions'=>[]])
<div class="client-create-page">
    <form class="client-main-form" method="post" action="{{ route('user.vault.clients.update', $item) }}">
        @csrf @method('PUT')
        @include('user.vault.clients._form', ['item' => $item])
    </form>
</div>
@endsection
