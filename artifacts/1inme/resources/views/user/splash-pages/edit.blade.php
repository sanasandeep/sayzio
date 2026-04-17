@extends('user.layouts.app')
@section('title', 'Edit · ' . $splashPage->name)

@section('content')
<div class="max-w-4xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => $splashPage->name,
        'subtitle' => 'Splash page settings',
        'icon'     => 'fa-rocket',
        'back'     => route('user.splash-pages.index'),
        'chips'    => [
            ['icon' => 'fa-link', 'text' => $splashPage->links()->count() . ' link(s)'],
        ],
        'actions'  => [
            ['url' => route('user.splash-pages.preview', $splashPage), 'label' => 'Preview', 'icon' => 'fa-eye', 'class' => 'btn-ghost', 'target' => '_blank'],
        ],
    ])
    @include('user.splash-pages._form')
</div>
@endsection
