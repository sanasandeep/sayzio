@extends('admin.layouts.app')
@section('title', 'File Scan Queue')
@section('page-title', 'File Scan Queue')

@section('content')
<div class="max-w-6xl">
    <div class="glass rounded-2xl border border-white/10 p-6 mb-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90 ak-strong">Quarantined uploads</h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl ak-muted">
                    Files flagged by the virus + phishing scanner across all workspaces.
                    Use the rescan button to re-check after engine updates, or acknowledge
                    once you've reached out to the affected creator.
                </p>
            </div>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-xl px-4 py-3 border border-white/10 bg-white/[0.02]">
                    <div class="text-[10px] uppercase font-bold tracking-wider text-white/50 ak-muted">Awaiting review</div>
                    <div class="text-2xl font-bold text-rose-400 ak-red">{{ number_format($counts['flagged_pending']) }}</div>
                </div>
                <div class="rounded-xl px-4 py-3 border border-white/10 bg-white/[0.02]">
                    <div class="text-[10px] uppercase font-bold tracking-wider text-white/50 ak-muted">Flagged total</div>
                    <div class="text-2xl font-bold text-white ak-strong">{{ number_format($counts['flagged_total']) }}</div>
                </div>
                <div class="rounded-xl px-4 py-3 border border-white/10 bg-white/[0.02]">
                    <div class="text-[10px] uppercase font-bold tracking-wider text-white/50 ak-muted">Pending scan</div>
                    <div class="text-2xl font-bold text-sky-400 ak-blue">{{ number_format($counts['pending']) }}</div>
                </div>
            </div>
        </div>

        <form method="GET" class="mt-5 flex items-center gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <label class="text-[10px] uppercase font-bold tracking-wider text-white/50 ak-muted">Status</label>
                <select name="status" onchange="this.form.submit()"
                        class="bg-black/30 border border-white/15 rounded-lg px-2.5 py-1.5 text-sm text-white ak-strong">
                    <option value="flagged" @selected($status === 'flagged')>Flagged</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="all"     @selected($status === 'all')>All</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-[10px] uppercase font-bold tracking-wider text-white/50 ak-muted">Review</label>
                <select name="reviewed" onchange="this.form.submit()"
                        class="bg-black/30 border border-white/15 rounded-lg px-2.5 py-1.5 text-sm text-white ak-strong">
                    <option value="pending"  @selected($reviewed === 'pending')>Awaiting review</option>
                    <option value="reviewed" @selected($reviewed === 'reviewed')>Already reviewed</option>
                    <option value="all"      @selected($reviewed === 'all')>All</option>
                </select>
            </div>
        </form>
    </div>

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        @if($files->isEmpty())
            <div class="p-10 text-center text-sm text-white/50 ak-muted">
                <i class="fas fa-shield-check text-3xl text-emerald-400 mb-3 ak-green"></i>
                <div>Nothing in the review queue right now. Nice.</div>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="text-[10px] uppercase tracking-wider text-white/50 bg-white/[0.03] ak-muted">
                    <tr>
                        <th class="text-left px-4 py-3">File</th>
                        <th class="text-left px-4 py-3">Owner</th>
                        <th class="text-left px-4 py-3">Reason</th>
                        <th class="text-left px-4 py-3">Quarantined</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($files as $f)
                        <tr class="border-t border-white/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 align-top">
                                <div class="font-mono text-xs text-white/85 truncate max-w-xs ak-strong" title="{{ $f->original_name }}">
                                    {{ $f->original_name }}
                                </div>
                                <div class="text-[10px] text-white/40 mt-1 ak-note">
                                    {{ strtoupper((string) pathinfo($f->original_name, PATHINFO_EXTENSION) ?: '?') }} ·
                                    {{ $f->size_human }} · {{ $f->mime_type }}
                                </div>
                                @if($f->isHighRiskExtension())
                                    <div class="mt-1 inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded"
                                         style="background: rgba(239,68,68,0.15); color: #fca5a5;">
                                        <i class="fas fa-triangle-exclamation"></i>High-risk type
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="text-xs text-white/85 ak-strong">{{ $f->user->name ?? '—' }}</div>
                                <div class="text-[10px] text-white/40 ak-note">{{ $f->user->email ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded"
                                     style="background: rgba(239,68,68,0.15); color: #fca5a5;">
                                    <i class="fas fa-shield-exclamation"></i>{{ $f->scan_status }}
                                </div>
                                <div class="text-xs text-white/70 mt-1 ak-strong">{{ $reasonLabel($f->scan_reason) }}</div>
                                @if(!empty($f->scan_meta))
                                    <details class="mt-1">
                                        <summary class="text-[10px] text-white/40 cursor-pointer ak-note">Details</summary>
                                        <pre class="text-[10px] text-white/60 mt-1 max-w-xs whitespace-pre-wrap break-all ak-muted">{{ json_encode($f->scan_meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top text-xs text-white/60 ak-muted">
                                {{ optional($f->quarantined_at)->diffForHumans() ?? '—' }}
                                @if($f->scan_admin_reviewed)
                                    <div class="text-[10px] text-emerald-400 mt-1 ak-green"><i class="fas fa-check"></i> Reviewed</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top text-right">
                                <form method="POST" action="{{ route('admin.file-scan-queue.rescan', $f->id) }}" class="inline">@csrf
                                    <button class="px-2.5 py-1 rounded text-[11px] text-white ak-strong" style="background: rgba(56,189,248,0.25); color: #7dd3fc;">
                                        <i class="fas fa-arrows-rotate mr-1"></i>Rescan
                                    </button>
                                </form>
                                @unless($f->scan_admin_reviewed)
                                    <form method="POST" action="{{ route('admin.file-scan-queue.acknowledge', $f->id) }}" class="inline">@csrf
                                        <button class="px-2.5 py-1 rounded text-[11px] text-white ak-strong" style="background: rgba(16,185,129,0.25); color: #6ee7b7;">
                                            <i class="fas fa-check mr-1"></i>Acknowledge
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="p-4 border-t border-white/5">{{ $files->links() }}</div>
        @endif
    </div>
</div>
@endsection
