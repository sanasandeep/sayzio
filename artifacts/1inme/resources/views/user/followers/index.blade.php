@extends('user.layouts.app')
@section('title', 'Followers')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center gap-2 mb-6">
        <h1 class="text-2xl font-bold flex-1" style="color: var(--text-primary);">Followers</h1>
        <a href="{{ route('user.followers.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ request()->routeIs('user.followers.*') ? 'bg-blue-600 text-white' : '' }}" style="{{ request()->routeIs('user.followers.*') ? '' : 'background: var(--bg-soft); color: var(--text-muted);' }}">Followers</a>
        <a href="{{ route('user.following.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold" style="background: var(--bg-soft); color: var(--text-muted);">Following</a>
    </div>

    @if($followers->count() === 0)
        <div class="text-center py-16 rounded-2xl border" style="background: var(--bg-card); border-color: var(--border-soft);">
            <i class="fas fa-user-group text-4xl mb-3" style="color: var(--text-faint);"></i>
            <p style="color: var(--text-muted);">You don't have any followers yet. Share your Link in Bio and creators directory profile to get started.</p>
        </div>
    @else
        <div class="rounded-2xl border divide-y" style="background: var(--bg-card); border-color: var(--border-soft);">
            @foreach($followers as $f)
                @php $u = $f->follower; @endphp
                @if($u)
                <div class="flex items-center gap-3 p-4">
                    @if($u->avatar)
                        <img src="{{ \App\Support\PublicStorageUrl::resolve($u->avatar) }}" class="w-10 h-10 rounded-full object-cover" alt=""/>
                    @else
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center font-bold">{{ $u->getInitials() }}</div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold truncate" style="color: var(--text-primary);">{{ $u->name }}</p>
                        <p class="text-xs truncate" style="color: var(--text-faint);">{{ $u->email }} · followed {{ $f->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                @endif
            @endforeach
        </div>
        <div class="mt-6">{{ $followers->links() }}</div>
    @endif
</div>
@endsection
