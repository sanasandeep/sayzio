@extends('admin.layouts.app')
@section('title', 'Demo Content')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold mb-1" style="color: var(--text-primary);">Demo Content</h1>
        <p class="text-sm" style="color: var(--text-dimmed);">
            Seed sample creators, Link in Bio pages, and feed posts across every visibility tier
            (public / registered / followers / subscribers), or wipe them all in one click.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="rounded-2xl border p-4" style="background: var(--surface-1); border-color: var(--border-soft);">
            <div class="text-[11px] uppercase tracking-wide" style="color: var(--text-dimmed);">Demo creators</div>
            <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ number_format($stats['creators'] ?? $stats['users']) }}</div>
        </div>
        <div class="rounded-2xl border p-4" style="background: var(--surface-1); border-color: var(--border-soft);">
            <div class="text-[11px] uppercase tracking-wide" style="color: var(--text-dimmed);">Demo links</div>
            <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ number_format($stats['links']) }}</div>
        </div>
        <div class="rounded-2xl border p-4" style="background: var(--surface-1); border-color: var(--border-soft);">
            <div class="text-[11px] uppercase tracking-wide" style="color: var(--text-dimmed);">Demo feed posts</div>
            <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ number_format($stats['feed_events']) }}</div>
        </div>
        <div class="rounded-2xl border p-4" style="background: var(--surface-1); border-color: var(--border-soft);">
            <div class="text-[11px] uppercase tracking-wide" style="color: var(--text-dimmed);">Demo super-admin</div>
            <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['demo_user'] ? 'Yes' : 'No' }}</div>
        </div>
        <div class="rounded-2xl border p-4" style="background: var(--surface-1); border-color: var(--border-soft);">
            <div class="text-[11px] uppercase tracking-wide" style="color: var(--text-dimmed);">Demo team workspaces</div>
            <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ number_format($stats['workspaces'] ?? 0) }}</div>
        </div>
        <div class="rounded-2xl border p-4" style="background: var(--surface-1); border-color: var(--border-soft);">
            <div class="text-[11px] uppercase tracking-wide" style="color: var(--text-dimmed);">Demo task boards</div>
            <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ number_format($stats['task_boards'] ?? 0) }}</div>
        </div>
        <div class="rounded-2xl border p-4" style="background: var(--surface-1); border-color: var(--border-soft);">
            <div class="text-[11px] uppercase tracking-wide" style="color: var(--text-dimmed);">Demo task cards</div>
            <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ number_format($stats['task_cards'] ?? 0) }}</div>
        </div>
        <div class="rounded-2xl border p-4" style="background: var(--surface-1); border-color: var(--border-soft);">
            <div class="text-[11px] uppercase tracking-wide" style="color: var(--text-dimmed);">Demo team members</div>
            <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ number_format($stats['team_members'] ?? 0) }}</div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="grid md:grid-cols-2 gap-4">
        {{-- Seed --}}
        <div class="rounded-2xl border p-5" style="background: var(--surface-1); border-color: var(--border-soft);">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(16,185,129,.15); color:#34d399;">
                    <i class="fas fa-seedling"></i>
                </div>
                <h2 class="text-base font-semibold" style="color: var(--text-primary);">Create demo content</h2>
            </div>
            <p class="text-xs mb-4" style="color: var(--text-dimmed);">
                Wipes any existing demo data, then re-creates the full demo footprint: 50+ links of every
                type, 5 workspaces (1 personal + 4 team) with members, invites and populated task boards,
                10 demo creators with Link in Bio pages, and 50+ feed events per creator across all four visibility
                tiers. Existing real users are never touched.
            </p>
            <ul class="text-xs mb-4 space-y-1.5" style="color: var(--text-dimmed);">
                <li><span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-2"></span><strong>Public</strong> — visible to anyone</li>
                <li><span class="inline-block w-2 h-2 rounded-full bg-sky-400 mr-2"></span><strong>Registered</strong> — visible to logged-in viewers</li>
                <li><span class="inline-block w-2 h-2 rounded-full bg-fuchsia-400 mr-2"></span><strong>Followers-only</strong> — must follow first</li>
                <li><span class="inline-block w-2 h-2 rounded-full bg-amber-400 mr-2"></span><strong>Subscribers-only</strong> — paid / subscribed</li>
            </ul>
            <form method="POST" action="{{ route('admin.demo-content.seed') }}"
                  onsubmit="return window.themedConfirmSubmit(this, {title: 'Re-seed demo content?', message: 'Any existing demo rows will be replaced.', confirmText: 'Re-seed', confirmIcon: 'fa-rotate', iconClass: 'fa-rotate'})">
                @csrf
                <button class="w-full px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold">
                    <i class="fas fa-magic mr-2"></i>Create / re-seed demo content
                </button>
            </form>
        </div>

        {{-- Wipe --}}
        <div class="rounded-2xl border p-5" style="background: var(--surface-1); border-color: var(--border-soft);">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(239,68,68,.15); color:#fca5a5;">
                    <i class="fas fa-broom"></i>
                </div>
                <h2 class="text-base font-semibold" style="color: var(--text-primary);">Remove all demo content</h2>
            </div>
            <p class="text-xs mb-4" style="color: var(--text-dimmed);">
                Deletes every row marked as demo (creators, Link in Bio pages, short links, file/event/vCard links,
                feed posts, demo follows and demo subscribers). The original demo super-admin
                (<code>sazioapp@gmail.com</code>) is preserved so you keep dashboard access.
            </p>
            <form method="POST" action="{{ route('admin.demo-content.wipe') }}"
                  onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove all demo content?', message: 'This cannot be undone.', confirmText: 'Remove', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                @csrf
                <button class="w-full px-4 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-400 text-white text-sm font-semibold">
                    <i class="fas fa-trash-can mr-2"></i>Wipe demo content
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
