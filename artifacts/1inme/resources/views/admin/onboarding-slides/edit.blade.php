@extends('admin.layouts.app')
@section('title', 'Edit Onboarding Slide')
@section('page-title', 'Edit Onboarding Slide')

@section('content')
<div class="glass rounded-2xl border border-white/10 p-6 max-w-5xl">
    <form action="{{ route('admin.onboarding-slides.update', $slide) }}" method="POST" enctype="multipart/form-data">
        @method('PUT')
        @include('admin.onboarding-slides._form', ['submitLabel' => 'Save changes'])
    </form>
</div>
@endsection
