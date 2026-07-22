@extends('admin.layouts.app')
@section('title', 'Add stat')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-white mb-6 ak-strong">Add stat</h1>
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
        <form method="POST" action="{{ route('admin.site-stats.store') }}">
            @include('admin.site-stats._form', ['stat' => null])
        </form>
    </div>
</div>
@endsection
