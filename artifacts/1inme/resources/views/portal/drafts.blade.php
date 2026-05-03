@extends('portal.layout')
@section('title', 'Drafts for review')
@section('content')
<h1 class="text-xl font-bold mb-4">Drafts awaiting your review</h1>

@forelse($drafts as $row)
    @php $share = $row['share']; $post = $row['post']; @endphp
    <div class="bg-white border border-slate-200 rounded-xl p-5 mb-4">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div class="min-w-0">
                <h2 class="font-semibold">{{ $post->title ?: 'Untitled draft' }}</h2>
                @if($post->scheduled_at)
                    <div class="text-xs text-slate-500">Scheduled for {{ $post->scheduled_at->format('M j, Y g:i A') }}</div>
                @endif
            </div>
            @if($share->approval_status)
                <span class="text-xs px-2 py-1 rounded-full
                    {{ $share->approval_status === 'approved' ? 'bg-emerald-100 text-emerald-700' :
                       ($share->approval_status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">
                    {{ ucfirst($share->approval_status) }}
                </span>
            @endif
        </div>

        @if($post->image)
            <img src="{{ $post->image }}" alt="" class="rounded mb-3 max-h-72 object-cover w-full">
        @endif
        <div class="prose prose-sm max-w-none text-slate-700 whitespace-pre-wrap mb-4">{{ $post->body }}</div>

        @if($share->approval_comment)
            <div class="bg-slate-50 border border-slate-200 rounded p-3 text-sm mb-3">
                <div class="text-xs text-slate-500 mb-1">Latest comment</div>
                <div>{{ $share->approval_comment }}</div>
            </div>
        @endif

        @if($share->approval_status !== 'approved')
            <form action="{{ route('portal.drafts.decide', $share->id) }}" method="POST" class="space-y-3">
                @csrf
                <textarea name="comment" rows="2" placeholder="Add a comment (optional)…"
                          class="w-full text-sm rounded border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none"></textarea>
                <div class="flex flex-wrap gap-2">
                    <button name="decision" value="approved" class="brand-btn px-4 py-2 rounded text-sm font-semibold">
                        <i class="fas fa-check mr-1"></i>Approve
                    </button>
                    <button name="decision" value="rejected" class="px-4 py-2 rounded text-sm font-semibold border border-rose-300 text-rose-700 hover:bg-rose-50">
                        <i class="fas fa-times mr-1"></i>Request changes
                    </button>
                    <button name="decision" value="comment" class="px-4 py-2 rounded text-sm font-semibold border border-slate-300 text-slate-700 hover:bg-slate-50">
                        <i class="far fa-comment mr-1"></i>Just comment
                    </button>
                </div>
            </form>
        @endif
    </div>
@empty
    <div class="bg-white border border-dashed border-slate-300 rounded-xl p-10 text-center text-slate-500">
        No drafts shared with you right now.
    </div>
@endforelse
@endsection
