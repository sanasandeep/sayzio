@extends('admin.layouts.app')
@section('title', 'Review Badge Request')
@section('content')
<div class="max-w-2xl mx-auto">
    <a href="{{ route('admin.badge-requests.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold mb-4" style="color: var(--text-dimmed);">
        <i class="fas fa-arrow-left"></i> Back to queue
    </a>

    <div class="rounded-2xl p-5 mb-5" style="border:1px solid var(--border-glass); background: var(--bg-card);">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-lg font-bold" style="color: var(--text-primary);">{{ $badgeRequest->requestedLabel() }}</h1>
            @php $c = ['pending' => '#f59e0b', 'approved' => '#10b981', 'rejected' => '#ef4444'][$badgeRequest->status] ?? '#64748b'; @endphp
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold text-white" style="background: {{ $c }};">{{ ucfirst($badgeRequest->status) }}</span>
        </div>

        <dl class="space-y-3 text-sm">
            <div>
                <dt class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">User</dt>
                <dd style="color: var(--text-primary);">{{ $badgeRequest->user->name ?? 'User #' . $badgeRequest->user_id }} <span style="color: var(--text-faint);">({{ $badgeRequest->user->email ?? '' }})</span></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Requested</dt>
                <dd style="color: var(--text-primary);">
                    @if($badgeRequest->account_badge_id && $badgeRequest->badge)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold text-white" style="background: {{ $badgeRequest->badge->color }};"><i class="fas fa-certificate text-[10px]"></i> {{ $badgeRequest->badge->name }}</span>
                    @else
                        {{ $badgeRequest->custom_name }} <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded" style="background: rgba(245,158,11,0.15); color:#f59e0b;">custom</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Reason</dt>
                <dd style="color: var(--text-primary); white-space: pre-line;">{{ $badgeRequest->reason }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Submitted</dt>
                <dd style="color: var(--text-primary);">{{ $badgeRequest->created_at->format('M j, Y g:i A') }}</dd>
            </div>
            @if($badgeRequest->assigned_badge_id && $badgeRequest->assignedBadge)
            <div>
                <dt class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Assigned badge</dt>
                <dd style="color: var(--text-primary);">{{ $badgeRequest->assignedBadge->name }}</dd>
            </div>
            @endif
            @if($badgeRequest->admin_notes)
            <div>
                <dt class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Admin notes</dt>
                <dd style="color: var(--text-primary);">{{ $badgeRequest->admin_notes }}</dd>
            </div>
            @endif
        </dl>
    </div>

    @if($badgeRequest->isPending())
    <div class="grid sm:grid-cols-2 gap-4">
        <form method="POST" action="{{ route('admin.badge-requests.approve', $badgeRequest->id) }}" class="rounded-2xl p-5" style="border:1px solid rgba(16,185,129,0.25); background: rgba(16,185,129,0.04);" x-data="{ src: 'existing' }">
            @csrf
            <h2 class="text-sm font-bold mb-3" style="color:#10b981;"><i class="fas fa-check-circle mr-1"></i> Approve &amp; assign</h2>

            @if($badgeRequest->account_badge_id)
                <p class="text-xs mb-3" style="color: var(--text-dimmed);">Assigns the requested <strong>{{ $badgeRequest->badge->name ?? 'badge' }}</strong>. Choose another below to override.</p>
                <select name="assign_badge_id" class="w-full mb-3 px-3 py-2 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-glass); color: var(--text-primary);">
                    <option value="">Use requested badge</option>
                    @foreach($badges as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            @else
                <div class="flex gap-4 mb-3 text-xs" style="color: var(--text-primary);">
                    <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="__src" value="existing" x-model="src"> Existing badge</label>
                    <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="__src" value="new" x-model="src"> New badge</label>
                </div>
                <select name="assign_badge_id" x-show="src === 'existing'" :disabled="src !== 'existing'" class="w-full mb-3 px-3 py-2 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-glass); color: var(--text-primary);">
                    <option value="">Choose a badge</option>
                    @foreach($badges as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
                <div x-show="src === 'new'" x-cloak class="mb-3 flex items-center gap-2">
                    <input type="text" name="new_badge_name" value="{{ $badgeRequest->custom_name }}" :disabled="src !== 'new'" maxlength="120" placeholder="Badge name" class="flex-1 px-3 py-2 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-glass); color: var(--text-primary);">
                    <input type="color" name="new_badge_color" value="#3b82f6" :disabled="src !== 'new'" class="h-9 w-12 rounded cursor-pointer" title="Badge color">
                </div>
            @endif

            <textarea name="admin_notes" rows="2" maxlength="2000" placeholder="Optional note to the user" class="w-full mb-3 px-3 py-2 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-glass); color: var(--text-primary);"></textarea>
            <button type="submit" class="w-full py-2.5 rounded-lg text-sm font-bold text-white" style="background:#10b981;">Approve</button>
        </form>

        <form method="POST" action="{{ route('admin.badge-requests.reject', $badgeRequest->id) }}" class="rounded-2xl p-5" style="border:1px solid rgba(239,68,68,0.25); background: rgba(239,68,68,0.04);">
            @csrf
            <h2 class="text-sm font-bold mb-3" style="color:#ef4444;"><i class="fas fa-times-circle mr-1"></i> Reject</h2>
            <textarea name="admin_notes" rows="5" maxlength="2000" required placeholder="Reason (shown to the user)" class="w-full mb-3 px-3 py-2 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-glass); color: var(--text-primary);"></textarea>
            <button type="submit" class="w-full py-2.5 rounded-lg text-sm font-bold text-white" style="background:#ef4444;">Reject</button>
        </form>
    </div>
    @else
        <div class="rounded-2xl p-4 text-sm" style="border:1px solid var(--border-glass); background: var(--bg-card); color: var(--text-dimmed);">
            This request was {{ $badgeRequest->status }} {{ $badgeRequest->reviewed_at?->diffForHumans() }}.
        </div>
    @endif
</div>
@endsection
