@extends('user.layouts.app')
@section('title', 'My Posts')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6" style="color: var(--text-primary);">My Posts</h1>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <form action="{{ route('user.posts.store') }}" method="POST" enctype="multipart/form-data" class="rounded-2xl border p-5 mb-6 space-y-3" style="background: var(--bg-card); border-color: var(--border-soft);">
        @csrf
        <input type="text" name="title" placeholder="Title (optional)" class="w-full px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);" value="{{ old('title') }}"/>
        <textarea name="body" placeholder="Share an update with your followers..." rows="3" required class="w-full px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);">{{ old('body') }}</textarea>
        <div class="flex items-center justify-between">
            <input type="file" name="image" accept="image/*" class="text-xs" style="color: var(--text-muted);"/>
            <button class="px-5 py-2 rounded-lg bg-violet-600 text-white text-sm font-semibold">Publish</button>
        </div>
        @error('body')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
    </form>

    @if($posts->count() === 0)
        <div class="text-center py-10 rounded-2xl border" style="background: var(--bg-card); border-color: var(--border-soft);">
            <p style="color: var(--text-muted);">You haven't published any posts yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($posts as $post)
                <div class="rounded-2xl border p-4" style="background: var(--bg-card); border-color: var(--border-soft);">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            @if($post->title)<h3 class="font-bold" style="color: var(--text-primary);">{{ $post->title }}</h3>@endif
                            <p class="text-sm whitespace-pre-line mt-1" style="color: var(--text-muted);">{{ $post->body }}</p>
                            @if($post->image)<img src="{{ $post->image }}" class="mt-3 rounded-lg max-h-72"/>@endif
                            <p class="text-xs mt-2" style="color: var(--text-faint);">{{ $post->created_at->diffForHumans() }}</p>
                        </div>
                        <form action="{{ route('user.posts.destroy', $post) }}" method="POST" onsubmit="return confirm('Delete this post?');">
                            @csrf @method('DELETE')
                            <button class="text-xs text-rose-600 font-semibold">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $posts->links() }}</div>
    @endif
</div>
@endsection
