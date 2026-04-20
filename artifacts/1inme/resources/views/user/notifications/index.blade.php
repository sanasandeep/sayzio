@extends('user.layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Notifications</h1>
        <form action="{{ route('user.notifications.read') }}" method="POST">@csrf
            <button class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-violet-600 text-white">Mark all read</button>
        </form>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    @if($notifications->count() === 0)
        <div class="text-center py-16 rounded-2xl border" style="background: var(--bg-card); border-color: var(--border-soft);">
            <i class="fas fa-bell-slash text-4xl mb-3" style="color: var(--text-faint);"></i>
            <p style="color: var(--text-muted);">You're all caught up.</p>
        </div>
    @else
        <div class="rounded-2xl border divide-y" style="background: var(--bg-card); border-color: var(--border-soft);">
            @foreach($notifications as $n)
                @php $d = $n->data ?? []; @endphp
                <div class="p-4 flex items-start gap-3 {{ $n->read_at ? '' : 'bg-violet-50/30' }}">
                    @if(!empty($d['follower_avatar']) || !empty($d['creator_avatar']))
                        <img src="{{ $d['follower_avatar'] ?? $d['creator_avatar'] }}" class="w-10 h-10 rounded-full object-cover" alt=""/>
                    @else
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white flex items-center justify-center font-bold">
                            {{ strtoupper(substr($d['follower_name'] ?? $d['creator_name'] ?? '?', 0, 1)) }}
                        </div>
                    @endif
                    <div class="flex-1">
                        @if($n->type === 'new_follower')
                            <p class="text-sm" style="color: var(--text-primary);"><span class="font-semibold">{{ $d['follower_name'] ?? 'Someone' }}</span> started following you.</p>
                        @elseif($n->type === 'follower_update')
                            <p class="text-sm" style="color: var(--text-primary);"><span class="font-semibold">{{ $d['creator_name'] ?? 'A creator' }}</span> {{ $d['message'] ?? 'has new activity' }}</p>
                        @else
                            <p class="text-sm" style="color: var(--text-primary);">{{ $d['message'] ?? $n->type }}</p>
                        @endif
                        <p class="text-xs mt-1" style="color: var(--text-faint);">{{ $n->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $notifications->links() }}</div>
    @endif
</div>
@endsection
