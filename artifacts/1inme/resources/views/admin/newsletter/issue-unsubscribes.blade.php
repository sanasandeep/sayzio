@extends('admin.layouts.app')
@section('title', 'Issue Unsubscribes')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-white ak-strong">Unsubscribes from this issue</h2>
            <p class="text-sm text-white/50 mt-1 ak-muted">
                <span class="text-white ak-strong">{{ $issue->subject }}</span>
                · sent {{ optional($issue->sent_at ?? $issue->created_at)->format('Y-m-d H:i') }}
            </p>
        </div>
        <a href="{{ route('admin.newsletter.compose') }}" class="text-xs text-white/60 hover:text-white ak-muted">
            <i class="fas fa-arrow-left mr-1"></i> Back to issues
        </a>
    </div>

    <div class="glass rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="text-sm text-white/60 ak-muted">
                <span class="text-white font-medium ak-strong">{{ number_format($rows->total()) }}</span>
                subscriber{{ $rows->total() === 1 ? '' : 's' }} opted out using the link in this issue.
                @if(($issue->unsubscribed_count ?? 0) !== $rows->total())
                    <span class="ml-2 text-amber-300 ak-amber">
                        (counter on issue says {{ number_format($issue->unsubscribed_count) }})
                    </span>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-white/40 border-b border-white/10 ak-note">
                        <th class="py-2 pr-3">Email</th>
                        <th class="py-2 pr-3">Source</th>
                        <th class="py-2 pr-3">Unsubscribed at</th>
                        <th class="py-2 pr-3">Current status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($rows as $row)
                        <tr class="text-white/80 ak-strong">
                            <td class="py-2 pr-3 font-mono text-xs text-white ak-strong">
                                {{ $row->subscriber->email ?? '(deleted subscriber)' }}
                            </td>
                            <td class="py-2 pr-3 text-xs text-white/60 ak-muted">
                                {{ optional($row->subscriber)->source ?: '—' }}
                            </td>
                            <td class="py-2 pr-3 text-xs text-white/60 ak-muted">
                                {{ optional($row->unsubscribed_at)->format('Y-m-d H:i') }}
                            </td>
                            <td class="py-2 pr-3 text-xs">
                                @if(!$row->subscriber)
                                    <span class="px-2 py-0.5 rounded-full bg-white/5 text-white/50 ak-muted">deleted</span>
                                @elseif($row->subscriber->unsubscribed_at)
                                    <span class="px-2 py-0.5 rounded-full bg-white/5 text-white/50 ak-muted">unsubscribed</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-200 ak-green">resubscribed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-white/40 text-sm ak-note">
                            No one unsubscribed from this issue.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
