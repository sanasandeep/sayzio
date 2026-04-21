@extends('user.layouts.app')
@section('title', 'My Posts')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6" style="color: var(--text-primary);">My Posts</h1>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>
    @endif

    @canInWorkspace('posts.create')
    @include('user.cloud-files._attach-picker')
    <div x-data="cloudAttachPicker({ mode: 'form' })" class="rounded-2xl border p-5 mb-6 space-y-3" style="background: var(--bg-card); border-color: var(--border-soft);">
        <form action="{{ route('user.posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <input type="text" name="title" placeholder="Title (optional)" class="w-full px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);" value="{{ old('title') }}"/>
            <textarea name="body" placeholder="Share an update with your followers..." rows="3" required class="w-full px-3 py-2 rounded-lg border text-sm" style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);">{{ old('body') }}</textarea>

            {{-- Picked cloud-library files become hidden inputs the controller reads. --}}
            <template x-for="f in picked" :key="f.id">
                <input type="hidden" name="cloud_file_ids[]" :value="f.id">
            </template>
            <div x-show="picked.length > 0" class="flex flex-wrap gap-2">
                <template x-for="f in picked" :key="'chip' + f.id">
                    <span class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full"
                          style="background: rgba(124,58,237,0.12); color: var(--text-primary);">
                        <i :class="f.provider_icon" class="text-[11px]" style="color: var(--text-muted);"></i>
                        <span x-text="f.name" class="max-w-[200px] truncate"></span>
                        <button type="button" @click="remove(f.id)" class="text-[11px]" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
                    </span>
                </template>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <input type="file" name="image" accept="image/*" class="text-xs" style="color: var(--text-muted);"/>
                <label class="text-xs flex items-center gap-2" style="color: var(--text-muted);">
                    <i class="far fa-clock"></i> Schedule for:
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="px-2 py-1 rounded border text-xs" style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);"/>
                </label>
                <label class="text-xs flex items-center gap-1.5" style="color: var(--text-muted);">
                    <input type="checkbox" name="is_pinned" value="1" {{ old('is_pinned') ? 'checked' : '' }}/>
                    <i class="fas fa-thumbtack"></i> Pin this post
                </label>
                <button type="button" @click="show()" class="text-xs px-3 py-1.5 rounded-lg border"
                        style="border-color: var(--border-soft); color: var(--text-primary);">
                    <i class="fas fa-cloud mr-1"></i> Attach from Cloud Files
                </button>
                <button class="ml-auto px-5 py-2 rounded-lg bg-violet-600 text-white text-sm font-semibold">Publish / Schedule</button>
            </div>
            @error('body')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            @error('scheduled_at')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            <p class="text-[11px]" style="color: var(--text-faint);">Leave the schedule field empty to publish immediately. Pinned posts appear at the top of your followers' feeds and on your biolink.</p>
        </form>
        @include('user.cloud-files._attach-modal', ['confirmLabel' => 'Add to post'])
    </div>
    @else
    <div class="rounded-2xl border p-4 mb-6 flex items-center gap-3 text-sm" style="background: rgba(245,158,11,0.06); border-color: rgba(245,158,11,0.25); color: #b45309;">
        <i class="fas fa-lock"></i>
        <span>Your role doesn't allow creating posts in this workspace. Ask a workspace admin if you need access.</span>
    </div>
    @endcanInWorkspace

    @if($posts->count() === 0)
        <div class="text-center py-10 rounded-2xl border" style="background: var(--bg-card); border-color: var(--border-soft);">
            <p style="color: var(--text-muted);">You haven't published any posts yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($posts as $post)
                @php
                    $status = $post->statusLabel();
                    $badgeClasses = [
                        'Pinned'    => 'bg-amber-100 text-amber-800',
                        'Scheduled' => 'bg-sky-100 text-sky-800',
                        'Published' => 'bg-emerald-100 text-emerald-800',
                    ][$status] ?? 'bg-slate-100 text-slate-700';
                @endphp
                <div class="rounded-2xl border p-4 {{ $post->isPinned() ? 'ring-2 ring-amber-300' : '' }}" style="background: var(--bg-card); border-color: var(--border-soft);">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $badgeClasses }}">
                                    @if($status === 'Pinned')<i class="fas fa-thumbtack text-[9px]"></i>@endif
                                    @if($status === 'Scheduled')<i class="far fa-clock text-[9px]"></i>@endif
                                    {{ $status }}
                                </span>
                                @if($post->isScheduled())
                                    <span class="text-xs" style="color: var(--text-faint);">Goes live {{ $post->scheduled_at->format('M j, Y g:i A') }} ({{ $post->scheduled_at->diffForHumans() }})</span>
                                @endif
                            </div>
                            @if($post->title)<h3 class="font-bold" style="color: var(--text-primary);">{{ $post->title }}</h3>@endif
                            <p class="text-sm whitespace-pre-line mt-1" style="color: var(--text-muted);">{{ $post->body }}</p>
                            @if($post->image)<img src="{{ $post->image }}" class="mt-3 rounded-lg max-h-72"/>@endif
                            @if($post->cloudAttachments->isNotEmpty())
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($post->cloudAttachments as $att)
                                        @php($cf = $att->cloudFile)
                                        @if($cf)
                                            <a href="{{ $cf->link }}" target="_blank" rel="noopener noreferrer"
                                               class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-full border hover:underline"
                                               style="background: var(--bg-soft); border-color: var(--border-soft); color: var(--text-primary);"
                                               title="{{ $cf->providerLabel() }} · {{ $cf->humanSize() }}">
                                                <i class="{{ $cf->providerIcon() }}" style="color: var(--text-muted);"></i>
                                                <span class="max-w-[220px] truncate">{{ $cf->name }}</span>
                                                <i class="fas fa-arrow-up-right-from-square text-[10px]" style="color: var(--text-faint);"></i>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                            <p class="text-xs mt-2" style="color: var(--text-faint);">
                                @if($post->isPublished())
                                    Published {{ $post->published_at->diffForHumans() }}
                                @else
                                    Created {{ $post->created_at->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            @canInWorkspace('posts.edit')
                                @if($post->isPublished())
                                    @if($post->isPinned())
                                        <form action="{{ route('user.posts.unpin', $post) }}" method="POST">
                                            @csrf
                                            <button class="text-xs text-amber-700 font-semibold"><i class="fas fa-thumbtack"></i> Unpin</button>
                                        </form>
                                    @else
                                        <form action="{{ route('user.posts.pin', $post) }}" method="POST">
                                            @csrf
                                            <button class="text-xs text-violet-600 font-semibold"><i class="fas fa-thumbtack"></i> Pin</button>
                                        </form>
                                    @endif
                                @endif
                            @endcanInWorkspace
                            @canInWorkspace('posts.delete')
                                <form action="{{ route('user.posts.destroy', $post) }}" method="POST" onsubmit="return confirm('Delete this post?');">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-rose-600 font-semibold">Delete</button>
                                </form>
                            @else
                                <button type="button" disabled class="text-xs font-semibold cursor-not-allowed opacity-50" style="color: var(--text-faint);" title="Your role doesn't allow deleting posts">
                                    <i class="fas fa-lock text-[10px] mr-1"></i>Delete
                                </button>
                            @endcanInWorkspace
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $posts->links() }}</div>
    @endif
</div>
@endsection
