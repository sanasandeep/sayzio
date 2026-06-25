@extends('user.layouts.app')
@section('title', 'Following')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center gap-2 mb-6">
        <h1 class="text-2xl font-bold flex-1" style="color: var(--text-primary);">Following</h1>
        <a href="{{ route('user.followers.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold" style="background: var(--bg-soft); color: var(--text-muted);">Followers</a>
        <a href="{{ route('user.following.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-600 text-white">Following</a>
    </div>

    @if($following->count() === 0)
        <div class="text-center py-16 rounded-2xl border" style="background: var(--bg-card); border-color: var(--border-soft);">
            <i class="fas fa-compass text-4xl mb-3" style="color: var(--text-faint);"></i>
            <p class="mb-3" style="color: var(--text-muted);">You're not following anyone yet.</p>
            <a href="{{ route('creators.index') }}" class="inline-block px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">Browse creators</a>
        </div>
    @else
        <div class="rounded-2xl border divide-y" style="background: var(--bg-card); border-color: var(--border-soft);">
            @foreach($following as $f)
                @php $u = $f->creator; @endphp
                @if($u)
                <div class="flex items-center gap-3 p-4">
                    @if($u->avatar)
                        <img src="{{ $u->avatar }}" class="w-10 h-10 rounded-full object-cover" alt=""/>
                    @else
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center font-bold">{{ $u->getInitials() }}</div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold truncate" style="color: var(--text-primary);">{{ $u->name }}</p>
                        @if($u->handle)<p class="text-xs truncate" style="color: var(--text-faint);">&#64;{{ $u->handle }}</p>@endif
                    </div>
                    <form onsubmit="event.preventDefault(); var f=this; fetch(f.action, {method:'POST', headers:{'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept':'application/json'}}).then(r=>r.json()).then(()=>f.closest('div.flex').remove());" action="{{ route('viewer.follow.toggle', $u->id) }}">
                        @csrf
                        <button class="px-3 py-1.5 rounded-lg text-xs font-semibold btn-ghost hover:bg-rose-100 hover:text-rose-700">Unfollow</button>
                    </form>
                </div>
                @endif
            @endforeach
        </div>
        <div class="mt-6">{{ $following->links() }}</div>
    @endif
</div>
@endsection
