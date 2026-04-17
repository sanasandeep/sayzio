@extends('admin.layouts.app')
@section('title', 'New Template')
@section('page-title', 'Create ' . ucfirst($kind) . ' Template')

@section('content')
<div class="max-w-4xl">
    <a href="{{ route('admin.templates.index', ['tab' => $kind]) }}" class="text-xs text-white/40 hover:text-white mb-4 inline-block"><i class="fas fa-arrow-left mr-1"></i>Back to templates</a>
    @include('admin.templates._form', ['categories' => $categories, 'plans' => $plans, 'kind' => $kind])
</div>
@endsection
