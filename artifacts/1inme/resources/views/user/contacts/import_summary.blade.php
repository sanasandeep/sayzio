@extends('user.layouts.app')

@section('title', 'Import results')

@section('content')
<div class="max-w-4xl mx-auto" data-import-id="{{ $import->id }}" data-status-url="{{ route('user.contacts.import.status', $import) }}">
    @php
        $failedCount = count($import->failed ?? []);
        $heroChips = [
            ['icon' => 'fa-list text-cyan-400',     'text' => $import->total_rows . ' rows in file'],
            ['icon' => 'fa-check text-emerald-400', 'text' => $import->created_count . ' added'],
            ['icon' => 'fa-times text-red-400',     'text' => $failedCount . ' failed'],
        ];
        $heroSubtitle = match ($import->status) {
            'pending', 'processing' => 'Your import is running in the background — you can leave this page and come back any time.',
            'failed'                => 'The import stopped before finishing. Already-added contacts are kept.',
            default                 => 'Here\'s what happened with the file you uploaded.',
        };
    @endphp

    @include('user.partials.page-hero', [
        'title' => 'Import results',
        'subtitle' => $heroSubtitle,
        'icon' => 'fa-clipboard-check',
        'chips' => $heroChips,
    ])

    @if($import->isInProgress())
        <div id="importProgressCard" class="card-premium p-5 mb-6" style="border:1px solid rgba(61,107,255,.30); background:linear-gradient(135deg,rgba(61,107,255,.08),rgba(236,72,153,.06));">
            <div class="flex items-center justify-between mb-3">
                <div class="text-sm font-bold" style="color:var(--text-primary);">
                    <i class="fas fa-spinner fa-spin text-indigo-400 mr-1.5"></i>
                    Importing in the background
                </div>
                <div class="text-xs font-mono" id="importProgressPct" style="color:var(--text-muted);">{{ $import->progressPercent() }}%</div>
            </div>
            <div class="w-full h-2 rounded-full overflow-hidden" style="background:rgba(255,255,255,.06);">
                <div id="importProgressBar" class="h-full transition-all" style="width: {{ $import->progressPercent() }}%; background:linear-gradient(135deg,#3d6bff,#ec4899);"></div>
            </div>
            <div class="text-[11px] mt-2" style="color:var(--text-faint);">
                <span id="importProgressText">{{ $import->processed_rows }} of {{ $import->total_rows }} rows processed</span>
                — this page refreshes automatically.
            </div>
        </div>
    @elseif($import->status === 'failed')
        <div class="mb-6 px-4 py-3 rounded-xl text-sm" style="background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5;">
            <i class="fas fa-exclamation-triangle mr-1.5"></i>
            Import failed: {{ $import->error ?: 'Unknown error.' }}
        </div>
    @endif

    @if($import->skipped_cap_count > 0)
        <div class="mb-6 px-4 py-3 rounded-xl text-sm" style="background: rgba(245,158,11,0.10); border: 1px solid rgba(245,158,11,0.25); color: #f59e0b;">
            <i class="fas fa-exclamation-triangle mr-1.5"></i>
            {{ $import->skipped_cap_count }} row(s) were skipped because the contact cap was reached. Free up space and re-upload them.
        </div>
    @endif

    @if($import->status === 'completed' && ($duplicateCount ?? 0) > 0)
    <div class="mb-6 px-4 py-3 rounded-xl flex items-center justify-between gap-3 flex-wrap"
         style="background:linear-gradient(135deg,rgba(245,158,11,.08),rgba(61,107,255,.08));border:1px solid rgba(245,158,11,.25);">
        <div class="flex items-center gap-2">
            <i class="fas fa-copy text-amber-400"></i>
            <span class="text-sm font-semibold" style="color:var(--text-primary);">
                {{ $duplicateCount }} duplicate {{ \Illuminate\Support\Str::plural('group', $duplicateCount) }} detected
            </span>
            <span class="text-xs" style="color:var(--text-muted);">— new contacts may match existing ones.</span>
        </div>
        <a href="{{ route('user.contacts.duplicates') }}"
           class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
           style="background:linear-gradient(135deg,#f59e0b,#ec4899);">
            <i class="fas fa-code-merge mr-1"></i> Review &amp; Merge
        </a>
    </div>
    @endif

    <div class="card-premium p-5 mb-6">
        <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Summary</h3>
        <div class="grid grid-cols-3 gap-4 text-center">
            <div>
                <div class="text-2xl font-bold" style="color:var(--text-primary);" id="sumTotal">{{ $import->total_rows }}</div>
                <div class="text-[11px]" style="color:var(--text-muted);">Rows in file</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-emerald-400" id="sumCreated">{{ $import->created_count }}</div>
                <div class="text-[11px]" style="color:var(--text-muted);">Contacts added</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-red-400" id="sumFailed">{{ $failedCount }}</div>
                <div class="text-[11px]" style="color:var(--text-muted);">Failed rows</div>
            </div>
        </div>
        @if($import->original_filename)
            <div class="text-[11px] mt-4" style="color:var(--text-faint);">
                Source file: <span class="font-mono" style="color:var(--text-muted);">{{ $import->original_filename }}</span>
                @if($import->completed_at)
                    · finished {{ $import->completed_at->diffForHumans() }}
                @endif
            </div>
        @endif
    </div>

    @if($failedCount > 0)
    <div class="card-premium p-5 mb-6">
        <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Failures</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase" style="color:var(--text-faint);">
                        <th class="py-2 pr-3">Row</th>
                        <th class="py-2 pr-3">Name</th>
                        <th class="py-2">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($import->failed as $f)
                        <tr style="border-top:1px solid rgba(255,255,255,.06);">
                            <td class="py-2 pr-3 font-mono text-xs" style="color:var(--text-muted);">#{{ $f['row'] ?? '?' }}</td>
                            <td class="py-2 pr-3" style="color:var(--text-primary);">{{ $f['name'] ?? '—' }}</td>
                            <td class="py-2 text-xs" style="color:#fca5a5;">{{ $f['reason'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="flex items-center gap-3">
        <a href="{{ route('user.contacts.index') }}" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
            <i class="fas fa-arrow-left mr-1"></i> Back to contacts
        </a>
        <a href="{{ route('user.contacts.import') }}" class="text-xs" style="color:var(--text-muted);">Import another file</a>
    </div>
</div>

@if($import->isInProgress())
<script>
(function () {
    var root = document.querySelector('[data-import-id="{{ $import->id }}"]');
    if (!root) return;
    var url = root.getAttribute('data-status-url');
    var bar = document.getElementById('importProgressBar');
    var pct = document.getElementById('importProgressPct');
    var txt = document.getElementById('importProgressText');
    var sumCreated = document.getElementById('sumCreated');
    var sumFailed  = document.getElementById('sumFailed');
    var poll = function () {
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (bar) bar.style.width = j.percent + '%';
                if (pct) pct.textContent = j.percent + '%';
                if (txt) txt.textContent = j.processed + ' of ' + j.total + ' rows processed';
                if (sumCreated) sumCreated.textContent = j.created;
                if (sumFailed)  sumFailed.textContent  = j.failed;
                if (!j.in_progress) { window.location.reload(); return; }
                setTimeout(poll, 2500);
            })
            .catch(function () { setTimeout(poll, 5000); });
    };
    setTimeout(poll, 2500);
})();
</script>
@endif
@endsection
