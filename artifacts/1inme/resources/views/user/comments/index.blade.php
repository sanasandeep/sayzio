@extends('user.layouts.app')

@section('title', 'Comments — ' . $link->title)

@section('content')
<div class="max-w-6xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Block comments',
        'subtitle' => $link->title . ' · Block #' . $block->id,
        'icon'     => 'fa-comments',
        'back'     => route('user.links.show', $link),
    ])

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.2); color:#10b981;">{{ session('success') }}</div>
    @endif

    <div class="space-y-3">
        @forelse($comments as $c)
        <div class="rounded-xl p-4 flex items-start gap-4" style="background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);">
            <div class="flex-1">
                <div class="text-sm text-white/60">
                    <strong class="text-white">{{ $c->author_name ?: 'Guest' }}</strong>
                    <span class="ml-2 text-xs">{{ $c->created_at?->diffForHumans() }}</span>
                    <span class="ml-2 text-xs uppercase tracking-wide">{{ $c->status }}</span>
                    @if($c->is_pinned)<span class="ml-2 text-xs text-amber-400"><i class="fas fa-thumbtack"></i> pinned</span>@endif
                    @if($c->is_locked)<span class="ml-2 text-xs text-red-300"><i class="fas fa-lock"></i> locked</span>@endif
                </div>
                <p class="mt-1 text-white/80 whitespace-pre-line">{{ $c->body }}</p>
            </div>
            <div class="flex flex-col gap-2 text-xs">
                <form method="POST" action="{{ route('user.links.comments.update', [$link, $block, $c]) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $c->status === 'visible' ? 'hidden' : 'visible' }}">
                    <button class="text-white/70 hover:text-white">{{ $c->status === 'visible' ? 'Hide' : 'Show' }}</button>
                </form>
                <form method="POST" action="{{ route('user.links.comments.update', [$link, $block, $c]) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="is_locked" value="{{ $c->is_locked ? 0 : 1 }}">
                    <button class="text-white/70 hover:text-white">{{ $c->is_locked ? 'Unlock' : 'Lock thread' }}</button>
                </form>
                <form method="POST" action="{{ route('user.links.comments.ban-author', [$link, $block, $c]) }}" onsubmit="return confirm('Ban this author across this block?');">
                    @csrf
                    <button class="text-red-400 hover:text-red-300">Ban author</button>
                </form>
                <form method="POST" action="{{ route('user.links.comments.destroy', [$link, $block, $c]) }}" onsubmit="return confirm('Delete?');">
                    @csrf @method('DELETE')
                    <button class="text-red-400 hover:text-red-300">Delete</button>
                </form>
            </div>
        </div>
        @empty
        <div class="text-center py-12 text-white/40"><i class="far fa-comment text-4xl mb-3 opacity-40"></i><p>No comments yet.</p></div>
        @endforelse
    </div>

    <div class="mt-6">{{ $comments->links() }}</div>
</div>
@endsection
