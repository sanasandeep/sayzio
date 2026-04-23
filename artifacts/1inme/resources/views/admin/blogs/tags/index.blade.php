@extends('admin.layouts.app')
@section('title', 'Blog tags')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-white">Tags</h1>
        <a href="{{ route('admin.blogs.posts.index') }}" class="text-xs text-violet-400 hover:underline">← Posts</a>
    </div>

    @if(session('success'))<div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>@endif

    @if(auth('admin')->user()->hasPermission('blogs.manage'))
    <form method="POST" action="{{ route('admin.blogs.tags.store') }}" class="glass rounded-2xl p-5 grid sm:grid-cols-3 gap-3">
        @csrf
        <input type="text" name="name" required placeholder="Tag name" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
        <input type="text" name="slug" placeholder="slug (auto)" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm font-mono">
        <button class="px-4 py-2 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm text-white">Add tag</button>
    </form>
    @endif

    <div class="space-y-2">
        @forelse($tags as $t)
            <div class="glass rounded-lg p-3 flex items-center gap-3">
                <form method="POST" action="{{ route('admin.blogs.tags.update', $t) }}" class="flex-1 flex gap-2">
                    @csrf @method('PUT')
                    <input type="text" name="name" value="{{ $t->name }}" class="flex-1 px-3 py-1.5 bg-white/5 border border-white/10 rounded text-white text-sm">
                    <input type="text" name="slug" value="{{ $t->slug }}" class="flex-1 px-3 py-1.5 bg-white/5 border border-white/10 rounded text-white text-sm font-mono">
                    <span class="text-xs text-white/50 self-center">{{ $t->posts_count }}</span>
                    <button class="px-3 py-1.5 bg-white/10 hover:bg-white/20 rounded text-xs text-white">Save</button>
                </form>
                <form method="POST" action="{{ route('admin.blogs.tags.destroy', $t) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this tag?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                    @csrf @method('DELETE')
                    <button class="px-3 py-1.5 bg-red-500/15 hover:bg-red-500/25 rounded text-xs text-red-300">Delete</button>
                </form>
            </div>
        @empty
            <div class="glass rounded-xl p-8 text-center text-white/50">No tags yet.</div>
        @endforelse
    </div>

    <div>{{ $tags->links() }}</div>
</div>
@endsection
