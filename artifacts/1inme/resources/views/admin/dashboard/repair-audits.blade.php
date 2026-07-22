@extends('admin.layouts.app')
@section('title', 'Schema repair audit log')
@section('page-title', 'Schema repair audit log')

@section('content')
<div class="space-y-6">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-lg font-semibold text-white ak-strong">Schema repair audit log</h2>
            <p class="text-sm text-white/50 max-w-3xl ak-muted">
                Every run of the dashboard's one-click <span class="text-white/70 font-medium ak-strong">Fix now</span>
                schema repair, who ran it, when, and exactly which columns were
                added/backfilled per table. Whole-missing tables the repair could
                not recreate are listed too (those still need
                <code class="px-1 py-0.5 rounded bg-black/30 text-white/70 ak-strong">php artisan migrate --force</code>).
                Only schema metadata is recorded, never row data.
            </p>
        </div>
        <a href="{{ route('admin.dashboard') }}"
           class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white/80 text-sm font-medium whitespace-nowrap ak-strong">
            <i class="fas fa-arrow-left mr-1"></i> Back to dashboard
        </a>
    </div>

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase ak-note">When (IST)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase ak-note">Who</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase ak-note">Columns added</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase ak-note">Could not repair</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase ak-note">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($audits as $audit)
                    <tr class="hover:bg-white/5 align-top">
                        <td class="px-6 py-4 text-sm text-white/80 whitespace-nowrap ak-strong">
                            {{ optional(\App\Support\PlatformTimezone::display($audit->created_at))->toDayDateTimeString() }}
                            <div class="text-xs text-white/40 ak-note">{{ optional($audit->created_at)->diffForHumans() }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-white/80 ak-strong">
                            <div class="font-medium">{{ $audit->actorLabel() }}</div>
                            @if($audit->actor_email)
                                <div class="text-xs text-white/40 ak-note">{{ $audit->actor_email }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-white/70 ak-strong">
                            @if($audit->added_columns_count > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-300 mb-2 ak-green">
                                    {{ $audit->added_columns_count }} {{ \Illuminate\Support\Str::plural('column', $audit->added_columns_count) }}
                                    across {{ $audit->added_tables_count }} {{ \Illuminate\Support\Str::plural('table', $audit->added_tables_count) }}
                                </span>
                                <ul class="space-y-1 text-xs text-white/50 font-mono ak-muted">
                                    @foreach(($audit->added ?? []) as $table => $columns)
                                        <li>{{ $table }} &mdash; {{ implode(', ', $columns) }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="text-xs text-white/40 ak-note">No changes (already up to date)</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-white/70 ak-strong">
                            @if($audit->unrepairable_count > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-300 mb-2 ak-red">
                                    {{ $audit->unrepairable_count }} {{ \Illuminate\Support\Str::plural('table', $audit->unrepairable_count) }}
                                </span>
                                <ul class="space-y-1 text-xs text-white/50 font-mono ak-muted">
                                    @foreach(($audit->unrepairable ?? []) as $table)
                                        <li>{{ $table }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="text-xs text-white/30 ak-note">&mdash;</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-xs text-white/40 font-mono whitespace-nowrap ak-note">{{ $audit->ip ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-sm text-white/40 ak-note">
                            <i class="fas fa-wrench text-2xl text-white/20 mb-3 block ak-note"></i>
                            No schema repairs have been run yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($audits->hasPages())
        <div>{{ $audits->links() }}</div>
    @endif
</div>
@endsection
