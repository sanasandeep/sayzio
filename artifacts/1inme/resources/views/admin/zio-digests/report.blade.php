@extends('admin.layouts.app')
@section('title', 'Digest Delivery Report')
@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-white">Delivery report — {{ $digest->title }}</h2>
        <a href="{{ route('admin.zio-digests.index') }}" class="text-xs text-white/60 hover:text-white">
            <i class="fas fa-arrow-left mr-1"></i> Back to digests
        </a>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="glass rounded-2xl p-5">
            <h3 class="text-sm font-semibold text-white mb-2"><i class="fas fa-envelope mr-2 text-white/50"></i>Email ({{ ucfirst($digest->email_status) }})</h3>
            <div class="text-xs text-white/60 space-x-3">
                <span>Queued: <span class="text-white">{{ $digest->email_queued_count }}</span></span>
                <span>Sent: <span class="text-emerald-300">{{ $digest->email_sent_count }}</span></span>
                <span>Failed: <span class="text-red-300">{{ $digest->email_failed_count }}</span></span>
                <span>Skipped: <span class="text-amber-300">{{ $digest->email_skipped_count }}</span></span>
                <span>Unsubscribed: <span class="text-white">{{ $digest->unsubscribed_count }}</span></span>
            </div>
        </div>
        <div class="glass rounded-2xl p-5">
            <h3 class="text-sm font-semibold text-white mb-2"><i class="fab fa-whatsapp mr-2 text-white/50"></i>WhatsApp ({{ ucfirst($digest->wa_status) }})</h3>
            <div class="text-xs text-white/60 space-x-3">
                <span>Queued: <span class="text-white">{{ $digest->wa_queued_count }}</span></span>
                <span>Sent: <span class="text-emerald-300">{{ $digest->wa_sent_count }}</span></span>
                <span>Failed: <span class="text-red-300">{{ $digest->wa_failed_count }}</span></span>
                <span>Skipped (no phone): <span class="text-amber-300">{{ $digest->wa_skipped_count }}</span></span>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-2 text-xs">
        @foreach(['email' => 'Email', 'whatsapp' => 'WhatsApp'] as $ch => $label)
            <a href="{{ route('admin.zio-digests.report', ['digest' => $digest, 'channel' => $ch]) }}"
               class="px-3 py-1.5 rounded-lg border {{ $channel === $ch ? 'bg-white/15 border-white/20 text-white' : 'bg-white/5 border-white/10 text-white/60 hover:text-white' }}">{{ $label }}</a>
        @endforeach
        <span class="mx-2 text-white/20">|</span>
        @foreach([null => 'All', 'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped', 'queued' => 'Queued'] as $st => $label)
            <a href="{{ route('admin.zio-digests.report', array_filter(['digest' => $digest, 'channel' => $channel, 'status' => $st])) }}"
               class="px-3 py-1.5 rounded-lg border {{ $status === $st ? 'bg-white/15 border-white/20 text-white' : 'bg-white/5 border-white/10 text-white/60 hover:text-white' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="glass rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wider text-white/40 border-b border-white/10">
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">{{ $channel === 'email' ? 'Email' : 'Phone' }}</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Error</th>
                    <th class="px-4 py-3">Updated</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                <tr class="border-b border-white/5 text-white/80">
                    <td class="px-4 py-3">{{ $row->user?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-white/60">
                        {{ $channel === 'email' ? ($row->user?->email ?? '—') : ($row->user?->phone ?: $row->user?->mobile ?: '—') }}
                    </td>
                    <td class="px-4 py-3">
                        @php($tone = ['sent' => 'text-emerald-300', 'failed' => 'text-red-300', 'skipped' => 'text-amber-300'][$row->status] ?? 'text-white/60')
                        <span class="text-xs {{ $tone }}">{{ ucfirst($row->status) }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs text-white/50">{{ $row->error ?? '' }}</td>
                    <td class="px-4 py-3 text-xs text-white/40">{{ $row->updated_at?->format('M j, H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-white/40 text-sm">No recipient records for this channel yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $rows->links() }}</div>
</div>
@endsection
