@extends('admin.layouts.app')
@section('title', 'Add Banned Name')
@section('page-title', 'Add Banned Name')

@section('content')
<div class="max-w-xl">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <form method="POST" action="{{ route('admin.banned-names.store') }}" class="space-y-5">
            @csrf
            @include('admin.banned-names._form', ['item' => null])
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white">
                    Add to list
                </button>
                <a href="{{ route('admin.banned-names.index') }}" class="text-sm text-white/60 hover:text-white ak-muted">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
