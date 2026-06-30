@extends('admin.layouts.app')
@section('title', 'Badge Requests')
@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold" style="color: var(--text-primary);">Badge requests</h1>
        <p class="text-sm mt-1" style="color: var(--text-dimmed);">Review and approve account badge requests from users.</p>
    </div>

    @php $isAll = ! in_array($status, ['pending', 'approved', 'rejected'], true); @endphp
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
            @php $active = ($key === 'all' && $isAll) || $status === $key; @endphp
            <a href="{{ route('admin.badge-requests.index', $key === 'all' ? [] : ['status' => $key]) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $active ? 'bg-blue-600 text-white' : '' }}"
               style="{{ $active ? '' : 'border:1px solid var(--border-glass); color: var(--text-dimmed);' }}">
                {{ $label }}@isset($counts[$key])<span class="ml-1 opacity-70">{{ $counts[$key] }}</span>@endisset
            </a>
        @endforeach
    </div>

    @if($requests->isEmpty())
        <div class="text-center py-16 rounded-2xl" style="border:1px solid var(--border-glass); background: var(--bg-card);">
            <i class="fas fa-certificate text-3xl mb-3" style="color: var(--text-faint);"></i>
            <p style="color: var(--text-dimmed);">No badge requests here.</p>
        </div>
    @else
        <div class="rounded-2xl overflow-hidden" style="border:1px solid var(--border-glass);">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--bg-subtle); color: var(--text-dimmed);" class="text-left text-xs uppercase tracking-wider">
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Requested</th>
                        <th class="px-4 py-3">Reason</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $r)
                        @php $c = ['pending' => '#f59e0b', 'approved' => '#10b981', 'rejected' => '#ef4444'][$r->status] ?? '#64748b'; @endphp
                        <tr style="border-top:1px solid var(--border-glass);">
                            <td class="px-4 py-3" style="color: var(--text-primary);">
                                {{ $r->user->name ?? 'User #' . $r->user_id }}
                                <div class="text-xs" style="color: var(--text-faint);">{{ $r->user->email ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3" style="color: var(--text-primary);">
                                {{ $r->requestedLabel() }}
                                @if(!$r->account_badge_id)<span class="ml-1 text-[10px] px-1.5 py-0.5 rounded" style="background: rgba(245,158,11,0.15); color:#f59e0b;">custom</span>@endif
                            </td>
                            <td class="px-4 py-3 max-w-xs" style="color: var(--text-dimmed);">
                                @if(filled($r->reason))
                                    <span title="{{ $r->reason }}">{{ \Illuminate\Support\Str::limit($r->reason, 80) }}</span>
                                @else
                                    <span style="color: var(--text-faint);">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold text-white" style="background: {{ $c }};">{{ ucfirst($r->status) }}</span>
                            </td>
                            <td class="px-4 py-3" style="color: var(--text-faint);">{{ $r->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.badge-requests.review', $r->id) }}" class="text-blue-400 hover:underline font-semibold text-xs">Review</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
