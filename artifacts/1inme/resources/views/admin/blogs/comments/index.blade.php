@extends('admin.layouts.app')
@section('title', 'Blog comments')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Comments</h1>
            <p class="text-sm text-white/50 mt-1">Moderate, approve and reply to reader comments.</p>
        </div>
        <a href="{{ route('admin.blogs.posts.index') }}" class="text-xs text-violet-400 hover:underline">← Posts</a>
    </div>

    @if(session('success'))<div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>@endif

    <div class="flex flex-wrap gap-2 text-xs">
        @foreach(['pending'=>'Pending','approved'=>'Approved','spam'=>'Spam','trash'=>'Trash'] as $k=>$label)
            <a href="{{ route('admin.blogs.comments.index', ['status'=>$k]) }}" class="px-3 py-1.5 rounded-full {{ $status===$k ? 'bg-violet-600 text-white' : 'bg-white/5 text-white/70 hover:bg-white/10' }}">{{ $label }} <span class="opacity-60">({{ $counts[$k] ?? 0 }})</span></a>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.blogs.comments.bulk') }}" class="glass rounded-2xl">
        @csrf
        <div class="p-3 flex items-center gap-2 border-b border-white/10">
            <select name="action" class="px-3 py-1.5 bg-white/5 border border-white/10 rounded text-xs text-white">
                <option value="approve">Approve</option>
                <option value="spam">Mark spam</option>
                <option value="trash">Trash</option>
                <option value="delete">Delete</option>
            </select>
            <button class="px-3 py-1.5 bg-white/10 hover:bg-white/15 rounded text-xs text-white">Apply</button>
            <span class="ml-auto text-xs text-white/50">Showing {{ $comments->total() }} {{ $status }}</span>
        </div>

        <div class="divide-y divide-white/5">
            @forelse($comments as $c)
                <div class="p-4 flex gap-3" x-data="{ replyOpen:false, editOpen:false }">
                    <input type="checkbox" name="ids[]" value="{{ $c->id }}" class="mt-2">
                    <div class="w-9 h-9 shrink-0 rounded-full flex items-center justify-center font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#38bdf8);">{{ strtoupper(substr($c->author_name ?: '?',0,1)) }}</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between text-xs text-white/60">
                            <div>
                                <span class="font-semibold text-white/90">{{ $c->author_name }}</span>
                                <span class="ml-1">&lt;{{ $c->author_email }}&gt;</span>
                                @if($c->parent_id)<span class="ml-2 px-1.5 py-0.5 rounded bg-white/10 text-[10px] uppercase tracking-wider">Reply</span>@endif
                            </div>
                            <span>{{ $c->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="text-[11px] text-white/40 mt-0.5">on <a href="{{ route('site.blogs.show', optional($c->post)->slug) }}#comment-{{ $c->id }}" target="_blank" class="text-violet-400 hover:underline">{{ optional($c->post)->title ?: '(deleted post)' }}</a></div>
                        <p class="mt-2 text-sm text-white/85 whitespace-pre-line">{{ $c->body }}</p>

                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            @if($c->status !== 'approved')
                                <button form="cm-{{ $c->id }}-approve" class="px-2.5 py-1 rounded bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300">Approve</button>
                            @endif
                            @if($c->status !== 'spam')
                                <button form="cm-{{ $c->id }}-spam" class="px-2.5 py-1 rounded bg-amber-500/20 hover:bg-amber-500/30 text-amber-300">Spam</button>
                            @endif
                            @if($c->status !== 'trash')
                                <button form="cm-{{ $c->id }}-trash" class="px-2.5 py-1 rounded bg-white/10 hover:bg-white/15 text-white/80">Trash</button>
                            @endif
                            <button form="cm-{{ $c->id }}-delete" class="px-2.5 py-1 rounded bg-red-500/20 hover:bg-red-500/30 text-red-300" onclick="return window.themedConfirmAction(this, {title: 'Delete this comment forever?', message: 'This cannot be undone.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">Delete</button>
                            <button type="button" @click="editOpen=!editOpen" class="px-2.5 py-1 rounded bg-sky-500/20 hover:bg-sky-500/30 text-sky-300">Edit</button>
                            @if(auth('admin')->user()->hasPermission('blogs.comments.reply') && !$c->parent_id)
                                <button type="button" @click="replyOpen=!replyOpen" class="px-2.5 py-1 rounded bg-violet-500/20 hover:bg-violet-500/30 text-violet-300">Reply</button>
                            @endif
                        </div>

                        <div x-show="editOpen" x-cloak class="mt-3">
                            <form method="POST" action="{{ route('admin.blogs.comments.edit', $c) }}" class="space-y-2">
                                @csrf
                                <textarea name="body" rows="3" required class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">{{ $c->body }}</textarea>
                                <div class="flex gap-2">
                                    <button class="px-4 py-1.5 bg-sky-600 hover:bg-sky-700 rounded text-xs text-white">Save changes</button>
                                    <button type="button" @click="editOpen=false" class="px-3 py-1.5 bg-white/10 hover:bg-white/15 rounded text-xs text-white/80">Cancel</button>
                                </div>
                            </form>
                        </div>

                        <div x-show="replyOpen" x-cloak class="mt-3">
                            <form method="POST" action="{{ route('admin.blogs.comments.reply', $c) }}" class="space-y-2">
                                @csrf
                                <textarea name="body" rows="3" required placeholder="Write a public reply…" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm"></textarea>
                                <button class="px-4 py-1.5 bg-violet-600 hover:bg-violet-700 rounded text-xs text-white">Post staff reply</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center text-white/50">No {{ $status }} comments.</div>
            @endforelse
        </div>
    </form>

    @foreach($comments as $c)
        @foreach(['approve','spam','trash','delete'] as $a)
            <form id="cm-{{ $c->id }}-{{ $a }}" method="POST" action="{{ $a==='delete' ? route('admin.blogs.comments.destroy', $c) : route('admin.blogs.comments.update', $c) }}" class="hidden">
                @csrf
                @if($a==='delete') @method('DELETE') @else <input type="hidden" name="action" value="{{ $a }}"> @endif
            </form>
        @endforeach
    @endforeach

    <div>{{ $comments->links() }}</div>
</div>
@endsection
