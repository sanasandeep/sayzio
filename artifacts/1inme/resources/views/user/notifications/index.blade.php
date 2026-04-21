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
                    @if($n->type === 'social_connection_broken')
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background: rgba(239,68,68,0.12); color:#ef4444;">
                            <i class="fas fa-triangle-exclamation"></i>
                        </div>
                    @elseif($n->type === 'workspace_access_request')
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background: rgba(139,92,246,0.12); color:#7c3aed;">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    @elseif($n->type === 'task_assigned')
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background: rgba(34,197,94,0.12); color:#16a34a;">
                            <i class="fas fa-list-check"></i>
                        </div>
                    @elseif($n->type === 'task_mention')
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background: rgba(59,130,246,0.12); color:#2563eb;">
                            <i class="fas fa-at"></i>
                        </div>
                    @elseif($n->type === 'task_due')
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background: rgba(234,179,8,0.12); color:#ca8a04;">
                            <i class="fas fa-clock"></i>
                        </div>
                    @elseif($n->type === 'task_overdue')
                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                             style="background: rgba(239,68,68,0.12); color:#dc2626;">
                            <i class="fas fa-fire"></i>
                        </div>
                    @elseif(!empty($d['follower_avatar']) || !empty($d['creator_avatar']))
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
                        @elseif($n->type === 'social_connection_broken')
                            <p class="text-sm" style="color: var(--text-primary);">{{ $d['message'] ?? 'A social connection needs your attention.' }}</p>
                            @if(!empty($d['fix_url']))
                                <a href="{{ $d['fix_url'] }}" class="inline-flex items-center gap-1 mt-1 text-xs font-semibold text-violet-600 hover:underline">
                                    <i class="fas fa-rotate-right"></i> Fix it on Connected Accounts
                                </a>
                            @endif
                        @elseif($n->type === 'workspace_access_request')
                            <p class="text-sm" style="color: var(--text-primary);">
                                <span class="font-semibold">{{ $d['requester_name'] ?? 'A teammate' }}</span>
                                is asking for access to
                                <span class="font-semibold">{{ $d['workspace_name'] ?? 'a workspace' }}</span>@if(!empty($d['path'])) — they tried to open <code class="text-xs px-1 rounded" style="background: var(--bg-subtle);">/{{ ltrim($d['path'], '/') }}</code>@endif.
                            </p>
                            @if(!empty($d['note']))
                                <blockquote class="mt-2 text-sm italic border-l-2 pl-3 py-1"
                                            style="border-color:#7c3aed; color: var(--text-primary); background: rgba(139,92,246,0.06);">
                                    &ldquo;{{ $d['note'] }}&rdquo;
                                </blockquote>
                            @endif
                            <a href="{{ route('user.team.index') }}" class="inline-flex items-center gap-1 mt-1 text-xs font-semibold text-violet-600 hover:underline">
                                <i class="fas fa-users-gear"></i> Manage team access
                            </a>
                        @elseif($n->type === 'task_assigned')
                            <p class="text-sm" style="color: var(--text-primary);">
                                <span class="font-semibold">{{ $d['assigner'] ?? 'Someone' }}</span>
                                assigned you to a task in
                                <span class="font-semibold">{{ $d['board_name'] ?? 'a board' }}</span>:
                                <span class="italic">{{ \Illuminate\Support\Str::limit($d['message'] ?? '', 80) }}</span>
                            </p>
                            @if(!empty($d['url']))
                                <a href="{{ $d['url'] }}" class="inline-flex items-center gap-1 mt-1 text-xs font-semibold text-emerald-600 hover:underline">
                                    <i class="fas fa-arrow-right"></i> Open task
                                </a>
                            @endif
                        @elseif($n->type === 'task_mention')
                            <p class="text-sm" style="color: var(--text-primary);">
                                <span class="font-semibold">{{ $d['mentioner'] ?? 'Someone' }}</span>
                                mentioned you in
                                <span class="font-semibold">{{ $d['board_name'] ?? 'a board' }}</span>:
                                <span class="italic">{{ \Illuminate\Support\Str::limit($d['snippet'] ?? $d['message'] ?? '', 100) }}</span>
                            </p>
                            @if(!empty($d['url']))
                                <a href="{{ $d['url'] }}" class="inline-flex items-center gap-1 mt-1 text-xs font-semibold text-blue-600 hover:underline">
                                    <i class="fas fa-arrow-right"></i> Jump to comment
                                </a>
                            @endif
                        @elseif($n->type === 'task_due')
                            <p class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-clock mr-1" style="color:#ca8a04;"></i>
                                A card you're assigned to is
                                <span class="font-semibold">due today</span>
                                in <span class="font-semibold">{{ $d['board_name'] ?? 'a board' }}</span>:
                                <span class="italic">{{ \Illuminate\Support\Str::limit($d['message'] ?? '', 80) }}</span>
                            </p>
                            @if(!empty($d['url']))
                                <a href="{{ $d['url'] }}" class="inline-flex items-center gap-1 mt-1 text-xs font-semibold text-yellow-700 hover:underline">
                                    <i class="fas fa-arrow-right"></i> Open card
                                </a>
                            @endif
                        @elseif($n->type === 'task_overdue')
                            <p class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-fire mr-1" style="color:#dc2626;"></i>
                                <span class="font-semibold text-red-600">Overdue:</span>
                                a card you're assigned to in
                                <span class="font-semibold">{{ $d['board_name'] ?? 'a board' }}</span>
                                — <span class="italic">{{ \Illuminate\Support\Str::limit($d['message'] ?? '', 80) }}</span>
                                @if(!empty($d['due_date'])) <span class="text-xs" style="color: var(--text-faint);">(was due {{ $d['due_date'] }})</span>@endif
                            </p>
                            @if(!empty($d['url']))
                                <a href="{{ $d['url'] }}" class="inline-flex items-center gap-1 mt-1 text-xs font-semibold text-red-600 hover:underline">
                                    <i class="fas fa-arrow-right"></i> Resolve now
                                </a>
                            @endif
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
