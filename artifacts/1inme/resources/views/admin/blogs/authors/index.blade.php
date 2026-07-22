@extends('admin.layouts.app')
@section('title', 'Blog authors')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white ak-strong">Authors</h1>
            <p class="text-sm text-white/50 mt-1 ak-muted">Staff members who have written blog posts.</p>
        </div>
        <a href="{{ route('admin.blogs.posts.index') }}" class="text-xs text-blue-400 hover:underline ak-blue">← All posts</a>
    </div>

    <div class="glass rounded-2xl divide-y divide-white/5">
        @forelse($authors as $a)
            @php $c = $counts->get($a->id); @endphp
            <div class="p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-white ak-strong" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                    {{ strtoupper(substr($a->name ?: $a->email, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-white font-medium ak-strong">{{ $a->name ?: $a->email }}</div>
                    <div class="text-xs text-white/50 ak-muted">{{ $a->email }} · {{ optional($a->role)->name }}</div>
                </div>
                <div class="hidden sm:flex gap-4 text-xs text-white/70 ak-strong">
                    <div><span class="text-white font-semibold ak-strong">{{ (int)($c->published ?? 0) }}</span> published</div>
                    <div><span class="text-white font-semibold ak-strong">{{ (int)($c->scheduled ?? 0) }}</span> scheduled</div>
                    <div><span class="text-white font-semibold ak-strong">{{ (int)($c->drafts ?? 0) }}</span> drafts</div>
                </div>
                <a href="{{ route('admin.blogs.posts.index', ['author' => $a->id]) }}" class="px-3 py-1.5 text-xs bg-blue-600 hover:bg-blue-700 rounded text-white">View posts</a>
            </div>
        @empty
            <div class="p-12 text-center text-white/50 ak-muted">No authors yet, create your first post to populate this list.</div>
        @endforelse
    </div>
</div>
@endsection
