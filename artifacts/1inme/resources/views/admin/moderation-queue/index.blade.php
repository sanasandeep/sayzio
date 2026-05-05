@extends('admin.layouts.app')

@section('title', 'Moderation queue')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="flex items-end justify-between mb-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">Moderation queue</h1>
            <p class="text-sm text-slate-500">User reports + DMCA takedowns. Coalesced reports float up.</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex border-b border-slate-200 mb-4 text-sm font-semibold">
        <a href="{{ route('admin.moderation-queue.index', ['tab' => 'reports']) }}"
           class="px-4 py-2 -mb-px {{ $tab === 'reports' ? 'border-b-2 border-violet-600 text-violet-700' : 'text-slate-600 hover:text-slate-900' }}">
            Reports
            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] bg-rose-100 text-rose-700">{{ $pendingCounts['reports'] }}</span>
        </a>
        <a href="{{ route('admin.moderation-queue.index', ['tab' => 'dmca']) }}"
           class="px-4 py-2 -mb-px {{ $tab === 'dmca' ? 'border-b-2 border-violet-600 text-violet-700' : 'text-slate-600 hover:text-slate-900' }}">
            DMCA
            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] bg-rose-100 text-rose-700">{{ $pendingCounts['dmca'] }}</span>
        </a>
    </div>

    @if(session('success'))
        <div class="mb-3 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-2 mb-4 text-sm">
        <input type="hidden" name="tab" value="{{ $tab }}"/>
        <select name="status" class="px-3 py-2 rounded-lg border border-slate-200 bg-white">
            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All statuses</option>
            @if($tab === 'dmca')
                @foreach($dmcaStatuses as $k => $label)
                    <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            @else
                @foreach($statuses as $k => $label)
                    <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            @endif
        </select>
        @if($tab === 'reports')
            <select name="type" class="px-3 py-2 rounded-lg border border-slate-200 bg-white">
                <option value="">All targets</option>
                @foreach($reportTypes as $k => $label)
                    <option value="{{ $k }}" {{ $type === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="reason" class="px-3 py-2 rounded-lg border border-slate-200 bg-white">
                <option value="">All reasons</option>
                @foreach($reasons as $k => $label)
                    <option value="{{ $k }}" {{ $reason === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        @endif
        <input type="text" name="q" value="{{ $search }}" placeholder="Search…" class="flex-1 min-w-[220px] px-3 py-2 rounded-lg border border-slate-200 bg-white"/>
        <button class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold">Filter</button>
    </form>

    {{-- Table --}}
    @if($tab === 'reports')
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                    <tr><th class="px-4 py-2">Target</th><th>Reason</th><th>Reporter</th><th>Reports</th><th>Status</th><th>When</th><th class="text-right pr-4">Actions</th></tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr class="border-t border-slate-100 align-top">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ $r->target_label }}</div>
                            <div class="text-xs text-slate-500">{{ $reportTypes[$r->target_type] ?? $r->target_type }}</div>
                            @if(!empty($r->comment))
                                <div class="mt-1 text-xs text-slate-700 italic line-clamp-2">"{{ $r->comment }}"</div>
                            @endif
                        </td>
                        <td><span class="inline-block px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[11px]">{{ $reasons[$r->reason] ?? $r->reason }}</span></td>
                        <td class="text-xs">
                            @if($r->reporter_user_id)
                                user #{{ $r->reporter_user_id }}
                            @else
                                <span class="text-slate-500">anon</span>
                            @endif
                        </td>
                        <td>×{{ $r->coalesced_count }}</td>
                        <td>
                            <span class="inline-block px-2 py-0.5 rounded-full text-[11px] {{ $r->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $statuses[$r->status] ?? $r->status }}
                            </span>
                        </td>
                        <td class="text-xs text-slate-500">{{ $r->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('admin.moderation-queue.user-reports.act', $r->id) }}" class="inline-flex flex-wrap gap-1 justify-end">
                                @csrf
                                @foreach(['dismiss' => 'Dismiss', 'warn' => 'Warn', 'remove' => 'Remove', 'suspend' => 'Suspend'] as $a => $label)
                                    <button name="action" value="{{ $a }}" class="px-2 py-1 rounded-md border border-slate-200 hover:bg-slate-50 text-xs">{{ $label }}</button>
                                @endforeach
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500 text-sm">No reports match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                    <tr><th class="px-4 py-2">Reporter</th><th>Original</th><th>Infringing</th><th>Status</th><th>When</th><th class="text-right pr-4">Actions</th></tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr class="border-t border-slate-100 align-top">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ $r->reporter_name }}</div>
                            <div class="text-xs text-slate-500">{{ $r->reporter_email }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs"><a href="{{ $r->original_work_url }}" class="text-violet-700 hover:underline break-all" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($r->original_work_url, 50) }}</a></td>
                        <td class="px-4 py-3 text-xs"><a href="{{ $r->infringing_url }}" class="text-rose-700 hover:underline break-all" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($r->infringing_url, 50) }}</a></td>
                        <td><span class="inline-block px-2 py-0.5 rounded-full text-[11px] {{ $r->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">{{ $dmcaStatuses[$r->status] ?? $r->status }}</span></td>
                        <td class="text-xs text-slate-500">{{ $r->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('admin.moderation-queue.dmca.act', $r->id) }}" class="inline-flex flex-wrap gap-1 justify-end">
                                @csrf
                                @foreach(['valid' => 'Valid', 'removed' => 'Remove', 'invalid' => 'Reject', 'counter' => 'Counter'] as $a => $label)
                                    <button name="action" value="{{ $a }}" class="px-2 py-1 rounded-md border border-slate-200 hover:bg-slate-50 text-xs">{{ $label }}</button>
                                @endforeach
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500 text-sm">No DMCA takedowns to review.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-4">{{ $rows->links() }}</div>
</div>
@endsection
