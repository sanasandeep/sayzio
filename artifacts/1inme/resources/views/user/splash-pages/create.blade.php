@extends('user.layouts.app')
@section('title', 'New Splash Page')

@section('content')
<div class="max-w-4xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'New Splash Page',
        'subtitle' => 'A reusable transition page that visitors see before reaching their destination.',
        'icon'     => 'fa-rocket',
        'back'     => route('user.splash-pages.index'),
    ])
    @include('user.splash-pages._form')
</div>
@endsection
