@extends('admin.layouts.app')
@section('title', 'Edit Event Hashtag')
@section('page-title', 'Edit Event Hashtag')

@section('content')
<div class="max-w-2xl">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <form method="POST" action="{{ route('admin.event-hashtags.update', $hashtag) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('admin.event-hashtags._form', ['hashtag' => $hashtag])
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white">
                    Save changes
                </button>
                <a href="{{ route('admin.event-hashtags.index') }}" class="text-sm text-white/60 hover:text-white">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
