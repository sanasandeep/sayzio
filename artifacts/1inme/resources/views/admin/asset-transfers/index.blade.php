@extends('admin.layouts.app')
@section('title', 'Asset Transfers')
@section('page-title', 'Asset Transfers')

@section('content')
<div class="glass rounded-2xl border border-white/10 p-6">
    <div class="flex items-center justify-between mb-4 gap-2 flex-wrap">
        <div>
            <h3 class="text-white font-semibold ak-strong">Link &amp; workspace transfers</h3>
            <p class="text-xs text-white/40 ak-note">Audit log of admin-granted transfers between accounts. Grant the capability per user from their account page.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.users.asset-transfers.index') }}"
          class="rounded-xl border border-white/5 bg-white/[0.02] p-3 mb-4 flex flex-wrap items-end gap-2">
        <label class="block">
            <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1 ak-note">Search</span>
            <input type="text" name="q" value="{{ $q }}" placeholder="Asset, sender or recipient email"
                   class="w-64 px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-blue-500/40 ak-strong ak-input">
        </label>
        <label class="block">
            <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1 ak-note">Kind</span>
            <select name="kind"
                    class="px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 ak-strong ak-input">
                <option value="">All</option>
                <option value="link" @selected($kind === 'link')>Links</option>
                <option value="workspace" @selected($kind === 'workspace')>Workspaces</option>
            </select>
        </label>
        <button type="submit" class="px-3 py-1.5 rounded-lg bg-blue-500/20 text-blue-200 hover:bg-blue-500/30 text-xs font-medium ak-blue">
            <i class="fas fa-filter mr-1"></i> Apply
        </button>
        @if($q !== '' || $kind !== '')
            <a href="{{ route('admin.users.asset-transfers.index') }}" class="px-3 py-1.5 rounded-lg bg-white/5 text-white/60 hover:bg-white/10 text-xs ak-muted">Clear</a>
        @endif
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[10px] uppercase tracking-wide text-white/40 border-b border-white/10 ak-note">
                    <th class="py-2 pr-3">When</th>
                    <th class="py-2 pr-3">Kind</th>
                    <th class="py-2 pr-3">Asset</th>
                    <th class="py-2 pr-3">From</th>
                    <th class="py-2 pr-3">To</th>
                    <th class="py-2 pr-3">Channel</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $t)
                    <tr class="border-b border-white/5 text-white/80 ak-strong">
                        <td class="py-2 pr-3 whitespace-nowrap text-white/50 ak-muted">{{ $t->created_at?->format('M j, Y H:i') }}</td>
                        <td class="py-2 pr-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide {{ $t->kind === 'workspace' ? 'bg-blue-500/15 text-blue-200 ak-blue' : 'bg-emerald-500/15 text-emerald-200 ak-green' }}">{{ $t->kind }}</span>
                        </td>
                        <td class="py-2 pr-3">{{ $t->asset_label ?: ('#' . $t->asset_id) }} <span class="text-white/30 ak-note">(#{{ $t->asset_id }})</span></td>
                        <td class="py-2 pr-3">
                            @if($t->fromUser)
                                <a href="{{ route('admin.users.show', $t->fromUser) }}" class="text-blue-300 hover:text-blue-200 ak-blue">{{ $t->fromUser->name ?: $t->from_email }}</a>
                            @else
                                {{ $t->from_email }}
                            @endif
                        </td>
                        <td class="py-2 pr-3">
                            @if($t->toUser)
                                <a href="{{ route('admin.users.show', $t->toUser) }}" class="text-blue-300 hover:text-blue-200 ak-blue">{{ $t->toUser->name ?: $t->to_email }}</a>
                            @else
                                {{ $t->to_email }}
                            @endif
                        </td>
                        <td class="py-2 pr-3 text-white/50 ak-muted">{{ $t->channel }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-white/40 ak-note">No transfers recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $transfers->links() }}</div>
</div>
@endsection
