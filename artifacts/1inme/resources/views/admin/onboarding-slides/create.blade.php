@extends('admin.layouts.app')
@section('title', 'Add Onboarding Slide')
@section('page-title', 'Add Onboarding Slide')

@section('content')
<div class="glass rounded-2xl border border-white/10 p-6 max-w-5xl">
    <form action="{{ route('admin.onboarding-slides.store') }}" method="POST" enctype="multipart/form-data">
        @include('admin.onboarding-slides._form', ['submitLabel' => 'Create slide'])
    </form>
</div>
@endsection
