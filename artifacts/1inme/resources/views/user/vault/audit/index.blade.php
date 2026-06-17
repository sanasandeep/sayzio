@extends('user.layouts.app')
@section('title', 'Vault — Activity')
@section('content')
@include('user.vault._tabs')

<div class="rounded-xl border border-white/10 overflow-hidden" style="background: var(--bg-card);">
    <table class="min-w-full text-sm">
        <thead class="bg-white/5 text-xs uppercase tracking-wide" style="color: var(--text-faint);">
            <tr>
                <th class="px-4 py-3 text-left">When</th>
                <th class="px-4 py-3 text-left">Actor</th>
                <th class="px-4 py-3 text-left">Action</th>
                <th class="px-4 py-3 text-left">Target</th>
                <th class="px-4 py-3 text-left">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse($items as $a)
                <tr>
                    <td class="px-4 py-2 text-xs" style="color: var(--text-faint);">{{ $a->occurred_at?->toDateTimeString() }}</td>
                    <td class="px-4 py-2">{{ $a->actor?->name ?? '—' }}</td>
                    <td class="px-4 py-2"><span class="px-2 py-0.5 text-[10px] rounded bg-white/10 uppercase">{{ $a->action }}</span></td>
                    <td class="px-4 py-2">{{ $a->target_type }}: {{ $a->target_label }}</td>
                    <td class="px-4 py-2 text-xs" style="color: var(--text-muted);">{{ $a->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-12 text-center" style="color: var(--text-faint);">No vault activity yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@if(!$isAdmin)
    <p class="text-xs mt-3" style="color: var(--text-muted);">Showing your own actions only. Workspace owners and admins see entries from every member.</p>
@endif
@endsection
