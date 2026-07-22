@extends('admin.layouts.app')
@section('title', 'Add testimonial')
@section('page-title', 'Add testimonial')

@section('content')
<div class="max-w-3xl">
    <div class="glass rounded-2xl border border-white/10 p-6">
        @if($errors->any())
            <div class="mb-4 rounded-xl px-4 py-3 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm ak-red">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('admin.testimonials.store') }}">
            @csrf
            @include('admin.testimonials._form')
        </form>
    </div>
</div>
@endsection
