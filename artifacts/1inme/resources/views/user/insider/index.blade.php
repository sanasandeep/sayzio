@extends('user.layouts.app')

@section('title', 'Insider Feed — ' . $link->title)

@section('content')
<div class="max-w-6xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Insider Feed',
        'subtitle' => $link->title,
        'icon'     => 'fa-lock',
        'back'     => route('user.links.show', $link),
        'chips'    => [
            ['icon' => 'fa-user-check text-emerald-400', 'text' => ($memberCounts['free'] ?? 0) . ' free'],
            ['icon' => 'fa-crown text-amber-400',        'text' => ($memberCounts['paid'] ?? 0) . ' paid'],
        ],
        'actions'  => [
            ['label' => 'Members',  'url' => route('user.links.insider.members', [$link, $block]), 'icon' => 'fa-users', 'class' => 'btn-secondary'],
            ['label' => 'Comments', 'url' => route('user.links.comments.index',  [$link, $block]), 'icon' => 'fa-comments', 'class' => 'btn-secondary'],
        ],
    ])

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.2); color:#10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Composer --}}
    <div class="rounded-2xl p-6 mb-8" style="background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);">
        <h2 class="text-lg font-semibold mb-4 text-white">New Insider post</h2>
        <form method="POST" action="{{ route('user.links.insider.posts.store', [$link, $block]) }}" class="space-y-4">
            @csrf
            <input type="text" name="title" placeholder="Title (optional)" maxlength="255" class="w-full px-4 py-2 rounded-lg bg-black/20 border border-white/10 text-white placeholder-white/40">
            <textarea name="body" required rows="4" placeholder="What's new for your insiders?" class="w-full px-4 py-2 rounded-lg bg-black/20 border border-white/10 text-white placeholder-white/40"></textarea>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <select name="media_type" class="px-4 py-2 rounded-lg bg-black/20 border border-white/10 text-white">
                    <option value="">No media</option>
                    <option value="image">Image</option>
                    <option value="video">Video</option>
                </select>
                <input type="url" name="media_url" placeholder="Media URL" class="px-4 py-2 rounded-lg bg-black/20 border border-white/10 text-white placeholder-white/40">
                <select name="access" class="px-4 py-2 rounded-lg bg-black/20 border border-white/10 text-white">
                    <option value="members">Members only (free signup)</option>
                    <option value="paid">Paid subscribers only</option>
                    <option value="public">Public</option>
                    <option value="followers">Followers only</option>
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-center">
                <select name="status" class="px-4 py-2 rounded-lg bg-black/20 border border-white/10 text-white">
                    <option value="published">Publish now</option>
                    <option value="scheduled">Schedule</option>
                    <option value="draft">Save as draft</option>
                </select>
                <input type="datetime-local" name="scheduled_for" class="px-4 py-2 rounded-lg bg-black/20 border border-white/10 text-white">
                <button class="btn-primary px-4 py-2 rounded-lg"><i class="fas fa-paper-plane mr-1"></i> Save post</button>
            </div>
        </form>
    </div>

    {{-- Existing posts --}}
    <div class="space-y-4">
        @forelse($posts as $post)
        <div class="rounded-2xl p-5" style="background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);">
            <div class="flex items-start justify-between gap-4 mb-2">
                <div>
                    <div class="text-xs uppercase tracking-wide text-white/40">
                        {{ ucfirst($post->status) }} · {{ ucfirst($post->access) }}
                        @if($post->published_at) · {{ $post->published_at->diffForHumans() }} @endif
                        @if($post->scheduled_for && $post->status === 'scheduled') · scheduled {{ $post->scheduled_for->format('M j, H:i') }} @endif
                    </div>
                    @if($post->title)<h3 class="text-lg font-semibold text-white">{{ $post->title }}</h3>@endif
                </div>
                <form method="POST" action="{{ route('user.links.insider.posts.destroy', [$link, $block, $post]) }}" onsubmit="return confirm('Delete this post?');">
                    @csrf @method('DELETE')
                    <button class="text-red-400 hover:text-red-300 text-sm"><i class="fas fa-trash"></i></button>
                </form>
            </div>
            <p class="text-white/80 whitespace-pre-line">{{ $post->body }}</p>
            <div class="mt-3 text-xs text-white/40">
                <span><i class="far fa-heart mr-1"></i>{{ $post->reactions_count }}</span>
                <span class="ml-3"><i class="far fa-comment mr-1"></i>{{ $post->comments_count }}</span>
            </div>
        </div>
        @empty
        <div class="text-center py-12 text-white/50">
            <i class="fas fa-lock text-4xl mb-3 opacity-40"></i>
            <p>No Insider posts yet. Compose your first one above.</p>
        </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $posts->links() }}</div>
</div>
@endsection
