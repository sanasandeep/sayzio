@extends('admin.layouts.app')
@section('title', 'Newsletter Subscribers')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="glass rounded-2xl p-6">
        <div class="flex items-start justify-between gap-4 mb-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white mb-1">Newsletter Subscribers</h2>
                <p class="text-sm text-white/50">
                    {{ number_format($totals['active']) }} active · {{ number_format($totals['all']) }} total
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.newsletter.compose') }}"
                   class="px-3 py-2 bg-emerald-500/15 border border-emerald-400/30 hover:bg-emerald-500/25 rounded-lg text-xs text-emerald-100">
                    <i class="fas fa-paper-plane mr-1"></i> Compose &amp; send
                </a>
                <a href="{{ route('admin.newsletter.export') }}"
                   class="px-3 py-2 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-xs text-white">
                    <i class="fas fa-download mr-1"></i> Export CSV
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        <form method="GET" class="mb-4">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search by email…"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-white/40 border-b border-white/10">
                        <th class="py-2 pr-3">Email</th>
                        <th class="py-2 pr-3">Source</th>
                        <th class="py-2 pr-3">Subscribed</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($subscribers as $s)
                        <tr class="text-white/80">
                            <td class="py-2 pr-3 font-mono text-xs text-white">{{ $s->email }}</td>
                            <td class="py-2 pr-3 text-xs text-white/60">{{ $s->source ?: '—' }}</td>
                            <td class="py-2 pr-3 text-xs text-white/60">{{ optional($s->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="py-2 pr-3 text-xs">
                                @if($s->unsubscribed_at)
                                    <span class="px-2 py-0.5 rounded-full bg-white/5 text-white/50">unsubscribed</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-200">active</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-right">
                                <form method="POST" action="{{ route('admin.newsletter.destroy', $s) }}"
                                      onsubmit="return confirm('Delete this subscriber?');" class="inline">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-300 hover:text-red-200"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-white/40 text-sm">No subscribers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $subscribers->links() }}</div>
    </div>
</div>
@endsection
