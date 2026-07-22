@extends('admin.layouts.app')
@section('title', 'Blog categories')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white ak-strong">Categories</h1>
            <p class="text-sm text-white/50 mt-1 ak-muted">Group articles for filtering and category landing pages.</p>
        </div>
        <a href="{{ route('admin.blogs.posts.index') }}" class="text-xs text-blue-400 hover:underline ak-blue">← Posts</a>
    </div>

    @if(session('success'))<div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm ak-green">{{ session('success') }}</div>@endif

    @if(auth('admin')->user()->hasPermission('blogs.manage'))
    <form method="POST" action="{{ route('admin.blogs.categories.store') }}" class="glass rounded-2xl p-5 grid sm:grid-cols-5 gap-3">
        @csrf
        <input type="text" name="name" required placeholder="Name" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm ak-strong ak-input">
        <input type="text" name="slug" placeholder="slug (auto)" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm font-mono ak-strong ak-input">
        <input type="text" name="color" placeholder="#3d6bff" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm ak-strong ak-input">
        <input type="number" name="sort_order" value="0" placeholder="Sort" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm ak-strong ak-input">
        <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm text-white">Add</button>
        <input type="text" name="description" placeholder="Description (shown on category page)" class="sm:col-span-5 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm ak-strong ak-input">
    </form>
    @endif

    <div class="space-y-3">
        @forelse($categories as $c)
            <form method="POST" action="{{ route('admin.blogs.categories.update', $c) }}" class="glass rounded-xl p-4 grid sm:grid-cols-12 gap-3 items-center">
                @csrf @method('PUT')
                <input type="text" name="name" value="{{ $c->name }}" class="sm:col-span-3 px-3 py-2 bg-white/5 border border-white/10 rounded text-white text-sm ak-strong ak-input">
                <input type="text" name="slug" value="{{ $c->slug }}" class="sm:col-span-3 px-3 py-2 bg-white/5 border border-white/10 rounded text-white text-sm font-mono ak-strong ak-input">
                <input type="text" name="color" value="{{ $c->color }}" placeholder="#3d6bff" class="sm:col-span-2 px-3 py-2 bg-white/5 border border-white/10 rounded text-white text-sm ak-strong ak-input">
                <input type="number" name="sort_order" value="{{ $c->sort_order }}" class="sm:col-span-1 px-3 py-2 bg-white/5 border border-white/10 rounded text-white text-sm ak-strong ak-input">
                <span class="sm:col-span-1 text-center text-xs text-white/50 ak-muted">{{ $c->posts_count }} posts</span>
                <div class="sm:col-span-2 flex justify-end gap-2">
                    <button class="px-3 py-2 bg-white/10 hover:bg-white/20 rounded text-xs text-white ak-strong">Save</button>
                </div>
                <input type="text" name="description" value="{{ $c->description }}" placeholder="Description" class="sm:col-span-12 px-3 py-2 bg-white/5 border border-white/10 rounded text-white text-sm ak-strong ak-input">
            </form>
            <form method="POST" action="{{ route('admin.blogs.categories.destroy', $c) }}" class="text-right -mt-2" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this category?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                @csrf @method('DELETE')
                <button class="text-[11px] text-red-300 hover:text-red-200 ak-red">Delete</button>
            </form>
        @empty
            <div class="glass rounded-xl p-8 text-center text-white/50 ak-muted">No categories yet.</div>
        @endforelse
    </div>
</div>
@endsection
