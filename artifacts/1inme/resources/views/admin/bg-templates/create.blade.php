@extends('admin.layouts.app')
@section('title', 'New Background Template')
@section('page-title', 'New Background Template')

@section('content')
<div class="max-w-4xl">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <form method="POST" action="{{ route('admin.bg-templates.store') }}" class="space-y-5">
            @csrf
            @include('admin.bg-templates._form', ['template' => $template, 'categories' => $categories])
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white">
                    Create template
                </button>
                <a href="{{ route('admin.bg-templates.index') }}" class="text-sm text-white/60 hover:text-white ak-muted">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
