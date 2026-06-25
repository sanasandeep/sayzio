@extends('admin.layouts.app')
@section('title', 'Privacy Requests')
@section('content')
@php
    $statusStyles = [
        'pending_verification' => 'bg-amber-500/20 text-amber-300',
        'verified'             => 'bg-blue-500/20 text-blue-300',
        'approved'             => 'bg-sky-500/20 text-sky-300',
        'processing'           => 'bg-sky-500/20 text-sky-300',
        'completed'            => 'bg-emerald-500/20 text-emerald-300',
        'rejected'             => 'bg-gray-500/20 text-gray-400',
        'failed'               => 'bg-red-500/20 text-red-300',
        'blocked'              => 'bg-red-500/20 text-red-300',
    ];
    $statusLabel = fn ($s) => ucwords(str_replace('_', ' ', $s));
@endphp
<div class="max-w-6xl mx-auto space-y-6">
    @if(session('status'))
        <div class="rounded-xl px-4 py-3 text-sm bg-emerald-500/10 border border-emerald-500/30 text-emerald-300">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl px-4 py-3 text-sm bg-red-500/10 border border-red-500/30 text-red-300">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
                <h2 class="text-lg font-semibold text-white">Privacy Requests</h2>
                <p class="text-xs text-white/50 mt-1">GDPR / CCPA account deletion & data export requests.</p>
            </div>
            <form method="GET" class="flex items-center gap-2">
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search email…"
                       class="px-3 py-1.5 rounded-lg text-xs bg-white/5 border border-white/10 text-white w-44">
                <select name="type" class="px-3 py-1.5 rounded-lg text-xs bg-white/5 border border-white/10 text-white">
                    <option value="all" @selected($filters['type']==='all')>All types</option>
                    <option value="deletion" @selected($filters['type']==='deletion')>Deletion</option>
                    <option value="export" @selected($filters['type']==='export')>Export</option>
                </select>
                <button class="px-3 py-1.5 rounded-lg text-xs bg-blue-600 text-white hover:bg-blue-700">Filter</button>
            </form>
        </div>

        <div class="flex flex-wrap items-center gap-2 mb-5">
            @php
                $statusFilters = ['all'=>'All','pending_verification'=>'Pending','verified'=>'Verified','approved'=>'Approved','processing'=>'Processing','completed'=>'Completed','rejected'=>'Rejected','failed'=>'Failed','blocked'=>'Blocked'];
            @endphp
            @foreach($statusFilters as $key=>$label)
                <a href="{{ route('admin.privacy-requests.index', array_merge($filters, ['status'=>$key])) }}"
                   class="px-3 py-1.5 rounded-lg text-xs {{ $filters['status']===$key ? 'bg-blue-600 text-white' : 'bg-white/5 text-white/70 hover:bg-white/10' }}">
                    {{ $label }}@if($key!=='all' && isset($counts[$key])) <span class="opacity-60">({{ $counts[$key] }})</span>@endif
                </a>
            @endforeach
        </div>

        @if($requests->count() === 0)
            <div class="text-center text-white/40 py-12 text-sm">No privacy requests found.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-white/40 text-xs uppercase tracking-wider border-b border-white/10">
                            <th class="py-2 pr-4">Type</th>
                            <th class="py-2 pr-4">Email</th>
                            <th class="py-2 pr-4">Account</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Submitted</th>
                            <th class="py-2 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $r)
                            <tr class="border-b border-white/5 hover:bg-white/[0.02]">
                                <td class="py-3 pr-4">
                                    <span class="inline-flex items-center gap-1.5 text-white/90">
                                        <i class="fas {{ $r->isDeletion() ? 'fa-user-slash text-red-300' : 'fa-download text-blue-300' }} text-xs"></i>
                                        {{ $r->typeLabel() }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-white/80">{{ $r->email }}</td>
                                <td class="py-3 pr-4 text-white/50">{{ $r->user_id ? '#'.$r->user_id : '—' }}</td>
                                <td class="py-3 pr-4">
                                    <span class="text-[10px] px-2 py-0.5 rounded-full {{ $statusStyles[$r->status] ?? 'bg-white/10 text-white/60' }}">
                                        {{ $statusLabel($r->status) }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-white/50 text-xs">{{ $r->created_at?->format('M j, Y g:i a') }}</td>
                                <td class="py-3 pr-4 text-right">
                                    <a href="{{ route('admin.privacy-requests.show', $r->id) }}"
                                       class="px-3 py-1.5 rounded-lg text-xs bg-white/5 text-white/80 hover:bg-white/10">Review</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
@endsection
