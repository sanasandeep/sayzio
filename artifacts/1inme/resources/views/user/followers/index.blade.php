@extends('user.layouts.app')
@section('title', 'Followers')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    @include('user.partials.page-hero', [
        'title'      => 'Followers',
        'subtitle'   => 'People following your creator profile.',
        'emblemIcon' => 'fa-user-group',
        'back'       => route('user.dashboard'),
        'chips'      => [
            ['text' => \App\Modules\User\Support\HeroCount::label($followers ?? null, 'follower'), 'icon' => 'fa-user-group'],
        ],
        'actions'    => [
            ['label' => 'Following', 'url' => route('user.following.index'), 'icon' => 'fa-user-plus', 'class' => 'btn-ghost'],
        ],
    ])

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
                        <img src="{{ \App\Support\PublicStorageUrl::resolve($u->avatar) }}" class="w-10 h-10 rounded-full object-cover" alt=""
                             @if($u->handle && $u->profile_published) data-mini-profile="{{ $u->handle }}" style="cursor:pointer;" @endif>
                    @else
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center font-bold"
                             @if($u->handle && $u->profile_published) data-mini-profile="{{ $u->handle }}" style="cursor:pointer;" @endif>{{ $u->getInitials() }}</div>
                    @endif
                    <div class="flex-1 min-w-0">
                        @if($u->handle && $u->profile_published)
                            <a href="{{ route('creator-profile.show', $u->handle) }}" data-mini-profile="{{ $u->handle }}" class="font-semibold truncate block" style="color: var(--text-primary);">{{ $u->name }}</a>
                        @else
                            <p class="font-semibold truncate" style="color: var(--text-primary);">{{ $u->name }}</p>
                        @endif
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
