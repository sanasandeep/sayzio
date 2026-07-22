@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
{{-- Open scheduled-job failure-episode banner (mirrors the schema-health pattern) --}}
@include('admin.partials.scheduled-job-failure-banner', ['failureEpisodes' => $failureEpisodes ?? []])

{{-- System update banner: shown on EC2 when GitHub main has new commits --}}
@php
    $su           = $updateStatus ?? null;
    $suAvailable  = !empty($su['available']);
    $suDismissed  = $suAvailable && request()->cookie('su_dismissed_sha') === ($su['remote_sha'] ?? '');
@endphp
@if($suAvailable && !$suDismissed)
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(59,130,246,0.35); background: rgba(59,130,246,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-blue-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-circle-up text-blue-400 text-lg ak-blue"></i>
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="text-base font-semibold text-blue-200 ak-blue">
                Update available
                @if(!empty($su['commits_behind']))
                    &mdash; {{ $su['commits_behind'] }} {{ \Illuminate\Support\Str::plural('commit', $su['commits_behind']) }} behind
                @endif
            </h2>
            @if(!empty($su['remote_message']))
            <p class="text-sm text-white/70 mt-1 truncate ak-strong">
                Latest: <span class="text-blue-200 font-mono text-xs ak-blue">{{ substr($su['remote_sha'] ?? '', 0, 8) }}</span>
                &mdash; {{ $su['remote_message'] }}
                @if(!empty($su['remote_date']))
                    <span class="text-white/40 ak-note">({{ \Carbon\Carbon::parse($su['remote_date'])->diffForHumans() }})</span>
                @endif
            </p>
            @endif
            <div class="mt-3 flex flex-wrap items-center gap-3">
                @if(!\App\Services\Integrations\SystemUpdateService::isDeployInProgress())
                <form method="POST" action="{{ route('admin.system-update.deploy') }}"
                      onsubmit="return confirm('Trigger the GitHub Actions \'Deploy to EC2\' workflow now? This will pull the latest code and restart services on the EC2 server.');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white transition"
                            onclick="this.disabled=true; this.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Dispatching…'; this.form.submit();">
                        <i class="fas fa-rocket"></i> Update now
                    </button>
                </form>
                @else
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600/30 text-white/50 border border-blue-500/20 ak-muted">
                    <i class="fas fa-spinner fa-spin"></i> Deploy in progress…
                </span>
                @endif
                <a href="{{ route('admin.system-update.show') }}"
                   class="inline-flex items-center gap-1.5 text-xs text-blue-300/70 hover:text-blue-200 transition ak-blue">
                    <i class="fas fa-info-circle"></i> Details
                </a>
                <form method="POST" action="{{ route('admin.system-update.dismiss') }}" class="ml-auto">
                    @csrf
                    <input type="hidden" name="sha" value="{{ $su['remote_sha'] ?? '' }}">
                    <button type="submit"
                            class="text-xs text-white/30 hover:text-white/50 transition ak-note">
                        Dismiss
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

@if(!empty($schemaHealth['available']) && !empty($schemaHealth['pending']))
@php
    $pendingMigrations = $schemaHealth['pending'];
@endphp
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(239,68,68,0.35); background: rgba(239,68,68,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-red-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-database text-red-400 text-lg ak-red"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-red-300 ak-red">Database schema is out of date</h2>
            <p class="text-sm text-white/70 mt-1 ak-strong">
                {{ count($pendingMigrations) }} {{ \Illuminate\Support\Str::plural('migration', count($pendingMigrations)) }}
                {{ count($pendingMigrations) === 1 ? 'has' : 'have' }} not been applied. This usually means the deploy's
                <code class="px-1 py-0.5 rounded bg-black/30 text-red-200 ak-red">php artisan migrate --force</code> step failed,
                leaving tables/columns missing, some pages may return errors until it's fixed.
                Run <code class="px-1 py-0.5 rounded bg-black/30 text-red-200 ak-red">php artisan migrate --force</code> against production.
            </p>
            <details class="mt-3">
                <summary class="text-xs text-red-300/80 cursor-pointer select-none ak-red">Show pending migrations</summary>
                <ul class="mt-2 space-y-1 text-xs text-white/50 font-mono ak-muted">
                    @foreach(array_slice($pendingMigrations, 0, 20) as $m)
                        <li>{{ $m }}</li>
                    @endforeach
                    @if(count($pendingMigrations) > 20)
                        <li class="text-white/40 ak-note">…and {{ count($pendingMigrations) - 20 }} more</li>
                    @endif
                </ul>
            </details>
        </div>
    </div>
</div>
@endif

@if(!empty($workspaceColumnHealth['available']) && !empty($workspaceColumnHealth['missing']))
@php
    $missingColumns = $workspaceColumnHealth['missing'];
@endphp
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(239,68,68,0.35); background: rgba(239,68,68,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-red-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-table-columns text-red-400 text-lg ak-red"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-red-300 ak-red">Workspace columns are missing</h2>
            <p class="text-sm text-white/70 mt-1 ak-strong">
                {{ count($missingColumns) }} {{ \Illuminate\Support\Str::plural('table', count($missingColumns)) }}
                {{ count($missingColumns) === 1 ? 'is' : 'are' }} missing a
                <code class="px-1 py-0.5 rounded bg-black/30 text-red-200 ak-red">workspace_id</code> /
                <code class="px-1 py-0.5 rounded bg-black/30 text-red-200 ak-red">created_by_user_id</code> column even though
                their migration is recorded as applied, a half-applied migration. Workspace-scoped pages for these
                tables will return errors until it's fixed. Run
                <code class="px-1 py-0.5 rounded bg-black/30 text-red-200 ak-red">php artisan db:check-workspace-columns --repair</code>
                against production.
            </p>
            <details class="mt-3">
                <summary class="text-xs text-red-300/80 cursor-pointer select-none ak-red">Show affected tables</summary>
                <ul class="mt-2 space-y-1 text-xs text-white/50 font-mono ak-muted">
                    @foreach(array_slice($missingColumns, 0, 25) as $m)
                        <li>{{ $m['table'] }} &mdash; {{ implode(', ', $m['columns']) }}</li>
                    @endforeach
                    @if(count($missingColumns) > 25)
                        <li class="text-white/40 ak-note">…and {{ count($missingColumns) - 25 }} more</li>
                    @endif
                </ul>
            </details>
        </div>
    </div>
</div>
@endif

@if(!empty($expectedSchemaHealth['available']) && !empty($expectedSchemaHealth['missing']))
@php
    $missingExpected = $expectedSchemaHealth['missing'];
@endphp
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(239,68,68,0.35); background: rgba(239,68,68,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-red-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-table-columns text-red-400 text-lg ak-red"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-red-300 ak-red">Expected database columns are missing</h2>
            <p class="text-sm text-white/70 mt-1 ak-strong">
                {{ count($missingExpected) }} {{ \Illuminate\Support\Str::plural('table', count($missingExpected)) }}
                {{ count($missingExpected) === 1 ? 'is' : 'are' }} missing a column the app depends on even though
                their migration is recorded as applied, an <span class="text-red-200 ak-red">edited-after-applied</span>
                migration (a recorded migration was later changed to add columns, so Laravel never re-ran it and
                <code class="px-1 py-0.5 rounded bg-black/30 text-red-200 ak-red">migrate:status</code> still shows 0 pending).
                Pages that read these columns will return errors until it's fixed.
                @php
                    $columnDriftOnly = collect($missingExpected)->every(fn ($m) => empty($m['table_missing']));
                @endphp
                @if($columnDriftOnly)
                    Click <span class="text-red-200 font-semibold ak-red">Fix now</span> to add and backfill the missing
                    columns in place, or run
                    <code class="px-1 py-0.5 rounded bg-black/30 text-red-200 ak-red">php artisan migrate --force</code>
                    against production.
                @else
                    Some entries are whole missing tables that need a full migration, run
                    <code class="px-1 py-0.5 rounded bg-black/30 text-red-200 ak-red">php artisan migrate --force</code>
                    against production. Fix now will repair any missing columns it can.
                @endif
            </p>
            <div class="mt-3 flex items-center gap-3">
                <form method="POST" action="{{ route('admin.schema.repair-expected-columns') }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-red-500/20 hover:bg-red-500/30 text-red-200 border border-red-500/40 transition ak-red"
                        onclick="this.disabled=true; this.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Repairing…'; this.form.submit();">
                        <i class="fas fa-wrench"></i> Fix now
                    </button>
                </form>
                <a href="{{ route('admin.schema.repair-audits') }}"
                   class="inline-flex items-center gap-2 text-xs text-red-300/80 hover:text-red-200 underline ak-red">
                    <i class="fas fa-clock-rotate-left"></i> View repair history
                </a>
            </div>
            <details class="mt-3">
                <summary class="text-xs text-red-300/80 cursor-pointer select-none ak-red">Show affected tables</summary>
                <ul class="mt-2 space-y-1 text-xs text-white/50 font-mono ak-muted">
                    @foreach(array_slice($missingExpected, 0, 25) as $m)
                        <li>{{ $m['table'] }} &mdash; {{ !empty($m['table_missing']) ? 'entire table missing' : implode(', ', $m['columns']) }}</li>
                    @endforeach
                    @if(count($missingExpected) > 25)
                        <li class="text-white/40 ak-note">…and {{ count($missingExpected) - 25 }} more</li>
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
            <i class="fas fa-hard-drive text-amber-400 text-lg ak-amber"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-amber-300 ak-amber">Analytics storage is growing unbounded</h2>
            <p class="text-sm text-white/70 mt-1 ak-strong">
                A high-volume analytics table has crossed the alert threshold of
                <span class="font-mono text-amber-200 ak-amber">{{ number_format($statsStorage['alert_threshold']) }}</span> rows and
                nothing will prune it &mdash; {{ $statsStorage['reason'] }}. Set a hard cap to bound storage.
            </p>
            <div class="mt-3">
                <a href="{{ route('admin.stats-storage.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 border border-amber-500/40 transition ak-amber">
                    <i class="fas fa-sliders"></i> Review analytics storage
                </a>
            </div>
        </div>
    </div>
</div>
@endif

@if(!empty($templateGalleryHealth['available']) && !empty($templateGalleryHealth['has_gaps']))
@php
    $tghEmpty     = !empty($templateGalleryHealth['empty']);
    $tghUncovered = $templateGalleryHealth['uncovered'] ?? [];
    $tghGated     = $templateGalleryHealth['gated'] ?? [];
    $tghNames     = collect($tghUncovered)->pluck('label')->filter()->values();
    $tghGatedNames= collect($tghGated)->pluck('label')->filter()->values();
@endphp
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-amber-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-layer-group text-amber-400 text-lg ak-amber"></i>
        </div>
        <div class="min-w-0">
            @if($tghEmpty)
                <h2 class="text-base font-semibold text-amber-300 ak-amber">The onboarding template gallery is empty</h2>
                <p class="text-sm text-white/70 mt-1 ak-strong">
                    There are <span class="text-amber-200 font-semibold ak-amber">no active page templates</span>, so the
                    new-user onboarding wizard silently degrades to its "No templates available yet" escape and new
                    users land on a bare setup screen. Add or re-activate at least one template so onboarding can
                    offer a starting point again.
                </p>
            @else
                <h2 class="text-base font-semibold text-amber-300 ak-amber">
                    {{ $tghNames->count() === 1 ? 'A persona has' : 'Some personas have' }} no recommended templates
                </h2>
                <p class="text-sm text-white/70 mt-1 ak-strong">
                    These {{ $tghNames->count() === 1 ? 'persona has' : 'personas have' }}
                    <span class="text-amber-200 font-semibold ak-amber">no active recommended page templates</span>:
                    <span class="text-amber-200 font-semibold ak-amber">{{ $tghNames->join(', ', ' and ') }}</span>.
                    New users who pick {{ $tghNames->count() === 1 ? 'that persona' : 'those personas' }} in
                    onboarding get no tailored "Recommended for you" row, only the generic browse-all list. Add a
                    template (or tag an existing one) for each so onboarding can recommend a starting point.
                </p>
            @endif
            @if($tghGatedNames->isNotEmpty())
                <p class="text-xs text-white/50 mt-2 ak-muted">
                    Also worth noting, every recommended template is locked behind a paid tier for:
                    <span class="text-white/70 ak-strong">{{ $tghGatedNames->join(', ', ' and ') }}</span>, so entry-level
                    users see an all-locked recommended row.
                </p>
            @endif
            @php
                // Carry the uncovered persona slug(s) so the templates admin can
                // pre-filter (Manage) or pre-check them (Add) — one-click coverage.
                $tghCoverSlugs = collect($tghUncovered)->pluck('slug')->filter()->values()->all();
                $tghCoverParam = !$tghEmpty && !empty($tghCoverSlugs) ? implode(',', $tghCoverSlugs) : null;
            @endphp
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('admin.templates.index', array_filter(['tab' => 'page', 'cover' => $tghCoverParam])) }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 border border-amber-500/40 transition ak-amber">
                    <i class="fas fa-layer-group"></i> Manage templates
                </a>
                <a href="{{ route('admin.templates.create', array_filter(['kind' => 'page', 'persona' => $tghCoverParam])) }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-white/5 hover:bg-white/10 text-white/80 border border-white/10 transition ak-strong">
                    <i class="fas fa-plus"></i> Add a template
                </a>
            </div>
        </div>
    </div>
</div>
@endif

@if(!empty($bgTemplateHealth['available']) && !empty($bgTemplateHealth['shortage']))
@php
    $bgEmpty  = !empty($bgTemplateHealth['empty']);
    $bgActive = $bgTemplateHealth['active'] ?? 0;
    $bgFloor  = $bgTemplateHealth['floor'] ?? 50;
@endphp
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-amber-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-image text-amber-400 text-lg ak-amber"></i>
        </div>
        <div class="min-w-0">
            @if($bgEmpty)
                <h2 class="text-base font-semibold text-amber-300 ak-amber">The background template library is empty</h2>
                <p class="text-sm text-white/70 mt-1 ak-strong">
                    There are <span class="text-amber-200 font-semibold ak-amber">no active biolink background templates</span>,
                    so the biolink editor's Appearance &rarr; Page background &rarr; Template picker is silently
                    showing "No templates available yet" to every user. Add or re-activate templates (or re-run the
                    background template seeder) so the picker offers choices again.
                </p>
            @else
                <h2 class="text-base font-semibold text-amber-300 ak-amber">The background template library is running low</h2>
                <p class="text-sm text-white/70 mt-1 ak-strong">
                    Only <span class="font-mono text-amber-200 ak-amber">{{ number_format($bgActive) }}</span> biolink background
                    template{{ $bgActive === 1 ? ' is' : 's are' }} active &mdash; below the expected floor of
                    <span class="font-mono text-amber-200 ak-amber">{{ number_format($bgFloor) }}</span>. This usually means a
                    bulk wipe or deactivation. The Appearance &rarr; Page background &rarr; Template picker still works
                    but offers users far fewer choices than intended; add or re-activate templates to restore the library.
                </p>
            @endif
            <div class="mt-3">
                <a href="{{ route('admin.bg-templates.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 border border-amber-500/40 transition ak-amber">
                    <i class="fas fa-image"></i> Manage background templates
                </a>
            </div>
        </div>
    </div>
</div>
@endif

@if(!empty($zioBrowserHealth))
@php
    $zbSince   = $zioBrowserHealth['failing_since'] ?? null;
    $zbSuccess = $zioBrowserHealth['last_success_at'] ?? null;
    $zbVersion = $zioBrowserHealth['version'] ?? null;
    try { $zbSinceHuman = $zbSince ? \Carbon\Carbon::parse($zbSince)->diffForHumans() : null; } catch (\Throwable $e) { $zbSinceHuman = null; }
    try { $zbSuccessHuman = $zbSuccess ? \Carbon\Carbon::parse($zbSuccess)->diffForHumans() : null; } catch (\Throwable $e) { $zbSuccessHuman = null; }
@endphp
<div class="mb-8 rounded-2xl p-5 border" style="border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.08);">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 bg-amber-500/15 rounded-xl flex items-center justify-center">
            <i class="fas fa-download text-amber-400 text-lg ak-amber"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-amber-300 ak-amber">SayZio Browser download links are going stale</h2>
            <p class="text-sm text-white/70 mt-1 ak-strong">
                The scheduled release refresh has been <span class="text-amber-200 ak-amber">failing continuously{{ $zbSinceHuman ? ' since ' . $zbSinceHuman : '' }}</span>,
                so the public /download page keeps serving the last-known release{{ $zbVersion ? ' (v' . $zbVersion . ')' : '' }}.
                Visitors still get working installer links, but they fall further behind every release that ships.
                @if($zbSuccessHuman)
                    Last successful refresh: <span class="text-amber-200 ak-amber">{{ $zbSuccessHuman }}</span>.
                @endif
                Check the GitHub release tag/asset names, then re-run the job. This banner clears automatically once a refresh succeeds.
            </p>
            <div class="mt-3">
                <a href="{{ route('admin.cron-jobs.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 border border-amber-500/40 transition ak-amber">
                    <i class="fas fa-clock"></i> Open Scheduled Jobs
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
            <i class="fas fa-inbox text-amber-400 text-lg ak-amber"></i>
        </div>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-amber-300 ak-amber">Leads are arriving but no one is being notified</h2>
            <p class="text-sm text-white/70 mt-1 ak-strong">
                No contact recipient email is configured, so callback, WhatsApp and email
                requests are saved to the Contact Inbox but <span class="text-amber-200 ak-amber">no notification is sent</span>.
                @if(!empty($contactRecipientHealth['total_leads']))
                    <span class="font-mono text-amber-200 ak-amber">{{ number_format($contactRecipientHealth['total_leads']) }}</span>
                    lead{{ $contactRecipientHealth['total_leads'] === 1 ? ' has' : 's have' }} already arrived
                    @if(!empty($contactRecipientHealth['pending_leads']))
                        (<span class="font-mono text-amber-200 ak-amber">{{ number_format($contactRecipientHealth['pending_leads']) }}</span> still unread)
                    @endif
                    &mdash; set a recipient so future leads reach someone.
                @else
                    Set a recipient so leads reach someone.
                @endif
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('admin.site-pages.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 border border-amber-500/40 transition ak-amber">
                    <i class="fas fa-envelope"></i> Set contact recipient
                </a>
                <a href="{{ route('admin.contact-inbox.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold bg-white/5 hover:bg-white/10 text-white/80 border border-white/10 transition ak-strong">
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
                <p class="text-sm text-white/40 ak-note">Total Users</p>
                <p class="text-2xl font-bold text-white mt-1 ak-strong">{{ number_format($stats['total_users']) }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-blue-400 text-lg ak-blue"></i>
            </div>
        </div>
        <p class="text-xs text-emerald-400 mt-3 ak-green"><i class="fas fa-arrow-up mr-1"></i>{{ $stats['users_today'] }} today</p>
    </div>

    <div class="glass rounded-2xl p-6 border border-white/10 ">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white/40 ak-note">Active Users</p>
                <p class="text-2xl font-bold text-white mt-1 ak-strong">{{ number_format($stats['active_users']) }}</p>
            </div>
            <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-user-check text-emerald-400 text-lg ak-green"></i>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl p-6 border border-white/10 ">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white/40 ak-note">Staff Members</p>
                <p class="text-2xl font-bold text-white mt-1 ak-strong">{{ number_format($stats['total_staff']) }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-user-shield text-blue-400 text-lg ak-blue"></i>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl p-6 border border-white/10 ">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white/40 ak-note">This Month</p>
                <p class="text-2xl font-bold text-white mt-1 ak-strong">{{ number_format($stats['users_this_month']) }}</p>
            </div>
            <div class="w-12 h-12 bg-amber-500/10 rounded-xl flex items-center justify-center">
                <i class="fas fa-calendar text-amber-400 text-lg ak-amber"></i>
            </div>
        </div>
    </div>
</div>

<div class="glass rounded-2xl border border-white/10 ">
    <div class="p-6 border-b border-white/10">
        <h2 class="text-lg font-semibold text-white ak-strong">Recent Users</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-white/5">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase ak-note">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase ak-note">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase ak-note">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase ak-note">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($stats['recent_users'] as $user)
                <tr class="hover:bg-white/5">
                    <td class="px-6 py-4 text-sm text-white ak-strong">{{ $user->name }}</td>
                    <td class="px-6 py-4 text-sm text-white/40 ak-note">{{ $user->email }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $user->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 ak-green' : 'bg-red-500/10 text-red-400 ak-red' }}">
                            {{ ucfirst($user->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-white/40 ak-note">{{ $user->created_at->diffForHumans() }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-white/30 ak-note">No users yet</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
