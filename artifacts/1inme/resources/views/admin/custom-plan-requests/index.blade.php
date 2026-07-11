@extends('admin.layouts.app')

@section('title', 'Custom Plan Requests')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold" style="color:var(--text-main)">Custom Plan Requests</h1>
            <p class="text-sm mt-0.5" style="color:var(--text-muted)">Review and approve negotiated custom plan offers from prospects and users.</p>
        </div>
        @if($newCount > 0)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold" style="background:rgba(59,130,246,0.12);color:#60a5fa;">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span>
                {{ $newCount }} new {{ Str::plural('request', $newCount) }}
            </span>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-5 p-3.5 rounded-xl text-sm font-medium" style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);color:#6ee7b7;">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="mb-5 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search name, email, company…"
                   class="admin-input w-full text-sm">
        </div>
        <div>
            <select name="status" class="admin-input text-sm">
                <option value="">All statuses</option>
                @foreach($statuses as $key => $info)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $info['label'] }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="admin-btn-sm admin-btn-primary text-sm">Filter</button>
        @if(request()->anyFilled(['search','status']))
            <a href="{{ route('admin.custom-plan-requests.index') }}" class="admin-btn-sm admin-btn-secondary text-sm">Clear</a>
        @endif
    </form>

    <div class="admin-card overflow-hidden">
        @if($requests->isEmpty())
            <div class="text-center py-16" style="color:var(--text-muted)">
                <i class="fas fa-file-contract text-3xl mb-3 opacity-40"></i>
                <p class="text-sm">No custom plan requests found.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom:1px solid var(--border-subtle)">
                        <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:var(--text-faint)">Requester</th>
                        <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:var(--text-faint)">Company</th>
                        <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:var(--text-faint)">Cycle</th>
                        <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:var(--text-faint)">Budget</th>
                        <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:var(--text-faint)">Status</th>
                        <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:var(--text-faint)">Submitted</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $req)
                        @php
                            $colorMap = ['new'=>'blue','reviewing'=>'amber','approved'=>'green','paid'=>'purple','declined'=>'red'];
                            $clr = $colorMap[$req->status] ?? 'gray';
                            $bgMap = ['blue'=>'rgba(59,130,246,0.1)','amber'=>'rgba(245,158,11,0.1)','green'=>'rgba(16,185,129,0.1)','purple'=>'rgba(139,92,246,0.1)','red'=>'rgba(239,68,68,0.1)','gray'=>'rgba(107,114,128,0.1)'];
                            $txtMap = ['blue'=>'#60a5fa','amber'=>'#fbbf24','green'=>'#6ee7b7','purple'=>'#c4b5fd','red'=>'#f87171','gray'=>'#9ca3af'];
                        @endphp
                        <tr class="hover:bg-white/[0.02] transition-colors" style="border-bottom:1px solid var(--border-subtle)">
                            <td class="px-4 py-3">
                                <div class="font-medium" style="color:var(--text-main)">{{ $req->name }}</div>
                                <div class="text-xs mt-0.5" style="color:var(--text-muted)">{{ $req->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs" style="color:var(--text-muted)">{{ $req->company ?: '—' }}</td>
                            <td class="px-4 py-3 text-xs capitalize" style="color:var(--text-muted)">{{ $req->preferred_cycle ?: '—' }}</td>
                            <td class="px-4 py-3 text-xs" style="color:var(--text-muted)">{{ $req->budget ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold" style="background:{{ $bgMap[$clr] }};color:{{ $txtMap[$clr] }}">
                                    {{ $req->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs" style="color:var(--text-faint)">{{ $req->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.custom-plan-requests.show', $req) }}" class="admin-btn-sm admin-btn-secondary text-xs">Review</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-4 py-3">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
