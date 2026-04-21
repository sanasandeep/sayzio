@extends('admin.layouts.app')
@section('title', 'Send Newsletter')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-white">Send a newsletter</h2>
        <a href="{{ route('admin.newsletter.index') }}" class="text-xs text-white/60 hover:text-white">
            <i class="fas fa-arrow-left mr-1"></i> Back to subscribers
        </a>
    </div>

    @if(session('success'))
        <div class="px-3 py-2 bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <form method="POST" action="{{ route('admin.newsletter.send') }}" class="space-y-4"
              onsubmit="return confirm('Send this issue to {{ number_format($activeCount) }} active subscriber(s)?');">
            @csrf

            <div class="text-sm text-white/60">
                This will be queued and delivered to
                <span class="text-white font-medium">{{ number_format($activeCount) }}</span>
                active subscriber{{ $activeCount === 1 ? '' : 's' }} (people who have not unsubscribed).
            </div>

            <div>
                <label class="block text-xs uppercase tracking-wider text-white/40 mb-1">Subject</label>
                <input type="text" name="subject" required maxlength="255"
                       value="{{ old('subject') }}"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                @error('subject')
                    <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs uppercase tracking-wider text-white/40 mb-1">
                    Body (HTML allowed)
                </label>
                <textarea name="body_html" required rows="14"
                          class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white font-mono"
                          placeholder="<h1>Hello!</h1>&#10;<p>What's new this month…</p>">{{ old('body_html') }}</textarea>
                <p class="mt-1 text-[11px] text-white/40">
                    Plain text is fine too — basic HTML tags (h1, p, a, ul, strong, em, br) will render in most email clients.
                </p>
                @error('body_html')
                    <p class="mt-1 text-xs text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="{{ route('admin.newsletter.index') }}"
                   class="px-3 py-2 bg-white/5 border border-white/10 hover:bg-white/10 rounded-lg text-xs text-white">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-emerald-500/20 border border-emerald-400/40 hover:bg-emerald-500/30 rounded-lg text-xs text-emerald-100"
                        @if($activeCount === 0) disabled @endif>
                    <i class="fas fa-paper-plane mr-1"></i> Queue &amp; send
                </button>
            </div>
        </form>
    </div>

    <div class="glass rounded-2xl p-6">
        <h3 class="text-sm font-semibold text-white mb-3">Past issues</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-white/40 border-b border-white/10">
                        <th class="py-2 pr-3">Subject</th>
                        <th class="py-2 pr-3">Started</th>
                        <th class="py-2 pr-3">Finished</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3">Delivered</th>
                        <th class="py-2 pr-3">Sent by</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($issues as $issue)
                        <tr class="text-white/80 align-top">
                            <td class="py-2 pr-3 text-white">{{ $issue->subject }}</td>
                            <td class="py-2 pr-3 text-xs text-white/60">
                                {{ optional($issue->sent_at ?? $issue->created_at)->format('Y-m-d H:i') }}
                            </td>
                            <td class="py-2 pr-3 text-xs text-white/60">
                                {{ $issue->finished_at ? $issue->finished_at->format('Y-m-d H:i') : '—' }}
                            </td>
                            <td class="py-2 pr-3 text-xs">
                                @php
                                    $statusClass = match($issue->status) {
                                        'sent'    => 'bg-emerald-500/15 text-emerald-200',
                                        'sending' => 'bg-amber-500/15 text-amber-200',
                                        'failed'  => 'bg-red-500/15 text-red-200',
                                        default   => 'bg-white/5 text-white/60',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 rounded-full {{ $statusClass }}">{{ $issue->status }}</span>
                            </td>
                            <td class="py-2 pr-3 text-xs text-white/60">
                                {{ number_format($issue->sent_count) }} / {{ number_format($issue->recipients_count) }}
                                @if($issue->failed_count > 0)
                                    <span class="text-red-300 ml-1">({{ number_format($issue->failed_count) }} failed)</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-xs text-white/60">{{ $issue->sender_email ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-white/40 text-sm">No issues sent yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $issues->links() }}</div>
    </div>
</div>
@endsection
