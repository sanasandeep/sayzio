@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@if(!empty($schemaHealth['available']) && !empty($schemaHealth['pending']))
@php($pendingMigrations = $schemaHealth['pending'])
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(239,68,68,0.35); background: rgba(239,68,68,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-red-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-database text-red-400 text-lg"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-red-300">Database schema is out of date</h2>
            <p class="text-sm text-white/70 mt-1">
                {{ count($pendingMigrations) }} {{ \Illuminate\Support\Str::plural('migration', count($pendingMigrations)) }}
                {{ count($pendingMigrations) === 1 ? 'has' : 'have' }} not been applied. This usually means the deploy's
                <code class="px-1 py-0.5 rounded bg-black/30 text-red-200">php artisan migrate --force</code> step failed,
                leaving tables/columns missing — some pages may return errors until it's fixed.
                Run <code class="px-1 py-0.5 rounded bg-black/30 text-red-200">php artisan migrate --force</code> against production.
            </p>
            <details class="mt-3">
                <summary class="text-xs text-red-300/80 cursor-pointer select-none">Show pending migrations</summary>
                <ul class="mt-2 space-y-1 text-xs text-white/50 font-mono">
                    @foreach(array_slice($pendingMigrations, 0, 20) as $m)
                        <li>{{ $m }}</li>
                    @endforeach
                    @if(count($pendingMigrations) > 20)
                        <li class="text-white/40">…and {{ count($pendingMigrations) - 20 }} more</li>
                    @endif
                </ul>
            </details>
        </div>
    </div>
</div>
@endif

@if(!empty($workspaceColumnHealth['available']) && !empty($workspaceColumnHealth['missing']))
@php($missingColumns = $workspaceColumnHealth['missing'])
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(239,68,68,0.35); background: rgba(239,68,68,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-red-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-table-columns text-red-400 text-lg"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-red-300">Workspace columns are missing</h2>
            <p class="text-sm text-white/70 mt-1">
                {{ count($missingColumns) }} {{ \Illuminate\Support\Str::plural('table', count($missingColumns)) }}
                {{ count($missingColumns) === 1 ? 'is' : 'are' }} missing a
                <code class="px-1 py-0.5 rounded bg-black/30 text-red-200">workspace_id</code> /
                <code class="px-1 py-0.5 rounded bg-black/30 text-red-200">created_by_user_id</code> column even though
                their migration is recorded as applied — a half-applied migration. Workspace-scoped pages for these
                tables will return errors until it's fixed. Run
                <code class="px-1 py-0.5 rounded bg-black/30 text-red-200">php artisan db:check-workspace-columns --repair</code>
                against production.
            </p>
            <details class="mt-3">
                <summary class="text-xs text-red-300/80 cursor-pointer select-none">Show affected tables</summary>
                <ul class="mt-2 space-y-1 text-xs text-white/50 font-mono">
                    @foreach(array_slice($missingColumns, 0, 25) as $m)
                        <li>{{ $m['table'] }} &mdash; {{ implode(', ', $m['columns']) }}</li>
                    @endforeach
                    @if(count($missingColumns) > 25)
                        <li class="text-white/40">…and {{ count($missingColumns) - 25 }} more</li>
                    @endif
                </ul>
            </details>
        </div>
    </div>
</div>
@endif

@if(!empty($expectedSchemaHealth['available']) && !empty($expectedSchemaHealth['missing']))
@php($missingExpected = $expectedSchemaHealth['missing'])
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(239,68,68,0.35); background: rgba(239,68,68,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-red-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-table-columns text-red-400 text-lg"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-red-300">Expected database columns are missing</h2>
            <p class="text-sm text-white/70 mt-1">
                {{ count($missingExpected) }} {{ \Illuminate\Support\Str::plural('table', count($missingExpected)) }}
                {{ count($missingExpected) === 1 ? 'is' : 'are' }} missing a column the app depends on even though
                their migration is recorded as applied — an <span class="text-red-200">edited-after-applied</span>
                migration (a recorded migration was later changed to add columns, so Laravel never re-ran it and
                <code class="px-1 py-0.5 rounded bg-black/30 text-red-200">migrate:status</code> still shows 0 pending).
                Pages that read these columns will return errors until it's fixed.
                @php($columnDriftOnly = collect($missingExpected)->every(fn ($m) => empty($m['table_missing'])))
                @if($columnDriftOnly)
                    Click <span class="text-red-200 font-semibold">Fix now</span> to add and backfill the missing
                    columns in place, or run
                    <code class="px-1 py-0.5 rounded bg-black/30 text-red-200">php artisan migrate --force</code>
                    against production.
                @else
                    Some entries are whole missing tables that need a full migration — run
                    <code class="px-1 py-0.5 rounded bg-black/30 text-red-200">php artisan migrate --force</code>
                    against production. Fix now will repair any missing columns it can.
                @endif
            </p>
            <div class="mt-3 flex items-center gap-3">
                <form method="POST" action="{{ route('admin.schema.repair-expected-columns') }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-red-500/20 hover:bg-red-500/30 text-red-200 border border-red-500/40 transition"
                        onclick="this.disabled=true; this.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Repairing…'; this.form.submit();">
                        <i class="fas fa-wrench"></i> Fix now
                    </button>
                </form>
                <a href="{{ route('admin.schema.repair-audits') }}"
                   class="inline-flex items-center gap-2 text-xs text-red-300/80 hover:text-red-200 underline">
                    <i class="fas fa-clock-rotate-left"></i> View repair history
                </a>
            </div>
            <details class="mt-3">
                <summary class="text-xs text-red-300/80 cursor-pointer select-none">Show affected tables</summary>
                <ul class="mt-2 space-y-1 text-xs text-white/50 font-mono">
                    @foreach(array_slice($missingExpected, 0, 25) as $m)
                        <li>{{ $m['table'] }} &mdash; {{ !empty($m['table_missing']) ? 'entire table missing' : implode(', ', $m['columns']) }}</li>
                    @endforeach
                    @if(count($missingExpected) > 25)
                        <li class="text-white/40">…and {{ count($missingExpected) - 25 }} more</li>
                    @endif
                </ul>
            </details>
        </div>
    </div>
</div>
@endif

@if(!empty($statsStorage['available']) && !empty($statsStorage['growth_unbounded']))
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-amber-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-hard-drive text-amber-400 text-lg"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-amber-300">Analytics storage is growing unbounded</h2>
            <p class="text-sm text-white/70 mt-1">
                A high-volume analytics table has crossed the alert threshold of
                <span class="font-mono text-amber-200">{{ number_format($statsStorage['alert_threshold']) }}</span> rows and
                nothing will prune it &mdash; {{ $statsStorage['reason'] }}. Set a hard cap to bound storage.
            </p>
            <div class="mt-3">
                <a href="{{ route('admin.stats-storage.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 border border-amber-500/40 transition">
                    <i class="fas fa-sliders"></i> Review analytics storage
                </a>
            </div>
        </div>
    </div>
</div>
@endif

@if(!empty($templateGalleryHealth['available']) && !empty($templateGalleryHealth['empty']))
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-amber-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-layer-group text-amber-400 text-lg"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-amber-300">The onboarding template gallery is empty</h2>
            <p class="text-sm text-white/70 mt-1">
                There are <span class="text-amber-200 font-semibold">no active page templates</span>, so the
                new-user onboarding wizard silently degrades to its "No templates available yet" escape and new
                users land on a bare setup screen. Add or re-activate at least one template so onboarding can
                offer a starting point again.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('admin.templates.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 border border-amber-500/40 transition">
                    <i class="fas fa-layer-group"></i> Manage templates
                </a>
                <a href="{{ route('admin.templates.create') }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-white/5 hover:bg-white/10 text-white/80 border border-white/10 transition">
                    <i class="fas fa-plus"></i> Add a template
                </a>
            </div>
        </div>
    </div>
</div>
@endif

@if(!empty($contactRecipientHealth['available']) && empty($contactRecipientHealth['configured']))
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-amber-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-inbox text-amber-400 text-lg"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-amber-300">Leads are arriving but no one is being notified</h2>
            <p class="text-sm text-white/70 mt-1">
                No contact recipient email is configured, so callback, WhatsApp and email
                requests are saved to the Contact Inbox but <span class="text-amber-200">no notification is sent</span>.
                @if(!empty($contactRecipientHealth['total_leads']))
                    <span class="font-mono text-amber-200">{{ number_format($contactRecipientHealth['total_leads']) }}</span>
                    lead{{ $contactRecipientHealth['total_leads'] === 1 ? ' has' : 's have' }} already arrived
                    @if(!empty($contactRecipientHealth['pending_leads']))
                        (<span class="font-mono text-amber-200">{{ number_format($contactRecipientHealth['pending_leads']) }}</span> still unread)
                    @endif
                    &mdash; set a recipient so future leads reach someone.
                @else
                    Set a recipient so leads reach someone.
                @endif
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('admin.site-pages.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 border border-amber-500/40 transition">
                    <i class="fas fa-envelope"></i> Set contact recipient
                </a>
                <a href="{{ route('admin.contact-inbox.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-white/5 hover:bg-white/10 text-white/80 border border-white/10 transition">
                    <i class="fas fa-inbox"></i> Open Contact Inbox
                </a>
            </div>
        </div>
    </div>
</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="glass rounded-2xl p-6 border border-white/10 ">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white/40">Total Users</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['total_users']) }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-blue-400 text-lg"></i>
            </div>
        </div>
        <p class="text-xs text-emerald-400 mt-3"><i class="fas fa-arrow-up mr-1"></i>{{ $stats['users_today'] }} today</p>
    </div>

    <div class="glass rounded-2xl p-6 border border-white/10 ">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white/40">Active Users</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['active_users']) }}</p>
            </div>
            <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-user-check text-emerald-400 text-lg"></i>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl p-6 border border-white/10 ">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white/40">Staff Members</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['total_staff']) }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-user-shield text-blue-400 text-lg"></i>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl p-6 border border-white/10 ">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white/40">This Month</p>
                <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['users_this_month']) }}</p>
            </div>
            <div class="w-12 h-12 bg-amber-500/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-calendar text-amber-400 text-lg"></i>
            </div>
        </div>
    </div>
</div>

<div class="glass rounded-2xl border border-white/10 ">
    <div class="p-6 border-b border-white/10">
        <h2 class="text-lg font-semibold text-white">Recent Users</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-white/5">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($stats['recent_users'] as $user)
                <tr class="hover:bg-white/5">
                    <td class="px-6 py-4 text-sm text-white">{{ $user->name }}</td>
                    <td class="px-6 py-4 text-sm text-white/40">{{ $user->email }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $user->status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                            {{ ucfirst($user->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-white/40">{{ $user->created_at->diffForHumans() }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-white/30">No users yet</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
