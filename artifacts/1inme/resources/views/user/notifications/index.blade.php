@extends('user.layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Notifications</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('user.notifications.preferences') }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold border"
               style="border-color: var(--border-soft); color: var(--text-primary);">
                <i class="fas fa-sliders-h mr-1"></i> Preferences
            </a>
            <form action="{{ route('user.notifications.read') }}" method="POST">@csrf
                <button class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-600 text-white">Mark all read</button>
            </form>
        </div>
    </div>

    @if(session('dismissed_id'))
        <div class="mb-4 p-3 rounded-lg bg-slate-800/90 text-white text-sm flex items-center justify-between gap-3">
            <span><i class="fas fa-check-circle mr-1 text-emerald-400"></i> Notification removed.</span>
            <form action="{{ route('user.notifications.restore', session('dismissed_id')) }}" method="POST">@csrf
                <button type="submit" class="px-3 py-1 rounded-md bg-white/15 hover:bg-white/25 font-semibold text-xs transition-colors">
                    <i class="fas fa-rotate-left mr-1"></i> Undo
                </button>
            </form>
        </div>
    @elseif(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    @if($notifications->count() === 0)
        <div class="text-center py-16 rounded-2xl border" style="background: var(--bg-card); border-color: var(--border-soft);">
            <i class="fas fa-bell-slash text-4xl mb-3" style="color: var(--text-faint);"></i>
            <p style="color: var(--text-muted);">You're all caught up.</p>
        </div>
    @else
        <div class="rounded-2xl border divide-y" style="background: var(--bg-card); border-color: var(--border-soft);">
            @foreach($notifications as $n)
                @php $d = $n->data ?? []; $target = $n->targetUrl(); @endphp
                <div class="relative p-4 flex items-start gap-3 {{ $n->read_at ? '' : 'bg-blue-50/30' }} {{ $target ? 'hover:bg-blue-500/5 transition-colors' : '' }}">
                    @if($target)
                        {{-- Stretched link: clicking anywhere on the row opens the
                             target and marks this notification read in one step.
                             The dismiss / mark-read controls sit above it (z-10). --}}
                        <a href="{{ route('user.notifications.open', $n->id) }}"
                           class="absolute inset-0 z-0" aria-label="Open notification"></a>
                    @endif
                    @include('user.notifications._icon', ['n' => $n, 'd' => $d])
                    <div class="flex-1">
                        @if($n->type === 'new_follower')
                            <p class="text-sm" style="color: var(--text-primary);"><span class="font-semibold">{{ $d['follower_name'] ?? 'Someone' }}</span> started following you.</p>
                        @elseif($n->type === 'follower_update')
                            <p class="text-sm" style="color: var(--text-primary);"><span class="font-semibold">{{ $d['creator_name'] ?? 'A creator' }}</span> {{ $d['message'] ?? 'has new activity' }}</p>
                        @elseif($n->type === 'social_connection_broken')
                            <p class="text-sm" style="color: var(--text-primary);">{{ $d['message'] ?? 'A social connection needs your attention.' }}</p>
                            @if(!empty($d['fix_url']))
                                <a href="{{ $d['fix_url'] }}" class="inline-flex items-center gap-1 mt-1 text-xs font-semibold text-blue-600 hover:underline">
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
                                            style="border-color:#3d6bff; color: var(--text-primary); background: rgba(92,131,255,0.06);">
                                    &ldquo;{{ $d['note'] }}&rdquo;
                                </blockquote>
                            @endif
                            <a href="{{ route('user.team.index') }}" class="inline-flex items-center gap-1 mt-1 text-xs font-semibold text-blue-600 hover:underline">
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
                        @elseif($n->type === 'billing.subscription_update')
                            <p class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-credit-card mr-1" style="color:#2563eb;"></i>
                                {{ $d['message'] ?? 'Your subscription has changed.' }}
                            </p>
                            @if(!empty($d['url']))
                                <a href="{{ $d['url'] }}" class="inline-flex items-center gap-1 mt-1 text-xs font-semibold text-blue-600 hover:underline">
                                    <i class="fas fa-arrow-right"></i> View billing
                                </a>
                            @endif
                        @elseif($n->type === 'delivery_project.comment')
                            <p class="text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-comment-dots mr-1" style="color:#3d6bff;"></i>
                                {{ $d['message'] ?? 'A client commented on a delivery project' }}
                                @if(!empty($d['snippet']))
                                    <span class="italic">— {{ \Illuminate\Support\Str::limit($d['snippet'], 100) }}</span>
                                @endif
                            </p>
                            @if(!empty($d['url']))
                                <a href="{{ $d['url'] }}" class="inline-flex items-center gap-1 mt-1 text-xs font-semibold text-blue-600 hover:underline">
                                    <i class="fas fa-arrow-right"></i> Open project
                                </a>
                            @endif
                        @else
                            <p class="text-sm" style="color: var(--text-primary);">{{ $d['message'] ?? $n->type }}</p>
                        @endif
                        <p class="text-xs mt-1" style="color: var(--text-faint);">{{ $n->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="relative z-10 flex items-center gap-1 flex-shrink-0">
                        @if(!$n->read_at)
                            <form action="{{ route('user.notifications.read-one', $n->id) }}" method="POST">@csrf
                                <button type="submit" title="Mark as read"
                                        class="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-blue-500/10 transition-colors"
                                        style="color: var(--text-faint);">
                                    <i class="fas fa-check text-xs"></i>
                                </button>
                            </form>
                        @endif
                        <form action="{{ route('user.notifications.destroy', $n->id) }}" method="POST"
                              onsubmit="return confirm('Remove this notification?');">@csrf @method('DELETE')
                            <button type="submit" title="Dismiss"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-rose-500/10 transition-colors"
                                    style="color: var(--text-faint);">
                                <i class="fas fa-xmark text-xs"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $notifications->links() }}</div>
    @endif

    @if(!empty($dismissed) && $dismissed->count() > 0)
        <div class="mt-10">
            <div class="flex items-center gap-2 mb-3">
                <i class="fas fa-trash-can-arrow-up text-sm" style="color: var(--text-faint);"></i>
                <h2 class="text-sm font-semibold" style="color: var(--text-muted);">Recently dismissed</h2>
                <span class="text-xs" style="color: var(--text-faint);">— restore within 30 days</span>
            </div>
            <div class="rounded-2xl border divide-y" style="background: var(--bg-card); border-color: var(--border-soft);">
                @foreach($dismissed as $n)
                    @php $d = $n->data ?? []; @endphp
                    <div class="p-4 flex items-start gap-3 opacity-75">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                             style="background: rgba(148,163,184,0.15); color: var(--text-faint);">
                            <i class="fas fa-bell-slash"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm truncate" style="color: var(--text-primary);">{{ $d['message'] ?? $n->type }}</p>
                            <p class="text-xs mt-1" style="color: var(--text-faint);">dismissed {{ $n->dismissed_at->diffForHumans() }}</p>
                        </div>
                        <form action="{{ route('user.notifications.restore', $n->id) }}" method="POST" class="flex-shrink-0">@csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold border hover:bg-blue-500/10 transition-colors"
                                    style="border-color: var(--border-soft); color: var(--text-primary);">
                                <i class="fas fa-rotate-left"></i> Restore
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
