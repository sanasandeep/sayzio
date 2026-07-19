@extends('user.layouts.app')

@section('title', 'Email history')

@section('content')
<div class="max-w-4xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Email history',
        'subtitle' => 'The transactional emails we sent you. You can resend invoices and verification emails to yourself if one went missing.',
    ])

    @if (session('success'))
        <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">{{ session('error') }}</div>
    @endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-white/[0.03] text-white/40 text-xs">
                <tr>
                    <th class="text-left font-medium px-4 py-2.5">When</th>
                    <th class="text-left font-medium px-4 py-2.5">Subject</th>
                    <th class="text-left font-medium px-4 py-2.5">Status</th>
                    <th class="text-right font-medium px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse ($logs as $log)
                    <tr class="hover:bg-white/[0.02]">
                        <td class="px-4 py-2.5 text-white/50 whitespace-nowrap">{{ optional($log->created_at)->format('M j, Y H:i') }}</td>
                        <td class="px-4 py-2.5 text-white/80 max-w-sm truncate">{{ $log->subject ?: '—' }}</td>
                        <td class="px-4 py-2.5">
                            @if ($log->status === 'failed')
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-red-500/15 border border-red-500/25 text-red-300">Failed</span>
                            @else
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/25 text-emerald-300">Sent</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                            @if (in_array($log->email_key, $resendableKeys, true))
                                <form method="POST" action="{{ route('user.emails.resend', $log) }}" class="inline"
                                      onsubmit="return confirm('Resend this email to yourself?');">
                                    @csrf
                                    <button type="submit" class="text-blue-300 hover:text-blue-200 text-xs font-semibold">Resend</button>
                                </form>
                            @else
                                <span class="text-white/20 text-xs">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-white/40">No emails yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
